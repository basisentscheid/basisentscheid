<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class Relation {

	public $id;

	private $table;

	protected $boolean_fields = array();


	/**
	 *
	 * @param unknown $id_row (optional)
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
	 * Create a new proposal
	 *
	 * @return boolean
	 * @param unknown $fields (optional)
	 */
	public function create( $fields=false ) {

		if (!$fields) $fields = $this->create_fields;

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}
		DB::insert($this->table, $fields_values, $this->id);

	}


	/**
	 *
	 * @param unknown $fields (optional)
	 */
	function update( $fields=false ) {

		if (!$fields) $fields = $this->update_fields;

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::update($this->table, "id=".intval($this->id), $fields_values);

	}


}
