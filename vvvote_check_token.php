<?
/**
 * check if a vvvote token is valid
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


const DOCROOT = "";

require "inc/config.php";
require "inc/errors.php";
require "inc/common.php";
require "inc/functions.php";


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

if (empty($post->electionId)) {
	return_error("ElectionId parameter missing");
}

if ( ! preg_match('#^'.preg_quote(VVVOTE_CONFIG_ID, '#').'/(\d+)$#', $post->electionId, $periodmatches) or empty($periodmatches[1]) ) {
	return_error("Period could not be extracted from electionId");
}

$sql = "SELECT count(*) FROM vvvote_token WHERE token=".DB::esc($post->token)." AND period=".intval($periodmatches[1]);
if ( DB::fetchfield($sql) ) {
	echo json_encode(['allowed'=>true]);
} else {
	echo json_encode(['allowed'=>false]);
}
