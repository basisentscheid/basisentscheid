<?
/**
 * confirm mail address
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

if ($action) {
	if ($action!="confirm") error(_("Unknown action"));
	action_required_parameters('secret');
	action_confirm_mail($_POST['secret']);
}

// link in confirmation request mail clicked
if (isset($_GET['secret'])) {
	action_confirm_mail($_GET['secret']);
}


html_head(_("Email address confirmation"));

form(BN);
?>
<label><?=_("Confirmation code")?>: <input type="text" name="secret" size="20" value="<?=trim(@$_REQUEST['secret'])?>"></label>
<input type="hidden" name="action" value="confirm">
<input type="submit" value="<?=_("confirm")?>">
<?
form_end();

html_foot();


/**
 * action used for GET and POST
 *
 * @param string  $secret
 */
function action_confirm_mail($secret) {
	$sql = "SELECT * FROM members WHERE mail_secret = ".DB::esc(trim($secret))." AND mail_secret_expiry > now()";
	$result = DB::query($sql);
	if ( $member = DB::fetch_object($result, "Member") ) {
		$member->mail = $member->mail_unconfirmed;
		$member->mail_unconfirmed   = null;
		$member->mail_secret        = null;
		$member->mail_secret_expiry = null;
		$member->mail_lock_expiry   = null;
		$member->update(array('mail', 'mail_unconfirmed', 'mail_secret', 'mail_secret_expiry', 'mail_lock_expiry'));
		success(_("Your email address is confirmed now."));
		redirect("index.php");
	}
	warning(_("The confirmation code is not valid!"));
}
