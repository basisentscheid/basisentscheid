<?
/**
 * proposal draft
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$draft = new Draft(@$_GET['id']);
if (!$draft->id) {
	error("The requested draft does not exist.");
}

$proposal = new Proposal($draft->proposal);

$issue = $proposal->issue();

list($supporters, $proponents, $is_supporter, $is_proponent) = $proposal->supporters();

if (!$is_proponent and !Login::$admin) {
	error("You are not a proponent of this proposal!");
}


html_head(sprintf(_("Proposal %d, draft from %s"), $proposal->id, datetimeformat($draft->created)));

?>

<div class="proposal_info">
<? $proposal->display_proposal_info($issue, $proponents, $is_proponent); ?>
</div>

<div class="proposal_content">
<h2><?=_("Title")?></h2>
<p class="proposal proposal_title"><?=h($draft->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><?=content2html($draft->content)?></p>
<h2><?=_("Reason")?></h2>
<p class="proposal"><?=content2html($draft->reason)?></p>
</div>

<div class="clearfix"></div>

<?

html_foot();
