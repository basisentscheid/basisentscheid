<?
/**
 * inc/classes/Proposal.php
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
	public $state;
	public $supporters;
	public $quorum_reached;
	public $admission_decision;
	public $submitted;

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
	 * @param unknown $area
	 * @param unknown $fields (optional)
	 */
	public function create( $area=false, $fields = array("title", "content", "reason", "issue") ) {

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

		$this->add_support(false, true);

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
	 */
	public function submit() {
		$this->state = "submitted";
		$this->update(array('state'));
	}


	/**
	 * add the logged in member as supporter or proponent
	 *
	 * @param boolean $anonymous (optional)
	 * @param boolean $proponent (optional)
	 */
	function add_support($anonymous=false, $proponent=false) {
		$sql = "INSERT INTO supporters (proposal, member, anonymous, proponent)
			VALUES (".intval($this->id).", ".intval(Login::$member->id).", ".DB::bool2pg($anonymous).", ".DB::bool2pg($proponent).")";
		DB::query($sql);
		$this->update_supporters_cache();
		$this->issue()->area()->activate_participation();
	}


	/**
	 *
	 */
	function revoke_support() {
		$sql = "DELETE FROM supporters WHERE proposal=".intval($this->id)." AND member=".intval(Login::$member->id);
		DB::query($sql);
		$this->update_supporters_cache();
	}


	/**
	 *
	 */
	function update_supporters_cache() {

		$sql = "SELECT COUNT(1) FROM supporters
			WHERE proposal=".intval($this->id)."
			AND created > current_date - interval ".DB::m(SUPPORTERS_VALID_INTERVAL);
		$this->supporters = DB::fetchfield($sql);

		if ($this->supporters >= $this->quorum_required()) {
			$this->quorum_reached = true;
			$this->state = "admitted";
			$this->update(array("supporters", "quorum_reached", "state"));
			$this->select_period();
		} else {
			$this->update(array("supporters"));
		}

	}


	/**
	 * cancel the proposal
	 *
	 * @param string  $state (optional) destination state: "cancelled", "revoked" or "done"
	 */
	public function cancel($state="cancelled") {

		// cancel proposal
		$this->state = $state;
		$this->update(array("state"));

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
		if ($this->admission_decision===null) {
			$this->admission_decision = $text;
			$this->state = "admitted";
			$this->update(array("admission_decision", "state"));
			$this->select_period();
		} else {
			$this->admission_decision = $text;
			$this->update(array("admission_decision"));
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
		$sql = "SELECT id FROM periods WHERE debate > now() AND online=TRUE ORDER BY debate LIMIT 1";
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
		$supporters = array();
		$proponents = array();
		$is_supporter = false;
		$is_proponent = false;
		$sql = "SELECT member, anonymous, proponent FROM supporters WHERE proposal=".intval($this->id);
		$result = DB::query($sql);
		while ( $row = pg_fetch_assoc($result) ) {
			$member = new Member($row['member']);
			if (Login::$member and $member->id==Login::$member->id) {
				if ($row['proponent']===DB::value_true) {
					$proponents[] = $member;
					$is_proponent = true;
					$supporters[] = '<span class="proponent">'.$member->username().'</span>';
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
		}
		return array($supporters, $proponents, $is_supporter, $is_proponent);
	}


	/**
	 * look if the supplied member is a proponent of this proposal
	 *
	 * @param object  $member
	 * @return boolean
	 */
	public function is_proponent(Member $member) {
		$sql = "SELECT COUNT(1) FROM supporters WHERE proposal=".intval($this->id)." AND member=".intval($member->id)." AND proponent=TRUE";
		if ( DB::fetchfield($sql) ) return true;
		return false;
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
	 *
	 * @return unknown
	 */
	function quorum_level() {

		$sql = "SELECT * FROM proposals WHERE issue=".intval($this->issue)." AND quorum_reached=TRUE";
		if ( DB::numrows($sql) ) {
			return array(QUORUM_SUPPORT_ALTERNATIVE_NUM, QUORUM_SUPPORT_ALTERNATIVE_DEN); // 5%
		} else {
			return array(QUORUM_SUPPORT_NUM, QUORUM_SUPPORT_DEN); // 10%
		}

	}


	/**
	 *
	 * @return unknown
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
			"#00AA00"
		);
	}


}
