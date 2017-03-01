<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$ngroup = Ngroup::get();

if ($action) {
	Login::access_action("member");
	action_required_parameters('area');
	$area = new Area($_POST['area']);
	if (!$area->id) {
		warning("The requested area does not exist!");
		redirect();
	}
	switch ($action) {
	case "subscribe":
		$area->activate_participation();
		redirect();
		break;
	case "unsubscribe":
		$area->deactivate_participation();
		redirect();
		break;
	}
	warning(_("Unknown action"));
	redirect();
}


html_head(_("Subject areas"), true);

$entitled = ( Login::$member and Login::$member->entitled($ngroup->id) );

?>
<div class="bg_white">
<table>
	<tr>
		<th><?=_("Name")?></th>
		<th><?=_("Participants")?></th>
<? if ($entitled) { ?>
		<th><?=_("Participation")?></th>
<? } ?>
	</tr>
<?

if ($entitled) {
	$sql = "SELECT area.*, participant.activated
		FROM area
		LEFT JOIN participant ON area.id = participant.area AND participant.member=".intval(Login::$member->id);
} else {
	$sql = "SELECT area.*
		FROM area";
}
$sql .= "	WHERE ngroup = ".intval($ngroup->id)." ORDER BY area.name, area.id";
$result = DB::query($sql);
while ($row = DB::fetch_assoc($result)) {

?>
	<tr class="<?=stripes()?>">
		<td><?=$row['name']?></td>
		<td class="center"><?=$row['participants']?></td>
<? if ($entitled) { ?>
		<td>
<?
		if ($row['activated']) {
?>
			&#10003; <?=_("last time activated")?>: <?=dateformat($row['activated'])?>
<?
			form(URI::same(), 'class="button"');
?>
<input type="hidden" name="area" value="<?=$row['id']?>">
<input type="hidden" name="action" value="unsubscribe">
<input type="submit" value="<?=_("unsubscribe")?>">
<?
			form_end();
		}
		form(URI::same(), 'class="button"');
?>
<input type="hidden" name="area" value="<?=$row['id']?>">
<input type="hidden" name="action" value="subscribe">
<input type="submit" title="Für die kommenden zwei Abstimmungsperioden anmelden" value="<?=$row['activated']?_("subscribe anew"):_("subscribe")?>">
<?
		form_end();
?>
		</td>
<? } ?>
	</tr>
<?

}

?>
</table>
</div>
<?


html_foot();
