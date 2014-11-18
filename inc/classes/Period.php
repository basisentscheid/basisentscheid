<?
/**
 * Period
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Period extends Relation {

	public $ngroup;
	public $debate;
	public $preparation;
	public $voting;
	public $ballot_assignment;
	public $ballot_preparation;
	public $counting;
	public $online_voting;
	public $ballot_voting;
	public $state;
	public $postage;

	private $ngroup_obj;

	protected $boolean_fields = array("online_voting", "ballot_voting", "postage");


	/**
	 * get the ngroup this period belongs to
	 *
	 * @return object
	 */
	function ngroup() {
		if (!is_object($this->ngroup_obj)) $this->ngroup_obj = new Ngroup($this->ngroup);
		return $this->ngroup_obj;
	}


	/**
	 * information about the current proposal/issue phase
	 *
	 * @return string
	 */
	public function current_phase() {
		$time = time();
		if (strtotime($this->counting) <= $time) {
			return sprintf(_("Counting started at %s"),
				datetimeformat_smart($this->counting)
			);
		} elseif (strtotime($this->voting) <= $time) {
			return sprintf(_("Voting started at %s"),
				datetimeformat_smart($this->voting)
			);
		} elseif (strtotime($this->preparation) <= $time) {
			return sprintf(_("Voting preparation started at %s"),
				datetimeformat_smart($this->preparation)
			);
		} elseif (strtotime($this->debate) <= $time) {
			return sprintf(_("Debate started at %s"),
				datetimeformat_smart($this->debate)
			);
		}
		return sprintf(_("Debate starts at %s"),
			datetimeformat_smart($this->debate)
		);
	}


	/**
	 * information about the ballot phase
	 *
	 * @return string
	 */
	public function ballot_phase_info() {
		switch ($this->state) {
		case "ballot_preparation":
			return sprintf(_("Ballot preparation started at %s"),
				datetimeformat_smart($this->ballot_preparation)
			);
		case "ballot_assignment":
			return sprintf(_("Ballot assignment started at %s and goes until %s"),
				datetimeformat_smart($this->ballot_assignment),
				datetimeformat_smart($this->ballot_preparation)
			);
		}
		return sprintf(_("Ballot assignment starts at %s"),
			datetimeformat_smart($this->ballot_assignment)
		);
	}


	/**
	 * member selects postal voting
	 */
	public function select_postal() {
		$fields_values = array(
			'member' => Login::$member->id,
			'period' => $this->id,
			'ballot' => null,
			'agent'  => false
		);
		$keys = array("member", "period");
		DB::insert_or_update("voters", $fields_values, $keys);
	}


	/**
	 * the logged in member selects a ballot
	 *
	 * @param Ballot  $ballot
	 * @param boolean $agent  (optional)
	 */
	public function select_ballot(Ballot $ballot, $agent=false) {
		$ballot->assign_member(Login::$member, $agent);
		$this->update_voters_cache();
	}


	/**
	 * postal voting is selected and postage has already started
	 *
	 * @return boolean
	 */
	public function postage() {
		if ($this->postage) {
			$sql = "SELECT COUNT(1) FROM voters
				WHERE member=".intval(Login::$member->id)."
					AND period=".intval($this->id)."
					AND ballot IS NULL";
			if ( DB::fetchfield($sql) ) return true;
		}
	}


	/**
	 * the logged in member revokes his ballot choice
	 */
	public function unselect_ballot() {
		if ( $this->postage() ) {
			warning(_("You can not change your choice for postal voting any longer, because postage has already started."));
			redirect();
		}
		DB::delete("voters", "member=".intval(Login::$member->id)." AND period=".intval($this->id));
		$this->update_voters_cache();
	}


	/**
	 * count voters for all ballots
	 */
	private function update_voters_cache() {

		$sql = "SELECT id FROM ballots WHERE period=".intval($this->id);
		$result = DB::query($sql);
		while ( $row = DB::fetch_assoc($result) ) {

			$sql = "SELECT COUNT(1) FROM voters WHERE ballot=".intval($row['id']);
			$count = DB::fetchfield($sql);

			$sql = "UPDATE ballots SET voters=".intval($count)." WHERE id=".intval($row['id']);
			DB::query($sql);

		}

	}


	/**
	 * apply selection of approved ballots
	 */
	public function save_approved_ballots() {
		foreach ( $_POST['approved_id'] as $key => $ballot_id ) {
			$value = !empty($_POST['approved'][$key]);
			$sql = "UPDATE ballots SET approved=".DB::bool_to_sql($value)." WHERE id=".intval($ballot_id);
			DB::query($sql);
		}
	}


	/**
	 * assign all remaining members to their nearest ballots
	 */
	public function assign_members_to_ballots() {

		// get all approved ballots
		$sql_ballot = "SELECT * FROM ballots WHERE period=".intval($this->id)." AND approved=TRUE";
		$result_ballot = DB::query($sql_ballot);
		$ballots = array();
		while ( $ballot = DB::fetch_object($result_ballot, "Ballot") ) {
			$ballots[] = $ballot;
		}

		// If no ballots were approved at all, ballot voting just can not take place.
		if (!$ballots) return;

		// get all ngroups within the ngroup of the period
		$result = DB::query("SELECT * FROM ngroups");
		$ngroups = array();
		while ( $ngroup = DB::fetch_object($result, "Ngroup") ) $ngroups[$ngroup->id] = $ngroup;
		$period_ngroups = Ngroup::parent_sort($ngroups, $ngroups[$this->ngroup]->parent);

		// get all members, who are in the current period not assigned to a ballot yet
		$sql = "SELECT members.* FROM members
			JOIN members_ngroups ON members_ngroups.member = members.id AND members_ngroups.ngroup = ".intval($this->ngroup)."
			LEFT JOIN voters ON members.id = voters.member AND voters.period = ".intval($this->id)."
			WHERE voters.member IS NULL";
		$result = DB::query($sql);
		while ( $member = DB::fetch_object($result, "Member") ) {

			// get lowest member ngroup (within the ngroup of the period)
			$lowest_member_ngroup = $member->lowest_ngroup($period_ngroups);
			if (!$lowest_member_ngroup) {
				trigger_error("No lowest member ngroup found", E_USER_NOTICE);
				$best_ballots = $ballots;
			} else {

				// rate ballots by distance between member and ballot
				foreach ( $ballots as $ballot ) {

					$ballot_ngroup = $period_ngroups[$ballot->ngroup];
					$ballot->score = 0; // if no matching is found at all

					// climb up from the lowest ngroup of the member until the top
					$reference_member_ngroup = $lowest_member_ngroup;
					do {
						if ( self::ngroup_is_equal_or_child($ballot_ngroup, $reference_member_ngroup, $period_ngroups) ) {
							// Score depends on how far the member has to climb up, but not on how deep the ballot is from there.
							$ballot->score = $reference_member_ngroup->depth;
							break;
						}
					} while (
						$reference_member_ngroup->parent and
						$reference_member_ngroup = $period_ngroups[$reference_member_ngroup->parent] // step up to parent of member ngroup
					);

				}

				// pick ballots with highest score
				$highest_score = 0;
				$best_ballots = array();
				foreach ( $ballots as $ballot ) {
					if ($ballot->score == $highest_score) {
						$best_ballots[] = $ballot;
					} elseif ($ballot->score > $highest_score) {
						$best_ballots = array($ballot);
						$highest_score = $ballot->score;
					}
				}

			}

			// assign member to random of the best ballots
			$best_ballots[rand(0, count($best_ballots)-1)]->assign_member($member);

		}

		$this->update_voters_cache();

	}


	/**
	 * check if child ngroup is equal to or child of parent ngroup
	 *
	 * @param Ngroup  $child
	 * @param Ngroup  $parent
	 * @param array   $ngroups
	 * @return boolean
	 */
	private static function ngroup_is_equal_or_child(Ngroup $child, Ngroup $parent, array $ngroups) {
		// climb up from the child ngroup until the top
		do {
			if ($child->id == $parent->id) return true;
		} while (
			$child->parent and
			$child = $ngroups[$child->parent] // step up to parent ngroup
		);
	}


	/**
	 * display a timestamp
	 *
	 * @param string  $content
	 * @param array   $column
	 */
	public function dbtableadmin_print_timestamp($content, array $column) {

		// for NULL columns
		if (!$content) return;

		?><span<?

		$timestamp = strtotime($content);

		if ($timestamp <= time()) {
			switch ($column[0]) {
			case "debate":
				if (strtotime($this->preparation) <= time()) {
					?> class="over"<?
				} else {
					?> class="current"<?
				}
				break;
			case "preparation":
				if (strtotime($this->voting) <= time()) {
					?> class="over"<?
				} else {
					?> class="current"<?
				}
				break;
			case "voting":
				if (strtotime($this->counting) <= time()) {
					?> class="over"<?
				} else {
					?> class="current"<?
				}
				break;
			case "ballot_assignment":
				if (strtotime($this->ballot_preparation) <= time()) {
					?> class="over"<?
				} else {
					?> class="current"<?
				}
				break;
			case "ballot_preparation":
				if (strtotime($this->counting) <= time()) {
					?> class="over"<?
				} else {
					?> class="current"<?
				}
				break;
			case "counting":
				?> class="over"<?
				break;
			default:
				trigger_error("invalid column name".$column[0], E_USER_NOTICE);
			}
		}

		?>><?=date(DATETIMEYEAR_FORMAT, $timestamp)?></span><?

	}


	/**
	 * edit a timestamp
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 * @param integer $id
	 * @param boolean $disabled
	 * @param array   $column
	 */
	public function dbtableadmin_edit_timestamp($colname, $default, $id, $disabled, array $column) {
		if ($default) $default = datetimeformat($default);
		input_text($colname, $default, $disabled, 'size="30"');
	}


}
