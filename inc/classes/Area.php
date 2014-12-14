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
		$sql = "UPDATE participant SET activated=current_date WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
		$result = DB::query($sql);
		if ( !DB::affected_rows($result) ) {
			$sql = "INSERT INTO participant (member, area) VALUES (".intval(Login::$member->id).", ".intval($this->id).")";
			DB::query($sql);
		}
		$this->update_participants_cache();
	}


	/**
	 * deactivate area participation
	 */
	public function deactivate_participation() {
		$sql = "DELETE FROM participant WHERE member=".intval(Login::$member->id)." AND area=".intval($this->id);
		DB::query($sql);
		$this->update_participants_cache();
	}


	/**
	 * count participants
	 */
	function update_participants_cache() {

		$sql = "SELECT COUNT(1) FROM participant WHERE area=".intval($this->id);
		$count = DB::fetchfield($sql);

		$sql = "UPDATE area SET participants=".intval($count)." WHERE id=".intval($this->id);
		DB::query($sql);

	}


	/**
	 * number of population for quorum
	 *
	 * @return integer
	 */
	function population() {
		return max($this->participants, $this->ngroup()->minimum_population);
	}


}
