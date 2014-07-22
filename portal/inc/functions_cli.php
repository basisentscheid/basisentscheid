<?
/**
 * inc/functions_cli.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


/**
 *
 */
function cron() {

	$sql_period = "SELECT *,
		debate      <= now() AS debate_now,
		preparation <= now() AS preparation_now,
		voting      <= now() AS voting_now,
		counting    <= now() AS counting_now
	FROM periods";
	$result_period = DB::query($sql_period);
	while ( $row_period = pg_fetch_assoc($result_period) ) {

		$sql_issue = "SELECT *, clear <= now() AS clear_now FROM issues WHERE period=".intval($row_period['id']);
		$result_issue = DB::query($sql_issue);
		while ( $row_issue = pg_fetch_assoc($result_issue) ) {

			$issue = new Issue($row_issue);

			// admitted -> debate
			switch ($issue->state) {
			case "admission":
				if ($row_period['debate_now']=="f") break;

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
						$proposal->state=="cancelled";
						$proposal->update(array("state"));
					}

				}

				$issue->state = "debate";
				$issue->update(array("state"));

				break;

			case "debate":
				if ($row_period['preparation_now']=="f") break;

				$issue->state = "preparation";
				$issue->update(array("state"));

				break;

			case "preparation":
				if ($row_period['voting_now']=="f") break;

				// check if the period provides the right voting type
				if ( ($issue->secret_reached and $row_period['secret']) or (!$issue->secret_reached and $row_period['online']) ) {
					$issue->state = "voting";
					$issue->update(array("state"));
				} else {

					// skip to next available period
					$sql = "SELECT id FROM periods WHERE preparation >= now() AND ";
					if ($issue->secret_reached) $sql .= "secret"; else $sql .= "online";
					$sql .= "=TRUE ORDER BY preparation LIMIT 1";
					$issue->period = DB::fetchfield($sql);
					if ($issue->period) {
						$issue->update(array("period"));
					} else {
						// TODO Error
					}

				}

				break;

			case "voting":
				if ($row_period['counting_now']=="f") break;

				$issue->state = "counting";
				$issue->update(array("state"));

				// TODO Count

				$issue->state = "finished";
				$issue->update(array("state"), "clear = current_date + ".DB::m(CLEAR_INTERVAL)."::INTERVAL");

				// remove inactive participants from areas, whos last activation is before the counting of the period before the current one
				// EO: "Eine Anmeldung verfällt automatisch nach dem zweiten Stichtag nach der letzten Anmeldung des Teilnehmers."
				$sql = "SELECT counting FROM periods WHERE counting <= now() ORDER BY counting DESC LIMIT 2";
				$result = DB::query($sql);
				pg_fetch_assoc($result); // skip the current counting
				if ( $last_counting = pg_fetch_assoc($result) ) {
					$sql = "DELETE FROM participants WHERE activated < ".DB::m($last_counting['counting']);
					echo $sql;
					DB::query($sql);
				}

				break;

			case "finished":
				if ($row_issue['clear_now']=="f") break;

				// TODO Clear

				$issue->state = "cleared";
				$issue->update(array("state"), "clear = NULL");

				break;

			}

		}

	}


	// cancel proposals, which have not been admitted within 6 months
	$sql = "UPDATE proposals SET state='cancelled'
	WHERE state='submitted'
		AND submitted < current_date - interval ".DB::m(CANCEL_NOT_ADMITTED_INTERVAL);
	DB::query($sql);


}
