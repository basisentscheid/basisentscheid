<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

if ($action) {
	if ($action!="save") {
		warning("Unknown action");
		redirect();
	}
	if (!isset($_POST['username'])) {
		warning("Parameter missing.");
		redirect();
	}

	$username = trim($_POST['username']);
	if ($username==Login::$member->username) redirect();

	if ($username) {
		$sql = "SELECT * FROM members WHERE username=".DB::m($username);
		$result = DB::query($sql);
		if ( pg_num_rows($result) ) {
			warning("This username is already used by someone else. Please choose a different one!");
			redirect();
		}
		Login::$member->username = $username;
		Login::$member->update(array("username"));
		success("The new username has been saved.");
		redirect();
	}

	Login::$member->username = NULL;
	Login::$member->update(array("username"));
	success("You are now anonymous.");
	redirect();
}


html_head(_("Member"));

?>
<form action="<?=BN?>" method="post">
<?=_("Username")?> (leave empty to be displayed as "anonymous"): <input type="text" name="username" value="<?=h(Login::$member->username)?>"><br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
</form>
<?


html_foot();
