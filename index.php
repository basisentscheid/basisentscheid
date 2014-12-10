<?
/**
 * index.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

html_head("Willkommen auf der Testinstallation der Basisentscheid-Software!");

?>

<p>Ihr könnt hier gerne alles anschauen und ausprobieren. Lasst euch von den automatisch generierten Testdaten, die überall drin stehen, nicht irritieren. Sie machen es zwar teilweise etwas unübersichtlich, aber dafür kann man damit so ziemlich alle möglichen Zustände sehen.</p>

<p>Ihr könnt euch selbst einen <a href="create_member.php">Mitglieds-Account erstellen</a>. Um z.B. das Abstimmen auszuprobieren kann man sich als einer der bei einem Antrag eingetragenen Unterstützer einloggen, diese Test-Benutzer haben alle das Passwort "test". Zu den Funktionalitäten für die Verantwortlichen gibt es eine separate <a href="https://praktikabler-basisentscheid.piratenpad.de/4">Dokumentation</a>.</p>

<p>Wenn ihr Fehler findet, könnt ihr die in das <a href="https://praktikabler-basisentscheid.piratenpad.de/2">Pad <i>Bugs/Fehler</i></a> eintragen. Ihr müsst aber nicht unbedingt nach Fehlern suchen, es gibt sicherlich noch viele. Interessanter ist die Frage, ob alles so umgesetzt ist, wie wir es haben wollen. Welche Funktionen fehlen oder verhalten sich anders, als es vielleicht sinnvoller wäre? Wenn euch da etwas auffällt, könnt ihr das in das <a href="https://praktikabler-basisentscheid.piratenpad.de/1">Pad <i>Funktionalität</i></a> eintragen.</p>

<hr>

<?
help();
?>

<div class="dates">
	<h2><?=_("Upcoming dates")?></h2>
	<table>
<?

$dates = array();
$info = array();

$sql = "SELECT * FROM periods WHERE debate > now()";
$result = DB::query($sql);
while ( $period = DB::fetch_object($result, "Period") ) {
	$dates[] = $period->debate;
	$info[] = array("debate", $period);
}

$sql = "SELECT * FROM periods WHERE voting > now()";
$result = DB::query($sql);
while ( $period = DB::fetch_object($result, "Period") ) {
	$dates[] = $period->voting;
	$info[] = array("voting", $period);
}

$sql = "SELECT * FROM periods WHERE counting > now()";
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
$entitled = ( Login::$member and Login::$member->entitled );
if ($entitled) {
?>
	<tr>
		<th colspan="4"></th>
		<th><?=_("entitled")?></th>
	</tr>
<?
}
if ($entitled) {
	$sql = "SELECT ngroups.*, members_ngroups.member
		FROM ngroups
		LEFT JOIN members_ngroups ON ngroups.id = members_ngroups.ngroup AND members_ngroups.member = ".intval(Login::$member->id);
} else {
	$sql = "SELECT *
		FROM ngroups";
}
$sql .= " ORDER BY name";
$result = DB::query($sql);
$ngroups = array();
while ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
	$ngroups[] = $ngroup;
}
list($ngroups) = Ngroup::parent_sort_active_tree($ngroups);
foreach ($ngroups as $ngroup) {
?>
	<tr class="<?=stripes()?>">
<?
	if ($ngroup->active) {
?>
		<td><?=str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $ngroup->depth).$ngroup->name?></td>
		<td><a href="proposals.php?ngroup=<?=$ngroup->id?>"><?=_("Proposals")?> (<?=$ngroup->proposals_count()?>)</a></td>
		<td><a href="periods.php?ngroup=<?=$ngroup->id?>"><?=_("Periods")?> (<?=$ngroup->periods_count()?>)</a></td>
		<td><a href="areas.php?ngroup=<?=$ngroup->id?>"><?=_("Areas")?></a></td>
<?
	} else {
?>
		<td class="inactive"><?=str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $ngroup->depth).$ngroup->name?></td>
		<td></td>
		<td></td>
		<td></td>
<?
	}
	if ($entitled) {
?>
		<td class="center"><?
		if ($ngroup->member and $ngroup->active) {
			?>&#10003;<?
			if ( $nyvic = $ngroup->not_yet_voted_issues_count() ) {
				?> <a href="proposals.php?ngroup=<?=$ngroup->id?>&filter=voting"><?=Ngroup::not_yet_voted($nyvic)?></a><?
			}
		}
		?></td>
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
