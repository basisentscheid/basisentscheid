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

$d->msg_add_record              = _("New admin");
$d->msg_edit_record             = _("Edit admin %id%");
$d->msg_record_saved            = _("The new admin %id% has been saved.");
$d->msg_really_delete           = _("Do you really want to delete the admin %id%?");
$d->msg_record_deleted          = _("The admin %id% has been deleted.");
$d->msg_record                  = _("Admin");
$d->msg_no_record_available     = _("no admin available for this view");

$d->action($action);

html_head(_("Admins"));

$d->display();

html_foot();
