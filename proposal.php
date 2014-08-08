<?
/**
 * proposal.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

URI::strip_one_time_params(array('argument_parent', 'argument_edit'));

$proposal = new Proposal(@$_GET['id']);
if (!$proposal->id) {
	error("The requested proposal does not exist!");
}

$issue = $proposal->issue();

if (Login::$member) $edit_limit = strtotime("- ".ARGUMENT_EDIT_INTERVAL);

if ($action) {
	switch ($action) {

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

	case "add_argument":
		Login::access_action("member");
		action_required_parameters("title", "content", "parent");
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
			warning(strtr(
					_("The title has been truncated to the maximum allowed length of %length% characters!"),
					array('%length%'=>Argument::title_length)
				));
		}

		$argument->content = trim($_POST['content']);
		if (!$argument->content) {
			warning("The content of the argument must be not empty.");
			break;
		}
		if (mb_strlen($argument->content) > Argument::content_length) {
			$argument->content = limitstr($argument->content, Argument::content_length);
			warning(strtr(
					_("The content has been truncated to the maximum allowed length of %length% characters!"),
					array('%length%'=>Argument::content_length)
				));
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


html_head(_("Proposal")." ".$proposal->id);

?>


<div style="float:right; margin-left:20px; width:20%">
<h2><?=_("Area")?></h2>
<p class="proposal"><?=h($issue->area()->name)?></p>
<h2><?=_("Proponents")?></h2>
<p class="proposal"><?=content2html($proposal->proponents)?></p>
</div>

<div style="overflow:hidden">
<!--<div style="float:right"><a href="proposal_edit.php?id=<?=$proposal->id?>"><?=_("Edit proposal")?></a></div>-->
<h2><?=_("Title")?></h2>
<p class="proposal proposal_title"><?=h($proposal->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><?=content2html($proposal->content)?></p>
<h2><?=_("Reason")?></h2>
<p class="proposal"><?=content2html($proposal->reason)?></p>
</div>

<br style="clear:both">

<div>
	<div class="arguments_side" style="float:left">
<?
if (Login::$member and @$_GET['argument_parent']!="pro") {
?>
		<div style="float:right"><a href="<?=URI::append(array('argument_parent'=>"pro"))?>#form"><?=_("Add new pro argument")?></a></div>
<?
}
?>
		<h2><?=_("Pro")?></h2>
		<? arguments("pro", "pro", 0); ?>
	</div>
	<div class="arguments_side" style="float:right">
<?
if (Login::$member and @$_GET['argument_parent']!="contra") {
?>
		<div style="float:right"><a href="<?=URI::append(array('argument_parent'=>"contra"))?>#form"><?=_("Add new contra argument")?></a></div>
<?
}
?>
		<h2><?=_("Contra")?></h2>
		<? arguments("contra", "contra", 0); ?>
	</div>
	<div style="clear:both"></div>
</div>

<?
if (Login::$member or Login::$admin) {
?>
<div class="quorum">
<div style="float:left; margin-right:10px">
<?
	$proposal->bargraph_quorum();
?>
</div>
<b><?=_("Supporters")?>:</b> <?
	$supported_by_member = $proposal->show_supporters();
	if (Login::$member and $proposal->state=="submitted") {
?>
<br clear="both">
<?
		if ($supported_by_member) {
			form(URI::same(), 'style="background-color:green; display:inline-block"');
?>
&#10003; <?
			if ($supported_by_member==="anonymous") {
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
?>
</div>
<div class="quorum">
<div style="float:left; margin-right:10px">
<?
	$issue->bargraph_secret();
?>
</div>
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
?>
</div>
<?
}
?>

<div style="margin-top:20px">
<?
if (Login::$member) {
?>
<div style="float:right"><a href="proposal_edit.php?issue=<?=$proposal->issue?>"><?=_("Add alternative proposal")?></a></div>
<?
}
?>
<h2><?=_("This and alternative proposals")?></h2>
<table border="0" cellspacing="1" class="proposals">
<?
Issue::display_proposals_th();
$proposals = $issue->proposals_list();
if (Login::$member) $issue->read_secret_by_member();
$issue->display_proposals($proposals, count($proposals), $proposal->id);
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
function arguments($side, $parent, $level) {
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
				echo strtr(_("show remaining %count% arguments"), array('%count%'=>$remaining));
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
		if (Login::$member and $member->id==Login::$member->id and @$_GET['argument_edit']==$argument->id and !$argument->removed) {
?>
		<div class="author"><?=$member->username()?> <?=datetimeformat($argument->created)?></div>
<?
			if (strtotime($argument->created) > $edit_limit) {
?>
		<div class="time"><?=strtr(_("This argument can be updated until %datetime%."), array('%datetime%'=>datetimeformat($argument->created." + ".ARGUMENT_EDIT_INTERVAL)))?></div>
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
			if (Login::$member and $member->id==Login::$member->id and strtotime($argument->created) > $edit_limit and !$argument->removed) {
				?><a href="<?=URI::append(array('argument_edit'=>$argument->id))?>#argument<?=$argument->id?>"><?=_("edit")?></a> <?
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
		if (Login::$member and @$_GET['argument_parent']!=$argument->id and !$argument->removed) {
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
				!$argument->removed
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
		arguments($side, $argument->id, $level+1);
?>
	</li>
<?
	}

	if (Login::$member and @$_GET['argument_parent']==$parent) {
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
