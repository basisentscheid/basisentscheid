<?
/**
 * Register user from invitation
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$invite = trim(@$_GET['invite']);

if ($invite) {
	$sql = "SELECT *, now() > invite_expiry AS invite_expired FROM member WHERE invite=".DB::esc($invite)." AND activated IS NULL";
	$result = DB::query($sql);
	$member = DB::fetch_object($result, "Member");
	if ($member) {
		DB::to_bool($member->invite_expired);
		if ($member->invite_expired) {
			warning(sprintf(_("The code you've entered is expired! Please contact %s to get a new one!"), MAIL_SUPPORT), true);
			$member = false;
		}
	} else {
		warning(_("The code you've entered is invalid!"));
	}
} else {
	$member = false;
}

if (!$member) {

	html_head(_("Registration"));

?>
<form action="<?=BN?>" method="GET">
<fieldset class="member">
	<div class="description td0"><?=_("Please enter the invite code you've received.")?></div>
	<div class="input td0">
		<label for="invite"><?=_("Invite code")?></label>
		<span class="input"><input type="text" name="invite" id="invite" size="30" value="<?=h($invite)?>" required></span>
	</div>
	<div class="button th"><input type="submit" value="<?=_("Proceed with registration")?>"></div>
</fieldset>
</form>
<?

	html_foot();
	exit;

}


$username = "";
$password = "";
$mail     = "";

if ($action) {
	switch ($action) {
	case "activate":
		action_required_parameters('username', 'password', 'password2', 'mail');

		$username  = trim($_POST['username']);
		$password  = trim($_POST['password']);
		$password2 = trim($_POST['password2']);
		$mail      = trim($_POST['mail']);

		if ( ! Login::check_username($username)             ) break;
		if ( ! Login::check_password($password, $password2) ) break;
		if ( ! Login::check_mail($mail)                     ) break;

		Login::$member = $member;

		Login::$member->username = $username;
		Login::$member->password = crypt($password);
		if ( ! Login::$member->update(['username', 'password'], 'activated=now()') ) break;
		success(_("Your account has been activated."));

		Login::$member->set_mail($mail);

		$_SESSION['member'] = Login::$member->id;

		redirect("settings.php");

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Registration"));

// terms of use
if ( preg_match('#<section id="terms_of_use">.*</section>#s', file_get_contents("locale/about_".LANG.".html"), $matches) ) {
	echo $matches[0];
}

form(h(BN."?invite=".$invite));
?>
<fieldset class="member">
	<div class="description td0"><?=_("Please choose a username, i.e. your real name or your nick name. This name will be used for login and will be shown to others to identify you. The username is case sensitive.")?></div>
	<div class="input td0">
		<label for="username"><?=_("Username")?></label>
		<span class="input"><input type="text" name="username" id="username" value="<?=h($username)?>" size="32" maxlength="32" required></span>
	</div>
	<div class="description td1"><?=_("Please choose a password and enter it twice. The password is case sensitive and has to be at least 8 characters long.")?></div>
	<div class="input td1">
		<label for="password"><?=_("Password")?></label>
		<span class="input"><input type="password" name="password" id="password" value="<?=h($password)?>" size="32" required> <input type="password" name="password2" value="<?=h($password)?>" size="32" required></span>
	</div>
	<div class="description td0"><?=_("Please enter your email address. This address will be used for automatic notifications (if you request them) and in case you've lost your password. This address will not be published. After registration you will receive an email with a confirmation link.")?></div>
	<div class="input td0">
		<label for="mail"><?=_("Email address for notifications")?></label>
		<span class="input"><input type="email" name="mail" id="mail" value="<?=h($mail)?>" size="40" required></span>
	</div>
	<div class="button th"><input type="submit" value="<?=_("Activate account")?>"></div>
</fieldset>
<input type="hidden" name="action" value="activate">
<?
form_end();

html_foot();
