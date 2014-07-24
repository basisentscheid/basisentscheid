<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Member {

	public $id;
	public $auid;
	public $username;


	/**
	 *
	 * @param unknown $id_row (optional)
	 */
	function __construct($id_row=0) {

		if (!$id_row) return;

		if (!is_array($id_row)) {
			$sql = "SELECT * FROM members WHERE id=".intval($id_row);
			if ( ! $id_row = DB::fetchassoc($sql) ) return;
		}

		foreach ( $id_row as $key => $value ) {
			$this->$key = $value;
		}

	}


	/**
	 * Create a new invoice
	 *
	 * @return boolean
	 * @param unknown $fields (optional)
	 */
	public function create( $fields = array("auid", "username") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::insert("members", $fields_values, $this->id);

	}


	/**
	 *
	 * @param unknown $fields (optional)
	 */
	function update( $fields = array("username") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::update("members", "id=".intval($this->id), $fields_values);

	}


	/**
	 *
	 * @param unknown $username
	 */
	function set_unique_username($username) {

		$this->username = $username;

		$suffix = 0;
		do {
			$sql = "SELECT * FROM members WHERE username=".DB::m($this->username);
			$result = DB::query($sql);
			if ( $exists = pg_num_rows($result) ) {
				$this->username = $username . ++$suffix;
			}
		} while ($exists);

		if ($this->username != $username) {
			notice("The username is already used by someone else, so we added a number to it.");
		}

	}


	/**
	 *
	 * @param unknown $username
	 * @return unknown
	 */
	public function username() {
		return self::username_static($this->username);
	}


	/**
	 *
	 * @param unknown $username
	 * @return unknown
	 */
	public static function username_static($username) {
		if ($username) return $username;
		return _("anonymous");
	}


}
