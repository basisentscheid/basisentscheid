<?
/**
 * voting mode result
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$issue = new Issue(@$_GET['issue']);
if (!$issue->id) {
	error("The requested issue does not exist.");
}
if (!$issue->votingmode_determination_finished()) {
	error("Voting mode determination is not finished yet.");
}

html_head(_("Voting mode result"), true);

if ($issue->votingmode_admin) {
?>
<p><?=_("Offline voting has been selected by admins.")?></p>
<?
} else {

?>
<table class="proposals">
<?
	Issue::display_proposals_th(true);
	list($proposals, $submitted) = $issue->proposals_list(true);
	$issue->display_proposals($proposals, $submitted, count($proposals), true);
?>
</table>

<h2><?=_("Votes")?></h2>
<?

	if ($issue->cleared) {
?>
<p><? printf(_("Raw data has been cleared at %s."), datetimeformat($issue->cleared)); ?></p>
<?
	} else {
		// display list of votes
		$sql = "SELECT token, demand, votetime FROM votingmode_token
 		LEFT JOIN votingmode_vote USING (token)
 		WHERE issue=".intval($issue->id)."
 		ORDER BY token ASC, votetime DESC";
		$result = DB::query($sql);
		if (Login::$member) $token = $issue->votingmode_token(); else $token = null;
		Issue::display_votingmode_votes($result, $token);
	}

}

?>
<div class="clearfix"></div>
<?

html_foot();
