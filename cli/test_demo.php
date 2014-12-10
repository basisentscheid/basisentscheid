#!/usr/bin/php
<?
/**
 * generate demo data
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

require DOCROOT."inc/functions_test.php";


$ngroup = new Ngroup;
$ngroup->id = 10;
$ngroup->name = "Beispielgliederung";
$ngroup->active = true;
$ngroup->minimum_population = 110;
$ngroup->create(['id', 'name', 'active', 'minimum_population']);


// create area
$area = new Area;
$area->ngroup = $ngroup->id;
$area->name = "Beispielthemenbereich";
$area->create();

$blindtext = "Auch gibt es niemanden, der den Schmerz an sich liebt, sucht oder wünscht, nur, weil er Schmerz ist, es sei denn, es kommt zu zufälligen Umständen, in denen Mühen und Schmerz ihm große Freude bereiten können. Um ein triviales Beispiel zu nehmen, wer von uns unterzieht sich je anstrengender körperlicher Betätigung, außer um Vorteile daraus zu ziehen? Aber wer hat irgend ein Recht, einen Menschen zu tadeln, der die Entscheidung trifft, eine Freude zu genießen, die keine unangenehmen Folgen hat, oder einen, der Schmerz vermeidet, welcher keine daraus resultierende Freude nach sich zieht?";

$password = crypt("test");


// period in finished state

$sql = "INSERT INTO periods (debate, preparation, voting, counting, online_voting, ballot_voting, ngroup)
	VALUES (
		now() - interval '4 weeks',
		now() - interval '2 weeks',
		now() - interval '1 week',
		now(),
		true,
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

$sql = "INSERT INTO periods (debate, preparation, voting, counting, online_voting, ballot_voting, ngroup)
	VALUES (
		now() - interval '2 weeks',
		now() - interval '3 days',
		now(),
		now() + interval '2 weeks',
		true,
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
$proposal10->content = $blindtext."\n\n".$blindtext;
$proposal10->reason = $blindtext;
$proposal10->create(Login::$member->username, $area->id);

for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal10->add_proponent(Login::$member->username, true);
}

$proposal10->submit();
$proposal10->read();

for ( $i=6; $i<=15; $i++ ) {
	login($i);
	$proposal10->add_support();
}

$sql = "INSERT INTO periods (debate, preparation, voting, counting, online_voting, ballot_voting, ngroup)
	VALUES (
		now(),
		now() + interval '2 weeks',
		now() + interval '3 weeks',
		now() + interval '4 weeks',
		true,
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
$proposal->content = $blindtext."\n\n".$blindtext;
$proposal->reason = $blindtext;
$proposal->create(Login::$member->username, $area->id);

login(1);
$proposal2 = new Proposal;
$proposal2->title = "zugelassener Beispielantrag";
$proposal2->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal2->reason = $blindtext."\n\n".$blindtext;
$proposal2->create(Login::$member->username, $area->id);

for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal2->add_proponent(Login::$member->username, true);
}

$proposal2->submit();
$proposal2->read();

for ( $i=6; $i<=11; $i++ ) {
	login($i);
	$proposal2->add_support();
}

login(1);
$proposal3 = new Proposal;
$proposal3->title = "zugelassener Alternativantrag";
$proposal3->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal3->reason = $blindtext."\n\n".$blindtext;
$proposal3->issue = $proposal2->issue;
$proposal3->create(Login::$member->username, $area->id);

for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal3->add_proponent(Login::$member->username, true);
}

$proposal3->submit();
$proposal3->read();

for ( $i=6; $i<=7; $i++ ) {
	login($i);
	$proposal3->add_support();
}

login(1);
$proposal4 = new Proposal;
$proposal4->title = "eingereichter Antrag";
$proposal4->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal4->reason = $blindtext."\n\n".$blindtext;
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
$proposal5->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal5->reason = $blindtext."\n\n".$blindtext;
$proposal5->issue = $proposal2->issue;
$proposal5->create(Login::$member->username, $area->id);


/**
 *
 * @param integer $period
 */
function create_vote_proposals($period) {
	global $area, $blindtext, $proposal1, $proposal2, $proposal3, $issue1, $issue2;

	// single proposal
	login(1);
	$proposal1 = new Proposal;
	$proposal1->title = "einzelner Beispielantrag";
	$proposal1->content = $blindtext."\n\n".$blindtext;
	$proposal1->reason = $blindtext;
	$proposal1->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal1->add_proponent(Login::$member->username, true);
	}
	$proposal1->submit();
	$proposal1->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal1->add_support();
	}

	$issue1 = $proposal1->issue();
	// assign issue to period
	$issue1->period = $period;
	/** @var $issue Issue */
	$issue1->update(['period']);

	// two proposals
	login(1);
	$proposal2 = new Proposal;
	$proposal2->title = "Beispielantrag";
	$proposal2->content = $blindtext."\n\n".$blindtext;
	$proposal2->reason = $blindtext;
	$proposal2->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal2->add_proponent(Login::$member->username, true);
	}
	$proposal2->submit();
	$proposal2->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal2->add_support();
	}

	login(1);
	$proposal3 = new Proposal;
	$proposal3->title = "Alternativantrag";
	$proposal3->content = $blindtext."\n\n".$blindtext;
	$proposal3->reason = $blindtext;
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

	$issue2 = $proposal2->issue();
	// assign issue to period
	$issue2->period = $period;
	/** @var $issue Issue */
	$issue2->update(['period']);

}


/**
 * create a member once
 *
 * @param integer $id
 */
function login($id) {
	global $password, $ngroup;

	static $members = array();

	if (isset($members[$id])) {
		Login::$member = $members[$id];
		return;
	}

	$names = array("Donald Duck", "Dagobert Duck", "Tick", "Trick", "Track", "Daisy Duck", "Gustav Gans", "Franz Gans", "Dussel Duck", "Micky Maus", "Goofy", "Pluto", "Minni Maus", "Kommissar Hunter", "Klarabella Kuh", "Kater Karlo", "Gundel Gaukeley", "Hugo Habicht", "Rita Rührig", "Frieda", "Daniel Düsentrieb", "Gitta Gans", "Ede Wolf", "Asterix", "Obelix", "Idefix", "Majestix", "Gutemiene", "Falbala", "Caesar", "Kleopatra", "Verleihnix", "Miraculix", "Automatix", "Troubadix");

	Login::$member = new Member;
	Login::$member->invite = Login::generate_token(24);
	Login::$member->entitled = true;
	Login::$member->create();
	Login::$member->username = $names[$id];
	Login::$member->password = $password;
	$update_fields = array('username', 'password', 'entitled');
	Login::$member->update($update_fields, 'activated=now()');
	DB::insert("members_ngroups", array('member'=>Login::$member->id, 'ngroup'=>$ngroup->id));

	$members[$id] = Login::$member;

}
