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
require "../inc/common_cli.php";

$date = date("Y-m-d_H-i-s");


// delete everything
DB::query("TRUNCATE periods CASCADE");
DB::query("TRUNCATE members CASCADE");
DB::query("TRUNCATE areas CASCADE");


// create main member
$login = new Member;
$login->username = "test".$date."login";
$login->auid = $login->username;
$login->create();


// go through all cases
$case = 1;
do {
	$stopcase = 1;
	while ( ! $return = create_case($case, $stopcase) ) {
		$case++;
		$stopcase++;
	}
} while ( $return !== "end" );


/**
 * create one test case
 *
 * @param integer $case
 * @param unknown $stopcase
 * @return boolean true after last case
 */
function create_case($case, $stopcase) {
	global $date, $login;

	$stop = 0;

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
	$proposal->proponents = "Test proponent 1 #1001, Test proponent 2 #1002, Test proponent 3 #1003, Test proponent 4 #1004, Test proponent 5 #1005";
	$proposal->title = "Test proposal case ".$casedesc;
	$proposal->content = "Test content";
	$proposal->reason = "Test reason";
	$proposal->create($area);

	$branch1_array = array(0, 48, 49); // the first supporter is the proponent
	$supporter_count = $branch1_array[$branch1];

	for ( $i=1; $i<=$supporter_count; $i++ ) {
		add_supporter($proposal, $case, "a".$supporter_count."i".$i);
	}

	if ($stopcase == ++$stop) return;

	// create alternative proposal
	$proposal2 = new Proposal;
	$proposal2->proponents = "Test proponent 1 #1001, Test proponent 2 #1002, Test proponent 3 #1003, Test proponent 4 #1004, Test proponent 5 #1005";
	$proposal2->title = "Test alternative proposal case ".$casedesc;
	$proposal2->content = "Test content";
	$proposal2->reason = "Test reason";
	$proposal2->issue = $proposal->issue;
	$proposal2->create($area);

	$branch2_array = array(0, 23, 24); // the first supporter is the proponent
	$supporter_count2 = $branch2_array[$branch2];

	for ( $i=1; $i<=$supporter_count2; $i++ ) {
		add_supporter($proposal2, $case, "a".$supporter_count."b".$supporter_count2."i".$i);
	}

	if ($stopcase == ++$stop) return;

	// create period
	$sql = "INSERT INTO periods (debate, preparation, voting, counting, online, secret)
		VALUES (
			now() + '1 hours'::INTERVAL,
			now() + '2 hours'::INTERVAL,
			now() + '3 hours'::INTERVAL,
			now() + '4 hours'::INTERVAL,
			true,
			true
		) RETURNING id";
	$result = DB::query($sql);
	$row = pg_fetch_row($result);
	$period = $row[0];

	// assign issue to period
	$issue = $proposal->issue();
	$issue->period = $period;
	$issue->update(array("period"));

	// assigned, but not yet started
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "debate"
	time_warp($period);
	cron();

	$branch3_array = array(0, 24, 25);
	$secret_count = $branch3_array[$branch3];

	for ( $i=1; $i<=$secret_count; $i++ ) {
		add_secretdemander($proposal2, $case, "a".$supporter_count."b".$supporter_count2."s".$secret_count."i".$i);
	}

	if ($stopcase == ++$stop) return;

	// move on to state "preparation"
	time_warp($period);
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "voting"
	time_warp($period);
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "counting"
	time_warp($period);
	cron();

	if ($stopcase == ++$stop) return;

	// move on to state "finished"
	download_vote($proposal->issue());

	if ($stopcase == ++$stop) return;

	// move on to state "cleared"
	time_warp_clear($issue);
	cron();

	// continue with next case if branches are still available
	if (isset($branch3_array[++$branch3])) return true;
	if (isset($branch2_array[++$branch2])) { $branch3=0; return true; }
	if (isset($branch1_array[++$branch1])) { $branch3=0; $branch2=0; return true; }

	// end of last case
	return "end";
}


/**
 *
 * @param unknown $proposal
 * @param unknown $case
 * @param unknown $i
 */
function add_supporter($proposal, $case, $i) {
	global $date;

	Login::$member = new Member;
	Login::$member->username = "testc".$case."p".$proposal->id.$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->create();
	$proposal->add_support();
}


/**
 *
 * @param unknown $proposal
 * @param unknown $case
 * @param unknown $i
 */
function add_secretdemander($proposal, $case, $i) {
	global $date;

	Login::$member = new Member;
	Login::$member->username = "testc".$case."p".$proposal->id.$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->create();
	$proposal->issue()->demand_secret();
}


/**
 * move the period times in the past to pretend we moved in the future
 *
 * @param integer $period
 */
function time_warp($period) {
	$sql = "UPDATE periods SET
			debate      = debate      - '1 hour'::INTERVAL,
			preparation = preparation - '1 hour'::INTERVAL,
			voting      = voting      - '1 hour'::INTERVAL,
			counting    = counting    - '1 hour'::INTERVAL
		WHERE id=".intval($period);
	DB::query($sql);
}


/**
 * move the issue clearing time in the past to pretend we moved in the future
 *
 * @param object  $issue
 */
function time_warp_clear($issue) {
	$sql = "UPDATE issues SET clear = clear - ".DB::m(CLEAR_INTERVAL)."::INTERVAL
		WHERE id=".intval($issue->id)."
			AND clear IS NOT NULL";
	DB::query($sql);
}
