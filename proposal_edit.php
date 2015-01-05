<?
/**
 * create or edit a proposal
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

if (!empty($_GET['id'])) {
	Login::access("user");
	$proposal = new Proposal($_GET['id']);
	if (!$proposal->id) {
		error(_("This proposal does not exist!"));
	}
	if ($proposal->allowed_edit_content()) {
		$edit_content = true;
	} elseif ($proposal->allowed_edit_reason_only()) {
		$edit_content = false;
	} else {
		warning(_("This proposal may not be changed anymore."));
		redirect("proposal.php?id=".$proposal->id);
	}
	if (!Login::$admin and !$proposal->is_proponent(Login::$member)) {
		warning(_("Your are not a proponent of this proposal."));
		redirect("proposal.php?id=".$proposal->id);
	}
	$issue = $proposal->issue();
	$ngroup_id = $issue->area()->ngroup;
} elseif (!empty($_GET['issue'])) {
	Login::access("member");
	$issue = new Issue($_GET['issue']);
	if (!$issue) {
		error("The selected issue does not exist!");
	}
	$proposal = new Proposal;
	$edit_content = true;
	$ngroup_id = $issue->area()->ngroup;
} else {
	Login::access("member");
	$proposal = new Proposal;
	$edit_content = true;
	$issue = false;
	$ngroup = Ngroup::get();
	$ngroup_id = $ngroup->id;
}

Login::access(["entitled", "admin"], $ngroup_id);

if ($action) {
	switch ($action) {
	case "save":

		action_required_parameters('reason');

		$proposal->reason = trim($_POST['reason']);
		if (mb_strlen($proposal->reason) > Proposal::reason_length) {
			$proposal->reason = limitstr($proposal->reason, Proposal::reason_length);
			warning(sprintf(_("The reason has been truncated to the maximum allowed length of %d characters!"), Proposal::reason_length));
		}

		if (isset($_POST['content'])) {
			$proposal->content = trim($_POST['content']);
			if (mb_strlen($proposal->content) > Proposal::content_length) {
				$proposal->content = limitstr($proposal->content, Proposal::content_length);
				warning(sprintf(_("The content has been truncated to the maximum allowed length of %d characters!"), Proposal::content_length));
			}
		}

		if (isset($_POST['title'])) {
			$proposal->title = trim($_POST['title']);
			if (!$proposal->title) {
				warning(_("The title must be not empty."));
				break;
			}
			if (mb_strlen($proposal->title) > Proposal::title_length) {
				$proposal->title = limitstr($proposal->title, Proposal::title_length);
				warning(sprintf(_("The title has been truncated to the maximum allowed length of %d characters!"), Proposal::title_length));
			}
		}

		if (!$edit_content and (isset($_POST['title']) or isset($_POST['content']))) {
			warning(_("The proposal title and content may not be changed anymore, so sadly we can only save the reason."));
			break;
		}

		if ($proposal->id) {
			// update existing proposal
			$proposal->new_draft();
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
				$issue_post = new Issue($_POST['issue']);
				if (!$issue_post->id) {
					error(_("The supplied issue does not exist."));
				}
				if (!$issue_post->allowed_add_alternative_proposal()) {
					warning(_("In this phase it is not allowed to create an alternative proposal. Thus a new proposal has been created instead."));
					$proposal->create($proponent, $issue_post->area);
					redirect();
				}
				$proposal->issue = $issue_post->id;
				$proposal->create($proponent);
			} elseif (isset($_POST['area'])) {
				// add new proposal
				if (!$_POST['area']) {
					warning(_("Please select a subject area!"));
					break;
				}
				$proposal->create($proponent, $_POST['area']);
			} else {
				error("Missing parameters");
			}
			if (!$proposal->id) {
				warning(_("The proposal could not be created!"));
				break;
			}
		}

		// don't activate participation for admins
		if (Login::$member) $proposal->issue()->area()->activate_participation();

		redirect("proposal.php?id=".$proposal->id);
		break;

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


if ($proposal->id) {
	html_head(sprintf(_("Edit Proposal %d"), $proposal->id));
} elseif ($issue) {
	html_head(_("New alternative proposal"), true);
} else {
	html_head(_("New proposal"), true);
}

list($supporters, $proponents, $is_supporter, $is_proponent) = $proposal->supporters();

form(URI::same(), 'class="proposal"', "proposal", true);

if (isset($_GET['issue'])) {
?>
<input type="hidden" name="issue" value="<?=intval($issue->id)?>">
<?
}

?>
<section class="proposal_info">
<? display_proposal_info($proposal, $issue, $proponents); ?>
</section>

<section class="proposal_content">
<?

if ($edit_content) {
?>
<h2><?=_("Title")?></h2>
<input type="text" name="title" value="<?=h($proposal->title)?>" maxlength="<?=Proposal::title_length?>" required><br>
<h2><?=_("Content")?></h2>
<textarea name="content" maxlength="<?=Proposal::content_length?>"><?=h($proposal->content)?></textarea><br>
<?
} else {
?>
<h2><?=_("Title")?></h2>
<p class="proposal proposal_title"><?=h($proposal->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><?=content2html($proposal->content)?></p>
<?
}

?>
<h2><?=_("Reason")?></h2>
<textarea name="reason" maxlength="<?=Proposal::reason_length?>"><?=h($proposal->reason)?></textarea><br>
</section>

<input type="hidden" name="action" value="save">
<input type="submit" value="<?=_("Save")?>">
<?
form_end();
?>

<div class="clearfix"></div>
<?

html_foot();


/**
 * display the right column with area and proponents
 *
 * @param Proposal $proposal
 * @param object  $issue      Issue or empty
 * @param array   $proponents
 */
function display_proposal_info(Proposal $proposal, $issue, array $proponents) {
	global $ngroup_id;
?>
<h2><?=_("Area")?></h2>
<div class="proposal">
<?
	if ($issue) {
		echo h($issue->area()->name);
	} else {
		$sql = "SELECT id, name FROM area WHERE ngroup = ".intval($ngroup_id)." ORDER BY name";
		$result = DB::query($sql);
		$options = array(0 => _("&mdash; please select &mdash;"));
		while ( $row = DB::fetch_assoc($result) ) {
			$options[$row['id']] = $row['name'];
		}
		if (!$options) warning(_("There are no areas in this group. You can not add a proposal without an area."));
		input_select("area", $options, @$_POST['area']);
	}
?>
</div>
<h2><?=_("Proponents")?></h2>
<ul>
<?
	if ($proposal->id) {
		foreach ( $proponents as $proponent ) {
?>
	<li><?
			if ($proponent->proponent_confirmed) {
				echo content2html($proponent->proponent_name);
			} else {
				?><span class="unconfirmed"><?=content2html($proponent->proponent_name)?></span><?
			}
			?></li>
<?
		}
	} else {
?>
	<li>
		<div class="form">
			<input type="text" name="proponent" value="<?
		if (!empty($_POST['proponent'])) echo h($_POST['proponent']); else echo h(Login::$member->username());
		?>" maxlength="<?=Proposal::proponent_length?>" required><br>
			<div class="explain"><?=_("Enter your name and contact details as you would like to see them in the proposal.")?></div>
		</div>
	</li>
<?
	}
?>
</ul>
<?

	if (!$proposal->id) return;

	$proposal->display_drafts_without_form($proponents);

}
