<?
/**
 * vote vvvote
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$period = new Period(@$_GET['period']);
if (!$period->id) {
	error(_("The requested period does not exist."));
}
if (strtotime($period->voting) > time()) {
	error(_("The voting in this period has not yet started."));
}
if (strtotime($period->counting) < time()) {
	error(_("The voting in this period is already closed."));
}

$ngroup = $period->ngroup();
Login::access("entitled", $ngroup->id);

$sql = "SELECT token, generated FROM vvvote_token WHERE period=".intval($period->id)." AND member=".intval(Login::$member->id);
if ( $row = DB::fetchassoc($sql) ) {

	DB::to_bool($row['generated']);
	if ($row['generated']) {
		html_head(_("Voting with vvvote"));
		notice(_("You have already generated an envelope for this voting. Open the envelope in your browser to vote."));
		html_foot();
		exit;
	}

	// use existing token
	$token = $row['token'];

} else {

	// generate token
	DB::transaction_start();
	do {
		$token = Login::generate_token(24);
		$sql = "SELECT token FROM vvvote_token WHERE token=".DB::esc($token);
	} while ( DB::numrows($sql) );
	$sql = "INSERT INTO vvvote_token (member, period, token) VALUES (".intval(Login::$member->id).", ".intval($period->id).", ".DB::esc($token).")";
	DB::query($sql);
	DB::transaction_commit();

}

// redirect to vvvote
redirect($period->vvvote_configurl."&token=".$token);
