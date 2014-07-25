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


/*
DB::query("TRUNCATE admins");
$a = new Admin;
$a->username = "test";
$a->password = crypt("test");
$a->create();
*/


// login

if ( isset($_POST['username']) and isset($_POST['password']) ) {

	$sql = "SELECT id, password FROM admins WHERE username=".DB::m($_POST['username']);
	$result = DB::query($sql);
	if ( $row = pg_fetch_assoc($result) ) {

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

?>

<form action="<?=BN?>" method="POST">
<?=_("Username")?>: <input type="text" name="username"><br>
<?=_("Password")?>: <input type="password" name="password"><br>
<input type="submit">
</form>

<?

html_foot();
