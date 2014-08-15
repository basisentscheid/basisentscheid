<?
/**
 * Member
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Member extends Relation {

	public $auid;
	public $username;
	public $participant;
	public $activated;
	public $profile   = "";
	public $public_id = "";

	protected $boolean_fields = array("participant");
	protected $create_fields = array("auid", "username", "public_id", "profile");
	protected $update_fields = array("username");


	/**
	 * set the username
	 *
	 * @param string  $username
	 */
	function set_unique_username($username) {

		$this->username = $username;

		$suffix = 0;
		do {
			$sql = "SELECT * FROM members WHERE username=".DB::esc($this->username);
			$result = DB::query($sql);
			if ( $exists = DB::num_rows($result) ) {
				$this->username = $username . ++$suffix;
			}
		} while ($exists);

		if ($this->username != $username) {
			notice(_("The username is already used by someone else, so we added a number to it."));
		}

	}


	/**
	 * get the username or "anonymous"
	 *
	 * @param string  $username
	 * @return string
	 */
	public function username() {
		return self::username_static($this->username);
	}


	/**
	 * get the username or "anonymous"
	 *
	 * @param string  $username
	 * @return string
	 */
	public static function username_static($username) {
		if ($username) return $username;
		return _("anonymous");
	}


	/**
	 * name to identify the member for admins and other proponents
	 *
	 * @return string
	 */
	public function identity() {
		if ($this->public_id) return _("Real name").": ".$this->public_id;
		return _("User name").": ".$this->username;
	}


	/**
	 * activate general participation (distinct from area participation)
	 */
	public function activate_participation() {
		$this->participant = true;
		$this->update(array("participant"), "activated=now()");
	}


	/**
	 * deactivate general participation
	 */
	public function deactivate_participation() {
		$this->participant = false;
		$this->update(array("participant"));
	}


	/**
	 * hide help on a page
	 *
	 * @param string  $basename
	 */
	public function hide_help($basename) {
		$pages = explode_no_empty(",", $this->hide_help);
		$pages[] = $basename;
		$pages = array_unique($pages);
		$this->hide_help = join(",", $pages);
		$this->update(array("hide_help"));
	}


	/**
	 * hide help on a page
	 *
	 * @param string  $basename
	 */
	public function show_help($basename) {
		$pages = explode_no_empty(",", $this->hide_help);
		foreach ( $pages as $key => $page ) {
			if ($page==$basename) unset($pages[$key]);
		}
		$this->hide_help = join(",", $pages);
		$this->update(array("hide_help"));
	}


	/**
	 * update member's nested groups
	 *
	 * @param array   $member_groups
	 */
	public function update_nested_groups(array $member_groups) {

		// get needed groups

		$sql = "SELECT id FROM nested_groups";
		$result = DB::query($sql);
		$existing_groups = DB::fetchfieldarray($sql);
		$needed_groups = array_diff($member_groups, $existing_groups);

		if ($needed_groups) {

			$response_nested_groups = curl_fetch(NESTED_GROUPS_URL);
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

			if (!isset($response_nested_groups['result']['results'])) {
				trigger_error("Fetching nested groups from ID server failed", E_USER_WARNING);
				return;
			}

			// use ids as index
			$groups = array();
			foreach ( $response_nested_groups['result']['results'] as $nested_group ) {
				$groups[$nested_group['id']] = $nested_group;
			}

			$missing_groups = array_diff($needed_groups, array_keys($groups));
			if ($missing_groups) {
				trigger_error("Member nested group(s) ".join(", ", $missing_groups)." are missing in list", E_USER_WARNING);
				return;
			}

			// insert all new groups
			$insert_groups = array_diff_key($groups, array_flip($existing_groups));

			// convert parents from urls to ids
			foreach ($insert_groups as &$insert_group) {
				if ($insert_group['parent']===null) continue;
				if ( preg_match("#/nested_groups/(\d+)/$#", $insert_group['parent'], $matches) ) {
					$insert_group['parent'] = $matches[1];
				} else {
					trigger_error("Nested group parent ".$insert_group['parent']." does not match expression", E_USER_WARNING);
					return;
				}
			}
			unset($insert_group);

			// sort parents before children to avoid foreign key violations
			usort($insert_groups, "self::nested_groups_sort");

			$sql = "INSERT INTO nested_groups (id, name, parent) VALUES ";
			resetfirst();
			foreach ($insert_groups as $insert_group) {
				if (!first()) $sql .= ", ";
				$sql .= "(".intval($insert_group['id']).", ".DB::esc($insert_group['name']).", ".DB::int_to_sql($insert_group['parent']).")";
			}
			DB::query($sql);

		}

		// update member groups

		DB::transaction_start();

		$sql = "SELECT nested_group FROM members_nested_groups WHERE member=".intval($this->id);
		$existing_member_groups = DB::fetchfieldarray($sql);

		$insert_member_groups = array_diff($member_groups, $existing_member_groups);
		if ($insert_member_groups) {
			$sql = "INSERT INTO members_nested_groups (member, nested_group) VALUES ";
			resetfirst();
			foreach ($insert_member_groups as $insert_member_group) {
				if (!first()) $sql .= ", ";
				$sql .= "(".intval($this->id).", ".intval($insert_member_group).")";
			}
			DB::query($sql);
		}

		$delete_member_groups = array_diff($existing_member_groups, $member_groups);
		if ($delete_member_groups) {
			$sql = "DELETE FROM members_nested_groups
			WHERE member=".intval($this->id)."
				AND nested_group IN (".join(", ", array_map("intval", $delete_member_groups)).")";
			DB::query($sql);
		}

		DB::transaction_commit();

	}


	/**
	 * sort parents before children
	 *
	 * @param array   $a
	 * @param array   $b
	 * @return boolean
	 */
	private static function nested_groups_sort(array $a, array $b) {
		return $a['parent'] > $b['id'];
	}


}
