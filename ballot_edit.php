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
		error(_("This ballot does not exist!"));
	}
	$period = new Period($ballot->period);
	if ($period->state=="ballot_preparation") {
		warning(_("Ballot preparation has already begun, so ballots can not be changed anymore."));
		redirect("ballots.php?period=".$period->id);
	}
} else {
	$period = new Period(@$_GET['period']);
	if (!$period) {
		error(_("The requested period does not exist!"));
	}
	if ($period->state=="ballot_preparation") {
		warning(_("Ballot preparation has already begun, so ballots can not be changed anymore."));
		redirect("ballots.php?period=".$period->id);
	}
	if ($period->state=="ballot_assignment") {
		warning(_("Ballot assignment has already begun, so ballot applications are not allowed anymore."));
		redirect("ballots.php?period=".$period->id);
	}
	$ballot = new Ballot;
	$ballot->period = $period->id;
}


if ($action) {
	switch ($action) {

	case "save":
		Login::access_action("member");
		action_required_parameters('name', 'agents', 'opening_hour', 'opening_minute', 'ngroup');
		if ($period->state=="ballot_preparation") {
			warning(_("Ballot preparation has already begun, so ballots can not be changed anymore."));
			redirect("ballots.php?period=".$period->id);
		}
		$ballot->name = trim($_POST['name']);
		$ballot->agents = trim($_POST['agents']);
		$ballot->opening = sprintf("%02d:%02d:00", $_POST['opening_hour'], $_POST['opening_minute']);
		$ballot->ngroup = intval($_POST['ngroup']);
		if (!$ballot->name) {
			warning(_("The ballot name must not be empty."));
			break;
		}
		if (!$ballot->agents) {
			warning(_("The ballot agents must not be empty."));
			break;
		}
		if ($ballot->id) {
			$ballot->update();
		} else {
			if ($period->state=="ballot_assignment") {
				warning(_("Ballot assignment has already begun, so ballot applications are not allowed anymore."));
				redirect("ballots.php?period=".$period->id);
			}
			$ballot->create();
			if (!$ballot->id) {
				warning(_("The ballot could not be created!"));
				redirect();
			}
		}
		$period->select_ballot($ballot, true);
		redirect("ballots.php?period=".$period->id);

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


if ($ballot->id) {
	html_head(sprintf(_("Edit Ballot %d"), $ballot->id));
} else {
	html_head(_("New ballot"));
}

?>
<div id="dbtableadmin" class="edit_ballot">
<?
form(URI::$uri, 'id="dbtableadmin_editform"');
?>
<fieldset>
<div class="input <?=stripes()?>" style="width:100%"><label for="name"><?=_("Name or location of the ballot")?></label><span class="input"><input type="text" name="name" value="<?=h($ballot->name)?>"></span></div>
<div class="input <?=stripes()?>"><label for="ngroup"><?=_("Group of location")?></label><span class="input">
<?
input_select("ngroup", Ngroup::options($period->ngroup()->parent), $ballot->ngroup);
?>
</span></div>
<div class="input <?=stripes()?>"><label for="opening_hour"><?=_("Opening hours")?></label><span class="input">
<select name="opening_hour">
<?
if ($ballot->opening) {
	list($hour, $minute, $second) = explode(":", $ballot->opening);
} else {
	$hour = 0;
	$minute = 0;
}
list($close_hour, $close_minute) = explode(":", BALLOT_CLOSE_TIME);
for ( $h=0; $h<$close_hour; $h++ ) {
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
&mdash; <?=BALLOT_CLOSE_TIME?>
</span></div>
<div class="input <?=stripes()?>"><label for="agents"><?=_("Agents")?></label><span class="input"><input type="text" name="agents" value="<?=h($ballot->agents)?>"></span></div>
<div class="buttons th"><span class="cancel"><a href="periods.php?ngroup=1"><?=_("cancel")?></a></span><span class="input"><input type="submit" value="<?=_("Save")?>"></span></div>
</fieldset>
<input type="hidden" name="action" value="save">
</form>
</div>
<?

html_foot();
