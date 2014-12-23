<?
/**
 * functions used by command line scripts
 *
 * @see inc/common_cli.php
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


/**
 * called by cli/cron.php
 *
 * @param boolean $skip_if_locked (optional)
 */
function cron($skip_if_locked=false) {

	if ($skip_if_locked) {
		if (!cron_lock()) return;
	} else {
		while (!cron_lock()) {
			echo "Waiting for lock ...\n";
			sleep(5);
		}
	}

	$sql_period = "SELECT *,
		debate             <= now() AS debate_now,
		preparation        <= now() AS preparation_now,
		voting             <= now() AS voting_now,
		ballot_assignment  <= now() AS ballot_assignment_now,
		ballot_preparation <= now() AS ballot_preparation_now,
		counting           <= now() AS counting_now
	FROM period";
	// TODO: exclude closed
	$result_period = DB::query($sql_period);
	while ( $period = DB::fetch_object($result_period, "Period") ) {
		/** @var Period $period */
		DB::to_bool($period->debate_now);
		DB::to_bool($period->preparation_now);
		DB::to_bool($period->voting_now);
		DB::to_bool($period->ballot_assignment_now);
		DB::to_bool($period->ballot_preparation_now);
		DB::to_bool($period->counting_now);

		$issues_start_debate = array();
		// collect issues for upload to the ID server
		$issues_start_voting = array();
		$issues_finished_voting = array();

		// ballots
		switch ($period->state) {

			// ballot assignment
		case "ballot_application":
			if (!$period->ballot_assignment_now) break;

			$period->assign_members_to_ballots();

			$sql_ballot = "SELECT * FROM ballot WHERE period=".intval($period->id);
			$result_ballot = DB::query($sql_ballot);
			while ( $ballot = DB::fetch_object($result_ballot, "Ballot") ) {

				// notification to the ballot agents whether the ballot was approved
				// TODO: more than one ballot agent
				$notification = new Notification("ballot_approved");
				$notification->period = $period;
				$notification->ballot = $ballot;
				$sql = "SELECT member FROM offlinevoter WHERE ballot = ".intval($ballot->id)." AND agent = TRUE";
				$recipients = DB::fetchfieldarray($sql);
				$notification->send($recipients);

				if (!$ballot->approved) continue;

				// notification to the members to which ballots they were assigned
				$notification = new Notification("ballot_assigned");
				$notification->period = $period;
				$notification->ballot = $ballot;
				$sql = "SELECT member FROM offlinevoter WHERE ballot = ".intval($ballot->id);
				$recipients = DB::fetchfieldarray($sql);
				$notification->send($recipients);

			}

			$period->state = "ballot_assignment";
			$period->update(["state"]);

			break;

			// ballot preparation
		case "ballot_assignment":
			if (!$period->ballot_preparation_now) break;

			// final upload of the complete postal and ballot voters
			if ( upload_voters($period, true) ) {
				$period->state = "ballot_preparation";
				$period->update(["state"]);
			}

			// ballot_preparation is the final state.
		}


		// proposals and issues
		$sql_issue = "SELECT * FROM issue
			WHERE period=".intval($period->id)."
			AND state NOT IN ('finished', 'cancelled')";
		$result_issue = DB::query($sql_issue);
		while ( $issue = DB::fetch_object($result_issue, "Issue") ) {
			/** @var Issue $issue */

			switch ($issue->state) {

				// debate
			case "entry":
				if (!$period->debate_now) break;

				$all_proposals_revoked = true;
				$admitted_proposals = false;
				$not_admitted_proposals = false;
				foreach ( $issue->proposals() as $proposal ) {
					/** @var Proposal $proposal */
					if ($proposal->state_cancelled()) continue;
					//if ( $proposal->check_proponents() ) {
					$all_proposals_revoked = false;
					if ($proposal->state=="admitted") $admitted_proposals = true; else $not_admitted_proposals = true;
					/*} else {
						// revoke proposals without proponents
						$proposal->state = "revoked";
						$proposal->update(["state"]);
					}*/
				}

				if ($all_proposals_revoked) {
					$issue->cancel();
					break;
				}

				// None of the proposals are admitted yet.
				if (!$admitted_proposals) break;

				if ($not_admitted_proposals) {

					/* split issue
					$new_issue = new Issue;
					$new_issue->area = $issue->area;
					$new_issue->create();
					foreach ( $issue->proposals() as $proposal ) {
						if ($proposal->state_cancelled()) continue;
						if ($proposal->state=="admitted") continue;
						$proposal->issue = $new_issue->id;
						$proposal->update(["issue"]);
					}*/

					// cancel proposals
					foreach ( $issue->proposals() as $proposal ) {
						/** @var $proposal Proposal */
						if ($proposal->state_cancelled()) continue;
						if ($proposal->state=="admitted") continue;
						$proposal->cancel("cancelled_debate");
					}

				}

				$issue->state = "debate";
				$issue->update(["state"], 'debate_started=now()');

				$issues_start_debate[] = $issue;

				break;

				// preparation
			case "debate":
				if (!$period->preparation_now) break;

				// revoke proposals, which were scheduled for revokation
				revoke_before_preparation($issue);
				// don't proceed to preparation if all proposals were cancelled
				if ($issue->state == "cancelled") break;

				$issue->state = "preparation";
				$issue->update(["state"], 'preparation_started=now()');

				break;

				// voting
			case "preparation":
				if (!$period->voting_now) break;

				// collect issues for online voting start
				if ( !$issue->votingmode_offline() ) $issues_start_voting[] = $issue;
				// Issues which reached offline voting, stay in preparation state until an admin enters the voting result.

				break;

				// counting
			case "voting":
				if (!$period->counting_now) break;

				$issue->state = "counting";
				$issue->update(["state"], 'counting_started=now()');

				$issue->counting();
				$issue->finish();

				$issues_finished_voting[] = $issue;

				remove_inactive_participants($period->ngroup);

				break;
				// "finished" and "cancelled" are the final issue states.
			}

		}

		// debate start notifications
		if ($issues_start_debate) {
			$notification = new Notification("debate");
			$notification->period = $period;
			$notification->issues = $issues_start_debate;
			$notification->send();
		}

		// voting finished notifications
		if ($issues_finished_voting) {
			$notification = new Notification("finished");
			$notification->period = $period;
			$notification->issues = $issues_finished_voting;
			$notification->send();
		}

		// start voting and send individual notifications with tokens
		if ($issues_start_voting) $period->start_voting($issues_start_voting);

	}

	// actually only daily tasks
	revoke_not_enough_proponents();
	cancel_not_admitted();
	clear_issues();
	update_activity();

	cron_unlock();
}


/**
 * revoke due proposals, which have have less than required proponents
 */
function revoke_not_enough_proponents() {
	$sql = "SELECT * FROM proposal WHERE revoke < now()";
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		// clear revoke date
		$proposal->revoke = null;
		$proposal->update(['revoke']);
		if (
			// Drafts need just 1 proponent, submitted proposals need the count required for submission.
			$proposal->proponents_count() < ($proposal->state=="draft" ? 1 : REQUIRED_PROPONENTS) and
			// don't revoke already cancelled proposals
			!$proposal->state_cancelled()
		) {
			$proposal->cancel("revoked");
		}
	}
}


/**
 * revoke proposals, which were scheduled for revokation (and still have less than required proponents)
 *
 * @param Issue   $issue
 */
function revoke_before_preparation(Issue $issue) {
	$sql = "SELECT * FROM proposal WHERE issue=".intval($issue->id)." AND revoke NOTNULL";
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		// to set the cancelled state if all proposals get cancelled
		$proposal->set_issue($issue);
		// clear revoke date
		$proposal->revoke = null;
		$proposal->update(['revoke']);
		// don't revoke already cancelled proposals
		if ( !$proposal->state_cancelled() ) {
			$proposal->cancel("revoked");
		}
	}
}


/**
 * cancel proposals, which have not been admitted within the specified period
 */
function cancel_not_admitted() {
	$sql = "SELECT * FROM proposal
		WHERE state='submitted'
			AND submitted < now() - interval ".DB::esc(CANCEL_NOT_ADMITTED_INTERVAL);
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		/** @var Proposal $proposal */
		$proposal->cancel("cancelled_interval");
	}
}


/**
 * clear issues
 */
function clear_issues() {
	$sql = "SELECT * FROM issue WHERE clear <= now()";
	$result = DB::query($sql);
	while ( $issue = DB::fetch_object($result, "Issue") ) {
		// delete raw voting data
		$sql_delete = "DELETE FROM vote_token WHERE issue=".intval($issue->id);
		DB::query($sql_delete);
		$sql_delete = "DELETE FROM votingmode_token WHERE issue=".intval($issue->id);
		DB::query($sql_delete);
		$issue->clear = null;
		$issue->update(["clear"], "cleared=now()");
	}
}


/**
 * remove inactive participants from areas, who's last activation is before the counting of the period before the current one
 *
 * @param integer $ngroup
 */
function remove_inactive_participants($ngroup) {
	$sql = "SELECT counting FROM period
		WHERE ngroup=".intval($ngroup)."
			AND counting <= now()
		ORDER BY counting DESC
		LIMIT 2";
	$result = DB::query($sql);
	DB::fetch_assoc($result); // skip the current counting
	if ( $last_counting = DB::fetch_assoc($result) ) {
		// area participation
		$sql = "DELETE FROM participant
			WHERE area IN (SELECT id FROM area WHERE ngroup=".intval($ngroup).")
				AND activated < ".DB::esc($last_counting['counting']);
		DB::query($sql);
	}
}


/**
 * count recent comments
 */
function update_activity() {
	$sql = "SELECT * FROM proposal WHERE activity!=0 OR state IN ('draft', 'submitted', 'admitted')";
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		$sql = "SELECT COUNT(created) + COUNT(created > now() - interval '3 day') + COUNT(created > now() - interval '1 day') FROM comment
		WHERE proposal=".intval($proposal->id)." AND created > now() - interval '7 day'";
		$activity = DB::fetchfield($sql);
		$sql = "UPDATE proposal SET activity=".intval($activity)." WHERE id=".intval($proposal->id);
		DB::query($sql);
	}
}


/**
 * upload voter lists for postal voting and for each ballot
 *
 * @return boolean
 * @param Period  $period
 * @param boolean $include_ballot_voters (optional)
 */
function upload_voters($period, $include_ballot_voters=false) {

	$data = array();

	// postal voters
	$sql = "SELECT invite FROM member
		JOIN offlinevoter ON offlinevoter.member = member.id AND offlinevoter.ballot IS NULL AND offlinevoter.period = ".intval($period->id);
	$data[0] = array(
		'name'   => "postal voting",
		'voters' => DB::fetchfieldarray($sql)
	);

	// ballot voters
	if ($include_ballot_voters) {
		$sql_ballot = "SELECT * FROM ballot WHERE period=".intval($period->id)." AND approved=TRUE";
		$result_ballot = DB::query($sql_ballot);
		while ( $ballot = DB::fetch_object($result_ballot, "Ballot") ) {
			$sql = "SELECT invite FROM member
				JOIN offlinevoter ON offlinevoter.member = member.id AND offlinevoter.ballot = ".intval($ballot->id);
			$data[$ballot->id] = array(
				'name'    => $ballot->name,
				'ngroup'  => $ballot->ngroup()->name,
				'opening' => timeformat($ballot->opening),
				'closing' => BALLOT_CLOSE_TIME,
				'agents'  => $ballot->agents,
				'voters'  => DB::fetchfieldarray($sql)
			);
		}
	}

	// TODO: For now we don't use the data.

}


/**
 * avoid more than one execution of cron() at the same time
 *
 * @return boolean
 */
function cron_lock() {

	$pid = getmypid();
	$ps = explode(PHP_EOL, `ps -e | awk '{print $1}'`);

	DB::transaction_start();

	$result = DB::query("SELECT pid FROM cron_lock");
	if ( $row = DB::fetch_assoc($result) ) {
		// check if process is still running
		if (in_array($row['pid'], $ps)) {
			DB::transaction_commit();
			return false;
		}
		// remove lock for no longer running process
		cron_unlock();
	}

	DB::query("INSERT INTO cron_lock (pid) VALUES (".intval($pid).")");
	DB::transaction_commit();

	return true;
}


/**
 *
 */
function cron_unlock() {
	DB::query("TRUNCATE cron_lock");
}
