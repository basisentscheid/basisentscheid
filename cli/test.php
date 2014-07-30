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
while ( !create_case($case) ) $case++;


/**
 *
 * @param unknown $case
 * @return unknown
 */
function create_case($case) {
	global $date, $login;

	$stop = 0;

	Login::$member = $login;

	// create area
	$area = 0;
	DB::insert("areas", array('name'=>"Test ".$case." area"), $area);

	// create new proposal
	$proposal = new Proposal;
	$proposal->proponents = "Test proponent 1 #1001, Test proponent 2 #1002, Test proponent 3 #1003, Test proponent 4 #1004, Test proponent 5 #1005";
	$proposal->title = "Test proposal case ".$case;
	$proposal->content = "Test content";
	$proposal->reason = "Test reason";
	$proposal->create($area);

	if ($case == ++$stop) return;

	// create period
	$sql = "INSERT INTO periods (debate, preparation, voting, counting, online, secret)
		VALUES (
			now() + '1 hours'::INTERVAL - ".DB::m($case." minutes")."::INTERVAL,
			now() + '2 hours'::INTERVAL - ".DB::m($case." minutes")."::INTERVAL,
			now() + '3 hours'::INTERVAL - ".DB::m($case." minutes")."::INTERVAL,
			now() + '4 hours'::INTERVAL - ".DB::m($case." minutes")."::INTERVAL,
			true,
			true
		) RETURNING id";
	$result = DB::query($sql);
	$row = pg_fetch_row($result);
	$period = $row[0];

	// one less than required
	for ( $i=1; $i<$proposal->quorum_required()-1; $i++ ) {
		add_supporter($proposal, $case, $i);
	}

	if ($case == ++$stop) return;

	// exactly as many as required
	add_supporter($proposal, $case, ++$i);

	if ($case == ++$stop) return;

	// one more than required
	add_supporter($proposal, $case, ++$i);

	if ($case == ++$stop) return;

	// create alternative proposal
	$proposal2 = new Proposal;
	$proposal2->proponents = "Test proponent 1 #1001, Test proponent 2 #1002, Test proponent 3 #1003, Test proponent 4 #1004, Test proponent 5 #1005";
	$proposal2->title = "Test alternative proposal case ".$case;
	$proposal2->content = "Test content";
	$proposal2->reason = "Test reason";
	$proposal2->issue = $proposal->issue;
	$proposal2->create($area);

	if ($case == ++$stop) return;

	// one less than required
	for ( $i=1; $i<$proposal2->quorum_required()-1; $i++ ) {
		add_supporter($proposal2, $case, $i);
	}

	if ($case == ++$stop) return;

	// exactly as many as required
	add_supporter($proposal2, $case, ++$i);

	if ($case == ++$stop) return;

	// one more than required
	add_supporter($proposal2, $case, ++$i);

	if ($case == ++$stop) return;

	// move on to state "debate"
	time_warp($period);
	cron();

	if ($case == ++$stop) return;

	// one less than required
	for ( $i=1; $i<$proposal2->issue()->secret_required()-1; $i++ ) {
		add_secretdemander($proposal2, $case, $i);
	}

	if ($case == ++$stop) return;

	// exactly as many as required
	add_secretdemander($proposal2, $case, ++$i);

	if ($case == ++$stop) return;

	// one more than required
	add_secretdemander($proposal2, $case, ++$i);

	if ($case == ++$stop) return;

	// move on to state "preparation"
	time_warp($period);
	cron();

	if ($case == ++$stop) return;

	// move on to state "voting"
	time_warp($period);
	cron();

	if ($case == ++$stop) return;

	// move on to state "counting"
	time_warp($period);
	cron();

	if ($case == ++$stop) return;

	// move on to state "finished"
	download_vote($proposal->issue());

	if ($case == ++$stop) return;

	// move on to state "cleared"
	time_warp_clear($period);
	cron();

	// end of last case
	return true;
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
	Login::$member->username = "testc".$case."p".$proposal->id."s".$i;
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
	Login::$member->username = "testc".$case."p".$proposal->id."g".$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->create();
	$proposal->issue()->demand_secret();
}


/**
 *
 * @param unknown $period
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
 *
 * @param unknown $period
 */
function time_warp_clear($period) {
	$sql = "UPDATE issues SET clear = clear - ".DB::m(CLEAR_INTERVAL)."::INTERVAL
		WHERE clear IS NOT NULL AND period=".intval($period);
	DB::query($sql);
}
