<?
/**
 * inc/classes/Proposal.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Proposal extends Relation {

	public $proponents;
	public $title;
	public $content;
	public $reason;
	public $issue;
	public $state;
	public $supporters;
	public $quorum_reached;
	public $admission_decision;
	public $submitted;

	private $issue_obj;

	protected $boolean_fields = array("quorum_reached");
	protected $update_fields = array("proponents", "title", "content", "reason");


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


	/*
	function	 new_issue($row) {
		$this->issue_obj = new Issue($row);
	}
	*/


	/**
	 * Create a new proposal
	 *
	 * @return boolean
	 * @param unknown $area
	 * @param unknown $fields (optional)
	 */
	public function create( $area=false, $fields = array("proponents", "title", "content", "reason", "issue") ) {

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

		$this->add_support();

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
	 *
	 */
	function add_support() {
		$sql = "INSERT INTO supporters (proposal, member) VALUES (".intval($this->id).", ".intval(Login::$member->id).")";
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
	 *
	 * @param unknown $link
	 */
	function set_admitted_by_decision($link) {

		$this->admission_decision_link = $link;
		$this->state = "admitted";
		$this->update(array("admission_decision_link", "state"));
		$this->select_period();

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
	 *
	 * @return unknown
	 */
	public function show_supporters() {
		$supported_by_member = false;
		$sql = "SELECT member FROM supporters WHERE proposal=".intval($this->id);
		$result = DB::query($sql);
		resetfirst();
		while ( $row = pg_fetch_assoc($result) ) {
			$member = new Member($row['member']);
			if (Login::$member and $member->id==Login::$member->id) $supported_by_member = true;
			if (!first()) echo ", ";
			echo $member->username();
		}
		return $supported_by_member;
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
	 *
	 */
	public function bargraph_quorum() {
		$required = $this->quorum_required();
		bargraph(
			$this->supporters,
			$required,
			strtr( _("%value% of currently required %required% (%percent% of %population%)"), array('%value%'=>$this->supporters, '%required%'=>$required, '%percent%'=>numden2percent($this->quorum_level()), '%population%'=>$this->issue()->area()->population()) ),
			"#00AA00"
		);
	}


}
