<?
/**
 * to be inherited by every model class
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class Relation {

	public $id;

	private $table;

	protected $create_fields;
	protected $update_fields;

	/**
	 * list of all boolean fields
	 *
	 * Boolean attributes must always be saved as PHP true/false/null instead of any database specific convention like "t"/"f" or "0"/"1".
	 */
	protected $boolean_fields = array();


	/**
	 * make an instance from a database record or from the provided array
	 *
	 * @param mixed   $id_row (optional)
	 */
	function __construct($id_row=0) {

		$this->table = strtolower(get_class($this))."s";

		if (!$id_row) return;

		if (!is_array($id_row)) {
			$sql = "SELECT * FROM ".$this->table." WHERE id=".intval($id_row);
			if ( ! $id_row = DB::fetchassoc($sql) ) return;
		}

		foreach ( $id_row as $key => $value ) {
			$this->$key = $value;
			if (in_array($key, $this->boolean_fields)) DB::pg2bool($this->$key);
		}

	}


	/**
	 * save the current object as a new record in the database
	 *
	 * @param array   $fields (optional)
	 * @return boolean
	 */
	public function create( $fields=false ) {

		if (!$fields) $fields = $this->create_fields;

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		return DB::insert($this->table, $fields_values, $this->id);

	}


	/**
	 * save the changed values to the record in the database
	 *
	 * @param array   $fields (optional) save only these fields
	 * @return unknown
	 */
	function update( $fields=false ) {

		if (!$fields) $fields = $this->update_fields;

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		return DB::update($this->table, "id=".intval($this->id), $fields_values);

	}


	/**
	 * delete the record from the database
	 *
	 * @return unknown
	 */
	function delete() {

		return DB::delete($this->table, "id=".intval($this->id));

	}


}
