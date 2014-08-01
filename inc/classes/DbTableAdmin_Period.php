<?
/**
 * inc/classes/DbTableAdmin_Period.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class DbTableAdmin_Period extends DbTableAdmin {


	/**
	 * link to ballots page
	 *
	 * @param mixed   $content
	 * @param object  $object
	 * @param array   $column
	 */
	protected function print_ballots($content, $object, $column) {
		if (!$object->secret) return;
		?><a href="ballots.php?period=<?=$object->id?>"><?=_("Ballots")?></a><?
	}


	/**
	 * check input
	 *
	 * @return boolean
	 */
	protected function beforesave() {

		$debate      = strtotime($this->object->debate);
		$preparation = strtotime($this->object->preparation);
		$voting      = strtotime($this->object->voting);
		$counting    = strtotime($this->object->counting);

		if (!$debate) {
			warning(_("The debate time is not valid!"));
			return false;
		}
		if (!$preparation) {
			warning(_("The preparation time is not valid!"));
			return false;
		}
		if (!$debate) {
			warning(_("The voting time is not valid!"));
			return false;
		}
		if (!$counting) {
			warning(_("The counting time is not valid!"));
			return false;
		}

		if ($debate >= $preparation) {
			warning(_("The debate must start before the preparation!"));
			return false;
		}
		if ($preparation >= $voting) {
			warning(_("The preparation must start before the voting!"));
			return false;
		}
		if ($voting >= $counting) {
			warning(_("The voting must start before the counting!"));
			return false;
		}

		$ballot_assignment  = $this->object->ballot_assignment;
		$ballot_preparation = $this->object->ballot_preparation;
		if ($ballot_assignment) {
			$ballot_assignment = strtotime($this->object->ballot_assignment);
			if (!$ballot_assignment) {
				warning(_("The ballot assignment time is not valid!"));
				return false;
			}
		}
		if ($ballot_preparation) {
			$ballot_preparation = strtotime($this->object->ballot_preparation);
			if (!$ballot_preparation) {
				warning(_("The ballot preparation time is not valid!"));
				return false;
			}
		}

		if ($ballot_assignment and $ballot_preparation and $ballot_assignment >= $ballot_preparation) {
			warning(_("The ballot assignment must start before the ballot preparation!"));
			return false;
		}
		if ($ballot_preparation and $ballot_preparation > $counting) {
			warning(_("The ballot preparation must start before the counting!"));
			return false;
		}

		if ($this->object->secret and (!$ballot_assignment or !$ballot_preparation)) {
			warning(_("If secret voting is activated, also the ballot assignment and preparation times have to be entered!"));
			return false;
		}

		if ($this->object->id and !$this->object->secret) {
			$sql = "SELECT id FROM ballots WHERE period=".intval($this->object->id);
			$result = DB::query($sql);
			if (pg_num_rows($result)) {
				warning(_("Since there are already ballot applications you can not deactivate secret voting!"));
				return false;
			}
		}

		return true;
	}


}
