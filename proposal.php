<?
/**
 * proposal.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

URI::strip_one_time_params(array('argument_parent', 'argument_edit', 'edit_admission_decision', 'show_drafts', 'edit_proponent', 'become_proponent', 'remove_proponent'));

$proposal = new Proposal(@$_GET['id']);
if (!$proposal->id) {
	error("The requested proposal does not exist!");
}

$issue = $proposal->issue();

if (Login::$member) $edit_limit = strtotime("- ".ARGUMENT_EDIT_INTERVAL);

if ($action) {
	switch ($action) {

	case "submit_proposal":
		Login::access_action("member");
		if ($proposal->state!="draft") {
			warning(_("The proposal has already been submitted."));
			redirect("proposal.php?id=".$proposal->id);
		}
		if (!$proposal->is_proponent(Login::$member)) {
			warning(_("Your are not a proponent of this proposal."));
			redirect();
		}
		if ($proposal->proponents_count() < Proposal::proponents_required_submission) {
			warning(sprintf(_("For submission %d proponents are required."), Proposal::proponents_required_submission));
			redirect();
		}
		$proposal->submit();
		redirect();
		break;
	case "revoke_proposal":
		Login::access_action("member");
		if ($issue->state != "admission" and $issue->state != "debate") {
			warning(_("In the current phase the proposal can not be revoked anymore."));
			redirect();
		}
		if (!$proposal->is_proponent(Login::$member)) {
			warning(_("Your are not a proponent of this proposal."));
			redirect();
		}
		$proposal->cancel("revoked");
		redirect();
		break;

	case "apply_proponent":
		Login::access_action("member");
		action_required_parameters('proponent');
		if (!$proposal->allowed_edit_proponent()) {
			warning(_("Your proponent info can not be changed anymore once voting preparation has started or the proposal has been closed!"));
			redirect();
		}
		$proponent = trim($_POST['proponent']);
		if (!$proponent) {
			warning(_("Your proponent info must be not empty."));
			redirect();
		}
		if (mb_strlen($proponent) > Proposal::proponent_length) {
			$proponent = limitstr($proponent, Proposal::proponent_length);
			warning(sprintf(_("The input has been truncated to the maximum allowed length of %d characters!"), Proposal::proponent_length));
		}
		$proposal->update_proponent($proponent);
		redirect();
		break;
	case "become_proponent":
		Login::access_action("member");
		action_required_parameters('proponent');
		if (!$proposal->allowed_edit_proponent()) {
			warning(_("You can not become proponent anymore once voting preparation has started or the proposal has been closed!"));
			redirect();
		}
		$proponent = trim($_POST['proponent']);
		if (!$proponent) {
			warning(_("Your proponent info must be not empty."));
			redirect();
		}
		if (mb_strlen($proponent) > Proposal::proponent_length) {
			$proponent = limitstr($proponent, Proposal::proponent_length);
			warning(sprintf(_("The input has been truncated to the maximum allowed length of %d characters!"), Proposal::proponent_length));
		}
		$proposal->add_support(false, $proponent);
		notice(_("Your application to become proponent has been submitted to the current proponents to confirm your request."));
		redirect();
		break;
	case "confirm_proponent":
		Login::access_action("member");
		action_required_parameters('member');
		if (!$proposal->allowed_edit_proponent()) {
			warning(_("You can not confirm proponents anymore once voting preparation has started or the proposal has been closed!"));
			redirect();
		}
		if (!$proposal->is_proponent(Login::$member)) {
			warning(_("Your are not a proponent of this proposal."));
			redirect();
		}
		$member = new Member($_POST['member']);
		if (!$member->id) {
			warning("The member does not exist.");
			redirect();
		}
		if (!$proposal->is_proponent($member, false)) {
			warning(_("The to be confirmed member is not applying to become proponent of this proposal."));
			redirect();
		}
		$proposal->confirm_proponent($member);
		redirect();
		break;
	case "confirm_remove_proponent":
		Login::access_action("member");
		if (!$proposal->allowed_edit_proponent()) {
			warning(_("You can not remove yourself from the proponents list once voting preparation has started or the proposal has been closed!"));
			redirect();
		}
		$proposal->remove_proponent(Login::$member);
		redirect();
		break;

	case "add_support":
		Login::access_action("member");
		if (!$proposal->admission()) {
			warning("Support for this proposal can not be added, because it is not in the admission phase!");
			redirect();
		}
		$proposal->add_support(@$_POST['anonymous']==1);
		redirect();
		break;
	case "revoke_support":
		Login::access_action("member");
		if (!$proposal->admission()) {
			warning("Support for this proposal can not be removed, because it is not in the admission phase!");
			redirect();
		}
		if ($proposal->is_proponent(Login::$member, false)) {
			warning("You can not remove your support while you are proponent!");
			redirect();
		}
		$proposal->revoke_support();
		redirect();
		break;
	case "demand_ballot_voting":
		Login::access_action("member");
		if (!$issue->voting_type_determination()) {
			warning("Demand for ballot voting can not be added, because the proposal is not in admission, admitted or debate phase!");
			redirect();
		}
		$issue->demand_ballot_voting(@$_POST['anonymous']==1);
		redirect();
		break;
	case "revoke_demand_for_ballot_voting":
		Login::access_action("member");
		if (!$issue->voting_type_determination()) {
			warning("Demand for ballot voting can not be removed, because the proposal is not in admission, admitted or debate phase!");
			redirect();
		}
		$issue->revoke_demand_for_ballot_voting();
		redirect();
		break;

	case "select_period":
		Login::access_action("admin");
		action_proposal_select_period();
		break;

	case "admission_decision":
		Login::access_action("admin");
		action_required_parameters("admission_decision");
		$proposal->set_admission_decision(trim($_POST['admission_decision']));
		redirect();
		break;

	case "add_argument":
		Login::access_action("member");
		action_required_parameters("title", "content", "parent");
		if (!$proposal->allowed_add_arguments()) {
			warning(_("Adding or rating arguments is allowed in this phase."));
			redirect();
		}
		$argument = new Argument;
		if ($_POST['parent']=="pro" or $_POST['parent']=="contra") {
			$argument->parent = 0;
			$argument->side = $_POST['parent'];
		} else {
			$parent = new Argument($_POST['parent']);
			if (!$parent->id) {
				warning("Parent argument does not exist.");
				redirect();
			}
			$argument->parent = $parent->id;
			$argument->side = $parent->side;
		}
		$argument->proposal = $proposal->id;
		$argument->member = Login::$member->id;

		$argument->title = trim($_POST['title']);
		if (!$argument->title) {
			warning("The title of the argument must be not empty.");
			break;
		}
		if (mb_strlen($argument->title) > Argument::title_length) {
			$argument->title = limitstr($argument->title, Argument::title_length);
			warning(sprintf(_("The title has been truncated to the maximum allowed length of %d characters!"), Argument::title_length));
		}

		$argument->content = trim($_POST['content']);
		if (!$argument->content) {
			warning("The content of the argument must be not empty.");
			break;
		}
		if (mb_strlen($argument->content) > Argument::content_length) {
			$argument->content = limitstr($argument->content, Argument::content_length);
			warning(sprintf(_("The content has been truncated to the maximum allowed length of %d characters!"), Argument::content_length));
		}

		$argument->create();
		redirect(URI::same()."#argument".$argument->id);
		break;

	case "update_argument":
		Login::access_action("member");
		action_required_parameters("title", "content", "id");
		$argument = new Argument($_POST['id']);
		if (!$argument->id) {
			warning("This argument does not exist.");
			redirect();
		}
		if ($argument->member!=Login::$member->id) {
			warning("You are not the author of the argument.");
			redirect();
		}
		if (strtotime($argument->created) < $edit_limit) {
			warning("This argument may not be updated any longer.");
			redirect();
		}
		$argument->title = trim($_POST['title']);
		if (!$argument->title) {
			warning("The title of the argument must be not empty.");
			break;
		}
		$argument->content = trim($_POST['content']);
		if (!$argument->content) {
			warning("The content of the argument must be not empty.");
			break;
		}
		$argument->update(array("title", "content"), "updated=now()");
		redirect(URI::same()."#argument".$argument->id);
		break;

	case "remove_argument":
	case "restore_argument":
		Login::access_action("admin");
		action_required_parameters("id");
		$argument = new Argument($_POST['id']);
		if (!$argument->id) {
			warning("This argument does not exist.");
			redirect();
		}
		$argument->removed = ($action=="remove_argument");
		$argument->update(array("removed"));
		redirect(URI::same()."#argument".$argument->id);
		break;

	case "set_rating":
		Login::access_action("member");
		action_required_parameters("argument", "rating");
		if (!$proposal->allowed_add_arguments()) {
			warning(_("Adding or rating arguments is allowed in this phase."));
			redirect();
		}
		$argument = new Argument($_POST['argument']);
		if (!$argument->id) {
			warning("This argument does not exist.");
			redirect();
		}
		if ($argument->member==Login::$member->id) {
			warning("Rating your own arguments is not allowed.");
			redirect();
		}
		if ($argument->removed) {
			warning("The argument has been removed.");
			redirect();
		}
		$rating = intval($_POST['rating']);
		$argument->set_rating($rating);
		redirect(URI::same()."#argument".$argument->id);
		break;

	case "reset_rating":
		Login::access_action("member");
		action_required_parameters("argument");
		if (!$proposal->allowed_add_arguments()) {
			warning(_("Adding or rating arguments is allowed in this phase."));
			redirect();
		}
		$argument = new Argument($_POST['argument']);
		if (!$argument->id) {
			warning("This argument does not exist.");
			redirect();
		}
		if ($argument->removed) {
			warning("The argument has been removed.");
			redirect();
		}
		$argument->delete_rating();
		redirect(URI::same()."#argument".$argument->id);
		break;

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


list($supporters, $proponents, $is_supporter, $is_proponent) = $proposal->supporters();

if (isset($_GET['draft'])) {
	$draft = new Draft($_GET['draft']);
	if (!$is_proponent) {
		error("You are not a proponent of this proposal!");
	}
	if ($draft->proposal != $proposal->id) {
		error("The requested draft does not exist!");
	}
	html_head(sprintf(_("Proposal %d, draft from %s"), $proposal->id, datetimeformat($draft->created)));
	$content_obj = $draft;
} else {
	html_head(_("Proposal")." ".$proposal->id);
	$content_obj = $proposal;
}


if (isset($_GET['remove_proponent']) and $proposal->is_proponent(Login::$member, false)) {
?>
<div class="messages">
<?
	if (!$proposal->allowed_edit_proponent()) {
		warning(_("You can not remove yourself from the proponents list once voting preparation has started or the proposal has been closed!"));
	} else {
		form(URI::same(), 'class="notice"');
?>
&#10148; <?=_("Do you really want to remove yourself from the proponents of this proposal?")?>
<input type="hidden" name="action" value="confirm_remove_proponent">
<input type="submit" value="<?=_("Yes")?>">
<a href="<?=URI::same()?>"><?=_("No")?></a>
</form>
<?
	}
?>
</div>
<div class="clearfix"></div>
<?
}


?>

<div class="proposal_info">
<? display_proposal_info($proposal, $issue, $proponents, $is_proponent); ?>
</div>

<div class="proposal_content">
<?
if ($proposal->state=="draft" and $is_proponent) {
?>
<div class="add"><a href="proposal_edit.php?id=<?=$proposal->id?>" class="icontextlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>"><?=_("Edit proposal")?></a></div>
<?
}
?>
<h2><?=_("Title")?></h2>
<p class="proposal proposal_title"><?=h($content_obj->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><?=content2html($content_obj->content)?></p>
<h2><?=_("Reason")?></h2>
<p class="proposal"><?=content2html($content_obj->reason)?></p>
</div>

<div class="clearfix"></div>

<?
if ($proposal->state != "draft" and !isset($_GET['draft'])) {
?>
<div>
	<div class="arguments_side arguments_pro">
<?
	if (Login::$member and @$_GET['argument_parent']!="pro" and $proposal->allowed_add_arguments()) {
?>
		<div class="add"><a href="<?=URI::append(array('argument_parent'=>"pro"))?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new pro argument")?></a></div>
<?
	}
?>
		<h2><?=_("Pro")?></h2>
		<? display_arguments("pro", "pro", 0); ?>
	</div>
	<div class="arguments_side arguments_contra">
<?
	if (Login::$member and @$_GET['argument_parent']!="contra" and $proposal->allowed_add_arguments()) {
?>
		<div class="add"><a href="<?=URI::append(array('argument_parent'=>"contra"))?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new contra argument")?></a></div>
<?
	}
?>
		<h2><?=_("Contra")?></h2>
		<? display_arguments("contra", "contra", 0); ?>
	</div>
	<div class="clearfix"></div>
</div>

<?

	// time bar
	$times = array();
	if ($proposal->submitted) {
		$times[] = array($proposal->submitted, _("Submitted"), _("Submitted at %s."));
		if ($proposal->admitted) {
			$times[] = array($proposal->admitted, _("Admitted"), _("Admitted at %s."));
		}
		if ($issue->debate_started) {
			$times[] = array($issue->debate_started, _("Debate"), _("Debate started at %s."));
		} elseif ($issue->period) {
			$times[] = array($issue->period()->debate, _("Debate"), _("Debate starts at %s."));
		}
		if ($issue->preparation_started) {
			$times[] = array($issue->preparation_started, _("Voting preparation"), _("Voting preparation started at %s."));
		} elseif ($issue->period) {
			$times[] = array($issue->period()->preparation, _("Voting preparation"), _("Voting preparation starts at %s."));
		}
		if ($issue->voting_started) {
			$times[] = array($issue->voting_started, _("Voting"), _("Voting started at %s."));
		} elseif ($issue->period) {
			$times[] = array($issue->period()->voting, _("Voting"), _("Voting starts at %s."));
		}
		if ($issue->counting_started) {
			$times[] = array($issue->counting_started, _("Counting"), ("Counting started at %s."));
		} elseif ($issue->period) {
			$times[] = array($issue->period()->counting, _("Counting"), _("Counting starts at %s."));
		}
		if ($issue->cleared) {
			$times[] = array($issue->cleared, _("Cleared"), _("Cleared at %s."));
		} elseif ($issue->clear) {
			$times[] = array($issue->clear, _("Clear"), _("Will be cleared at %s."));
		}
		if ($proposal->cancelled) {
			switch ($proposal->state) {
			case "revoked":
				$times[] = array($proposal->cancelled, _("Revoked"), _("Revoked at %s."));
				break;
			case "done":
				$times[] = array($proposal->cancelled, _("Done"), _("Marked as done otherwise at %s."));
				break;
			case "cancelled":
				$times[] = array($proposal->cancelled, _("Cancelled"), _("Cancelled at %s."));
				break;
			}
		}
		Timebar::display($times);
	}

	display_quorum($proposal, $issue, $supporters, $is_supporter);
}

list($proposals, $submitted) = $issue->proposals_list();
if ($submitted) {
	display_ballot_voting_quorum($issue, $submitted);
}

?>

<div class="issue">
<?
if (Login::$member and $issue->allowed_add_alternative_proposal()) {
?>
<div class="add"><a href="proposal_edit.php?issue=<?=$proposal->issue?>" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add alternative proposal")?></a></div>
<?
}
?>
<h2><?=_("This and alternative proposals")?></h2>
<table class="proposals">
<?
if (Login::$member) $issue->read_ballot_voting_demanded_by_member();
$show_results = in_array($issue->state, array('finished', 'cleared', 'cancelled'));
Issue::display_proposals_th($show_results);
$issue->display_proposals($proposals, $submitted, count($proposals), $show_results, $proposal->id);
?>
</table>
</div>

<?

html_foot();


/**
 *
 * @param object  $proposal
 * @param object  $issue
 * @param array   $proponents
 * @param boolean $is_proponent
 */
function display_proposal_info(Proposal $proposal, Issue $issue, array $proponents, $is_proponent) {
?>
<h2><?=_("Area")?></h2>
<p class="proposal"><?=h($issue->area()->name)?></p>
<?

	$is_any_proponent = false;
	if (Login::$member) {
		foreach ( $proponents as $proponent ) {
			if ($proponent->id==Login::$member->id) {
				$is_any_proponent = true;
				break;
			}
		}
		$allowed_edit_proponent = $proposal->allowed_edit_proponent();
		if ($allowed_edit_proponent and !$is_any_proponent) {
?>
<div class="add"><a href="<?=URI::append(array('become_proponent'=>1))?>" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("become proponent")?></a></div>
<?
		}
	}
?>
<h2><?=_("Proponents")?></h2>
<ul>
<?
	$confirmed_proponents = 0;
	foreach ( $proponents as $proponent ) {
		if ($proponent->proponent_confirmed) $confirmed_proponents++;
		// show unconfirmed proponents only to confirmed proponents and himself
		if (!$is_proponent and !$proponent->proponent_confirmed and (!Login::$member or $proponent->id!=Login::$member->id)) continue;
?>
	<li><?
		if (Login::$member and $proponent->id==Login::$member->id and $allowed_edit_proponent) {
			if (isset($_GET['edit_proponent'])) {
				form(URI::same());
?>
<input type="text" name="proponent" value="<?=h($proponent->proponent_name)?>" maxlength="<?=Proposal::proponent_length?>"><br>
<input type="hidden" name="action" value="apply_proponent">
<input type="submit" value="<?=_("apply changes")?>">
</form>
<?
			} else {
				if ($proponent->proponent_confirmed) {
					echo content2html($proponent->proponent_name);
				} else {
?>
<span class="unconfirmed"><?=content2html($proponent->proponent_name)?></span>
(<?=$proponent->identity()?>)
<?
				}
				?> <a href="<?=URI::append(array('edit_proponent'=>1))?>" class="iconlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>" title="<?=_("edit your proponent name and contact details")?>"></a><a href="<?=URI::append(array('remove_proponent'=>1))?>" class="iconlink"><img src="img/delete.png" width="21" height="16" alt="<?=_("delete")?>" title="<?=_("remove yourself from the list of proponents")?>"></a><?
			}
		} elseif ($proponent->proponent_confirmed) {
			echo content2html($proponent->proponent_name);
		} elseif ($is_proponent and $allowed_edit_proponent) {
			form(URI::same());
?>
<span class="unconfirmed"><?=content2html($proponent->proponent_name)?></span>
(<?=$proponent->identity()?>)
<input type="hidden" name="member" value="<?=$proponent->id?>">
<input type="hidden" name="action" value="confirm_proponent">
<input type="submit" value="<?=_("confirm")?>">
</form>
<?
		} else {
			?><span class="unconfirmed"><?=content2html($proponent->proponent_name)?></span><?
		}
		?></li>
<?
	}
	if (Login::$member and $allowed_edit_proponent and isset($_GET['become_proponent']) and !$is_any_proponent) {
?>
	<li><?
		form(URI::same());
?>
<input type="text" name="proponent" value="<?=h(Login::$member->username())?>" maxlength="<?=Proposal::proponent_length?>"><br>
<div class="explain"><?=_("Enter your name and contact details as you would like to see them in the proposal. To prevent fraud, also the following will be shown to the other proponents:")?> (<?=h(Login::$member->identity())?>)</div>
<input type="hidden" name="action" value="become_proponent">
<input type="submit" value="<?=_("apply to become proponent")?>">
</form>
	</li>
<?
	}
?>
</ul>
<?

	// show drafts only to the proponents
	if (!$is_proponent) return;

?>
<h2><?=_("Drafts")?></h2>
<?
	if ($proposal->state=="draft" or !empty($_GET['show_drafts'])) {
?>
<table>
<?
		$sql = "SELECT * FROM drafts WHERE proposal=".intval($proposal->id)." ORDER BY created DESC";
		$result = DB::query($sql);
		$i = DB::num_rows($result);
		while ( $draft = DB::fetch_object($result, "Draft") ) {
			$author = new Member($draft->author);
?>
<tr class="<?=stripes()?>">
	<td class="right"><?=$i?></td>
	<td><a href="<?=URI::append(array('draft'=>$draft->id))?>"><?=datetimeformat($draft->created)?></a></td>
	<td><?=$author->username()?></td>
</tr>
<?
			$i--;
		}
?>
</table>
<?
	} else {
?>
<a href="<?=URI::append(array('show_drafts'=>1))?>"><?=_("Drafts")?></a>
<?
	}

?>
<h2><?=_("Actions")?></h2>
<?
	if ($proposal->state=="draft" and $confirmed_proponents >= Proposal::proponents_required_submission) {
		form(URI::same());
?>
<input type="hidden" name="action" value="submit_proposal">
<input type="submit" value="<?=_("submit proposal")?>">
</form>
<?
	}
	if ($issue->state=="admission" or $issue->state=="debate") {
		form(URI::same());
?>
<input type="hidden" name="action" value="revoke_proposal">
<input type="submit" value="<?=_("revoke proposal")?>">
</form>
<?
	}

}


/**
 * list the sub-arguments for one parent-argument
 *
 * @param string  $side   "pro" or "contra"
 * @param mixed   $parent ID of parent argument or "pro" or "contra"
 * @param integer $level
 */
function display_arguments($side, $parent, $level) {
	global $proposal, $edit_limit;

	$sql = "SELECT arguments.*";
	if (Login::$member) {
		$sql .= ", ratings.score
			FROM arguments
			LEFT JOIN ratings ON ratings.argument = arguments.id AND ratings.member = ".intval(Login::$member->id);
	} else {
		$sql .= "
			FROM arguments";
	}
	// intval($parent) gives parent=0 for "pro" and "contra"
	$sql .= "	WHERE arguments.proposal=".intval($proposal->id)."
			AND side=".DB::esc($side)."
			AND parent=".intval($parent)."
		ORDER BY removed, rating DESC, created";
	$result = DB::query($sql);
	$num_rows = DB::num_rows($result);
	if (!$num_rows and @$_GET['argument_parent']!=$parent) return;

?>
<ul>
<?

	$i = 0;
	while ( $argument = DB::fetch_object($result, "Argument") ) {
		$i++;

		// open remaining arguments
		if (
			(!defined('ARGUMENTS_LIMIT_'.$level) or $i > constant('ARGUMENTS_LIMIT_'.$level)) and
			(!isset($_GET['open']) or !is_array($_GET['open']) or !in_array($parent, $_GET['open']))
		) {
			if (isset($_GET['open']) and is_array($_GET['open'])) {
				$open = $_GET['open'];
			} else {
				$open = array();
			}
			$open[] = $parent;
			$open = array_unique($open);
?>
		<a href="<?=URI::append(array('open'=>$open))?>"><?
			$remaining = $num_rows - $i + 1;
			if ($remaining==1) {
				echo _("show remaining argument");
			} else {
				printf(_("show remaining %d arguments"), $remaining);
			}
			?></a>
<?
			break; // break while loop
		}

		if (Login::$member) DB::to_bool($argument->positive);
		$member = new Member($argument->member);
?>
	<li>
<?

		// author and form
		if (
			Login::$member and $member->id==Login::$member->id and
			@$_GET['argument_edit']==$argument->id and
			!$argument->removed
		) {
?>
		<div class="author"><?=$member->username()?> <?=datetimeformat($argument->created)?></div>
<?
			if (strtotime($argument->created) > $edit_limit) {
?>
		<div class="time"><?printf(_("This argument can be updated until %s."), datetimeformat($argument->created." + ".ARGUMENT_EDIT_INTERVAL))?></div>
<?
				form(URI::append(array('argument_edit'=>$argument->id)), 'class="argument"');
?>
<a name="argument<?=$argument->id?>"></a>
<input name="title" type="text" maxlength="<?=Argument::title_length?>" value="<?=h(!empty($_POST['title'])?$_POST['title']:$argument->title)?>"><br>
<textarea name="content" rows="5" maxlength="<?=Argument::content_length?>"><?=h(!empty($_POST['content'])?$_POST['content']:$argument->content)?></textarea><br>
<input type="hidden" name="action" value="update_argument">
<input type="hidden" name="id" value="<?=$argument->id?>">
<input type="submit" value="<?=_("apply changes")?>">
</form>
<?
				$display_content = false;
			} else {
?>
		<div class="time"><?=_("This argument may not be updated any longer!")?></div>
<?
				$display_content = true;
			}
		} else {
?>
		<div class="author<?=$argument->removed?' removed':''?>"><?
			if (
				Login::$member and $member->id==Login::$member->id and
				strtotime($argument->created) > $edit_limit and
				!$argument->removed and
				$proposal->allowed_add_arguments()
			) {
				?><a href="<?=URI::append(array('argument_edit'=>$argument->id))?>#argument<?=$argument->id?>" class="iconlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>></a> <?
			}
			?><?=$member->username()?> <?=datetimeformat($argument->created)?></div>
<?
			$display_content = true;
		}

		// title and content
		if ($display_content) {
			if ($argument->updated) {
?>
		<div class="author<?=$argument->removed?' removed':''?>"><?=_("updated")?> <?=datetimeformat($argument->updated)?></div>
<?
			}
			if ($argument->removed) {
?>
		<h3 class="removed"><a class="anchor" name="argument<?=$argument->id?>"></a>&mdash; <?=_("argument removed by admin")?> &mdash;</h3>
<?
			} else {
?>
		<h3><a class="anchor" name="argument<?=$argument->id?>"></a><?=h($argument->title)?></h3>
		<p><?=content2html($argument->content)?></p>
<?
			}
		}

		// reply
		if (
			Login::$member and
			@$_GET['argument_parent']!=$argument->id and
			!$argument->removed and
			$proposal->allowed_add_arguments()
		) {
?>
		<div class="reply"><a href="<?=URI::append(array('argument_parent'=>$argument->id))?>#form" class="iconlink"><img src="img/reply.png" width="16" height="16" <?alt(_("reply"))?>></a></div>
<?
		}

		// rating and remove/restore
		if ($argument->rating) {
			?><span class="rating">+<?=$argument->rating?></span> <?
		}
		if (Login::$member) {
			if (
				// don't allow to rate ones own arguments
				$argument->member!=Login::$member->id and
				// don't allow to rate removed arguments
				!$argument->removed and
				$proposal->allowed_add_arguments()
			) {
				$uri = URI::same();
				if ($argument->score) {
					form($uri, 'class="button rating reset"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
<input type="hidden" name="action" value="reset_rating">
<input type="submit" value="0">
</form>
<?
				}
				for ($score=1; $score <= Argument::rating_score_max; $score++) {
					form($uri, 'class="button rating'.($score <= $argument->score?' selected':'').'"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
<input type="hidden" name="action" value="set_rating">
<input type="hidden" name="rating" value="<?=$score?>">
<input type="submit" value="+<?=$score?>">
</form>
<?
				}

			}
		} elseif (Login::$admin) {
			form(URI::same(), 'class="button"');
?>
<input type="hidden" name="id" value="<?=$argument->id?>">
<?
			if ($argument->removed) {
?>
<input type="hidden" name="action" value="restore_argument">
<input type="submit" value="<?=_("restore")?>">
<?
			} else {
?>
<input type="hidden" name="action" value="remove_argument">
<input type="submit" value="<?=_("remove")?>">
<?
			}
?>
</form>
<?
		}

?>
		<div class="clearfix"></div>
<?
		display_arguments($side, $argument->id, $level+1);
?>
	</li>
<?
	}

	if (Login::$member and @$_GET['argument_parent']==$parent and $proposal->allowed_add_arguments()) {
?>
	<li>
<?
		form(URI::append(array('argument_parent'=>$parent)), 'class="argument"');
?>
<a name="form"></a>
<div class="time"><?=_("New argument")?></div>
<input name="title" type="text" maxlength="<?=Argument::title_length?>" value="<?=h(@$_POST['title'])?>"><br>
<textarea name="content" rows="5" maxlength="<?=Argument::content_length?>"><?=h(@$_POST['content'])?></textarea><br>
<input type="hidden" name="action" value="add_argument">
<input type="hidden" name="parent" value="<?=$parent?>">
<input type="submit" value="<?=_("save")?>">
</form>
	</li>
<?
	}

?>
</ul>
<?
}


/**
 * display supporters and ballot voting demanders
 *
 * @param object  $proposal
 * @param object  $issue
 * @param array   $supporters
 * @param mixed   $is_supporter
 */
function display_quorum(Proposal $proposal, Issue $issue, array $supporters, $is_supporter) {
?>
<div class="quorum">
<div class="bargraph_container">
<?
	$proposal->bargraph_quorum();
?>
</div>
<?

	if (Login::$member or Login::$admin) {
?>
<b><?=_("Supporters")?>:</b> <?=join(", ", $supporters);
		if (Login::$member and $proposal->admission()) {
?>
<br clear="both">
<?
			if ($is_supporter) {
				form(URI::same(), 'style="background-color:green; display:inline-block"');
?>
&#10003; <?
				if ($is_supporter==="anonymous") {
					echo _("You support this proposal anonymously.");
				} else {
					echo _("You support this proposal.");
				}
?>
<input type="hidden" name="action" value="revoke_support">
<input type="submit" value="<?=_("Revoke your support for this proposal")?>">
</form>
<?
			} else {
				form(URI::same(), 'style="display:inline-block"');
?>
<input type="hidden" name="action" value="add_support">
<input type="checkbox" name="anonymous" value="1"><?=_("anonymous")."\n"?>
<input type="submit" value="<?=_("Support this proposal")?>">
</form>
<?
			}
		}
	} else {
?>
<b><?=_("Supporters")?></b>
<?
	}

?>
<div class="clearfix"></div>
<?

	// admission by decision
	if (Login::$admin and !empty($_GET['edit_admission_decision'])) {
		if ($proposal->admission_decision!==null) {
			form(URI::same()."#admission_decision", 'class="admission_decision"');
?>
<a name="admission_decision" class="anchor"></a>
<b><?=_("Admitted by decision")?>:</b><br>
<input type="text" name="admission_decision" value="<?=h($proposal->admission_decision)?>"><br>
<input type="submit" value="<?=_("apply changes")?>">
<input type="hidden" name="action" value="admission_decision">
</form>
<?
		} elseif ($proposal->state=="submitted") {
			form(URI::same()."#admission_decision", 'class="admission_decision"');
?>
<a name="admission_decision" class="anchor"></a>
<b><?=_("Admit proposal due to a decision")?>:</b><br>
<input type="text" name="admission_decision"><br>
<input type="submit" value="<?=_("admit proposal")?>">
<input type="hidden" name="action" value="admission_decision">
</form>
<?
		}
	} elseif (Login::$admin and $proposal->admission()) {
?>
<div class="admission_decision">
<a href="<?=URI::append(array('edit_admission_decision'=>1))?>#admission_decision"><?=_("Admit proposal due to a decision")?></a>
</div>
<?
	} elseif ($proposal->admission_decision!==null) {
?>
<div class="admission_decision">
<a name="admission_decision" class="anchor"></a>
<b><?=_("Admitted by decision")?>:</b>
<?=content2html($proposal->admission_decision)?>
<?
		if (Login::$admin) {
?>
&nbsp;
<a href="<?=URI::append(array('edit_admission_decision'=>1))?>#admission_decision" class="iconlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>></a>
<?
		}
?>
</div>
<?
	}

?>
</div>
<?
}


/**
 * display ballot voting demanders
 *
 * @param object  $issue
 * @param boolean $submitted if at least one proposal is submitted
 */
function display_ballot_voting_quorum(Issue $issue, $submitted) {
?>
<div class="quorum">
<div class="bargraph_container">
<?
	$issue->bargraph_ballot_voting();
?>
</div>
<?
	if (Login::$member or Login::$admin) {
?>
<b><?=_("Ballot voting demanders")?>:</b> <?
		$demanded_by_member = $issue->show_ballot_voting_demanders();
		if (Login::$member and $issue->voting_type_determination($submitted)) {
?>
<br clear="both">
<?
			if ($demanded_by_member) {
				form(URI::same(), 'style="background-color:red; display:inline-block"');
?>
&#10003; <?
				if ($demanded_by_member==="anonymous") {
					echo _("You demand ballot voting for this issue anonymously.");
				} else {
					echo _("You demand ballot voting for this issue.");
				}
?>
<input type="hidden" name="action" value="revoke_demand_for_ballot_voting">
<input type="submit" value="<?=_("Revoke your demand for ballot voting")?>">
</form>
<?
			} else {
				form(URI::same(), 'style="display:inline-block"');
?>
<input type="hidden" name="action" value="demand_ballot_voting">
<input type="checkbox" name="anonymous" value="1"><?=_("anonymous")."\n"?>
<input type="submit" value="<?=_("Demand ballot voting for this issue")?>">
</form>
<?
			}
		}
	} else {
?>
<b><?=_("Ballot voting demanders")?></b>
<?
	}
?>
<div class="clearfix"></div>
</div>
<?
}
