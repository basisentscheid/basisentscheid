<?
/**
 * inc/classes/Issue.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Area {

	public $id;
	public $name;
	public $participants;


	/**
	 *
	 * @param unknown $id_row (optional)
	 */
	function __construct($id_row=0) {

		if (!$id_row) return;

		if (!is_array($id_row)) {
			$sql = "SELECT * FROM areas WHERE id=".intval($id_row);
			if ( ! $id_row = DB::fetchassoc($sql) ) return;
		}

		foreach ( $id_row as $key => $value ) {
			$this->$key = $value;
		}

	}


	/**
	 *
	 */
	public function subscribe() {
		global $member;

		$sql = "UPDATE participants SET activated=current_date WHERE member=".intval($member->id)." AND area=".intval($this->id);
		$result = DB::query($sql);
		if ( !pg_affected_rows($result) ) {
			$sql = "INSERT INTO participants (member, area) VALUES (".intval($member->id).", ".intval($this->id).")";
			DB::query($sql);
		}

		$this->update_participants_cache();
	}


	/**
	 *
	 */
	public function unsubscribe() {
		global $member;

		$sql = "DELETE FROM participants WHERE member=".intval($member->id)." AND area=".intval($this->id);
		DB::query($sql);
		$this->update_participants_cache();
	}


	/**
	 *
	 */
	function update_participants_cache() {

		$sql = "SELECT COUNT(1) FROM participants WHERE area=".intval($this->id);
		$count = DB::fetchfield($sql);

		$sql = "UPDATE areas SET participants=".intval($count)." WHERE id=".intval($this->id);
		DB::query($sql);

	}


	/**
	 *
	 * @return unknown
	 */
	function population() {
		return max($this->participants, MINIMUM_POPULATION);
	}


}
