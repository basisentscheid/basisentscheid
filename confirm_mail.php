<?
/**
 * confirm mail address
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

if ($action) {
	if ($action!="confirm") error(_("Unknown action"));
	action_required_parameters('code');
	action_confirm_mail($_POST['code']);
}

// link in confirmation request mail clicked
if (isset($_GET['code'])) {
	action_confirm_mail($_GET['code']);
}


html_head(_("Email address confirmation"));

form(BN);
?>
<label><?=_("Code")?>: <input type="text" name="code" size="20" value="<?=trim(@$_REQUEST['code'])?>"></label>
<input type="hidden" name="action" value="confirm">
<input type="submit" value="<?=_("confirm")?>">
<?
form_end();

html_foot();


/**
 * action used for GET and POST
 *
 * @param string  $code
 */
function action_confirm_mail($code) {
	$sql = "SELECT * FROM member WHERE mail_code = ".DB::esc(trim($code))." AND mail_code_expiry > now()";
	$result = DB::query($sql);
	if ( $member = DB::fetch_object($result, "Member") ) {
		$member->mail = $member->mail_unconfirmed;
		$member->mail_unconfirmed = null;
		$member->mail_code        = null;
		$member->mail_code_expiry = null;
		$member->mail_lock_expiry = null;
		$member->update(['mail', 'mail_unconfirmed', 'mail_code', 'mail_code_expiry', 'mail_lock_expiry']);
		success(_("Your email address is confirmed now."));
		redirect("index.php");
	}
	warning(_("The confirmation code is not valid!"));
}
