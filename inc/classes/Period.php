<?
/**
 * inc/classes/Period.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Period extends Relation {

	public $debate;
	public $preparation;
	public $voting;
	public $counting;
	public $online;
	public $secret;

	protected $boolean_fields = array("online", "secret");


	/**
	 *
	 */
	function save_approved_ballots() {

		foreach ( $_POST['approved_id'] as $key => $ballot_id ) {
			$value = !empty($_POST['approved'][$key]);
			$sql = "UPDATE ballots SET approved=".DB::bool2pg($value)." WHERE id=".intval($ballot_id);
			DB::query($sql);
		}

	}


}
