<?
/**
 * Member
 *
 * There are two methods to manage members and groups:
 *
 * 1) manual administration (config.php: IMPORT_MEMBERS=false)
 * - Members are identified by the "identity" column, where you can enter e.g. the real name or a member number. Of course this method offers no anonymity at all.
 * - If an invitation code expired, a new member account should be created.
 * - If a member forgets his password and has no working confirmed or unconfirmed email address, an admin can set a new unconfirmed email address. Then the member can use the password reset function.
 *
 * 2) import CSV files (config.php: IMPORT_MEMBERS=true)
 * - Members are identified by their invite code. The identity column stays empty. If the admin of the Basisentscheid and the admin of the member source database are different persons, the members can only be identified if both bring their data together.
 * - If a member forgets his password and has no working confirmed or unconfirmed email address, or if an invite code expired, the member should get a new member account by setting a new invite code in the member source database and sending the member a new invitation.
 *
 * Members can not be deleted. If they are no longer allowed to vote, switch off the "eligible" flag.
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
	public $identity;
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

	private $mail_unconfirmed_changed = false;


	/**
	 * create a new member
	 *
	 * @return boolean
	 */
	public function create($fields = false, $extra=array()) {

		$fields_values = array(
			'eligible' => $this->eligible,
			'verified' => $this->verified
		);

		// create a new member manually
		if (BN=="admin_members.php") {
			if (IMPORT_MEMBERS) return; // just to be sure
			$this->invite = Login::generate_token(24);
			$fields_values['mail_unconfirmed'] = $this->mail_unconfirmed;
		}

		$fields_values['invite'] = $this->invite;

		$extra = array('invite_expiry' => "now() + ".DB::esc(INVITE_EXPIRY));
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
		return _("Username").": ".$this->username;
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

		$this->password = password_hash($password, PASSWORD_DEFAULT);

		// notification
		if ($this->mail) {
			$subject = _("Change of your password");
			$body = _("Someone, probably you, changed your password.")."\n\n"
				.sprintf(_("If this was not you, somebody else got access to your account. In this case please contact %s as soon as possible!"), MAIL_SUPPORT);
			send_mail($this->mail, $subject, $body);
		}

	}


	/**
	 * save not yet confirmed email address and send confirmation request
	 *
	 * @param string  $mail
	 * @param boolean $admin (optional) an admin sets the new email address
	 */
	public function set_mail($mail, $admin=false) {

		if ( !$admin and strtotime($this->mail_lock_expiry) > time() ) {
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
			if ($admin) {
				$this->update(array());
				success(_("A confirmation request email has been sent."));
			} else {
				$this->update(array(), "mail_lock_expiry = now() + interval '1 hour'");
				success(_("Your email address has been saved. An email with a confirmation code has been sent."));
			}
		} else {
			if ($admin) {
				warning(_("The confirmation request email could not be sent."));
			} else {
				warning(sprintf(_("Your email address has been saved, but the email with the confirmation code could not be sent. Try again later or contact %s.")), MAIL_SUPPORT);
			}
		}

		// notification to old mail address
		if ($this->mail) {
			$subject = _("Change of your email address");
			if ($admin) {
				$body = _("An administrator changed your email address to:")."\n"
					.$this->mail_unconfirmed."\n\n"
					.sprintf(_("If this was not arranged with you, please contact %s!"), MAIL_SUPPORT);
			} else {
				$body = _("Someone, probably you, changed your email address to:")."\n"
					.$this->mail_unconfirmed."\n\n"
					._("If this was not you, somebody else got access to your account. In this case please log in as soon as possible and change your password:")."\n"
					.BASE_URL."settings.php\n"
					.sprintf(_("Then try to set the email address back to your one and contact %s!"), MAIL_SUPPORT);
			}
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

		if (!$this->mail) {
			?><p class="problem"><?=_("Please confirm your email address and then reload this page!")?></p><?
			return;
		}

		$gnupg = new_gnupg();

		$info = $gnupg->keyinfo($this->fingerprint());
		//var_dump($info);

		if ( !gnupg_keyinfo_matches_email($info, $this->mail) ) {
			?><p class="problem"><?=_("No key matching fingerprint and email address was found.")?></p><?
			return;
		}

		if ($info[0]["disabled"]) {
			?><p class="problem"><?=_("This key is disabled.")?></p><?
			return;
		}
		if ($info[0]["expired"]) {
			?><p class="problem"><?=_("This key is expired.")?></p><?
			return;
		}
		if ($info[0]["revoked"]) {
			?><p class="problem"><?=_("This key is revoked.")?></p><?
			return;
		}
		if ($info[0]["is_secret"]) {
			?><p class="problem"><?=_("This key is a secret key.")?></p><?
			return;
		}
		if (!$info[0]["can_encrypt"]) {
			?><p class="problem"><?=_("This key can not encrypt.")?></p><?
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
	 * edit a timestamp
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 */
	public function dbtableadmin_edit_timestamp($colname, $default) {
		echo datetimeformat($default);
	}


	/**
	 * display the list of groups
	 */
	public function dbtableadmin_print_ngroups() {
		$this->display_ngroups();
	}


	/**
	 * edit the list of groups
	 */
	public function dbtableadmin_edit_ngroups() {
?>
<table>
	<tr>
		<th></th>
		<th><?=_("ID")?></th>
		<th><?=_("Name")?></th>
		<th><?=_("active")?></th>
	</tr>
<?
		$sql = "SELECT id, name, active, member_ngroup.member FROM ngroup
			LEFT JOIN member_ngroup ON ngroup.id = member_ngroup.ngroup AND member_ngroup.member=".intval($this->id)."
			ORDER BY id";
		$result = DB::query($sql);
		while ( $row = DB::fetch_assoc($result) ) {
?>
	<tr>
		<td><? input_checkbox("ngroups[]", $row['id'], boolval($row['member'])); ?></td>
		<td class="right"><?=$row['id']?></td>
		<td><?=$row['name']?></td>
		<td class="center"><? display_checked($row['active']); ?></td>
	</tr>
<?
		}
?>
</table>
<?
	}


	/**
	 * check email address
	 *
	 * @return boolean
	 */
	public function dbtableadmin_beforesave_mail_unconfirmed() {
		$this->mail_unconfirmed = trim($this->mail_unconfirmed);
		return Login::check_mail($this->mail_unconfirmed);
	}


	/**
	 * save selection of ngroups
	 */
	private function save_ngroups() {
		if (isset($_POST['ngroups'])) $ngroups = $_POST['ngroups']; else $ngroups = array();
		if (is_array($ngroups)) {
			$this->update_ngroups($ngroups);
		} else {
			warning("Invalid value for groups");
		}
	}


	/**
	 * called after a member was created
	 */
	public function dbtableadmin_after_create() {

		$this->save_ngroups();

		// send invitation
		$subject = _("Invitation to Basisentscheid");
		$body = _("You are invited to Basisentscheid. Please click the following link to register:")."\n"
			.BASE_URL."register.php?invite=".$this->invite."\n\n"
			._("If this link does not work, please open the following URL in your web browser:")."\n"
			.BASE_URL."register.php\n"
			._("On that page enter the code:")."\n"
			.$this->invite;
		if ( send_mail($this->mail_unconfirmed, $subject, $body) ) {
			success(_("An invitation email has been sent to the new member."));
		} else {
			warning(_("The new member was not created, because the invitation email could not be sent!"));
		}

	}


	/**
	 * called after a member was edited
	 */
	public function dbtableadmin_after_edit() {

		$this->save_ngroups();

	}


}
