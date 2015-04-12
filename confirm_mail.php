<?
/**
 * confirm mail address
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";


if (Login::$member) {

	if ($action) {
		if ($action!="confirm") error(_("Unknown action"));
		action_required_parameters('code');
		action_confirm_mail($_POST['code']);
	}

	// link in confirmation request mail clicked
	if (isset($_GET['code'])) {
		action_confirm_mail($_GET['code']);
	}

}


html_head(_("Email address confirmation"));

if (Login::$member) {

	form(BN);
?>
<label><?=_("Code")?>: <input type="text" name="code" size="20" value="<?=trim(@$_REQUEST['code'])?>"></label>
<input type="hidden" name="action" value="confirm">
<input type="submit" value="<?=_("confirm")?>">
<?
	form_end();

} else {
	notice(_("Please log in to confirm your email address!"));
}

html_foot();


/**
 * action used for GET and POST
 *
 * @param string  $code
 */
function action_confirm_mail($code) {
	if (
		Login::$member->mail_code == trim($code) and
		strtotime(Login::$member->mail_code_expiry) > time()
	) {
		Login::$member->mail = Login::$member->mail_unconfirmed;
		Login::$member->mail_unconfirmed = null;
		Login::$member->mail_code        = null;
		Login::$member->mail_code_expiry = null;
		Login::$member->mail_lock_expiry = null;
		Login::$member->update(['mail', 'mail_unconfirmed', 'mail_code', 'mail_code_expiry', 'mail_lock_expiry']);
		success(_("Your email address is confirmed now."));
		redirect("settings.php");
	}
	warning(_("The confirmation code is not valid!"));
}
