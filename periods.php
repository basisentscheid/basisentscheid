<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Ngroup::get();

$d = new DbTableAdmin_Period("Period");
if (Login::$admin) {
	$d->columns = array(
		/* column name,             column head                       css-class, method print_... edit_... used to display/edit the content */
		array("id", 				_("No."), 						  "right", "", false),
		array("debate",             _("Debate from"),                 "period", "timestamp", "timestamp",     'required'=>true), // 4 weeks before counting
		array("preparation",        _("Voting preparation from"),     "period", "timestamp", "timestamp", 'required'=>true), // 1 week before voting
		array("voting",             _("Online voting from"),          "period", "timestamp", "timestamp", 'required'=>true), // 2 weeks before counting
		array("counting",           _("Counting/End of period from"), "period", "timestamp", "timestamp", 'required'=>true), // "Stichtag"
		array(false,                _("Voting method"),     		  "center", "voting_method", "voting_method", 'type'=>"boolean"), 
	    array("vvvote_vote_delay",  _("Delay voting intervall"),      "period", "text", "interval", 'null'=>true), // each day
	    array("vvvote_last_reg",    _("End of envelops generation"),  "period", "timestamp", "timestamp", 'null'=>true), // 1 hour before counting
		array("ballot_assignment",  _("Ballot assignment from"),      "period", "timestamp", "timestamp", 'null'=>true), // 3 weeks before counting
		array("ballot_preparation", _("Ballot preparation from"),     "period", "timestamp", "timestamp", 'null'=>true), // 1 week before counting
		array("postage",            _("Postage"),                     "center", "boolean",   "postage", 'type'=>"boolean")
	);
} else {
	$d->columns = array(
		array("id", 				_("No."), 						  "right", "", false),
		array("debate",             _("Debate from"),                 "period", "timestamp", "timestamp",     'required'=>true), // 4 weeks before counting
		array("preparation",        _("Voting preparation from"),     "period", "timestamp", "timestamp", 'required'=>true), // 1 week before voting
		array("voting",             _("Online voting from"),          "period", "timestamp", "timestamp", 'required'=>true), // 2 weeks before counting
		array("vvvote_vote_delay",  _("Delay voting intervall"),      "period", "text", "interval", 'null'=>true), // each day
		array("vvvote_last_reg",    _("End of envelops generation"),  "period", "timestamp", "timestamp", 'null'=>true), // 1 hour before counting
		array("counting",           _("Counting/End of period from"), "period", "timestamp", "timestamp", 'required'=>true), // "Stichtag"
	);
}


$d->enable_filter = false;

$d->global_where = array('ngroup' => $_SESSION['ngroup']);

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
