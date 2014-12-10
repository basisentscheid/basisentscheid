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

$ngroup = $period->ngroup;
$_SESSION['ngroup'] = $ngroup;

if (!$period->ballot_voting) {
	warning("There is no ballot voting available in this period!");
	redirect("periods.php?ngroup=".$ngroup."&hl=".$period->id);
}

if (Login::$member) {
	$sql = "SELECT * FROM offlinevoters WHERE member=".intval(Login::$member->id)." AND period=".intval($period->id);
	if ( $row_voters = DB::fetchassoc($sql) ) {
		DB::to_bool($row_voters['agent']);
	}
}

if ($action) {
	switch ($action) {
	case "select":
		Login::access_action("entitled", $ngroup);
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
	case "select_postal":
		Login::access_action("entitled", $ngroup);
		if ($period->state=="ballot_preparation") {
			warning(_("In ballot preparation phase it is not allowed anymore to select postal voting."));
			redirect();
		}
		$period->select_postal();
		redirect();
		break;
	case "unselect":
		Login::access_action("entitled", $ngroup);
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
		$period->save_approved_ballots();
		redirect();
		break;
	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head( sprintf(_("Ballots for <a%s>voting period %d</a>"), ' href="periods.php?ngroup='.$ngroup.'&amp;hl='.$period->id.'"', $period->id) );

help();

$entitled = ( Login::$member and Login::$member->entitled($ngroup) );
?>

<p><?=$period->ballot_phase_info()?></p>

<div class="tableblock">
<?

// don't show select buttons if the member is agent for some ballot
$show_select = ( $entitled and (!$row_voters or !$row_voters['agent']) and !$period->postage() );

if ($show_select and $entitled and $period->state=="ballot_application") {
?>
<div class="add_record"><a href="ballot_edit.php?period=<?=$period->id?>" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Apply to operate a ballot")?></a></div>
<?
}

if (Login::$admin) {
	form(URI::$uri);
}

$colspan = 7;
?>

<table>
	<tr>
		<th><?=_("No.")?></th>
		<th><?=_("Name")?></th>
		<th><?=_("Group")?></th>
		<th><?=_("Opening hours")?></th>
		<th><?=_("Agents")?></th>
		<th><?=_("Voters")?></th>
<? if ($entitled) { $colspan++; ?>
		<th><?=_("My ballot")?></th>
<? } ?>
		<th><?=_("Approved")?></th>
	</tr>
<?

$pager = new Pager;

$sql = "SELECT * FROM ballots	WHERE period=".DB::esc($period->id)." ORDER BY ballots.id";
$result = DB::query($sql);
$pager->seek($result);
if (!$pager->linescount) {
?>
	<tr class="td0"><td colspan="<?=$colspan?>" class="center"><?
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
		<td class="right"><?=$ballot->id?></td>
		<td><?=h($ballot->name)?></td>
		<td><?=$ballot->ngroup()->name?></td>
		<td class="center"><?=timeformat($ballot->opening)?> &ndash; <?=BALLOT_CLOSE_TIME?></td>
		<td><?=h($ballot->agents)?></td>
		<td class="center"><?=$ballot->voters?></td>
<?
		if ($entitled) {
?>
		<td>
<?
			if ($row_voters and $row_voters['ballot']==$ballot->id) {
				if ($row_voters['agent']) {
					?><a href="ballot_edit.php?id=<?=$ballot->id?>" class="icontextlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>><?=_("You are agent for this ballot.")?></a><?
				} else {
					?>&#10003; <?=_("You selected this ballot for voting.");
				}
				if ($period->state!="ballot_preparation") {
					form(URI::$uri, 'class="button"');
?>
<input type="hidden" name="action" value="unselect">
<input type="submit" value="<?=_("remove selection")?>">
<?
					form_end();
				}
			} elseif (
				$show_select and (
					$period->state=="ballot_application" or
					($period->state=="ballot_assignment" and $ballot->approved)
				)
			) {
				form(URI::$uri, 'class="button"');
?>
<input type="hidden" name="ballot" value="<?=$ballot->id?>">
<input type="hidden" name="action" value="select">
<input type="submit" value="<?=_("select this ballot for voting")?>">
<?
				form_end();
			}
?>
		</td>
<?
		}
?>
		<td class="center"><?
		if (Login::$admin and $period->state=="ballot_application") {
			?><input type="checkbox" name="approved[<?=$ballot->id?>]" value="1"<? if ($ballot->approved) { ?> checked<? } ?>><input type="hidden" name="approved_id[<?=$ballot->id?>]" value="<?=$ballot->id?>"><?
		} else {
			display_checked($ballot->approved);
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
<?
	form_end();
}

$pager->msg_itemsperpage = _("Ballots per page");
$pager->display();

?>
</div>
<div class="clearfix"></div>
<?


// postal voting
if ($entitled) {
	if ($row_voters and $row_voters['ballot']===null) {
?>
<h2 class="postal"><?=_("Postal voting")?></h2>
<div class="postal">
&#10003; <?=_("You selected postal voting.");
		if ($period->state!="ballot_preparation") {
			if ($period->postage()) {
				?> <?=_("The sending of the letters has already started, so you can not change your choice any longer.");
			} else {
				form(URI::$uri, 'class="button"');
?>
<input type="hidden" name="action" value="unselect">
<input type="submit" value="<?=_("remove selection")?>">
<?
				form_end();
			}
		}
?>
</div>
<?
	} elseif ($period->state=="ballot_application" or $period->state=="ballot_assignment") {
?>
<h2 class="postal"><?=_("Postal voting")?></h2>
<div class="postal">
<?
		if ($show_select) {
			form(URI::$uri, 'class="button"');
?>
<input type="hidden" name="action" value="select_postal">
<input type="submit" value="<?=_("select postal voting")?>">
<?
			if ($period->postage()) {
				?> <?=_("The postage has already started, so if you choose postal voting once, you can not change this choice any longer.");
			}
			form_end();
		} else {
			echo _("As an agent for a ballot you have to vote at that ballot and can not select postal voting at the same time.");
		}
?>
</div>
<?
	}
}


html_foot();
