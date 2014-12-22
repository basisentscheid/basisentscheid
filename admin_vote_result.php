<?
/**
 * enter or edit offline voting result
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("admin");

$issue = new Issue(@$_GET['issue']);
if (!$issue->id) {
	error(_("The requested issue does not exist."));
}
if (!$issue->votingmode_offline()) {
	error(_("This issue does not use offline voting."));
}
if ($issue->state != 'preparation' and $issue->state != 'finished') {
	error(_("This issue is not in voting preparation or finished."));
}

if ($action) {
	switch ($action) {
	case "submit":
		$proposals = $issue->proposals(true);
		foreach ( $proposals as $proposal ) {
			if (
				!isset($_POST['yes'][$proposal->id]) or
				!isset($_POST['no'][$proposal->id]) or
				!isset($_POST['abstention'][$proposal->id]) or
				( count($proposals) > 1 and !isset($_POST['score'][$proposal->id]) )
			) {
				warning("Parameter missing.");
				redirect();
			}
		}
		foreach ( $proposals as $proposal ) {
			/** @var Proposal $proposal yes */
			$proposal->yes        = intval($_POST['yes'][$proposal->id]);
			$proposal->no         = intval($_POST['no'][$proposal->id]);
			$proposal->abstention = intval($_POST['abstention'][$proposal->id]);
			if (count($proposals) > 1) $proposal->score = intval($_POST['score'][$proposal->id]);
			$proposal->accepted = ( $proposal->yes > $proposal->no );
			$proposal->update(['yes', 'no', 'abstention', 'score', 'accepted']);
		}
		$issue->finish();
		success(_("The voting result has been saved and the issue finished."));
		redirect();
		break;
	default:
		warning(_("Unknown action"));
		redirect();
	}
}

html_head(_("Edit offline voting result"), true);

// voting result form
form(BN."?issue=".$issue->id, 'class="clear"');
?>
<input type="hidden" name="action" value="submit">
<table class="proposals">
<?
list($proposals, $submitted) = $issue->proposals_list(true);
Issue::display_proposals_th(true, count($proposals) > 1);
$issue->display_proposals($proposals, $submitted, count($proposals), true);
?>
	<tr>
		<td></td>
		<td colspan="<?
if (count($proposals) > 1) echo 4; else echo 3;
?>" class="th"><input type="submit" value="<?=_("Submit voting result")?>"></td>
	</tr>
</table>
<?
form_end();


html_foot();
