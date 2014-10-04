<?
/**
 * vote
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

$issue = new Issue(@$_GET['issue']);
if (!$issue->id) {
	error("The requested issue does not exist.");
}
if ($issue->state == 'finished' or $issue->state == 'cleared') {
	error("The voting on this issue is already closed.");
} elseif ($issue->state != 'voting') {
	error("The issue is not in voting state.");
}

$ngroup = $issue->area()->ngroup();


if ($action) {
	switch ($action) {

	case "submit":
		Login::access_action("entitled", $ngroup->id);

		$vote = serialize($_POST['vote']);

		DB::transaction_start();
		DB::delete("vote", "member=".intval(Login::$member->id)." AND issue=".intval($issue->id));
		$sql = "INSERT INTO vote (member, issue, vote) VALUES (".intval(Login::$member->id).", ".intval($issue->id).", ".DB::esc($vote).") RETURNING votetime";
		if ( $result = DB::query($sql) ) {
			list($votetime) = pg_fetch_row($result);

			$subject = _("Vote receipt");

			$body = _("Group").": ".$ngroup->name."\n\n";

			$body = sprintf(_("Vote receipt for your vote on issue %d:"), $issue->id)."\n\n";
			foreach ( $_POST['vote'] as $proposal_id => $vote_proposal ) {
				$proposal = new Proposal($proposal_id);
				$body .= mb_wordwrap(_("Proposal")." ".$proposal_id.": ".$proposal->title)."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n"
					._("Acceptance").": ".acceptance($vote_proposal['acceptance']);
				if (isset($vote_proposal['score'])) $body .= ", "._("Score").": ".$vote_proposal['score'];
				$body .= "\n\n";
			}
			$body .= _("Voting time").": ".datetimeformat($votetime)."\n\n"
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

		redirect("proposals.php?ngroup=".$ngroup->id."&filter=voting");
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
$issue->display_proposals($proposals, $submitted, count($proposals));
?>
	<tfoot>
		<tr>
			<td colspan="2" class="right"><input type="submit"></td>
		</tr>
	</tfoot>
</table>
<?
form_end();
?>

<div class="clearfix"></div>
<?

html_foot();
