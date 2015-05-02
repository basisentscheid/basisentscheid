<?
/**
 * included in every page, which is called by http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see about.php
 * @see admin_areas.php
 * @see admin_members.php
 * @see admin_ngroups.php
 * @see admin_vote_result.php
 * @see admins.php
 * @see areas.php
 * @see ballot_edit.php
 * @see ballots.php
 * @see confirm_mail.php
 * @see create_member.php
 * @see diff.php
 * @see draft.php
 * @see index.php
 * @see login.php
 * @see manual.php
 * @see member.php
 * @see periods.php
 * @see proposal.php
 * @see proposal_edit.php
 * @see proposals.php
 * @see register.php
 * @see request_password_reset.php
 * @see reset_password.php
 * @see settings.php
 * @see settings_encryption.php
 * @see settings_notifications.php
 * @see test_dbtableadmin.php
 * @see test_mail.php
 * @see test_redirect_errors.php
 * @see vote.php
 * @see vote_result.php
 * @see votingmode_result.php
 */


const DOCROOT = "";

require "inc/config.php";
require "inc/errors.php";
require "inc/common.php";
require "inc/functions.php";
require "inc/functions_http.php";

// buffer output until it either gets displayed on this page or gets written to the session at a redirect
ob_start();

require "inc/locale.php";

Login::init(); // start session

Ngroup::init(); // get current Ngroup

// all actions use this global variable
if (isset($_POST['action'])) $action = $_POST['action']; else $action = null;

// detect CSRF attacks
// To make this work the 'action' parameter should be used on every POST form.
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
	success(_("You have logged out."));
	redirect();
case "hide_help":
	hide_help();
	redirect();
case "show_help":
	show_help();
	redirect();
}
