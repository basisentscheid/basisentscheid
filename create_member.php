<?
/**
 * Create a new member for testing
 *
 * This file has to be removed in live environment!
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::logout();


if ($action) {
	switch ($action) {
	case "create":

		$member = new Member;
		$member->invite = Login::generate_token(24);
		$member->eligible = true;
		$member->verified = true;
		$member->create();

		// become member of ngroups
		if (!empty($_POST['ngroups'])) {
			foreach ( $_POST['ngroups'] as $ngroup ) {
				$ngroup_int = intval($ngroup);
				if (!$ngroup_int or $ngroup_int!=$ngroup) continue;
				DB::insert("member_ngroup", array('member'=>$member->id, 'ngroup'=>$ngroup_int));
			}
		}

		redirect("register.php?invite=".$member->invite);

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Create new member"));

?>
<section class="help"><p><?=_("In this demo or test installation you can just create a member account by yourself. Next you will be forwarded to the registration, where you can register yourself with this account.")?></p></section>
<?

form(BN);
echo _("Groups of the new member")?>:<br><?
input_hidden("action", "create");
$sql = "SELECT * FROM ngroup WHERE active=TRUE";
$result = DB::query($sql);
while ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
	input_checkbox("ngroups[]", $ngroup->id, true);
	echo $ngroup->name;
	?><br><?
}
input_submit(_("Create new member"));
form_end();

html_foot();
