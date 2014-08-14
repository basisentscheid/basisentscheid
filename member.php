<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

if ($action) {
	if ($action!="save") {
		warning(_("Unknown action"));
		redirect();
	}
	action_required_parameters('username', 'mail');

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

	$mail = trim($_POST['mail']);
	if ($mail != Login::$member->mail) {
		Login::$member->mail = $mail;
		//Login::$member->send_mail_confirmation_request();
		$success_msgs[] = _("Your mail address has been saved."); // A confirmation request has been sent.");
	}

	Login::$member->update(array('username', 'mail'));
	foreach ($success_msgs as $msg) success($msg);
	redirect();
}


html_head(_("Member"));

form(BN);
?>
<?=_("Username")?> (<?=_('leave empty to be displayed as "anonymous"')?>): <input type="text" name="username" value="<?=h(Login::$member->username)?>"><br>
<?=_("Mail address for notifications")?>: <input type="text" name="mail" value="<?=h(Login::$member->mail)?>"><br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
</form>
<?


html_foot();
