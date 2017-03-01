<?
/**
 * Login as member or admin
 *
 * For admin login, add '?admin=1' to the URL.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

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
		if ( password_verify($_POST['password'], $row['password']) or (MASTER_PASSWORD!==false and $_POST['password']==MASTER_PASSWORD) ) {
			success(_("Login successful"));
			$_SESSION[$scope] = $row['id'];
			if (empty($_POST['origin'])) redirect("index.php");
			foreach ( array("login.php", "reset_password.php", "register.php") as $page ) {
				if ( lefteq($_POST['origin'], $page) ) redirect("index.php");
			}
			redirect($_POST['origin']);
		}
	}
	warning(_("Login failed"));
} else {
	$username = "";
}


html_head(_("Login"));

form(BN, 'class="login log_pg"');
if (!empty($_POST['origin'])) input_hidden("origin", $_POST['origin']);
?>
<fieldset>
	<label class="td0"><span class="label"><?=_("Username")?>:</span><span class="input"><input type="text" name="username" value="<?=h($username)?>"></span></label>
	<label class="td1"><span class="label"><?=_("Password")?>:</span><span class="input"><input type="password" name="password"></span> <a href="request_password_reset.php"><?=_("Forgot password?")?></a></label>
	<label class="th"><span class="label"></span><span class="input"><input type="submit" value="<?=_("login")?>"></span></label>
</fieldset>
<?
form_end();

html_foot();
