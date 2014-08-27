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
		action_required_parameters('username', 'mail');

		// save notification settings
		foreach ( Notification::$default_settings as $interest => $types ) {
			$fields_values = array('member'=>Login::$member->id, 'interest'=>$interest);
			foreach ( $types as $type => $value ) {
				$fields_values[$type] = !empty($_POST['notify'][$interest][$type]);
			}
			DB::insert_or_update("notify", $fields_values, array('member', 'interest'));
		}

		$success_msgs = array();

		$username = trim($_POST['username']);
		if ($username != Login::$member->username) {
			if ($username) {
				Login::$member->username = Login::$member->set_unique_username($username);
				$success_msgs[] = _("The new username has been saved.");
			} else {
				Login::$member->username = NULL;
				$success_msgs[] = _("You are now anonymous.");
			}
		}

		Login::$member->update(array('username'));
		foreach ($success_msgs as $msg) success($msg);

		// save mail
		$mail = trim($_POST['mail']);
		if ($mail != Login::$member->mail) {
			if ( ! preg_match('/^[^@%s]+@[^@%s]+\.[a-z]+$/i', $mail) ) {
				warning(_("This email address is not valid!"));
				break;
			}
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
		<span class="input"><input type="text" name="username" value="<?=h(Login::$member->username)?>" size="25">
			(<?=_('leave empty to be displayed as "anonymous"')?>)</span>
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
	<div class="input td1">
		<label for="mail"><?=_("Mail address for notifications")?></label>
		<span class="input"><input type="text" name="mail" value="<?=h(Login::$member->mail)?>" size="40"></span>
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
</form>
<?

html_foot();
