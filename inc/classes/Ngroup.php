<?
/**
 * Ngroup (nested group)
 *
 * @property $depth
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ngroup extends Relation {

	// database table
	public $parent;
	public $name;
	public $active;
	public $minimum_population;
	public $minimum_quorum_votingmode;

	public $member;
	public $depth;

	protected $boolean_fields = array("active");


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
			if ($ngroup->active) {
				// override differing GET parameter
				$_SESSION['ngroup'] = $ngroup->id;
				return $ngroup;
			}
		}

		// redirect to ngroup from session
		if (!empty($_SESSION['ngroup'])) {
			$ngroup = new Ngroup($_SESSION['ngroup']);
			if ($ngroup->active) {
				redirect(BN."?ngroup=".$ngroup->id);
			}
		}

		// redirect to default ngroup
		$default_ngroup = DB::fetchfield("SELECT id FROM ngroup WHERE active=TRUE ORDER BY id LIMIT 1");
		if (!$default_ngroup) error(_("No active groups found"));
		redirect(BN."?ngroup=".$default_ngroup);
	}


	/**
	 * sort parents before children
	 *
	 * The output array uses the Ngroup IDs as indexes, so the indexes are independent from the order.
	 *
	 * @param array   $ngroups input
	 * @param integer $parent  (optional) starting parent ID
	 * @param integer $depth   (optional) internal
	 * @return array           output
	 */
	public static function parent_sort(array $ngroups, $parent=0, $depth=0) {
		$result = array();
		foreach ($ngroups as $key => $ngroup) {
			if ($ngroup->parent == $parent) {
				$ngroup->depth = $depth;
				unset($ngroups[$key]);
				$result[$ngroup->id] = $ngroup;
				$result += self::parent_sort($ngroups, $ngroup->id, $depth + 1);
			}
		}
		return $result;
	}


	/**
	 * sort parents before children, return only active ngroups
	 *
	 * This is a separate function, because it is used by the navigation and thus probably performance critical.
	 *
	 * The output array uses the Ngroup IDs as indexes, so the indexes are independent from the order.
	 *
	 * @param array   $ngroups input
	 * @param integer $parent  (optional) starting parent ID
	 * @return array           output
	 */
	public static function parent_sort_active(array $ngroups, $parent=0) {
		$result = array();
		foreach ($ngroups as $key => $ngroup) {
			if ($ngroup->parent == $parent) {
				unset($ngroups[$key]);
				if ($ngroup->active) $result[$ngroup->id] = $ngroup;
				$result += self::parent_sort_active($ngroups, $ngroup->id);
			}
		}
		return $result;
	}


	/**
	 * sort parents before children, return only active ngroups and ngroups with active children
	 *
	 * This is a separate function, because it is used by the start page and thus probably performance critical.
	 *
	 * The output array uses the Ngroup IDs as indexes, so the indexes are independent from the order.
	 *
	 * @param array   $ngroups input
	 * @param integer $parent  (optional) starting parent ID
	 * @param integer $depth   (optional) internal
	 * @return array           output: sorted array and boolean if any ngroups is active
	 */
	public static function parent_sort_active_tree(array $ngroups, $parent=0, $depth=0) {
		$result = array();
		$active = false;
		foreach ($ngroups as $key => $ngroup) {
			if ($ngroup->parent == $parent) {
				$ngroup->depth = $depth;
				unset($ngroups[$key]);
				list($result_children, $active_children) = self::parent_sort_active_tree($ngroups, $ngroup->id, $depth + 1);
				// show only if active or with active children
				if ($ngroup->active or $active_children) {
					$result[$ngroup->id] = $ngroup;
					$result += $result_children;
					$active = true;
				}
			}
		}
		return array($result, $active);
	}


	/**
	 * options for drop down menu
	 *
	 * Here we use all ngroups, not only the active ones.
	 *
	 * @param integer $parent
	 * @return array
	 */
	public static function options($parent) {
		$options = array();
		$sql = "SELECT ngroup.*, member FROM ngroup
			LEFT JOIN member_ngroup ON member_ngroup.ngroup = ngroup.id AND member_ngroup.member = ".intval(Login::$member->id)."
			ORDER BY name";
		$result = DB::query($sql);
		$ngroups = array();
		while ( $ngroup = DB::fetch_object($result, "Ngroup") ) $ngroups[] = $ngroup;
		$ngroups = Ngroup::parent_sort($ngroups, $parent);
		// own ngroups
		foreach ($ngroups as $ngroup) {
			if (!$ngroup->member) continue;
			$options[$ngroup->id] = $ngroup->name." &#8710;";
		}
		// other ngroups
		foreach ($ngroups as $ngroup) {
			if ($ngroup->member) continue;
			$options[$ngroup->id] = $ngroup->name;
		}
		return $options;
	}


	/**
	 * display the group name with indenting and black or white diamond
	 *
	 * @param boolean $own
	 */
	public function display_list_name($own) {
		echo str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $this->depth);
		if ($own) { ?>&#9670; <? } else { ?>&#9671; <? }
		echo $this->name;
	}


	/**
	 * number of proposals
	 *
	 * @return integer
	 */
	public function proposals_count() {
		$sql = "SELECT count(1) FROM proposal
			JOIN issue ON proposal.issue = issue.id
 			JOIN area ON issue.area = area.id
 			WHERE ngroup=".intval($this->id);
		return DB::fetchfield($sql);
	}


	/**
	 * number of voting periods
	 *
	 * @return integer
	 */
	public function periods_count() {
		$sql = "SELECT count(1) FROM period
 			WHERE ngroup=".intval($this->id);
		return DB::fetchfield($sql);
	}


	/**
	 * number of issues the logged in member has not yet voted on
	 *
	 * @return integer
	 */
	public function not_yet_voted_issues_count() {
		if (!Login::$member) return;
		$sql = "SELECT count(1) FROM vote_token
			JOIN issue ON issue.id = vote_token.issue AND issue.state = 'voting'
 			JOIN area ON issue.area = area.id AND area.ngroup = ".intval($this->id)."
 			LEFT JOIN vote_vote USING (token)
			WHERE vote_token.member = ".intval(Login::$member->id)."
				AND vote_vote.vote IS NULL";
		return DB::fetchfield($sql);
	}


	/**
	 * message about not yet voted issues
	 *
	 * @param integer $count
	 * @return string
	 */
	public static function not_yet_voted($count) {
		if ($count == 1) return _("You have not yet voted on 1 issue.");
		return sprintf(_("You have not yet voted on %d issues."), $count);
	}


}
