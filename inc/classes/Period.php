<?
/**
 * inc/classes/Period.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Period {

	public $id;
	public $debate;
	public $preparation;
	public $voting;
	public $counting;
	public $online;
	public $secret;


	/**
	 *
	 * @param unknown $id_row (optional)
	 */
	function __construct($id_row=0) {

		if (!$id_row) return;

		if (!is_array($id_row)) {
			$sql = "SELECT * FROM periods WHERE id=".intval($id_row);
			if ( ! $id_row = DB::fetchassoc($sql) ) return;
		}

		foreach ( $id_row as $key => $value ) {
			$this->$key = $value;
		}
		DB::pg2bool($this->online);
		DB::pg2bool($this->secret);

	}


}
