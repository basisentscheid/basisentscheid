#!/usr/bin/php
<?
/**
 * generate demo data (in German)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require "../inc/common_cli.php";

require "../inc/functions_test.php";


// create groups and areas
list($ngroup, $area) = create_ngroup("Bund");
list($ngroup2) = create_ngroup("LV Bayern", $ngroup);


// period in finished state

$period = create_period($ngroup);
list($issue1, $issue2, $issue3) = create_vote_proposals($period);

cron();
// move to preparation
time_warp($issue1, "2 weeks", true);
time_warp($issue2, "2 weeks");
time_warp($issue3, "2 weeks");
cron();
// move to voting
time_warp($issue1, "3 days", true);
time_warp($issue2, "3 days");
time_warp($issue3, "3 days");
cron();

/** @noinspection PhpUndefinedVariableInspection */
random_votes($issue1);
/** @noinspection PhpUndefinedVariableInspection */
random_votes($issue2);

// move to counting
time_warp($issue1, "2 weeks", true);
time_warp($issue2, "2 weeks");
time_warp($issue3, "2 weeks");
cron();


// period in voting state

$period = create_period($ngroup);
list($issue1, $issue2, $issue3) = create_vote_proposals($period);

cron();
// move to preparation
time_warp($issue1, "2 weeks", true);
time_warp($issue2, "2 weeks");
time_warp($issue3, "2 weeks");
cron();
// move to voting
time_warp($issue1, "3 days", true);
time_warp($issue2, "3 days");
time_warp($issue3, "3 days");
cron();


// period in debate state

login(1);
$proposal10 = new Proposal;
$proposal10->title = "Beispielantrag in der Debatte";
$proposal10->content = $lorem_ipsum;
$proposal10->reason = $lorem_ipsum;
$proposal10->create(Login::$member->username, $area->id);

$issue10 = $proposal10->issue();

for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal10->add_proponent(Login::$member->username, true);
}

time_warp($issue10);
$proposal10->submit();
time_warp($issue10);
$proposal10->read();
for ( $i=6; $i<=25; $i++ ) {
	login($i);
	$proposal10->add_support();
}
time_warp($issue10);

// assign issue to period
$issue10->period = create_period($ngroup);
/** @var $issue Issue */
$issue10->update(['period']);

cron();

// generate comments
comments($proposal10);


// proposals without period

login(0);
$proposal = new Proposal;
$proposal->title = "Neuer Beispielantrag";
$proposal->content = $lorem_ipsum;
$proposal->reason = $lorem_ipsum;
$proposal->create(Login::$member->username, $area->id);


login(0);
$proposal = new Proposal;
$proposal->title = "abgebrochener Beispielantrag";
$proposal->content = $lorem_ipsum;
$proposal->reason = $lorem_ipsum;
$proposal->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal->add_proponent(Login::$member->username, true);
}
$proposal->submit();
// time warp the proposal in the past
time_warp($proposal->issue(), '6 months');
$proposal->read();
cron();


login(1);
$proposal2 = new Proposal;
$proposal2->title = "zugelassener Beispielantrag";
$proposal2->content = $lorem_ipsum;
$proposal2->reason = $lorem_ipsum;
$proposal2->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal2->add_proponent(Login::$member->username, true);
}
$proposal2->submit();

login(1);
$proposal3 = new Proposal;
$proposal3->title = "zugelassener Alternativantrag";
$proposal3->content = $lorem_ipsum;
$proposal3->reason = $lorem_ipsum;
$proposal3->issue = $proposal2->issue;
$proposal3->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal3->add_proponent(Login::$member->username, true);
}
$proposal3->submit();

login(1);
$proposal4 = new Proposal;
$proposal4->title = "eingereichter Antrag";
$proposal4->content = $lorem_ipsum;
$proposal4->reason = $lorem_ipsum;
$proposal4->issue = $proposal2->issue;
$proposal4->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal4->add_proponent(Login::$member->username, true);
}
$proposal4->submit();

login(1);
$proposal5 = new Proposal;
$proposal5->title = "noch nicht eingereichter Antrag";
$proposal5->content = $lorem_ipsum;
$proposal5->reason = $lorem_ipsum;
$proposal5->issue = $proposal2->issue;
$proposal5->create(Login::$member->username, $area->id);

time_warp($proposal2->issue());

$proposal2->read();
for ( $i=6; $i<=21; $i++ ) {
	login($i);
	$proposal2->add_support();
}

$proposal3->read();
for ( $i=6; $i<=12; $i++ ) {
	login($i);
	$proposal3->add_support();
}


/**
 * create group and areas
 *
 * @param string  $name
 * @param object  $parent (optional)
 * @return array
 */
function create_ngroup($name, Ngroup $parent=null) {

	$ngroup = new Ngroup;
	$ngroup->id = DB::fetchfield("SELECT max(id) FROM ngroup") + 1;
	$ngroup->name = $name;
	if ($parent) $ngroup->parent = $parent->id;
	$ngroup->active = true;
	$ngroup->minimum_population = 200;
	$ngroup->minimum_quorum_votingmode = 10;
	$ngroup->create(['id', 'name', 'parent', 'active', 'minimum_population', 'minimum_quorum_votingmode']);

	$area = new Area;
	$area->ngroup = $ngroup->id;
	$area->name = "Politik";
	$area->create();
	$area2 = new Area;
	$area2->ngroup = $ngroup->id;
	$area2->name = "Innerparteiliches";
	$area2->create();

	return [$ngroup, $area];
}


/**
 *
 * @param Ngroup  $ngroup
 * @return integer
 */
function create_period(Ngroup $ngroup) {
	$now = date("Y-m-d H:i:s", strtotime("yesterday 18:00"));
	$sql = "INSERT INTO period (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		timestamp '$now',
		timestamp '$now' + interval '2 weeks',
		timestamp '$now' + interval '2 weeks 3 days',
		timestamp '$now' + interval '4 weeks 3 days',
		false,
		".$ngroup->id."
	) RETURNING id";
	$result = DB::query($sql);
	$row = DB::fetch_row($result);
	return $row[0];
}


/**
 *
 * @param integer $period
 * @return array
 */
function create_vote_proposals($period) {
	global $area, $lorem_ipsum;


	// single proposal
	login(1);
	$proposal1 = new Proposal;
	$proposal1->title = "einzelner Beispielantrag";
	$proposal1->content = $lorem_ipsum;
	$proposal1->reason = $lorem_ipsum;
	$proposal1->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal1->add_proponent(Login::$member->username, true);
	}

	$issue1 = $proposal1->issue();
	// assign issue to period
	$issue1->period = $period;
	/** @var $issue Issue */
	$issue1->update(['period']);

	time_warp($issue1);
	$proposal1->submit();
	time_warp($issue1);
	$proposal1->read();
	for ( $i=6; $i<=23; $i++ ) {
		login($i);
		$proposal1->add_support();
	}
	time_warp($issue1);


	// 4 proposals
	login(1);
	$proposal2 = new Proposal;
	$proposal2->title = "Beispielantrag";
	$proposal2->content = $lorem_ipsum;
	$proposal2->reason = $lorem_ipsum;
	$proposal2->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal2->add_proponent(Login::$member->username, true);
	}

	$issue2 = $proposal2->issue();
	// assign issue to period
	$issue2->period = $period;
	/** @var $issue Issue */
	$issue2->update(['period']);

	login(1);
	$proposal3 = new Proposal;
	$proposal3->title = "Alternativantrag";
	$proposal3->content = $lorem_ipsum;
	$proposal3->reason = $lorem_ipsum;
	$proposal3->issue = $proposal2->issue;
	$proposal3->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal3->add_proponent(Login::$member->username, true);
	}

	login(1);
	$proposal4 = new Proposal;
	$proposal4->title = "nicht zugelassener Alternativantrag";
	$proposal4->content = $lorem_ipsum;
	$proposal4->reason = $lorem_ipsum;
	$proposal4->issue = $proposal2->issue;
	$proposal4->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal4->add_proponent(Login::$member->username, true);
	}

	login(1);
	$proposal5 = new Proposal;
	$proposal5->title = "zurückgezogener Antrag";
	$proposal5->content = $lorem_ipsum;
	$proposal5->reason = $lorem_ipsum;
	$proposal5->issue = $proposal2->issue;
	$proposal5->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal5->add_proponent(Login::$member->username, true);
	}

	time_warp($issue2);

	login(1);
	$proposal2->submit();
	$proposal3->submit();
	$proposal4->submit();
	$proposal5->submit();

	time_warp($issue2);

	$proposal2->read();
	for ( $i=6; $i<=24; $i++ ) {
		login($i);
		$proposal2->add_support();
	}

	$proposal3->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal3->add_support();
	}

	$proposal4->read();
	for ( $i=6; $i<=8; $i++ ) {
		login($i);
		$proposal4->add_support();
	}

	$proposal5->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal5->add_support();
	}
	// revoke by removing all proponents
	for ( $i=1; $i<=5; $i++ ) {
		login($i);
		$proposal5->remove_proponent(Login::$member);
	}

	time_warp($issue2);


	// single proposal for offline voting
	login(1);
	$proposal6 = new Proposal;
	$proposal6->title = "einzelner Beispielantrag";
	$proposal6->content = $lorem_ipsum;
	$proposal6->reason = $lorem_ipsum;
	$proposal6->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal6->add_proponent(Login::$member->username, true);
	}

	$issue3 = $proposal6->issue();
	// assign issue to period
	$issue3->period = $period;
	/** @var $issue Issue */
	$issue3->update(['period']);

	time_warp($issue3);
	$proposal6->submit();
	time_warp($issue3);
	$proposal6->read();
	for ( $i=6; $i<=23; $i++ ) {
		login($i);
		$proposal6->add_support();
	}

	for ( $i=1; $i<=23; $i++ ) {
		login($i);
		$issue3->demand_votingmode();
	}

	time_warp($issue3);


	return [$issue1, $issue2, $issue3];
}


/**
 * generate comments
 *
 * @param Proposal $proposal
 */
function comments(Proposal $proposal) {
	global $lorem_ipsum;

	$rubrics = array("pro", "contra", "discussion");
	$parents = $rubrics;

	for ( $i = 1; $i <= 500; $i++ ) {

		$author = rand(0, 999);
		login($author);

		$parent_id = $parents[array_rand($parents)];

		$comment = new Comment;
		if (in_array($parent_id, $rubrics)) {
			$comment->parent = 0;
			$comment->rubric = $parent_id;
		} else {
			$parent = new Comment($parent_id);
			$comment->parent = $parent->id;
			$comment->rubric = $parent->rubric;
		}
		$comment->proposal = $proposal->id;
		$comment->member = Login::$member->id;
		$comment->title = mb_substr($lorem_ipsum, 0, rand(1, 100));;
		$comment->content = mb_substr($lorem_ipsum, 0, rand(1, 1000));
		$comment->add($proposal);

		$parents[] = $comment->id;

		for ( $j = 1; $j <= rand(0, 100); $j++ ) {
			$user = rand(0, 999);
			// don't try to rate own comments
			if ($user == $author) continue;
			login($user);
			$comment->set_rating(rand(0, 2));
		}

	}

}


/**
 * move all times in the past to pretend we moved into the future
 *
 * @param Issue   $issue
 * @param string  $interval (optional)
 * @param boolean $period   (optional)
 */
function time_warp(Issue $issue, $interval="1 week", $period=false) {
	$interval = "interval '".$interval."'";

	foreach ( array('submitted', 'admitted', 'cancelled', 'revoke') as $column ) {
		$sql = "UPDATE proposal SET
				$column = $column - $interval
			WHERE issue=".intval($issue->id)."
				AND $column IS NOT NULL";
		DB::query($sql);
	}

	$sql = "UPDATE supporter SET created = created - $interval
		WHERE proposal IN (SELECT id FROM proposal WHERE issue=".intval($issue->id).")";
	DB::query($sql);

	foreach ( array('debate_started', 'preparation_started', 'voting_started', 'counting_started', 'clear', 'cleared') as $column ) {
		$sql = "UPDATE issue SET
				$column = $column - $interval
			WHERE id=".intval($issue->id)."
				AND $column IS NOT NULL";
		DB::query($sql);
	}

	if ($issue->period and $period) {
		$sql = "UPDATE period SET
				debate      = debate      - $interval,
				preparation = preparation - $interval,
				voting      = voting      - $interval,
				counting    = counting    - $interval
			WHERE id=".intval($issue->period);
		DB::query($sql);
	}

	cron_daily();
}
