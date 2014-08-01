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
	 * activate area participation
	 */
	public function activate_participation() {
		$sql = "UPDATE participants SET activated=current_date WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
		$result = DB::query($sql);
		if ( !pg_affected_rows($result) ) {
			$sql = "INSERT INTO participants (member, area) VALUES (".intval(Login::$member->id).", ".intval($this->id).")";
			DB::query($sql);
		}
		// also activate general participation
		Login::$member->activate_participation();
		$this->update_participants_cache();
	}


	/**
	 * deactivate area participation
	 */
	public function deactivate_participation() {
		$sql = "DELETE FROM participants WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
		DB::query($sql);
		$this->update_participants_cache();
	}


	/**
	 * count participants
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
