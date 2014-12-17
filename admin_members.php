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
	array("id", _("No."), "right", "", false, 'type'=>"integer"),
	array("invite", _("Invite code")),
	array("created", _("Created"), "", "timestamp", 'nosearch'=>true),
	array("invite_expiry", _("Invite expiry"), "", "timestamp", 'nosearch'=>true),
	array("activated", _("Activated"), "", "timestamp", 'nosearch'=>true),
	array("username", _("Username")),
	array("mail", _("Mail")),
	array("mail_unconfirmed", _("Mail unconfirmed")),
	array("eligible", _("Eligible"), "center", "boolean", 'nosearch'=>true),
	array("verified", _("Verified"), "center", "boolean", 'nosearch'=>true),
	array("", _("Groups"), "", "ngroups", 'noorder'=>true)
);

$d->enable_filter = true;
$d->filter->filters = array(
	'activated' => array(
		''                      => _("all")." ["._("activated")."]",
		'activated IS NOT NULL' => _("activated"),
		'activated IS NULL'     => _("not activated")
	),
	'eligible' => array(
		''                      => _("all")." ["._("elegible")."]",
		'eligible=TRUE'         => _("eligible"),
		'eligible=FALSE'        => _("not eligible")
	),
	'verified' => array(
		''                      => _("all")." ["._("verified")."]",
		'verified=TRUE'         => _("verified"),
		'verified=FALSE'        => _("not verified")
	)
);

$d->enable_edit           = false;
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
