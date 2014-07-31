<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$d = new DbTableAdmin_Period("Period");
$d->dbtable = "periods";
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("debate",      _("Debate"),      "center", "timestamp", "timestamp"),
	array("preparation", _("Preparation"), "center", "timestamp", "timestamp"),
	array("voting",      _("Voting"),      "center", "timestamp", "timestamp"),
	array("counting",    _("Counting"),    "center", "timestamp", "timestamp"),
	array("online", _("Online"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array("secret", _("Secret"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array(false, _("Ballots"), "center", "ballots", false)
);
$d->enable_filter = false;

$d->msg_add_record          = _("New period");
$d->msg_edit_record         = _("Edit period %id%");
$d->msg_record_saved        = _("The new period %id% has been saved.");
$d->msg_really_delete       = _("Do you really want to delete the period %id%?");
$d->msg_record_deleted      = _("The period %id% has been deleted.");
$d->msg_record              = _("Period");
$d->msg_no_record_available = _("no period available for this view");

if (Login::$admin) {
	$d->action($action);
} else {
	$d->enable_insert = false;
	$d->enable_edit   = false;
	$d->enable_delete_single = false;
}

html_head(_("Periods"));

$d->display();

html_foot();
