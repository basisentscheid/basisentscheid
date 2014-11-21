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

$scope = empty($_GET['admin']) ? "member" : "admin";

if ( isset($_POST['username']) and isset($_POST['password']) ) {
	$sql = "SELECT id, password FROM ".$scope."s WHERE username=".DB::esc($_POST['username']);
	$result = DB::query($sql);
	if ( $row = DB::fetch_assoc($result) ) {
		if ( crypt($_POST['password'], $row['password']) == $row['password'] ) {
			success(_("Login successful"));
			$_SESSION[$scope] = $row['id'];
			if (!empty($_POST['origin']) and !lefteq($_POST['origin'], BN)) redirect($_POST['origin']);
			redirect("index.php");
		}
	}
	warning(_("Login failed"));
	redirect();
}

html_head($scope=="admin"?_("Admin login"):_("Login"));

form(BN.($scope=="admin"?"?admin=1":""), 'class="login"');
if (!empty($_POST['origin'])) input_hidden("origin", $_POST['origin']);
?>
<fieldset>
<label class="td0"><span class="label"><?=_("Username")?>:</span><span class="input"><input type="text" name="username"></span></label>
<label class="td1"><span class="label"><?=_("Password")?>:</span><span class="input"><input type="password" name="password"></span></label>
<div class="th"><input type="submit" value="<?=_("login")?>"></div>
</fieldset>
<?
form_end();

html_foot();
