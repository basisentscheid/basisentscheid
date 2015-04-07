<?
/**
 * included in every script, which is called by vvvote over http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see vvvote_check_token.php
 * @see vvvote_send_confirmation.php
 */


const DOCROOT = "";

require "inc/config.php";
require "inc/errors.php";
require "inc/common.php";
require "inc/functions.php";


// read JSON POST data
$post = json_decode(file_get_contents("php://input"));

if (!$post) {
	return_error("No valid JSON POST data");
}

if (empty($post->verifierPassw)) {
	return_error("Password parameter missing");
}

if ( !in_array($post->verifierPassw, split_csa(VVVOTE_CHECK_TOKEN_PASSWORDS)) ) {
	return_error("Wrong password");
}

if (empty($post->token)) {
	return_error("Token parameter missing");
}


/**
 * return an error message
 *
 * @param string  $text
 */
function return_error($text) {
	echo json_encode([
			'errorText' => $text,
			'errorType' => "adminNeeded"
		]);
	exit;
}
