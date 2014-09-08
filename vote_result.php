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

	$vote_result = json_decode($issue->vote);

?>
<table>
<?
	if (count($vote_result->optionOrder) == 1) {
?>
	<tr>
		<th><?=_("Token")?></th>
		<th><?=_("Time")?></th>
		<th><?=_("Acceptance")?></th>
	</tr>
<?
	} else {
?>
	<tr>
		<th rowspan="2"><?=_("Token")?></th>
		<th rowspan="2"><?=_("Time")?></th>
<?
		foreach ($vote_result->optionOrder as $proposal_id) {
?>
		<th colspan="2"><?=_("Proposal")?> <?=$proposal_id?></th>
<?
		}
?>
	</tr>
	<tr>
<?
		foreach ($vote_result->optionOrder as $proposal_id) {
?>
		<th><?=_("Acceptance")?></th>
		<th><?=_("Score")?></th>
<?
		}
?>
	</tr>
<?
	}
	foreach ( $vote_result->votes as $vote ) {
?>
	<tr class="<?=stripes()?>">
		<td><?=$vote->token?></td>
		<td><?=$vote->time?></td>
<?
		foreach ($vote_result->optionOrder as $key => $proposal_id) {
			// acceptance
?>
		<td class="tdc center"><?
			switch ($vote->options[$key][0]) {
			case -1:
				echo _("Abstention");
				break;
			case 0:
				echo _("No");
				break;
			case 1:
				echo _("Yes");
				break;
			default:
				echo "illegal value: ".h($vote->options[$key][0]);
			}
			?></td>
<?
			// score
			if ($vote->options[$key][1] != -2) {
?>
		<td class="center"><?
				switch ($vote->options[$key][1]) {
				case -1:
					echo _("Abstention");
					break;
				default:
					echo h($vote->options[$key][1]);
				}
				?></td>
<?
			}
		}
?>
	</tr>
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
