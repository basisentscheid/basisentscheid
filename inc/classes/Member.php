<?
/**
 * Member
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Member extends Relation {

	public $invite;
	public $invite_expiry;
	public $created;
	public $activated;
	public $username;
	public $password;
	public $public_id = "";
	public $profile   = "";
	public $entitled;
	public $mail;
	public $mail_unconfirmed;
	public $mail_code;
	public $mail_code_expiry;
	public $mail_lock_expiry;
	public $hide_help;

	protected $boolean_fields = array("entitled");
	protected $update_fields = array("username");

	private $ngroups;


	/**
	 * create a new member
	 *
	 * @return boolean
	 */
	public function create() {
		$fields_values = array('invite' => $this->invite, 'entitled' => $this->entitled);
		$extra = array('invite_expiry' => "now() + interval '1 month'");
		return DB::insert("members", $fields_values, $this->id, $extra);
	}


	/**
	 * check, if the member may change anything
	 *
	 * @param integer $ngroup
	 * @return boolean
	 */
	public function entitled($ngroup) {
		if (!$this->entitled) return false;
		if ($this->ngroups===null) {
			$sql = "SELECT ngroup FROM members_ngroups WHERE member=".intval($this->id);
			$this->ngroups = DB::fetchfieldarray($sql);
		}
		return in_array($ngroup, $this->ngroups);
	}


	/**
	 * get lowest member ngroup within the given ngroups
	 *
	 * @param array   $ngroups
	 * @return Ngroup
	 */
	public function lowest_ngroup(array $ngroups) {

		// get member ngroups
		$sql = "SELECT ngroup FROM members_ngroups WHERE member=".intval($this->id);
		$member_ngroups = DB::fetchfieldarray($sql);

		// find lowest in member ngroups
		$lowest_member_ngroup = null;
		foreach ( $ngroups as $ngroup ) {
			if ( in_array($ngroup->id, $member_ngroups) and (!$lowest_member_ngroup or $ngroup->depth > $lowest_member_ngroup->depth) ) {
				$lowest_member_ngroup = $ngroup;
			}
		}

		return $lowest_member_ngroup;
	}


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
	 * name to identify the member for admins and other proponents
	 *
	 * @return string
	 */
	public function identity() {
		if ($this->public_id) return _("Real name").": ".$this->public_id;
		return _("User name").": ".$this->username;
	}


	/**
	 * get the current notification settings
	 *
	 * @return array
	 */
	public function notification_settings() {

		$notify = Notification::$default_settings;

		$sql = "SELECT * FROM notify WHERE member=".intval($this->id);
		$result = DB::query($sql);
		while ( $row = DB::fetch_assoc($result) ) {
			foreach (Notification::$default_settings['all'] as $type => $dummy) DB::to_bool($row[$type]);
			$notify[$row['interest']] = $row;
		}

		return $notify;
	}


	// actions


	/**
	 * save not yet confirmed mail address and send confirmation request
	 *
	 * @param string  $mail
	 */
	public function set_mail($mail) {

		if ( strtotime($this->mail_lock_expiry) > time() ) {
			warning(_("We have sent an email with activation link already in the last hour. Please try again later!"));
			redirect();
		}

		$this->mail_unconfirmed = $mail;

		DB::transaction_start();
		do {
			$this->mail_code = Login::generate_token(16);
			$sql = "SELECT id FROM members WHERE mail_code=".DB::esc($this->mail_code);
		} while ( DB::numrows($sql) );
		$this->update(array('mail_unconfirmed', 'mail_code'), "mail_code_expiry = now() + interval '7 days'");
		DB::transaction_commit();

		$subject = _("Email confirmation request");
		$body = _("Please confirm your email address by clicking the following link:")."\n"
			.BASE_URL."confirm_mail.php?code=".$this->mail_code."\n\n"
			._("If this link does not work, please open the following URL in your web browser:")."\n"
			.BASE_URL."confirm_mail.php\n"
			._("On that page enter the code:")."\n"
			.$this->mail_code;

		if ( send_mail($mail, $subject, $body) ) {
			$this->update(array(), "mail_lock_expiry = now() + interval '1 hour'");
			success(_("Your email address has been saved. A confirmation request has been sent."));
		} else {
			warning(_("Your email address has been saved, but the confirmation request could not be sent. Try again later or contact the system administrator."));
		}

	}


	/**
	 * get pages where to hide help
	 *
	 * @return array
	 */
	public function hide_help() {
		return explode_no_empty(",", $this->hide_help);
	}


	/**
	 * save pages where to hide help
	 *
	 * @param array   $hide
	 */
	public function update_help(array $hide) {
		$this->hide_help = join(",", $hide);
		$this->update(array("hide_help"));
	}


	/**
	 * update member's ngroups
	 *
	 * @param array   $ngroups array of integers
	 */
	public function update_ngroups(array $ngroups) {

		DB::transaction_start();

		$sql = "SELECT ngroup FROM members_ngroups WHERE member=".intval($this->id);
		$existing_ngroups = DB::fetchfieldarray($sql);

		$insert_ngroups = array_diff($ngroups, $existing_ngroups);
		if ($insert_ngroups) {
			$sql = "INSERT INTO members_ngroups (member, ngroup) VALUES ";
			resetfirst();
			foreach ($insert_ngroups as $insert_ngroup) {
				if (!first()) $sql .= ", ";
				$sql .= "(".intval($this->id).", ".intval($insert_ngroup).")";
			}
			DB::query($sql);
		}

		$delete_ngroups = array_diff($existing_ngroups, $ngroups);
		if ($delete_ngroups) {
			$sql = "DELETE FROM members_ngroups
				WHERE member=".intval($this->id)."
					AND ngroup IN (".join(", ", array_map("intval", $delete_ngroups)).")";
			DB::query($sql);
		}

		DB::transaction_commit();

	}


}
