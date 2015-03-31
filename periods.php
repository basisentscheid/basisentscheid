<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$ngroup = Ngroup::get();

$d = new DbTableAdmin_Period("Period");
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("debate",             _("Debate"),                 "period", "timestamp", "timestamp", 'required'=>true), // 4 weeks before counting
	array("preparation",        _("Voting preparation"),     "period", "timestamp", "timestamp", 'required'=>true), // 1 week before voting
	array("voting",             _("Online voting"),          "period", "timestamp", "timestamp", 'required'=>true), // 2 weeks before counting
	array("counting",           _("Counting/End of period"), "period", "timestamp", "timestamp", 'required'=>true), // "Stichtag"
	array("ballot_voting", _("Ballot voting"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array("ballot_assignment",  _("Ballot assignment"),      "period", "timestamp", "timestamp", 'null'=>true), // 3 weeks before counting
	array("ballot_preparation", _("Ballot preparation"),     "period", "timestamp", "timestamp", 'null'=>true), // 1 week before counting
	array("postage", _("Postage"), "center", "boolean", "postage", 'type'=>"boolean"),
	array(false, _("Ballots"), "center", "ballots", false)
);
if (Login::$admin) {
	$d->columns[] = array("vvvote", _("vvvote"), "center", "boolean", "boolean", 'type'=>"boolean");
}
$d->enable_filter = false;

$d->global_where = array('ngroup' => $ngroup->id);

$d->reference_check = array(
	"SELECT id FROM issue  WHERE period=%d",
	"SELECT id FROM ballot WHERE period=%d"
);

$d->msg_add_record          = _("New period");
$d->msg_edit_record         = _("Edit period %id%");
$d->msg_record_saved        = _("The new period %id% has been saved.");
$d->msg_really_delete       = _("Do you really want to delete the period %id%?");
$d->msg_record_deleted      = _("The period %id% has been deleted.");
$d->msg_record              = _("Period");
$d->msg_no_record_available = _("no period available for this view");
$d->pager->msg_itemsperpage = _("Periods per page");

if (Login::$admin) {
	$d->action($action);
} else {
	$d->enable_insert = false;
	$d->enable_edit   = false;
	$d->enable_delete_single = false;
}

html_head(_("Periods"), true);

$d->display();

html_foot();
