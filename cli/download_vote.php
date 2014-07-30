#!/usr/bin/php
<?
/**
 * download the voting result for one issue
 *
 * to be called by some daemon, triggered by the ID server
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);

define('DOCROOT', "../");
require "../inc/common_cli.php";


if (empty($_SERVER['argv'][1])) {
	trigger_error("Missing parameter", E_USER_ERROR);
}

$issue = new Issue($_SERVER['argv'][1]);

if (!$issue->id) {
	trigger_error("The requested issue does not exist!", E_USER_ERROR);
}

if ( !download_vote($issue) ) exit(1);
