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

	// lists of fields to be inserted or updated by create() and update()
	protected $create_fields;
	protected $update_fields;

	/**
	 * list of all boolean fields
	 *
	 * Boolean attributes must always be saved as PHP true/false/null instead of any database specific convention like "t"/"f" or "0"/"1".
	 */
	protected $boolean_fields = array();

	/**
	 * additional attributes, which are not columns of the table, but have to be also reset on read()
	 */
	protected $dependent_attributes = array();


	/**
	 * make an instance from a database record or convert boolean values after fetch_object
	 *
	 * @param integer $id                (optional)
	 * @param boolean $from_fetch_object (optional)
	 */
	function __construct($id=0, $from_fetch_object=false) {

		if (is_array($id)) {
			trigger_error("Constructor called with array", E_USER_ERROR);
		}

		$this->table = strtolower(get_class($this))."s";

		if ($from_fetch_object) {
			foreach ( $this->boolean_fields as $key ) DB::to_bool($this->$key);
			return;
		}

		if (!$id) return;

		$this->read($id);

	}


	/**
	 * read record from database (again)
	 *
	 * @param integer $id (optional) only needed on new reads
	 */
	public function read($id=0) {

		if (!$id) $id = $this->id;

		$sql = "SELECT * FROM ".$this->table." WHERE id=".intval($id);
		if ( ! $row = DB::fetchassoc($sql) ) return;

		foreach ( $row as $key => $value ) $this->$key = $value;

		foreach ( $this->boolean_fields as $key ) DB::to_bool($this->$key);

		// reset dependent attributes
		foreach ( $this->dependent_attributes as $attribute ) $this->$attribute = null;

	}


	/**
	 * save the current object as a new record in the database
	 *
	 * @param mixed   $fields (optional) array or false
	 * @return boolean
	 */
	public function create($fields=false) {

		if (!$fields) $fields = $this->create_fields;

		$fields_values = array();
		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		return DB::insert($this->table, $fields_values, $this->id);
	}


	/**
	 * save the changed values to the record in the database
	 *
	 * @param mixed   $fields (optional) array or false - save only these fields
	 * @param string  $extra  (optional)
	 * @return resource
	 */
	function update($fields=false, $extra="") {

		if (!$fields) $fields = $this->update_fields;

		$fields_values = array();
		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		return DB::update($this->table, "id=".intval($this->id), $fields_values, $extra);
	}


	/**
	 * delete the record from the database
	 *
	 * @return resource
	 */
	function delete() {
		return DB::delete($this->table, "id=".intval($this->id));
	}


}
