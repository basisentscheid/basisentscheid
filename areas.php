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


html_head(_("Subject areas"));

$help = <<<HELP
Hier kannst du dich zur Teilnahme an den verschiedenen Themenbereichen an- und abmelden. Durch Einreichung, Unterstützung oder Abstimmung eines Antrags meldest du dich automatisch beim entsprechenden Themenbereich an. Die Anmeldung verfällt nach dem zweiten Stichtag nach der letzten Anmeldung.

Die Anzahl der Teilnehmer in einem Themenbereich bildet die Grundlage für das Quorum zur Zulassung eines Antrags und für das Quorum zur Urnenabstimmung.
HELP;
help($help);

?>
<table>
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
$sql .= "	ORDER BY areas.name, areas.id";
$result = DB::query($sql);
while ($row = DB::fetch_assoc($result)) {

?>
	<tr class="<?=stripes()?>">
		<td><?=$row['name']?></td>
		<td class="center"><?=$row['participants']?></td>
<? if (Login::$member) { ?>
		<td>
<?
		if ($row['activated']) {
?>
			&#10003; <?=_("last time activated")?>: <?=dateformat($row['activated'])?>
<?
			form(BN, 'class="button"');
?>
<input type="hidden" name="area" value="<?=$row['id']?>">
<input type="hidden" name="action" value="unsubscribe">
<input type="submit" value="<?=_("unsubscribe")?>">
</form>
<?
		}
		form(BN, 'class="button"');
?>
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
