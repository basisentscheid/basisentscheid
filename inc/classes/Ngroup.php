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


}
