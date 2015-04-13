<?
/**
 * send a confirmation email for a vvvote envelope
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http_vvvote.php";
require "inc/locale.php";


$sql = "SELECT member, period FROM vvvote_token WHERE token=".DB::esc($post->token);
if ( ! $row = DB::fetchassoc($sql) ) {
	return_error("Token not found");
}

// mark the token as used for envelope generation
$sql = "UPDATE vvvote_token SET generated=TRUE WHERE token=".DB::esc($post->token);
DB::query($sql);

$period = new Period($row['period']);
if (!$period->id) {
	return_error("Period does not exist");
}

// get issues for online voting, cp. Issue::votingmode_offline()
$sql_issue = "SELECT * FROM issue
	WHERE period=".intval($period->id)."
		AND votingmode_reached=FALSE
		AND votingmode_admin=FALSE";
$result_issue = DB::query($sql_issue);
$issues = array();
while ( $issue = DB::fetch_object($result_issue, "Issue") ) {
	/** @var Issue $issue */
	$issues[] = $issue;
}
if (!$issues) {
	return_error("No issues found");
}

$notification = new Notification("vvvote");
$notification->period = $period;
$notification->issues = $issues;
if ( $notification->send([$row['member']]) ) {
	echo json_encode(['sent'=>true]);
} else {
	echo json_encode(['sent'=>false]);
}
