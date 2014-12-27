#!/usr/bin/php
<?
/**
 * to be called by a daily cron job at (or shortly after) midnight
 *
 * crontab example:
 * 0 0  * * *  <path>/cli/cron_daily.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require DOCROOT."inc/common_cli.php";

cron_daily();
