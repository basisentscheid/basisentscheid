<?
/**
 * proposal details
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

URI::strip_one_time_params(array(
		'parent',
		'comment_edit',
		'openhl',
		'edit_admission_decision',
		'show_drafts',
		'edit_proponent',
		'become_proponent',
		'remove_proponent'
	));

$proposal = new Proposal(@$_GET['id']);
if (!$proposal->id) {
	error("The requested proposal does not exist!");
}

$issue = $proposal->issue();

$ngroup = $issue->area()->ngroup;
$_SESSION['ngroup'] = $ngroup;

if ($action) {
	switch ($action) {

	case "submit_proposal":
		Login::access_action("entitled", $ngroup);
		if (!$proposal->is_proponent(Login::$member)) {
			warning(_("Your are not a proponent of this proposal."));
			redirect();
		}
		$proposal->submit();
		redirect();
		break;

	case "apply_proponent":
		Login::access_action("entitled", $ngroup);
		action_required_parameters('proponent');
		$proposal->update_proponent(trim($_POST['proponent']));
		redirect();
		break;
	case "become_proponent":
		Login::access_action("entitled", $ngroup);
		action_required_parameters('proponent');
		$proposal->add_proponent(trim($_POST['proponent']));
		redirect();
		break;
	case "confirm_proponent":
		Login::access_action("entitled", $ngroup);
		action_required_parameters('member');
		if (!$proposal->is_proponent(Login::$member)) {
			warning(_("Your are not a proponent of this proposal."));
			redirect();
		}
		$member = new Member($_POST['member']);
		if (!$member->id) {
			warning(_("The member does not exist."));
			redirect();
		}
		$proposal->confirm_proponent($member);
		redirect();
		break;
	case "confirm_remove_proponent":
		Login::access_action("entitled", $ngroup);
		$proposal->remove_proponent(Login::$member);
		redirect();
		break;

	case "add_support":
		Login::access_action("entitled", $ngroup);
		$proposal->add_support();
		redirect();
		break;
	case "add_support_anonym":
		Login::access_action("entitled", $ngroup);
		$proposal->add_support(true);
		redirect();
		break;
	case "revoke_support":
		Login::access_action("entitled", $ngroup);
		$proposal->revoke_support();
		redirect();
		break;
	case "demand_votingmode":
		Login::access_action("entitled", $ngroup);
		$issue->demand_votingmode();
		redirect();
		break;
	case "revoke_votingmode":
		Login::access_action("entitled", $ngroup);
		$issue->revoke_votingmode();
		redirect();
		break;

	case "save_votingmode_admin":
		Login::access_action("admin");
		$issue->save_votingmode_admin(!empty($_POST['votingmode_admin']));
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

	case "move_to_issue":
		Login::access_action("admin");
		action_required_parameters("issue");
		$proposal->move_to_issue($_POST['issue']);
		redirect();
		break;

	case "add_comment":
		if ( !Login::access_allowed("comment") ) {
			warning(_("You don't have permissions to write comments."));
			redirect();
		}
		action_required_parameters("title", "content", "parent");
		$comment = new Comment;
		if ( in_array($_POST['parent'], ["pro", "contra", "discussion"]) ) {
			$comment->parent = 0;
			$comment->rubric = $_POST['parent'];
		} else {
			$parent = new Comment($_POST['parent']);
			if (!$parent->id) {
				warning(_("Parent comment does not exist."));
				redirect();
			}
			$comment->parent = $parent->id;
			$comment->rubric = $parent->rubric;
		}
		if ( !$proposal->allowed_add_comments($comment->rubric) ) {
			warning(_("Adding or rating comments is not allowed in this phase."));
			redirect();
		}
		$comment->proposal = $proposal->id;
		$comment->title = trim($_POST['title']);
		if (!$comment->title) {
			warning(_("The title of the comment must be not empty."));
			break;
		}
		$comment->content = trim($_POST['content']);
		if (!$comment->content) {
			warning(_("The content of the comment must be not empty."));
			break;
		}
		$comment->add($proposal);
		Comments::redirect_append_show($comment);
		break;
	case "update_comment":
		if ( !Login::access_allowed("comment") ) {
			warning(_("You don't have permissions to write comments."));
			redirect();
		}
		action_required_parameters("title", "content", "id");
		$comment = new Comment($_POST['id']);
		if (!$comment->id) {
			warning(_("This comment does not exist."));
			redirect();
		}
		$comment->title = trim($_POST['title']);
		if (!$comment->title) {
			warning(_("The title of the comment must be not empty."));
			break;
		}
		$comment->content = trim($_POST['content']);
		if (!$comment->content) {
			warning(_("The content of the comment must be not empty."));
			break;
		}
		$comment->apply_changes();
		Comments::redirect_append_show($comment);
		break;

	case "set_rating":
		if ( !Login::access_allowed("rate") ) {
			warning(_("You don't have permissions to rate comments."));
			redirect();
		}
		action_required_parameters("comment", "rating");
		$comment = new Comment($_POST['comment']);
		if (!$comment->id) {
			warning(_("This comment does not exist."));
			redirect();
		}
		if ( !$proposal->allowed_add_comments($comment->rubric) ) {
			warning(_("Adding or rating arguments is not allowed in this phase."));
			redirect();
		}
		if ( !$comment->set_rating(intval($_POST['rating'])) ) redirect();
		redirect(URI::same(true)."#comment".$comment->id);
		break;
	case "reset_rating":
		if ( !Login::access_allowed("rate") ) {
			warning(_("You don't have permissions to rate comments."));
			redirect();
		}
		action_required_parameters("comment");
		$comment = new Comment($_POST['comment']);
		if (!$comment->id) {
			warning(_("This comment does not exist."));
			redirect();
		}
		if ( !$proposal->allowed_add_comments($comment->rubric) ) {
			warning(_("Adding or rating arguments is not allowed in this phase."));
			redirect();
		}
		if ( !$comment->delete_rating() ) redirect();
		redirect(URI::same(true)."#comment".$comment->id);
		break;

	case "remove_comment":
	case "restore_comment":
		Login::access_action("admin");
		action_required_parameters("id");
		$comment = new Comment($_POST['id']);
		if (!$comment->id) {
			warning(_("This comment does not exist."));
			redirect();
		}
		$comment->removed = ($action=="remove_comment");
		$comment->update(["removed"]);
		redirect(URI::same(true)."#comment".$comment->id);
		break;

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


list($supporters, $proponents, $is_supporter, $is_proponent) = $proposal->supporters();


html_head(_("Proposal")." ".$proposal->id, true);



// messages
if (isset($_GET['remove_proponent']) and $proposal->is_proponent(Login::$member, false)) {
?>
<div class="messages">
<?
	if (!$proposal->allowed_change_proponents()) {
		warning(_("You can not remove yourself from the proponents list once voting preparation has started or the proposal has been closed!"));
	} else {
		form(URI::same(), 'class="notice"');
		?>&#10148; <?=_("Do you really want to remove yourself from the proponents of this proposal?");
		if ($proposal->proponents_count()==1 and $proposal->is_proponent(Login::$member, true)) {
			?> <?=_("Since you are the last proponent, the proposal will be scheduled to be revoked.");
		}
?>
<input type="hidden" name="action" value="confirm_remove_proponent">
<input type="submit" value="<?=_("Yes")?>">
<a href="<?=URI::same()?>"><?=_("No")?></a>
<?
		form_end();
	}
?>
</div>
<div class="clearfix"></div>
<?
}

if (Login::$member and $proposal->revoke) {
?>
<div class="messages">
<?
	notice(sprintf(
			_("This proposal lost all it's proponents and will be revoked on %s or when voting preparation begins, if it then still has less than %d proponents."),
			dateformat_smart($proposal->revoke), REQUIRED_PROPONENTS
		));
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
if (($is_proponent or Login::$admin) and $proposal->allowed_edit_content()) {
?>
<div class="add"><a href="proposal_edit.php?id=<?=$proposal->id?>" class="icontextlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>"><?=_("Edit proposal")?></a></div>
<?
}
?>
<h2><?=_("Title")?></h2>
<p class="proposal proposal_title"><?=h($proposal->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><?=content2html($proposal->content)?></p>
<?
if ($is_proponent and $proposal->allowed_edit_reason_only()) {
?>
<div class="add"><a href="proposal_edit.php?id=<?=$proposal->id?>" class="icontextlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>"><?=_("Edit reason")?></a></div>
<?
}
?>
<h2><?=_("Reason")?></h2>
<p class="proposal"><?=content2html($proposal->reason)?></p>
</div>

<div class="clearfix"></div>

<?
Comments::display($proposal);

// time bar
if ($proposal->submitted or $proposal->revoke) {
	$times = array();
	// proposal dates
	if ($proposal->submitted) {
		$times[] = array($proposal->submitted, _("Submitted"), _("Submitted at %s."));
	}
	if ($proposal->admitted) {
		$times[] = array($proposal->admitted, _("Admitted"), _("Admitted at %s."));
	}
	if ($proposal->revoke) {
		$times[] = array($proposal->revoke, _("Revoke"),
			strtr(_("Revoke proposal at %s if it then has less than %d proponents."), array('%d'=>REQUIRED_PROPONENTS))
		);
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
			// TODO: Maybe it would be better to have 2 different states for this
			if (strtotime($proposal->cancelled) > strtotime($proposal->submitted." + ".CANCEL_NOT_ADMITTED_INTERVAL)) {
				$times[] = array($proposal->cancelled, _("Cancelled"), _("Cancelled at %s because not admitted for too long."));
			} else {
				$times[] = array($proposal->cancelled, _("Cancelled"), _("Cancelled at %s because for other proposals of the same issue the debate was started."));
			}
			break;
		}
	}
	// issue/period dates
	if ($issue->debate_started) {
		if (!$proposal->cancelled or $proposal->cancelled > $issue->debate_started) {
			$times[] = array($issue->debate_started, _("Debate"), _("Debate started at %s."));
		}
	} elseif ($issue->period and !$proposal->cancelled) {
		$times[] = array($issue->period()->debate, _("Debate"), _("Debate starts at %s."));
	}
	if ($issue->preparation_started) {
		if (!$proposal->cancelled or $proposal->cancelled > $issue->preparation_started) {
			$times[] = array($issue->preparation_started, _("Preparation"), _("Voting preparation started at %s."));
		}
	} elseif ($issue->period and !$proposal->cancelled) {
		$times[] = array($issue->period()->preparation, _("Preparation"), _("Voting preparation starts at %s."));
	}
	if ($issue->voting_started) {
		if (!$proposal->cancelled or $proposal->cancelled > $issue->voting_started) {
			$times[] = array($issue->voting_started, _("Voting"), _("Voting started at %s."));
		}
	} elseif ($issue->period and !$proposal->cancelled and !$issue->votingmode_offline()) {
		$times[] = array($issue->period()->voting, _("Voting"), _("Voting starts at %s."));
	}
	if ($issue->counting_started) {
		if (!$proposal->cancelled or $proposal->cancelled > $issue->counting_started) {
			$times[] = array($issue->counting_started, _("Counting"), ("Counting started at %s."));
		}
	} elseif ($issue->period and !$proposal->cancelled and !$issue->votingmode_offline()) {
		$times[] = array($issue->period()->counting, _("Counting"), _("Counting starts at %s."));
	}
	if ($issue->finished and !$proposal->cancelled) {
		$times[] = array($issue->finished, _("Finished"), _("Finished at %s."));
	}
	if ($issue->cleared) {
		$times[] = array($issue->cleared, _("Cleared"), _("Cleared at %s."));
	}
	if ($issue->clear) {
		$times[] = array($issue->clear, _("Clear"), _("Will be cleared at %s."));
	}
	Timebar::display($times);
}

display_quorum($proposal, $supporters, $is_supporter);

?>

<div class="issue">
<?
if (Login::$member) {
	if (Login::$member->entitled($ngroup) and $issue->allowed_add_alternative_proposal()) {
?>
<div class="add"><a href="proposal_edit.php?issue=<?=$proposal->issue?>" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add alternative proposal")?></a></div>
<?
	}
} elseif (Login::$admin and $proposal->allowed_move_to_issue()) {
?>
<div class="add"><?=_("Move this proposal to issue")?>: <?
	form(URI::same(), 'style="display:inline-block"');
	input_select("issue", $proposal->options_move_to_issue());
	input_hidden("action", "move_to_issue");
	input_submit(_("move"));
	form_end();
	?></div>
<?
}
?>
<h2><?=_("This and alternative proposals")?></h2>
<table class="proposals">
<?
$show_results = in_array($issue->state, array('finished', 'cancelled'));
Issue::display_proposals_th($show_results);
list($proposals, $submitted) = $issue->proposals_list();
$issue->display_proposals($proposals, $submitted, count($proposals), $show_results, $proposal->id);
?>
</table>
</div>

<?

html_foot();


/**
 * display the right column with area and proponents
 *
 * @param Proposal $proposal
 * @param Issue   $issue
 * @param array   $proponents
 * @param boolean $is_proponent
 */
function display_proposal_info(Proposal $proposal, Issue $issue, array $proponents, $is_proponent) {
	global $ngroup;
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
		$allowed_edit_proponent = ( $proposal->allowed_change_proponents() and Login::$member->entitled($ngroup) );
		if ($allowed_edit_proponent and !$is_any_proponent) {
?>
<div class="add"><a href="<?=URI::append(['become_proponent'=>1])?>" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("become proponent")?></a></div>
<?
		}
	}
?>
<h2><?=_("Proponents")?></h2>
<ul>
<?
	$confirmed_proponents = 0;
	foreach ( $proponents as $proponent ) {
		/** @var Member $proponent */
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
<?
				form_end();
			} else {
				if ($proponent->proponent_confirmed) {
					echo content2html($proponent->proponent_name);
				} else {
?>
<span class="unconfirmed"><?=content2html($proponent->proponent_name)?></span>
(<?=$proponent->identity()?>)
<?
				}
				?> <a href="<?=URI::append(['edit_proponent'=>1])?>" class="iconlink"><img src="img/edit.png" width="16" height="16" alt="<?=_("edit")?>" title="<?=_("edit your proponent name and contact details")?>"></a><a href="<?=URI::append(['remove_proponent'=>1])?>" class="iconlink"><img src="img/delete.png" width="21" height="16" alt="<?=_("delete")?>" title="<?=_("remove yourself from the list of proponents")?>"></a><?
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
<?
			form_end();
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
<?
		form_end();
?>
	</li>
<?
	}
?>
</ul>
<?

	// show drafts only to the proponents
	if (!$is_proponent and !Login::$admin) return;

	if ($proposal->state=="draft" or !empty($_GET['show_drafts'])) {
		$proposal->display_drafts($proponents);
	} else {
?>
<a href="<?=URI::append(['show_drafts'=>1])?>"><?=_("Drafts")?></a>
<?
	}

	if ($proposal->state=="draft" and $confirmed_proponents >= REQUIRED_PROPONENTS) {
?>
<h2><?=_("Actions")?></h2>
<?
		form(URI::same());
?>
<input type="hidden" name="action" value="submit_proposal">
<input type="submit" value="<?=_("submit proposal")?>">
<?
		form_end();
	}

}


/**
 * display supporters and offline voting demanders
 *
 * @param Proposal $proposal
 * @param array   $supporters
 * @param mixed   $is_supporter
 */
function display_quorum(Proposal $proposal, array $supporters, $is_supporter) {
	global $ngroup;
?>
<div class="quorum">
<h2 id="supporters"><?=_("Supporters")?>:</h2>
<div class="bargraph_container">
<?
	$proposal->bargraph_quorum($is_supporter);
?>
</div>
<?

	if (Login::$member or Login::$admin) {
?>
<?=join(", ", $supporters);
		if (Login::$member and Login::$member->entitled($ngroup) and $proposal->allowed_change_supporters()) {
?>
<br class="clear">
<?
			if ($is_supporter) {
				form(URI::same()."#supporters", 'class="supported"');
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
<?
				form_end();
			} else {
				form(URI::same()."#supporters", 'style="display:inline-block"');
?>
<input type="hidden" name="action" value="add_support">
<input type="submit" value="<?=_("Support this proposal")?>">
<?
				form_end();
				form(URI::same()."#supporters", 'style="display:inline-block"');
?>
<input type="hidden" name="action" value="add_support_anonym">
<input type="submit" value="<?=_("Support this proposal anonymously")?>">
<?
				form_end();
			}
		}
	}

?>
<div class="clearfix"></div>
<?

	// admission by decision
	if (Login::$admin) {
		if (!empty($_GET['edit_admission_decision'])) {
			form(URI::same()."#admission_decision", 'class="admission_decision"');
			if ($proposal->admission_decision!==null) {
?>
<b><?=_("Admitted by decision")?>:</b>
<input type="text" name="admission_decision" value="<?=h($proposal->admission_decision)?>">
<input type="submit" value="<?=_("apply changes")?>">
<?
			} else {
?>
<b id="admission_decision"><?=_("Admit proposal due to a decision")?>:</b>
<input type="text" name="admission_decision">
<input type="submit" value="<?=_("admit proposal")?>">
<?
			}
?>
<input type="hidden" name="action" value="admission_decision">
<?
			form_end();
		} elseif ($proposal->admission_decision!==null) {
?>
<div id="admission_decision" class="admission_decision">
	<b><?=_("Admitted by decision")?>:</b>
	<?=content2html($proposal->admission_decision)?>
	&nbsp;
	<a href="<?=URI::append(['edit_admission_decision'=>1])?>#admission_decision" class="iconlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>></a>
</div>
<?
		} else {
?>
<div class="admission_decision">
	<a href="<?=URI::append(['edit_admission_decision'=>1])?>#admission_decision"><?=_("Admit proposal due to a decision")?></a>
</div>
<?
		}
	} elseif ($proposal->admission_decision!==null) {
?>
<div id="admission_decision" class="admission_decision">
	<b><?=_("Admitted by decision")?>:</b>
	<?=content2html($proposal->admission_decision)?>
</div>
<?
	}

?>
</div>
<?
}
