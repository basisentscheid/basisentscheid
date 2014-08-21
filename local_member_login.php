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
	$sql = "SELECT id FROM members WHERE username=".DB::esc($_POST['username']);
	$result = DB::query($sql);
	if ( $row = DB::fetch_assoc($result) ) {
		// user already in the database
		$_SESSION['member'] = $row['id'];
		success("Logged in as existing member ".$_POST['username']);
	} else {
		// user not yet in the database
		$member = new Member;
		$member->set_unique_username($_POST['username']);
		$member->auid = substr($member->username."_".rand(), 0, 36);
		$member->entitled = true;
		$member->create();
		// become member of ngroups
		if (!empty($_POST['ngroups'])) {
			foreach ( $_POST['ngroups'] as $ngroup ) {
				DB::insert("members_ngroups", array('member'=>$member->id, 'ngroup'=>$ngroup));
			}
		}
		$_SESSION['member'] = $member->id;
		success("Logged in as new member ".$member->username);
	}
	redirect("index.php");
}


html_head(_("Local member login"));

form();
?>
<?=_("Username")?>: <input type="text" name="username"><br>
<?
$sql = "SELECT * FROM ngroups";
$result = DB::query($sql);
while ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
	input_checkbox("ngroups[]", $ngroup->id, true);
	echo $ngroup->name;
	?><br><?
}
input_submit(_("login"));
form_end();

html_foot();
