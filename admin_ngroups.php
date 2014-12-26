<?
/**
 * ngroups
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("admin");

$d = new DbTableAdmin("Ngroup");
$d->columns = array(
	array("id", _("ID"), "right", "", false, 'type'=>"integer"),
	array("parent", "parent", "right", "", false, 'type'=>"integer"),
	array("name", _("Name"), 'disabled'=>true),
	array("active", _("active"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array("minimum_population", _("minimum population"), "center", "", "", 'type'=>"integer"),
	array("minimum_quorum_votingmode", _("minimum quorum votingmode"), "center", "", "", 'type'=>"integer")
);
$d->enable_filter = false;

$d->enable_insert         = false;
$d->enable_delete_single  = false;

$d->action($action);

html_head(_("Groups"));

$d->display();

html_foot();
