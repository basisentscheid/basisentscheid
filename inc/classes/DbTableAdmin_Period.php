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

		if ($debate > $preparation) {
			warning(_("The debate must start before the preparation!"));
			return false;
		}
		if ($preparation > $voting) {
			warning(_("The preparation must start before the voting!"));
			return false;
		}
		if ($voting > $counting) {
			warning(_("The voting must start before the counting!"));
			return false;
		}

		return true;
	}


}
