<?
/**
 * included in every page, which is called by http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see about.php
 * @see admin.php
 * @see admin_areas.php
 * @see areas.php
 * @see auth.php
 * @see ballot_edit.php
 * @see ballots.php
 * @see index.php
 * @see local_member_login.php
 * @see login.php
 * @see member.php
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

define("BN", basename($_SERVER['PHP_SELF']));

// buffer output until it either gets displayed on this page or gets written to the session at a redirect
ob_start();

Login::init();

require "inc/locale.php";

// all actions use this global variable
$action = @$_POST['action'];

// action on all pages
if ($action=="logout") {
	Login::logout();
	redirect();
}
