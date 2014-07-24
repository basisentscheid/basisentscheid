<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ballot {

	public $id;
	public $name;
	public $agents;
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
	public function create( $fields = array("name", "agents", "period", "opening") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}
		DB::insert("ballots", $fields_values, $this->id);

		$this->select();

	}


	/**
	 *
	 * @param unknown $fields (optional)
	 */
	function update( $fields = array("name", "agents", "opening") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::update("ballots", "id=".intval($this->id), $fields_values);

	}


	/**
	 *
	 * @param unknown $agent (optional)
	 */
	public function select($agent=false) {
		$fields_values = array('member'=>Login::$member->id, 'period'=>$this->period, 'ballot'=>$this->id, 'agent'=>DB::bool2pg($agent));
		$where = "member=".intval(Login::$member->id)." AND period=".intval($this->period);
		DB::insert_or_update("voters", $fields_values, $where);
		self::update_voters_cache($this->period);
	}


	/**
	 *
	 * @param unknown $period
	 */
	public static function unselect($period) {
		DB::delete("voters", "member=".intval(Login::$member->id)." AND period=".intval($period));
		self::update_voters_cache($period);
	}


	/**
	 *
	 * @param unknown $period
	 */
	static function update_voters_cache($period) {

		$sql = "SELECT id FROM ballots WHERE period=".intval($period);
		$result = DB::query($sql);
		while ( $row = pg_fetch_assoc($result) ) {

			$sql = "SELECT COUNT(1) FROM voters WHERE ballot=".intval($row['id']);
			$count = DB::fetchfield($sql);

			$sql = "UPDATE ballots SET voters=".intval($count)." WHERE id=".intval($row['id']);
			DB::query($sql);

		}

	}


}
