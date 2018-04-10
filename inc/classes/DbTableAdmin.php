<?
/**
 * html table to edit the content of a database table
 *
 * example:
 *
 * require "inc/common_http.php";
 * $d = new DbTableAdmin("Example");
 * $d->columns = array(
 *     array("id", _("ID"), "right", "", false, 'type'=>"integer"),
 *     array("name", _("Name"))
 * );
 * $d->action($action);
 * html_head(_("Examples"));
 * $d->display();
 * html_foot();
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class DbTableAdmin {

	// configuration attributes

	/**
	 * list of columns, which are displayed and edited
	 *
	 * $column = array(
	 *   0 => (string) database column name, false for database independent content
	 *   1 => (string) title
	 *   optional:
	 *   2 => (string) css classes (e.g. "nowrap center")
	 *   3 => (string) print method, "" for default, false to hide the column
	 *   4 => (string) edit  method, "" for default, false to hide the column
	 *   assoc:
	 *   'beforesave' => (string)  beforesave method
	 *   'type'       => (string)  type (default: string)
	 *   'null'       => (boolean) save empty values as NULL
	 *   'noorder'    => (boolean) no order link and arrows on column head
	 *   'nosearch'   => (boolean) no searching in this column
	 * );
	 *
	 * @var array
	 */
	public $columns;

	/**
	 * default column for ORDER BY
	 *
	 * @var string
	 */
	public $order_default = "id";

	/**
	 * default to ORDER BY ... DESC
	 *
	 * @var boolean
	 */
	public $orderdesc_default = false;

	// functionality
	public $enable_insert = true;
	public $enable_edit   = true;
	public $enable_delete_single  = true;
	public $enable_delete_checked = false; // delete using a column of checkboxes
	public $enable_duplicate    = false;

	// pager
	public $enable_pager = true;
	public $itemsperpage_default;

	/**
	 * Pager object (for changing settings)
	 *
	 * @var object
	 */
	public $pager;

	/**
	 * use a filter on the list page
	 *
	 * @var boolean
	 */
	public $enable_filter = true;

	/**
	 * Filter object (for changing settings)
	 *
	 * @var object
	 */
	public $filter;

	/**
	 * will be added at every WHERE and INSERT to show and edit only certain records
	 *
	 * e.g. array('column1'=>'value1', 'column2'=>'value2')
	 *
	 * @var array
	 */
	public $global_where = array();

	/**
	 * SQL statements to find records in other tables which reference a record in the current table
	 *
	 * The wildcard "%d" will be replaced by the ID of the record of the current table.
	 *
	 * @var array
	 */
	public $reference_check = array();

	/**
	 * javascript function to check input on edit page
	 *
	 * @var string
	 */
	public $js_submit_edit = "true";

	/**
	 * javascript function to check direct edit input on list page
	 *
	 * @var string
	 */
	public $js_submit_directedit = "true";

	/**
	 * name of the database table
	 *
	 * Has to be set only if it's not the class name in lower case.
	 *
	 * @var string
	 */
	public $dbtable;

	// internal attributes

	/**
	 * ID of the current record when on edit page
	 *
	 * @var integer
	 */
	protected $id = 0;

	/**
	 * object of the current record when on edit page
	 *
	 * @var object
	 */
	protected $object;

	/**
	 * objects of rejected records at direct editing on the list page
	 *
	 * @var object
	 */
	protected $directedit_objects = array();

	/**
	 * name of the class corresponing to the database table
	 *
	 * @var string
	 */
	protected $classname;

	protected $order;
	protected $orderdesc;


	/**
	 *
	 * @param string  $classname name of the class corresponing to the database table
	 */
	public function __construct($classname) {

		$this->classname = $classname;
		$this->dbtable = strtolower($this->classname);

		$this->id = intval(@$_GET['id']);

		// instance classes already here to make them available for changing settings
		if ($this->enable_filter) {
			$this->filter = new Filter();
		}
		if ($this->enable_pager) {
			if ($this->itemsperpage_default) {
				$this->pager = new Pager($this->itemsperpage_default);
			} else {
				$this->pager = new Pager;
			}
		}

		// default messages
		$this->msg_add_record              = _("New record");
		$this->msg_edit_record             = _("Edit record %id%");
		$this->msg_record_saved            = _("The new record %id% has been saved.");
		$this->msg_remaining_records_saved = _("The changes of the remaining records have been saved.");
		$this->msg_really_delete           = _("Do you really want to delete the record %id%?");
		$this->msg_really_delete_multiple  = _("Do you really want to delete %count% records?");
		$this->msg_record_deleted          = _("The record %id% has been deleted.");
		$this->msg_record                  = _("Record");
		$this->msg_no_record_available     = _("no record available for this view");

	}


	// content:
	// - action
	// - display
	// - helper
	// - print
	// - edit


	/**
	 * actions
	 *
	 * @param string  $action
	 */
	public function action($action) {

		// page called without action
		if (!$action) return;

		switch ($action) {

		case "delete":
			if (!$this->enable_delete_single) {
				error("Action not allowed");
			}
			action_required_parameters('id');
			$this->delete($_POST['id']);
			redirect();

		case "duplicate":
			if (!$this->enable_duplicate) {
				error("Action not allowed");
			}
			action_required_parameters('id');
			$this->duplicate($_POST['id']);
			redirect();

		case "moveup":
		case "movedown":
		case "movefirst":
		case "movelast":
			action_required_parameters('id');
			$this->action_manualorder($action, $_POST['id']);
			redirect();

		case "editsubmit":

			if ($this->id) {

				// update existing record

				if (!$this->enable_edit) {
					error("Action not allowed");
				}

				$this->object = new $this->classname($this->id);
				if (!$this->object->id) {
					warning("The record to be updated does not exist!");
					return;
				}

				$columns = $this->convert_input($this->object, $_POST);
				if ($columns===false) return;

				if ( $this->object->update($columns) ) {
					success(_("The changes have been saved."));
					if ($this->object->id and method_exists($this->object, "dbtableadmin_after_edit") ) {
						$this->object->dbtableadmin_after_edit($this->object);
					}
				}

				$this->redirect_to_list();
			}

			// insert new record

			if (!$this->enable_insert) {
				error("Action not allowed");
			}

			$this->object = new $this->classname;

			$columns = $this->convert_input($this->object, $_POST);
			if ($columns===false) return;

			foreach ($this->global_where as $key => $value) {
				$this->object->$key = $value;
				$columns[] = $key;
			}

			if ( $this->object->create($columns) ) {
				success($this->msg_strtr($this->msg_record_saved, array('id'=>$this->object->id)));
				if ($this->object->id and method_exists($this->object, "dbtableadmin_after_create") ) {
					$this->object->dbtableadmin_after_create($this->object);
				}
			}

			$this->redirect_to_list();
		}

		// actions on the list page ///////////////////
		// handle action from multiple submit buttons
		if (is_array($action)) {

			// get $action_name from the $_POST['action'] array
			// example:
			// $_POST => Array (
			//   ['action'] => Array (
			//     ['delete_checked'] => 'delete checked'
			//   )
			// )
			if (count($action) != 1) {
				error("Parameter with invalid value");
			}
			foreach ( $action as $action_name => $dummy ) {}
			/** @noinspection PhpUndefinedVariableInspection */
			switch ($action_name) {

			case "delete_checked":
				if (!$this->enable_delete_checked) {
					error("Action not allowed");
				}
				if (isset($_POST["delete"]) and is_array($_POST["delete"])) {
					foreach ( $_POST["delete"] as $id ) {
						$this->delete($id);
					}
				}
				redirect();

			case "apply_directedit":
				if (!$this->enable_edit) {
					error("Action not allowed");
				}
				action_required_parameters('directedit_key');
				if (!is_array($_POST['directedit_key'])) {
					error("Parameter has wrong type");
				}
				if (!count($_POST['directedit_key'])) {
					redirect();
				}

				$saved = 0;
				$failed = 0;
				foreach ( $_POST['directedit_key'] as $id => $columnarray ) {

					if ( !is_array($columnarray) or !count($columnarray) ) continue;

					$object = new $this->classname($id);
					/** @var Relation $object */
					if (!$object->id) {
						warning(_("One of the records to be updated does not exist!"));
						continue;
					}

					$save_columns = array();
					foreach ( $columnarray as $colname => $key_value ) {
						$save_columns[] = $colname;
					}
					$msg_prefix = $this->msg_record." ".$object->id.": ";
					$columns = $this->convert_input($object, @$_POST['directedit'][$object->id], $save_columns, $msg_prefix);
					if ($columns===false) {
						// save the rejected object to fill the direct edit form fields again
						$this->directedit_objects[$object->id] = $object;
						$failed++;
						continue;
					}

					if ($object->update($save_columns)) $saved++; else $failed++;

				}
				if ($saved) {
					if ($failed) {
						success($this->msg_remaining_records_saved);
					} else {
						success(_("The changes have been saved."));
					}
				}
				redirect();

			}

		}

		warning(_("Unknown action"));
		redirect();
	}


	/**
	 *
	 */
	protected function redirect_to_list() {
		redirect(URI::strip(["id"], true));
	}


	/**
	 * delete one record
	 *
	 * @param integer $id
	 */
	protected function delete($id) {

		$object = new $this->classname($id);
		/** @var Relation $object */

		// If the record does not exists, we don't have to delete it (again).
		if (!$object->id) return;

		if ($this->reference_check($object)) {
			warning(sprintf(_("The record %d can not be deleted!"), $object->id));
			return;
		}

		if ($object->delete()) {
			success($this->msg_strtr($this->msg_record_deleted, $object));
			if ( method_exists($this, "after_delete")) {
				$this->after_delete($object);
			}
		}

	}


	/**
	 * duplicate record
	 *
	 * @param integer $id
	 */
	protected function duplicate($id) {

		$object = new $this->classname($id);
		/** @var Relation $object */
		if (!$object->id) {
			warning(_("The record to be duplicated does not exist."));
			return;
		}

		$save_columns = array();
		foreach ( $this->columns as $column ) {

			// skip columns without a name
			if (!$column[0]) continue;

			// don't duplicate the PRIMARY KEY
			if ($column[0]=="id") continue;

			$save_columns[] = $column[0];
		}

		if ($object->create($save_columns)) {
			success(sprintf(_("The record %d has been duplicated. ID of the copy: %d"), $id, $object->id));
			if ( method_exists($this, "after_duplicate") ) {
				$this->after_duplicate($this->object);
			}
		}

	}


	/**
	 * change manual order
	 *
	 * needs a database column:
	 *   `manualorder` smallint(6) NOT NULL DEFAULT '0'
	 * column description:
	 *   array("manualorder", _("Order"), "", "manualorder", false)
	 *
	 * @param string  $action
	 * @param integer $id      ID of the record to be moved
	 * @param string  $where   (optional) WHERE part of the SQL statement
	 * @param string  $colname (optional) name of the database column to use
	 */
	protected function action_manualorder($action, $id, $where="", $colname="manualorder") {

		// renumber records
		$sql = "SELECT ".$this->dbtable.".id FROM ".$this->dbtable." ".$where." ORDER BY ".$this->dbtable.".".DB::ident($colname);
		$renumids = DB::fetchfieldarray($sql);

		$aktindex = array_search($id, $renumids);

		switch ($action) {
		case "movefirst":
			$renumids[$aktindex] = "";     // delete occurrence of the current ID
			array_unshift($renumids, $id); // set the current ID to the beginning of the array
			break;
		case "movelast":
			$renumids[$aktindex] = "";     // delete occurrence of the current ID
			$renumids[] = $id;             // set the current ID to the end of the array
			break;
		case "moveup":
		case "movedown":

			if ($action=="moveup") $swapindex = $aktindex - 1;
			else                   $swapindex = $aktindex + 1;

			// If the order was changed in between, it could be that there is no record, which could be exchanged with the current one.
			if (!isset($renumids[$swapindex])) return;

			$aktvalue = $id;
			$swapvalue = $renumids[$swapindex];

			$renumids[$swapindex] = $aktvalue;
			$renumids[$aktindex] = $swapvalue;

			break;
		default:
			return;
		}

		$i = -32760;
		foreach ( $renumids as $renumid ) {
			if ($renumid) {
				DB::query("UPDATE ".$this->dbtable." SET ".$colname."=".$i." WHERE id=".$renumid);
				++$i;
			}
		}

	}


	/**
	 * copy the POST input into the object and make a list of columns to save
	 *
	 * @param object  $object
	 * @param array   $post          $_POST or a subset of it
	 * @param mixed   $input_columns (optional) columns to be saved, false = all columns
	 * @param string  $msg_prefix    (optional) record number notice
	 * @return array                 array of columns to save or false if input is rejected by beforesave method
	 */
	protected function convert_input($object, array $post, $input_columns=false, $msg_prefix="") {

		$save_columns = array();
		$success = true;

		foreach ($this->columns as $column) {

			// skip columns which are not in the input
			if (is_array($input_columns) and !in_array($column[0], $input_columns)) continue;

			// skip columns without a name
			if (!$column[0]) continue;

			// skip columns which are not in the edit form
			if (isset($column[4]) and ($column[4]===false or $column[4]==="display")) continue;

			// skip disabled columns
			if (!empty($column['disabled'])) continue;

			// type conversion
			switch ( @$column['type'] ) {
			case "boolean":
				$content = !empty($post[$column[0]]);
				break;
			case "integer":
				$content = intval(@$post[$column[0]]);
				break;
			case "csa":
				if (@is_array($post[$column[0]])) {
					$content = implode(",", $post[$column[0]]);
				} else {
					$content = "";
				}
				break;
			default: // string
				if (isset($post[$column[0]])) {
					$content = trim($post[$column[0]]);
				} else {
					$content = "";
				}
			}

			// if NULL is allowed, save empty input as NULL
			if ( !empty($column['null']) and !$content ) $content = null;

			$object->{$column[0]} = $content;

			// per column beforesave method
			$save_column = true;
			if ( !empty($column['beforesave']) ) {
				$method = "beforesave_".$column['beforesave'];
				if (method_exists($this, $method)) {
					$callee = $this;
				} else {
					$callee = $object;
					$method = "dbtableadmin_".$method;
				}
				$return = $callee->$method(
					// parameters for column beforesave methods:
					$object->{$column[0]}, // content
					$column,             // column description (array)
					$msg_prefix          // prefix to show at direct edit
				);
				if ($return===null) { // skip the column on return null
					$save_column = false;
				} elseif (!$return) {
					$success=false;
				}
			}

			if ($save_column) $save_columns[] = $column[0];

		}

		// process all columns before break off
		if (!$success) return false;

		// global beforesave method
		$method = "beforesave";
		if (method_exists($this, $method)) {
			if ( !$this->$method(
					// parameters for general beforesave methods:
					$save_columns, // columns to be saved (indexed array)
					$msg_prefix    // prefix to show at direct edit
				) ) return false;
		} elseif (method_exists($object, "dbtableadmin_".$method)) {
			$method = "dbtableadmin_".$method;
			if ( !$object->$method(
					// parameters for general beforesave methods:
					$save_columns, // columns to be saved (indexed array)
					$msg_prefix    // prefix to show at direct edit
				) ) return false;
		}

		return $save_columns;
	}


	// view functions


	/**
	 * display everything
	 */
	public function display() {
?>
<div class="bg_white">
<div id="dbtableadmin">
<?

		if ( !empty($_GET['order']) and $this->column_name_exists($_GET['order']) ) {
			$this->order = $_GET['order'];
			$this->orderdesc = !empty($_GET['orderdesc']);
		} else {
			$this->order     = $this->order_default;
			$this->orderdesc = $this->orderdesc_default;
		}

		if ($this->enable_edit and isset($_GET['id'])) {
			$this->display_edit();
		} else {
			$this->display_list();
		}

?>
</div>
</div>
<?
	}


	/**
	 * check, if a column name exists
	 *
	 * @param string  $name
	 * @return boolean
	 */
	protected function column_name_exists($name) {
		foreach ( $this->columns as $column ) {
			if ( $column[0] == $name ) return true;
		}
		return false;
	}


	/**
	 * list page
	 */
	protected function display_list() {

		if ($this->enable_insert) {
			$this->display_add_record();
		}

		if ($this->enable_filter) {
			$this->filter->display_form();
		}

		$sql = $this->sql_list();
		$result = DB::query($sql);
		$linescount = DB::num_rows($result);
		if ($linescount) {
			$button_js = $this->enable_delete_single or $this->enable_duplicate;
			$direct_edit = false;
			foreach ($this->columns as $column) {
				if ( !isset($column[3]) ) continue;
				if ( $column[3]=="manualorder" ) {
					$button_js = true;
				} elseif ( righteq($column[3], "_directedit") ) {
					$direct_edit = true;
				}
			}
			$show_form = $direct_edit or $this->enable_delete_checked;
			$show_edit_column = $this->enable_edit or $this->enable_duplicate or $this->enable_delete_single or $this->enable_delete_checked;
		} else {
			$button_js        = false;
			$direct_edit      = false;
			$show_form        = false;
			$show_edit_column = false;
		}

		if ($button_js) {
			form("", 'name="dbtableadmin_jsform"');
?>
	<input type="hidden" name="action" value="">
	<input type="hidden" name="id" value="">
<?
			form_end();
		}
		if ($button_js or $this->enable_delete_checked) {
			$this->display_list_javascript($button_js);
		}
		if ($show_form) {
			form("", 'name="dbtableadmin_listform" id="dbtableadmin_listform"');
		}

?>
<div id="doublescroll">
<table id="dbtableadmin_list">
<?
		$this->display_list_thead($show_edit_column);
		if ($linescount) {
			$this->display_list_tbody($result, $direct_edit, $show_edit_column, $linescount);
			$this->display_list_tfoot($direct_edit, $show_edit_column);
		} else {
			$this->display_list_tbody_empty();
		}
?>
</table>
</div>
<?

		if ($show_form) form_end();

		if ($this->enable_pager) {
			$this->pager->display();
		}

	}


	/**
	 * table head
	 *
	 * @param boolean $show_edit_column
	 */
	protected function display_list_thead($show_edit_column) {
?>
<thead>
	<tr>
<?
		foreach ( $this->columns as $column ) {
			if (isset($column[3]) and $column[3]===false) continue;
?>
		<th<? $this->cellclass($column) ?>><?
			if (empty($column['noorder']) and $column[0]!==false) {
				?><a href="<?=URI::append(
					array(
						'order'     => $column[0],
						'orderdesc' => ($this->order!=$column[0] xor !$this->orderdesc) ? "1" : null,
						'page'      => null
					)
				)?>"><?=$column[1]?></a><?
				if ( $this->order==$column[0] ) {
					?><span class="arrow"><?=$this->orderdesc?'&uarr;':'&darr;'?></span><?
				}
			} else {
				echo $column[1];
			}
			?></th>
<?
		}
		// right column for edit, delete, duplicate
		if ($show_edit_column) {
?>
		<th></th>
<?
		}
?>
	</tr>
</thead>
<?
	}


	/**
	 * table body
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
			$this->pager->seek($result);
			$line = $this->pager->firstline;
		} else {
			$line = 0;
		}
		while ( $object = DB::fetch_object($result, $this->classname) and (!$this->enable_pager or $line <= $this->pager->lastline) ) {
?>
	<tr class="<?=stripes($line)?>">
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
						($column[0]?$object->{$column[0]}:null), // 1 content
						$object,                               // 2 object
						$column,                               // 3 column description (array)
						$line,                                 // 4 line number (starting at 0)
						$linescount                            // 5 count of lines selected in the database
					);
				} else {
					$method = "dbtableadmin_".$method;
					$object->$method(
						// parameters for print methods:
						($column[0]?$object->{$column[0]}:null), // 1 content
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
			<a href="<?=URI::append(['id'=>$object->id])?>" class="iconlink"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAQAAAD8fJRsAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAACxIAAAsSAdLdfvwAAAAHdElNRQfhAgkMCSYc0bm9AAAAO0lEQVQY083IMRGAMBAAsIzUBSKokBrFQ81wx/YOnr1fAWQM1WU6dx3SXLsLKfS/9uHdNUN6anMbWu0P2jUhgcbH0ewAAAAASUVORK5CYII=" <?alt(_("edit"))?>></a>
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
	 * empty table body
	 */
	protected function display_list_tbody_empty() {
		$colspan = 0;
		foreach ( $this->columns as $column ) {
			if (isset($column[3]) and $column[3]===false) continue;
			$colspan++;
		}
?>
<tbody>
	<tr class="<?=stripes()?>">
		<td colspan="<?=$colspan?>" class="center"><?=$this->msg_no_record_available?></td>
	</tr>
</tbody>
<?
	}


	/**
	 * table foot
	 *
	 * @param boolean $direct_edit
	 * @param boolean $show_edit_column
	 */
	protected function display_list_tfoot($direct_edit, $show_edit_column) {
		if (!$direct_edit and !$this->enable_delete_checked) return;
?>
<tfoot>
	<tr>
		<td colspan="<?=count($this->columns)?>" class="right">
<?
		if ($direct_edit) {
?>
			<input type="submit" name="action[apply_directedit]" value="<?=_("apply changes")?>" onsubmit="return <?=$this->js_submit_directedit?>;">
<?
		}
?>
		</td>
<?
		if ($show_edit_column) {
?>
		<td class="right">
<?
			if ($this->enable_delete_checked) {
?>
			<input type="submit" name="action[delete_checked]" value="<?=_("delete selected")?>" onclick="return submit_delete_checked();">
<?
			}
?>
		</td>
<?
		}
?>
	</tr>
</tfoot>
<?
	}


	/**
	 * form elements for deleting
	 *
	 * @param object  $object
	 */
	protected function display_list_delete($object) {

		if ($this->reference_check($object)) return;

		if ($this->enable_delete_single) {
?>
			<img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAsAAAAPCAQAAACYNP27AAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAACxIAAAsSAdLdfvwAAAAHdElNRQfhAgkMAzOL47XcAAAA3klEQVQY0y3KTSsEYQAA4OedndXutNi2/WAlBylKyU25UX6Bmx/l5KeJOMiFcJhmd3bIrHkdeM4PPX0hsuPKiRBFUerCsXl4M7Rrz50CgqFdW0YGhroKL17dhuhPWNG3asPUnvvg0JHgxxK1qKXjIXXg1JcViU9d1Nqylqm+G5sy1/blbmx7T7wLohKVaKHSViQKjVUzUSqo9GTyxEIjM0ciKnUEZeLrfzcCcpmgStQW1s19CxqlnkaZWMr11SqNWmFNpUrjT/hwZmTs0ti5gWd1iiffJmamShMtj7H+BRQOU4sQ0A+cAAAAAElFTkSuQmCC" class="iconbutton" <?alt(_("delete"))?> onclick="return submit_delete_button(<?=$object->id?>);">
<?
		}
		if ($this->enable_delete_checked) {
?>
			<input type="checkbox" name="delete[]" value="<?=$object->id?>">
<?
		}

	}


	/**
	 * javascript for the list page
	 *
	 * @param boolean $button_js
	 */
	protected function display_list_javascript($button_js) {
?>
<script>
<?
		if ($button_js) {
?>
function submit_button(action, id) {
	document.dbtableadmin_jsform.action.value = action;
	document.dbtableadmin_jsform.id.value = id;
	document.dbtableadmin_jsform.submit();
}
<?
		}
		if ($this->enable_delete_single) {
?>
function submit_delete_button(id) {
	var msg = '<?=$this->msg_really_delete?>';
	msg = msg.replace(/%id%/, id);
	if ( confirm(msg) ) submit_button('delete', id);
}
<?
		}
		if ($this->enable_delete_checked) {
?>
function submit_delete_checked() {
	var elist = document.getElementsByTagName('input');
	if (elist) {
		var c = 0;
		for (var i = 0; i < elist.length; i++) {
			if (
				elist[i].name &&
				elist[i].name.search(/^delete/) != -1 &&
				elist[i].checked
			) {
				c++;
			}
		}
		if (c > 0) {
			var msg = '<?=$this->msg_really_delete_multiple?>';
			msg = msg.replace(/%count%/, c);
			return confirm(msg);
		}
	}
	return true;
}
<?
		}
?>
</script>
<?
	}


	/**
	 * display the page with the form to edit or add a record
	 */
	protected function display_edit() {

		if ($this->id) {
			// edit existing record
			if (!$this->object) {
				$this->object = new $this->classname($this->id);
				if (!$this->object->id) {
					warning(_("The record you want to edit does not exist."));
					$this->redirect_to_list();
				}
			}
?>
<h2><?=$this->msg_strtr($this->msg_edit_record, $this->object)?></h2>
<?
			$form_action = URI::append(['id'=>$this->object->id]);
		} else {
			// add new record
			if (!$this->object) {
				$this->object = new $this->classname;
			}
?>
<h2><?=$this->msg_add_record?></h2>
<?
			$form_action = "";
		}

		$this->display_edit_form_top($form_action);

?>
<fieldset>
<?

		$this->display_edit_content();

		if (method_exists($this, "after_edit_content")) {
			$this->after_edit_content();
		}

?>
<div class="buttons th"><span class="cancel"><a class="orange_but first" href="<?=URI::strip(['id'])?>"><?=_("cancel")?></a></span><span class="input wid"><input type="submit" class="orange_but" value="<?=_("save")?>"></span></div>
</fieldset>
<input type="hidden" name="action" value="editsubmit">
<?
		form_end();

	}


	/**
	 * head of the edit form
	 *
	 * @param string  $form_action
	 */
	protected function display_edit_form_top($form_action) {
		form($form_action, 'name="dbtableadmin_editform" class="editform" onsubmit="return '.$this->js_submit_edit.';"');
	}


	/**
	 * form fields of the edit form
	 */
	protected function display_edit_content() {

		foreach ($this->columns as $index => $column) {
			if (isset($column[4]) and $column[4]===false) continue;
?>
<div class="input <?=stripes($index)?>"><label for="<?=$column[0]?>"><?=$column[1]?></label><span class="input"><?
			$method = "edit_".(!empty($column[4])?$column[4]:'text');
			if (method_exists($this, $method)) {
				$callee = $this;
			} else {
				$callee = $this->object;
				$method = "dbtableadmin_".$method;
			}
			if ($column[0]) {
				$callee->$method(
					// parameters for edit functions:
					$column[0],                  // 1 column/attribute name
					$this->object->{$column[0]},   // 2 default
					$this->object->id,           // 3 ID
					!empty($column['disabled']), // 4 disabled (not editable)
					$column                      // 5 (array) column description
				);
			} else {
				$callee->$method();
			}
			?></span></div>
<?
		}

	}


	/**
	 * link to insert a new record
	 */
	protected function display_add_record() {
?>
<div class="add_record"><a href="<?=URI::append(['id'=>0])?>" class="icontextlink"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=$this->msg_add_record?></a></div>
<?
	}


	/**
	 * replace wildcards in messages
	 *
	 * @param string  $text
	 * @param mixed   $columns array or object
	 * @return string
	 */
	protected function msg_strtr($text, $columns) {
		$replace = array();
		foreach ( $columns as $key => $value ) {
			$replace['%'.$key.'%'] = $value;
		}
		return strtr($text, $replace);
	}


	/**
	 * CSS class for table cell
	 *
	 * @param array   $column
	 */
	protected function cellclass($column) {
		if (!empty($column[2])) { ?> class="<?=$column[2]?>"<? }
	}


	/**
	 * generate the SQL statement for the list page
	 *
	 * @return string
	 */
	protected function sql_list() {

		$sql = $this->sql_select_from();

		$where = array();

		foreach ($this->global_where as $key => $value) {
			$where[] = $key."=".DB::value_to_sql($value);
		}

		// filter, search
		if ( $this->enable_filter ) {
			$search_columns = array();
			$search_columns_integer = array();
			foreach ( $this->columns as $column ) {
				if (!empty($column['nosearch'])) continue;
				if (!$column[0]) continue;
				switch (@$column['type']) {
				case "boolean":
					continue;
				case "integer":
					$search_columns_integer[] = $this->dbtable.".".$column[0];
					continue;
				default:
					$search_columns[] = $this->dbtable.".".$column[0];
				}
			}
			if ( $where_filter = $this->filter->where(["%%%order%%%"=>$this->order], $search_columns, $search_columns_integer) ) {
				$where[] = $where_filter;
			}
		}

		if (count($where)) {
			$sql .= " WHERE ".join(" AND ", $where);
		}

		$sql .= " ".$this->sql_order_by($sql);

		return $sql;
	}


	/**
	 * first part of the select statement until WHERE
	 *
	 * @return string
	 */
	protected function sql_select_from() {
		return "SELECT ".$this->dbtable.".* FROM ".$this->dbtable;
	}


	/**
	 * ORDER BY part of the select statement
	 *
	 * @return string
	 */
	protected function sql_order_by() {
		$sql = "ORDER BY ".$this->dbtable.".".DB::ident($this->order);
		if ($this->orderdesc) $sql .= " DESC";
		return $sql;
	}


	/**
	 * check if the record/object is referenced by other tables
	 *
	 * @param object  $object
	 * @return boolean
	 */
	protected function reference_check($object) {

		// check if activated
		if ( !count($this->reference_check) ) return false;

		reset($this->reference_check);
		foreach ( $this->reference_check as $value ) {
			$sql = sprintf($value, $object->id)." LIMIT 1";
			$result = DB::query($sql);
			if ( DB::num_rows($result) ) return true;
		}
		return false;
	}


	///////////////////////////////////////////////////////
	// print functions: display in table cell on list page
	//
	// parameters:
	// 1 $content    (mixed)   content
	// 2 $object     (object)  object
	// 3 $column     (array)   column description
	// 4 $line       (integer) line number (starting at 0)
	// 5 $linescount (integer) count of lines selected in the database
	//
	// The output should contain no linebreaks to fit perfectly in <td>here</td>.


	/**
	 * text (default)
	 *
	 * @param mixed   $content
	 */
	protected function print_text($content) {
		echo h($content);
	}


	/**
	 * text with direct edit
	 *
	 * @param mixed   $content
	 * @param object  $object
	 * @param array   $column
	 */
	protected function print_text_directedit($content, $object, array $column) {
		if (!empty($column['print_size'])) $size = $column['size']; else $size = 10;
		?><input type="hidden" name="directedit_key[<?=$object->id?>][<?=$column[0]?>]" value="1"><?
		?><input type="text" name="directedit[<?=$object->id?>][<?=$column[0]?>]" size="<?=$size?>" value="<?=h($content)?>"><?
	}


	/**
	 * first part of a longer text
	 *
	 * @param mixed    $content
	 * @param Relation $object
	 * @param array    $column
	 */
	protected function print_text_limit($content, /** @noinspection PhpUnusedParameterInspection */ Relation $object, array $column) {
		if (!empty($column['print_limit'])) $limit = $column['print_limit']; else $limit = 50;
		$content = ltrim($content);
		if (mb_strlen($content) > $limit) {
			echo h(rtrim(mb_substr($content, 0, $limit))."...");
		} else {
			echo h($content);
		}
	}


	/**
	 * boolean only display without checkbox
	 *
	 * @param mixed   $content
	 */
	protected function print_boolean($content) {
		display_checked($content);
	}


	/**
	 * boolean checkbox
	 *
	 * @param mixed   $content
	 * @param object  $object
	 * @param array   $column
	 */
	protected function print_boolean_directedit($content, $object, array $column) {
		?><input type="hidden" name="directedit_key[<?=$object->id?>][<?=$column[0]?>]" value="1"><?
		?><input type="checkbox" name="directedit[<?=$object->id?>][<?=$column[0]?>]" value="1"<?
		if ($content) { ?> checked<? }
		?>><?
	}


	/**
	 * selected value from a drop down menu
	 *
	 * @param mixed    $content
	 * @param Relation $object
	 * @param array    $column
	 */
	protected function print_select($content, /** @noinspection PhpUnusedParameterInspection */ Relation $object, array $column) {
		echo h(@$column['options'][$content]);
	}


	/**
	 * drop down menu
	 *
	 * @param mixed   $content
	 * @param object  $object
	 * @param array   $column
	 */
	protected function print_select_directedit($content, $object, array $column) {
		input_hidden("directedit_key[".$object->id."][".$column[0]."]", "integer");
		input_select("directedit[".$object->id."][".$column[0]."]", $column['options'], $content);
	}


	/**
	 * arrows to change the manual order
	 *
	 * @param mixed    $content
	 * @param Relation $object
	 * @param array    $column
	 * @param integer  $line
	 * @param integer  $linescount
	 */
	protected function print_manualorder(/** @noinspection PhpUnusedParameterInspection */ $content, Relation $object, array $column, $line, $linescount) {

		// show arrows only if order is by manualorder ascending
		if ( $this->order!=$column[0] or $this->orderdesc ) return;

		// If filter or searching is active, we can not change the manual order at the same time.
		if ( $this->enable_filter and ($this->filter->search or $this->filter->filter) ) return;

		if ( $line > 0 ) {
			?><div class="moveup"><input type="button" value="&uarr;&uarr;" onclick="submit_button('movefirst', <?=$object->id?>);"><input type="button" value="&uarr;" onclick="submit_button('moveup', <?=$object->id?>);"></div><?
		}
		if ( $line < ($linescount - 1) ) {
			?><div class="movedown"><input type="button" value="&darr;" onclick="submit_button('movedown', <?=$object->id?>);"><input type="button" value="&darr;&darr;" onclick="submit_button('movelast', <?=$object->id?>);"></div><?
		}

	}


	////////////////////////////////////////////////
	// edit functions: form fields on the edit page
	//
	// parameters:
	// 1 $colname  (string)  column/attribute name
	// 2 $default  (mixed)   default (current value)
	// 3 $id       (integer) ID
	// 4 $disabled (boolean) disabled (not editable)
	// 5 $column   (array)   column description
	//
	// The current record can be accessed by $this->object.
	//
	// The output should contain linebreaks to fit perfectly in
	// <td>
	// here.
	// </td>


	/**
	 * text (default)
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 * @param integer $id
	 * @param boolean $disabled
	 * @param array   $column
	 */
	protected function edit_text($colname, $default, /** @noinspection PhpUnusedParameterInspection */ $id, $disabled, array $column) {
		$attributes = array();
		if (isset($column['size'])) {
			$attributes[] = 'size="'.$column['size'].'"';
		}
		if (isset($column['width'])) {
			$attributes[] = 'style="width:'.$column['width'].'"';
		}
		if (isset($column['maxlength'])) {
			$attributes[] = 'maxlength="'.$column['maxlength'].'"';
		}
		if (!empty($column['required'])) {
			$attributes[] = 'required';
		}
		input_text($colname, $default, $disabled, join(" ", $attributes));
	}


	/**
	 * textarea
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 * @param integer $id
	 * @param boolean $disabled
	 * @param array   $column
	 */
	protected function edit_area($colname, $default, /** @noinspection PhpUnusedParameterInspection */ $id, $disabled, array $column) {
		$attributes = array();
		if (isset($column['cols'])) {
			$attributes[] = 'cols="'.$column['cols'].'"';
		}
		if (isset($column['width'])) {
			$attributes[] = 'style="width:'.$column['width'].'"';
		}
		if (isset($column['rows'])) {
			$attributes[] = 'rows="'.$column['rows'].'"';
		}
		if (!empty($column['required'])) {
			$attributes[] = 'required';
		}
		input_textarea($colname, $default, $disabled, join(" ", $attributes));
	}


	/**
	 * checkbox
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 * @param integer $id
	 * @param boolean $disabled
	 */
	protected function edit_boolean($colname, $default, /** @noinspection PhpUnusedParameterInspection */ $id, $disabled) {
		input_checkbox($colname, "1", $default, $disabled);
	}


	/**
	 * drop down menu
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 * @param integer $id
	 * @param boolean $disabled
	 * @param array   $column
	 */
	protected function edit_select($colname, $default, /** @noinspection PhpUnusedParameterInspection */ $id, $disabled, array $column) {
		input_select($colname, $column['options'], $default, $disabled, @$column['attributes']);
	}


	/**
	 * display content without editing
	 *
	 * @param string  $colname
	 * @param mixed   $default
	 */
	protected function edit_display(/** @noinspection PhpUnusedParameterInspection */ $colname, $default) {
		echo h($default);
	}


}
