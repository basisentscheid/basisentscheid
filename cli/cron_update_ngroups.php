#!/usr/bin/php
<?
/**
 * to be called by a cron job about once per day
 *
 * crontab example:
 * 38 0  * * *  <path>/cli/cron_update_ngroups.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

Ngroup::update_ngroups();
