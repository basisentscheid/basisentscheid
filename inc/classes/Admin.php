<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Admin {

	public $id;
	public $username;
	public $password;


	/**
	 *
	 * @param unknown $id_row (optional)
	 */
	function __construct($id_row=0) {

		if (!$id_row) return;

		if (!is_array($id_row)) {
			$sql = "SELECT * FROM admins WHERE id=".intval($id_row);
			if ( ! $id_row = DB::fetchassoc($sql) ) return;
		}

		foreach ( $id_row as $key => $value ) {
			$this->$key = $value;
		}

	}


	/**
	 *
	 * @return boolean
	 * @param unknown $fields (optional)
	 */
	public function create( $fields = array("username", "password") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::insert("admins", $fields_values, $this->id);

	}



}
