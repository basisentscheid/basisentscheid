<?

/**
 * Member
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Member extends Relation {

	const profile_length = 2000;
	const fingerprint_length = 100;

	// database table
	public $invite;
	public $invite_expiry;
	public $created;
	public $activated;
	public $eligible;
	public $verified;
	public $realname = "";
	public $username;
	public $password;
	public $mail;
	public $mail_unconfirmed;
	public $mail_code;
	public $mail_code_expiry;
	public $mail_lock_expiry;
	public $fingerprint;
	public $profile  = "";
	public $hide_help;

	public $proponent_name;
	public $proponent_confirmed;

	protected $boolean_fields = array("eligible", "verified");
	protected $update_fields = array("username");

	private $ngroups;


	/**
	 * create a new member
	 *
	 * @return boolean
	 */
	public function create() {
		$fields_values = array(
			'invite' => $this->invite,
			'eligible' => $this->eligible,
			'verified' => $this->verified
		);
		$extra = array('invite_expiry' => "now() + interval '1 month'");
		return DB::insert("member", $fields_values, $this->id, $extra);
	}


	/**
	 * check, if the member may vote
	 *
	 * @param integer $ngroup
	 * @return boolean
	 */
	public function entitled($ngroup) {
		if (!$this->eligible or !$this->verified) return false;
		if ($this->ngroups===null) {
			$sql = "SELECT ngroup FROM member_ngroup WHERE member=".intval($this->id);
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
		$sql = "SELECT ngroup FROM member_ngroup WHERE member=".intval($this->id);
		$member_ngroups = DB::fetchfieldarray($sql);

		// find lowest in member ngroups
		/** @var Ngroup $lowest_member_ngroup */
		$lowest_member_ngroup = null;
		foreach ( $ngroups as $ngroup ) {
			/** @var Ngroup $ngroup */
			if ( in_array($ngroup->id, $member_ngroups) and (!$lowest_member_ngroup or $ngroup->depth > $lowest_member_ngroup->depth) ) {
				$lowest_member_ngroup = $ngroup;
			}
		}

		return $lowest_member_ngroup;
	}


	/**
	 * get the username
	 *
	 * @return string
	 */
	public function username() {
		return $this->username;
	}


	/**
	 * name to identify the member for admins and other proponents
	 *
	 * @return string
	 */
	public function identity() {
		if ($this->realname) return _("Real name").": ".$this->realname;
		return _("User name").": ".$this->username;
	}


	/**
	 * username with link to profile
	 *
	 * @return string
	 */
	public function link() {
		if ($this->profile) {
			return '<a href="member.php?id='.$this->id.'">'.$this->username().'</a>';
		} else {
			return $this->username();
		}
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
	 * save new password
	 *
	 * @param string  $password
	 */
	public function set_new_password($password) {

		$this->password = crypt($password);

		// notification
		if ($this->mail) {
			$subject = _("Change of your password");
			$body = _("Someone, probably you, changed your password.")."\n\n"
				.sprintf(_("If this was not you, somebody else got access to your account. In this case please contact %s as soon as possible!"), MAIL_SUPPORT);
			send_mail($this->mail, $subject, $body);
		}

	}


	/**
	 * save not yet confirmed mail address and send confirmation request
	 *
	 * @param string  $mail
	 */
	public function set_mail($mail) {

		if ( strtotime($this->mail_lock_expiry) > time() ) {
			warning(_("We have sent an email with a confirmation code already in the last hour. Please try again later!"));
			redirect();
		}

		$this->mail_unconfirmed = $mail;

		DB::transaction_start();
		do {
			$this->mail_code = Login::generate_token(16);
			$sql = "SELECT id FROM member WHERE mail_code=".DB::esc($this->mail_code);
		} while ( DB::numrows($sql) );
		// The member has 7 days to confirm the email address.
		$this->update(['mail_unconfirmed', 'mail_code'], "mail_code_expiry = now() + interval '7 days'");
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
			success(_("Your email address has been saved. An email with a confirmation code has been sent."));
		} else {
			warning(sprintf(_("Your email address has been saved, but the email with the confirmation code could not be sent. Try again later or contact %s.")), MAIL_SUPPORT);
		}

		// notification to old mail address
		if ($this->mail) {
			$subject = _("Change of your email address");
			$body = _("Someone, probably you, changed your email address to:")."\n"
				.$this->mail_unconfirmed."\n\n"
				._("If this was not you, somebody else got access to your account. In this case please log in as soon as possible and change your password:")."\n"
				.BASE_URL."settings.php\n"
				.sprintf(_("Then try to set the email address back to your one and contact %s!"), MAIL_SUPPORT);
			send_mail($this->mail, $subject, $body);
		}

	}


	/**
	 * save profile
	 *
	 * @param string  $profile
	 */
	public function set_profile($profile) {

		$this->profile = $profile;
		if (mb_strlen($this->profile) > self::profile_length) {
			$this->profile = limitstr($this->profile, self::profile_length);
			warning(sprintf(_("The profile has been truncated to the maximum allowed length of %d characters!"), self::profile_length));
		}

	}


	/**
	 * save fingerprint
	 *
	 * @param string  $fingerprint
	 */
	public function set_fingerprint($fingerprint) {
		$this->fingerprint = limitstr($fingerprint, self::fingerprint_length);
	}


	/**
	 * fingerprint without spaces
	 *
	 * @return string
	 */
	public function fingerprint() {
		return str_replace(" ", "", $this->fingerprint);
	}


	/**
	 * check if the matching key is available
	 */
	public function display_fingerprint_info() {

		if (!Login::$member->fingerprint) return;

		$gnupg = new_gnupg();

		$info = $gnupg->keyinfo($this->fingerprint());
		//var_dump($info);

		if ( !gnupg_keyinfo_matches_email($info, $this->mail) ) {
			?><span class="problem"><?=_("No key matching fingerprint and email address was found.")?></span><?
			return;
		}

		if ($info[0]["disabled"]) {
			?><span class="problem"><?=_("This key is disabled.")?></span><?
			return;
		}
		if ($info[0]["expired"]) {
			?><span class="problem"><?=_("This key is expired.")?></span><?
			return;
		}
		if ($info[0]["revoked"]) {
			?><span class="problem"><?=_("This key is revoked.")?></span><?
			return;
		}
		if ($info[0]["is_secret"]) {
			?><span class="problem"><?=_("This key is a secret key.")?></span><?
			return;
		}
		if (!$info[0]["can_encrypt"]) {
			?><span class="problem"><?=_("This key can not encrypt.")?></span><?
			return;
		}

		?><span class="fine" title="<?=_("The key was found and is usable.")?>">&#10003;</span><?

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
	 * display list of groups
	 */
	public function display_ngroups() {
		$sql = "SELECT name FROM member_ngroup
			JOIN ngroup ON ngroup.id = member_ngroup.ngroup
			WHERE member=".intval($this->id);
		echo join(", ", DB::fetchfieldarray($sql));
	}


	/**
	 * update member's ngroups
	 *
	 * @param array   $ngroups array of integers
	 */
	public function update_ngroups(array $ngroups) {

		DB::transaction_start();

		$sql = "SELECT ngroup FROM member_ngroup WHERE member=".intval($this->id);
		$existing_ngroups = DB::fetchfieldarray($sql);

		$insert_ngroups = array_diff($ngroups, $existing_ngroups);
		if ($insert_ngroups) {
			$sql_groups = array();
			foreach ($insert_ngroups as $insert_ngroup) {
				$sql_groups[] = "(".intval($this->id).", ".intval($insert_ngroup).")";
			}
			DB::query("INSERT INTO member_ngroup (member, ngroup) VALUES ".join(", ", $sql_groups));
		}

		$delete_ngroups = array_diff($existing_ngroups, $ngroups);
		if ($delete_ngroups) {
			$sql = "DELETE FROM member_ngroup
				WHERE member=".intval($this->id)."
					AND ngroup IN (".join(", ", array_map("intval", $delete_ngroups)).")";
			DB::query($sql);
		}

		DB::transaction_commit();

	}


	/**
	 * display a timestamp
	 *
	 * @param string  $content
	 */
	public function dbtableadmin_print_timestamp($content) {
		echo datetimeformat($content);
	}


	/**
	 * display the list of groups
	 */
	public function dbtableadmin_print_ngroups() {
		$this->display_ngroups();
	}


}
