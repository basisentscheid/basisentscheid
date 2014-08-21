<?
/**
 * Ngroup (nested group)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ngroup extends Relation {


	/**
	 * get the current Ngroup object from GET or redirect to the default Ngroup
	 *
	 * $_SESSION['ngroup'] must be used for navigation only, not for actual content or action!
	 *
	 * @return object
	 */
	public static function get() {

		if (!empty($_GET['ngroup'])) {
			$ngroup = new Ngroup($_GET['ngroup']);
			if ($ngroup->id) {
				// override differing GET parameter
				$_SESSION['ngroup'] = $ngroup->id;
				return $ngroup;
			}
		}

		// redirect to ngroup from session
		if (!empty($_SESSION['ngroup'])) {
			$ngroup = new Ngroup($_SESSION['ngroup']);
			if ($ngroup->id) {
				redirect(BN."?ngroup=".$ngroup->id);
			}
		}

		// redirect to default ngroup
		$default_ngroup = DB::fetchfield("SELECT id FROM ngroups ORDER BY id LIMIT 1");
		if (!$default_ngroup) error(_("No groups found"));
		redirect(BN."?ngroup=".$default_ngroup);
	}


	/**
	 * activate general participation (distinct from area participation)
	 */
	public function activate_participation() {
		$where = "member=".intval(Login::$member->id)." AND ngroup=".intval($this->id);
		DB::update("members_ngroups", $where, array(), 'participant=current_date');
	}


	/**
	 * deactivate general participation
	 */
	public function deactivate_participation() {
		$where = "member=".intval(Login::$member->id)." AND ngroup=".intval($this->id);
		DB::update("members_ngroups", $where, array(), 'participant=NULL');
	}


	/**
	 * sort parents before children
	 *
	 * The output array uses the Ngroup IDs as indexes, so the indexes are independent from the order.
	 *
	 * @param array   $ngroups input
	 * @param integer $parent  (optional) internal
	 * @param array   $result  (optional, reference) internal
	 * @param integer $depth   (optional) internal
	 * @return array           output
	 */
	public static function parent_sort(array $ngroups, $parent=0, array &$result=array(), $depth=0) {
		foreach ($ngroups as $key => $ngroup) {
			if ($ngroup->parent == $parent) {
				$ngroup->depth = $depth;
				$result[$ngroup->id] = $ngroup;
				unset($ngroups[$key]);
				self::parent_sort($ngroups, $ngroup->id, $result, $depth + 1);
			}
		}
		return $result;
	}


	/**
	 * options for drop down menu
	 *
	 * @param integer $parent
	 * @return array
	 */
	public static function options($parent) {
		$options = array();
		$sql = "SELECT ngroups.*, member FROM ngroups
			LEFT JOIN members_ngroups ON members_ngroups.ngroup = ngroups.id AND members_ngroups.member = ".intval(Login::$member->id)."
			ORDER BY name";
		$result = DB::query($sql);
		$ngroups = array();
		while ( $ngroup = DB::fetch_object($result, "Ngroup") ) $ngroups[] = $ngroup;
		$ngroups = Ngroup::parent_sort($ngroups, $parent);
		// entitled ngroups
		foreach ($ngroups as $ngroup) {
			if (!$ngroup->member) continue;
			$options[$ngroup->id] = $ngroup->name." ("._("entitled").")";
		}
		// not entitled ngroups
		foreach ($ngroups as $ngroup) {
			if ($ngroup->member) continue;
			$options[$ngroup->id] = $ngroup->name;
		}
		return $options;
	}


}
