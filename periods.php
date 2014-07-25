<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";



html_head(_("Periods"));



?>
<table border="0" cellspacing="1">
	<tr>
		<th>Nr</th>
		<th><?=_("Debate")?></th>
		<th><?=_("Preparation")?></th>
		<th><?=_("Voting")?></th>
		<th><?=_("Counting")?></th>
		<th><?=_("Online")?></th>
		<th><?=_("Secret")?></th>
		<th><?=_("Ballots")?></th>
	</tr>
<?


$sql = "SELECT *,
		debate      <= now() AS debate_due,
		preparation <= now() AS preparation_due,
		voting      <= now() AS voting_due,
		counting    <= now() AS counting_due
  FROM periods ORDER BY id";
$result = DB::query($sql);
while ($row = pg_fetch_assoc($result)) {
	$period = new Period($row);
?>
	<tr<?=stripes()?>>
		<td align="right"><?=$period->id?></td>
		<td<?
	if ($row['debate_due']=="t") {
		if ($row['preparation_due']=="t") {
			?> class="over"<?
		} else {
			?> class="current"<?
		}
	}
	?>><?=datetimeformat($period->debate)?></td>
		<td<?
	if ($row['preparation_due']=="t") {
		if ($row['voting_due']=="t") {
			?> class="over"<?
		} else {
			?> class="current"<?
		}
	}
	?>><?=datetimeformat($period->preparation)?></td>
		<td<?
	if ($row['voting_due']=="t") {
		if ($row['counting_due']=="t") {
			?> class="over"<?
		} else {
			?> class="current"<?
		}
	}
	?>><?=datetimeformat($period->voting)?></td>
		<td<?
	if ($row['counting_due']=="t") {
		?> class="over"<?
	}
	?>><?=datetimeformat($period->counting)?></td>
		<td align="center"><?=boolean($period->online)?></td>
		<td align="center"><?=boolean($period->secret)?></td>
		<td><a href="ballots.php?period=<?=$period->id?>"><?=_("Ballots")?></a></td>
	</tr>
<?

}

?>
</table>

<?


html_foot();
