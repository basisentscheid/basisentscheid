<?
/**
 * proposal draft differences
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";
require "inc/libs/PHP-FineDiff/finediff.php";

$draft = new Draft(@$_GET['draft1']);
if (!$draft->id) {
	error(_("The requested draft does not exist."));
}

$draft2 = new Draft(@$_GET['draft2']);
if (!$draft2->id) {
	error(_("The requested draft does not exist."));
}

$proposal = new Proposal($draft->proposal);

$issue = $proposal->issue();

list($supporters, $proponents, $is_supporter, $is_proponent) = $proposal->supporters();

if (!$is_proponent and !Login::$admin) {
	error(_("You are not a proponent of this proposal!"));
}


html_head( sprintf(_("<a%s>Proposal %d</a>, version differences"), ' href="proposal.php?id='.$proposal->id.'"', $proposal->id) );

?>

<div class="proposal_info">
<? $proposal->display_proposal_info($issue, $proponents, $is_proponent); ?>
</div>

<div class="proposal_content diff">
<h2><?=_("Title")?></h2>
<p class="proposal proposal_title"><? diff($draft->title, $draft2->title)?></p>
<h2><?=_("Content")?></h2>
<p class="proposal"><? diff($draft->content, $draft2->content)?></p>
<h2><?=_("Reason")?></h2>
<p class="proposal"><? diff($draft->reason, $draft2->reason)?></p>
</div>

<div class="clearfix"></div>

<?

html_foot();


/**
 * wrapper for PHP-FineDiff library
 *
 * @param string  $from_text
 * @param string  $to_text
 */
function diff($from_text, $to_text) {
	/** @noinspection PhpUndefinedClassInspection */
	$opcodes = FineDiff::getDiffOpcodes($from_text, $to_text);
	/** @noinspection PhpUndefinedClassInspection */
	echo FineDiff::renderDiffToHTMLFromOpcodes($from_text, $opcodes);
}
