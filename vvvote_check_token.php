<?
/**
 * check if a vvvote token is valid
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http_vvvote.php";


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
