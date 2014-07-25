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
		<th width="10%"><?=_("Ergebnis")?></th>
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
				if (Login::$admin) {
					if (@$_GET['edit_period']==$this->id) {
?>
<form action="<?=URI::strip(array('edit_period'))?>" method="POST">
<?
						// TODO: restrict available periods
						$sql_periods = "SELECT * FROM periods";
						$result_periods = DB::query($sql_periods);
						$options = array();
						while ( $row_periods = pg_fetch_assoc($result_periods) ) {
							$options[$row_periods['id']] = $row_periods['id'];
						}
						input_select("period", $options, $this->period);
						input_hidden("issue", $this->id);
						input_hidden("action", "select_period");
?>
<input type="submit">
</form>
<?
					} else {
						?><?=$this->period?> <a href="<?=URI::append(array('edit_period'=>$this->id))?>"><?=_("edit")?></a><?
					}
				} else {
					echo $this->period;
				}
				?></td>
			<td rowspan="<?=$num_rows?>" align="center"><?

				if ($this->secret_reached) {
					echo _("Secret");
				} elseif ($this->state=="debate" or $proposal->state=="admitted" or $proposal=="submitted") {
					$this->bargraph_secret();
				} else {
					echo _("Online");
				}

				?></td>
<?
			}
?>
			<td>
<?
			switch ($this->state) {
			case "finished":
			case "cleared":
				// voting results
			}
?>
			</td>
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


}
