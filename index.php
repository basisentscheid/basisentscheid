<?
/**
 * index.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

html_head(TITLE, true);

?>

<div class="dates">
	<h2><?=_("Upcoming dates")?></h2>
	<table>
<?

$dates = array();
$info = array();

$sql = "SELECT * FROM period WHERE debate > now()";
$result = DB::query($sql);
while ( $period = DB::fetch_object($result, "Period") ) {
	$dates[] = $period->debate;
	$info[] = array("debate", $period);
}

$sql = "SELECT * FROM period WHERE voting > now()";
$result = DB::query($sql);
while ( $period = DB::fetch_object($result, "Period") ) {
	$dates[] = $period->voting;
	$info[] = array("voting", $period);
}

$sql = "SELECT * FROM period WHERE counting > now()";
$result = DB::query($sql);
while ( $period = DB::fetch_object($result, "Period") ) {
	$dates[] = $period->counting;
	$info[] = array("counting", $period);
}

asort($dates);

$line = 1;
foreach ( $dates as $index => $time ) {
	list($field, $period) = $info[$index];
?>
			<tr class="<?=stripes()?>">
				<td><?=datetimeformat_smart($time)?></td>
				<td><?=$period->ngroup()->name?></td>
				<td><?
	switch ($field) {
	case "debate":
		printf(_("debate in period %d starts"), $period->id);
		break;
	case "voting":
		printf(_("voting in period %d starts"), $period->id);
		break;
	case "counting":
		printf(_("voting in period %d closes"), $period->id);
		break;
	}
	?></td>
			</tr>
<?
	if (++$line > 10) break;
}

?>
	</table>
</div>

<div class="ngroups">
<h2 id="ngroups"><?=_("Groups")?></h2>
<table>
<?
if (Login::$member) {
	$sql = "SELECT ngroup.*, member_ngroup.member
		FROM ngroup
		LEFT JOIN member_ngroup ON ngroup.id = member_ngroup.ngroup AND member_ngroup.member = ".intval(Login::$member->id);
} else {
	$sql = "SELECT *
		FROM ngroup";
}
$sql .= " ORDER BY name";
$result = DB::query($sql);
$ngroups = array();
while ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
	$ngroups[] = $ngroup;
}
list($ngroups) = Ngroup::parent_sort_active_tree($ngroups);
foreach ($ngroups as $ngroup) {
	/** @var Ngroup $ngroup */
	if (Login::$member and $ngroup->member) {
?>
	<tr class="<?=stripes()?> own" title="<?=_("You are member of this group.")?>">
<?
		$own = true;
	} else {
?>
	<tr class="<?=stripes()?>">
<?
		$own = false;
	}
	if ($ngroup->active) {
?>
		<td class="name"><?
		$ngroup->display_list_name($own);
		?></td>
		<td class="proposals"><a href="proposals.php?ngroup=<?=$ngroup->id?>"><?=_("Proposals")?> (<?=$ngroup->proposals_count()?>)</a><?
		if ( $own and Login::$member->eligible and Login::$member->verified and $nyvic = $ngroup->not_yet_voted_issues_count() ) {
			?> <a href="proposals.php?ngroup=<?=$ngroup->id?>&filter=voting"><?=Ngroup::not_yet_voted($nyvic)?></a><?
		}
		?></td>
		<td><a href="periods.php?ngroup=<?=$ngroup->id?>"><?=_("Periods")?> (<?=$ngroup->periods_count()?>)</a></td>
		<td><a href="areas.php?ngroup=<?=$ngroup->id?>"><?=_("Areas")?></a></td>
<?
	} else {
?>
		<td class="name inactive" title="<?=_("This group does not participate.")?>"><?
		$ngroup->display_list_name($own);
		?></td>
		<td></td>
		<td></td>
		<td></td>
<?
	}
?>
	</tr>
<?
}
?>
</table>
</div>

<div class="clearfix"></div>
<?

html_foot();
