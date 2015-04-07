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

// get issues for online voting, cp. Issue::votingmode_offline()
$sql_issues = "SELECT id FROM issue
	WHERE period=".intval($row['period'])."
		AND votingmode_reached=FALSE
		AND votingmode_admin=FALSE";
$issues = DB::fetchfieldarray($sql_issues);

$notification = new Notification("vvvote");
$notification->period = $row['period'];
$notification->issues = $issues;
if ( $notification->send($row['member']) ) echo json_encode(['sent'=>true]);

return_error("Mail could not be sent");
