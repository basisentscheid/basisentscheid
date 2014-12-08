<?

/**
 * Proposal
 *
 * @property  supported_by_member
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Proposal extends Relation {

	const proponent_length = 100;

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
	public $revoke;
	public $admitted;
	public $cancelled;

	// voting result
	public $yes;
	public $no;
	public $abstention;
	public $score;
	public $accepted;

	protected $boolean_fields = array("quorum_reached", "supported_by_member", "accepted");
	protected $update_fields = array("title", "content", "reason");

	protected $issue_obj;
	protected $dependent_attributes = array('issue_obj');


	/**
	 * get the referenced issue (read it only once from the database)
	 *
	 * @return Issue
	 */
	public function issue() {
		if (!is_object($this->issue_obj)) $this->issue_obj = new Issue($this->issue);
		return $this->issue_obj;
	}


	/**
	 * set the referenced issue (instead of reading it from the database)
	 *
	 * @param Issue   $issue
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
	public function create($proponent, $area=0, array $fields=array("title", "content", "reason", "issue")) {

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
		$this->add_proponent(
			$proponent,
			true // the first proponent starts already confirmed
		);

		$notification = new Notification("new_proposal");
		$notification->proposal = $this;
		$notification->proponent = $this->proponent_name(Login::$member->id);
		$notification->send();

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
		if (Login::$member) {
			$draft->author = Login::$member->id;
		} else {
			// admin
			$draft->author = null;
		}
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
	 * look if the supplied member is a proponent of this proposal
	 *
	 * @param Member  $member
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


	// allowed & actions


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
		if ($this->proponents_count() < REQUIRED_PROPONENTS) {
			warning(sprintf(_("For submission %d proponents are required."), REQUIRED_PROPONENTS));
			return false;
		}
		$this->state = "submitted";
		$this->update(array('state'), "submitted=now()");

		$notification = new Notification("submitted");
		$notification->proposal = $this;
		$notification->proponent = $this->proponent_name(Login::$member->id);
		$notification->send();

	}


	/**
	 * look if we are collecting supporters
	 *
	 * @return boolean
	 */
	public function allowed_change_supporters() {
		switch ($this->issue()->state) {
		case "admission":
		case "debate":
		case "preparation":
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
	 * add the logged in member as supporter
	 *
	 * @param boolean $anonymous (optional)
	 * @return boolean
	 */
	public function add_support($anonymous=false) {
		if (!$this->allowed_change_supporters()) {
			warning("Support for this proposal can not be added, because it is not in the admission phase!");
			return false;
		}
		$fields_values = array(
			'proposal' => $this->id,
			'member' => Login::$member->id,
			'anonymous' => $anonymous
		);
		$keys = array('proposal', 'member');

		DB::insert_or_update("supporters", $fields_values, $keys);

		$this->update_supporters_cache();
		$this->issue()->area()->activate_participation();
		return true;
	}


	/**
	 * remove support
	 *
	 * @return boolean
	 */
	public function revoke_support() {
		if (!$this->allowed_change_supporters()) {
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
	 * if it's allowed to add, remove and confirm proponents and edit their names and contact info
	 *
	 * @return boolean
	 */
	public function allowed_change_proponents() {
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
	 * add the logged in member as proponent
	 *
	 * @param string  $proponent           display name of the proponent
	 * @param boolean $proponent_confirmed (optional)
	 * @return boolean
	 */
	public function add_proponent($proponent, $proponent_confirmed=false) {
		if (!$proponent) {
			warning(_("Your proponent info must be not empty."));
			return false;
		}

		DB::transaction_start();
		$this->read();
		if (!$this->allowed_change_proponents()) {
			warning(_("You can not become proponent anymore once voting preparation has started or the proposal has been closed!"));
			DB::transaction_rollback();
			return false;
		}
		if (mb_strlen($proponent) > Proposal::proponent_length) {
			$proponent = limitstr($proponent, Proposal::proponent_length);
			warning(sprintf(_("The input has been truncated to the maximum allowed length of %d characters!"), self::proponent_length));
		}
		// the first proponent is already confirmed
		if (!$proponent_confirmed and !$this->proponents_count()) $proponent_confirmed = true;
		$fields_values = array(
			'proposal' => $this->id,
			'member' => Login::$member->id,
			'proponent' => $proponent,
			'proponent_confirmed' => $proponent_confirmed
		);
		$keys = array('proposal', 'member');
		DB::insert_or_update("supporters", $fields_values, $keys);
		DB::transaction_commit();

		if ($proponent_confirmed) {
			$this->check_required_proponents();
		} else {

			// notification to the other proponents
			$notification = new Notification("apply_proponent");
			$notification->proposal = $this;
			$notification->proponent = $proponent;
			$notification->send($this->proponents());

			notice(_("Your application to become proponent has been submitted to the current proponents to confirm your request."));
		}

		$this->update_supporters_cache();
		$this->issue()->area()->activate_participation();
		return true;
	}


	/**
	 * update a proponent's name and contact info
	 *
	 * @param string  $proponent
	 * @return boolean
	 */
	public function update_proponent($proponent) {
		if (!$proponent) {
			warning(_("Your proponent info must be not empty."));
			return false;
		}

		DB::transaction_start();
		if (!$this->allowed_change_proponents()) {
			warning(_("Your proponent info can not be changed anymore once voting preparation has started or the proposal has been closed!"));
			DB::transaction_rollback();
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
		DB::transaction_commit();
	}


	/**
	 * confirm an applying member as proponent
	 *
	 * @param Member  $member
	 * @return boolean
	 */
	public function confirm_proponent(Member $member) {
		DB::transaction_start();
		if (!$this->allowed_change_proponents()) {
			warning(_("You can not confirm proponents anymore once voting preparation has started or the proposal has been closed!"));
			DB::transaction_rollback();
			return false;
		}
		if (!$this->is_proponent($member, false)) {
			warning(_("The to be confirmed member is not applying to become proponent of this proposal."));
			DB::transaction_rollback();
			return false;
		}
		$sql = "UPDATE supporters SET proponent_confirmed=TRUE
			WHERE proposal=".intval($this->id)."
				AND member=".intval($member->id)."
				AND proponent IS NOT NULL";
		DB::query($sql);
		DB::transaction_commit();

		// notification to the other proponents
		$notification = new Notification("confirmed_proponent");
		$notification->proposal = $this;
		$notification->proponent_confirmed  = $this->proponent_name($member->id);
		$notification->proponent_confirming = $this->proponent_name(Login::$member->id);
		$notification->send($this->proponents());

		$this->check_required_proponents();
	}


	/**
	 * remove revoke date if the required number of proponents is reached
	 */
	private function check_required_proponents() {
		if (!$this->revoke) return;
		if ($this->state=="draft") {
			// Drafts need only one proponent.
			if ($this->proponents_count() < 1) return;
		} else {
			if ($this->proponents_count() < REQUIRED_PROPONENTS) return;
		}
		$this->revoke = null;
		$this->update(array('revoke'));
	}


	/**
	 * convert a proponent to an ordinary supporter
	 *
	 * @param Member  $member
	 * @return boolean
	 */
	public function remove_proponent(Member $member) {

		$proponent = $this->proponent_name($member->id);

		DB::transaction_start();
		$this->read();
		if (!$this->allowed_change_proponents()) {
			warning(_("You can not remove yourself from the proponents list once voting preparation has started or the proposal has been closed!"));
			DB::transaction_rollback();
			return false;
		}
		$sql = "UPDATE supporters SET proponent=NULL, proponent_confirmed=FALSE
			WHERE proposal=".intval($this->id)."
				AND member=".intval($member->id);
		DB::query($sql);
		DB::transaction_commit();

		// notification to the other proponents
		$notification = new Notification("removed_proponent");
		$notification->proposal = $this;
		$notification->proponent = $proponent;
		$notification->send($this->proponents());

		// set revoke date if we deleted the last proponent
		if ($this->proponents_count()) return;
		// We don't have to check if the to be removed proponent is confirmed, because otherways revoke would be already set anyway.
		$sql = "UPDATE proposals SET revoke = now() + interval '1 week' WHERE id=".intval($this->id)." AND revoke IS NULL";
		DB::query($sql);
	}


	/**
	 * get proponent name
	 *
	 * @param integer $member_id
	 * @return string
	 */
	private function proponent_name($member_id) {
		$sql = "SELECT proponent FROM supporters
			WHERE proposal=".intval($this->id)."
				AND member=".intval($member_id);
		return DB::fetchfield($sql);
	}


	/**
	 * member IDs of all (confirmed) proponents
	 *
	 * @return array
	 */
	public function proponents() {
		$sql = "SELECT member FROM supporters
		  WHERE proposal=".intval($this->id)."
		    AND proponent_confirmed=TRUE";
		return DB::fetchfieldarray($sql);
	}


	/**
	 * count supporters and admit proposal if quorum is reached
	 */
	private function update_supporters_cache() {

		$sql = "SELECT COUNT(1) FROM supporters
			WHERE proposal=".intval($this->id)."
				AND created > current_date - interval ".DB::esc(SUPPORTERS_VALID_INTERVAL);
		$this->supporters = DB::fetchfield($sql);

		if ( !$this->quorum_reached and $this->supporters >= $this->quorum_required() ) {
			// admit proposal
			$this->quorum_reached = true;
			$this->state = "admitted";
			$this->update(array("supporters", "quorum_reached", "state"), "admitted=now()");
			$this->issue()->proposal_admitted($this);
		} else {
			$this->update(array("supporters"));
		}

	}


	/**
	 * admit the proposal circumventing the quorum
	 *
	 * @param string  $text
	 */
	function set_admission_decision($text) {
		DB::transaction_start();
		$this->read();
		if ($this->state=="draft" or $this->state=="submitted") {
			$this->state = "admitted";
			$this->update(array("admission_decision", "state"), 'admitted=now()');
			DB::transaction_commit();
			$this->issue()->proposal_admitted($this);
		} else {
			// Setting or updating the description is always allowed.
			$this->update(array("admission_decision"));
			DB::transaction_commit();
		}
	}


	/**
	 * if it is allowed to move the issue to a different issue
	 *
	 * @return boolean
	 */
	public function allowed_move_to_issue() {
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
	 * get all issues, where the proposal could be moved to
	 *
	 * @return array
	 */
	public function options_move_to_issue() {

		$options = array();

		if ( count($this->issue()->proposals()) > 1 ) {
			$options[0] = _("create a new issue");
		}

		$sql = "SELECT * FROM issues WHERE id != ".intval($this->issue)." AND period";
		if ($period = $this->issue()->period) $sql .= "=".intval($period); else $sql .= " IS NULL";
		$sql .= " ORDER BY area, id DESC";
		$result = DB::query($sql);
		while ( $issue = DB::fetch_object($result, "Issue") ) {
			$proposals = $issue->proposals(true);
			if (!$proposals) continue; // ignore issues with only closed proposals
			$title = limitstr($issue->area()->name, 20);
			$i = 0;
			foreach ( $proposals as $proposal ) {
				$title .= ", ".$proposal->id;
				if ($i < 3) $title .= ": ".limitstr($proposal->title, 20);
				$i++;
			}
			$options[$issue->id] = $title;
		}

		return $options;
	}


	/**
	 * move the proposal to a different issue
	 *
	 * @param integer $new_issue_id
	 */
	public function move_to_issue($new_issue_id) {

		DB::transaction_start();
		$this->read();

		if ( !$this->allowed_move_to_issue() ) {
			DB::transaction_rollback();
			warning(_("Moving this proposal is not allowed anymore."));
			redirect();
		};

		$options = $this->options_move_to_issue();
		if (!isset($options[$new_issue_id])) {
			DB::transaction_rollback();
			warning(_("The selected option is not available."));
			redirect();
		}

		$old_issue = $this->issue();

		if ($new_issue_id) {
			$new_issue = new Issue($new_issue_id);
			if (!$new_issue->id) {
				DB::transaction_rollback();
				warning(_("The issue does not exist."));
				redirect();
			}
		} else {
			// create a new empty issue
			$new_issue = new Issue;
			$new_issue->area   = $old_issue->area;
			$new_issue->period = $old_issue->period;
			$new_issue->state  = $old_issue->state;
			// If the old issue reached ballot voting, the new issue gets ballot voting unseen the number of ballot voting demanders.
			$new_issue->votingmode_reached = $old_issue->votingmode_reached;
			$new_issue->debate_started = $old_issue->debate_started;
			$new_issue->create();
		}

		$this->issue = $new_issue->id;

		if ( ! $this->update(array('issue')) ) {
			DB::transaction_rollback();
			return;
		}

		DB::transaction_commit();

		// cancel empty issue
		if ( ! $old_issue->proposals() ) $old_issue->cancel();

		// send notification
		$notification = new Notification("proposal_moved");
		$notification->period    = $period;
		$notification->issue_old = $old_issue;
		$notification->issue     = $new_issue;
		$notification->proposal  = $this;
		// votingmode voters of both issues
		$sql = "SELECT DISTINCT member FROM votingmode_tokens WHERE issue=".intval($old_issue->id)." OR issue=".intval($new_issue->id);
		$recipients = DB::fetchfieldarray($sql);
		// supporters and proponents of the proposal
		$sql = "SELECT DISTINCT member FROM supporters WHERE proposal=".intval($this->id);
		$recipients = array_unique($recipients, DB::fetchfieldarray($sql));
		$notification->send($recipients);

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
		$this->revoke = null;
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
		$issue->cancel();

	}


	/**
	 *
	 * @return boolean
	 */
	public function allowed_edit_content() {
		if (Login::$admin) {
			switch ($this->issue()->state) {
			case "admission":
			case "debate":
			case "preparation":
				return true;
			}
		} else {
			switch ($this->issue()->state) {
			case "admission":
				switch ($this->state) {
				case "draft":
					return true;
				}
			}
		}
		return false;
	}


	/**
	 *
	 * @return boolean
	 */
	public function allowed_edit_reason_only() {
		switch ($this->issue()->state) {
		case "admission":
		case "debate":
			switch ($this->state) {
			case "submitted":
			case "admitted":
				return true;
			}
		}
		return false;
	}


	/**
	 * if it's allowed to add or rate arguments
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


	// view


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
					$supporters[] = '<span class="self">'.$row['proponent'].' <i>('._("proponent").')</i></span>';
				} elseif ($row['anonymous']===DB::value_true) {
					$is_supporter = "anonymous";
					$supporters[] = '<span class="self">'._("anonymous").'</span>';
				} else {
					$is_supporter = true;
					$supporters[] = '<span class="self">'.$member->link().'</span>';
				}
			} else {
				if ($row['proponent_confirmed']) {
					$supporters[] = $row['proponent'].' <i>('._("proponent").')</i>';
				} elseif ($row['anonymous']===DB::value_true) {
					$supporters[] = _("anonymous");
				} else {
					$supporters[] = $member->link();
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
	 * display quorum bargraph
	 *
	 * @param boolean $supported_by_member (optional)
	 */
	public function bargraph_quorum($supported_by_member=false) {

		$value = $this->supporters;
		$population = $this->issue()->area()->population();
		$required = $this->quorum_required();
		$title = sprintf(
			_("%d supporters of %d participants - %d supporters (%s) currently required for admission"),
			$this->supporters,
			$population,
			$required,
			numden2percent($this->quorum_level())
		);

		$min_width = 120;
		$max_width = 360;
		$bar_width     = round( min($value,    $population) / $population * $max_width );
		$required_left = round( min($required, $population) / $population * $max_width );
		$width = max($min_width, $bar_width);

		?><div class="bargraph bargraph_quorum" style="width:<?=$width?>px" title="<?=$title?>"><?
		?><div class="bar yes" style="width:<?=$bar_width?>px"></div><?
		?><div class="required" style="margin-left:<?=$required_left?>px"></div><?
		?><div class="legend" style="width:<?=$width?>px"><?=$value?></div><?
		if ($supported_by_member) {
			?><div class="supported" title="<?=_("You support this proposal.")?>">&#10003;</div><?
		}
		?><div class="clear"></div><?
		?></div><?

	}


	/**
	 * display score bargraph
	 *
	 * @param integer $score
	 * @param integer $score_max
	 */
	public function bargraph_score($score, $score_max) {

		$title = sprintf(_("%d points"), $score);

		$width = 100;
		if ($score_max) {
			$bar_width = round( $score / $score_max * $width );
		} else {
			$bar_width = 0;
		}

		?><div class="bargraph score" title="<?=$title?>"><?
		?><div class="bar" style="width:<?=$bar_width?>px"></div><?
		?><div class="legend"><?=$score?></div><?
		?><div class="clear"></div><?
		?></div><?

	}


	/**
	 * display acceptance bargraph
	 *
	 * @param integer $yes
	 * @param integer $no
	 * @param integer $abstention
	 * @param boolean $accepted
	 */
	public function bargraph_acceptance($yes, $no, $abstention, $accepted) {

		$all = $yes + $no;

		$width = 100;

		if ($all) {
			$title = sprintf(
				_("Yes: %d (%s), No: %d (%s), Abstention: %d"),
				$yes,
				$legend = round($yes / $all * 100)."%",
				$no,
				round($no / $all * 100)."%",
				$abstention
			);
			$width_yes = round( $yes / $all * $width );
		} else {
			$title = sprintf(
				_("Yes: 0, No: 0, Abstention: %d"),
				$abstention
			);
		}

		?><div class="bargraph acceptance" title="<?=$title?>"><?
		if ($all) {
			$width_no = $width - $width_yes;
			?><div class="bar yes" style="width:<?=$width_yes?>px">&nbsp;</div><?
			?><div class="bar no" style="width:<?=$width_no?>px">&nbsp;</div><?
			if ($accepted) $legend .= " *";
			?><div class="legend"><?=$legend?></div><?
		} else {
			?><div class="bar" style="width:<?=$width?>px">&nbsp;</div><?
		}
		?><div class="clear"></div><?
		?></div><?

	}


	/**
	 * display the right column with area and proponents
	 *
	 * @param Issue   $issue
	 * @param array   $proponents
	 * @param boolean $is_proponent
	 */
	public function display_proposal_info(Issue $issue, array $proponents, $is_proponent) {
?>
<h2><?=_("Area")?></h2>
<p class="proposal"><?=h($issue->area()->name)?></p>
<h2><?=_("Proponents")?></h2>
<ul>
<?
		foreach ( $proponents as $proponent ) {
?>
	<li><?
			if ($proponent->proponent_confirmed) {
				echo content2html($proponent->proponent_name);
			} else {
				?><span class="unconfirmed"><?=content2html($proponent->proponent_name)?></span><?
			}
			?></li>
<?
		}
?>
</ul>
<?

		// show drafts only to the proponents
		if (!$is_proponent) return;

		$this->display_drafts($proponents);

	}


	/**
	 * display the list of drafts for the right column
	 *
	 * @param array   $proponents
	 */
	public function display_drafts(array $proponents) {
?>
<h2><?=_("Drafts")?></h2>
<?
		$sql = "SELECT * FROM drafts WHERE proposal=".intval($this->id)." ORDER BY created DESC";
		$result = DB::query($sql);
		$i = DB::num_rows($result);
?>
<script type="text/javascript">
<!--
function draft_select(side, draft) {
	for (var i = 0; i < <?=$i?>; i++) {
		if ( ( side == 1 && i > draft ) || ( side == 2 && i < draft ) ) {
			document.getElementById('draft'+side+'_'+i).disabled = false;
		} else {
			document.getElementById('draft'+side+'_'+i).disabled = true;
		}
	}
}
//-->
</script>
<form action="diff.php" method="GET">
<table class="drafts">
<?
		$j = 0;
		$disabled1 = true;
		$disabled2 = false;
		while ( $draft = DB::fetch_object($result, "Draft") ) {
			if ($draft->author===null) {
				$proponent_name = '<span class="admin">'._("Admin").'</span>';
			} else {
				// get the author's proponent name
				$author = new Member($draft->author);
				$proponent_name = "("._("proponent revoked").")";
				foreach ($proponents as $proponent) {
					if ($proponent->id == $author->id) {
						$proponent_name = limitstr($proponent->proponent_name, 50);
						break;
					}
				}
			}
			if ($j==0) {
				$link = "proposal.php?id=".$this->id;
			} else {
				$link = "draft.php?id=".$draft->id;
			}
?>
<tr<?
			if (
				(BN=="draft.php" and $draft->id==@$_GET['id']) or
				((BN=="proposal.php" or BN=="proposal_edit.php") and $j==0)
			) { ?> class="active"<? }
			?>>
	<td class="diffradio"><span><?=$i?></span><br><input type="radio" name="draft1" id="draft1_<?=$j?>" value="<?=$draft->id?>"<?
			if ( isset($_GET['draft1']) ? $_GET['draft1']==$draft->id : $j==1 ) {
				?> checked<?
				$disabled1=false;
			} elseif ($disabled1) {
				?> disabled<?
			}
			?> onClick="draft_select(2, <?=$j?>)"><input type="radio" name="draft2" id="draft2_<?=$j?>" value="<?=$draft->id?>"<?
			if ( isset($_GET['draft2']) ? $_GET['draft2']==$draft->id : $j==0 ) {
				?> checked<?
				$disabled2=true;
			} elseif ($disabled2) {
				?> disabled<?
			}
			?> onClick="draft_select(1, <?=$j?>)"></td>
	<td class="content" onClick="location.href='<?=$link?>'"><a href="<?=$link?>"><?=datetimeformat_smart($draft->created)?></a> <?=$proponent_name?></td>
</tr>
<?
			$i--;
			$j++;
		}
?>
</table>
<input type="submit" value="<?=_("compare versions")?>">
</form>
<?
	}


	/**
	 * display the list of drafts for the right column
	 *
	 * @param array   $proponents
	 */
	public function display_drafts_without_form(array $proponents) {
?>
<h2><?=_("Drafts")?></h2>
<table class="drafts">
<?
		$sql = "SELECT * FROM drafts WHERE proposal=".intval($this->id)." ORDER BY created DESC";
		$result = DB::query($sql);
		$i = DB::num_rows($result);
		$j = 0;
		while ( $draft = DB::fetch_object($result, "Draft") ) {
			// get the author's proponent name
			$author = new Member($draft->author);
			$proponent_name = "("._("proponent revoked").")";
			foreach ($proponents as $proponent) {
				if ($proponent->id == $author->id) {
					$proponent_name = $proponent->proponent_name;
					break;
				}
			}
			if ($j==0) {
				$link = "proposal.php?id=".$this->id;
			} else {
				$link = "draft.php?id=".$draft->id;
			}
?>
<tr>
	<td class="content" onClick="location.href='<?=$link?>'"><?=$i?> <a href="<?=$link?>"><?=datetimeformat_smart($draft->created)?></a> <?=limitstr($proponent_name, 30)?></td>
</tr>
<?
			$i--;
			$j++;
		}
?>
</table>
<?
	}


}
