<?
/**
 * Issue
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Issue extends Relation {

	public $period;
	public $area;
	public $vote;
	public $ballot_voting_demanders;
	public $ballot_voting_reached;
	public $debate_started;
	public $preparation_started;
	public $voting_started;
	public $counting_started;
	public $clear;
	public $cleared;
	public $state;

	public $ballot_voting_demanded_by_member;

	private $area_obj;
	private $period_obj;

	protected $boolean_fields = array("ballot_voting_reached", "ballot_voting_demanded_by_member");
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

		return DB::update("issues", "id=".intval($this->id), $fields_values, $extra);
	}


	/**
	 * counting of votes
	 */
	public function counting() {

		$sql = "SELECT vote FROM vote WHERE issue=".intval($this->id);



		// save result per issue
		foreach ( $result->questions as $question ) {

			$issue = new Issue($question->questionID);
			if (!$issue) {
				trigger_error("Issue not found", E_USER_WARNING);
				continue;
			}

			// save result for each proposal
			foreach ( $question->results as $result ) {
				$proposal = new Proposal($result->option);
				if ($proposal->issue != $issue->id) {
					trigger_error("Proposal in result is not part of issue", E_USER_WARNING);
					continue;
				}
				$proposal->rank       = $result->rank;
				$proposal->yes        = $result->yes;
				$proposal->no         = $result->no;
				$proposal->abstention = $result->abstention;
				$proposal->points     = $result->points;
				$proposal->accepted   = $result->accepted;
				$proposal->update(array('rank', 'yes', 'no', 'abstention', 'points', 'accepted'));
			}

			// save the downloaded voting result and set date for clearing
			$issue->vote = json_encode($question);
			$issue->state = "finished";
			$issue->update(array("vote", "state"), "clear = current_date + interval ".DB::esc(CLEAR_INTERVAL));

		}

		//}

	}


	/**
	 * get all proposals in this issue
	 *
	 * @param boolean $open (optional) get only open proposals
	 * @return array
	 */
	public function proposals($open=false) {
		$sql = "SELECT * FROM proposals WHERE issue=".intval($this->id);
		if ($open) $sql .= " AND state IN ('draft', 'submitted', 'admitted')";
		$result = DB::query($sql);
		$proposals = array();
		while ( $proposal = DB::fetch_object($result, "Proposal") ) {
			$proposals[] = $proposal;
		}
		return $proposals;
	}


	/**
	 * look if the logged in member demands ballot voting
	 */
	public function read_ballot_voting_demanded_by_member() {
		$sql = "SELECT * FROM ballot_voting_demanders WHERE issue=".intval($this->id)." AND member=".intval(Login::$member->id);
		$result = DB::query($sql);
		$this->ballot_voting_demanded_by_member = ( DB::num_rows($result) == true );
	}


	/**
	 * human friendly state names
	 *
	 * @return string
	 */
	public function state_name() {
		static $states;
		if (!$states) $states = array(
				'admission'   => _("Admission"), // Usually proposal states are shown instead.
				'debate'      => _("Debate"),
				'preparation' => _("Preparation"),
				'voting'      => _("Voting"),
				'counting'    => _("Counting"),
				'finished'    => _("Finished"),
				'cleared'     => _("Finished and cleared"),
				'cancelled'   => _("Cancelled") // when all proposals are 'cancelled', 'revoked' or 'done'
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
			return sprintf(
				_("until %s"),
				'<span class="datetime">'.datetimeformat_smart($this->period()->voting).'</span>'
			);
		case "voting":
			return sprintf(
				_("until %s"),
				'<span class="datetime">'.datetimeformat_smart($this->period()->counting).'</span>'
			);
		case "finished":
			return sprintf(
				_("will be cleared on %s"),
				dateformat_smart($this->clear)
			);
		}
	}


	/**
	 * look if we are in the phase of voting type determination
	 *
	 * @param boolean $submitted
	 * @return boolean
	 */
	public function voting_type_determination($submitted=null) {

		if ($this->ballot_voting_reached) return false;

		if ($this->state=="debate") return true;

		if ($this->state!="admission") return false;

		if ($submitted!==null) return $submitted==true;

		// look if there is at least one already submitted proposal
		$sql = "SELECT COUNT(1) FROM proposals	WHERE issue=".intval($this->id)." AND state!='draft'";
		return DB::fetchfield($sql) == true;
	}


	/**
	 *
	 * @param boolean $anonymous
	 * @return boolean
	 */
	function demand_ballot_voting($anonymous=false) {
		if (!$this->voting_type_determination()) {
			warning("Demand for ballot voting can not be added, because the proposal is not in admission, admitted or debate phase!");
			return false;
		}
		$sql = "INSERT INTO ballot_voting_demanders (issue, member, anonymous)
			VALUES (".intval($this->id).", ".intval(Login::$member->id).", ".DB::bool_to_sql($anonymous).")";
		DB::query($sql);
		$this->update_ballot_voting_cache();
	}


	/**
	 *
	 * @return boolean
	 */
	function revoke_demand_for_ballot_voting() {
		if (!$this->voting_type_determination()) {
			warning("Demand for ballot voting can not be removed, because the proposal is not in admission, admitted or debate phase!");
			return false;
		}
		$sql = "DELETE FROM ballot_voting_demanders WHERE issue=".intval($this->id)." AND member=".intval(Login::$member->id);
		DB::query($sql);
		$this->update_ballot_voting_cache();
	}


	/**
	 *
	 */
	function update_ballot_voting_cache() {

		$sql = "SELECT COUNT(1) FROM ballot_voting_demanders WHERE issue=".intval($this->id);
		$count = DB::fetchfield($sql);

		if ($count >= $this->quorum_ballot_voting_required()) {
			$sql = "UPDATE issues SET ballot_voting_demanders=".intval($count).", ballot_voting_reached=TRUE WHERE id=".intval($this->id);
			DB::query($sql);
		} else {
			$sql = "UPDATE issues SET ballot_voting_demanders=".intval($count)." WHERE id=".intval($this->id);
			DB::query($sql);
		}

	}


	/**
	 * quorum for ballot voting
	 *
	 * @return array
	 */
	function quorum_ballot_voting_level() {
		return array(QUORUM_BALLOT_VOTING_NUM, QUORUM_BALLOT_VOTING_DEN);
	}


	/**
	 * number of required ballot voting demanders
	 *
	 * @return integer
	 */
	function quorum_ballot_voting_required() {
		list($num, $den) = $this->quorum_ballot_voting_level();
		$area = new Area($this->area);
		return ceil($area->population() * $num / $den);
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
			if ($proposal->id==$admitted_proposal->id) continue;
			if ($proposal->supporters >= $proposal->quorum_required()) {
				$proposal->quorum_reached = true;
				$proposal->state = "admitted";
				$proposal->update(array("quorum_reached", "state"), "admitted=now()");
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
		$sql = "SELECT id FROM periods
			WHERE ngroup=".intval($this->period()->ngroup)."
				AND debate > now()
				AND online_voting=TRUE
			ORDER BY debate
			LIMIT 1";
		$this->period = DB::fetchfield($sql);
		if ($this->period) {
			$this->update(array("period"));
		} else {
			// TODO Error
		}

		*/

	}


	/**
	 *
	 * @param boolean $show_results (optional) display the result column
	 */
	public static function display_proposals_th($show_results=false) {
?>
	<tr>
		<th class="proposal"><?=_("Proposal")?></th>
<? if (BN=="vote.php") { ?>
		<th class="vote"><?=_("Vote")?></th>
<? } else { ?>
		<th class="support"><?=_("Support")?></th>
<? if ($show_results) { ?>
		<th class="result"><?=_("Result")?></th>
<? } ?>
		<th class="state"><?=_("State")?></th>
		<th class="period"><?=_("Period")?></th>
		<th class="voting_type"><?=_("Voting type")?></th>
<? } ?>
	</tr>
<?
	}


	/**
	 * proposals to display in the list
	 *
	 * @param unknown $admitted (optional)
	 * @return array
	 */
	public function proposals_list($admitted=false) {

		if (Login::$member) {
			$sql = "SELECT proposals.*, supporters.member AS supported_by_member FROM proposals
					LEFT JOIN supporters ON proposals.id = supporters.proposal AND supporters.member = ".intval(Login::$member->id);
		} else {
			$sql = "SELECT * FROM proposals";
		}
		$sql .= " WHERE issue=".intval($this->id);
		if ($admitted) $sql .= " AND state='admitted'";
		$sql .= " ORDER BY state DESC, rank, id";
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
	 */
	function display_proposals(array $proposals, $submitted=false, $period_rowspan=0, $show_results=false, $selected_proposal=0) {

		$first = true;
		$first_admitted = true;
		$num_rows = count($proposals);
		foreach ( $proposals as $proposal ) {

			$link = "proposal.php?id=".$proposal->id;

?>
	<tr class="proposal">
		<td class="proposal_link<?
			if ($selected_proposal==$proposal->id) { ?>_active<? }
			switch ($proposal->state) {
			case "revoked":
				?> revoked<?
				break;
			case "cancelled":
				?> cancelled<?
				break;
			case "done":
				?> done<?
				break;
			}
			?>" onClick="location.href='<?=$link?>'"><?=_("Proposal")?> <?=$proposal->id?>: <a href="<?=$link?>"><?=h($proposal->title)?></a></td>
<?

			// column "vote"
			if (BN=="vote.php") {
?>
		<td class="nowrap">
			<input type="radio" name="vote[<?=$proposal->id?>][acceptance]" value="1"><?=_("Yes")?>
			<input type="radio" name="vote[<?=$proposal->id?>][acceptance]" value="0"><?=_("No")?>
			<input type="radio" name="vote[<?=$proposal->id?>][acceptance]" value="-1" checked><?=_("Abstention")?>
			<? if ($num_rows > 1) {
					if ($num_rows > 3) $max_score = 9; else $max_score = 3;
					for ( $score = 0; $score <= $max_score; $score++ ) {
?>
						<input type="radio" name="vote[<?=$proposal->id?>][score]" value="<?=$score?>"<? if ($score==0) { ?> checked<? } ?>><?=$score?>
						<?
					}
				} ?>
		</td>
<?
			} else {

				// column "support"
?>
		<td><?
				$proposal->bargraph_quorum($proposal->supported_by_member);
				?></td>
<?

				// column "voting results"
				if ($show_results) {
					if ($this->state != 'finished' and $this->state != 'cleared') {
?>
		<td></td>
<?
					} else {
?>
		<td class="result" onClick="location.href='vote_result.php?issue=<?=$this->id?>'"><?
						if ($first) {
							// get number of options and highest number of points
							$options_count = 0;
							$points_max = 0;
							foreach ( $proposals as $proposal_vote ) {
								if ($proposal_vote->rank === null) continue; // skip cancelled proposals
								$points_max = max($points_max, $proposal_vote->points);
								$options_count++;
							}
						}
						if ( $proposal->rank !== null ) { // skip cancelled proposals
							$proposal->bargraph_acceptance($proposal->yes, $proposal->no, $proposal->abstention, $proposal->accepted);
							if ( $options_count > 1 ) {
								$proposal->bargraph_score($proposal->rank, $proposal->points, $points_max);
							}
						}
						?></td>
<?
					}
				}

				// column "state"
				if ($this->state=="admission" or $this->state=="cancelled") {
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
							}
							?></td>
<?
							$first_admitted = false;
						}
					} else {
						// submitted, cancelled, revoked, done
?>
		<td class="center"><?=$proposal->state_name()?></td>
<?
					}
				} else {
					// issue states
					if ($first) {
?>
		<td rowspan="<?=$num_rows?>" class="center"><?
						if ($this->state=="voting") {
							?><a href="vote.php?issue=<?=$this->id?>"><?=_("Voting")?></a><?
						} else {
							echo $this->state_name();
						}
						if ( $state_info = $this->state_info() ) {
							?><br><span class="stateinfo"><?=$state_info?></span><?
						}
						?></td>
<?
					}
				}

				// columns "period" and "voting type"
				if ($first) {
					if (Login::$admin) {
?>
		<td rowspan="<?=$num_rows?>" class="center nowrap"><?
						if ( !$this->display_edit_state() ) {
							?><a href="periods.php?ngroup=<?=$this->area()->ngroup?>&amp;hl=<?=$this->period?>"><?=$this->period?></a><?
						}
						?></td>
<?
					} elseif ($period_rowspan) {
?>
		<td rowspan="<?=$period_rowspan?>" class="center"><a href="periods.php?ngroup=<?=$this->area()->ngroup?>&amp;hl=<?=$this->period?>"><?=$this->period?></a></td>
<?
					}
?>
		<td rowspan="<?=$num_rows?>" class="center"<?

					if ($this->voting_type_determination($submitted)) {
						?> title="<?
						$entitled = ( Login::$member and Login::$member->entitled($this->area()->ngroup) );
						if ($this->ballot_voting_demanded_by_member) {
							echo _("You demand ballot voting.");
						} elseif ($entitled) {
							echo _("You can demand ballot voting.");
						} else {
							echo _("Members can demand ballot voting.");
						}
						?>">
<img src="img/votingtype20.png" width="75" height="20" <?alt(_("determination if online or ballot voting"))?> class="vmiddle">
<?
						if (Login::$member) {
							if ($this->ballot_voting_demanded_by_member) {
								?>&#10003;<?
							}
							if ($selected_proposal and $entitled) {
								form(URI::same());
								if ($this->ballot_voting_demanded_by_member) {
									echo _("You demand ballot voting.")?>
<input type="hidden" name="action" value="revoke_demand_for_ballot_voting">
<input type="submit" value="<?=_("Revoke")?>">
<?
								} else {
?>
<input type="hidden" name="action" value="demand_ballot_voting">
<input type="submit" value="<?=_("Demand ballot voting")?>">
<?
								}
								form_end();
							}
						}
					} elseif ($this->ballot_voting_reached) {
						?> title="<?=_("ballot voting")?>"><img src="img/ballot30.png" width="37" height="30" <?alt(_("ballot voting"))?> class="vmiddle"><?
					} elseif ($this->state!="admission") {
						?> title="<?=_("online voting")?>"><img src="img/online30.png" width="24" height="30" <?alt(_("online voting"))?> class="vmiddle"><?
					} else {
						?>><?
					}

					?></td>
<?
				}

			}

?>
	</tr>
<?

			$first = false;
		}

	}


	/**
	 * check if it's allowed to add an alternative proposal
	 *
	 * @return boolean
	 */
	public function allowed_add_alternative_proposal() {
		if ($this->state=="admission") return true;
		return false;
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
	 * @return array list of options for drop down menu or false
	 */
	public function available_periods() {

		// find out if the state may be changed
		switch ($this->state) {
		case "admission":
			// At least one proposal has to be admitted.
			$sql = "SELECT COUNT(1) FROM proposals WHERE issue=".intval($this->id)." AND state='admitted'::proposal_state";
			if ( !DB::fetchfield($sql) ) return false;
		case "debate":
		case "preparation":

			// Issues, on which the voting already started, may not be postponed anymore.
			// Issues, which were not started debating, may only be moved into periods where the debate has not yet started. Otherwise the debate time would be shorter than for other issues.

			// read options once from the database
			static $options_all = false;
			static $options_admission = false;
			if ($options_all===false) {
				$sql_period = "SELECT *, debate > now() AS debate_not_started FROM periods
					WHERE ngroup=".intval($this->period()->ngroup)."
						AND voting > now()
					ORDER BY id";
				$result_period = DB::query($sql_period);
				$options_all = array();
				$options_admission = array();
				while ( $period = DB::fetch_object($result_period, "Period") ) {
					DB::to_bool($period->debate_not_started);
					$options_all[$period->id] = $period->id.": ".$period->current_phase();
					if ($period->debate_not_started) {
						$options_admission[$period->id] = $options_all[$period->id];
					}
				}
			}

			if ($this->state=="admission") {
				return $options_admission;
			} else {
				return $options_all;
			}

		}

		return false;
	}


	/**
	 * admins select a voting period
	 *
	 * @return boolean true if the period may be changed
	 */
	private function display_edit_state() {

		$options =& $this->available_periods();
		if (!$options) return false;

		if (@$_GET['edit_period']==$this->id) {
			form(URI::strip(array('edit_period')));
			input_select("period", $options, $this->period);
			?><br><?
			input_hidden("issue", $this->id);
			input_hidden("action", "select_period");
?>
<input type="submit" value="<?=_("apply")?>">
<?
			form_end();
		} else {
			if ($this->period) {
				?><a href="periods.php?ngroup=<?=$this->area()->ngroup?>&amp;hl=<?=$this->period?>"><?=$this->period?></a><?
			}
			?><a href="<?=URI::append(array('edit_period'=>$this->id))?>" class="iconlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>" title="<?=_("select voting period")?>"></a><?
		}

		return true;
	}


}
