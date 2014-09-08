#!/usr/bin/php
<?
/**
 * download the voting result for one voting period
 *
 * to be called by some daemon, triggered by the ID server
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";


if (empty($_SERVER['argv'][1])) {
	trigger_error("Missing parameter", E_USER_ERROR);
}

$period = new Period($_SERVER['argv'][1]);

if (!$period->id) {
	trigger_error("The requested voting period does not exist!", E_USER_ERROR);
}

if ( !$period->download_vote() ) exit(1);
