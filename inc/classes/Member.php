<?
/**
 * Member
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
	 * set the username
	 *
	 * @param string  $username
	 */
	function set_unique_username($username) {

		$this->username = $username;

		$suffix = 0;
		do {
			$sql = "SELECT * FROM members WHERE username=".DB::esc($this->username);
			$result = DB::query($sql);
			if ( $exists = DB::num_rows($result) ) {
				$this->username = $username . ++$suffix;
			}
		} while ($exists);

		if ($this->username != $username) {
			notice(_("The username is already used by someone else, so we added a number to it."));
		}

	}


	/**
	 * get the username or "anonymous"
	 *
	 * @param string  $username
	 * @return string
	 */
	public function username() {
		return self::username_static($this->username);
	}


	/**
	 * get the username or "anonymous"
	 *
	 * @param string  $username
	 * @return string
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
	 * @param string  $basename
	 */
	public function hide_help($basename) {
		$pages = explode_no_empty(",", $this->hide_help);
		$pages[] = $basename;
		$pages = array_unique($pages);
		$this->hide_help = join(",", $pages);
		$this->update(array("hide_help"));
	}


	/**
	 * hide help on a page
	 *
	 * @param string  $basename
	 */
	public function show_help($basename) {
		$pages = explode_no_empty(",", $this->hide_help);
		foreach ( $pages as $key => $page ) {
			if ($page==$basename) unset($pages[$key]);
		}
		$this->hide_help = join(",", $pages);
		$this->update(array("hide_help"));
	}


}
