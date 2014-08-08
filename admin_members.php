<?
/**
 * member list for admins
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("admin");

$d = new DbTableAdmin("Member");
$d->dbtable = "members";
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("username", _("Username")),
	array("participant", _("Participant"), "center", "boolean", "boolean", 'disabled'=>true),
	array("activated", _("Participation activated"), "center", "", "display", 'disabled'=>true)
);
$d->enable_filter = false;

$d->enable_insert         = false;
$d->enable_delete_single  = false;

$d->action($action);

html_head(_("Members"));

$d->display();

html_foot();
