<?
/**
 * Test for many features of DbTableAdmin
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

// You can delete the table after usage.
DB::query('
CREATE TABLE IF NOT EXISTS test_dbtableadmin (
    id serial NOT NULL PRIMARY KEY,
    manualorder integer DEFAULT 0 NOT NULL,
    text text NOT NULL,
    area text NOT NULL,
    "int" integer NOT NULL,
    "boolean" boolean NOT NULL,
    dropdown integer NOT NULL
);
');

$d = new DbTableAdmin_Test("Test_DbTableAdmin");

$options = array("Apfel", "Birne", "Banane", "Traube", "Kirsche");

$d->columns = array(
	array("id", "ID", "right", "", false, 'type'=>"integer"),
	array("manualorder", "Order", "", "manualorder", false, 'nosearch'=>true),
	array("text", "Text"),
	array("area", "Area", "", "text_directedit", "area", 'beforesave'=>"not_empty"),
	array("int", "Integer", "right", "", "", 'type'=>"integer"),
	array("boolean", "Boolean", "center", "boolean_directedit", "boolean", 'type'=>"boolean"),
	array("dropdown", "Dropdown", "", "select", "select", 'options'=>$options, 'type'=>"integer", 'nosearch'=>true),
	array(false, "Independent", "center", "independent", false)
);

$d->enable_delete_checked = true;
$d->enable_duplicate      = true;

$d->action($action);

html_head("DbTableAdmin Test");

$d->filter->filters = array(
	'dropdown' => array(
		""           => "- Select -",
		"dropdown=0" => "Apfel",
		"dropdown=1" => "Birne"
	)
);

$d->display();

html_foot();
