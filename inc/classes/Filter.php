<?
/**
 * filter and search for lists
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Filter {

	// configuration attributes

	/**
	 * filters
	 *
	 * example:
	 * array(
	 *   'filter1' => array(
	 *     ''         => "all",
	 *     'column=0' => "foo",
	 *     'column=1' => "bar"
	 *     ...
	 *   ),
	 *   'filter2 => array(
	 *     ...
	 * )
	 *
	 * @var array
	 */
	public $filters = array();

	/**
	 * keys of filters to hide
	 *
	 * @var array
	 */
	public $hide = array();

	/**
	 * enable text search
	 *
	 * @var boolean
	 */
	public $enable_search = true;

	/**
	 * drop down menu automatically submits form
	 *
	 * @var boolean
	 */
	public $enable_onchange = false;

	// internal attributes

	/**
	 * currently set filter
	 *
	 * @var array
	 */
	public $filter = array();

	/**
	 * current search string
	 *
	 * @var string
	 */
	public $search = "";


	/**
	 * read GET parameters
	 */
	public function __construct() {

		// filter
		if ( isset($_GET['filter']) and is_array($_GET['filter']) ) {
			$this->filter = $_GET['filter'];
		}

		// search
		if ( isset($_GET['search']) and is_string($_GET['search']) ) {
			$this->search = $_GET['search'];
		}

	}


	/**
	 * make a WHERE clause with the current filter and search
	 *
	 * @param array   $replace         (optional) replace wildcards in SQL
	 * @param array   $columns         (optional) string database columns to search in
	 * @param array   $columns_integer (optional) integer database columns to search in
	 * @return string
	 */
	public function where($replace=array(), $columns=array(), $columns_integer=array()) {

		$where = array();

		// filter
		foreach ( $this->filter as $key => $filtersql ) {
			if (!$filtersql) continue;
			// check for manipulations
			if (!isset($this->filters[$key][$filtersql])) continue;
			$where[] = strtr($filtersql, $replace);
		}

		// search
		if ( $this->search ) {
			$where_search = array();
			foreach ( $columns as $column ) {
				$where_search[] = $column." ILIKE ".m("%".$this->search."%");
			}
			// search in integer values only when searching for a non-zero integer
			if (intval($this->search)) {
				foreach ( $columns_integer as $column ) {
					$where_search[] = $column."=".intval($this->search);
				}
			}
			if ($where_search) {
				if (count($where_search)==1) $where[] = $where_search[0]; else $where[] = "(".join(" OR ", $where_search).")";
			}
		}

		if ($where) {
			if (count($where)==1) return $where[0]; else return "(".join(" AND ", $where).")";
		}
		return "";
	}


	/**
	 * display form with filters and search field
	 */
	public function display_form() {
?>
<form action="<?=BN?>" method="get" class="filter">
<?
		// pass on all other parameters
		URI::hidden(array('filter'=>null, 'search'=>null, 'page'=>null));

		// filter
		if ($this->filters) {
			$this->display_select_filter();
		}

		// search
		if ( $this->enable_search ) {
?>
<input type="text" name="search" value="<?=$this->search?>" size="20">
<input type="submit" value="<?=_("search and filter")?>">
<?
		} else {
?>
<input type="submit" value="<?=_("filter")?>">
<?
		}
?>
<a href="<?=URI::strip(array('filter', 'search'))?>"><?=_("reset")?></a>
</form>
<?
	}


	/**
	 * drop down menu(s) with the filters
	 */
	private function display_select_filter() {

		foreach ($this->filters as $filterkey => $filterarray) {
			if (in_array($filterkey, $this->hide)) {
?>
<input type="hidden" name="filter[<?=$filterkey?>]" value="<?=h(stripslashes(@$this->filter[$filterkey]))?>">
<?
				return;
			}
?>
<select name="filter[<?=$filterkey?>]"<? if ($this->enable_onchange) { ?> onchange="this.form.submit();"<? } ?>>
<?
			foreach ($filterarray as $where => $title) {
?>
	<option value="<?=$where?>"<?
				if (
					isset($this->filter[$filterkey]) and
					stripslashes($this->filter[$filterkey])==$where
				) {
					?> selected<?
				}
				?>><?=h($title)?></option>
<?
			}
?>
</select>
<?
		}

	}


}
