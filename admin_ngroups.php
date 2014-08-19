<?
/**
 * ngroups
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("admin");

$d = new DbTableAdmin("Ngroup");
$d->dbtable = "ngroups";
$d->columns = array(
	array("id", _("ID"), "right", "", false, 'type'=>"integer"),
	array("parent", "parent", "right", "", false, 'type'=>"integer"),
	array("name", _("Name"))
);
$d->enable_filter = false;

$d->enable_insert         = false;
$d->enable_edit           = false;
$d->enable_delete_single  = false;

$d->action($action);

html_head(_("Groups"));

$d->display();

html_foot();
