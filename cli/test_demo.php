#!/usr/bin/php
<?
/**
 * generate demo data (in German)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

require DOCROOT."inc/functions_test.php";


$ngroup = new_ngroup("Beispielgliederung", 200);

// create area
$area = new Area;
$area->ngroup = $ngroup->id;
$area->name = "Politik";
$area->create();
$area2 = new Area;
$area2->ngroup = $ngroup->id;
$area2->name = "Innerparteiliches";
$area2->create();


// period in finished state

$sql = "INSERT INTO period (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		now() - interval '4 weeks',
		now() - interval '2 weeks',
		now() - interval '1 week',
		now(),
		true,
		".$ngroup->id."
	) RETURNING id";
$result = DB::query($sql);
$row = DB::fetch_row($result);
$period = $row[0];

create_vote_proposals($period);

cron();
cron();
cron();

random_votes($issue1);
random_votes($issue2);

cron();


// period in voting state

$sql = "INSERT INTO period (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		now() - interval '2 weeks',
		now() - interval '3 days',
		now(),
		now() + interval '2 weeks',
		true,
		".$ngroup->id."
	) RETURNING id";
$result = DB::query($sql);
$row = DB::fetch_row($result);
$period = $row[0];

create_vote_proposals($period);

cron();
cron();
cron();


// period in debate state

login(1);
$proposal10 = new Proposal;
$proposal10->title = "Beispielantrag in der Debatte";
$proposal10->content = $lorem_ipsum;
$proposal10->reason = $lorem_ipsum;
$proposal10->create(Login::$member->username, $area->id);

for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal10->add_proponent(Login::$member->username, true);
}

$proposal10->submit();
$proposal10->read();

for ( $i=6; $i<=25; $i++ ) {
	login($i);
	$proposal10->add_support();
}

$sql = "INSERT INTO period (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		now(),
		now() + interval '2 weeks',
		now() + interval '3 weeks',
		now() + interval '4 weeks',
		true,
		".$ngroup->id."
	) RETURNING id";
$result = DB::query($sql);
$row = DB::fetch_row($result);
$period = $row[0];

$issue10 = $proposal10->issue();
// assign issue to period
$issue10->period = $period;
/** @var $issue Issue */
$issue10->update(['period']);

cron();


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
DB::query("UPDATE proposal SET submitted = now() - interval '7 months' WHERE id=".intval($proposal->id));
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
$proposal2->read();
for ( $i=6; $i<=21; $i++ ) {
	login($i);
	$proposal2->add_support();
}

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
$proposal3->read();
for ( $i=6; $i<=12; $i++ ) {
	login($i);
	$proposal3->add_support();
}

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


/**
 *
 * @param integer $period
 */
function create_vote_proposals($period) {
	global $area, $lorem_ipsum, $proposal1, $proposal2, $proposal3, $issue1, $issue2;

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
	$proposal1->submit();
	$proposal1->read();
	for ( $i=6; $i<=23; $i++ ) {
		login($i);
		$proposal1->add_support();
	}

	$issue1 = $proposal1->issue();
	// assign issue to period
	$issue1->period = $period;
	/** @var $issue Issue */
	$issue1->update(['period']);

	// three proposals
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
	$proposal2->submit();
	$proposal2->read();
	for ( $i=6; $i<=24; $i++ ) {
		login($i);
		$proposal2->add_support();
	}

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
	$proposal3->submit();
	$proposal3->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal3->add_support();
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
	$proposal4->submit();
	$proposal4->read();
	for ( $i=6; $i<=8; $i++ ) {
		login($i);
		$proposal4->add_support();
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
	$proposal5->submit();
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

	$issue2 = $proposal2->issue();
	// assign issue to period
	$issue2->period = $period;
	/** @var $issue Issue */
	$issue2->update(['period']);

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
	$proposal6->submit();
	$proposal6->read();
	for ( $i=6; $i<=23; $i++ ) {
		login($i);
		$proposal6->add_support();
	}

	$issue3 = $proposal6->issue();

	for ( $i=1; $i<=23; $i++ ) {
		login($i);
		$issue3->demand_votingmode();
	}

	// assign issue to period
	$issue3->period = $period;
	/** @var $issue Issue */
	$issue3->update(['period']);

}
