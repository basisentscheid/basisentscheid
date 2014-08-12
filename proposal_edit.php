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
	if ($proposal->state!="draft") {
		warning(_("The proposal has already been submitted and may not be changed anymore."));
		redirect("proposal.php?id=".$proposal->id);
	}
	if (!$proposal->is_proponent(Login::$member)) {
		warning(_("Your are not a proponent of this proposal."));
		redirect("proposal.php?id=".$proposal->id);
	}
} else {
	$proposal = new Proposal;
}


if ($action) {

	if ($action!="save") {
		warning(_("Unknown action"));
		redirect();
	}

	action_required_parameters('title', 'content', 'reason');

	$proposal->title   = trim($_POST['title']);
	if (mb_strlen($proposal->title) > Proposal::title_length) {
		$proposal->title = limitstr($proposal->title, Proposal::title_length);
		warning(sprintf(_("The title has been truncated to the maximum allowed length of %d characters!"), Proposal::title_length));
	}

	$proposal->content = trim($_POST['content']);
	if (mb_strlen($proposal->content) > Proposal::content_length) {
		$proposal->content = limitstr($proposal->content, Proposal::content_length);
		warning(sprintf(_("The content has been truncated to the maximum allowed length of %d characters!"), Proposal::content_length));
	}

	$proposal->reason  = trim($_POST['reason']);
	if (mb_strlen($proposal->reason) > Proposal::reason_length) {
		$proposal->reason = limitstr($proposal->reason, Proposal::reason_length);
		warning(sprintf(_("The reason has been truncated to the maximum allowed length of %d characters!"), Proposal::reason_length));
	}

	if ($proposal->id) {
		// update existing proposal
		$proposal->create_draft();
		$proposal->update();
	} else {
		// add proposal
		action_required_parameters('proponent');
		$proponent = trim($_POST['proponent']);
		if (mb_strlen($proponent) > Proposal::proponent_length) {
			$proponent = limitstr($proponent, Proposal::proponent_length);
			warning(sprintf(_("The input has been truncated to the maximum allowed length of %d characters!"), Proposal::proponent_length));
		}
		if (!empty($_POST['issue'])) {
			// add alternative proposal
			$issue = new Issue($_POST['issue']);
			if (!$issue->id) {
				error(_("The supplied issue does not exist."));
			}
			if (!$issue->allowed_add_alternative_proposal()) {
				warning(_("In this phase it is not allowed to create an alternative proposal. Thus a new proposal has been created instead."));
				$proposal->create($proponent, $issue->area);
				redirect();
			}
			$proposal->issue = $issue->id;
			$proposal->create($proponent);
		} elseif (!empty($_POST['area'])) {
			// add new proposal
			$proposal->create($proponent, $_POST['area']);
		} else {
			warning("Missing parameters");
			redirect();
		}
		if (!$proposal->id) {
			warning("The proposal could not be created!");
			redirect();
		}
	}

	$proposal->issue()->area()->activate_participation();

	redirect("proposal.php?id=".$proposal->id);
}


if ($proposal->id) {
	html_head(sprintf(_("Edit Proposal %d"), $proposal->id));
	$issue = $proposal->issue();
} else {
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
}

form(BN.($proposal->id?"?id=".$proposal->id:""), 'class="proposal"');

if (isset($_GET['issue'])) {
?>
<input type="hidden" name="issue" value="<?=intval($issue->id)?>">
<?
}

?>
<h2><?=_("Area")?></h2>
<?
if ($issue) {
	echo $issue->area()->name;
} else {
	$sql = "SELECT id, name FROM areas ORDER BY name";
	$result = DB::query($sql);
	$options = array();
	while ( $row = DB::fetch_assoc($result) ) {
		$options[$row['id']] = $row['name'];
	}
	input_select("area", $options);
}

if (!$proposal->id) {
?>
<h2><?=_("Proponent")?> <span class="explain"><?=_("Enter your name and contact details as you would like to see them in the proposal.")?></span></h2>
<input type="text" name="proponent" value="<?=h(Login::$member->username())?>" maxlength="<?=Proposal::proponent_length?>"><br>
<?
}

?>
<h2><?=_("Title")?></h2>
<input type="text" name="title" value="<?=h($proposal->title)?>" maxlength="<?=Proposal::title_length?>"><br>
<h2><?=_("Content")?></h2>
<textarea name="content" maxlength="<?=Proposal::content_length?>"><?=h($proposal->content)?></textarea><br>
<h2><?=_("Reason")?></h2>
<textarea name="reason" maxlength="<?=Proposal::reason_length?>"><?=h($proposal->reason)?></textarea><br>
<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
</form>
<?


html_foot();
