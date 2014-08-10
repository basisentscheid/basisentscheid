<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Draft extends Relation {

	public $title;
	public $content;
	public $reason;

	protected $create_fields = array("proposal", "title", "content", "reason", "author");

}
