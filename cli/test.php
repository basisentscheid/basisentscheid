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
$login->create();


// go through all cases
$case = 0;
do {
	$stopcase = 0;
	do {
		$case++;
		$stopcase++;
	} while ( ! $return = create_case($case, $stopcase) );
} while ( $return !== "end" );


/**
 * create one test case
 *
 * @param integer $case
 * @param integer $stopcase
 * @return boolean true after last case
 */
function create_case($case, $stopcase) {
	global $date, $login;

	$stop = 0;
	$branch = 0;
	static $branch1 = 0;
	static $branch2 = 0;
	static $branch3 = 0;
	$casedesc = $case." (".$stopcase."/".$branch3."/".$branch2."/".$branch1.")";
	echo "Test case ".$casedesc."\n";

	Login::$member = $login;

	// create area
	$area = 0;
	DB::insert("areas", array('name'=>"Test area case ".$casedesc), $area);

	// create new proposal
	$proposal = new Proposal;
	$proposal->title = "Test ".$date." proposal case ".$casedesc;
	$proposal->content = "Test content";
	$proposal->reason = "Test reason";
	$proposal->create("Test proponent ".$date." proposal case ".$casedesc, $area);

	if ($stopcase == ++$stop) return;

	$proposal->submit();

	${'branch'.++$branch.'_array'} = array(0, 48, 49); // the first supporter is the proponent
	$supporter_count = ${'branch'.$branch.'_array'}[${'branch'.$branch}];

	for ( $i=1; $i<=$supporter_count; $i++ ) {
		add_supporter($proposal, $case, "a".$supporter_count."i".$i);
	}

	if ($stopcase == ++$stop) return;

	if ($stopcase == ++$stop) {
		time_warp_cancel($proposal);
		cron();
		return;
	}

	// create alternative proposal
	$proposal2 = new Proposal;
	$proposal2->title = "Test ".$date." alternative proposal case ".$casedesc;
	$proposal2->content = "Test content";
	$proposal2->reason = "Test reason";
	$proposal2->issue = $proposal->issue;
	$proposal2->create("Test proponent ".$date." alternative proposal case ".$casedesc, $area);

	if ($stopcase == ++$stop) return;

	$proposal2->submit();

	${'branch'.++$branch.'_array'} = array(0, 23, 24); // the first supporter is the proponent
	$supporter_count2 = ${'branch'.$branch.'_array'}[${'branch'.$branch}];

	for ( $i=1; $i<=$supporter_count2; $i++ ) {
		add_supporter($proposal2, $case, "a".$supporter_count."b".$supporter_count2."i".$i);
	}

	if ($stopcase == ++$stop) return;

	if ($stopcase == ++$stop) {
		time_warp_cancel($proposal);
		time_warp_cancel($proposal2);
		cron();
		return;
	}

	// create period
	$sql = "INSERT INTO periods (debate, preparation, voting, counting, online_voting, ballot_voting)
		VALUES (
			now() + '1 week'::INTERVAL,
			now() + '2 weeks'::INTERVAL,
			now() + '3 weeks'::INTERVAL,
			now() + '4 weeks'::INTERVAL,
			true,
			false
		) RETURNING id";
	$result = DB::query($sql);
	$row = DB::fetch_row($result);
	$period = $row[0];

	// assign issue to period
	$issue = $proposal->issue();
	$issue->period = $period;
	$issue->update(array("period"));

	// assigned, but not yet started
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "debate"
	time_warp($period, "1 week");
	cron();

	${'branch'.++$branch.'_array'} = array(0, 24, 25);
	$ballot_voting_demanders_count = ${'branch'.$branch.'_array'}[${'branch'.$branch}];

	for ( $i=1; $i<=$ballot_voting_demanders_count; $i++ ) {
		add_ballot_voting_demander($proposal2, $case, "a".$supporter_count."b".$supporter_count2."s".$ballot_voting_demanders_count."i".$i);
	}

	if ($stopcase == ++$stop) return;

	// move on to state "preparation"
	time_warp($period, "1 week");
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "voting"
	time_warp($period, "1 week");
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "counting"
	time_warp($period, "1 week");
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "finished"
	$result = download_vote($issue);
	$issue->save_vote($result);

	if ($stopcase == ++$stop) return;

	// move on to state "cleared"
	time_warp_clear($issue);
	cron();

	// continue with next case if branches are still available
	for ($i=1; $i<=$branch; $i++) {
		if (isset(${'branch'.$i.'_array'}[++${'branch'.$i}])) {
			for ($j=1; $j<$i; $j++) ${'branch'.$j}=0;
			return true;
		}
	}

	// end of last case
	return "end";
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
	Login::$member->create();
	$proposal->add_support();
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
	Login::$member->create();
	$proposal->issue()->demand_ballot_voting();
}


/**
 * move the period times in the past to pretend we moved into the future
 *
 * @param integer $period
 * @param string  $interval (optional)
 */
function time_warp($period, $interval="1 hour") {
	$interval = "'".$interval."'::INTERVAL";
	$sql = "UPDATE periods SET
			debate      = debate      - ".$interval.",
			preparation = preparation - ".$interval.",
			voting      = voting      - ".$interval.",
			counting    = counting    - ".$interval."
		WHERE id=".intval($period);
	DB::query($sql);
}


/**
 * move the issue clearing time in the past to pretend we moved into the future
 *
 * @param object  $issue
 */
function time_warp_clear(Issue $issue) {
	$sql = "UPDATE issues SET clear = clear - ".DB::esc(CLEAR_INTERVAL)."::INTERVAL
		WHERE id=".intval($issue->id)."
			AND clear IS NOT NULL";
	DB::query($sql);
}


/**
 * move the submitted time in the past to pretend we moved into the future
 *
 * @param object  $proposal
 */
function time_warp_cancel(Proposal $proposal) {
	$sql = "UPDATE proposals SET submitted = submitted - ".DB::esc(CANCEL_NOT_ADMITTED_INTERVAL)."::INTERVAL - '1 day'::INTERVAL
		WHERE id=".intval($proposal->id);
	DB::query($sql);
}
