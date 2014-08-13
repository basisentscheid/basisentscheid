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
	$result_period = DB::query($sql_period);
	while ( $period = DB::fetch_object($result_period, "Period") ) {
		DB::to_bool($period->debate_now);
		DB::to_bool($period->preparation_now);
		DB::to_bool($period->voting_now);
		DB::to_bool($period->ballot_assignment_now);
		DB::to_bool($period->ballot_preparation_now);
		DB::to_bool($period->counting_now);

		// collect issues for upload to the ID server
		$issues_start_voting = array();

		// ballots
		switch ($period->state) {
		case "ballot_application":
			if (!$period->ballot_assignment_now) break;

			// Zuordnung der noch nicht zugeordneten Mitglieder
			$period->assign_members_to_ballots();

			// Mitteilung über genehmigte Urnen an Urnenantragsteller

			// Mitteilung an Mitglied über Zuordung zur Urne

			$period->state = "ballot_assignment";
			$period->update(array("state"));

			break;

		case "ballot_assignment":
			if (!$period->ballot_preparation_now) break;

			// Mitteilung der Teilnehmerliste und Wahlunterlagen an Urnenbeauftragte (per Post oder E-Mail?)

			$period->state = "ballot_preparation";
			$period->update(array("state"));

			// ballot_preparation is the final state.
		}

		// proposals and issues
		$sql_issue = "SELECT *, clear <= now() AS clear_now FROM issues WHERE period=".intval($period->id);
		$result_issue = DB::query($sql_issue);
		while ( $issue = DB::fetch_object($result_issue, "Issue") ) {
			DB::to_bool($issue->clear_now);

			// admitted -> debate
			switch ($issue->state) {
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
						$proposal->state=="cancelled";
						$proposal->update(array("state"));
					}

				}

				$issue->state = "debate";
				$issue->update(array("state"), 'debate_started=now()');

				break;

			case "debate":
				if (!$period->preparation_now) break;

				$issue->state = "preparation";
				$issue->update(array("state"), 'preparation_started=now()');

				break;

			case "preparation":
				if (!$period->voting_now) break;

				// check if the period provides the right voting type
				if ( ($issue->ballot_voting_reached and $period->ballot_voting) or (!$issue->ballot_voting_reached and $period->online_voting) ) {

					$issues_start_voting[] = $issue;

				} else {

					// skip to next available period
					$sql = "SELECT id FROM periods WHERE preparation >= now() AND ";
					if ($issue->ballot_voting_reached) $sql .= "ballot_voting"; else $sql .= "online_voting";
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
				if (!$period->counting_now) break;

				// We just set the state, but don't actually do anything. The voting server has to detect hisself when the voting has to be closed and counted.
				$issue->state = "counting";
				$issue->update(array("state"), 'counting_started=now()');

				// remove inactive participants from areas, who's last activation is before the counting of the period before the current one
				// EO: "Eine Anmeldung verfällt automatisch nach dem zweiten Stichtag nach der letzten Anmeldung des Teilnehmers."
				$sql = "SELECT counting FROM periods WHERE counting <= now() ORDER BY counting DESC LIMIT 2";
				$result = DB::query($sql);
				DB::fetch_assoc($result); // skip the current counting
				if ( $last_counting = DB::fetch_assoc($result) ) {
					// area participation
					$sql = "DELETE FROM participants WHERE activated < ".DB::esc($last_counting['counting']);
					DB::query($sql);
					// general participation
					$sql = "UPDATE members SET participant=FALSE WHERE participant=TRUE AND activated < ".DB::esc($last_counting['counting']);
					DB::query($sql);
				}

				break;

				// Case "counting" is handled by download_vote().

			case "finished":
				if (!$issue->clear_now) break;

				// TODO Clear

				$issue->state = "cleared";
				$issue->update(array("state"), "clear=NULL, cleared=now()");

				// "cleared" and "cancelled" are the final issue states.
			}

		}


		// upload votings to the ID server
		if ($issues_start_voting) {
			$json_string = build_json($issues_start_voting, $period);
			//echo $json_string."\n";
			if ($json_string) {
				if ( upload_voting_data($json_string) ) {
					foreach ($issues_start_voting as $issue) {
						$issue->state = "voting";
						$issue->update(array("state"), 'voting_started=now()');
					}
				}
			}
		}


	}


	// cancel proposals, which have not been admitted within 6 months
	// https://basisentscheid.piratenpad.de/entscheidsordnung
	// "Ein Antrag verfällt, sobald er auf dem Parteitag behandelt wurde oder wenn er innerhalb von sechs Monaten das notwendige Quorum zur Zulassung zur Abstimmung nicht erreicht hat."
	$sql = "SELECT * FROM proposals
		WHERE state='submitted'
			AND submitted < now() - ".DB::esc(CANCEL_NOT_ADMITTED_INTERVAL)."::INTERVAL";
	$result = DB::query($sql);
	while ( $proposal = DB::fetch_object($result, "Proposal") ) {
		$proposal->cancel();
	}


	cron_unlock();

}


/**
 * build json string for the upload of voting data
 *
 * @param array   $issues array of objects
 * @param object  $period
 * @return string
 */
function build_json($issues, $period) {

	/* from https://basisentscheid.piratenpad.de/testabstimmungsdaten

Beschreibungen (je Gliederung und Termin): wer ist berechtigt
 Basisentscheide
   Optionen
{
 "ballotID":"GTVsdffgsdwt40QXffsd452re",
 "votingStart": "2014-02-10T21:20:00Z",
 "votingEnd": "2014-03-04T00:00:00Z",
 "access":
     {
     "listID": "DEADBEEF",
     "groups": [ 1,2,3]
     },
 "ballotName": "Abstimmungszeitraum A-B von Gliederung X",
 "questions":
    [
        {
         "questionID":1,
         "questionWording":"Basisentscheid nummer 1 von Abstimmungszeitraum/Gliederung",
         "voteSystem":
             {
             "type": "score",
             "max-score": 1,
             "abstention": true,
             "single-step": true
             },
         "options":
                 [
                    { "optionID": "eughu5RBGG","optionText": "Ja, linksherum" },
                    { "optionID": 2, "optionText": "Ja, rechtsherum" },
                    { "optionID": 3, "optionText": "Nein. Ab durch die Mitte!" },
                    { "optionID": 4, "optionText": "Jein. Wir drehen durch." }
                 ],
         "references":
           [
               { "referenceName":"Abschlussparty und Auflösung", "referenceAddress":"https://lqfb.piratenpartei.de/lf/initiative/show/5789.html" },
               { "referenceName":"Bilder zur Motivation","referenceAddress":"https://startpage.com/do/search?cat=pics&cmd=process_search&language=deutsch&query=cat+content" }
           ]
        },
        {
        "questionID":2,
        "questionWording":"Basisentscheid zum Thema Bringen uns Schuldzuweisungen irgendwas? in Basisentscheid-Sammlung drölf",
         "voteSystem":
             {
             "type": "score",
             "max-score": 1,
             "abstention": false,
             "single-step": true
             },
        "options":
                [
                    { "optionID":1, "optionText": "Ja. Befriedigung! Ha!"},
                    { "optionID":2, "optionText": "Ja. Streit und Ärger."},
                    { "optionID":3, "optionText": "nö, aber wir machen's dennoch" },
                    { "optionID":4, "optionText": "Huch? Das macht noch jemand?" }
                ],
        "references":
                [
               { "referenceName":"piff paff puff kappotschießen", "referenceAddress":"https://twitter.com/czossi/status/436217916803911680/photo/1" },
                ]
        }
    ],
 "references":
    [
        {"referenceName":"Piratenpartei","referenceAddress":"https://piratenpartei.de/"}
    ]
}

	*/

	// enable gettext translation
	include_once DOCROOT."inc/locale.php";

	$json_questions = array();
	foreach ($issues as $issue) {

		$json_options = array();
		foreach ($issue->proposals() as $proposal) {
			$json_options[] = array(
				"optionID"   => $proposal->id,
				"optionText" => $proposal->title
			);
		}

		$json_questions[] = array(
			"questionID" => $issue->id,
			"questionWording" => sprintf(_("Basisentscheid %d of voting period %d"), $issue->id, $period->id),
			"voteSystem" => array(
				"type" => "score",
				"max-score" => 1,
				"abstention" => true,
				"single-step" => true
			),
			"options" => $json_options,
			"references" => array(
				array(
					"referenceName" => "Abschlussparty und Auflösung",
					"referenceAddress" => "https://lqfb.piratenpartei.de/lf/initiative/show/5789.html"
				),
				array(
					"referenceName" => "Bilder zur Motivation",
					"referenceAddress" => "https://startpage.com/do/search?cat=pics&cmd=process_search&language=deutsch&query=cat+content"
				)
			)
		);

	}

	$json_array = array(
		"ballotID" => $period->id,
		"votingStart" => $period->voting,
		"votingEnd"   => $period->counting,
		"access" => array(
			"listID" => "DEADBEEF",
			"groups" => array(1, 2, 3)
		),
		"ballotName" => sprintf(_("voting period %d"), $period->id),
		"questions" => $json_questions,
		"references" => array(
			array(
				"referenceName" => "Piratenpartei",
				"referenceAddress" => "https://piratenpartei.de/"
			)
		)
	);

	return json_encode($json_array);
}


/**
 * upload voting data to the ID server
 *
 * @param string  $json
 * @return boolean
 */
function upload_voting_data($json) {

	// for testing
	if (defined("SKIP_UPDOWNLOAD")) return true;

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, SHARE_URL);

	// POST
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

	// https handling
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_CAINFO,  CAINFO);
	curl_setopt($ch, CURLOPT_SSLCERT, SSLCERT);
	curl_setopt($ch, CURLOPT_SSLKEY,  SSLKEY);

	$result = curl_exec($ch);

	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$curl_error = curl_error($ch);
	curl_close($ch);

	if ($http_code==201) return true;

	trigger_error("HTTP code other than 201", E_USER_NOTICE);
	return false;
}


/**
 * download voting result from the ID server
 *
 * @param object  $issue
 * @return string
 */
function download_vote(Issue $issue) {

	// for testing
	if (defined("SKIP_UPDOWNLOAD")) return "test result";

	// TODO: The issue should probably be added to the URL.

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, SHARE_URL);

	// https handling
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_CAINFO,  CAINFO);
	curl_setopt($ch, CURLOPT_SSLCERT, SSLCERT);
	curl_setopt($ch, CURLOPT_SSLKEY,  SSLKEY);

	$result = curl_exec($ch);

	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$curl_error = curl_error($ch);
	curl_close($ch);

	if (!$result) {
		trigger_error($curl_error, E_USER_NOTICE);
		return false;
	}

	return $result;
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
