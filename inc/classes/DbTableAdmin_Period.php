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


}
