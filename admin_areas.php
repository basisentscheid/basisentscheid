<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("admin");

Ngroup::get();

$d = new DbTableAdmin("Area");
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("name", _("Name"), 'size'=>30, 'required'=>true),
	array("participants", _("Participants"), "center", "", false)
);
$d->enable_filter = false;

$d->global_where = array('ngroup' => $_SESSION['ngroup']);

$d->reference_check = array("SELECT id FROM issue WHERE area=%d");

$d->msg_add_record              = _("New area");
$d->msg_edit_record             = _("Edit area %id%");
$d->msg_record_saved            = _("The new area %id% has been saved.");
$d->msg_really_delete           = _("Do you really want to delete the area %id%?");
$d->msg_record_deleted          = _("The area %id% has been deleted.");
$d->msg_record                  = _("Area");
$d->msg_no_record_available     = _("no area available for this view");

$d->action($action);

html_head(_("Subject areas"));

$d->display();

html_foot();
