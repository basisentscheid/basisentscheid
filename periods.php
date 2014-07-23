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
<table border="0" cellspacing="1" cellpadding="2" class="proposals">
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



$sql = "SELECT * FROM periods ORDER BY id";
$result = DB::query($sql);
while ($row = pg_fetch_assoc($result)) {
	$period = new Period($row);
?>
	<tr<?=stripes()?>>
		<td align="right"><?=$period->id?></td>
		<td><?=datetimeformat($period->debate)?></td>
		<td><?=datetimeformat($period->preparation)?></td>
		<td><?=datetimeformat($period->voting)?></td>
		<td><?=datetimeformat($period->counting)?></td>
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
