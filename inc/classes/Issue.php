<?
/**
 * inc/classes/Issue.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Issue extends Relation {

	public $period;
	public $area;
	public $secret_demanders;
	public $secret_reached;
	public $state;

	private $area_obj;
	private $period_obj;

	protected $boolean_fields = array("secret_reached", "secret_by_member");
	protected $create_fields = array("area");


	/**
	 *
	 * @return unknown
	 */
	function area() {
		if ($this->area_obj) return $this->area_obj;
		$this->area_obj = new Area($this->area);
		return $this->area_obj;
	}


	/**
	 *
	 * @return unknown
	 */
	function period() {
		if ($this->period_obj) return $this->period_obj;
		$this->period_obj = new Period($this->period);
		return $this->period_obj;
	}


	/**
	 *
	 * @param unknown $fields (optional)
	 * @param unknown $extra  (optional)
	 */
	function update( $fields = array("period", "area", "state"), $extra=false ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}

		DB::update("issues", "id=".intval($this->id), $fields_values, $extra);

	}


	/**
	 *
	 * @return unknown
	 */
	public function proposals() {

		$sql = "SELECT * FROM proposals WHERE issue=".intval($this->id);
		$result = DB::query($sql);
		$proposals = array();
		while ( $proposal = DB::fetch_object($result, "Proposal") ) {
			$proposals[] = $proposal;
		}
		return $proposals;
	}


	/**
	 * look if the logged in member demands secret voting
	 */
	public function read_secret_by_member() {

		$sql = "SELECT * FROM offline_demanders WHERE issue=".intval($this->id)." AND member=".intval(Login::$member->id);
		$result = DB::query($sql);
		$this->secret_by_member = ( pg_num_rows($result) == true );

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
				'preparation' => _("Voting preparation"),
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
			return strtr(
				_("until %datetime%"),
				array('%datetime%' => '<span class="datetime">'.datetimeformat($this->period()->preparation).'</span>')
			);
		case "preparation":
			return strtr(
				_("until %datetime%"),
				array('%datetime%' => '<span class="datetime">'.datetimeformat($this->period()->voting).'</span>')
			);
		case "voting":
			return strtr(
				_("until %datetime%"),
				array('%datetime%' => '<span class="datetime">'.datetimeformat($this->period()->counting).'</span>')
			);
		case "finished":
			return strtr(
				_("will be cleared on %date%"),
				array('%date%' => dateformat($this->clear))
			);
		}
	}


	/**
	 *
	 * @param boolean $anonymous
	 */
	function demand_secret($anonymous=false) {
		$sql = "INSERT INTO offline_demanders (issue, member, anonymous)
			VALUES (".intval($this->id).", ".intval(Login::$member->id).", ".DB::bool2pg($anonymous).")";
		DB::query($sql);
		$this->update_secret_cache();
	}


	/**
	 *
	 */
	function revoke_secret() {
		$sql = "DELETE FROM offline_demanders WHERE issue=".intval($this->id)." AND member=".intval(Login::$member->id);
		DB::query($sql);
		$this->update_secret_cache();
	}


	/**
	 * display a list of members demanding offline voting and find out if the logged in member demands offline voting fot the issue
	 *
	 * @return mixed
	 */
	public function show_offline_demanders() {
		$demanded_by_member = false;
		$sql = "SELECT member, anonymous FROM offline_demanders WHERE issue=".intval($this->id);
		$result = DB::query($sql);
		resetfirst();
		while ( $row = pg_fetch_assoc($result) ) {
			if (!first()) echo ", ";
			$member = new Member($row['member']);
			if (Login::$member and $member->id==Login::$member->id) {
				if ($row['anonymous']==DB::value_true) {
					$demanded_by_member = "anonymous";
					?><span class="self"><?=_("anonymous")?></span><?
				} else {
					$demanded_by_member = true;
					?><span class="self"><?=$member->username()?></span><?
				}
			} else {
				if ($row['anonymous']==DB::value_true) {
					echo _("anonymous");
				} else {
					echo $member->username();
				}
			}
		}
		return $demanded_by_member;
	}


	/**
	 *
	 */
	function update_secret_cache() {

		// 12 weeks are 84 days
		$sql = "SELECT COUNT(1) FROM offline_demanders WHERE issue=".intval($this->id);
		$count = DB::fetchfield($sql);

		if ($count >= $this->secret_required()) {
			$sql = "UPDATE issues SET secret_demanders=".intval($count).", secret_reached=TRUE WHERE id=".intval($this->id);
			DB::query($sql);
		} else {
			$sql = "UPDATE issues SET secret_demanders=".intval($count)." WHERE id=".intval($this->id);
			DB::query($sql);
		}

	}


	/**
	 *
	 * @return unknown
	 */
	function secret_level() {
		return array(QUORUM_SECRET_NUM, QUORUM_SECRET_DEN);
	}


	/**
	 *
	 * @return unknown
	 */
	function secret_required() {

		list($num, $den) = $this->secret_level();

		$area = new Area($this->area);

		return ceil($area->population() * $num / $den);

	}


	/**
	 * save the downloaded voting result and set date for clearing
	 *
	 * @param unknown $result
	 */
	public function save_vote($result) {
		$this->vote = $result;
		$this->state = "finished";
		$this->update(array("vote", "state"), "clear = current_date + ".DB::m(CLEAR_INTERVAL)."::INTERVAL");
	}


	/**
	 *
	 * @param boolean $show_results display the result column
	 */
	public static function display_proposals_th($show_results) {
?>
	<tr>
		<th class="proposal"><?=_("Proposal")?></th>
		<th class="state"><?=_("State")?></th>
		<th class="period"><?=_("Period")?></th>
		<th class="secret"><?=_("Voting type")?></th>
<? if ($show_results) { ?>
		<th class="result"><?=_("Result")?></th>
<? } ?>
	</tr>
<?
	}


	/**
	 * proposals to display in the list
	 *
	 * @return array
	 */
	public function proposals_list() {

		if (Login::$member) {
			$sql = "SELECT proposals.*, supporters.member AS supported_by_member FROM proposals
					LEFT JOIN supporters ON proposals.id = supporters.proposal AND supporters.member = ".intval(Login::$member->id)."
				WHERE issue=".intval($this->id)."
				ORDER BY state DESC, id";
		} else {
			$sql = "SELECT * FROM proposals
				WHERE issue=".intval($this->id)."
				ORDER BY state DESC, id";
		}
		$result = DB::query($sql);
		$proposals = array();
		while ( $proposal = DB::fetch_object($result, "Proposal") ) {
			$proposal->set_issue($this);
			$proposals[] = $proposal;
		}

		return $proposals;
	}


	/**
	 * display table part for all proposals of the issue
	 *
	 * @param array   $proposals         array of objects
	 * @param integer $period_rowspan
	 * @param boolean $show_results      display the result column
	 * @param integer $selected_proposal (optional)
	 */
	function display_proposals($proposals, $period_rowspan, $show_results, $selected_proposal=0) {

		// look if there is at least one already submitted proposal
		$submitted = false;
		foreach ( $proposals as $proposal ) {
			if ($proposal->state!="draft") {
				$submitted = true;
				break;
			}
		}

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
							echo strtr(
								_("Debate starts at %datetime%"),
								array('%datetime%' => '<span class="datetime">'.datetimeformat($this->period()->debate).'</span>')
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
		<td class="center"><?=$proposal->state_name();
					if ($proposal->state=="submitted") {
						?><br><?
						$proposal->bargraph_quorum();
						if ($proposal->supported_by_member) {
							?><br>&#10003;<?
						}
					}
					?></td>
<?
				}
			} else {
				// issue states
				if ($first) {
?>
		<td rowspan="<?=$num_rows?>" class="center"><?=$this->state_name();
					if ( $state_info = $this->state_info() ) {
						?><br><span class="stateinfo"><?=$state_info?></span><?
					}
					?></td>
<?
				}
			}

			// columns "period", "voting type" and "result"
			if ($first) {
				if (Login::$admin) {
?>
		<td rowspan="<?=$num_rows?>" class="center nowrap"><?
					if ( !$this->display_edit_state() ) {
						?><a href="periods.php?hl=<?=$this->period?>"><?=$this->period?></a><?
					}
					?></td>
<?
				} elseif ($period_rowspan) {
?>
		<td rowspan="<?=$period_rowspan?>" class="center"><a href="periods.php?hl=<?=$this->period?>"><?=$this->period?></a></td>
<?
				}
?>
		<td rowspan="<?=$num_rows?>" class="center nowrap"><?

				if ($this->secret_reached) {
					?><img src="img/ballot30.png" width="37" height="30" <?alt(_("Secret"))?>><?
				} elseif (
					($this->state=="admission" and $submitted) or
					$this->state=="debate"
				) {
					?><img src="img/online16.png" width="13" height="16" class="online_small" <?alt(_("Online"))?>><?
					$this->bargraph_secret();
					?><img src="img/ballot16.png" width="20" height="16" class="ballot_small" <?alt(_("Secret"))?>><?
					if ($this->secret_by_member) {
						?><br>&#10003;<?
					}
				} elseif ($this->state!="admission") {
					?><img src="img/online30.png" width="24" height="30" <?alt(_("Online"))?>><?
				}

				?></td>
<?
				if ($show_results) {
?>
		<td rowspan="<?=$num_rows?>"><?
					if ($this->vote!==null) {
						// voting results
						echo $this->vote;
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
	 * display bargraph
	 */
	public function bargraph_secret() {
		$required = $this->secret_required();
		bargraph(
			$this->secret_demanders,
			$required,
			strtr(
				_("%value% of currently required %required% (%percent% of %population%) for secret voting"),
				array(
					'%value%'      => $this->secret_demanders,
					'%required%'   => $required,
					'%percent%'    => numden2percent($this->secret_level()),
					'%population%' => $this->area()->population()
				)
			),
			"#FF0000"
		);
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
				$sql_period = "SELECT *, debate > now() AS debate_not_started FROM periods WHERE voting > now() ORDER BY id";
				$result_period = DB::query($sql_period);
				$options_all = array();
				$options_admission = array();
				while ( $period = DB::fetch_object($result_period, "Period") ) {
					DB::pg2bool($period->debate_not_started);
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
			input_hidden("issue", $this->id);
			input_hidden("action", "select_period");
?>
<input type="submit" value="<?=_("apply")?>">
</form>
<?
		} else {
			if ($this->period) {
				?><a href="periods.php?hl=<?=$this->period?>"><?=$this->period?></a><?
			}
			?><a href="<?=URI::append(array('edit_period'=>$this->id))?>" class="iconlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>" title="<?=_("select voting period")?>"></a><?
		}

		return true;
	}


}
