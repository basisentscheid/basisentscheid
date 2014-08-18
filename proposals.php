<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


if ($action) {
	switch ($action) {
	case "select_period":
		Login::access_action("admin");
		action_proposal_select_period();
		break;
	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Proposals"));

if (Login::$member) {
?>
<div class="add_record"><a href="proposal_edit.php" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add proposal")?></a></div>
<?
}

$filter = @$_GET['filter'];
$search = trim(@$_GET['search']);

$filters = array(
	'' => _("Open"),
	'admission' => _("Admission"),
	'debate' => _("Debate"),
	'voting' => _("Voting"),
	'closed' => _("Closed")
);

?>
<div class="filter">
<?
foreach ( $filters as $key => $name ) {
	$params = array();
	if ($key)    $params['filter'] = $key;
	if ($search) $params['search'] = $search;
?>
<a href="<?=URI::build($params)?>"<?
	if ($key==$filter) { ?> class="active"<? }
	?>><?=$name?></a>
<?
}
?>
<form action="<?=BN?>" method="GET">
<?
if ($filter) input_hidden("filter", $filter);
?>
<?=_("Search")?>: <input type="text" name="search" value="<?=h($search)?>">
<input type="submit" value="<?=_("search")?>">
</form>
</div>

<table class="proposals">
<?

$pager = new Pager;

if (Login::$member) {
	$sql = "SELECT issues.*, ballot_voting_demanders.member AS ballot_voting_demanded_by_member
		FROM issues
		LEFT JOIN ballot_voting_demanders
			ON issues.id = ballot_voting_demanders.issue
			AND ballot_voting_demanders.member = ".intval(Login::$member->id);
} else {
	$sql = "SELECT issues.*
		FROM issues";
}

$where = array();
$order_by = " ORDER BY issues.id DESC";
$show_results = false;

switch (@$_GET['filter']) {
case "admission":
	$where[] = "issues.state='admission'";
	break;
case "debate":
	$where[] = "issues.state='debate'";
	$order_by = " ORDER BY issues.period DESC, issues.id DESC";
	break;
case "voting":
	$where[] = "(issues.state='voting' OR issues.state='preparation' OR issues.state='counting')";
	$order_by = " ORDER BY issues.period DESC, issues.id DESC";
	break;
case "closed":
	$where[] = "(issues.state='finished' OR issues.state='cleared' OR issues.state='cancelled')";
	$show_results = true;
	break;
default: // open
	$where[] = "(issues.state!='finished' AND issues.state!='cleared' AND issues.state!='cancelled')";
}

if ($search) {
	$sql .= " JOIN proposals ON proposals.issue = issues.id";
	$pattern = DB::esc("%".strtr($search, array('%'=>'\%', '_'=>'\_'))."%");
	$where[] = "(title ILIKE ".$pattern." OR content ILIKE ".$pattern." OR reason ILIKE ".$pattern.")";
	$sql .= DB::where_and($where);
	$sql .= " GROUP BY issues.id, ballot_voting_demanders.member";
} else {
	$sql .= DB::where_and($where);
}

$sql .= $order_by;

$result = DB::query($sql);
$pager->seek($result);
$line = $pager->firstline;

// collect issues and proposals
$issues = array();
$proposals_issue = array();
$submitted_issue = array();
$period = 0;
$period_rowspan = array();
$i = 0;
$i_first = 0;
while ( $issue = DB::fetch_object($result, "Issue") and $line <= $pager->lastline ) {
	$issues[] = $issue;
	list($proposals, $submitted) = $issue->proposals_list();
	$proposals_issue[] = $proposals;
	$submitted_issue[] = $submitted;
	// calculate period rowspan
	if ($period and $issue->period == $period) {
		$period_rowspan[$i] = 0;
		$period_rowspan[$i_first] += count($proposals) + 1;
	} else {
		$period_rowspan[$i] = count($proposals);
		$i_first = $i;
		$period = $issue->period;
	}
	$i++;
	$line++;
}

Issue::display_proposals_th($show_results);

// display issues and proposals
foreach ( $issues as $i => $issue ) {
?>
	<tr><td colspan="<?= $period_rowspan[$i] ? 6 : 5 ?>" class="issue_separator"></td></tr>
<?
	$issue->display_proposals($proposals_issue[$i], $submitted_issue[$i], $period_rowspan[$i], $show_results);
}

?>
</table>

<?
$pager->msg_itemsperpage = _("Issues per page");
$pager->display();


html_foot();
