<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("admin");

$ngroup = Ngroup::get();

$d = new DbTableAdmin("Area");
$d->dbtable = "areas";
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("name", _("Name")),
	array("participants", _("Participants"), "center", "", false)
);
$d->enable_filter = false;

$d->global_where = array('ngroup' => $ngroup->id);

$d->reference_check = array("SELECT id FROM issues WHERE area=%d");

$d->action($action);

html_head(_("Subject areas"));

$d->display();

html_foot();
