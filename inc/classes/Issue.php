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

	protected $boolean_fields = array("secret_reached");
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
		while ( $row = pg_fetch_assoc($result) ) {
			$proposals[] = new Proposal($row);
		}
		return $proposals;
	}


	/**
	 *
	 */
	function demand_secret() {
		$sql = "INSERT INTO offline_demanders (issue, member) VALUES (".intval($this->id).", ".intval(Login::$member->id).")";
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
	 *
	 * @return unknown
	 */
	public function show_offline_demanders() {
		$demanded_by_member = false;
		$sql = "SELECT member FROM offline_demanders WHERE issue=".intval($this->id);
		$result = DB::query($sql);
		resetfirst();
		while ( $row = pg_fetch_assoc($result) ) {
			$member = new Member($row['member']);
			if (Login::$member and $member->id==Login::$member->id) $demanded_by_member = true;
			if (!first()) echo ", ";
			echo $member->username();
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
	 *
	 */
	public static function display_proposals_th() {
?>
	<tr>
		<th width="60%"><?=_("Proposals")?></th>
		<th width="10%"><?=_("State")?></th>
		<th width="10%"><?=_("Voting period")?></th>
		<th width="10%"><?=_("Voting type")?></th>
		<th width="10%"><?=_("Result")?></th>
	</tr>
<?
	}


	/**
	 *
	 * @param unknown $selected_proposal (optional)
	 */
	function display_proposals($selected_proposal=0) {

		$sql = "SELECT proposals.*
			FROM proposals
			WHERE issue=".intval($this->id)."
			ORDER BY proposals.id";
		$result = DB::query($sql);
		$num_rows = pg_num_rows($result);
		$first = true;
		while ($row = pg_fetch_assoc($result)) {

			$proposal = new Proposal($row);
			$proposal->set_issue($this);

			$link = "proposal.php?id=".$proposal->id;

?>
		<tr class="proposal">
			<td class="proposal_link<?
			if ($selected_proposal==$proposal->id) { ?>_active<? }
			?>" onClick="location.href='<?=$link?>'"><?=_("Proposal")?> <?=$proposal->id?>: <a href="<?=$link?>"><?=h($proposal->title)?></a></td>
<?
			if ($this->state=="admission") {
?>
			<td align="center"><?=state_name($this->state, $proposal->state);

				if ($proposal->state=="submitted") {
					$proposal->bargraph_quorum();
				}

				?></td>
<?
			} else {
				if ($first) {
?>
			<td rowspan="<?=$num_rows?>" align="center"><?=state_name($this->state, $proposal->state);

					switch ($this->state) {
					case "debate":
						?><br><?=_("until")?> <?=datetimeformat($this->period()->preparation);
						break;
					case "preparation":
						?><br><?=_("until")?> <?=datetimeformat($this->period()->voting);
						break;
					case "voting":
						?><br><?=_("until")?> <?=datetimeformat($this->period()->counting);
						break;
					case "finished":
						?><br><?=_("until")?> <?=datetimeformat($this->clear);
						break;
					}

					?></td>
<?
				}
			}
			if ($first) {
?>
			<td rowspan="<?=$num_rows?>" align="center"><?
				if ( !Login::$admin or !$this->display_edit_state() ) {
					echo $this->period;
				}
				?></td>
			<td rowspan="<?=$num_rows?>" align="center"><?

				if ($this->secret_reached) {
					echo _("Secret");
				} elseif ($this->state=="debate" or $proposal->state=="admitted" or $proposal->state=="submitted") {
					$this->bargraph_secret();
				} else {
					echo _("Online");
				}

				?></td>
			<td rowspan="<?=$num_rows?>"><?
				if ($this->vote!==null) {
					// voting results
					echo $this->vote;
				}
				?>	</td>
<?
			}
?>
		</tr>
	<?

			$first = false;
		}

	}


	/**
	 *
	 */
	public function bargraph_secret() {
		$required = $this->secret_required();
		bargraph(
			$this->secret_demanders,
			$required,
			strtr( _("%value% of currently required %required% (%percent% of %population%)"), array('%value%'=>$this->secret_demanders, '%required%'=>$required, '%percent%'=>numden2percent($this->secret_level()), '%population%'=>$this->area()->population()) ),
			"#FF0000"
		);
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
			$result = DB::query($sql);
			if ( !DB::num_rows($result) ) return false;
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
				while ( $row_period = pg_fetch_assoc($result_period) ) {
					$period = new Period($row_period);
					$options_all[$period->id] = $period->id.": ".$period->current_period();
					if ($row_period['debate_not_started']=="t") {
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
?>
<form action="<?=URI::strip(array('edit_period'))?>" method="POST">
<?
			input_select("period", $options, $this->period);
			input_hidden("issue", $this->id);
			input_hidden("action", "select_period");
?>
<input type="submit" value="<?=_("apply")?>">
</form>
<?
		} else {
			?><?=$this->period?> <a href="<?=URI::append(array('edit_period'=>$this->id))?>"><?=_("edit")?></a><?
		}

		return true;
	}


}
