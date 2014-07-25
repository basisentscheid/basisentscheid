<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Admin extends Relation {

	public $username;
	public $password;

	protected $create_fields = array("username", "password");


}
