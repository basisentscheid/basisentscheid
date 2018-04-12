<?
/**
 * Request an email with a link to reset the password
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::logout();


$show_form = true;

if ($action) {
	switch ($action) {
	case "request_password_reset":
		action_required_parameters('username');

		if (!$_POST['username']) break;

		$sql = "SELECT * FROM member
	        WHERE username=".DB::esc(trim($_POST['username']))."
			AND ( password_reset_code IS NULL OR password_reset_code_expiry < now() )";
		$result = DB::query($sql);
		if ( $member = DB::fetch_object($result, "Member") ) {

			// send the mail to both the confirmed and the unconfirmed mail address
			if ($member->mail and $member->mail_unconfirmed) {
				$to = $member->mail.", ".$member->mail_unconfirmed;
			} elseif ($member->mail) {
				$to = $member->mail;
			} elseif ($member->mail_unconfirmed) {
				$to = $member->mail_unconfirmed;
			} else {
				warning(sprintf(_("Sorry, but there is no email address for this account. Please contact %s!"), MAIL_SUPPORT), true);
				$show_form = false;
				break;
			}

			$member->password_reset_code = Login::generate_token(24);
			if ( ! $member->update(['password_reset_code'], "password_reset_code_expiry = now() + interval '1 day'") ) {
				warning(sprintf(_("The generated code could not be saved. Please try again or contact %s!"), MAIL_SUPPORT), true);
				break;
			}

			$subject = _("Password reset request");

			$body = sprintf(_("Hello %s!"), $member->username)."\n\n"
				._("To reset your password please click on the following link:")."\n"
				.BASE_URL."reset_password.php?code=".$member->password_reset_code."\n\n"
				._("If this link does not work, please open the following URL in your web browser:")."\n"
				.BASE_URL."reset_password.php\n"
				._("On that page please enter the code:")."\n"
				.$member->password_reset_code."\n\n"
				._("This code is only valid for one day.");

			send_mail($to, $subject, $body);

		}

		success(_("If a member with this login exists, a reset link has been sent to the stored email address."));
		$show_form = false;
		break;

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Request password reset"));

if ($show_form) {

?>
<p><?=_("Please enter your login name! You will receive an email with a link to reset your password. Note that the login name is case sensitive!")?></p>
<?

	form(BN, 'class="login log_pg"');
	input_hidden("action", "request_password_reset");
?>
<fieldset>
	<label class="td0"><span class="label"><?=_("Username")?>:</span><span class="input"><input type="text" name="username" size="32" maxlength="32"></span></label>
	<label class="th req_pas"><span class="label"></span><span class="input"><input type="submit" value="<?=_("Request password reset")?>"></span></label>
</fieldset>
<?
	form_end();

}

html_foot();
