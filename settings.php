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

		redirect();

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Member settings"));

display_nav_settings();

form(BN, "", "settings", true);
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
		<span class="input"><input type="text" name="username" value="<?=h(Login::$member->username)?>" size="32" maxlength="32" required></span>
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
			<?=_("new")?>: <input type="email" name="mail" value="<?=h(Login::$member->mail_unconfirmed)?>" size="40">
<? if (Login::$member->mail_unconfirmed) { ?>
			<input type="submit" name="submit_mail" value="<?=_("send the confirmation email again")?>">
<? } ?>
		</span>
	</div>
	<div class="input <?=stripes()?>">
		<label><?=_("Profile")?></label>
		<span class="input"><textarea name="profile" cols="80" rows="10" maxlength="<?=Comment::title_length?>"><?=h(Login::$member->profile)?></textarea></span>
	</div>
	<div class="button th">
		<input type="hidden" name="action" value="save">
		<input type="submit" value="<?=_("Save")?>">
	</div>
</fieldset>
<?
form_end();

html_foot();
