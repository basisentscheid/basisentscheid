<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

if ($action) {
	switch ($action) {
	case "save":
		action_required_parameters('username', 'password', 'password2', 'mail', 'profile');
		if (GNUPG_SIGN_KEY) action_required_parameters('fingerprint', 'key');

		// save notification settings
		foreach ( Notification::$default_settings as $interest => $types ) {
			$fields_values = array('member'=>Login::$member->id, 'interest'=>$interest);
			foreach ( $types as $type => $value ) {
				$fields_values[$type] = !empty($_POST['notify'][$interest][$type]);
			}
			DB::insert_or_update("notify", $fields_values, array('member', 'interest'));
		}

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
			//var_dump($return);
			if ($import['imported'] + $import['unchanged'] > 1) {
				notice(sprintf(_("Multiple keys were uploaded at once. %d keys have been imported and %d keys are unchanged."), $import['imported'], $import['unchanged']));
			} elseif ($import['imported']) {
				if ($import['fingerprint'] != Login::$member->fingerprint()) {
					notice(_("The key has been imported, but does not match the fingerprint."));
				} else {
					$info = $gnupg->keyinfo($import['fingerprint']);
					if ( !Login::$member->keyinfo_matches_email($info) ) {
						notice(_("The key has been imported, but does not match the email address."));
					} else {
						success(_("The key has been imported."));
					}
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


html_head(_("Member"));

form(BN);
?>
<fieldset class="member">
	<div class="input td0">
		<label for="username"><?=_("Username")?></label>
		<span class="input"><input type="text" name="username" value="<?=h(Login::$member->username)?>" size="32" maxlength="32"></span>
	</div>
	<div class="input td1">
		<label for="password"><?=_("Change password")?></label>
		<span class="input">
			<?=_("To change the password, enter the new password twice:")?><br>
			<input type="password" name="password" size="25"> <?=_("again")?> <input type="password" name="password2" size="25">
		</span>
	</div>
	<div class="input td0">
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
	<div class="input td1">
		<label><?=_("PGP Public Key Fingerprint")?></label>
		<span class="input"><input type="text" name="fingerprint" value="<?=h(Login::$member->fingerprint)?>" size="50" maxlength="<?=Member::fingerprint_length?>">
<?
	Login::$member->display_fingerprint_info();
?>
		</span>
	</div>
	<div class="input td0">
		<label><?=_("PGP Public Key import")?></label>
		<span class="input"><textarea name="key" cols="80" rows="5" maxlength="10000"></textarea></span>
	</div>
<? } ?>
	<div class="input td1">
		<label><?=_("Real name (optional)")?></label>
		<span class="input"><?=h(Login::$member->public_id)?></span>
	</div>
	<div class="input td0">
		<label><?=_("Profile")?></label>
		<span class="input"><textarea name="profile" cols="80" rows="5" maxlength="<?=Argument::title_length?>"><?=h(Login::$member->profile)?></textarea></span>
	</div>
	<div class="input td1">
		<label><?=_("Entitled and verified")?></label>
		<span class="input"><? display_checked(Login::$member->entitled) ?></span>
	</div>
	<div class="input td0">
		<label><?=_("Groups")?></label>
		<span class="input"><?

$sql = "SELECT name FROM members_ngroups
	JOIN ngroups ON ngroups.id = members_ngroups.ngroup
	WHERE member=".intval(Login::$member->id);
echo join(", ", DB::fetchfieldarray($sql));

?></span>
	</div>
</fieldset>

<h2><?=_("Email notification settings")?></h2>
<table class="notify">
	<tr>
		<th></th>
<?
$types = Notification::types();
foreach ($types as $type => $type_title) {
?>
		<th class="type"><?=$type_title?></th>
<?
}
?>
	</tr>
<?
$notify = Login::$member->notification_settings();
foreach (Notification::interests() as $interest => $interest_title) {
?>
	<tr class="<?=stripes()?>">
		<td class="right"><?=$interest_title?></td>
<?
	foreach ($types as $type => $type_title) {
?>
		<td class="center"><input type="checkbox" name="notify[<?=$interest?>][<?=$type?>]" value="1"<?
		if ($notify[$interest][$type]) { ?> checked<? }
		?>></td>
<?
	}
?>
	</tr>
<?
}
?>
</table>

<br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
<?
form_end();

html_foot();
