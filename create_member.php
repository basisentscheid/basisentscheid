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
				DB::insert("member_ngroup", array('member'=>$member->id, 'ngroup'=>$ngroup));
			}
		}

		redirect("register.php?invite=".$member->invite);

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Create new member"));

form(BN);
input_hidden("action", "create");
$sql = "SELECT * FROM ngroup";
$result = DB::query($sql);
while ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
	input_checkbox("ngroups[]", $ngroup->id, true);
	echo $ngroup->name;
	?><br><?
}
input_submit(_("Create member"));
form_end();

html_foot();
