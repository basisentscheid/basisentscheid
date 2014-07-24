<?
/**
 * proposal_edit.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

if (!empty($_GET['id'])) {
	$proposal = new Proposal($_GET['id']);
	if (!$proposal->id) {
		error("This proposal does not exist!");
	}
} else {
	$proposal = new Proposal;
}


if ($action) {

	if ($action!="save") {
		warning("Unknown action");
		redirect();
	}

	action_required_parameters('proponents', 'title', 'content', 'reason');

	$proposal->proponents = trim($_POST['proponents']);
	$proposal->title      = trim($_POST['title']);
	$proposal->content    = trim($_POST['content']);
	$proposal->reason     = trim($_POST['reason']);
	/*if ($proposal->id) {
		$proposal->update();
	} else {*/
	if (!empty($_POST['issue'])) {
		$proposal->issue = intval($_POST['issue']);
		$proposal->create();
	} elseif (!empty($_POST['area'])) {
		$proposal->create($_POST['area']);
	} else {
		warning("Missing parameters");
		redirect();
	}
	if (!$proposal->id) {
		warning("The proposal could not be created!");
		redirect();
	}
	//}

	$proposal->issue()->area()->subscribe();

	redirect("proposal.php?id=".$proposal->id);
}


/*if ($proposal->id) {
	html_head( strtr( _("Edit Proposal %id%"), array('%id%'=>$proposal->id) ) );
	$issue = $proposal->issue();
} else {*/
if (isset($_GET['issue'])) {
	html_head(_("New alternative proposal"));
	$issue = new Issue($_GET['issue']);
	if (!$issue) {
		error("The selected issue does not exist!");
	}
} else {
	html_head(_("New proposal"));
	$issue = false;
}
//}

?>
<form action="<?=BN; if ($proposal->id) { ?>?id=<?=$proposal->id; } ?>" method="post">
<?

if (isset($_GET['issue'])) {
?>
<input type="hidden" name="issue" value="<?=intval($issue->id)?>">
<?
}

?>
<h2><?=_("Proponents")?></h2>
<input type="text" name="proponents" class="proposal" value="<?=h($proposal->proponents)?>"><br>
<h2><?=_("Area")?></h2>
<?
if ($issue) {
	echo $issue->area()->name;
} else {
	$sql = "SELECT * FROM areas ORDER BY name";
	$result = DB::query($sql);
	$options = array();
	while ( $row = pg_fetch_assoc($result) ) {
		$options[$row['id']] = $row['name'];
	}
	input_select("area", $options);
}
?>
<h2><?=_("Title")?></h2>
<input type="text" name="title" class="proposal" value="<?=h($proposal->title)?>"><br>
<h2><?=_("Content")?></h2>
<textarea name="content" class="proposal"><?=h($proposal->content)?></textarea><br>
<h2><?=_("Reason")?></h2>
<textarea name="reason" class="proposal"><?=h($proposal->reason)?></textarea><br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
</form>
<?


html_foot();
