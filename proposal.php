<?
/**
 * proposal.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

URI::strip_one_time_params(array('argument_parent', 'argument_edit', 'edit_admission_decision', 'show_drafts'));

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

	case "add_support":
		Login::access_action("member");
		if ($proposal->state=="submitted") {
			$proposal->add_support(@$_POST['anonymous']==1);
		} else {
			warning("Support for this proposal can not be added, because it is not in the submitted phase!");
		}
		redirect();
		break;
	case "revoke_support":
		Login::access_action("member");
		if ($proposal->state=="submitted") {
			$proposal->revoke_support();
		} else {
			warning("Support for this proposal can not be removed, because it is not in the submitted phase!");
		}
		redirect();
		break;
	case "demand_offline":
		Login::access_action("member");
		if ($proposal->state=="submitted" or $proposal->state=="admitted" or $issue->state=="debate") {
			$issue->demand_secret(@$_POST['anonymous']==1);
		} else {
			warning("Support for secret voting can not be added, because the proposal is not in submitted, admitted or debate phase!");
		}
		redirect();
		break;
	case "revoke_demand_offline":
		Login::access_action("member");
		if ($proposal->state=="submitted" or $proposal->state=="admitted" or $issue->state=="debate") {
			$issue->revoke_secret();
		} else {
			warning("Support for secret voting can not be removed, because the proposal is not in submitted, admitted or debate phase!");
		}
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

	case "rating_plus":
	case "rating_minus":
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
		if ($argument->member==Login::$member->id) {
			warning("Rating your own arguments is not allowed.");
			redirect();
		}
		if ($argument->removed) {
			warning("The argument has been removed.");
			redirect();
		}
		$argument->set_rating($action=="rating_plus");
		redirect(URI::same()."#argument".$argument->id);
		break;

	case "rating_reset":
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
		warning("Unknown action");
		redirect();
	}
}


if (isset($_GET['draft'])) {
	$draft = new Draft($_GET['draft']);
	if ($draft->proposal != $proposal->id) {
		error("The requested draft does not exist!");
	}
	html_head(sprintf(_("Proposal %d, draft from %s"), $proposal->id, datetimeformat($draft->created)));
	$content_obj = $draft;
} else {
	html_head(_("Proposal")." ".$proposal->id);
	$content_obj = $proposal;
}


list($supporters, $proponents, $is_supporter, $is_proponent) = $proposal->supporters();

?>

<div class="proposal_info">
<h2><?=_("Area")?></h2>
<p class="proposal"><?=h($issue->area()->name)?></p>
<h2><?=_("Proponents")?></h2>
<table>
<?
foreach ( $proponents as $proponent ) {
?>
<tr class="<?=stripes()?>">
	<td><?=$proponent->username()?></td>
</tr>
<?
}
?>
</table>
<?

if (Login::$member or Login::$admin) {

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

	// actions
	if ($is_proponent) {
		if ($proposal->state=="draft") {
?>
<h2><?=_("Actions")?></h2>
<?
			form(URI::same());
?>
<input type="hidden" name="action" value="submit_proposal">
<input type="submit" value="<?=_("submit proposal")?>">
</form>
<?
		}
		if ($issue->state=="admission" or $issue->state=="debate") {
?>
<h2><?=_("Actions")?></h2>
<?
			form(URI::same());
?>
<input type="hidden" name="action" value="revoke_proposal">
<input type="submit" value="<?=_("revoke proposal")?>">
</form>
<?
		}
	}

}

?>
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
	display_quorum($proposal, $issue, $supporters, $is_supporter);
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
$proposals = $issue->proposals_list();
if (Login::$member) $issue->read_secret_by_member();
$show_results = in_array($issue->state, array('finished', 'cleared', 'cancelled'));
Issue::display_proposals_th($show_results);
$issue->display_proposals($proposals, count($proposals), $show_results, $proposal->id);
?>
</table>
</div>

<?

html_foot();


/**
 * list the sub-arguments for one parent-argument
 *
 * @param string  $side   "pro" or "contra"
 * @param mixed   $parent ID of parent argument or "pro" or "contra"
 * @param integer $level
 */
function display_arguments($side, $parent, $level) {
	global $proposal, $edit_limit;

	$sql = "SELECT arguments.*, (arguments.plus - arguments.minus) AS rating";
	if (Login::$member) {
		$sql .= ", ratings.positive
			FROM arguments
			LEFT JOIN ratings ON ratings.argument = arguments.id AND ratings.member = ".intval(Login::$member->id);
	} else {
		$sql .= "
			FROM arguments";
	}
	// intval($parent) gives parent=0 for "pro" and "contra"
	$sql .= "	WHERE proposal=".intval($proposal->id)."
			AND side=".m($side)."
			AND parent=".intval($parent)."
		ORDER BY removed, rating DESC, arguments.created";
	$result = DB::query($sql);
	$num_rows = pg_num_rows($result);
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

		if (Login::$member) DB::pg2bool($argument->positive);
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
		<div class="reply"><a href="<?=URI::append(array('argument_parent'=>$argument->id))?>#form"><?=_("Reply")?></a></div>
<?
		}

		// rating and remove/restore
		if ($argument->plus) {
			?><span class="plus<? if (Login::$member and $argument->positive===true) { ?> me<? } ?>">+<?=$argument->plus?></span> <?
		}
		if ($argument->minus) {
			?><span class="minus<? if (Login::$member and $argument->positive===false) { ?> me<? } ?>">-<?=$argument->minus?></span> <?
		}
		if ($argument->plus and $argument->minus) {
			?><span class="rating">=<?=$argument->rating?></span> <?
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
				if ($argument->positive!==null) {
					form($uri, 'class="button"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
<input type="hidden" name="action" value="rating_reset">
<input type="submit" value="<?=_("reset")?>">
</form>
<?
				} else {
					form($uri, 'class="button"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
<input type="hidden" name="action" value="rating_plus">
<input type="submit" value="+1">
</form>
<?
					form($uri, 'class="button"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
<input type="hidden" name="action" value="rating_minus">
<input type="submit" value="-1">
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
 * display supporters and secret voting demanders
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
		if (Login::$member and $proposal->state=="submitted") {
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
	}

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
	} elseif (Login::$admin and $proposal->state=="submitted" and $proposal->admission_decision===null) {
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
<div class="quorum">
<div class="bargraph_container">
<?
	$issue->bargraph_secret();
?>
</div>
<?
	if (Login::$member or Login::$admin) {
?>
<b><?=_("Secret voting demanders")?>:</b> <?
		$demanded_by_member = $issue->show_offline_demanders();
		if (Login::$member and ($proposal->state=="submitted" or $proposal->state=="admitted" or $issue->state=="debate")) {
?>
<br clear="both">
<?
			if ($demanded_by_member) {
				form(URI::same(), 'style="background-color:red; display:inline-block"');
?>
&#10003; <?
				if ($demanded_by_member==="anonymous") {
					echo _("You demand secret voting for this issue anonymously.");
				} else {
					echo _("You demand secret voting for this issue.");
				}
?>
<input type="hidden" name="action" value="revoke_demand_offline">
<input type="submit" value="<?=_("Revoke your demand for secret voting")?>">
</form>
<?
			} else {
				form(URI::same(), 'style="display:inline-block"');
?>
<input type="hidden" name="action" value="demand_offline">
<input type="checkbox" name="anonymous" value="1"><?=_("anonymous")."\n"?>
<input type="submit" value="<?=_("Demand secret voting for this issue")?>">
</form>
<?
			}
		}
	}
?>
</div>
<?
}
