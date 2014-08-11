<?
/**
 * login as an admin user
 *
 * Admin users should use this page as start page.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


// login

if ( isset($_POST['username']) and isset($_POST['password']) ) {

	$sql = "SELECT id, password FROM admins WHERE username=".DB::esc($_POST['username']);
	$result = DB::query($sql);
	if ( $row = DB::fetch_assoc($result) ) {

		if ( crypt($_POST['password'], $row['password']) == $row['password'] ) {
			success(_("Login successful"));
			$_SESSION['admin'] = $row['id'];
			redirect("proposals.php");
		}

	}

	warning(_("Login failed"));
	redirect();

}


html_head(_("Admin login"));

form(BN, 'class="login"');
?>
<fieldset>
<label class="td0"><span class="label"><?=_("Username")?>:</span><span class="input"><input type="text" name="username"></span></label>
<label class="td1"><span class="label"><?=_("Password")?>:</span><span class="input"><input type="password" name="password"></span></label>
<div class="th"><input type="submit" value="<?=_("submit")?>"></div>
</fieldset>
</form>

<?

html_foot();
