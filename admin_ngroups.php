<?
/**
 * ngroups
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("admin");

$options = array(0 => "-");
$sql = "SELECT id, name FROM ngroup ORDER BY id";
$result = DB::query($sql);
while ( $row = DB::fetch_assoc($result) ) {
	$options[$row['id']] = $row['id']." ".$row['name'];
}

$d = new DbTableAdmin("Ngroup");
$d->columns = array(
	array("id", _("ID"), "right", "", false, 'type'=>"integer"),
	array("parent", "parent", "right", "", IMPORT_MEMBERS?false:"select", 'options'=>$options, 'null'=>true),
	array("name", _("Name"), "", "", IMPORT_MEMBERS?"display":"", 'required'=>"true"),
	array("active", _("active"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array("minimum_population",        _("minimum population"),        "center", "", "", 'type'=>"integer", 'required'=>"true"),
	array("minimum_quorum_votingmode", _("minimum quorum votingmode"), "center", "", "", 'type'=>"integer", 'required'=>"true")
);
$d->enable_filter = false;

if (IMPORT_MEMBERS) {
	$d->enable_insert = false;
}
$d->enable_delete_single  = false;

$d->action($action);

html_head(_("Groups"));

$d->display();

html_foot();
