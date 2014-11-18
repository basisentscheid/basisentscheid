<?

/**
 * Area
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Area extends Relation {

	public $name;
	public $participants;
	public $ngroup;

	private $ngroup_obj;

	protected $create_fields = array('ngroup', 'name');


	/**
	 * get the ngroup this area belongs to
	 *
	 * @return object
	 */
	function ngroup() {
		if (!is_object($this->ngroup_obj)) $this->ngroup_obj = new Ngroup($this->ngroup);
		return $this->ngroup_obj;
	}


	/**
	 * activate area participation
	 */
	public function activate_participation() {
		$sql = "UPDATE participants SET activated=current_date WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
		$result = DB::query($sql);
		if ( !DB::affected_rows($result) ) {
			$sql = "INSERT INTO participants (member, area) VALUES (".intval(Login::$member->id).", ".intval($this->id).")";
			DB::query($sql);
		}
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
	 * number of population for quorum
	 *
	 * @return integer
	 */
	function population() {
		return max($this->participants, MINIMUM_POPULATION);
	}


}
