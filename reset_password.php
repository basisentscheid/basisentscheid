<?
/**
 * Set a new password
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::logout();


if (!empty($_REQUEST['code'])) {

	$code = $_REQUEST['code'];

	$sql = "SELECT * FROM member WHERE password_reset_code=".DB::esc($code)." AND password_reset_code_expiry > now()";
	$result = DB::query($sql);
	$member = DB::fetch_object($result, "Member");

	if (!$member) {
		warning(_("The code is invalid!"));
	}

} else {
	$code = "";
	$member = false;
}


$password = "";

if ($action) {
	switch ($action) {
	case "set_password":
		action_required_parameters('password', 'password2');

		if (!$member) break;

		$password  = trim($_POST['password']);
		$password2 = trim($_POST['password2']);
		if ( ! Login::check_password($password, $password2) ) break;

		$member->password = crypt($password);
		if ( ! $member->update(['password'], 'password_reset_code=NULL, password_reset_code_expiry=NULL') ) break;
		success(_("Password has been reset successfully. You can log in with the new password now:"));

		redirect("login.php");
		break;

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Reset password"));

form(BN);
input_hidden("action", "set_password");
?>
<fieldset class="member">
<?
if ($member) {
	input_hidden("code", $code);
} else {
?>
	<div class="description td1"><?=_("Please enter the code you have received by email:")?></div>
	<div class="input td1">
		<label for="username"><?=_("Code")?></label>
		<span class="input"><input type="text" name="code" value="<?=h($code)?>" size="30"></span>
	</div>
<?
}
?>
	<div class="description td0"><?=_("Please enter your new password twice. The password is case sensitive and has to be at least 8 characters long.")?></div>
	<div class="input td0">
		<label for="password"><?=_("Password")?></label>
		<span class="input"><input type="password" name="password" value="<?=h($password)?>" size="25"> <input type="password" name="password2" value="<?=h($password)?>" size="25"></span>
	</div>
  	<div class="button th"><input type="submit" value="<?=_("Set new password")?>"></div>
</fieldset>
<?
form_end();

html_foot();
