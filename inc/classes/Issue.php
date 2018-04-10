<?
/**
 * Issue
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Issue extends Relation {

	// database table
	public $period;
	public $area;
	public $votingmode_demanders;
	public $votingmode_reached;
	public $votingmode_admin;
	public $debate_started;
	public $preparation_started;
	public $voting_started;
	public $counting_started;
	public $finished;
	public $clear;
	public $cleared;
	public $state;

	private $area_obj;
	private $period_obj;

	protected $boolean_fields = array("votingmode_reached", "votingmode_admin");
	protected $create_fields = array("area");
	protected $update_fields = array("period", "area", "state");


	/**
	 * get the area this issue belongs to
	 *
	 * @return object
	 */
	function area() {
		if (!is_object($this->area_obj)) $this->area_obj = new Area($this->area);
		return $this->area_obj;
	}


	/**
	 * get the voting period this issue is assigned to
	 *
	 * @return object
	 */
	function period() {
		if (!is_object($this->period_obj)) $this->period_obj = new Period($this->period);
		return $this->period_obj;
	}


	/**
	 * update with extra string for SQL expressions
	 *
	 * @param mixed   $fields (optional) array or false
	 * @param string  $extra  (optional)
	 * @return resource
	 */
	function update($fields=false, $extra="") {

		if (!$fields) $fields = $this->update_fields;

		$fields_values = array();
		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		return DB::update("issue", "id=".intval($this->id), $fields_values, $extra);
	}


	/**
	 * get all proposals in this issue
	 *
	 * @param boolean $open (optional) get only open proposals
	 * @return array
	 */
	public function proposals($open=false) {
		$sql = "SELECT * FROM proposal WHERE issue=".intval($this->id);
		if ($open) $sql .= " AND state IN ('draft', 'submitted', 'admitted')";
		$sql .= " ORDER BY id";
		$result = DB::query($sql);
		$proposals = array();
		while ( $proposal = DB::fetch_object($result, "Proposal") ) {
			$proposals[] = $proposal;
		}
		return $proposals;
	}


	/**
	 * human friendly state names
	 *
	 * @return string
	 */
	public function state_name() {
		static $states;
		if (!$states) $states = array(
				'entry'       => _("Entry"), // Usually proposal states are shown instead.
				'debate'      => _("Debate"),
				'preparation' => _("Voting preparation"),
				'voting'      => _("Voting"),
				'counting'    => _("Counting"),
				'finished'    => _("Finished"),
				'cancelled'   => _("Cancelled") // when all proposals are cancelled
			);
		return $states[$this->state];
	}


	/**
	 * additional info about the current state
	 *
	 * @return string
	 */
	private function state_info() {
		switch ($this->state) {
		case "debate":
			return sprintf(
				_("until %s"),
				'<span class="datetime">'.datetimeformat_smart($this->period()->preparation).'</span>'
			);
		case "preparation":
			if ($this->votingmode_offline()) return;
			return sprintf(
				_("until %s"),
				'<span class="datetime">'.datetimeformat_smart($this->period()->voting).'</span>'
			);
		case "voting":
			return sprintf(
				_("until %s"),
				'<span class="datetime">'.datetimeformat_smart($this->period()->counting).'</span>'
			);
		}
	}


	/**
	 * set the issue to state "finished" and set time for clearing
	 */
	public function finish() {
		$this->state = "finished";
		$this->update(["state"], "finished = now(), clear = current_date + interval ".DB::esc(CLEAR_INTERVAL));
	}


	/**
	 * set the issue to state "cancelled" and set time for clearing
	 */
	public function cancel() {
		$this->state = "cancelled";
		$this->update(["state"], "clear = current_date + interval ".DB::esc(CLEAR_INTERVAL));
	}


	/**
	 * create a unique token for the member and the current issue
	 *
	 * @param string  $table  database table
	 * @param Member  $member
	 * @return string
	 */
	private function create_unique_token($table, Member $member) {
		DB::transaction_start();
		do {
			$token = Login::generate_token(8);
			$sql = "SELECT token FROM $table WHERE token=".DB::esc($token);
		} while ( DB::numrows($sql) );
		$sql = "INSERT INTO $table (member, issue, token) VALUES (".intval($member->id).", ".intval($this->id).", ".DB::esc($token).")";
		DB::query($sql);
		DB::transaction_commit();
		return $token;
	}


	/**
	 * offline instead of online voting is used
	 *
	 * @return boolean
	 */
	public function votingmode_offline() {
		return $this->votingmode_reached or $this->votingmode_admin;
	}


	/**
	 * look if we are in the phase of voting mode determination
	 *
	 * The phase starts at the submission of the first proposal.
	 *
	 * @param boolean $submitted (optional) provide information if at least one proposal is already submitted
	 * @return boolean
	 */
	public function votingmode_determination($submitted=null) {

		if ( $this->votingmode_determination_finished() ) return false;

		if ($submitted!==null) return $submitted==true;
		// look if there is at least one already submitted proposal
		$sql = "SELECT COUNT(1) FROM proposal WHERE issue=".intval($this->id)." AND state!='draft'";
		return DB::fetchfield($sql) > 0;
	}


	/**
	 * Voting mode determination is finished if eigher the quorum is reached or voting preparation starts.
	 *
	 * @return boolean
	 */
	public function votingmode_determination_finished() {
		if ($this->votingmode_offline()) return true;
		if (!$this->votingmode_determination_admin()) return true;
	}


	/**
	 * Voting mode determination for admins is finished if voting preparation starts.
	 *
	 * @return boolean
	 */
	public function votingmode_determination_admin() {
		if ($this->state=="entry" or $this->state=="debate") return true;
	}


	/**
	 * quorum for offline voting
	 *
	 * @return array
	 */
	function quorum_votingmode_level() {
		return array(QUORUM_VOTINGMODE_NUM, QUORUM_VOTINGMODE_DEN);
	}


	/**
	 * number of required offline voting demanders
	 *
	 * @return integer
	 */
	function quorum_votingmode_required() {
		list($num, $den) = $this->quorum_votingmode_level();
		$area = new Area($this->area);
		return max( ceil($area->participants * $num / $den), $area->ngroup()->minimum_quorum_votingmode );
	}


	/**
	 * look if the logged in member demands offline voting
	 *
	 * @return boolean
	 */
	public function votingmode_demanded_by_member() {
		$sql = "SELECT demand
 			FROM votingmode_vote
			JOIN votingmode_token USING (token)
			WHERE issue=".intval($this->id)." AND member=".intval(Login::$member->id)."
 			ORDER BY votetime DESC
 			LIMIT 1";
		$result = DB::query($sql);
		if ( $row = DB::fetch_assoc($result) ) {
			DB::to_bool($row['demand']);
			return $row['demand'];
		} else {
			return false;
		}
	}


	/**
	 * demand offline voting
	 *
	 * @return boolean
	 */
	function demand_votingmode() {
		if (!$this->votingmode_determination()) {
			warning("Demand for offline voting can not be added, because the proposal is not in entry or debate phase!");
			return false;
		}
		$token = $this->votingmode_token(true);
		$this->votingmode_vote($token, true);
		$this->update_votingmode_cache();
	}


	/**
	 * revoke demand for offline voting
	 *
	 * @return boolean
	 */
	function revoke_votingmode() {
		if (!$this->votingmode_determination()) {
			warning("Demand for offline voting can not be removed, because the issue is not in entry or debate phase!");
			return false;
		}
		// The token has already been created when offline voting was demanded.
		$token = $this->votingmode_token();
		$this->votingmode_vote($token, false);
		$this->update_votingmode_cache();
	}


	/**
	 * save offline voting selected by admin
	 *
	 * @param boolean $value
	 * @return boolean
	 */
	function save_votingmode_admin($value) {
		if (!$this->votingmode_determination_admin()) {
			warning("Voting mode can not be set anymore, because the issue is not in entry or debate phase!");
			return false;
		}
		$this->votingmode_admin = $value;
		$this->update(['votingmode_admin']);
	}


	/**
	 * count offline voting demanders and save the result
	 */
	function update_votingmode_cache() {

		// count demanding latest votes
		$sql = "SELECT token, demand
 			FROM votingmode_vote
			JOIN votingmode_token USING (token)
			WHERE issue=".intval($this->id)."
 			ORDER BY token, votetime DESC";
		$result = DB::query($sql);
		$count = 0;
		$previous_token = null;
		while ( $row = DB::fetch_assoc($result) ) {

			// skip overridden votes
			if ($row['token']==$previous_token) continue;
			$previous_token = $row['token'];

			DB::to_bool($row['demand']);
			if ($row['demand']) $count++;

		}

		if ( $count >= $this->quorum_votingmode_required() ) {
			$sql = "UPDATE issue SET votingmode_demanders=".intval($count).", votingmode_reached=TRUE WHERE id=".intval($this->id);
		} else {
			$sql = "UPDATE issue SET votingmode_demanders=".intval($count)." WHERE id=".intval($this->id);
		}
		DB::query($sql);

	}


	/**
	 * get the token of the logged in member for demanding offline voting
	 *
	 * @param boolean $create (optional) create a new token if it does not exist yet
	 * @return string
	 */
	public function votingmode_token($create=false) {
		$sql = "SELECT token FROM votingmode_token WHERE member=".intval(Login::$member->id)." AND issue=".intval($this->id);
		$result = DB::query($sql);
		if ( $row = DB::fetch_assoc($result) ) return $row['token'];
		if ($create) return $this->create_unique_token("votingmode_token", Login::$member);
	}


	/**
	 * save vote for voting mode for this issue
	 *
	 * @param string  $token
	 * @param boolean $demand
	 */
	public function votingmode_vote($token, $demand) {

		DB::transaction_start();

		$sql = "INSERT INTO votingmode_vote (token, demand) VALUES (".DB::esc($token).", ".DB::bool_to_sql($demand).") RETURNING votetime";
		if ( $result = DB::query($sql) ) {
			list($votetime) = pg_fetch_row($result);

			if (!Login::$member->mail) {
				notice(_("Your vote on the voting mode has been saved, but the email receipt could not be sent, because you have no confirmed email address!"));
			} else {

				// Since the subject can not be encrypted, we don't show which issue.
				$subject = _("Vote receipt for voting mode");

				$body = _("Group").": ".$this->area()->ngroup()->name."\n\n";

				$body .= sprintf(_("Receipt for your vote on the voting mode for issue %d:"), $this->id)."\n\n";
				foreach ( $this->proposals() as $proposal ) {
					$body .= mb_wordwrap(_("Proposal")." ".$proposal->id.": ".$proposal->title)."\n"
						.BASE_URL."proposal.php?id=".$proposal->id."\n\n";
				}
				$body .= _("You demand offline voting").": ".($demand?_("Yes"):_("No"))."\n\n"
					._("Your username").": ".Login::$member->username."\n"
					._("Your user ID").": ".Login::$member->id."\n"
					._("Your voting mode token").": ".$token."\n"
					._("Voting time").": ".date(VOTETIME_FORMAT, strtotime($votetime))."\n\n"
					._("You can change your choice again:")."\n"
					.BASE_URL."vote.php?issue=".$this->id."\n";

				if ( send_mail(Login::$member->mail, $subject, $body, array(), Login::$member->fingerprint) ) {
					success(_("Your vote on the voting mode has been saved and an email receipt has been sent to you."));
				} else {
					warning(_("Your vote on the voting mode has been saved, but the email receipt could not be sent!"));
				}

			}

			DB::transaction_commit();

		} else {
			warning(_("Your vote on the voting mode could not be saved!"));
			DB::transaction_rollback();
		}

	}


	/**
	 * get the vote token of the logged in member
	 *
	 * @return string
	 */
	public function vote_token() {
		$sql = "SELECT token FROM vote_token WHERE member=".intval(Login::$member->id)." AND issue=".intval($this->id);
		return DB::fetchfield($sql);
	}


	/**
	 * save vote for this issue
	 *
	 * @param string  $token
	 * @param array   $vote
	 */
	public function vote($token, array $vote) {

		// example for one single proposal:
		// array( 123 => array('acceptance' => 0) )
		// example for two proposals:
		// array( 123 => array('acceptance' => 1, 'score' => 2), 456 => array('acceptance' => -1, 'score' => 0) )

		// convert strings to integers
		foreach ( $vote as &$value ) {
			$value = array_map('intval', $value);
		}
		unset($value);

		DB::transaction_start();

		$sql = "INSERT INTO vote_vote (token, vote) VALUES (".DB::esc($token).", ".DB::esc(serialize($vote)).") RETURNING votetime";
		if ( $result = DB::query($sql) ) {
			list($votetime) = pg_fetch_row($result);

			if (!Login::$member->mail) {
				notice(_("Your vote has been saved, but the email receipt could not be sent, because you have no confirmed email address!"));
			} else {

				// Since the subject can not be encrypted, we don't show which issue.
				$subject = _("Vote receipt");

				$body = _("Group").": ".$this->area()->ngroup()->name."\n\n";

				$body .= sprintf(_("Vote receipt for your vote on issue %d:"), $this->id)."\n\n";
				foreach ( $vote as $proposal_id => $vote_proposal ) {
					$proposal = new Proposal($proposal_id);
					$body .= mb_wordwrap(_("Proposal")." ".$proposal_id.": ".$proposal->title)."\n"
						.BASE_URL."proposal.php?id=".$proposal->id."\n"
						._("Acceptance").": ".acceptance($vote_proposal['acceptance']);
					if (isset($vote_proposal['score'])) $body .= ", "._("Score").": ".score($vote_proposal['score']);
					$body .= "\n\n";
				}
				$body .= _("Your username").": ".Login::$member->username."\n"
					._("Your user ID").": ".Login::$member->id."\n"
					._("Your vote token").": ".$token."\n"
					._("Voting time").": ".date(VOTETIME_FORMAT, strtotime($votetime))."\n\n"
					._("You can change your vote by voting again on:")."\n"
					.BASE_URL."vote.php?issue=".$this->id."\n";

				if ( send_mail(Login::$member->mail, $subject, $body, array(), Login::$member->fingerprint) ) {
					success(_("Your vote has been saved and an email receipt has been sent to you."));
				} else {
					warning(_("Your vote has been saved, but the email receipt could not be sent!"));
				}

			}

			DB::transaction_commit();

		} else {
			warning(_("Your vote could not be saved!"));
			DB::transaction_rollback();
		}

	}


	/**
	 * counting/download of votes
	 *
	 * @return boolean
	 */
	public function counting() {
		if ($this->period()->vvvote) {
			return $this->counting_vvvote();
		} else {
			return $this->counting_default();
		}
	}


	/**
	 * counting of votes
	 *
	 * @return boolean
	 */
	public function counting_default() {

		$proposals = $this->proposals(true);

		foreach ( $proposals as $proposal ) {
			$proposal->yes        = 0;
			$proposal->no         = 0;
			$proposal->abstention = 0;
			$proposal->score      = 0;
		}

		$sql = "SELECT token, vote FROM vote_vote
 			JOIN vote_token USING (token)
			WHERE issue=".intval($this->id)."
			ORDER BY token, votetime DESC";
		$result = DB::query($sql);
		$previous_token = null;
		while ( $row = DB::fetch_assoc($result) ) {

			// skip overridden votes
			if ($row['token']==$previous_token) continue;
			$previous_token = $row['token'];

			$vote = unserialize($row['vote']);

			foreach ( $proposals as $proposal ) {
				switch ($vote[$proposal->id]['acceptance']) {
				case -1:
					$proposal->abstention++;
					break;
				case 0:
					$proposal->no++;
					break;
				case 1:
					$proposal->yes++;
					break;
				}
				if ( count($proposals) > 1 ) {
					$proposal->score += $vote[$proposal->id]['score'];
				}
			}

		}

		foreach ( $proposals as $proposal ) {
			/** @var $proposal Proposal */
			$proposal->accepted = ( $proposal->yes > $proposal->no );
			$proposal->update(['yes', 'no', 'abstention', 'score', 'accepted']);
		}

		return true;
	}


	/**
	 * fetch voting result from vvvote server
	 *
	 * @return boolean
	 */
	public function counting_vvvote() {

		$servers = split_csa(VVVOTE_SERVERS);
		$post = array(
			'cmd' => "getStatistic",
			'electionId' => $this->period()->vvvote_election_id(),
			'questionID' => (int) $this->id
		);
		$post_json = json_encode($post);

		$result = vvvote_curl_post_json($servers[0]."api/v1/getresult", $post_json);
		if (! is_array($result) ) {
			trigger_error("While trying to get the result, an answer from Vvvote server could not be interpreted as json, ElectionId: " . $post['electionId'] . ', Server: ' . $server . ', received: ' . print_r($result, true), E_USER_WARNING);
			return;
		}
		
		if ( isset($result['cmd']) and $result['cmd'] == "showStatistic" and !empty($result['data'][$this->id]) ) {

			foreach ( $result['data'][$this->id] as $proposal_id => $proposal_result ) {

				$proposal = new Proposal($proposal_id);
				if ($proposal->issue != $this->id) {
					trigger_error("Proposal is not in the expected issue", E_USER_WARNING);
					continue;
				}
				$proposal->yes        = $proposal_result['yesNo']['numYes'];
				$proposal->no         = $proposal_result['yesNo']['numNo'];
				$proposal->abstention = $proposal_result['yesNo']['numAbstention'];
				if (isset($proposal_result['score'])) {
					$proposal->score  = $proposal_result['score']['sum'];
				}
				$proposal->accepted = ( $proposal->yes > $proposal->no );
				$proposal->update(['yes', 'no', 'abstention', 'score', 'accepted']);

			}

			return true;
		}

		trigger_error("Fetching vote result from vvvote server failed", E_USER_WARNING);
		return false;

	}


	/**
	 * will be called after a proposal gets admitted
	 *
	 * @param Proposal $admitted_proposal
	 */
	public function proposal_admitted(Proposal $admitted_proposal) {

		$admitted_proposals = array($admitted_proposal);

		// admit other proposals too, if they reached the quorum due to the now lower level
		foreach ( $this->proposals() as $proposal ) {
			/** @var $proposal Proposal */
			if ($proposal->id==$admitted_proposal->id) continue;
			if ( $proposal->submitted and !$proposal->quorum_reached and $proposal->supporters >= $proposal->quorum_required() ) {
				// admit proposal
				$proposal->quorum_reached = true;
				$proposal->state = "admitted";
				$proposal->update(["quorum_reached", "state"], "admitted=now()");
				$admitted_proposals[] = $proposal;
			}
		}

		// send notification
		$notification = new Notification("admitted");
		$notification->issue = $this;
		$notification->proposals = $admitted_proposals;
		$notification->send();

		/* for now admins do the selection of the periods manually

		// The period has already been set by another proposal in the same issue.
		if ($this->period) return;

		// select the next period, which has not yet started
		$sql = "SELECT id FROM period
			WHERE ngroup=".intval($this->period()->ngroup)."
				AND debate > now()
			ORDER BY debate
			LIMIT 1";
		$this->period = DB::fetchfield($sql);
		if ($this->period) {
			$this->update(["period"]);
		} else {
			// Error
		}

		*/

	}


	/**
	 * head for proposals table
	 *
	 * @param boolean $show_results (optional) display the result column
	 * @param boolean $show_score   (optional) display the score column
	 * @param boolean $show_ngroup  (optional)
	 */
	public static function display_proposals_th($show_results=false, $show_score=false, $show_ngroup=false) {
?>
	<tr>
<?
		if ($show_ngroup) {
?>
		<th><?=_("Group")?></th>
<?
		}
?>
		<th class="proposal"><?=_("Proposal")?></th>
<?
		if (BN=="vote.php") {
?>
		<th><?=_("Acceptance")?></th>
<?
			if ($show_score) {
?>
		<th><?=_("Score")?></th>
<?
			}
		} else {
			if (BN!="admin_vote_result.php") {
?>
		<th class="support"><?=_("Support")?></th>
<?
			}
			if ($show_results) {
				if (BN=="admin_vote_result.php") {
?>
		<th class="result"><?=_("Yes")?></th>
		<th class="result"><?=_("No")?></th>
		<th class="result"><?=_("Abstention")?></th>
<?
					if ($show_score) {
?>
		<th class="result"><?=_("Score")?></th>
<?
					}
				}
?>
		<th class="result"><?=_("Result")?></th>
<?
			}
?>
		<th class="state"><?=_("State")?></th>
		<th class="period"><?=_("Period")?></th>
		<th class="votingmode"><?=_("Voting mode")?></th>
<?
		}
?>
	</tr>
<?
	}


	/**
	 * proposals to display in the list
	 *
	 * @param boolean $admitted (optional) get only admitted proposals
	 * @return array
	 */
	public function proposals_list($admitted=false) {

		if (Login::$member) {
			$sql = "SELECT proposal.*,
						supporter.member  AS supported_by_member,
						supporter.created AS supported_created
					FROM proposal
					LEFT JOIN supporter ON proposal.id = supporter.proposal AND supporter.member = ".intval(Login::$member->id);
		} else {
			$sql = "SELECT * FROM proposal";
		}
		$sql .= " WHERE issue=".intval($this->id);
		if ($admitted) $sql .= " AND state='admitted'";
		$sql .= " ORDER BY state DESC, accepted DESC, score DESC, id";
		$result = DB::query($sql);
		$proposals = array();
		$submitted = false;
		while ( $proposal = DB::fetch_object($result, "Proposal") ) {
			$proposal->set_issue($this);
			$proposals[] = $proposal;
			// look if there is at least one already submitted proposal
			if ($proposal->state!="draft") $submitted = true;
		}

		return array($proposals, $submitted);
	}


	/**
	 * display table part for all proposals of the issue
	 *
	 * @param array   $proposals         array of objects
	 * @param boolean $submitted
	 * @param integer $period_rowspan
	 * @param boolean $show_results      (optional) display the result column
	 * @param integer $selected_proposal (optional)
	 * @param array   $vote              (optional)
	 * @param boolean $show_ngroup       (optional)
	 */
	function display_proposals(array $proposals, $submitted, $period_rowspan, $show_results=false, $selected_proposal=0, array $vote=array(), $show_ngroup=false) {

		$first = true;
		$first_admitted = true;
		$num_rows = count($proposals);
		foreach ( $proposals as $proposal ) {
			/** @var $proposal Proposal */

			$link = "proposal.php?id=".$proposal->id;

?>
	<tr class="proposal"<?
			if ($first) { ?> id="issue<?=$this->id?>"<? }
			?>>
<?
			if ($show_ngroup and $first) {
?>
		<td rowspan="<?=$num_rows?>" scope="row"><?=$proposal->issue()->area()->ngroup()->name?></td>
<?
			}
?>
		<td class="proposal_link<?
			if ($selected_proposal==$proposal->id) { ?>_active<? }
			$tooltip = "";
			switch ($proposal->state) {
			case "revoked":
				?> revoked<?
				$tooltip = ' title="'._("revoked proposal").'"';
				break;
			case "cancelled_interval":
			case "cancelled_debate":
			case "cancelled_admin":
				?> cancelled<?
				$tooltip = ' title="'._("cancelled proposal").'"';
				break;
			}
			?>" onClick="location.href='<?=$link?>'"><?
			if ($proposal->activity >= ACTIVITY_THRESHOLD) {
				?><img src="img/activity.png" width="31" height="16" class="activity" style="opacity:<?=min($proposal->activity / ACTIVITY_DIVISOR, 1)?>" <?alt(_("Recent activity"))?>><?
			}
			echo _("Proposal")?> <?=$proposal->id?>: <a href="<?=$link?>"<?=$tooltip?>><?=h($proposal->title)?></a></td>
<?
			if (BN=="vote.php") {
				$this->display_column_vote($proposal, $vote, $num_rows);
			} else {
				// column "support"
				if (BN!="admin_vote_result.php") {
?>
		<td class="support" onClick="location.href='<?=$link?>#supporters'"><?
					$proposal->bargraph_quorum($proposal->supported_by_member, $proposal->supporter_valid());
					?></td>
<?
				}
				// column "voting results"
				if ($show_results) {
					if (BN=="admin_vote_result.php") $this->display_column_admin_vote_result($proposal, $num_rows);
					$this->display_results($proposal, $proposals, $first);
				}
				// column "state"
				$this->display_column_state($proposal, $proposals, $first, $first_admitted, $num_rows);
				// columns "period" and "votingmode"
				if ($first) {
					$this->display_period($period_rowspan, $num_rows);
					$this->display_votingmode($num_rows, $submitted, $selected_proposal, $link);
				}
			}
?>
	</tr>
<?

			$first = false;
		}

	}


	/**
	 * column "results"
	 *
	 * @param Proposal $proposal
	 * @param array    $proposals
	 * @param boolean  $first
	 */
	private function display_results(Proposal $proposal, array $proposals, $first) {
		if ($this->state != 'finished') {
?>
		<td></td>
<?
			return;
		}
?>
		<td class="result" onClick="location.href='vote_result.php?issue=<?=$this->id?>'"><?
		static $options_count, $score_max;
		if ($first) {
			// get number of options and highest score
			$options_count = 0;
			$score_max = 0;
			foreach ( $proposals as $proposal_vote ) {
				if ($proposal_vote->yes === null) continue; // skip cancelled proposals
				$score_max = max($score_max, $proposal_vote->score);
				$options_count++;
			}
		}
		if ( $proposal->yes !== null ) { // skip cancelled proposals
			$title = $proposal->bargraph_acceptance($proposal->yes, $proposal->no, $proposal->abstention, $proposal->accepted);
			if ( $options_count > 1 ) {
				$proposal->bargraph_score($proposal->score, $score_max);
			}
			if (BN=="vote_result.php") {
				?><br><?=$title;
			}
		}
		?></td>
<?
	}


	/**
	 * column "state"
	 *
	 * @param Proposal $proposal
	 * @param array    $proposals
	 * @param boolean  $first
	 * @param boolean  $first_admitted (reference)
	 * @param integer  $num_rows
	 */
	private function display_column_state(Proposal $proposal, array $proposals, $first, &$first_admitted, $num_rows) {
		if ($this->state=="entry" or $this->state=="cancelled") {
			// individual proposal states
			if ($proposal->state=="admitted") {
				if ($first_admitted) {
					// count admitted proposals for rowspan
					$num_admitted_rows = 0;
					foreach ($proposals as $p) {
						if ($p->state=="admitted") $num_admitted_rows++;
					}
?>
		<td rowspan="<?=$num_admitted_rows?>" class="center"><?=$proposal->state_name();
					if ($this->period) {
						?><br><span class="stateinfo"><?
						printf(
							_("Debate starts at %s"),
							'<span class="datetime">'.datetimeformat_smart($this->period()->debate).'</span>'
						);
						?></span><?
					} else {
						?><br><span class="stateinfo"><?
						printf(
								_("Admin has to assign a period"));
					}
					?></td>
<?
					$first_admitted = false;
				}
			} else {
				// submitted, cancelled
?>
		<td class="center"><?=$proposal->state_name()?></td>
<?
			}
		} else {
			// issue states
			if ($first) {
?>
		<td rowspan="<?=$num_rows?>" class="center"><?
				if ($this->state=="voting") $this->display_voting(); else echo $this->state_name();
				if ( $state_info = $this->state_info() ) {
					?><br><span class="stateinfo"><?=$state_info?></span><?
				}
				if ($this->state=="voting" and $this->period()->vvvote) $this->display_vvvote_envelope();
				if (Login::$admin and $this->votingmode_offline() and BN!="admin_vote_result.php") {
					if ($this->state=="preparation") {
						?><br><a href="admin_vote_result.php?issue=<?=$this->id?>"><?=_("enter result")?></a><?
					} elseif ($this->state=="finished") {
						?><br><a href="admin_vote_result.php?issue=<?=$this->id?>"><?=_("edit result")?></a><?
					}
				}
				?></td>
<?
			}
		}
	}


	/**
	 * display voting in state column
	 */
	private function display_voting() {
		if ( !Login::$member ) {
			echo _("Voting");
			return;
		}
		if ( !Login::$member->entitled($this->area()->ngroup) ) {
			?><span title="<?=_("You can not vote on this issue, because you are are not entitled in the group.")?>"><?=_("Voting")?></span><?
			return;
		}
		if ($this->period()->vvvote) {
			echo _("Voting");
			return;
		}

		$sql = "SELECT vote_vote.token FROM vote_token
			LEFT JOIN vote_vote USING (token)
			WHERE member=".intval(Login::$member->id)." AND issue=".intval($this->id);
		$result = DB::query($sql);
		if ( list($token) = DB::fetch_row($result) ) {
			?><a href="vote.php?issue=<?=$this->id?>"><?=_("Voting")?></a><?
			if ($token) {
				?><span title="<?=_("You have voted on this issue.")?>">&#10003;</span><?
			}
		} else {
			?><span title="<?=_("You can not vote in this voting period, because you were not yet entitled when the voting started.")?>"><?=_("Voting")?></span><?
		}

	}


	/**
	 * display voting in state column
	 */
	private function display_vvvote_envelope() {
		if ( !Login::$member ) {
			return;
		}
		if ( !Login::$member->entitled($this->area()->ngroup) ) {
			return;
		}

		$sql = "SELECT count(*) FROM vvvote_token
			WHERE member=".intval(Login::$member->id)."
				AND period=".intval($this->period)."
				AND generated=TRUE";
		if ( DB::fetchfield($sql) ) {
			?><br><span class="already_icn" title="<?=_("You have already generated an envelope. Open the envelope in your browser to vote.")?>"><?=_("envelope")?> <i>&#10003;</i></span><?
		} else {
			?><br><a class="period_link" href="vote_vvvote.php?period=<?=$this->period?>"><?=_("envelope")?></a><br><span class="stateinfo"><?
			printf(
				_("until %s"),
				'<span class="datetime">'.datetimeformat_smart($this->period()->vvvote_last_reg).'</span>'
			);
			?></span><?
		}

	}


	/**
	 * column "period"
	 *
	 * @param integer $period_rowspan
	 * @param integer $num_rows
	 */
	private function display_period($period_rowspan, $num_rows) {
		if (Login::$admin and BN!="admin_vote_result.php") {
?>
		<td rowspan="<?=$num_rows?>" class="center nowrap"><?
			$this->display_edit_period();
			?></td>
<?
		} elseif ($period_rowspan) {
			if ($this->period) {
?>
		<td rowspan="<?=$period_rowspan?>" class="center"><a href="periods.php?ngroup=<?=$this->area()->ngroup?>&amp;hl=<?=$this->period?>"><?=$this->period?></a></td>
<?
			} else {
?>
		<td rowspan="<?=$period_rowspan?>"></td>
<?
			}
		}
	}


	/**
	 * column "voting mode"
	 *
	 * @param integer $num_rows
	 * @param boolean $submitted
	 * @param integer $selected_proposal enable form only on detail page
	 * @param string  $link
	 */
	private function display_votingmode($num_rows, $submitted, $selected_proposal, $link) {
?>
		<td rowspan="<?=$num_rows?>"<?

		$admin_form = (Login::$admin and $selected_proposal and $this->votingmode_determination_admin());

		if ($this->votingmode_determination($submitted)) {
			if ($selected_proposal) {
				?> class="votingmode"<?
			} else {
				?> class="votingmode link" onClick="location.href='<?=$link?>#issue'"<?
			}
			?> title="<?
			if (Login::$member) {
				$entitled = Login::$member->entitled($this->area()->ngroup);
				$votingmode_demanded = $this->votingmode_demanded_by_member();
				if ($votingmode_demanded) {
					echo _("You demand offline voting.");
				} elseif ($entitled) {
					echo _("You can demand offline voting.");
				} else {
					echo _("You are not entitled in this group.");
				}
			} else {
				$entitled = false;
				$votingmode_demanded = false;
				echo _("determination if online or offline voting");
			}
			?>"><?
			if (!$selected_proposal) { ?><a href="<?=$link?>#issue"><? }
			?><img src="data:img/png;base64,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" alt="<?=_("determination if online or offline voting")?>" class="vmiddle"><?
			if (!$selected_proposal) { ?></a><? }
			if ($votingmode_demanded) { ?>&#10003;<? }
			if ($selected_proposal) {
				$disabled = $entitled ? "" : " disabled";
				form(URI::same());
				if ($votingmode_demanded) {
					echo _("You demand offline voting.")?>
<input type="hidden" name="action" value="revoke_votingmode">
<input type="submit" class="orange_but first" value="<?=_("Revoke")?>"<?=$disabled?>>
<?
				} else {
?>
<input type="hidden" name="action" value="demand_votingmode">
<input type="submit" class="orange_but first" value="<?=_("Demand offline voting")?>"<?=$disabled?>>
<?
				}
				form_end();
			}
		} elseif ($this->votingmode_offline()) {
			if ($admin_form) {
				?> class="votingmode"<?
			} else {
				?> class="votingmode link" onClick="location.href='votingmode_result.php?issue=<?=$this->id?>'"<?
			}
			?> title="<?=_("offline voting")?>"><a href="votingmode_result.php?issue=<?=$this->id?>"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAAB0AAAAcCAMAAACqEUSYAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAACJVBMVEX///8AAAD/jjYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/jjb/jjYAAAAAAAD/jjb/jjb/jjYAAAAAAAAAAAD/jjb/jjYAAAAAAAAAAAD/jjb/jjb/jjb/jjYAAAAAAAD/jjb/jjYAAAAAAAAAAAD/jjb/jjb/jjYAAAAAAAAAAAD/jjb/jjYAAAAAAAD/jjb/jjb/jjYAAAAAAAAAAAD/jjb/jjYAAAAAAAAAAAD/jjb/jjYAAAAAAAD/jjYAAAAAAAD/jjb/jzf/jjYAAAAAAAAAAAD/jjb/jjb/m1H/jjYAAAAAAAD/jjb/jjb/jjb/jjYAAAD/jjb/lkUAAAAAAAD/jjb/jjb/jjYAAAAAAAD/jjb/kj3/kTwCAAAAAAD/jjY0FAMAAAD/jjb/k0AAAAAAAAD/jjZTJw3/jjb/jjb/jjb/jjb/kDn/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/3cT///////////////7/0rH/kj8AAAD/jjb/x6D/t4L/+PT/59b/2b3/////9Oz/nlb/rW7/pWL/0K7//Pr///7/lEL/u4n/oVv/zan/4Mn/nlX/qWj/v5D/6dn/nVT/7N3/sXj/p2T/p2f/xJj/yqX/7+T/8ej/jzj//v1YMhv/wpU1MS7/xZv/y6X/yKH/wZT/tH3/pGD/vY5DQD/9mmH/+fX/4cv/xp7/59T/nlSWSh4TAAAAg3RSTlMAAAAaz2kNk9UE6a8JbUhaXPUbn/ULtaUI8agD8/5MVE4o8K7tBkfsRQ/4mWRNYEF13QG65QJ7jxj8jXpRbzMgx9hI8Pki/oAKlP7leycz1vcQzHn6MHIhxFeH/WDx7s2/hO9XKvaLAWz9LrevGeybDf3o29jc5Ovt8vgCHigwOTgzMt9mna8AAAABYktHRAH/Ai3eAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH4QIJDAUrztWKDAAAAaJJREFUKM910vk7VFEYB/B7rGMJkXUmmlS0EIM22VKh7CqhUIRsZYrRQul9s5xWRSSGkC17+Pt678xcd+5lvj/c5znv5znf89xzryDsCbPG2cXVTXCo7gAah+rhCeBlWTtJ8T5AD6v6AICvUv0O+ksaAHAoUKlBGGzTkFCAMKZQrQ4Ph1s1goqPKFWPiEf1Fo0EOHZcqSdIMSqa8OQpgNNMqWdExRjSWCo+q9I4EeMNpAkAiUkqPSfqecILFwEuMZUmi3qZpilUnLqvptE0HSCDqfRKJuFVOvbadYAspWbn3HhPepNuI5eK8xSaX9Db109aWMSYBqCY2am25NYA5x9IPwaz27T1jp2W3v30+ctXPvgN8ftQWTlAhbOs9+4P/0AcGRgcRfw5VlkFUM129cGvcT4hvox5EnGK/34IUCNr7aPpmVm0ZYr/mYO6elmFBvM8ysoXHjcyOzU0mReX/i6vrFLDGufrG82SWm6ipbWfi9n8t7XEt3eePJUUHcembTrjs+ftHaYok7Hzxcs2SeUv8KrldVf3m7c97/wNu3+oIPwHIwKkUTy87QcAAAAASUVORK5CYII=" alt="<?=_("offline voting")?>" class="vmiddle"></a><?
		} elseif ($this->state!="entry") {
			?> class="votingmode link" onClick="location.href='votingmode_result.php?issue=<?=$this->id?>'" title="<?=_("online voting")?>"><a href="votingmode_result.php?issue=<?=$this->id?>"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAVCAMAAABrN94UAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABIFBMVEX/jjb///8AAAD/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjb/jjYAAAD/jjb/jjb/jjb/jjYAAAAAAAD/jjb/jjb/jjb/jjYAAAAAAAD/jjb/jjb/jjb/jjb/jjYAAAAAAAD/jjb/jjb/jjb/jjb/jjb/jjYAAAD/jjb/jjb/jjb/jjYAAAD/jjb/jjb/jjb/jjYAAAAAAAD/jjYAAAAAAAAAAAAAAAAAAAD/jjb/jjYAAAAAAAAAAAAAAAD/jjb/jjYAAAAAAAAAAAAAAAD/jjYAAAAAAAAAAAAAAAAAAAD/jjYAAABYwUI3AAAAXnRSTlMAAAAQB46XFciAGdhuD9JdArhqe4gw+swFz0sLJnKUBGJK38Xv+DUYqEaR4hVkxBv3lr8DJaxEi7cDjlvzydlZKNv2LvUvZ/n2e3BnEwH+W6GDHONqS98CbwXtQZFjkVkKvwAAAAFiS0dEAmYLfGQAAAAJcEhZcwAACxIAAAsSAdLdfvwAAAAHdElNRQfhAgkMBBvxF4vhAAAA8ElEQVQoz23Q11YCMRAG4GFUEBQsKFhAQOwKYgGRIkW6HQuIkH3/t3AyCXtBdi6yJ/+3Sc4MgFFz4KIy4vkFtyN4FoXXCXxLQiw7gD8ghFgxYXWNcrFuQlDmYsOAzRBDeBa2tlW+g7I42o3INcr5XixuQ2I/KTs4kHkQ8fBIwfEJ7U8BzmTuBUTr/EJCin9Mw2WGPlcgwbq+IbjN8tW5O1ry9wqsAr9RLFFWpoMPftBgVfjxao0P1R/BhkZTjZQ7aIGGdqfbe1K9PdPw8i8aXt/eP3SDAP3PUh80fOH3zwDtOQ1hCoi/oz+E2eJ5jCf4D5zmMziT9LzLAAAAAElFTkSuQmCC" alt="<?=_("online voting")?>" class="vmiddle"></a><?
		} else {
			?>><?
		}

		// offline voting by admin
		if (Login::$admin) {
			if ($admin_form) {
				form(URI::same()."#votingmode", 'id="votingmode"');
				input_hidden("action", "save_votingmode_admin");
?>
			<label><input type="checkbox" name="votingmode_admin" value="1"<?
				if ($this->votingmode_admin) { ?> checked<? }
				?>><?=_("offline voting by admin")?></label><br>
<?
				input_submit(_("apply"));
				form_end();
			} elseif ($this->votingmode_admin) {
				?><br><?=_("offline voting by admin");
			}
		}

		?></td>
<?
	}


	/**
	 * column "vote" on vote.php
	 *
	 * @param Proposal $proposal
	 * @param array    $vote
	 * @param integer  $num_rows
	 */
	private function display_column_vote(Proposal $proposal, array $vote, $num_rows) {
?>
		<td class="vote nowrap">
			<label><input type="radio" name="vote[<?=$proposal->id?>][acceptance]" value="1"<?
		if ($vote[$proposal->id]['acceptance'] == 1) { ?> checked<? }
		?>><?=_("Yes")?></label>
			<label><input type="radio" name="vote[<?=$proposal->id?>][acceptance]" value="0"<?
		if ($vote[$proposal->id]['acceptance'] == 0) { ?> checked<? }
		?>><?=_("No")."\n"?></label>
			<label><input type="radio" name="vote[<?=$proposal->id?>][acceptance]" value="-1"<?
		if ($vote[$proposal->id]['acceptance'] == -1) { ?> checked<? }
		?>><?=_("Abstention")?></label>
		</td>
<?
		if ($num_rows > 1) {
?>
		<td class="vote nowrap">
<?
			$max_score = max_score($num_rows);
			for ( $score = 0; $score <= $max_score; $score++ ) {
?>
			<label><input type="radio" name="vote[<?=$proposal->id?>][score]" value="<?=$score?>"<?
				if ($score == $vote[$proposal->id]['score']) { ?> checked<? }
				?>><?=$score?></label>
<?
			}
?>
		</td>
<?
		}
	}


	/**
	 * vote result form on page admin_vote_result.php
	 *
	 * @param Proposal $proposal
	 * @param integer  $num_rows
	 */
	private function display_column_admin_vote_result(Proposal $proposal, $num_rows) {
?>
		<td><input type="text" size="5" name="yes[<?=$proposal->id?>]" value="<?=$proposal->yes?>"></td>
		<td><input type="text" size="5" name="no[<?=$proposal->id?>]" value="<?=$proposal->no?>"></td>
		<td><input type="text" size="5" name="abstention[<?=$proposal->id?>]" value="<?=$proposal->abstention?>"></td>
<?
		if ($num_rows > 1) {
?>
		<td><input type="text" size="7" name="score[<?=$proposal->id?>]" value="<?=$proposal->score?>"></td>
<?
		}
	}


	/**
	 * check if it's allowed to add an alternative proposal
	 *
	 * @return boolean
	 */
	public function allowed_add_alternative_proposal() {
		return $this->state=="entry";
	}


	/**
	 * get all voting periods to which the issue may be assigned
	 *
	 * Issues, on which the voting already started, may not be postponed anymore.
	 * Issues, which were not started debating, may only be moved into periods
	 * where the debate has not yet started. Otherwise the debate time would be
	 * shorter than for other issues.
	 *
	 * This function may not be used in tests with time_warp(), because it uses static variables!
	 *
	 * @return array list of options for drop down menu or string with explanation
	 */
	public function available_periods() {

		// find out if the state may be changed
		switch ($this->state) {
		case "entry":
			if (!$this->period) {
				// At least one proposal has to be admitted.
				$sql = "SELECT COUNT(1) FROM proposal WHERE issue=".intval($this->id)." AND state='admitted'::proposal_state";
				if ( !DB::fetchfield($sql) ) {
					return _("The issue can be assigned to a voting period when at least one proposal is admitted.");
				}
			}
		case "debate":
		case "preparation":

			// Issues, which were not started debating, may only be moved into periods where the debate has not yet started. Otherwise the debate time would be shorter than for other issues.

			// read options once from the database
			static $options_all = false;
			static $options_admission = false;
			if ($options_all===false) {
				$sql_period = "SELECT *, debate > now() AS debate_not_started FROM period
					WHERE ngroup=".intval($this->area()->ngroup)."
						AND voting > now()
					ORDER BY id";
				$result_period = DB::query($sql_period);
				$options_all = array();
				$options_admission = array(0 => _("no voting period"));
				while ( $period = DB::fetch_object($result_period, "Period") ) {
					DB::to_bool($period->debate_not_started);
					$options_all[$period->id] = $period->id.": ".$period->current_phase();
					if ($period->debate_not_started) {
						$options_admission[$period->id] = $options_all[$period->id];
					}
				}
			}

			if ($this->state=="entry") {
				if ($options_admission) return $options_admission;
				return _("There are no voting periods available, in which the debate has not yet started.");
			} else {
				if ($options_all) return $options_all;
				return _("There are no voting periods available, in which the voting has not yet started.");
			}

		}

	}


	/**
	 * admins select a voting period
	 */
	private function display_edit_period() {

		$options = $this->available_periods();

		if (@$_GET['edit_period']==$this->id and is_array($options) and $options) {
			form(URI::strip(['edit_period'])."#issue".$this->id);
			input_select("period", $options, $this->period);
			?><br><?
			input_hidden("issue", $this->id);
			input_hidden("action", "select_period");
?>
<input type="submit" class="orange_but first mar_t_b" value="<?=_("apply")?>">
<?
			form_end();
		} else {
			if ($this->period) {
				?><a href="periods.php?ngroup=<?=$this->area()->ngroup?>&amp;hl=<?=$this->period?>"><?=$this->period?></a><?
			}
			if (!$options) return;
			if (is_string($options)) {
				?><span class="iconlink disabled"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAQAAAD8fJRsAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAACxIAAAsSAdLdfvwAAAAHdElNRQfhAgkMCSYc0bm9AAAAO0lEQVQY083IMRGAMBAAsIzUBSKokBrFQ81wx/YOnr1fAWQM1WU6dx3SXLsLKfS/9uHdNUN6anMbWu0P2jUhgcbH0ewAAAAASUVORK5CYII=" alt="<?=_("edit")?>" title="<?=$options?>"></span><?
				return;
			}
			?><a href="<?=URI::append(['edit_period'=>$this->id])?>#issue<?=$this->id?>" class="iconlink"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAQAAAD8fJRsAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAACxIAAAsSAdLdfvwAAAAHdElNRQfhAgkMCSYc0bm9AAAAO0lEQVQY083IMRGAMBAAsIzUBSKokBrFQ81wx/YOnr1fAWQM1WU6dx3SXLsLKfS/9uHdNUN6anMbWu0P2jUhgcbH0ewAAAAASUVORK5CYII=" alt="<?=_("edit")?>" title="<?=_("select voting period")?>"></a><?
		}

	}


	/**
	 * display a list of votes
	 *
	 * @param array    $proposals
	 * @param resource $result
	 * @param string   $token     (optional) token of the logged in member for highlighting
	 */
	public static function display_votes(array $proposals, $result, $token="") {
?>
<table class="votes">
<?
		// table head
		if (count($proposals) == 1) {
			$show_scores = false;
?>
<tr><th><?=_("Vote token")?></th><th><?=_("Voting time")?></th><th><?=_("Acceptance")?></th></tr>
<?
		} else {
			$show_scores = true;
?>
<tr><th rowspan="2"><?=_("Vote token")?></th><th rowspan="2"><?=_("Voting time")?></th><?
			foreach ($proposals as $proposal) {
				?><th colspan="2"><?=_("Proposal")?> <?=$proposal->id?></th><?
			}
			?></tr>
<tr><?
			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach ($proposals as $proposal) {
				?><th><?=_("Acceptance")?></th><th><?=_("Score")?></th><?
			}
			?></tr>
<?
		}

		// votes
		$last_token = null;
		while ( $row = DB::fetch_assoc($result) ) {
?>
<tr class="<?=stripes();
			// highlight votes of the logged in member
			if ($token == $row['token']) { ?> self<? }
			// strike through votes, which have been overridden by a later vote
			if ($row['token'] == $last_token) { ?> overridden<? } else $last_token = $row['token'];
			?>"><td><?=$row['token']?></td><?

			if ($row['vote']) {
				?><td class="tdc"><?=date(VOTETIME_FORMAT, strtotime($row['votetime']))?></td><?
				$vote = unserialize($row['vote']);
				foreach ($proposals as $proposal) {
					?><td><?=acceptance($vote[$proposal->id]['acceptance'])?></td><?
					if ($show_scores) {
						?><td class="tdc"><?=score($vote[$proposal->id]['score'])?></td><?
					}
				}
			} else {
				// member did not vote
				?><td class="tdc"></td><?
				/** @noinspection PhpUnusedLocalVariableInspection */
				foreach ($proposals as $proposal) {
					?><td></td><?
					if ($show_scores) {
						?><td class="tdc"></td><?
					}
				}
			}

			?></tr>
<?
		}
?>
</table>
<?
	}


	/**
	 * display a list of voting mode votes
	 *
	 * @param resource $result
	 * @param string   $token  (optional) token of the logged in member for highlighting
	 */
	public static function display_votingmode_votes($result, $token="") {
?>
<table class="votes">
<tr><th><?=_("Vote token")?></th><th><?=_("Voting time")?></th><th><?=_("Demands offline voting")?></th></tr>
<?
		// votes
		$previous_token = null;
		while ( $row = DB::fetch_assoc($result) ) {
			DB::to_bool($row['demand']);
?>
<tr class="<?=stripes();
			// highlight votes of the logged in member
			if ($token == $row['token']) { ?> self<? }
			// strike through votes, which have been overridden by a later vote
			if ($row['token'] == $previous_token) { ?> overridden<? } else $previous_token = $row['token'];
			?>"><td><?=$row['token']?></td><?
			?><td class="tdc"><?=date(VOTETIME_FORMAT, strtotime($row['votetime']))?></td><?
			?><td><? display_checked($row['demand']) ?></td><?
			?></tr>
<?
		}
?>
</table>
<?
	}


}
