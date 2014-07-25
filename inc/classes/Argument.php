<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Argument extends Relation {

	public $side;
	public $parent;
	public $proposal;

	protected $create_fields = array("title", "content", "proposal", "parent", "side", "member");


	/**
	 *
	 * @param unknown $value
	 */
	function set_rating($value) {
		$fields_values = array('argument'=>$this->id, 'member'=>Login::$member->id, 'positive'=>$value);
		$where = "argument=".intval($this->id)." AND member=".intval(Login::$member->id);
		DB::insert_or_update("ratings", $fields_values, $where);
		$this->update_ratings_cache();
	}


	/**
	 *
	 */
	function delete_rating() {
		DB::delete("ratings", "argument=".intval($this->id)." AND member=".intval(Login::$member->id));
		$this->update_ratings_cache();
	}


	/**
	 *
	 */
	private function update_ratings_cache() {

		$sql = "SELECT COUNT(1) FROM ratings WHERE argument=".intval($this->id)." AND positive=TRUE";
		$this->plus = DB::fetchfield($sql);

		$sql = "SELECT COUNT(1) FROM ratings WHERE argument=".intval($this->id)." AND positive=FALSE";
		$this->minus = DB::fetchfield($sql);

		$this->update(array("plus", "minus"));

	}


}
