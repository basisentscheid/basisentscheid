<?
/**
 * auth.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

require "inc/PHP-OAuth2/src/OAuth2/Client.php";
require "inc/PHP-OAuth2/src/OAuth2/GrantType/IGrantType.php";
require "inc/PHP-OAuth2/src/OAuth2/GrantType/AuthorizationCode.php";

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

//$response = $client->fetch('https://beoauth.piratenpartei-bayern.de/api/user/membership/');
//var_dump($response);

$response_profile = $client->fetch('https://beoauth.piratenpartei-bayern.de/api/user/profile/');
//var_dump($response_profile);

$response_auid = $client->fetch('https://beoauth.piratenpartei-bayern.de/api/user/auid/');
//var_dump($response);

$auid = $response_auid['result']['auid'];
$username = $response_profile['result']['username'];

// login
$sql = "SELECT id FROM members WHERE auid=".DB::esc($auid);
$result = DB::query($sql);
if ( $row = DB::fetch_assoc($result) ) {
	// user already in the database
	$_SESSION['member'] = $row['id'];
} else {
	// user not yet in the database
	$member = new Member;
	$member->auid = $auid;
	$member->set_unique_username($username);
	$member->create();
	$_SESSION['member'] = $member->id;
}

// redirect to where the user came from
if (!empty($_SESSION['origin'])) {
	$origin = $_SESSION['origin'];
	unset($_SESSION['origin']);
	redirect($origin);
} else {
	redirect("proposals.php");
}
