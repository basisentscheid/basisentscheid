#!/usr/bin/php
<?
/**
 * to be called by a regular cron job
 *
 * crontab example:
 * 0 *  * * *  <path>/cli/cron.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

cron(true);
