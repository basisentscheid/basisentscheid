#!/usr/bin/php
<?
/**
 * generate test data
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";


// delete everything
//DB::query("TRUNCATE periods CASCADE");
//DB::query("TRUNCATE members CASCADE");
//DB::query("TRUNCATE areas CASCADE");

// to aviod conflicts with existing usernames
$date = dechex(time());

// create main member
$login = new Member;
$login->username = "t".$date."login";
$login->auid = $login->username;
$login->entitled = true;
$login->create();

$ngroup = 1;

$bcase = array(1=>0, 2=>0, 3=>0, 4=>0);
// read start branches from command line
foreach ( $_SERVER['argv'] as $key => $value ) {
	if ($key==0) continue;
	$bcase[$key] = $value;
}

// go through all cases
$case = 0;
do {
	$stopcase = 0;
	do {
		$case++;
		$stopcase++;
	} while ( ! $return = create_case_wrapper($case, $stopcase) );
} while ( $return !== "end" );


/**
 * handle case titles
 *
 * @param integer $case
 * @param integer $stopcase
 * @return mixed null after one stopcase, false after one branchcase, true after last case
 */
function create_case_wrapper($case, $stopcase) {
	global $proposal, $proposal2, $casetitle;

	$return = create_case($case, $stopcase);

	if ($casetitle) {
		echo " - ".$casetitle."\n";
		if ($proposal) {
			$proposal->title .= " - ".$casetitle;
			$proposal->update(array('title'));
		}
		if ($proposal2) {
			$proposal2->title .= " - ".$casetitle;
			$proposal2->update(array('title'));
		}
	}

	return $return;
}


/**
 * create one test case
 *
 * @param integer $case
 * @param integer $stopcase
 * @return mixed null after one stopcase, false after one branchcase, true after last case
 */
function create_case($case, $stopcase) {
	global $bcase, $date, $login, $proposal, $proposal2, $casetitle, $ngroup;

	$stop = 0;
	$branch = 0;
	$casedesc = $case." (stop: ".$stopcase.", branches:";
	foreach ($bcase as $value) $casedesc .= " ".$value;
	$casedesc .= ")";
	echo "Test case ".$casedesc."\n";

	Login::$member = $login;
	$proposal = null;
	$proposal2 = null;
	$casetitle = "";

	// create area
	$area = new Area;
	$area->ngroup = $ngroup;
	$area->name = "Test area case ".$casedesc;
	$area->create();

	// create new proposal
	$proposal = new Proposal;
	$proposal->title = "Test ".$date." case ".$casedesc;
	$proposal->content = "Test content";
	$proposal->reason = "Test reason";
	$proposal->create("Test proponent ".$date." proposal case ".$casedesc, $area->id);
	$proponents = array(Login::$member);

	if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
		$casetitle = "draft";
		return;
	}

	$issue = $proposal->issue();

	if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
		$proposal->remove_proponent(Login::$member);
		cron();
		$casetitle = "remove proponent from draft";
		return;
	}

	if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
		$proposal->remove_proponent(Login::$member);
		time_warp($issue, "1 week");
		cron();
		$casetitle = "remove proponent from draft and finally revoke proposal";
		return;
	}

	if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
		$proposal->remove_proponent(Login::$member);
		add_proponent($proposal, $case, "px");
		time_warp($issue, "1 week");
		cron();
		$casetitle = "remove proponent from draft, add new proponent";
		return;
	}

	// add proponents
	for ( $i=2; $i<=REQUIRED_PROPONENTS; $i++ ) {
		add_proponent($proposal, $case, "pi".$i);
		$proponents[] = Login::$member;
	}

	if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
		$casetitle = "draft with proponents";
		return;
	}

	$proposal->submit();

	if ($proposal->state=="submitted") {

		if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
			foreach ($proponents as $proponent) {
				$proposal->remove_proponent($proponent);
			}
			cron();
			$casetitle = "remove all proponents from submitted proposal";
			return;
		}

		if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
			foreach ($proponents as $proponent) {
				$proposal->remove_proponent($proponent);
			}
			for ( $i=1; $i<=REQUIRED_PROPONENTS-1; $i++ ) {
				add_proponent($proposal, $case, "pr".$i);
			}
			time_warp($issue, "1 week");
			cron();
			$casetitle = "remove all proponents from submitted proposal, add less than required new proponents and finally revoke proposal";
			return;
		}

		if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
			foreach ($proponents as $proponent) {
				$proposal->remove_proponent($proponent);
			}
			for ( $i=1; $i<=REQUIRED_PROPONENTS; $i++ ) {
				add_proponent($proposal, $case, "pr".$i);
			}
			time_warp($issue, "1 week");
			cron();
			$casetitle = "remove all proponents from submitted proposal, add sufficient new proponents";
			return;
		}

		$required_supporters = $proposal->quorum_required();

		${'branch'.++$branch.'_array'} = array(0, $required_supporters-1, $required_supporters);
		$supporter_count = ${'branch'.$branch.'_array'}[$bcase[$branch]];

		for ( $i=1; $i<=$supporter_count-REQUIRED_PROPONENTS; $i++ ) {
			add_supporter($proposal, $case, "a".$supporter_count."i".$i);
		}

		if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
			$casetitle = "alternative proposal with $supporter_count supporters";
			return;
		}

		if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
			time_warp($issue, CANCEL_NOT_ADMITTED_INTERVAL);
			cron();
			$casetitle = "cancel long time not admitted proposal";
			return;
		}

		// create alternative proposal
		$proposal2 = new Proposal;
		$proposal2->title = "Test ".$date." alternative proposal case ".$casedesc;
		$proposal2->content = "Test content";
		$proposal2->reason = "Test reason";
		$proposal2->issue = $proposal->issue;
		$proposal2->create("Test proponent ".$date." alternative proposal case ".$casedesc, $area->id);
		$proponents = array(Login::$member);

		if ($stopcase == ++$stop) {
			$casetitle = "alternative draft";
			return;
		}

		// add proponents
		for ( $i=1; $i<=4; $i++ ) {
			add_proponent($proposal2, $case, "qi".$i);
			$proponents[] = Login::$member;
		}

		if ($stopcase == ++$stop) {
			$casetitle = "alternative draft with proponents";
			return;
		}

		$proposal2->submit();

		if ($proposal2->state=="submitted" and $stopcase == ++$stop) {
			foreach ($proponents as $proponent) {
				$proposal2->remove_proponent($proponent);
			}
			cron();
			$casetitle = "remove all proponents from submitted alternative proposal";
			return;
		}

		$required_supporters = $proposal2->quorum_required();

		${'branch'.++$branch.'_array'} = array(0, $required_supporters-1, $required_supporters);
		$supporter_count2 = ${'branch'.$branch.'_array'}[$bcase[$branch]];

		for ( $i=1; $i<=$supporter_count2; $i++ ) {
			add_supporter($proposal2, $case, "a".$supporter_count."b".$supporter_count2."i".$i);
		}

		if ($stopcase == ++$stop) {
			$casetitle = "alternative proposal with $supporter_count2 supporters";
			return;
		}

		if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
			time_warp($issue, CANCEL_NOT_ADMITTED_INTERVAL);
			cron();
			$casetitle = "cancel long time not admitted alternative proposal";
			return;
		}

		if ($proposal->state=="admitted" or $proposal2->state=="admitted") {

			// create period
			$sql = "INSERT INTO periods (debate, preparation, voting, counting, online_voting, ballot_voting, ngroup)
			VALUES (
				now() + interval '1 week',
				now() + interval '2 weeks',
				now() + interval '3 weeks',
				now() + interval '4 weeks',
				true,
				false,
				".$ngroup."
			) RETURNING id";
			$result = DB::query($sql);
			$row = DB::fetch_row($result);
			$period = $row[0];

			// assign issue to period
			$issue->period = $period;
			$issue->update(array("period"));

			// assigned, but not yet started
			cron();

			if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
				$casetitle = "assigned issue";
				return;
			}

			// move on to state "debate"
			time_warp($issue, "1 week");
			cron();

			$ballot_voting_required = $issue->quorum_ballot_voting_required();

			${'branch'.++$branch.'_array'} = array(0, $ballot_voting_required-1, $ballot_voting_required);
			$ballot_voting_demanders_count = ${'branch'.$branch.'_array'}[$bcase[$branch]];

			for ( $i=1; $i<=$ballot_voting_demanders_count; $i++ ) {
				add_ballot_voting_demander($proposal2, $case, "a".$supporter_count."b".$supporter_count2."s".$ballot_voting_demanders_count."i".$i);
			}

			if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
				$casetitle = "issue with $ballot_voting_demanders_count ballot voting demanders";
				return;
			}

			$proposal2->read();

			${'branch'.++$branch.'_array'} = array(false, true);
			if ( ${'branch'.$branch.'_array'}[$bcase[$branch]] and $proposal2->state!="cancelled" ) {
				// remove all proponents from alternative proposal during debate
				foreach ($proponents as $proponent) {
					$proposal2->remove_proponent($proponent);
				}
			}

			// move on to state "preparation"
			time_warp($issue, "1 week");
			cron();

			if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
				$casetitle = "issue in voting preparation state";
				return;
			}

			// move on to state "voting"
			time_warp($issue, "1 week");
			cron();

			if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
				$casetitle = "issue in voting state";
				return;
			}

			// move on to state "counting"
			time_warp($issue, "1 week");
			cron();

			if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
				$casetitle = "issue in counting state";
				return;
			}

			// move on to state "finished"
			$result = download_vote($issue);
			$issue->save_vote($result);

			if (no_branch_skip($branch, $bcase) and $stopcase == ++$stop) {
				$casetitle = "issue in finished state";
				return;
			}

			// move on to state "cleared"
			time_warp($issue, CLEAR_INTERVAL);
			cron();

			$casetitle = "issue in cleared state";

		} else {
			$casetitle = "no admitted proposal";
		}

	} else {
		$casetitle = "proposal not submitted";
	}

	// continue with next case if branches are still available
	for ($i=1; $i<=$branch; $i++) {
		if (isset(${'branch'.$i.'_array'}[++$bcase[$i]])) {
			for ($j=1; $j<$i; $j++) $bcase[$j]=0;
			return true;
		}
	}

	// end of last case
	return "end";
}


/**
 * return true if the case should be provided in the current branches
 *
 * @param integer $branch
 * @param array   $bcase
 * @param array   $exclude_branches (optional)
 * @return boolean
 */
function no_branch_skip($branch, array $bcase, array $exclude_branches=array()) {
	for ($b=$branch+1; $b<=count($bcase); $b++) {
		if (!in_array($b, $exclude_branches) and $bcase[$b]) return false;
	}
	return true;
}


/**
 * create a new member and let it support the supplied proposal
 *
 * @param object  $proposal
 * @param integer $case
 * @param string  $i
 */
function add_supporter(Proposal $proposal, $case, $i) {
	global $date;

	Login::$member = new Member;
	Login::$member->username = "t".$date."c".$case."p".$proposal->id.$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->entitled = true;
	Login::$member->create();
	$proposal->add_support();
}


/**
 * create a new member and let it support the supplied proposal
 *
 * @param object  $proposal
 * @param integer $case
 * @param string  $i
 */
function add_proponent(Proposal $proposal, $case, $i) {
	global $date;

	Login::$member = new Member;
	Login::$member->username = "t".$date."c".$case."p".$proposal->id.$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->entitled = true;
	Login::$member->create();
	$proposal->add_proponent(Login::$member->username, true);
}


/**
 * create a new member and let it support ballot voting for the supplied proposal
 *
 * @param object  $proposal
 * @param integer $case
 * @param string  $i
 */
function add_ballot_voting_demander(Proposal $proposal, $case, $i) {
	global $date;

	Login::$member = new Member;
	Login::$member->username = "t".$date."c".$case."p".$proposal->id.$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->entitled = true;
	Login::$member->create();
	$proposal->issue()->demand_ballot_voting();
}


/**
 * move all times in the past to pretend we moved into the future
 *
 * @param object  $issue
 * @param string  $interval (optional)
 */
function time_warp(Issue $issue, $interval="1 hour") {
	$interval = "interval '".$interval."'";

	foreach ( array('submitted', 'admitted', 'cancelled', 'revoke') as $column ) {
		$sql = "UPDATE proposals SET
				$column = $column - $interval
			WHERE issue=".intval($issue->id)."
				AND $column IS NOT NULL";
		DB::query($sql);
	}

	foreach ( array('debate_started', 'preparation_started', 'voting_started', 'counting_started', 'clear', 'cleared') as $column ) {
		$sql = "UPDATE issues SET
				$column = $column - $interval
			WHERE id=".intval($issue->id)."
				AND $column IS NOT NULL";
		DB::query($sql);
	}

	$sql = "UPDATE periods SET
			debate      = debate      - $interval,
			preparation = preparation - $interval,
			voting      = voting      - $interval,
			counting    = counting    - $interval
		WHERE id=".intval($issue->period);
	DB::query($sql);
}
