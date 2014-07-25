<?
/**
 * login for demonstation purposes when an ID server is not reachable
 *
 * This file has to be removed in live environment!
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


if ( !empty($_POST['username']) ) {
	$sql = "SELECT id FROM members WHERE username=".DB::m($_POST['username']);
	$result = DB::query($sql);
	if ( $row = pg_fetch_assoc($result) ) {
		// user already in the database
		$_SESSION['member'] = $row['id'];
		success("Logged in as existing member ".$_POST['username']);
	} else {
		// user not yet in the database
		$member = new Member;
		$member->set_unique_username($_POST['username']);
		$member->auid = $member->username."_".rand();
		$member->create();
		$_SESSION['member'] = $member->id;
		success("Logged in as new member ".$member->username);
	}
	redirect("proposals.php");
}


html_head(_("Local member login"));

?>

<form action="<?=BN?>" method="POST">
<?=_("Username")?>: <input type="text" name="username"><br>
<input type="submit">
</form>

<?

html_foot();
