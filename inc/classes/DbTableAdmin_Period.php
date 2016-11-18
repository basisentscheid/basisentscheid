<?
/**
 * DbTableAdmin_Period
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class DbTableAdmin_Period extends DbTableAdmin {


	/**
	 * table body
	 *
	 * overload method for line highlighting
	 *
	 * @param resource $result
	 * @param boolean  $direct_edit
	 * @param boolean  $show_edit_column
	 * @param integer  $linescount
	 */
	protected function display_list_tbody($result, $direct_edit, $show_edit_column, $linescount) {
?>
<tbody>
<?

		if ($this->enable_pager) {

			if (!isset($_GET['page']) and isset($_GET['hl'])) {
				// go to the page with the record to be highlighted
				$line = 0;
				while ( $object = DB::fetch_object($result, $this->classname) ) {
					if ($_GET['hl']==$object->id) {
						$this->pager->page = $this->pager->page_for_line($line);
						break;
					}
					$line++;
				}
			}

			$this->pager->seek($result);
			$line = $this->pager->firstline;
		} else {
			$line = 0;
		}
		while ( $object = DB::fetch_object($result, $this->classname) and (!$this->enable_pager or $line <= $this->pager->lastline) ) {
?>
	<tr class="<?=stripes($line);
			if (isset($_GET['hl']) and $_GET['hl']==$object->id) { ?> highlight<? }
			?>">
<?

			// use the submitted values instead of the the record from the database
			if ($direct_edit and isset($this->directedit_objects[$object->id])) {
				$object = $this->directedit_objects[$object->id];
			}

			foreach ( $this->columns as $column ) {
				if (isset($column[3]) and $column[3]===false) continue;
?>
		<td<? $this->cellclass($column) ?>><?

				$method = "print_".(!empty($column[3])?$column[3]:"text");
				if (method_exists($this, $method)) {
					$this->$method(
						// parameters for print methods:
						($column[0]?$object->$column[0]:null), // 1 content
						$object,                               // 2 object
						$column,                               // 3 column description (array)
						$line,                                 // 4 line number (starting at 0)
						$linescount                            // 5 count of lines selected in the database
					);
				} else {
					$method = "dbtableadmin_".$method;
					$object->$method(
						// parameters for print methods:
						($column[0]?$object->$column[0]:null), // 1 content
						$column,                               // 2 column description (array)
						$line,                                 // 3 line number (starting at 0)
						$linescount                            // 4 count of lines selected in the database
					);
				}
				?></td>
<?
			}

			// right column for edit, delete, duplicate
			if ($show_edit_column) {
?>
		<td class="nowrap">
<?
				// edit
				if ($this->enable_edit) {
?>
			<a href="<?=URI::append(['id'=>$object->id])?>" class="iconlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>></a>
<?
				}
				// duplicate
				if ($this->enable_duplicate) {
?>
			<input type="button" value="<?=_("duplicate")?>" onclick="submit_button('duplicate', <?=$object->id?>);">
<?
				}
				// delete
				if ( $this->enable_delete_single or $this->enable_delete_checked ) {
					$this->display_list_delete($object);
				}
?>
		</td>
<?
			}

?>
	</tr>
<?
			$line++;
		} // end while

?>
</tbody>
<?
	}


	/**
	 * link to ballots page
	 *
	 * @param mixed   $content
	 * @param object  $object
	 */
	protected function print_ballots(/** @noinspection PhpUnusedParameterInspection */ $content, $object) {
		if (!$object->ballot_voting) return;
		?><a href="ballots.php?period=<?=$object->id?>"><?=_("Ballots")?></a><?
	}


	/**
	 * checkbox for postage
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 */
	protected function edit_postage($colname, $default) {
		if ($default) {
			input_checkbox($colname, "1", true, true);
			input_hidden($colname, 1);
		} else {
			input_checkbox($colname, "1", false);
		}
		?><div class="comment"><?=_("Postage has started, choice for postal voting can not be changed any longer. Also this option itself can not be deactivated anymore once it has been activated.")?></div><?
	}


	/**
	 * check input
	 *
	 * @return boolean
	 */
	protected function beforesave() {

		$debate = strtotime($this->object->debate);
		if (!$debate) {
			warning(_("The debate time is not valid!"));
			return false;
		}
		$this->object->debate = date("c", $debate);

		$preparation = strtotime($this->object->preparation);
		if (!$preparation) {
			warning(_("The preparation time is not valid!"));
			return false;
		}
		$this->object->preparation = date("c", $preparation);

		$voting = strtotime($this->object->voting);
		if (!$voting) {
			warning(_("The voting time is not valid!"));
			return false;
		}
		$this->object->voting = date("c", $voting);

		$counting = strtotime($this->object->counting);
		if (!$counting) {
			warning(_("The counting time is not valid!"));
			return false;
		}
		$this->object->counting = date("c", $counting);

		if ($debate >= $preparation) {
			warning(_("The debate must start before the preparation!"));
			return false;
		}
		if ($preparation >= $voting) {
			warning(_("The preparation must start before the voting!"));
			return false;
		}
		if ($voting >= $counting) {
			warning(_("The voting must start before the counting!"));
			return false;
		}

		$ballot_assignment  = $this->object->ballot_assignment;
		$ballot_preparation = $this->object->ballot_preparation;
		if ($ballot_assignment) {
			$ballot_assignment = strtotime($this->object->ballot_assignment);
			if (!$ballot_assignment) {
				warning(_("The ballot assignment time is not valid!"));
				return false;
			}
			$this->object->ballot_assignment = date("c", $ballot_assignment);
		}
		if ($ballot_preparation) {
			$ballot_preparation = strtotime($this->object->ballot_preparation);
			if (!$ballot_preparation) {
				warning(_("The ballot preparation time is not valid!"));
				return false;
			}
			$this->object->ballot_preparation = date("c", $ballot_preparation);
		}

		if ($ballot_assignment and $ballot_preparation and $ballot_assignment >= $ballot_preparation) {
			warning(_("The ballot assignment must start before the ballot preparation!"));
			return false;
		}
		if ($ballot_preparation and $ballot_preparation > $counting) {
			warning(_("The ballot preparation must start before the counting!"));
			return false;
		}

		if ($this->object->ballot_voting and (!$ballot_assignment or !$ballot_preparation)) {
			warning(_("If ballot voting is activated, also the ballot assignment and preparation times have to be entered!"));
			return false;
		}

		if ($this->object->id and !$this->object->ballot_voting) {
			$sql = "SELECT id FROM ballot WHERE period=".intval($this->object->id);
			$result = DB::query($sql);
			if (DB::num_rows($result)) {
				warning(_("Since there are already ballot applications you can not deactivate ballot voting!"));
				return false;
			}
		}

		return true;
	}


}
