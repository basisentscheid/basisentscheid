#!/usr/bin/php
<?
/**
 * generate arguments (in German)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

require DOCROOT."inc/functions_test.php";


mb_internal_encoding("UTF-8");

$ngroup = new_ngroup("Argumente", 200);

// create area
$area = new Area;
$area->ngroup = $ngroup->id;
$area->name = "Politik";
$area->create();

login(0);
$proposal = new Proposal;
$proposal->title = "Beispielantrag";
$proposal->content = $lorem_ipsum;
$proposal->reason = $lorem_ipsum;
$proposal->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal->add_proponent(Login::$member->username, true);
}
$proposal->submit();

$parents = array("pro", "contra");

for ( $i = 1; $i <= 1000; $i++ ) {

	login(rand(0, 999));

	$parent_id = $parents[array_rand($parents)];

	$argument = new Argument;
	if ($parent_id=="pro" or $parent_id=="contra") {
		$argument->parent = 0;
		$argument->side = $parent_id;
	} else {
		$parent = new Argument($parent_id);
		$argument->parent = $parent->id;
		$argument->side = $parent->side;
	}
	$argument->proposal = $proposal->id;
	$argument->member = Login::$member->id;
	$argument->title = mb_substr($lorem_ipsum, 0, rand(1, 100));;
	$argument->content = mb_substr($lorem_ipsum, 0, rand(1, 1000));
	$argument->add($proposal);

	$parents[] = $argument->id;

	for ( $j = 1; $j <= rand(0, 100); $j++ ) {
		login(rand(0, 999));
		$argument->set_rating(rand(0, 2));
	}

}
