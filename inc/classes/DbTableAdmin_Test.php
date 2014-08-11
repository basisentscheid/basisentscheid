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
	 * @param array   $save_columns
	 * @param string  $msg_prefix
	 * @return boolean
	 */
	function beforesave(array $save_columns, $msg_prefix) {

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
	 * @param mixed   $value
	 * @param array   $column
	 * @param string  $msg_prefix
	 * @return boolean
	 */
	function beforesave_not_empty($value, array $column, $msg_prefix) {

		if (!$value) {
			warning($msg_prefix."The field ".$column[0]." must be not empty!");
			return false;
		}

		return true;
	}


}
