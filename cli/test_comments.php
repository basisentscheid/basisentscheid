#!/usr/bin/php
<?
/**
 * generate comments (in German)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

require DOCROOT."inc/functions_test.php";


mb_internal_encoding("UTF-8");

$ngroup = new_ngroup("Diskussion", 200, 10);

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

$rubrics = array("pro", "contra", "discussion");
$parents = $rubrics;

for ( $i = 1; $i <= 1000; $i++ ) {

	login(rand(0, 999));

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
		login(rand(0, 999));
		$comment->set_rating(rand(0, 2));
	}

}
