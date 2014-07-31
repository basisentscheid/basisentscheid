<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";


$period = new Period(@$_GET['period']);
if (!$period->id) {
	error("The requested period does not exist!");
}

if (Login::$member) {
	$sql = "SELECT * FROM voters WHERE member=".intval(Login::$member->id)." AND period=".intval($period->id);
	$row_voters = DB::fetchassoc($sql);
}

if ($action) {
	switch ($action) {
	case "select":
		Login::access_action("member");
		action_required_parameters('ballot');
		$ballot = new Ballot($_POST['ballot']);
		if (!$ballot->id) {
			warning("The requested area does not exist!");
			redirect();
		}
		$ballot->select();
		redirect();
		break;
	case "unselect":
		Login::access_action("member");
		Ballot::unselect($period->id);
		redirect();
		break;
	case "save_approved":
		Login::access_action("admin");
		action_required_parameters('approved_id');
		$period->save_approved_ballots();
		redirect();
		break;
	default:
		warning("Unknown action");
		redirect();
	}
}


html_head(strtr(_("Ballots for voting period %period%"), array('%period%'=>$period->id)));

?>
<div class="tableblock">
<?

if (Login::$member) {
?>
<div class="add_record"><a href="ballot_edit.php?period=<?=$period->id?>"><?=_("Apply to operate a ballot")?></a></div>
<?
}

if (Login::$admin) {
?>
<form action="<?=URI::$uri?>" method="POST">
<?
}

?>

<table border="0" cellspacing="1">
	<tr>
		<th><?=_("No.")?></th>
		<th><?=_("Name")?></th>
		<th><?=_("Opening")?></th>
		<th><?=_("Agents")?></th>
		<th><?=_("Voters")?></th>
		<th><?=_("Approved")?></th>
	</tr>
<?

$pager = new Pager;

$sql = "SELECT * FROM ballots	WHERE period=".DB::m($period->id)." ORDER BY ballots.id";
$result = DB::query($sql);
$pager->seek($result);
$line = $pager->firstline;
while ( $row = pg_fetch_assoc($result) and $line <= $pager->lastline ) {
	$ballot = new Ballot($row);
?>
	<tr class="<?=stripes()?>">
		<td align="right"><?=$ballot->id?></td>
		<td><?=h($ballot->name)?></td>
		<td align="center"><?=timeformat($ballot->opening)?></td>
		<td><?=h($ballot->agents)?></td>
		<td><?
	echo $ballot->voters;
	if (Login::$member) {
		if ($row_voters['ballot']==$ballot->id) {
?>
				&#10003;
<?
			if ($row_voters['agent']=="t") { ?><?=_("You are agent for this ballot.")?><? } else { ?><?=_("You selected this ballot for voting.")?><? }
?>
<form action="<?=URI::$uri?>" method="POST" class="button">
<input type="hidden" name="action" value="unselect">
<input type="submit" value="<?=_("remove selection")?>">
</form>
<?
		} elseif ($row_voters['agent']!="t") { // don't show select buttons if the member is agent for some ballot
?>
<form action="<?=URI::$uri?>" method="POST" class="button">
<input type="hidden" name="ballot" value="<?=$ballot->id?>">
<input type="hidden" name="action" value="select">
<input type="submit" value="<?=_("select this ballot for voting")?>">
</form>
<?
		}
	}
	?></td>
		<td align="center"><?
	if (Login::$admin) {
		?><input type="checkbox" name="approved[<?=$ballot->id?>]" value="1"<? if ($ballot->approved) { ?> checked<? } ?>><input type="hidden" name="approved_id[<?=$ballot->id?>]" value="<?=$ballot->id?>"><?
	} else {
		echo boolean($ballot->approved);
	}
	?></td>
	</tr>
<?
	$line++;
}

?>
</table>

<?

if (Login::$admin) {
?>
<input type="hidden" name="action" value="save_approved">
<input type="submit" value="<?=_("apply approved")?>">
</form>
<?
}

$pager->display();

?>
</div>
<?

html_foot();
