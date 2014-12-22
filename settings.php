<?
/**
 * member settings
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("member");

if ($action) {
	switch ($action) {
	case "save":
		action_required_parameters('username', 'password', 'password2', 'mail', 'profile');
		if (GNUPG_SIGN_KEY) action_required_parameters('fingerprint', 'key');

		$save_fields = array();
		$success_msgs = array();

		// save username
		$username = trim($_POST['username']);
		if ( $username != Login::$member->username and Login::check_username($username) ) {
			Login::$member->username = $username;
			$save_fields[] = "username";
			$success_msgs[] = _("The new username has been saved.");
		}

		// save password
		$password  = trim($_POST['password']);
		$password2 = trim($_POST['password2']);
		if ( ($password or $password2) and Login::check_password($password, $password2) ) {
			Login::$member->set_new_password($password);
			$save_fields[] = "password";
			$success_msgs[] = _("The new password has been saved.");
		}

		// save fingerprint
		if (GNUPG_SIGN_KEY) {
			$fingerprint = trim($_POST['fingerprint']);
			if ( $fingerprint != Login::$member->fingerprint ) {
				Login::$member->set_fingerprint($fingerprint);
				$save_fields[] = "fingerprint";
				$success_msgs[] = _("The PGP public key fingerprint has been saved.");
			}
		}

		// save profile
		$profile = trim($_POST['profile']);
		if ( $profile != Login::$member->profile ) {
			Login::$member->set_profile($profile);
			$save_fields[] = "profile";
			$success_msgs[] = _("The profile has been saved.");
		}

		Login::$member->update($save_fields);
		foreach ($success_msgs as $msg) success($msg);

		// save mail
		$mail = trim($_POST['mail']);
		if (
			$mail != Login::$member->mail and
			( $mail != Login::$member->mail_unconfirmed or !empty($_POST['submit_mail']) ) and
			Login::check_mail($mail)
		) {
			Login::$member->set_mail($mail);
		}

		// import PGP public key
		if (GNUPG_SIGN_KEY and $_POST['key']) {
			$gnupg = new_gnupg();
			$import = $gnupg->import($_POST['key']);
			if (DEBUG) {
?>
<!--
<?=h(print_r($import, true))?>
-->
<?
			}
			if ($import['imported'] + $import['unchanged'] + $import['newuserids'] + $import['newsubkeys'] > 1) {
				notice(sprintf(_("Multiple keys were uploaded at once. %d keys have been imported and %d keys are unchanged."), $import['imported'], $import['unchanged']));
			} elseif ($import['imported'] or $import['newuserids'] or $import['newuserids'] or $import['newsubkeys']) {
				if ($import['fingerprint'] != Login::$member->fingerprint()) {
					notice(_("The key has been imported, but does not match the fingerprint."));
				} elseif ( !gnupg_keyinfo_matches_email( $gnupg->keyinfo($import['fingerprint']), Login::$member->mail ) ) {
					notice(_("The key has been imported, but does not match the email address."));
				} else {
					success(_("The key has been imported."));
				}
			} elseif ($import['unchanged']) {
				notice(_("The key has already been imported."));
			} else {
				warning(_("The key could not be imported."));
			}
		}

		redirect();

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Member settings"));

display_nav_settings();

form(BN);
?>
<fieldset class="member">
	<div class="input <?=stripes()?>">
		<label><?=_("Groups")?></label>
		<span class="input"><?
Login::$member->display_ngroups();
?></span>
	</div>
<? if (Login::$member->realname) { ?>
	<div class="input <?=stripes()?>">
		<label><?=_("Real name")?></label>
		<span class="input"><?=h(Login::$member->realname)?></span>
	</div>
<? } ?>
	<div class="input <?=stripes()?>">
		<label for="username"><?=_("Username")?></label>
		<span class="input"><input type="text" name="username" value="<?=h(Login::$member->username)?>" size="32" maxlength="32"></span>
	</div>
	<div class="input <?=stripes()?>">
		<label for="password"><?=_("Change password")?></label>
		<span class="input">
			<?=_("To change the password, enter the new password twice:")?><br>
			<input type="password" name="password" size="25"> <input type="password" name="password2" size="25">
		</span>
	</div>
	<div class="input <?=stripes()?>">
		<label for="mail"><?=_("Email address for notifications")?></label>
		<span class="input">
			<p><?=_("confirmed")?>: <?=h(Login::$member->mail)?></p>
			<?=_("new")?>: <input type="text" name="mail" value="<?=h(Login::$member->mail_unconfirmed)?>" size="40">
<? if (Login::$member->mail_unconfirmed) { ?>
			<input type="submit" name="submit_mail" value="<?=_("send the confirmation email again")?>">
<? } ?>
		</span>
	</div>
<? if (GNUPG_SIGN_KEY) { ?>
	<div class="input <?=stripes()?>">
		<label><?=_("PGP Public Key Fingerprint")?></label>
		<span class="input"><input type="text" name="fingerprint" value="<?=h(Login::$member->fingerprint)?>" size="50" maxlength="<?=Member::fingerprint_length?>">
<?
	Login::$member->display_fingerprint_info();
?>
		</span>
	</div>
	<div class="input <?=stripes()?>">
		<label><?=_("PGP Public Key import")?></label>
		<span class="input"><textarea name="key" cols="80" rows="5"></textarea></span>
	</div>
<? } ?>
	<div class="input <?=stripes()?>">
		<label><?=_("Profile")?></label>
		<span class="input"><textarea name="profile" cols="80" rows="5" maxlength="<?=Comment::title_length?>"><?=h(Login::$member->profile)?></textarea></span>
	</div>
	<div class="button th">
		<input type="hidden" name="action" value="save">
		<input type="submit" value="<?=_("Save")?>">
	</div>
</fieldset>
<?
form_end();

html_foot();
