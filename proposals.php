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
		warning("Unknown action");
		redirect();
	}
}


html_head(_("Proposals"));

if (Login::$member) {
?>
<div style="float:right"><a href="proposal_edit.php"><?=_("Add proposal")?></a></div>
<?
}

$filter = @$_GET['filter'];
$search = trim(@$_GET['search']);

$filters = array(
	'' => _("All"),
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
<form action="<?=BN?>" method="GET" style="display:inline; margin-left:20px">
<?
if ($filter) input_hidden("filter", $filter);
?>
<?=_("Search")?>: <input type="text" name="search" value="<?=h($search)?>">
<input type="submit" value="<?=_("search")?>">
</form>
</div>

<table border="0" cellspacing="1" class="proposals">
<?
Issue::display_proposals_th();

$pager = new Pager;

$sql = "SELECT issues.*
	FROM issues";

$where = array();

switch (@$_GET['filter']) {
case "admission":
	$where[] = "issues.state='admission'";
	break;
case "debate":
	$where[] = "issues.state='debate'";
	break;
case "voting":
	$where[] = "(issues.state='voting' OR issues.state='preparation' OR issues.state='counting')";
	break;
case "closed":
	$where[] = "(issues.state='finished' OR issues.state='cleared' OR issues.state='cancelled')";
	break;
}

if ($search) {
	$sql .= " JOIN proposals ON proposals.issue = issues.id";
	$pattern = DB::m("%".strtr($search, array('%'=>'\%', '_'=>'\_'))."%");
	$where[] = "(proponents ILIKE ".$pattern." OR title ILIKE ".$pattern." OR content ILIKE ".$pattern." OR reason ILIKE ".$pattern.")";
	$sql .= DB::where_and($where);
	$sql .= " GROUP BY issues.id";
} else {
	$sql .= DB::where_and($where);
}

$sql .= " ORDER BY issues.id DESC";

$result = DB::query($sql);
$pager->seek($result);
$line = $pager->firstline;
while ( $row = pg_fetch_assoc($result) and $line <= $pager->lastline ) {
	$issue = new Issue($row);
?>
			<tr><td colspan="6" style="height:5px"></td></tr>
<?
	$issue->display_proposals();
	$line++;
}

?>
</table>

<?
$pager->display(_("Issues per page"));


html_foot();
