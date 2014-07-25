<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


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
		$area->subscribe();
		redirect();
		break;
	case "unsubscribe":
		$area->unsubscribe();
		redirect();
		break;
	}
	warning("Unknown action");
	redirect();
}


html_head(_("Subject areas"));



?>
<table border="0" cellspacing="1" cellpadding="2">
	<tr>
		<th><?=_("Name")?></th>
		<th><?=_("Participants")?></th>
<? if (Login::$member) { ?>
		<th><?=_("Participation")?></th>
<? } ?>
	</tr>
<?



if (Login::$member) {
	$sql = "SELECT areas.*, participants.activated
		FROM areas
		LEFT JOIN participants ON areas.id = participants.area AND participants.member=".intval(Login::$member->id);
} else {
	$sql = "SELECT areas.*
		FROM areas";
}
$sql .= "	ORDER BY areas.name";
$result = DB::query($sql);
while ($row = pg_fetch_assoc($result)) {

?>
	<tr<?=stripes()?>>
		<td><?=$row['name']?></td>
		<td align="center"><?=$row['participants']?></td>
<? if (Login::$member) { ?>
		<td>
<?
		if ($row['activated']) {
?>
			&#10003; <?=_("last time activated")?>: <?=dateformat($row['activated'])?>
			<form action="<?=BN?>" method="POST" class="button">
			<input type="hidden" name="area" value="<?=$row['id']?>">
			<input type="hidden" name="action" value="unsubscribe">
			<input type="submit" value="<?=_("unsubscribe")?>">
			</form>
<?
		}
?>
			<form action="<?=BN?>" method="POST" class="button">
			<input type="hidden" name="area" value="<?=$row['id']?>">
			<input type="hidden" name="action" value="subscribe">
			<input type="submit" value="<?=$row['activated']?_("subscribe anew"):_("subscribe")?>">
			</form>
		</td>
<? } ?>
	</tr>
<?

}

?>
</table>

<?


html_foot();
