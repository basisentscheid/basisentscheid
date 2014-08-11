<?
/**
 * Argument
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Argument extends Relation {

	public $side;
	public $parent;
	public $proposal;

	public $title;
	const title_length = 100;
	public $content;
	const content_length = 2000;

	protected $boolean_fields = array("removed");
	protected $create_fields = array("title", "content", "proposal", "parent", "side", "member");


	/**
	 *
	 * @param boolean $value
	 */
	function set_rating($value) {
		$fields_values = array('argument'=>$this->id, 'member'=>Login::$member->id, 'positive'=>$value);
		$keys = array("argument", "member");
		DB::insert_or_update("ratings", $fields_values, $keys);
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
