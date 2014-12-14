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
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("username", _("Username"))
);
$d->enable_filter = false;

$d->enable_insert         = false;
$d->enable_delete_single  = false;

$d->msg_edit_record             = _("Edit member %id%");
$d->msg_really_delete           = _("Do you really want to delete the member %id%?");
$d->msg_record_deleted          = _("The member %id% has been deleted.");
$d->msg_record                  = _("Member");
$d->msg_no_record_available     = _("no member available for this view");

$d->action($action);

html_head(_("Members"));

$d->display();

html_foot();
