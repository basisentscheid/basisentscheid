<?
/**
 * inc/classes/Issue.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Area extends Relation {

	public $name;
	public $participants;


	/**
	 *
	 */
	public function subscribe() {
		$sql = "UPDATE participants SET activated=current_date WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
		$result = DB::query($sql);
		if ( !pg_affected_rows($result) ) {
			$sql = "INSERT INTO participants (member, area) VALUES (".intval(Login::$member->id).", ".intval($this->id).")";
			DB::query($sql);
		}
		$this->update_participants_cache();
	}


	/**
	 *
	 */
	public function unsubscribe() {
		$sql = "DELETE FROM participants WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
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
