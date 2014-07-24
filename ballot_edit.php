<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("user");


if (!empty($_GET['id'])) {
	$ballot = new Ballot($_GET['id']);
	if (!$ballot->id) {
		error("This ballot does not exist!");
	}
} else {
	$period = new Period(@$_GET['period']);
	if (!$period) {
		error("The requested period does not exist!");
	}
	$ballot = new Ballot;
	$ballot->period = $period->id;
}


if ($action) {

	Login::access_action("user"); // TODO: distinct cases

	if ($action!="save") {
		warning("Unknown action.");
		redirect();
	}

	action_required_parameters('name', 'opening_hour', 'opening_minute');

	$ballot->name = trim($_POST['name']);
	$ballot->agents = trim($_POST['agents']);
	$ballot->opening = sprintf("%02d", $_POST['opening_hour']).":".sprintf("%02d", $_POST['opening_minute']).":00";
	if ($ballot->id) {
		$ballot->update();
	} else {
		$ballot->create();
		if (!$ballot->id) {
			warning("The ballot could not be created!");
			redirect();
		}
	}

	$ballot->select(true);

	redirect("ballots.php?period=".$period->id);
}


if ($ballot->id) {
	html_head( strtr( _("Edit Ballot %id%"), array('%id%'=>$ballot->id) ) );
} else {
	html_head(_("New ballot"));
}

?>
<form action="<?=URI?>" method="post" class="edit_ballot">
<h2><?=_("Name or location of the ballot")?></h2>
<input type="text" name="name" value="<?=h($ballot->name)?>"><br>
<h2><?=_("Agents")?></h2>
<input type="text" name="agents" value="<?=h($ballot->agents)?>"><br>
<h2><?=_("Opening")?></h2>
<select name="opening_hour">
<?
if ($ballot->opening) {
	list($hour, $minute, $second) = explode(":", $ballot->opening);
} else {
	$hour = 0;
	$minute = 0;
}
for ( $h=0; $h<24; $h++ ) {
?>
 <option value="<?=$h?>"<?
	if ($h==$hour) { ?> selected<? }
	?>><?=$h?></option>
<?
}
?>
</select>
:
<select name="opening_minute">
<?
for ( $m=0; $m<60; $m++ ) {
?>
 <option value="<?=$m?>"<?
	if ($m==$minute) { ?> selected<? }
	?>><?=sprintf("%02d", $m)?></option>
<?
}
?>
</select>
<br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
</form>
<?


html_foot();
