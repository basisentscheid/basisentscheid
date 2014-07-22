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
	</tr>
<?



$sql = "SELECT * FROM periods ORDER BY id";
$result = DB::query($sql);
while ($row = pg_fetch_assoc($result)) {
	DB::pg2bool($row['online']);
	DB::pg2bool($row['secret']);
?>
	<tr<?=stripes()?>>
		<td align="right"><?=$row['id']?></td>
		<td><?=timeformat($row['debate'])?></td>
		<td><?=timeformat($row['preparation'])?></td>
		<td><?=timeformat($row['voting'])?></td>
		<td><?=timeformat($row['counting'])?></td>
		<td align="center"><?=boolean($row['online'])?></td>
		<td align="center"><?=boolean($row['secret'])?></td>
	</tr>
<?

}

?>
</table>

<?


html_foot();
