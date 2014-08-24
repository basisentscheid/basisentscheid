<?
/**
 * index.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


if ($action) {
	Login::access_action("member");
	action_required_parameters('ngroup');
	$ngroup = new Ngroup($_POST['ngroup']);
	if (!$ngroup->id) {
		warning("The requested group does not exist!");
		redirect();
	}
	switch ($action) {
	case "subscribe":
		$ngroup->activate_participation();
		redirect();
		break;
	case "unsubscribe":
		$ngroup->deactivate_participation();
		redirect();
		break;
	}
	warning(_("Unknown action"));
	redirect();
}


html_head("Willkommen auf dem Testserver des Basisentscheid-Portals!");

?>

<p>Ihr könnt hier gerne alles anschauen und ausprobieren. Lasst euch von den automatisch generierten Testdaten, die überall drin stehen, nicht irritieren. Sie machen es zwar teilweise etwas unübersichtlich, aber dafür kann man damit so ziemlich alle möglichen Zustände sehen.</p>

Es gibt drei Möglichkeiten zur Anmeldung:
<ul>
	<li>als normales Mitglied mit dem Button "anmelden" rechts oben (über den ID-Server) <i>Username: test, Passwort: test</i>
	<li><a href="admin.php">als Verantwortlicher</a> <i>Username: test, Passwort: test</i>
	<li><a href="local_member_login.php">als lokales Mitglied</a> – Damit kann man sich ohne Passwort als normales Mitglied mit	beliebigem Benutzernamen anmelden. Dieses	Mitglied wird dann automatisch angelegt. Das ist natürlich nur zum Testen gedacht und wird für ein Live-System wieder entfernt.
</ul>

<p>Wenn ihr Fehler findet, könnt ihr die in das <a href="https://github.com/basisentscheid/portal/issues">Ticketsystem</a> eintragen. Ihr müsst aber nicht unbedingt nach Fehlern suchen, es gibt sicherlich noch viele. Interessanter ist die Frage, ob alles so umgesetzt ist, wie wir es haben wollen. Welche Funktionen fehlen oder verhalten sich anders, als es vielleicht sinnvoller wäre? Wenn euch da etwas auffällt, könnt ihr das an die <a href="https://lists.piratenpartei-bayern.de/mailman/listinfo/basisentscheid">Mailingliste der Projektgruppe Basisentscheid</a> schicken, <a href="http://wiki.piratenpartei.de/Benutzer:Cmrcx">mir mailen oder mich anrufen</a>, oder – sofern es etwas konkretes ist – es auch in das <a href="https://github.com/basisentscheid/portal/issues">Ticketsystem</a> eintragen.</p>

Folgende größere Dinge fehlen jedenfalls noch:
<ul>
	<li>Anbindung an das noch nicht fertige Abstimmungsmodul</li>
	<li>Urnenzuordung anhand der Gliederung</li>
</ul>

<h2>Informationen zum Basisentscheid</h2>

<p><a href="http://wiki.piratenpartei.de/Basisentscheid">http://wiki.piratenpartei.de/Basisentscheid</a><br>
<a href="http://basisentscheid.piratenpartei.de/">http://basisentscheid.piratenpartei.de/</a><br>
<a href="http://wiki.piratenpartei.de/Satzung#.C2.A7_16_-_Basisentscheid_und_Basisbefragung">http://wiki.piratenpartei.de/Satzung#.C2.A7_16_-_Basisentscheid_und_Basisbefragung</a><br>
<a href="http://wiki.piratenpartei.de/Entscheidsordnung">http://wiki.piratenpartei.de/Entscheidsordnung</a></p>

<hr>

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
				<td><?=datetimeformat($time)?></td>
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
<h2><a name="ngroups" class="anchor"></a><?=_("Groups")?></h2>
<table>
<?
$entitled = ( Login::$member and Login::$member->entitled );
if ($entitled) {
?>
	<tr>
		<th colspan="4"></th>
		<th><?=_("entitled")?></th>
		<th><?=_("Participation")?></th>
	</tr>
<?
}
if ($entitled) {
	$sql = "SELECT ngroups.*, members_ngroups.member, members_ngroups.participant
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
		<td><a href="proposals.php?ngroup=<?=$ngroup->id?>"><?=_("proposals")?></a></td>
		<td><a href="periods.php?ngroup=<?=$ngroup->id?>"><?=_("periods")?></a></td>
		<td><a href="areas.php?ngroup=<?=$ngroup->id?>"><?=_("areas")?></a></td>
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
		if ($ngroup->member and $ngroup->active) {
?>
		<td class="center">&#10003;</td>
		<td>
<?
			if ($ngroup->participant) {
?>
&#10003; <?=_("last time activated")?>: <?=dateformat($ngroup->participant)?>
<?
				form(URI::same()."#ngroups", 'class="button"');
?>
<input type="hidden" name="ngroup" value="<?=$ngroup->id?>">
<input type="hidden" name="action" value="unsubscribe">
<input type="submit" value="<?=_("unsubscribe")?>">
<?
				form_end();
			}
			form(URI::same()."#ngroups", 'class="button"');
?>
<input type="hidden" name="ngroup" value="<?=$ngroup->id?>">
<input type="hidden" name="action" value="subscribe">
<input type="submit" value="<?=$ngroup->participant?_("subscribe anew"):_("subscribe")?>">
<?
			form_end();
		} else {
?>
		<td></td>
		<td>
<?
		}
?>
		</td>
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
