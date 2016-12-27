<?
/**
 * Period
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Period extends Relation {

	// database table
	public $ngroup;
	public $debate;
	public $preparation;
	public $voting;
	public $ballot_assignment;
	public $ballot_preparation;
	public $counting;
	public $ballot_voting;
	public $state;
	public $postage;
	public $vvvote;
	public $vvvote_configurl;
	public $vvvote_vote_delay;
	public $vvvote_last_reg;

	public $debate_now;
	public $preparation_now;
	public $voting_now;
	public $ballot_assignment_now;
	public $ballot_preparation_now;
	public $counting_now;

	private $ngroup_obj;

	protected $boolean_fields = array("ballot_voting", "postage", "vvvote");

	private function getVoting_method() {
		$default = 'pseudonymous';
		if ($this->vvvote === true)        {$default = 'vvvote';}
		if ($this->ballot_voting === true) {$default = 'ballot_voting';}
		return $default;
	}
	
	public function dbtableadmin_edit_voting_method() {
		$options = array('vvvote' => _('Anonymous online voting'), 
				'pseudonymous'    => _('Pseudonymous online voting'), 
				'ballot_voting'   => _('Ballot voting'));
		$selected = $this->getVoting_method();
		$onClick = 'onChange="votingMethodChanged(this)"';
		input_select('voting_method', $options, $selected, $onClick);
		?>
		<script> function votingMethodChanged(source) {
			function displayBallotElements(display) {
				document.getElementsByName('ballot_assignment')[0].parentElement.parentElement.style.display=display;
				document.getElementsByName('ballot_preparation')[0].parentElement.parentElement.style.display=display;
				document.getElementsByName('postage')[0].parentElement.parentElement.style.display=display;
			}
			function displayVvvoteElements(display) {
				document.getElementsByName('vvvote_vote_delay')[0].parentElement.parentElement.style.display=display;
				document.getElementsByName('vvvote_last_reg')[0].parentElement.parentElement.style.display=display;
			}
			
			switch (source.value) {
			case "pseudonymous":
				displayBallotElements('none');
				displayVvvoteElements('none')
				break;
			case "vvvote":
				displayBallotElements('none');
				displayVvvoteElements('')
				break;
			case "ballot_voting":
				displayBallotElements('');
				displayVvvoteElements('none')
				break;
			}
		}
		// wait for the rest of the document is loaded then hide/show the elements needed
		var readyStateCheckInterval = setInterval(function() {
		    if (document.readyState === "complete") {
		        clearInterval(readyStateCheckInterval);
				votingMethodChanged(document.getElementsByName('voting_method')[0]);
		    }
		}, 10);
		</script>
		<?
	}
		public function dbtableadmin_print_voting_method() {
			$options = array('vvvote' => _('Anonymous online voting'),
					'pseudonymous'    => _('Pseudonymous online voting'),
					'ballot_voting'   => _('Ballot voting'));
			$selected = $this->getVoting_method();
			
			?> <?=$options[$selected]?> <?
		}
		
				
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
	 * start online voting
	 *
	 * @param array   $issues
	 */
	public function start_voting(array $issues) {

		// entitled members of the ngroup
		$sql = "SELECT member.* FROM member
			JOIN member_ngroup ON member.id = member_ngroup.member AND member_ngroup.ngroup=".intval($this->ngroup)."
			WHERE activated IS NOT NULL AND eligible=TRUE AND verified=TRUE";
		$members = DB::fetchobjectarray($sql, "Member");

		if ($this->vvvote) {
			$this->start_voting_vvvote($issues, $members);
		} else {
			$this->start_voting_default($issues, $members);
		}

	}


	/**
	 * start online voting with default voting method
	 *
	 * @param array   $issues
	 * @param array   $members
	 */
	private function start_voting_default(array $issues, array $members) {

		$personal_tokens = array();
		$all_tokens      = array();
		foreach ($issues as $issue) {
			/** @var $issue Issue */

			// generate vote tokens
			$all_tokens[$issue->id] = array();
			foreach ( $members as $member ) {
				DB::transaction_start();
				do {
					$token = Login::generate_token(8);
					$sql = "SELECT token FROM vote_token WHERE token=".DB::esc($token);
				} while ( DB::numrows($sql) );
				$sql = "INSERT INTO vote_token (member, issue, token) VALUES (".intval($member->id).", ".intval($issue->id).", ".DB::esc($token).")";
				DB::query($sql);
				DB::transaction_commit();
				$personal_tokens[$member->id][$issue->id] = $token;
				$all_tokens[$issue->id][]                 = $token;
			}

			$issue->state = "voting";
			$issue->update(["state"], 'voting_started=now()');

		}

		// notification mails
		$subject = sprintf(_("Voting started in period %d"), $this->id);
		$body_top = _("Group").": ".$this->ngroup()->name."\n\n"
			._("Online voting has started on the following proposals").":\n";
		$body_lists = "\n"._("Voting end").": ".datetimeformat($this->counting)
			."\n\n===== "._("Lists of all vote tokens")." =====\n";
		$issues_blocks = array();
		foreach ( $issues as $issue ) {
			$body_lists .= "\n"
				._("Issue")." ".$issue->id.":\n"
				.join(", ", $all_tokens[$issue->id])."\n";
			$issues_blocks[$issue->id] = "\n"._("Issue")." ".$issue->id."\n";
			foreach ( $issue->proposals(true) as $proposal ) {
				$issues_blocks[$issue->id] .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n";
			}
		}
		foreach ( $members as $member ) {
			if (!$member->mail) continue;
			$body = $body_top;
			foreach ( $issues as $issue ) {
				$body .= $issues_blocks[$issue->id]
					._("Vote").": ".BASE_URL."vote.php?issue=".$issue->id."\n"
					._("Your vote token").": ".$personal_tokens[$member->id][$issue->id]."\n";
			}
			$body .= $body_lists;
			send_mail($member->mail, $subject, $body, array(), $member->fingerprint);
		}

	}


	/**
	 * start online voting with vvvote
	 *
	 * @param array   $issues
	 * @param array   $members
	 */
	public function start_voting_vvvote(array $issues, array $members) {

		// calculate times
		$time_voting   = strtotime($this->voting);
		$time_counting = strtotime($this->counting);
		$time_until = strtotime($this->vvvote_last_reg); // strtotime("-".VVVOTE_LAST_VOTING_INTERVAL, $time_counting);
		$registration_end = $time_until;
		$delay_until = array();
		while ( $time_until > $time_voting ) {
			$delay_until[] = gmdate("c", $time_until);
			$time_until = strtotime("-".$this->vvvote_vote_delay, $time_until);
		}

		$post = array(
			'electionId' => $this->vvvote_election_id(),
			'electionTitle' => sprintf(_("Voting period %d in group %s"), $this->id, $this->ngroup()->name),
			'auth' => "externalToken",
			'authData' => array(
				'configId' => VVVOTE_CONFIG_ID,
				'RegistrationStartDate' => gmdate("c", $time_voting),
				'RegistrationEndDate'   => gmdate("c", $registration_end),
				'VotingStart'           => gmdate("c", $time_voting),
				'VotingEnd'             => gmdate("c", $time_counting),
				'DelayUntil'            => array_reverse($delay_until)
			),
			'tally' => "configurableTally",
			'questions' => array()
		);

		foreach ($issues as $issue) {
			/** @var $issue Issue */

			$options = array();
			$question_wording = "";
			foreach ($issue->proposals(true) as $proposal) {
				/** @var $proposal Proposal */
				$options[] = array(
					'proponents'  => $proposal->proponents_names(),
					'optionID'    => (int) $proposal->id,
					'optionTitle' => $proposal->title,
					'optionDesc'  => $proposal->content,
					'reasons'     => $proposal->reason
				);
				if ($question_wording) $question_wording .= "\n";
				$question_wording .= "* "._("Proposal")." ".$proposal->id.": ".$proposal->title;
			}

			$scheme = array(
				array(
					'name' => "yesNo",
					'abstention' => true,
					'quorum' => "1+",
					'abstentionAsNo' => false,
					'mode' => "quorum"
				)
			);
			$max_score = max_score(count($options));
			if ($max_score) {
				$scheme[] = array(
					'name' => "score",
					'minScore' => 0,
					'maxScore' => $max_score
				);
			}

			$post['questions'][] = array(
				'questionID' => (int) $issue->id,
				'questionWording' => $question_wording,
				'scheme' => $scheme,
				'findWinner' => array("yesNo", "score"),
				'options' => $options
			);

		}

		$post_json = json_encode($post);

		$all_servers_already_used = true;
		foreach ( split_csa(VVVOTE_SERVERS) as $server ) {

			$result = vvvote_curl_post_json($server."backend/newelection.php", $post_json);
			if ($result === false) return;

			if ( isset($result['cmd']) and $result['cmd'] == "saveElectionUrl" and !empty($result['configUrl']) ) {
				if (!$this->vvvote_configurl) {
					// save configUrl from the frist server
					$this->vvvote_configurl = $result['configUrl'];
					$this->update(['vvvote_configurl']);
				} else {
					// check configUrl from the second and following servers
					if ( strstr($result['configUrl'], "confighash=") != strstr($this->vvvote_configurl, "confighash=") ) {
						trigger_error("Confighash in received and saved configUrl are different", E_USER_WARNING);
						return;
					}
				}
				$all_servers_already_used = false;
				continue;
			}

			// election ID is already used
			if ( isset($result['cmd']) and $result['cmd'] == "error" and isset($result['errorNo']) and $result['errorNo'] == 2120 ) {
				if (!$this->vvvote_configurl) {
					trigger_error("Server says, election ID is already used, but no configUrl has been saved", E_USER_WARNING);
					return;
				}
				continue;
			}

			trigger_error("Fetching configUrl from vvvote server failed", E_USER_WARNING);
			return;

		}

		if ($all_servers_already_used) {
			trigger_error("Election ID is already used by all servers", E_USER_WARNING);
			return;
		}

		foreach ($issues as $issue) {
			/** @var $issue Issue */
			$issue->state = "voting";
			$issue->update(["state"], 'voting_started=now()');
		}

		// notification mails
		$subject = sprintf(_("Voting started in period %d"), $this->id);
		$body_top = _("Group").": ".$this->ngroup()->name."\n\n"
			._("Online voting has started on the following proposals").":\n";
		$body_bottom = "\n"._("Vote").": ".BASE_URL."vote_vvvote.php?period=".$this->id."\n"
			._("Voting end").": ".datetimeformat($this->counting)."\n";
		$issues_blocks = array();
		foreach ( $issues as $issue ) {
			$issues_blocks[$issue->id] = "\n"._("Issue")." ".$issue->id."\n";
			foreach ( $issue->proposals(true) as $proposal ) {
				$issues_blocks[$issue->id] .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n";
			}
		}
		foreach ( $members as $member ) {
			if (!$member->mail) continue;
			$body = $body_top;
			foreach ( $issues as $issue ) {
				$body .= $issues_blocks[$issue->id];
			}
			$body .= $body_bottom;
			send_mail($member->mail, $subject, $body);
		}

	}


	/**
	 * unique string to identify one voting period
	 *
	 * @return string
	 */
	public function vvvote_election_id() {
		return VVVOTE_CONFIG_ID."/".$this->id;
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
		DB::insert_or_update("offlinevoter", $fields_values, $keys);
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
			$sql = "SELECT COUNT(1) FROM offlinevoter
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
		DB::delete("offlinevoter", "member=".intval(Login::$member->id)." AND period=".intval($this->id));
		$this->update_voters_cache();
	}


	/**
	 * count voters for all ballots
	 */
	private function update_voters_cache() {

		$sql = "SELECT id FROM ballot WHERE period=".intval($this->id);
		$result = DB::query($sql);
		while ( $row = DB::fetch_assoc($result) ) {

			$sql = "SELECT COUNT(1) FROM offlinevoter WHERE ballot=".intval($row['id']);
			$count = DB::fetchfield($sql);

			$sql = "UPDATE ballot SET voters=".intval($count)." WHERE id=".intval($row['id']);
			DB::query($sql);

		}

	}


	/**
	 * apply selection of approved ballots
	 */
	public function save_approved_ballots() {
		foreach ( $_POST['approved_id'] as $key => $ballot_id ) {
			$value = !empty($_POST['approved'][$key]);
			$sql = "UPDATE ballot SET approved=".DB::bool_to_sql($value)." WHERE id=".intval($ballot_id);
			DB::query($sql);
		}
	}


	/**
	 * assign all remaining members to their nearest ballots
	 */
	public function assign_members_to_ballots() {

		// get all approved ballots
		$sql_ballot = "SELECT * FROM ballot WHERE period=".intval($this->id)." AND approved=TRUE";
		$result_ballot = DB::query($sql_ballot);
		$ballots = array();
		while ( $ballot = DB::fetch_object($result_ballot, "Ballot") ) {
			$ballots[] = $ballot;
		}

		// If no ballots were approved at all, ballot voting just can not take place.
		if (!$ballots) return;

		// get all ngroups within the ngroup of the period
		$result = DB::query("SELECT * FROM ngroup");
		$ngroups = array();
		while ( $ngroup = DB::fetch_object($result, "Ngroup") ) $ngroups[$ngroup->id] = $ngroup;
		$period_ngroups = Ngroup::parent_sort($ngroups, $ngroups[$this->ngroup]->parent);

		// get all members, who are in the current period not assigned to a ballot yet
		$sql = "SELECT member.* FROM member
			JOIN member_ngroup ON member_ngroup.member = member.id AND member_ngroup.ngroup = ".intval($this->ngroup)."
			LEFT JOIN offlinevoter ON member.id = offlinevoter.member AND offlinevoter.period = ".intval($this->id)."
			WHERE offlinevoter.member IS NULL";
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
			/** @noinspection PhpUndefinedMethodInspection */
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
			case "vvvote_last_reg":
				if (strtotime($this->vvvote_last_reg) <= time()) {
					?> class="over"<?
				} else {
					?> class="current"<?
				}
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
	public function dbtableadmin_edit_timestamp($colname, $default, /** @noinspection PhpUnusedParameterInspection */ $id, $disabled, array $column) {
		if ($default) {
			// adjust time format if it is a valid time
			$time = strtotime($default);
			if ($time) $default = date(DATETIMEYEAR_INPUT_FORMAT, $time);
		}
		$attributes = array('size="30"');
		if (!empty($column['required'])) {
			$attributes[] = 'required';
		}
		input_datetime($colname, $default, $disabled, join(" ", $attributes));
		?> <?=sprintf(_("date and time, format e.g. %s"), date(DATETIMEYEAR_FORMAT, 2117003400));
	}

	public function dbtableadmin_edit_interval($colname, $default, /** @noinspection PhpUnusedParameterInspection */ $id, $disabled, array $column) {
	    input_text($colname, $default);
		echo _('Examples: "1 day", "10 minutes" or "3 hours"');
	}

}
