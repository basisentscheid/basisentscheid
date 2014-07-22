<?
/**
 * included in every page, which is called by http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see admin.php
 * @see areas.php
 * @see auth.php
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

	session_save_path("var/sessions");
	session_name("BASISENTSCHEIDSESSION");
	ini_set("session.gc_maxlifetime", 86400);
	ini_set("session.use_cookies", 1);
	ini_set("session.use_only_cookies", 1);
	session_start();

	require "inc/locale.php";

	// get logged in member or admin
	$member = false;
	$admin  = false;
	if (!empty($_SESSION['member'])) {
		$member = new Member($_SESSION['member']);
		// automatically logout if member was deleted from the database
		if (!$member->id) {
			$member = false;
			unset($_SESSION['member']);
		}
	} elseif (!empty($_SESSION['admin'])) {
		$admin = new Admin($_SESSION['admin']);
		// automatically logout if admin was deleted from the database
		if (!$admin->id) {
			$admin = false;
			unset($_SESSION['admin']);
		}
	}

	$action = @$_POST['action'];

	// action on all pages
	if ($action=="logout") {
		unset($_SESSION['member'], $_SESSION['admin']);
		redirect();
	}

}
