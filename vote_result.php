<?
/**
 * vote result
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$issue = new Issue(@$_GET['issue']);
if (!$issue->id) {
	error(_("The requested issue does not exist."));
}
if ($issue->state != 'finished') {
	error(_("This issue is not finished."));
}

html_head(_("Vote result"));

?>
<table class="proposals">
<?
Issue::display_proposals_th(true);
list($proposals, $submitted) = $issue->proposals_list(true);
$issue->display_proposals($proposals, $submitted, count($proposals), true);
?>
</table>

<?

if ($issue->period()->vvvote) {
?>
<h2><?=_("Votes")?></h2>
<?
	help("votes_vvvote");
?>
<a href="<?=$issue->period()->vvvote_configurl?>&amp;showresult"><?=_("Tokens and votes")?></a>
<?
} else {
?>
<h2><?=_("Votes")?></h2>
<?
	help("votes");
	if ($issue->cleared) {
?>
<p><? printf(_("Raw data has been cleared at %s."), datetimeformat($issue->cleared)); ?></p>
<?
	} else {
		// display list of votes
		$sql = "SELECT token, vote, votetime FROM vote_token
	        LEFT JOIN vote_vote USING (token)
	        WHERE issue=".intval($issue->id)."
	        ORDER BY token ASC, votetime DESC";
		$result = DB::query($sql);
		if (Login::$member) $token = $issue->vote_token(); else $token = null;
		Issue::display_votes($proposals, $result, $token);
	}
}

?>
<div class="clearfix"></div>
<?

html_foot();
