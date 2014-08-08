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
		if ($period->state=="ballot_preparation") {
			warning(_("In ballot preparation phase it is not allowed anymore to select or change the ballot."));
			redirect();
		}
		if ($period->state=="ballot_assignment" and !$ballot->approved) {
			warning(_("In ballot assignment phase it is only allowed to select approved ballots."));
			redirect();
		}
		$period->select_ballot($ballot);
		redirect();
		break;
	case "unselect":
		Login::access_action("member");
		if ($period->state=="ballot_preparation") {
			warning(_("In ballot preparation phase it is not allowed anymore to change the ballot choice."));
			redirect();
		}
		$period->unselect_ballot();
		redirect();
		break;
	case "save_approved":
		Login::access_action("admin");
		action_required_parameters('approved_id');
		if ($period->state!="ballot_application") {
			warning(_("In the current phase of the period it is not allowed anymore to approve ballots."));
			redirect();
		}
		$period->deactivate_participation();
		redirect();
		break;
	default:
		warning("Unknown action");
		redirect();
	}
}


html_head(strtr(_("Ballots for voting period %period%"), array('%period%'=>$period->id)));

?>

<p><?=$period->ballot_phase_info()?></p>

<div class="tableblock">
<?

if (Login::$member and $period->state=="ballot_application") {
?>
<div class="add_record"><a href="ballot_edit.php?period=<?=$period->id?>"><?=_("Apply to operate a ballot")?></a></div>
<?
}

if (Login::$admin) {
	form(URI::$uri);
}

$colspan = 6;
?>

<table border="0" cellspacing="1">
	<tr>
		<th><?=_("No.")?></th>
		<th><?=_("Name")?></th>
		<th><?=_("Opening")?></th>
		<th><?=_("Agents")?></th>
		<th><?=_("Voters")?></th>
<? if (Login::$member) { $colspan++; ?>
		<th><?=_("My ballot")?></th>
<? } ?>
		<th><?=_("Approved")?></th>
	</tr>
<?

$pager = new Pager;

$sql = "SELECT * FROM ballots	WHERE period=".DB::m($period->id)." ORDER BY ballots.id";
$result = DB::query($sql);
$pager->seek($result);
if (!$pager->linescount) {
?>
	<tr class="td0"><td colspan="<?=$colspan?>" align="center"><?
	if ($period->state=="ballot_application") {
		echo _("There are no applications for ballots yet.");
	} else {
		echo _("There were no applications for ballots.");
	}
	?></td></tr>
<?
} else {

	$line = $pager->firstline;
	while ( $ballot = DB::fetch_object($result, "Ballot") and $line <= $pager->lastline ) {
?>
	<tr class="<?=stripes()?>">
		<td align="right"><?=$ballot->id?></td>
		<td><?=h($ballot->name)?></td>
		<td align="center"><?=timeformat($ballot->opening)?></td>
		<td><?=h($ballot->agents)?></td>
		<td align="center"><?=$ballot->voters?></td>
<?
		if (Login::$member) {
?>
		<td>
<?
			if ($row_voters['ballot']==$ballot->id) {
?>
				&#10003;
<?
				if ($row_voters['agent']=="t") {
					?><?=_("You are agent for this ballot.")?><?
				} else {
					?><?=_("You selected this ballot for voting.")?><?
				}
				if ($period->state!="ballot_preparation") {
					form(URI::$uri, 'class="button"');
?>
<input type="hidden" name="action" value="unselect">
<input type="submit" value="<?=_("remove selection")?>">
</form>
<?
				}
			} elseif (
				// don't show select buttons if the member is agent for some ballot
				$row_voters['agent']!="t" and (
					$period->state=="ballot_application" or
					($period->state=="ballot_assignment" and $ballot->approved)
				)
			) {
				form(URI::$uri, 'class="button"');
?>
<input type="hidden" name="ballot" value="<?=$ballot->id?>">
<input type="hidden" name="action" value="select">
<input type="submit" value="<?=_("select this ballot for voting")?>">
</form>
<?
			}
?>
		</td>
<?
		}
?>
		<td align="center"><?
		if (Login::$admin and $period->state=="ballot_application") {
			?><input type="checkbox" name="approved[<?=$ballot->id?>]" value="1"<? if ($ballot->approved) { ?> checked<? } ?>><input type="hidden" name="approved_id[<?=$ballot->id?>]" value="<?=$ballot->id?>"><?
		} else {
			echo boolean($ballot->approved);
		}
		?></td>
	</tr>
<?
		$line++;
	}

}

?>
</table>

<?

if (Login::$admin and $period->state=="ballot_application" and $pager->linescount) {
?>
<input type="hidden" name="action" value="save_approved">
<input type="submit" value="<?=_("apply approved")?>">
</form>
<?
}

$pager->msg_itemsperpage = _("Ballots per page");
$pager->display();

?>
</div>
<?

html_foot();
