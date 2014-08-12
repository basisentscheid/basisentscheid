<?
/**
 * member list for members
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

$d = new DbTableAdmin("Member");
$d->dbtable = "members";
$d->columns = array(
	array("username", _("Username"))
);
$d->enable_filter = false;

$d->enable_insert         = false;
$d->enable_edit           = false;
$d->enable_delete_single  = false;

$d->order_default = "username";

$d->action($action);

html_head(_("Members"));

$d->display();

html_foot();
