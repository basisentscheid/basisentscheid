<?
/**
 * included in every page, which is called by http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see admin.php
 * @see areas.php
 * @see auth.php
 * @see ballot_edit.php
 * @see ballots.php
 * @see login.php
 * @see member.php
 * @see periods.php
 * @see proposal.php
 * @see proposal_edit.php
 * @see proposals.php
 * @see share.php
 * @see test_redirect_errors.php
 */


define('DOCROOT', "");

require "inc/config.php";
require "inc/errors.php";
require "inc/functions.php";
require "inc/functions_http.php";

define("BN", basename($_SERVER['PHP_SELF']));


if (php_sapi_name()!="cli") {

	// Ausgabe puffern bis sie entweder auf der Seite ausgegeben oder bei einem Redirect in die Session geschrieben wird
	ob_start();

	Login::init();

	require "inc/locale.php";

	$action = @$_POST['action'];

	// action on all pages
	if ($action=="logout") {
		Login::logout();
		redirect();
	}

}
