<?
/**
 * for DbTableAdmin test
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class DbTableAdmin_Test extends DbTableAdmin {


	/**
	 *
	 * @param unknown $save_columns
	 * @param unknown $msg_prefix
	 * @return unknown
	 */
	function beforesave($save_columns, $msg_prefix) {

		if (in_array("text", $save_columns)) {
			if (!$this->object->text) {
				warning($msg_prefix."The field text must be not empty!");
				return false;
			}
		}

		return true;
	}


	/**
	 *
	 * @param unknown $value
	 * @param unknown $column
	 * @param unknown $msg_prefix
	 * @return unknown
	 */
	function beforesave_not_empty($value, $column, $msg_prefix) {

		if (!$value) {
			warning($msg_prefix."The field ".$column[0]." must be not empty!");
			return false;
		}

		return true;
	}


}
