<?
/**
 * functions used by command line scripts
 *
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
	FROM periods";
	// TODO: exclude closed
	$result_period = DB::query($sql_period);
	while ( $period = DB::fetch_object($result_period, "Period") ) {
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

			$sql_ballot = "SELECT * FROM ballots WHERE period=".intval($period->id);
			$result_ballot = DB::query($sql_ballot);
			while ( $ballot = DB::fetch_object($result_ballot, "Ballot") ) {

				// notification to the ballot agents whether the ballot was approved
				// TODO: more than one ballot agent
				$notification = new Notification("ballot_approved");
				$notification->period = $period;
				$notification->ballot = $ballot;
				$sql = "SELECT member FROM voters WHERE ballot = ".intval($ballot->id)." AND agent = TRUE";
				$recipients = DB::fetchfieldarray($sql);
				$notification->send($recipients);

				if (!$ballot->approved) continue;

				// notification to the members to which ballots they were assigned
				$notification = new Notification("ballot_assigned");
				$notification->period = $period;
				$notification->ballot = $ballot;
				$sql = "SELECT member FROM voters WHERE ballot = ".intval($ballot->id);
				$recipients = DB::fetchfieldarray($sql);
				$notification->send($recipients);

			}

			$period->state = "ballot_assignment";
			$period->update(array("state"));

			break;

			// ballot preparation
		case "ballot_assignment":
			if (!$period->ballot_preparation_now) break;

			// final upload of the complete postal and ballot voters
			if ( upload_voters($period, true) ) {
				$period->state = "ballot_preparation";
				$period->update(array("state"));
			}

			// ballot_preparation is the final state.
		}


		// proposals and issues
		$sql_issue = "SELECT *, clear <= now() AS clear_now FROM issues
		  WHERE period=".intval($period->id)."
		    AND state NOT IN ('cleared', 'cancelled')";
		$result_issue = DB::query($sql_issue);
		while ( $issue = DB::fetch_object($result_issue, "Issue") ) {
			DB::to_bool($issue->clear_now);

			switch ($issue->state) {

				// debate
			case "admission":
				if (!$period->debate_now) break;

				$all_proposals_revoked = true;
				$admitted_proposals = false;
				$not_admitted_proposals = false;
				foreach ( $issue->proposals() as $proposal ) {
					if ($proposal->state=="revoked" or $proposal->state=="cancelled" or $proposal->state=="done") continue;
					//if ( $proposal->check_proponents() ) {
					$all_proposals_revoked = false;
					if ($proposal->state=="admitted") $admitted_proposals = true; else $not_admitted_proposals = true;
					/*} else {
						// revoke proposals without proponents
						$proposal->state = "revoked";
						$proposal->update(array("state"));
					}*/
				}

				if ($all_proposals_revoked) {
					$issue->state = "cancelled";
					$issue->update(array("state"));
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
						if ($proposal->state=="revoked" or $proposal->state=="cancelled" or $proposal->state=="done") continue;
						if ($proposal->state=="admitted") continue;
						$proposal->issue = $new_issue->id;
						$proposal->update(array("issue"));
					}*/

					// cancel proposals
					foreach ( $issue->proposals() as $proposal ) {
						if ($proposal->state=="revoked" or $proposal->state=="cancelled" or $proposal->state=="done") continue;
						if ($proposal->state=="admitted") continue;
						$proposal->cancel();
					}

				}

				$issue->state = "debate";
				$issue->update(array("state"), 'debate_started=now()');

				$issues_start_debate[] = $issue;

				break;

				// preparation
			case "debate":
				if (!$period->preparation_now) break;

				// revoke proposals, which have were scheduled for revokation (and still have less than required proponents)
				$sql = "SELECT * FROM proposals
					WHERE issue=".intval($issue->id)."
						AND revoke IS NOT NULL";
				$result = DB::query($sql);
				while ( $proposal = DB::fetch_object($result, "Proposal") ) {
					// clear revoke date
					$proposal->revoke = null;
					$proposal->update(array('revoke'));
					// don't revoke already cancelled/revoked/done proposals
					if ( in_array($proposal->state, array("draft", "submitted", "admitted")) ) {
						$proposal->cancel("revoked");
					}
				}

				$issue->state = "preparation";
				$issue->update(array("state"), 'preparation_started=now()');

				break;

				// voting
			case "preparation":
				if (!$period->voting_now) break;

				// check if the period provides the right voting type
				if ( ($issue->ballot_voting_reached and $period->ballot_voting) or (!$issue->ballot_voting_reached and $period->online_voting) ) {

					$issues_start_voting[] = $issue;

				} else {

					// skip to next available period
					$sql = "SELECT id FROM periods
						WHERE ngroup=".intval($period->ngroup)."
							AND preparation >= now()
							AND ". $issue->ballot_voting_reached?"ballot_voting":"online_voting" ."=TRUE
						ORDER BY preparation
						LIMIT 1";
					$issue->period = DB::fetchfield($sql);
					if ($issue->period) {
						$issue->update(array("period"));
					} else {
						trigger_error(sprintf(_("Voting for issue %d could not be started, because the determined voting type is not available in the current and all further voting periods!"), $issue->id), E_USER_WARNING);
					}

				}

				break;

				// counting
			case "voting":
				if (!$period->counting_now) break;

				$issue->state = "counting";
				$issue->update(array("state"), 'counting_started=now()');

				$issue->counting();

				// set date for clearing
				$issue->state = "finished";
				$issue->update(array("state"), "clear = current_date + interval ".DB::esc(CLEAR_INTERVAL));

				$issues_finished_voting[] = $issue;

				// remove inactive participants from areas, who's last activation is before the counting of the period before the current one
				$sql = "SELECT counting FROM periods
					WHERE ngroup=".intval($period->ngroup)."
						AND counting <= now()
					ORDER BY counting DESC
					LIMIT 2";
				$result = DB::query($sql);
				DB::fetch_assoc($result); // skip the current counting
				if ( $last_counting = DB::fetch_assoc($result) ) {
					// area participation
					$sql = "DELETE FROM participants
						WHERE area IN (SELECT id FROM areas WHERE ngroup=".intval($period->ngroup).")
							AND activated < ".DB::esc($last_counting['counting']);
					DB::query($sql);
				}

				break;

				// clear
			case "finished":
				if (!$issue->clear_now) break;

				// delete raw voting data
				$sql = "DELETE FROM vote_tokens WHERE issue=".intval($issue->id);
				DB::query($sql);

				$issue->clear = null;
				$issue->state = "cleared";
				$issue->update(array("state"), "cleared=now()");

				// "cleared" and "cancelled" are the final issue states.
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
		if ($issues_start_voting) {

			// entitled members of the ngroup
			$sql = "SELECT id FROM members
				JOIN members_ngroups ON members.id = members_ngroups.member AND members_ngroups.ngroup=".intval($period->ngroup)."
				WHERE entitled=TRUE";
			$members = DB::fetchfieldarray($sql);

			$personal_tokens = array();
			$all_tokens      = array();
			foreach ($issues_start_voting as $issue) {

				// generate vote tokens
				foreach ( $members as $member_id ) {
					DB::transaction_start();
					do {
						$token = Login::generate_token(16);
						$sql = "SELECT token FROM vote_tokens WHERE token=".DB::esc($token);
					} while ( DB::numrows($sql) );
					$sql = "INSERT INTO vote_tokens (member, issue, token) VALUES (".intval($member_id).", ".intval($issue->id).", ".DB::esc($token).")";
					DB::query($sql);
					DB::transaction_commit();
					$personal_tokens[$member_id][$issue->id] = $token;
					$all_tokens[$issue->id][]                = $token;
				}

				$issue->state = "voting";
				$issue->update(array("state"), 'voting_started=now()');

			}

			foreach ( $members as $member_id ) {
				$notification = new Notification("voting");
				$notification->period = $period;
				$notification->issues = $issues_start_voting;
				$notification->personal_tokens = $personal_tokens[$member_id];
				$notification->all_tokens      = $all_tokens;
				$notification->send($member_id);
			}

		}




	}


	// revoke due proposals, which have have less than required proponents
	$sql = "SELECT * FROM proposals WHERE revoke < now()";
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		// clear revoke date
		$proposal->revoke = null;
		$proposal->update(array('revoke'));
		if (
			$proposal->proponents_count() < REQUIRED_PROPONENTS and
			// don't revoke already cancelled/revoked/done proposals
			in_array($proposal->state, array("draft", "submitted", "admitted"))
		) {
			$proposal->cancel("revoked");
		}
	}


	// cancel proposals, which have not been admitted within 6 months
	// https://basisentscheid.piratenpad.de/entscheidsordnung
	// "Ein Antrag verfällt, sobald er auf dem Parteitag behandelt wurde oder wenn er innerhalb von sechs Monaten das notwendige Quorum zur Zulassung zur Abstimmung nicht erreicht hat."
	$sql = "SELECT * FROM proposals
		WHERE state='submitted'
			AND submitted < now() - interval ".DB::esc(CANCEL_NOT_ADMITTED_INTERVAL);
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		$proposal->cancel();
	}


	cron_unlock();

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
	$sql = "SELECT invite FROM members
		JOIN voters ON voters.member = members.id AND voters.ballot IS NULL AND voters.period = ".intval($period->id);
	$data[0] = array(
		'name'   => "postal voting",
		'voters' => DB::fetchfieldarray($sql)
	);

	// ballot voters
	if ($include_ballot_voters) {
		$sql_ballot = "SELECT * FROM ballots WHERE period=".intval($period->id)." AND approved=TRUE";
		$result_ballot = DB::query($sql_ballot);
		while ( $ballot = DB::fetch_object($result_ballot, "Ballot") ) {
			$sql = "SELECT invite FROM members
				JOIN voters ON voters.member = members.id AND voters.ballot = ".intval($ballot->id);
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

	// For now we don't use the data.

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
