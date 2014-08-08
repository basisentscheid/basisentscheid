<?
/**
 * pager
 *
 * example:
 *
 * $pager = new Pager;
 * $sql = "SELECT * FROM table";
 * $result = pg_query($sql);
 * $pager->seek($result);
 * $line = $pager->firstline;
 * while ( $row = pg_fetch_assoc($result) and $line <= $pager->lastline ) {
 *   print_r($row);
 *   $line++;
 * }
 * $pager->display();
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Pager {


	public $separator = "\n";
	public $ellipsis = "...";

	public $page;
	private $itemsperpage;
	public $linescount;
	private $pagescount;
	// internal line numbers, starting at 0
	public $firstline;
	public $lastline;
	// human readable line numbers, starting at 1
	public $firsthline;
	public $lasthline;


	/**
	 * initialize attributes
	 *
	 * @param integer $itemsperpage_default (optional)
	 * @param integer $itemsperpage_min     (optional)
	 */
	function __construct($itemsperpage_default=20, $itemsperpage_min=10) {

		$this->itemsperpage = @$_SESSION['pager_itemsperpage'][BN];

		// apply change by user
		if ( isset($_GET['itemsperpage']) ) {
			$this->itemsperpage = $_GET['itemsperpage'];
			$_SESSION['pager_itemsperpage'][BN] = $this->itemsperpage;
		}

		if ( $this->itemsperpage < $itemsperpage_min ) $this->itemsperpage = $itemsperpage_default;

		$this->page = intval(@$_GET['page']);
		if ( ! $this->page > 0 ) $this->page = 1; // start with page 1

		// default messages
		$this->msg_itemsperpage = _("Records per page");

	}


	/**
	 * calculate line numbers
	 */
	public function calculate() {

		// line counting starts at 0

		$lastdataline = $this->linescount - 1;
		$firstpageline = ( $this->page - 1 ) * $this->itemsperpage;
		while ( $firstpageline > $lastdataline ) {
			$this->page--;
			$firstpageline = ( $this->page - 1 ) * $this->itemsperpage;
		}

		$this->firstline = min( $this->linescount - 1, ( $this->page - 1 ) * $this->itemsperpage );
		$this->lastline = min( $this->linescount - 1, $this->firstline + $this->itemsperpage - 1 );
		$this->pagescount = ceil( $this->linescount / $this->itemsperpage );

		if ($this->linescount > 0) {
			// line counting for humans starts at 1
			$this->firsthline = $this->firstline + 1;
			$this->lasthline = $this->lastline + 1;
		}

	}


	/**
	 * jump to first line in db
	 *
	 * @param resource $result
	 */
	public function seek($result) {

		$this->linescount = pg_num_rows($result);

		$this->calculate($result);

		if ($this->linescount > 0) {
			pg_result_seek($result, $this->firstline);
		}

	}


	/**
	 * display pager
	 *
	 * @param integer $pagelinksdist (optional)
	 */
	public function display($pagelinksdist=3) {

?>
<div class="pager">
<?

		$showpagebegin = max(1, $this->page - $pagelinksdist);
		$showpageend = min($this->pagescount, $this->page + $pagelinksdist);

		$linkpart = URI::linkpart(URI::strip(array('page', 'itemsperpage')));

		if ( $this->linescount > 10 ) { // display the items-per-page switch only if it would change anything
?>
<div class="itemsperpage"><?=$this->msg_itemsperpage?>: &nbsp;&nbsp;
<?
			foreach ( array(10, 20, 50, 100) as $i ) {
				?><a href="<?=$linkpart?>itemsperpage=<?=$i?>"<?
				if ( $this->itemsperpage == $i ) { ?> class="active"<? }
				?>><?=$i?></a>
<?
			}
			?></div>
<?
		}

		$linkpart2 = $linkpart."itemsperpage=".$this->itemsperpage."&amp;";

		if ( $this->pagescount > 1 ) { // display the page only if there is more than 1 page
?>
<div class="pages"><?=_("Pages")?>: &nbsp;&nbsp;
<?
			if ( $this->page > $pagelinksdist + 1) {
				?><a href="<?=$linkpart2?>page=1">1</a><?
				echo $this->separator;
				if ( $this->page > $pagelinksdist + 2 ) echo $this->ellipsis.$this->separator;
			}
			for ($i=$showpagebegin;$i<=$showpageend;$i++) {
				?><a href="<?=$linkpart2?>page=<?=$i?>"<?
				if ( $this->page==$i ) { ?> class="active"<? }
				?>><?=$i?></a><?
				if ( $i < $showpageend ) echo $this->separator;
			}
			if ( $this->page < $this->pagescount - $pagelinksdist ) {
				if ( $this->page < $this->pagescount - ($pagelinksdist+1) ) echo $this->separator.$this->ellipsis;
				echo $this->separator;
				?><a href="<?=$linkpart2?>page=<?=$this->pagescount?>"><?=$this->pagescount?></a><?
			}
			?></div>
<?
		}

?>
<div class="clearfix"></div>
</div>
<?

	}


}
