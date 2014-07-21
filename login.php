<?
/**
 * login.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$_SESSION['origin'] = @$_POST['origin'];

require "inc/PHP-OAuth2/src/OAuth2/Client.php";
require "inc/PHP-OAuth2/src/OAuth2/GrantType/IGrantType.php";
require "inc/PHP-OAuth2/src/OAuth2/GrantType/AuthorizationCode.php";

$client = new OAuth2\Client(OAUTH2_CLIENT_ID, OAUTH2_CLIENT_SECRET);

$extra_parameters = array('scope' => "member profile unique mail");
$auth_url = $client->getAuthenticationUrl(OAUTH2_AUTHORIZATION_ENDPOINT, BASE_URL.'auth.php', $extra_parameters);

redirect($auth_url);
