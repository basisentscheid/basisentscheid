#!/usr/bin/php
<?
/**
 * upload lists of postal voters to the ID server share
 *
 * A final list of postal and ballot voters is sent by cron() when entering ballot preparation phase. This script here is an addition for uploading incomplete lists of postal voters earlier and send the letters in multiple batches. If just everything is sent in the end in one batch, it can not be guaranteed that the voters get their mailings in time. When sending in multiple batches, this risk only affects late members.
 *
 * to be called by a cron job about once per day
 *
 * crontab example:
 * 48 0  * * *  <path>/cli/cron_update_postal_voters.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require DOCROOT."inc/common_cli.php";

// active between postage set and ballot preparation started
$sql_period = "SELECT * FROM period WHERE postage = TRUE AND state != 'ballot_preparation'";
$result_period = DB::query($sql_period);
while ( $period = DB::fetch_object($result_period, "Period") ) {
	/** @var $period Period */
	upload_voters($period);
}
