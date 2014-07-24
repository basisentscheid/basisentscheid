<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


if ($action) {
	if (!Login::$member) {
		warning("Access denied");
		redirect();
	}
	switch ($action) {
	case "select_period":

		$issue = new Issue(@$_POST['issue']);
		if (!$issue) {
			warning("The requested issue does not exist!");
			redirect();
		}

		$period = new Period(@$_POST['period']);
		if (!$period) {
			warning("The selected period does not exist!");
			redirect();
		}

		// TODO: restrict available periods

		$issue->period = $period->id;
		$issue->update(array("period"));

		redirect();
		break;
	default:
		warning("Unknown action");
		redirect();
	}
}


html_head(_("Proposals"));

$filter = @$_GET['filter'];
$search = trim(@$_GET['search']);

$filters = array(
	'' => _("All"),
	'admission' => _("Admission"),
	'debate' => _("Debate"),
	'voting' => _("Voting"),
	'closed' => _("Closed")
);

foreach ( $filters as $key => $name ) {
	$params = array();
	if ($key) $params['filter'] = $key;
	if ($search) $params['search'] = $search;
?>
<a href="<?=uri($params)?>"<?
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

<table border="0" cellspacing="1" cellpadding="2" class="proposals">
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
while ($row = pg_fetch_assoc($result)) {
	$issue = new Issue($row);
	$issue->display_proposals();
}

?>
</table>

<?
$params = array();
if ($filter) $params['filter'] = $filter;
if ($search) $params['search'] = $search;
$pager->output(uri($params));
?>

<a href="proposal_edit.php"><?=_("Add proposal")?></a>
<?


html_foot();
