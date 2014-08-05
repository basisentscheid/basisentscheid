<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("admin");

$d = new DbTableAdmin("Admin");
$d->dbtable = "admins";
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("username", _("Username")),
	array("password", _("Password (2x)"), "", false, "password", 'beforesave'=>"password")
);
$d->enable_filter = false;

$d->action($action);

html_head(_("Admins"));

$d->display();

html_foot();
