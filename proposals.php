<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

$ngroup = Ngroup::get();

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


html_head(_("Proposals"), true);

if (Login::$member and Login::$member->entitled($ngroup->id)) {
?>
<div class="add_record"><a href="proposal_edit.php?ngroup=<?=$ngroup->id?>" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add proposal")?></a></div>
<?
}

$filter = @$_GET['filter'];
$search = trim(@$_GET['search']);

// count issues in each state
$sql = "SELECT state, count(*)
	FROM issue
	JOIN area ON area.id = issue.area AND area.ngroup = ".intval($ngroup->id)."
	GROUP BY state";
$result = DB::query($sql);
$counts = array(
	'entry'       => 0,
	'debate'      => 0,
	'preparation' => 0,
	'voting'      => 0,
	'counting'    => 0,
	'finished'    => 0,
	'cancelled'   => 0
);
while ( $row = DB::fetch_row($result) ) $counts[$row[0]] = $row[1];

// count issues in entry stage with proposals in different states
foreach ( array('draft', 'submitted', 'admitted') as $state ) {
	$sql = "SELECT count(DISTINCT issue.id)
		FROM issue
		JOIN area ON area.id = issue.area AND area.ngroup = ".intval($ngroup->id)."
		JOIN proposal ON proposal.issue = issue.id
		WHERE issue.state='entry' AND proposal.state='$state'";
	$counts[$state] = DB::fetchfield($sql);
}

$params = array('ngroup'=>$ngroup->id);
if ($search) $params['search'] = $search;
?>
<div class="filter proposals">
<div class="top">
	<a href="<?=URI::build($params)?>" title="<?
echo _("issues in entry, debate and voting phases");
?>" class="top<?
if ($filter=="") { ?> active<? }
?>"><?=_("open")?></a>
	<div class="mid">
		<a href="<?=URI::build($params + ['filter'=>"entry"])?>" title="<?
printf(ngettext("%d issue in entry phase", "%d issues in entry phase", $counts['entry']), $counts['entry']);
?>"<?
if ($filter=="entry") { ?> class="active"<? }
?>><?=_("Entry")?> (<?=$counts['entry']?>)</a>
		<a href="<?=URI::build($params + ['filter'=>"draft"])?>" title="<?
printf(ngettext("%d issue in entry phase with proposals in draft phase", "%d issues in entry phase with proposals in draft phase", $counts['draft']), $counts['draft']);
?>" class="sub<?
if ($filter=="draft") { ?> active<? }
?>"><?=_("Draft")?> (<?=$counts['draft']?>)</a>
		<a href="<?=URI::build($params + ['filter'=>"submitted"])?>" title="<?
printf(ngettext("%d issue in entry phase with submitted proposals", "%d issues in entry phase with submitted proposals", $counts['submitted']), $counts['submitted']);
?>" class="sub<?
if ($filter=="submitted") { ?> active<? }
?>"><?=_("submitted")?> (<?=$counts['submitted']?>)</a>
		<a href="<?=URI::build($params + ['filter'=>"admitted"])?>" title="<?
printf(ngettext("%d issue in entry phase with admitted proposals", "%d issues in entry phase with admitted proposals", $counts['admitted']), $counts['admitted']);
?>" class="sub<?
if ($filter=="admitted") { ?> active<? }
?>"><?=_("admitted")?> (<?=$counts['admitted']?>)</a>
	</div>
	<div class="mid">
		<a href="<?=URI::build($params + ['filter'=>"debate"])?>" title="<?
printf(ngettext("%d issue in debate phase", "%d issues in debate phase", $counts['debate']), $counts['debate']);
?>"<?
if ($filter=="debate") { ?> class="active"<? }
?>><?=_("Debate")?> (<?=$counts['debate']?>)</a>
	</div>
	<div class="mid">
		<a href="<?=URI::build($params + ['filter'=>"voting"])?>" title="<?
printf(_("%d issues in voting, %d in voting preparation and %d in counting phase"), $counts['voting'], $counts['preparation'], $counts['counting']);
$nyvic = $ngroup->not_yet_voted_issues_count();
if ($nyvic) { ?> &mdash; <?=Ngroup::not_yet_voted($nyvic); }
?>"<?
if ($filter=="voting") { ?> class="active"<? }
?>><?=_("Voting")?> (<?=($counts['voting']+$counts['preparation']+$counts['counting']);
if ($nyvic) { ?>, <? printf(_("not voted on %d"), $nyvic); }
?>)</a>
	</div>
</div>
<div class="top">
	<div class="mid_dummy">
		<a href="<?=URI::build($params + ['filter'=>"closed"])?>" title="<?
printf(_("%d issues are finished, %d issues are cancelled"), $counts['finished'], $counts['cancelled']);
?>" class="top<?
if ($filter=="closed") { ?> active<? }
?>"><?=_("closed")?> (<?=($counts['finished']+$counts['cancelled'])?>)</a>
	</div>
</div>
<form id="search" action="<?=BN?>" method="GET">
<?
input_hidden('ngroup', $ngroup->id);
if ($filter) input_hidden("filter", $filter);
?>
<?=_("Search")?>: <input type="search" name="search" value="<?=h($search)?>">
<input type="submit" value="<?=_("search")?>">
<a href="<?=URI::strip(['search'])?>"><?=_("reset")?></a>
</form>
</div>

<table class="proposals">
<?

$pager = new Pager(10);

$sql = "SELECT issue.*
	FROM issue
	JOIN area ON area.id = issue.area AND area.ngroup = ".intval($ngroup->id);
$join_proposal = false;
$where = array();
$order_by = " ORDER BY issue.id DESC";
$show_results = false;

switch (@$_GET['filter']) {
case "entry":
	$where[] = "issue.state='entry'";
	break;
case "draft":
	$join_proposal = true;
	$where[] = "issue.state='entry' AND proposal.state='draft'";
	break;
case "submitted":
	$join_proposal = true;
	$where[] = "issue.state='entry' AND proposal.state='submitted'";
	break;
case "admitted":
	$join_proposal = true;
	$where[] = "issue.state='entry' AND proposal.state='admitted'";
	break;
case "debate":
	$where[] = "issue.state='debate'";
	$order_by = " ORDER BY issue.period DESC, issue.id DESC";
	break;
case "voting":
	$where[] = "(issue.state='voting' OR issue.state='preparation' OR issue.state='counting')";
	$order_by = " ORDER BY issue.period DESC, issue.id DESC";
	break;
case "closed":
	$where[] = "(issue.state='finished' OR issue.state='cancelled')";
	$show_results = true;
	break;
default: // open
	$where[] = "(issue.state!='finished' AND issue.state!='cancelled')";
}

if ($search) {
	$join_proposal = true;
	$pattern = DB::esc("%".strtr($search, array('%'=>'\%', '_'=>'\_'))."%");
	$where[] = "(title ILIKE ".$pattern." OR content ILIKE ".$pattern." OR reason ILIKE ".$pattern.")";
}

if ($join_proposal) {
	$sql .= " JOIN proposal ON proposal.issue = issue.id"
		.DB::where_and($where)
		." GROUP BY issue.id";
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
$separator_colspan = array();
$i = 0;
$i_first = 0;
while ( $issue = DB::fetch_object($result, "Issue") and $line <= $pager->lastline ) {
	$issues[] = $issue;
	list($proposals, $submitted) = $issue->proposals_list();
	$proposals_issue[] = $proposals;
	$submitted_issue[] = $submitted;
	// calculate period rowspan
	if ($period and $issue->period == $period and !Login::$admin) {
		$period_rowspan[$i] = 0;
		$period_rowspan[$i_first] += count($proposals) + 1;
		$separator_colspan[$i] = 0;
	} else {
		$period_rowspan[$i] = count($proposals);
		$separator_colspan[$i] = 1;
		$i_first = $i;
		$period = $issue->period;
	}
	$i++;
	$line++;
}

Issue::display_proposals_th($show_results);

// display issues and proposals
$cols = 3;
if ($show_results) $cols++;
foreach ( $issues as $i => $issue ) {
	/** @var $issue Issue */
?>
	<tr class="issue_separator"><td colspan="<?= $cols + $separator_colspan[$i] ?>"></td><td></td></tr>
<?
	$issue->display_proposals($proposals_issue[$i], $submitted_issue[$i], $period_rowspan[$i], $show_results);
}

?>
</table>

<?
$pager->msg_itemsperpage = _("Issues per page");
$pager->display();


html_foot();
