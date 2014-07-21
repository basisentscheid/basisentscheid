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
	if (!$member) {
		error("Access denied");
	}
	switch ($action) {
	case "add_support":
		if ($proposal->state=="submitted") {
			$proposal->add_support();
		} else {
			warning("Support for this proposal can not be added, because it is not in the submitted phase!");
		}
		redirect();
		break;
	case "revoke_support":
		if ($proposal->state=="submitted") {
			$proposal->revoke_support();
		} else {
			warning("Support for this proposal can not be removed, because it is not in the submitted phase!");
		}
		redirect();
		break;
	case "demand_offline":
		if ($proposal->state=="submitted" or $proposal->state=="admitted" or $issue->state=="debate") {
			$issue->demand_secret();
		} else {
			warning("Support for secret voting can not be added, because the proposal is not in submitted, admitted or debate phase!");
		}
		redirect();
		break;
	case "revoke_demand_offline":
		if ($proposal->state=="submitted" or $proposal->state=="admitted" or $issue->state=="debate") {
			$issue->revoke_secret();
		} else {
			warning("Support for secret voting can not be removed, because the proposal is not in submitted, admitted or debate phase!");
		}
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


<div class="quorum">
<div style="float:left; margin-right:10px">
<?
$proposal->bargraph_quorum();
?>
</div>
<b><?=_("Supporters")?>:</b> <?
$supported_by_member = $proposal->show_supporters();
if ($proposal->state=="submitted") {
?>
<br clear="both">
<?
	if ($supported_by_member) {
?>
<form action="<?=$_SERVER['REQUEST_URI']?>" method="POST" style="background-color:green; display:inline-block">
&#10003; <?=_("You support this proposal.")?>
<input type="hidden" name="action" value="revoke_support">
<input type="submit" value="<?=_("Revoke your support for this proposal")?>">
</form>
<?
	} else {
?>
<form action="<?=$_SERVER['REQUEST_URI']?>" method="POST" style="display:inline-block">
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
if ($proposal->state=="submitted" or $proposal->state=="admitted" or $issue->state=="debate") {
?>
<br clear="both">
<?
	if ($demanded_by_member) {
?>
<form action="<?=$_SERVER['REQUEST_URI']?>" method="POST" style="background-color:red; display:inline-block">
&#10003; <?=_("You demand secret voting for this issue.")?>
<input type="hidden" name="action" value="revoke_demand_offline">
<input type="submit" value="<?=_("Revoke your demand for secret voting")?>">
</form>
<?
	} else {
?>
<form action="<?=$_SERVER['REQUEST_URI']?>" method="POST" style="display:inline-block">
<input type="hidden" name="action" value="demand_offline">
<input type="submit" value="<?=_("Demand secret voting for this issue")?>">
</form>
<?
	}
}
?>
</div>


<div style="margin-top:20px">
<h2><?=_("This and alternative proposals")?>
<span class="hadd"><a href="proposal_edit.php?issue=<?=$proposal->issue?>"><?=_("Add alternative proposal")?></a></span>
</h2>
<table border="0" cellspacing="1" cellpadding="2" class="proposals">
<?
Issue::display_proposals_th();
$issue->display_proposals($proposal->id);
?>
</table>
</div>

<?


html_foot();
