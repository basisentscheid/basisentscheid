<?
/**
 * inc/classes/Period.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Period extends Relation {

	public $debate;
	public $preparation;
	public $voting;
	public $ballot_assignment;
	public $ballot_preparation;
	public $counting;
	public $online;
	public $secret;

	protected $boolean_fields = array("online", "secret");


	/**
	 * information about the current proposal/issue phase
	 *
	 * @return string
	 */
	public function current_phase() {
		$time = time();
		if (strtotime($this->counting) <= $time) {
			return strtr(_("Counting started at %datetime%."), array(
					'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->counting))
				));
		} elseif (strtotime($this->voting) <= $time) {
			return strtr(_("Voting started at %datetime%."), array(
					'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->voting))
				));
		} elseif (strtotime($this->preparation) <= $time) {
			return strtr(_("Voting preparation started at %datetime%."), array(
					'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->preparation))
				));
		} elseif (strtotime($this->debate) <= $time) {
			return strtr(_("Debate started at %datetime%."), array(
					'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->debate))
				));
		}
		return strtr(_("Debate starts at %datetime%."), array(
				'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->debate))
			));
	}


	/**
	 * information about the ballot phase
	 *
	 * @return string
	 */
	public function ballot_phase_info() {
		switch ($this->state) {
		case "ballot_preparation":
			return strtr(_("Ballot preparation started at %datetime%."), array(
					'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->ballot_preparation))
				));
		case "ballot_assignment":
			return strtr(_("Ballot assignment started at %datetime% and goes until %enddatetime%."), array(
					'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->ballot_assignment)),
					'%enddatetime%'=>date(DATETIME_FORMAT, strtotime($this->ballot_preparation))
				));
		}
		return strtr(_("Ballot assignment starts at %datetime%."), array(
				'%datetime%'=>date(DATETIME_FORMAT, strtotime($this->ballot_assignment))
			));
	}


	/**
	 * the logged in member selects a ballot
	 *
	 * @param object  $ballot
	 * @param boolean $agent  (optional)
	 */
	public function select_ballot(Ballot $ballot, $agent=false) {
		$ballot->assign_member(Login::$member, $agent);
		$this->update_voters_cache();
	}


	/**
	 * the logged in member revokes his ballot choice
	 */
	public function unselect_ballot() {
		DB::delete("voters", "member=".intval(Login::$member->id)." AND period=".intval($this->id));
		$this->update_voters_cache();
	}


	/**
	 * count voters for all ballots
	 */
	private function update_voters_cache() {

		$sql = "SELECT id FROM ballots WHERE period=".intval($this->id);
		$result = DB::query($sql);
		while ( $row = pg_fetch_assoc($result) ) {

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
			$sql = "UPDATE ballots SET approved=".DB::bool2pg($value)." WHERE id=".intval($ballot_id);
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
		while ( $row_ballot = pg_fetch_assoc($result_ballot) ) {
			$ballots[] = new Ballot($row_ballot);
		}

		if (!$ballots) return;

		// get all participants, who are in the current period not assigned to a ballot yet
		$sql = "SELECT members.* FROM members
			LEFT JOIN voters ON members.id = voters.member AND voters.period = ".intval($this->id)."
			WHERE participant=TRUE
				AND voters.member IS NULL";
		$result = DB::query($sql);
		while ( $row = pg_fetch_assoc($result) ) {

			$member = new Member($row);

			// assign members to random ballots until we get the documentation about the groups supplied by the ID server
			$ballots[rand(0, count($ballots)-1)]->assign_member($member);

		}

		$this->update_voters_cache();

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

		?>><?=date(DATETIME_FORMAT, $timestamp)?></span><?

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
	public function dbtableadmin_edit_timestamp($colname, $default, $id, $disabled, $column) {
		if ($default) $default = date(DATETIME_FORMAT, strtotime($default));
		input_text($colname, $default, $disabled, 'size="30"');
	}


}
