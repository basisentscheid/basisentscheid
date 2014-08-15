<?
/**
 * Proposal
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Proposal extends Relation {

	public $title;
	const title_length = 300;
	public $content;
	const content_length = 100000;
	public $reason;
	const reason_length = 100000;
	public $issue;
	public $state = "draft";
	public $supporters;
	public $quorum_reached;
	public $admission_decision;
	public $submitted;

	const proponent_length = 100;
	const proponents_required_submission = 5;

	private $issue_obj;

	protected $boolean_fields = array("quorum_reached", "supported_by_member");
	protected $update_fields = array("title", "content", "reason");


	/**
	 * get the referenced issue (read it only once from the database)
	 *
	 * @return object
	 */
	public function issue() {
		if (!is_object($this->issue_obj)) $this->issue_obj = new Issue($this->issue);
		return $this->issue_obj;
	}


	/**
	 * set the referenced issue (instead of reading it from the database)
	 *
	 * @param object  $issue
	 */
	public function set_issue(Issue $issue) {
		$this->issue_obj = $issue;
	}


	/**
	 * Create a new proposal
	 *
	 * @return boolean
	 * @param string  $proponent proponent name
	 * @param integer $area      (optional) area for a new created issue
	 * @param array   $fields    (optional)
	 */
	public function create($proponent, $area=false, array $fields=array("title", "content", "reason", "issue")) {

		if (!$this->issue) {
			$issue = new Issue;
			$issue->area = $area;
			$issue->create();
			$this->issue = $issue->id;
		}

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}
		DB::insert("proposals", $fields_values, $this->id);

		$this->create_draft();

		// become proponent
		$this->add_support(
			false, // not anonymous
			$proponent,
			true // the first proponent starts already confirmed
		);

	}


	/**
	 *
	 */
	public function create_draft() {

		$draft = new Draft;
		$draft->proposal = $this->id;
		$draft->title   = $this->title;
		$draft->content = $this->content;
		$draft->reason  = $this->reason;
		$draft->author = Login::$member->id;
		$draft->create();

	}


	/**
	 * human friendly state names
	 *
	 * @return string
	 */
	public function state_name() {
		static $states;
		if (!$states) $states = array(
				'draft'     => _("Draft"),
				'submitted' => _("Submitted"),
				'admitted'  => _("Admitted"),
				'revoked'   => _("Revoked"),
				'cancelled' => _("Cancelled"),
				'done'      => _("Done otherwise")
			);
		return $states[$this->state];
	}


	/**
	 * submit the proposal
	 *
	 * @return boolean
	 */
	public function submit() {
		if ($this->state!="draft") {
			warning(_("The proposal has already been submitted."));
			return false;
		}
		if ($this->proponents_count() < self::proponents_required_submission) {
			warning(sprintf(_("For submission %d proponents are required."), self::proponents_required_submission));
			return false;
		}
		$this->state = "submitted";
		$this->update(array('state'), "submitted=now()");
	}


	/**
	 * look if the proponents still are allowed to edit their names and contact info
	 *
	 * @return boolean
	 */
	public function allowed_edit_proponent() {
		switch ($this->issue()->state) {
		case "admission":
		case "debate":
			switch ($this->state) {
			case "draft":
			case "submitted":
			case "admitted":
				return true;
			}
		}
		return false;
	}


	/**
	 * look if we are collecting supporters
	 *
	 * @return boolean
	 */
	public function admission() {
		return in_array($this->state, array("draft", "submitted")) and $this->issue()->state=="admission";
	}


	/**
	 * add the logged in member as supporter or proponent
	 *
	 * @param boolean $anonymous           (optional)
	 * @param string  $proponent           (optional) display name of the proponent or null if not a proponent
	 * @param boolean $proponent_confirmed (optional)
	 * @return boolean
	 */
	public function add_support($anonymous=false, $proponent=null, $proponent_confirmed=false) {
		if ($proponent) {
			if (!$this->allowed_edit_proponent()) {
				warning(_("You can not become proponent anymore once voting preparation has started or the proposal has been closed!"));
				return false;
			}
			if (!$proponent) {
				warning(_("Your proponent info must be not empty."));
				return false;
			}
			if (mb_strlen($proponent) > Proposal::proponent_length) {
				$proponent = limitstr($proponent, Proposal::proponent_length);
				warning(sprintf(_("The input has been truncated to the maximum allowed length of %d characters!"), self::proponent_length));
			}
		} else {
			if (!$this->admission()) {
				warning("Support for this proposal can not be added, because it is not in the admission phase!");
				return false;
			}
		}
		$fields_values = array(
			'proposal' => $this->id,
			'member' => Login::$member->id,
			'anonymous' => $anonymous,
			'proponent' => $proponent,
			'proponent_confirmed' => $proponent_confirmed
		);
		$keys = array('proposal', 'member');
		DB::insert_or_update("supporters", $fields_values, $keys);
		$this->update_supporters_cache();
		$this->issue()->area()->activate_participation();
		return true;
	}


	/**
	 * remove support or proponent
	 *
	 * @return boolean
	 */
	public function revoke_support() {
		if (!$this->admission()) {
			warning("Support for this proposal can not be removed, because it is not in the admission phase!");
			return false;
		}
		if ($this->is_proponent(Login::$member, false)) {
			warning("You can not remove your support while you are proponent!");
			return false;
		}
		$sql = "DELETE FROM supporters WHERE proposal=".intval($this->id)." AND member=".intval(Login::$member->id);
		DB::query($sql);
		$this->update_supporters_cache();
	}


	/**
	 * count supporters and admit proposal if quorum is reached
	 */
	private function update_supporters_cache() {

		$sql = "SELECT COUNT(1) FROM supporters
			WHERE proposal=".intval($this->id)."
			AND created > current_date - interval ".DB::esc(SUPPORTERS_VALID_INTERVAL);
		$this->supporters = DB::fetchfield($sql);

		if ($this->supporters >= $this->quorum_required()) {
			$this->quorum_reached = true;
			$this->state = "admitted";
			$this->update(array("supporters", "quorum_reached", "state"), "admitted=now()");
			$this->select_period();
		} else {
			$this->update(array("supporters"));
		}

	}


	/**
	 * update a proponent's name and contact info
	 *
	 * @param string  $proponent
	 * @return boolean
	 */
	public function update_proponent($proponent) {
		if (!$this->allowed_edit_proponent()) {
			warning(_("Your proponent info can not be changed anymore once voting preparation has started or the proposal has been closed!"));
			return false;
		}
		if (!$proponent) {
			warning(_("Your proponent info must be not empty."));
			return false;
		}
		if (mb_strlen($proponent) > self::proponent_length) {
			$proponent = limitstr($proponent, self::proponent_length);
			warning(sprintf(_("The input has been truncated to the maximum allowed length of %d characters!"), self::proponent_length));
		}
		$sql = "UPDATE supporters SET proponent=".DB::esc($proponent)."
			WHERE proposal=".intval($this->id)."
				AND member=".intval(Login::$member->id)."
				AND proponent IS NOT NULL";
		DB::query($sql);
	}


	/**
	 * confirm an applying member as proponent
	 *
	 * @param object  $member
	 * @return boolean
	 */
	public function confirm_proponent($member) {
		if (!$this->allowed_edit_proponent()) {
			warning(_("You can not confirm proponents anymore once voting preparation has started or the proposal has been closed!"));
			return false;
		}
		if (!$this->is_proponent($member, false)) {
			warning(_("The to be confirmed member is not applying to become proponent of this proposal."));
			return false;
		}
		$sql = "UPDATE supporters SET proponent_confirmed=TRUE
			WHERE proposal=".intval($this->id)."
				AND member=".intval($member->id)."
				AND proponent IS NOT NULL";
		DB::query($sql);
	}


	/**
	 * convert a proponent to an ordinary supporter
	 *
	 * @param object  $member
	 * @return boolean
	 */
	public function remove_proponent($member) {
		if (!$this->allowed_edit_proponent()) {
			warning(_("You can not remove yourself from the proponents list once voting preparation has started or the proposal has been closed!"));
			return false;
		}
		$sql = "UPDATE supporters SET proponent=NULL, proponent_confirmed=FALSE
			WHERE proposal=".intval($this->id)."
				AND member=".intval($member->id);
		DB::query($sql);
	}


	/**
	 * cancel the proposal
	 *
	 * @param string  $state (optional) destination state: "cancelled", "revoked" or "done"
	 * @return boolean
	 */
	public function cancel($state="cancelled") {
		if (!in_array($this->issue()->state, array("admission", "debate"))) {
			warning(_("In the current phase the proposal can not be revoked anymore."));
			return false;
		}

		// cancel proposal
		$this->state = $state;
		$this->update(array("state"), "cancelled=now()");

		$issue = $this->issue();

		// check if all proposals of the issue are cancelled, revoked or done
		foreach ( $issue->proposals() as $proposal ) {
			switch ($proposal->state) {
			case "draft":
			case "submitted":
			case "admitted":
				return;
			}
		}

		// cancel issue
		$issue->state = "cancelled";
		$issue->update(array("state"));

	}


	/**
	 * admit the proposal circumventing the quorum
	 *
	 * @param string  $text
	 */
	function set_admission_decision($text) {
		switch ($this->state) {
		case "submitted":
			$this->admission_decision = $text;
			$this->state = "admitted";
			$this->update(array("admission_decision", "state"), 'admitted=now()');
			$this->select_period();
			break;
		case "admitted":
			$this->admission_decision = $text;
			$this->update(array("admission_decision"));
			break;
		default:
			trigger_error("set_admission_decision() called in unknown proposal state", E_USER_WARNING);
		}
	}


	/**
	 *
	 */
	private function select_period() {

		// for now admins do this manually
		return;

		$issue = new Issue($this->issue);

		// The period has already been set by another proposal in the same issue.
		if ($issue->period) return;

		// select the next period, which has not yet started
		$sql = "SELECT id FROM periods WHERE debate > now() AND online_voting=TRUE ORDER BY debate LIMIT 1";
		$issue->period = DB::fetchfield($sql);
		if ($issue->period) {
			$issue->update(array("period"));
		} else {
			// TODO Error
		}

	}


	/**
	 * make lists of supporters and proponents and find out if the logged in member is supporter or proponent
	 *
	 * @return array
	 */
	public function supporters() {
		$supporters = array(); // list of supporters as strings
		$proponents = array(); // list of proponents (also unconfirmed) as objects of class member
		$is_supporter = false; // if the logged in member is supporter
		$is_proponent = false; // if the logged in member is confirmed proponent
		$sql = "SELECT member, anonymous, proponent, proponent_confirmed FROM supporters WHERE proposal=".intval($this->id);
		$result = DB::query($sql);
		while ( $row = DB::fetch_assoc($result) ) {
			DB::to_bool($row['proponent_confirmed']);
			$member = new Member($row['member']);
			if (Login::$member and $member->id==Login::$member->id) {
				if ($row['proponent_confirmed']) {
					$is_proponent = true;
					$is_supporter = true;
					$supporters[] = '<span class="proponent">'.$row['proponent'].'</span>';
				} elseif ($row['anonymous']===DB::value_true) {
					$is_supporter = "anonymous";
					$supporters[] = '<span class="self">'._("anonymous").'</span>';
				} else {
					$is_supporter = true;
					$supporters[] = '<span class="self">'.$member->username().'</span>';
				}
			} else {
				if ($row['anonymous']===DB::value_true) {
					$supporters[] = _("anonymous");
				} else {
					$supporters[] = $member->username();
				}
			}
			if ($row['proponent']!==null) {
				$member->proponent_name      = $row['proponent'];
				$member->proponent_confirmed = $row['proponent_confirmed'];
				$proponents[] = $member;
			}
		}
		return array($supporters, $proponents, $is_supporter, $is_proponent);
	}


	/**
	 * look if the supplied member is a proponent of this proposal
	 *
	 * @param object  $member
	 * @param boolean $confirmed (optional)
	 * @return boolean
	 */
	public function is_proponent(Member $member, $confirmed=true) {
		$sql = "SELECT COUNT(1) FROM supporters
			WHERE proposal=".intval($this->id)."
				AND member=".intval($member->id)."
				AND proponent IS NOT NULL";
		if ($confirmed) $sql .= " AND proponent_confirmed=TRUE";
		if ( DB::fetchfield($sql) ) return true;
		return false;
	}


	/**
	 * count confirmed proponents
	 *
	 * @return integer
	 */
	public function proponents_count() {
		$sql = "SELECT COUNT(1) FROM supporters
			WHERE proposal=".intval($this->id)."
				AND proponent IS NOT NULL
				AND proponent_confirmed=TRUE";
		return DB::fetchfield($sql);
	}


	/**
	 * check if it's allowed to add or rate arguments
	 *
	 * @return boolean
	 */
	public function allowed_add_arguments() {
		switch ($this->issue()->state) {
		case "counting":
		case "finished":
		case "cleared":
		case "cancelled":
			return false;
		}
		switch ($this->state) {
		case "draft":
		case "revoked":
		case "cancelled":
		case "done":
			return false;
		}
		return true;
	}


	/**
	 * quorum this proposal has to reach to get admitted
	 *
	 * @return array
	 */
	function quorum_level() {

		$sql = "SELECT * FROM proposals WHERE issue=".intval($this->issue)." AND quorum_reached=TRUE";
		if ( DB::numrows($sql) ) {
			return array(QUORUM_SUPPORT_ALTERNATIVE_NUM, QUORUM_SUPPORT_ALTERNATIVE_DEN);
		} else {
			return array(QUORUM_SUPPORT_NUM, QUORUM_SUPPORT_DEN);
		}

	}


	/**
	 * number of required supporters
	 *
	 * @return integer
	 */
	function quorum_required() {

		list($num, $den) = $this->quorum_level();

		$issue = new Issue($this->issue);
		$area = new Area($issue->area);

		return ceil($area->population() * $num / $den);

	}


	/**
	 * display bargraph
	 */
	public function bargraph_quorum() {
		$required = $this->quorum_required();
		bargraph(
			$this->supporters,
			$required,
			sprintf(
				_("%d of currently required %d (%s of %d) for admission"),
				$this->supporters, $required, numden2percent($this->quorum_level()), $this->issue()->area()->population()
			),
			"supporters"
		);
	}


}
