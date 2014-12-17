<?
/**
 * Draft
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Draft extends Relation {

	// database table
	public $title;
	public $content;
	public $reason;
	public $proposal;
	public $author;
	public $created;

	protected $create_fields = array("proposal", "title", "content", "reason", "author");

}
