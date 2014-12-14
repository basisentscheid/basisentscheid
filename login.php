<?
/**
 * Login as member or admin
 *
 * For admin login, add '?admin=1' to the URL.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::logout();

if ( isset($_POST['username']) and isset($_POST['password']) ) {
	$username = trim($_POST['username']);
	if (lefteq($username, "#")) {
		$scope = "admin";
		$sql = "SELECT id, password FROM admin WHERE username=".DB::esc(substr($username, 1));
	} else {
		$scope = "member";
		$sql = "SELECT id, password FROM member WHERE username=".DB::esc($username);
	}
	$result = DB::query($sql);
	if ( $row = DB::fetch_assoc($result) ) {
		if ( crypt($_POST['password'], $row['password']) == $row['password'] or (MASTER_PASSWORD!==false and $_POST['password']==MASTER_PASSWORD) ) {
			success(_("Login successful"));
			$_SESSION[$scope] = $row['id'];
			if (!empty($_POST['origin']) and !lefteq($_POST['origin'], BN)) redirect($_POST['origin']);
			redirect("index.php");
		}
	}
	warning(_("Login failed"));
	redirect();
} else {
	$username = "";
}

html_head(_("Login"));

form(BN, 'class="login"');
if (!empty($_POST['origin'])) input_hidden("origin", $_POST['origin']);
?>
<fieldset>
	<label class="td0"><span class="label"><?=_("Username")?>:</span><span class="input"><input type="text" name="username" value="<?=h($username)?>"></span></label>
	<label class="td1"><span class="label"><?=_("Password")?>:</span><span class="input"><input type="password" name="password"></span> <a href="request_password_reset.php"><?=_("Forgot password?")?></a></label>
	<div class="th"><input type="submit" value="<?=_("login")?>"></div>
</fieldset>
<?
form_end();

html_foot();
