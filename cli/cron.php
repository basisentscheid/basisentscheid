#!/usr/bin/php
<?
/**
 * to be called by a regular cron job
 *
 * If you for example have your dates at whole hours, it would make sense to run this skript right after the whole hours.
 *
 * crontab example:
 * 0 *  * * *  <path>/cli/cron.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require "../inc/common_cli.php";

cron();
