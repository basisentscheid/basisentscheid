<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ballot {

	public $id;
	public $name;
	public $period;
	public $approved;
	public $opening;


	/**
	 *
	 * @param unknown $id_row (optional)
	 */
	function __construct($id_row=0) {

		if (!$id_row) return;

		if (!is_array($id_row)) {
			$sql = "SELECT * FROM ballots WHERE id=".intval($id_row);
			if ( ! $id_row = DB::fetchassoc($sql) ) return;
		}

		foreach ( $id_row as $key => $value ) {
			$this->$key = $value;
		}
		DB::pg2bool($this->approved);

	}


	/**
	 * Create a new proposal
	 *
	 * @return boolean
	 * @param unknown $fields (optional)
	 */
	public function create( $fields = array("name", "period", "opening") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}
		DB::insert("ballots", $fields_values, $this->id);

		$this->subscribe();

	}


	/**
	 *
	 * @param unknown $fields (optional)
	 */
	function update( $fields = array("name", "opening") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::update("ballots", "id=".intval($this->id), $fields_values);

	}


	/**
	 *
	 */
	public function subscribe() {
		$fields_values = array('member'=>Login::$member->id, 'ballot'=>$this->id);
		$where = DB::convert_fields_values($fields_values);
		DB::insert_or_update("voters", $fields_values, $where);
		$this->update_voters_cache();
	}


	/**
	 *
	 */
	public function unsubscribe() {
		DB::delete("voters", "member=".intval(Login::$member->id)." AND ballot=".intval($this->id));
		$this->update_voters_cache();
	}


	/**
	 *
	 */
	function update_voters_cache() {

		$sql = "SELECT COUNT(1) FROM voters WHERE ballot=".intval($this->id);
		$count = DB::fetchfield($sql);

		$sql = "UPDATE ballots SET voters=".intval($count)." WHERE id=".intval($this->id);
		DB::query($sql);

	}


}
