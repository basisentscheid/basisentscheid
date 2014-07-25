<?
/**
 * proposal.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$proposal = new Proposal(@$_GET['id']);
if (!$proposal->id) {
	error("The requested proposal does not exist!");
}

$issue = $proposal->issue();

if ($action) {
	switch ($action) {
	case "add_support":
		Login::access_action("member");
		if ($proposal->state=="submitted") {
			$proposal->add_support();
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
			$issue->demand_secret();
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
				warning("Invalid parent");
				redirect();
			}
			$argument->parent = $parent->id;
			$argument->side = $parent->side;
		}
		$argument->proposal = $proposal->id;
		$argument->member = Login::$member->id;
		$argument->title = trim($_POST['title']);
		$argument->content = trim($_POST['content']);
		$argument->create();
		redirect();
		break;
	case "rating_plus":
	case "rating_minus":
		Login::access_action("member");
		action_required_parameters("argument");
		$argument = new Argument($_POST['argument']);
		if ($argument->member==Login::$member->id) {
			warning("Rating your own arguments is not allowed.");
			redirect();
		}
		$argument->set_rating($action=="rating_plus");
		redirect();
		break;
	case "rating_reset":
		Login::access_action("member");
		action_required_parameters("argument");
		$argument = new Argument($_POST['argument']);
		$argument->delete_rating();
		redirect();
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
<p class="proposal"><?=h($proposal->proponents)?></p>
</div>

<div style="overflow:hidden">
<h2><?=_("Title")?>
<!--<span class="hadd"><a href="proposal_edit.php?id=<?=$proposal->id?>"><?=_("Edit proposal")?></a></span>-->
</h2>
<p class="proposal"><?=h($proposal->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><?=nl2br(h($proposal->content))?></p>
<h2><?=_("Reason")?></h2>
<p class="proposal"><?=nl2br(h($proposal->reason))?></p>
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
		<? arguments("pro", "pro"); ?>
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
		<? arguments("contra", "contra"); ?>
	</div>
	<div style="clear:both"></div>
</div>

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
?>
<form action="<?=URI?>" method="POST" style="background-color:green; display:inline-block">
&#10003; <?=_("You support this proposal.")?>
<input type="hidden" name="action" value="revoke_support">
<input type="submit" value="<?=_("Revoke your support for this proposal")?>">
</form>
<?
	} else {
?>
<form action="<?=URI?>" method="POST" style="display:inline-block">
<input type="hidden" name="action" value="add_support">
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
?>
<form action="<?=URI?>" method="POST" style="background-color:red; display:inline-block">
&#10003; <?=_("You demand secret voting for this issue.")?>
<input type="hidden" name="action" value="revoke_demand_offline">
<input type="submit" value="<?=_("Revoke your demand for secret voting")?>">
</form>
<?
	} else {
?>
<form action="<?=URI?>" method="POST" style="display:inline-block">
<input type="hidden" name="action" value="demand_offline">
<input type="submit" value="<?=_("Demand secret voting for this issue")?>">
</form>
<?
	}
}
?>
</div>

<div style="margin-top:20px">
<?
if (Login::$member) {
?>
<div class="hadd"><a href="proposal_edit.php?issue=<?=$proposal->issue?>"><?=_("Add alternative proposal")?></a></div>
<?
}
?>
<h2><?=_("This and alternative proposals")?></h2>
<table border="0" cellspacing="1" cellpadding="2" class="proposals">
<?
Issue::display_proposals_th();
$issue->display_proposals($proposal->id);
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
 */
function arguments($side, $parent) {
	global $proposal;

	$sql = "SELECT arguments.*, (arguments.plus - arguments.minus) AS rating";
	if (Login::$member) {
		$sql .= ", ratings.positive
			FROM arguments
			LEFT JOIN ratings ON ratings.argument = arguments.id AND ratings.member = ".intval(Login::$member->id);
	} else {
		$sql = "
			FROM arguments";
	}
	// intval($parent) gives parent=0 for "pro" and "contra"
	$sql .= "	WHERE proposal=".intval($proposal->id)."
			AND side=".m($side)."
			AND parent=".intval($parent)."
		ORDER BY rating DESC, arguments.created";
	$result = DB::query($sql);
	if (!pg_num_rows($result) and @$_GET['argument_parent']!=$parent) return;

?>
<ul>
<?

	while ( $row = pg_fetch_assoc($result) ) {
		$member = new Member($row['member']);
?>
	<li>
		<div class="author"><?=$member->username()?> <?=datetimeformat($row['created'])?></div>
		<h3><?=h($row['title'])?></h3>
		<p><?=nl2br(h($row['content']), false)?></p>
<?
		if (@$_GET['argument_parent']!=$row['id']) {
?>
		<div class="reply"><a href="<?=URI::append(array('argument_parent'=>$row['id']))?>#form"><?=_("Reply")?></a></div>
<?
		}
		if ($row['plus']) {
			?><span class="plus<? if ($row['positive']=="t") { ?> me<? } ?>">+<?=$row['plus']?></span> <?
		}
		if ($row['minus']) {
			?><span class="minus<? if ($row['positive']=="f") { ?> me<? } ?>">-<?=$row['minus']?></span> <?
		}
		if ($row['plus'] and $row['minus']) {
			?><span class="rating">=<?=$row['rating']?></span> <?
		}
		if (Login::$member and $row['member']!=Login::$member->id) { // don't allow to rate ones own arguments
			if ($row['positive']) {
?>
		<form action="<?=URI?>" method="POST" class="button">
		<input type="hidden" name="argument" value="<?=$row['id']?>">
		<input type="hidden" name="action" value="rating_reset">
		<input type="submit" value="reset">
		</form>
<?
			} else {
?>
		<form action="<?=URI?>" method="POST" class="button">
		<input type="hidden" name="argument" value="<?=$row['id']?>">
		<input type="hidden" name="action" value="rating_plus">
		<input type="submit" value="+1">
		</form>
		<form action="<?=URI?>" method="POST" class="button">
		<input type="hidden" name="argument" value="<?=$row['id']?>">
		<input type="hidden" name="action" value="rating_minus">
		<input type="submit" value="-1">
		</form>
<?
			}
		}
?>
		<div class="clearfix"></div>
<?
		arguments($side, $row['id']);
?>
	</li>
<?
	}

	if (@$_GET['argument_parent']==$parent) {
?>
	<li>
		<form action="<?=URI::strip(array('argument_parent' ))?>" method="POST" class="argument">
		<a name="form"></a>
		<input name="title" type="text"><br>
		<textarea name="content" rows="5"></textarea><br>
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
