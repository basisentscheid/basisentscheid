<?
/**
 * auth.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

require "inc/libs/PHP-OAuth2/src/OAuth2/Client.php";
require "inc/libs/PHP-OAuth2/src/OAuth2/GrantType/IGrantType.php";
require "inc/libs/PHP-OAuth2/src/OAuth2/GrantType/AuthorizationCode.php";

$client = new OAuth2\Client(OAUTH2_CLIENT_ID, OAUTH2_CLIENT_SECRET);
if (!isset($_GET['code'])) {
	error("Parameter missing.");
}

$params = array('code' => $_GET['code'], 'redirect_uri' => BASE_URL.'auth.php');
$response = $client->getAccessToken(OAUTH2_TOKEN_ENDPOINT, 'authorization_code', $params);
//var_dump($response);

//parse_str($response['result'], $info);
$client->setAccessToken($response['result']['access_token']);
$client->setAccessTokenType(OAuth2\Client::ACCESS_TOKEN_BEARER);

$response_auid = $client->fetch(OAUTH2_BASEURL."api/user/auid/");
//var_dump($response_auid);
/*
array(1) {
	["auid"]=>
	string(36) "3fa248a5-d9c0-4032-8076-fba431038c8d"
}
*/

$response_profile = $client->fetch(OAUTH2_BASEURL."api/user/profile/");
//var_dump($response_profile);
/*
array(3) {
	["username"]=>
	string(3) "foo"
	["profile"]=>
	string(28) "PhD in intercultural physics"
	["public_id"]=>
	string(14) "Dr. Mister Foo"
}
*/

$response_membership = $client->fetch(OAUTH2_BASEURL."api/user/membership/");
//var_dump($response_membership);
/*
array(6) {
	["nested_groups"]=>
	array(1) {
		[0]=>
		int(5)
	}
	["all_nested_hgroups"]=>
	array(3) {
		[0]=>
		int(1)
		[1]=>
		int(2)
		[2]=>
		int(5)
	}
	["verified"]=>
	bool(true)
	["hgroups"]=>
	array(1) {
		[0]=>
		int(5)
	}
	["type"]=>
	string(15) "entitled member"
	["all_hgroups"]=>
	array(3) {
		[0]=>
		int(1)
		[1]=>
		int(2)
		[2]=>
		int(5)
	}
}
*/

$auid = $response_auid['result']['auid'];

// login
$sql = "SELECT * FROM members WHERE auid=".DB::esc($auid);
$result = DB::query($sql);
if ( ! $member = DB::fetch_object($result, "Member") ) {
	// user not yet in the database
	$member = new Member;
	$member->auid = $auid;
	$member->set_unique_username($response_profile['result']['username']);
}
$member->public_id = (string) @$response_profile['result']['public_id'];
$member->profile   = (string) @$response_profile['result']['profile'];
$member->entitled  = ($response_membership['result']['type']=="entitled member");
if ($member->id) {
	$member->update(array('public_id', 'profile', 'entitled'));
} else {
	$member->create(array('auid', 'username', 'public_id', 'profile', 'entitled'));
}
$_SESSION['member'] = $member->id;

$member->update_ngroups($response_membership['result']['all_nested_groups']);

// redirect to where the user came from
if (!empty($_SESSION['origin'])) {
	$origin = $_SESSION['origin'];
	unset($_SESSION['origin']);
	redirect($origin);
} else {
	redirect("proposals.php");
}
