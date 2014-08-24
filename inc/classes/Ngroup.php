<?
/**
 * Ngroup (nested group)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ngroup extends Relation {

	public $parent;
	public $name;
	public $active;

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
		$default_ngroup = DB::fetchfield("SELECT id FROM ngroups WHERE active=TRUE ORDER BY id LIMIT 1");
		if (!$default_ngroup) error(_("No active groups found"));
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


	/**
	 * get new ngroups and update existing ngroups
	 *
	 * We never delete any ngroups.
	 */
	public static function update_ngroups() {

		if (defined("SKIP_UPDOWNLOAD")) {
			$result = file_get_contents(DOCROOT."var/ngroups.json");
		} else {
			$result = curl_fetch(NGROUPS_URL);
			//file_put_contents(DOCROOT."var/ngroups.json", $result);
		}

		/*
		["results"]=>
		array(5) {
			[0]=>
			object(stdClass)#2 (5) {
				["id"]=>
				int(1)
				["name"]=>
				string(4) "Bund"
				["parent"]=>
				NULL
				["depth"]=>
				int(0)
				["description"]=>
				string(0) ""
			}
			[1]=>
			object(stdClass)#3 (5) {
				["id"]=>
				int(2)
				["name"]=>
				string(6) "Bayern"
				["parent"]=>
				string(59) "https://example.com/api/nested_groups/1/"
				["depth"]=>
				int(1)
				["description"]=>
				string(0) ""
			}
			[2]=>
			object(stdClass)#4 (5) {
				["id"]=>
				int(5)
				["name"]=>
				string(12) "Niederbayern"
				["parent"]=>
				string(59) "https://example.com/api/nested_groups/2/"
				["depth"]=>
				int(2)
				["description"]=>
				string(0) ""
			}
			[3]=>
			object(stdClass)#5 (5) {
				["id"]=>
				int(3)
				["name"]=>
				string(10) "Oberbayern"
				["parent"]=>
				string(59) "https://example.com/api/nested_groups/2/"
				["depth"]=>
				int(2)
				["description"]=>
				string(0) ""
			}
			[4]=>
			object(stdClass)#6 (5) {
				["id"]=>
				int(4)
				["name"]=>
				string(6) "Hessen"
				["parent"]=>
				string(59) "https://example.com/api/nested_groups/1/"
				["depth"]=>
				int(1)
				["description"]=>
				string(0) ""
			}
		}
		*/

		$result = json_decode($result);

		if (!isset($result->results)) {
			trigger_error("Fetching ngroups from ID server failed", E_USER_WARNING);
			return;
		}

		// use ids as index
		foreach ( $result->results as $ng ) {
			// convert parents from urls to ids
			if ($ng->parent!==null) {
				if ( preg_match("#/nested_groups/(\d+)/$#", $ng->parent, $matches) ) {
					$ng->parent = $matches[1];
				} else {
					trigger_error("Ngroup parent ".$ng->parent." does not match expression", E_USER_WARNING);
					return;
				}
			}
			$fields_values = array(
				'id'     => $ng->id,
				'name'   => $ng->name,
				'parent' => $ng->parent
			);
			$insert_id = 0;
			DB::insert_or_update("ngroups", $fields_values, array('id'), $insert_id);
			if ($insert_id) {
				foreach ( explode_no_empty(",", DEFAULT_AREAS) as $name ) {
					$area = new Area;
					$area->ngroup = $insert_id;
					$area->name = trim($name);
					$area->create();
				}
			}
		}

	}


}
