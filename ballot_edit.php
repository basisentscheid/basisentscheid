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

	action_required_parameters('name', 'opening');

	$ballot->name = trim($_POST['name']);
	$ballot->opening = trim($_POST['opening']);
	if ($ballot->id) {
		$ballot->update();
	} else {
		$ballot->create();
		if (!$ballot->id) {
			warning("The ballot could not be created!");
			redirect();
		}
	}

	$ballot->subscribe();

	redirect("ballots.php?period=".$period->id);
}


if ($ballot->id) {
	html_head( strtr( _("Edit Ballot %id%"), array('%id%'=>$ballot->id) ) );
} else {
	html_head(_("New ballot"));
}

?>
<form action="<?=$_SERVER['REQUEST_URI']?>" method="post">
<h2><?=_("Name")?></h2>
<input type="text" name="name" value="<?=h($ballot->name)?>"><br>
<h2><?=_("Opening")?></h2>
<input type="text" name="opening" value="<?=h($ballot->opening)?>"><br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
</form>
<?


html_foot();
