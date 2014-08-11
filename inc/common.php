<?
/**
 * included in every page, which is called by http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see about.php
 * @see admin.php
 * @see admin_areas.php
 * @see admin_members.php
 * @see admins.php
 * @see areas.php
 * @see auth.php
 * @see ballot_edit.php
 * @see ballots.php
 * @see index.php
 * @see local_member_login.php
 * @see login.php
 * @see member.php
 * @see members.php
 * @see periods.php
 * @see proposal.php
 * @see proposal_edit.php
 * @see proposals.php
 * @see share.php
 * @see test_dbtableadmin.php
 * @see test_redirect_errors.php
 */


define('DOCROOT', "");

require "inc/config.php";
require "inc/errors.php";
require "inc/functions.php";
require "inc/functions_http.php";

define("VERSION", "development");

define("BN", basename($_SERVER['PHP_SELF']));

// buffer output until it either gets displayed on this page or gets written to the session at a redirect
ob_start();

Login::init();

require "inc/locale.php";

// all actions use this global variable
$action = @$_POST['action'];

// detect CSRF attacks
if ($action) {
	if (empty($_POST['csrf'])) {
		error("CSRF token missing in POST request!");
	}
	if ($_POST['csrf'] != $_SESSION['csrf']) {
		error("CSRF token does not match!");
	}
}

// action on all pages
switch ($action) {
case "logout":
	Login::logout();
	redirect();
case "hide_help":
	Login::access_action("member");
	Login::$member->hide_help(BN);
	redirect();
case "show_help":
	Login::access_action("member");
	Login::$member->show_help(BN);
	redirect();
}
