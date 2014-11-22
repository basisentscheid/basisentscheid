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
		action_required_parameters('username', 'password', 'password2', 'mail');

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

		// username
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
			Login::$member->password = crypt($password);
			$save_fields[] = "password";
			$success_msgs[] = _("The new password has been saved.");
		}

		Login::$member->update($save_fields);
		foreach ($success_msgs as $msg) success($msg);

		// save mail
		$mail = trim($_POST['mail']);
		if ( $mail != Login::$member->mail and $mail != Login::$member->mail_unconfirmed and Login::check_mail($mail) ) {
			Login::$member->set_mail($mail);
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
		<label for="password"><?=_("Password")?></label>
		<span class="input"><input type="password" name="password" size="25"> <?=_("again")?> <input type="password" name="password2" size="25"></span>
	</div>
	<div class="input td0">
		<label for="mail"><?=_("Mail address for notifications")?></label>
		<span class="input"><input type="text" name="mail" value="<?
if (Login::$member->mail) {
	echo Login::$member->mail;
	$unconfirmed = false;
} else {
	echo Login::$member->mail_unconfirmed;
	$unconfirmed = true;
}
?>" size="40"><?
if ($unconfirmed) { ?> <span class="unconfirmed"><?=_("Not yet confirmed!")?></span><? }
?></span>
	</div>
	<div class="input td1">
		<label><?=_("Real name (optional)")?></label>
		<span class="input"><?=h(Login::$member->public_id)?></span>
	</div>
	<div class="input td0">
		<label><?=_("Profile")?></label>
		<span class="input"><?=h(Login::$member->profile)?></span>
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
