<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Member extends Relation {

	public $auid;
	public $username;

	public $participant;
	public $activated;

	protected $boolean_fields = array("participant");
	protected $create_fields = array("auid", "username");
	protected $update_fields = array("username");


	/**
	 *
	 * @param unknown $username
	 */
	function set_unique_username($username) {

		$this->username = $username;

		$suffix = 0;
		do {
			$sql = "SELECT * FROM members WHERE username=".DB::m($this->username);
			$result = DB::query($sql);
			if ( $exists = pg_num_rows($result) ) {
				$this->username = $username . ++$suffix;
			}
		} while ($exists);

		if ($this->username != $username) {
			notice("The username is already used by someone else, so we added a number to it.");
		}

	}


	/**
	 *
	 * @param unknown $username
	 * @return unknown
	 */
	public function username() {
		return self::username_static($this->username);
	}


	/**
	 *
	 * @param unknown $username
	 * @return unknown
	 */
	public static function username_static($username) {
		if ($username) return $username;
		return _("anonymous");
	}


	/**
	 * activate general participation (distinct from area participation)
	 */
	public function activate_participation() {
		$this->participant = true;
		$this->update(array("participant"), "activated=now()");
	}


	/**
	 * deactivate general participation
	 */
	public function deactivate_participation() {
		$this->participant = false;
		$this->update(array("participant"));
	}


	/**
	 * hide help on a page
	 *
	 * @param string  $bn
	 */
	public function hide_help($bn) {
		$pages = explode_no_empty(",", $this->hide_help);
		$pages[] = $bn;
		$pages = array_unique($pages);
		$this->hide_help = join(",", $pages);
		$this->update(array("hide_help"));
	}


	/**
	 * hide help on a page
	 *
	 * @param string  $bn
	 */
	public function show_help($bn) {
		$pages = explode_no_empty(",", $this->hide_help);
		foreach ( $pages as $key => $page ) {
			if ($page==$bn) unset($pages[$key]);
		}
		$this->hide_help = join(",", $pages);
		$this->update(array("hide_help"));
	}


}
