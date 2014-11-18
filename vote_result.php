<?
/**
 * vote result
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$issue = new Issue(@$_GET['issue']);
if (!$issue->id) {
	error("The requested issue does not exist.");
}
if ($issue->state != 'finished' and $issue->state != 'cleared') {
	error("This issue is not finished.");
}

html_head(_("Vote result"));


?>
<table class="proposals">
<?
Issue::display_proposals_th(true);
list($proposals, $submitted) = $issue->proposals_list();
$issue->display_proposals($proposals, $submitted, count($proposals), true);
?>
</table>

<h2><?=_("Votes")?></h2>
<?

if ($issue->state == 'cleared') {
?>
<p><? printf(_("Raw data has been cleared at %s."), datetimeformat($issue->cleared)); ?></p>
<?
} else {
?>
<table class="votes">
<?
	if (count($proposals) == 1) {
?>
<tr><th><?=_("Token")?></th><th><?=_("Time")?></th><th><?=_("Acceptance")?></th></tr>
<?
	} else {
?>
<tr><th rowspan="2"><?=_("Token")?></th><th rowspan="2"><?=_("Time")?></th><?
		foreach ($proposals as $proposal) {
			?><th colspan="2"><?=_("Proposal")?> <?=$proposal->id?></th><?
		}
		?></tr>
<tr><?
		foreach ($proposals as $proposal) {
			?><th><?=_("Acceptance")?></th><th><?=_("Score")?></th><?
		}
		?></tr>
<?
	}
	$sql = "SELECT token, vote, votetime FROM vote
 		JOIN vote_tokens ON vote.token = vote_tokens.token
 		WHERE vote_tokens.issue=".intval($issue->id)."
 		ORDER BY token, votetime";
	$result = DB::query($sql);
	$last_votetime = null;
	while ( $row = DB::fetch_assoc($result) ) {
		if ($row['votetime']==$last_votetime) {
			$class_overridden = " overridden";
		} else {
			$class_overridden = "";
		}
		$last_votetime = $row['votetime'];
?>
<tr class="<?=stripes()?>"><td><?=$row['token']?></td><td><?=date(DATETIMEYEAR_FORMAT, $row['votetime'])?></td><?
		$vote = unserialize($row['vote']);
		foreach ($proposals as $proposal) {
			// acceptance
			?><td class="tdc<?=$class_overridden?>"><?=acceptance($vote[$proposal->id]['acceptance'])?></td><?
			// score
			if (isset($vote[$proposal->id]['score'])) {
				?><td class="tdc<?=$class_overridden?>"><?
				switch ($vote[$proposal->id]['score']) {
				case -1:
					echo _("Abstention");
					break;
				default:
					echo h($vote[$proposal->id]['score']);
				}
				?></td><?
			}
		}
		?></tr>
<?
	}
?>
</table>
<?

}

?>
<div class="clearfix"></div>
<?

html_foot();
