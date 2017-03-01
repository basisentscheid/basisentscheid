<?
/**
 * notification settings
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("member");

if ($action) {
	switch ($action) {
	case "save":
		foreach ( Notification::$default_settings as $interest => $types ) {
			$fields_values = array('member'=>Login::$member->id, 'interest'=>$interest);
			foreach ( $types as $type => $value ) {
				$fields_values[$type] = !empty($_POST['notify'][$interest][$type]);
			}
			DB::insert_or_update("notify", $fields_values, array('member', 'interest'));
		}
		success(_("The email notification settings have been saved."));
		redirect();
	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Member settings"));

display_nav_settings(); ?>
<div class="user_akk">
<?

form(BN, "", "", "settings_notifications", true);
?>
<table class="notify">
	<tr>
		<td></td>
<?
$types = Notification::types();
foreach ($types as $type => $type_title) {
?>
		<th class="type"><?=$type_title?></th>
<?
}
?>
	</tr>
<?
$notify = Login::$member->notification_settings();
foreach (Notification::interests() as $interest => $interest_title) {
?>
	<tr class="<?=stripes()?>">
		<td class="right"><?=$interest_title?></td>
<?
	foreach ($types as $type => $type_title) {
?>
		<td class="center"><input type="checkbox" name="notify[<?=$interest?>][<?=$type?>]" value="1"<?
		if ($notify[$interest][$type]) { ?> checked<? }
		?>></td>
<?
	}
?>
	</tr>
<?
}
?>
	<tr><td colspan="8" class="space"></td></tr>
	<tfoot>
		<tr>
			<td class="left"></td>
			<td colspan="7"><input type="hidden" name="action" value="save"><input type="submit" class="orange_but first" value="<?=_("Save")?>"></td>
		</tr>
	</tfoot>
</table>
<?
form_end(); ?>
</div>

<? html_foot();
