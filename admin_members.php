<?
/**
 * member list for admins
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("admin");

$d = new DbTableAdmin("Member");
$d->columns = array(
	array("id", _("No."), "right", "", false, 'type'=>"integer"),
	array("invite", _("Invite code"), "", "", "display",                     'disabled'=>true),
	array("created",       _("Created"),       "", "timestamp", "timestamp", 'disabled'=>true, 'nosearch'=>true),
	array("invite_expiry", _("Invite expiry"), "", "timestamp", "timestamp", 'disabled'=>true, 'nosearch'=>true),
	array("activated",     _("Activated"),     "", "timestamp", "timestamp", 'disabled'=>true, 'nosearch'=>true),
	array("identity", _("Identity")),
	array("username", _("Username"), "", "", "display", 'disabled'=>true),
	array("mail",     _("Email"),    "", "", "display", 'disabled'=>true),
	array("mail_unconfirmed", _("Email unconfirmed"), 'required'=>true, 'beforesave'=>"mail_unconfirmed"),
	array("eligible", _("Eligible"), "center", "boolean", "boolean", 'type'=>"boolean", 'nosearch'=>true),
	array("verified", _("Verified"), "center", "boolean", "boolean", 'type'=>"boolean", 'nosearch'=>true),
	array(false, _("Groups"), "", "ngroups", "ngroups", 'noorder'=>true)
);

$d->enable_filter = true;
$d->filter->filters = array(
	'activated' => array(
		''                      => _("all")." ["._("activated")."]",
		'activated IS NOT NULL' => _("activated"),
		'activated IS NULL'     => _("not activated")
	),
	'eligible' => array(
		''                      => _("all")." ["._("eligible")."]",
		'eligible=TRUE'         => _("eligible"),
		'eligible=FALSE'        => _("not eligible")
	),
	'verified' => array(
		''                      => _("all")." ["._("verified")."]",
		'verified=TRUE'         => _("verified"),
		'verified=FALSE'        => _("not verified")
	)
);

if (IMPORT_MEMBERS) {
	$d->enable_edit   = false;
	$d->enable_insert = false;
}
$d->enable_delete_single = false;

$d->msg_edit_record             = _("Edit member %id%");
$d->msg_record                  = _("Member");
$d->msg_no_record_available     = _("no member available for this view");

$d->action($action);

html_head(_("Members"));

$d->display();

html_foot();
