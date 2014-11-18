<?
/**
 * vote
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$issue = new Issue(@$_GET['issue']);
if (!$issue->id) {
	error(_("The requested issue does not exist."));
}
if ($issue->state == 'finished' or $issue->state == 'cleared') {
	error(_("The voting on this issue is already closed."));
} elseif ($issue->state != 'voting') {
	error(_("The issue is not in voting state."));
}

$ngroup = $issue->area()->ngroup();
Login::access("entitled", $ngroup->id);

$sql = "SELECT token FROM vote_tokens WHERE member=".intval(Login::$member->id)." AND issue=".intval($issue->id);
if ( ! $token = DB::fetchfield($sql) ) {
	error(_("You can not vote in this voting period, because you were not yet entitled when the voting started."));
}

if ($action) {
	switch ($action) {

	case "submit":

		$vote = serialize($_POST['vote']);
		// example for one single proposal:
		// array( 123 => array('acceptance' => 0) )
		// example for two proposals:
		// array( 123 => array('acceptance' => 1, 'score' => 2), 456 => array('acceptance' => -1, 'score' => 0) )

		DB::transaction_start();

		$sql = "INSERT INTO vote (token, vote) VALUES (".DB::esc($token).", ".DB::esc($vote).") RETURNING votetime";
		if ( $result = DB::query($sql) ) {
			list($votetime) = pg_fetch_row($result);

			$subject = _("Vote receipt");

			$body = _("Group").": ".$ngroup->name."\n\n";

			$body .= sprintf(_("Vote receipt for your vote on issue %d:"), $issue->id)."\n\n";
			foreach ( $_POST['vote'] as $proposal_id => $vote_proposal ) {
				$proposal = new Proposal($proposal_id);
				$body .= mb_wordwrap(_("Proposal")." ".$proposal_id.": ".$proposal->title)."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n"
					._("Acceptance").": ".acceptance($vote_proposal['acceptance']);
				if (isset($vote_proposal['score'])) $body .= ", "._("Score").": ".$vote_proposal['score'];
				$body .= "\n\n";
			}
			$body .= _("Your vote token").": ".$token."\n"
				._("Voting time").": ".datetimeformat($votetime)."\n\n"
				._("You can change your vote by voting again on:")."\n"
				.BASE_URL."vote.php?issue=".$issue->id."\n";

			if ( send_mail(Login::$member->mail, $subject, $body) ) {
				success(_("Your vote has been saved and an email receipt has been sent to you."));
			} else {
				warning(_("Your vote has been saved, but the email receipt could not be sent!"));
			}

			DB::transaction_commit();

		} else {
			warning(_("Your vote could not be saved!"));
			DB::transaction_rollback();
		}

		//redirect("proposals.php?ngroup=".$ngroup->id."&filter=voting");
		redirect();
		break;

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Vote"));

form("vote.php?issue=".$issue->id);
?>
<input type="hidden" name="action" value="submit">
<table class="proposals">
<?
Issue::display_proposals_th();
list($proposals, $submitted) = $issue->proposals_list(true);

$sql = "SELECT vote FROM vote WHERE token=".DB::esc($token)." ORDER BY votetime DESC";
$result = DB::query($sql);
// fetch only the first record, which is the latest vote
if ( $row = DB::fetch_assoc($result) ) {
	$vote = unserialize($row['vote']);
} else {
	$vote = array();
	foreach ( $proposals as $proposal ) {
		$vote[$proposal->id]['acceptance'] = -1; // default is abstention
		if (count($proposals) > 1) $vote[$proposal->id]['score'] = 0; // default is 0
	}
}

$issue->display_proposals($proposals, $submitted, count($proposals), false, 0, $vote);
?>
	<tr>
		<td></td>
		<td><input type="submit" value="<?=_("Submit vote")?>"></td>
	</tr>
</table>
<?
form_end();
?>

<div class="clearfix"></div>
<?

html_foot();
