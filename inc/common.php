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
 * @see admin_ngroups.php
 * @see admins.php
 * @see areas.php
 * @see auth.php
 * @see ballot_edit.php
 * @see ballots.php
 * @see confirm_mail.php
 * @see diff.php
 * @see draft.php
 * @see index.php
 * @see local_member_login.php
 * @see login.php
 * @see member.php
 * @see members.php
 * @see periods.php
 * @see proposal.php
 * @see proposal_edit.php
 * @see proposals.php
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

/*
 * The ngroup can be selected by the GET parameter or by other parameters selecting a period, area, issue or proposal
 * which reference a ngroup. This makes it possible to navigate in different ngroups in multiple browser windows. The
 * session must be used only for navigation!
 */
if (!empty($_GET['ngroup'])) {
	$_SESSION['ngroup'] = intval($_GET['ngroup']);
	switch (BN) {
		// pages which use the ngroup parameter
	case "proposals.php":
	case "periods.php":
	case "areas.php":
	case "admin_areas.php":
		break;
		// jump to different page if the same page doesn't show the equivalent content in other groups
	case "proposal.php":
	case "proposal_edit.php":
	case "draft.php":
		redirect("proposals.php?ngroup=".$_SESSION['ngroup']);
	default:
		// remove ngroup parameter from URLs of ngroup-independent pages
		redirect(URI::strip(array('ngroup')));
	}
} elseif (!isset($_SESSION['ngroup'])) {
	$_SESSION['ngroup'] = 0;
}

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
