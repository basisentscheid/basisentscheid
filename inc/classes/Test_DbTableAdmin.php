<?
/**
 * for DbTableAdmin test
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Test_DbTableAdmin extends Relation {

	public $text;
	public $area;
	public $int;
	public $boolean;
	public $dropdown;

	protected $boolean_fields = array('boolean');
	//protected $create_fields = array("text", "area", "integer", "boolean", "dropdown");

	/**
	 *
	 */
	public function dbtableadmin_print_independent() {
		echo "Test";
	}


}
