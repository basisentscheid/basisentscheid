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

// to avoid conflicts with existing usernames
$date = dechex(time());

$ngroup = new Ngroup(1);

// create main member
$login = new Member;
$login->username = "t".$date."login";
$login->auid = $login->username;
$login->entitled = true;
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
	global $date, $login, $ngroup;

	$stop = 0;
	$branch = 0;
	static $branch1 = 0;
	$casedesc = $case." (".$stopcase."/".$branch1.")";
	echo "Test case ".$casedesc."\n";

	Login::$member = $login;

	// create period
	if ($stopcase == ++$stop) {
		// period without ballot voting
		$sql = "INSERT INTO periods (debate, preparation, voting, ballot_assignment, ballot_preparation, counting, online_voting, ballot_voting, ngroup)
		VALUES (
			now(),
			now() + interval '1 week',
			now() + interval '2 weeks',
			NULL,
			NULL,
			now() + interval '4 weeks',
			true,
			false,
			".$ngroup->id."
		)";
		DB::query($sql);
		return;
	} else {
		// period with ballot voting
		$sql = "INSERT INTO periods (debate, preparation, voting, ballot_assignment, ballot_preparation, counting, online_voting, ballot_voting, ngroup, postage)
		VALUES (
			now(),
			now() + interval '1 week',
			now() + interval '2 weeks',
			now() + interval '1 week',
			now() + interval '3 weeks',
			now() + interval '4 weeks',
			true,
			true,
			".$ngroup->id.",
			true
		) RETURNING id";
		$result = DB::query($sql);
		$row = DB::fetch_row($result);
		$period = new Period($row[0]);
	}

	${'branch'.++$branch.'_array'} = array(0, 5, 15);
	$ballot_count = ${'branch'.$branch.'_array'}[${'branch'.$branch}];

	for ( $i=1; $i<=$ballot_count; $i++ ) {
		// create a ballot
		$ballot = new Ballot;
		$ballot->ngroup = $ngroup->id;
		$ballot->name = "Test ballot ".$casedesc;
		$ballot->agents = "Test agents";
		$ballot->period = $period->id;
		$ballot->opening = "8:00";
		$ballot->create();
		// add participants
		for ( $j=1; $j<=$i-1; $j++ ) {
			add_participant($period, $ballot, $case, "a".$ballot_count."i".$i."j".$j);
		}
	}

	// add postal voters
	for ( $j=1; $j<=10; $j++ ) {
		add_participant($period, true, $case, "a".$ballot_count."i0j".$j);
	}

	if ($stopcase == ++$stop) return;

	// approve ballots with 10 or more participants
	$sql = "SELECT * FROM ballots WHERE period=".intval($period->id);
	$result = DB::query($sql);
	while ( $ballot = DB::fetch_object($result, "Ballot") ) {
		if ($ballot->voters < 10) continue;
		$ballot->approved = true;
		$ballot->update(array("approved"));
	}

	if ($stopcase == ++$stop) return;

	// add further participants without assigning them to ballots
	for ($i=1; $i<=100; $i++) {
		add_participant($period, null, $case, "t".$date."c".$case."i".$i);
	}

	// move to phase "ballot_assignment"
	time_warp($period, "1 week");
	cron();

	if ($stopcase == ++$stop) return;

	// move to phase "ballot_preparation"
	time_warp($period, "2 weeks");
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
 * create a new member and let it become participant of the supplied ballot
 *
 * @param Period  $period
 * @param mixed   $ballot Ballot, true or null
 * @param integer $case
 * @param string  $i
 */
function add_participant(Period $period, $ballot, $case, $i) {
	global $date;

	Login::$member = new Member;
	Login::$member->username = "t".$date."c".$case."p".(is_object($ballot)?$ballot->id:$ballot).$i;
	Login::$member->auid = Login::$member->username;
	Login::$member->entitled = true;
	Login::$member->create();
	Login::$member->update_ngroups(array(1));
	if ($ballot) {
		if (is_object($ballot)) $period->select_ballot($ballot); else $period->select_postal();
	}
}


/**
 * move the period times in the past to pretend we moved into the future
 *
 * @param Period  $period
 * @param string  $interval (optional)
 */
function time_warp(Period $period, $interval="1 hour") {
	$interval = "interval '".$interval."'";
	$sql = "UPDATE periods SET
			debate             = debate             - ".$interval.",
			preparation        = preparation        - ".$interval.",
			voting             = voting             - ".$interval.",
			ballot_assignment  = ballot_assignment  - ".$interval.",
			ballot_preparation = ballot_preparation - ".$interval.",
			counting           = counting           - ".$interval."
		WHERE id=".intval($period->id);
	DB::query($sql);
}
