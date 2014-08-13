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

	const rating_score_max = 2;

	protected $boolean_fields = array("removed");
	protected $create_fields = array("title", "content", "proposal", "parent", "side", "member");


	/**
	 * set or update a rating score
	 *
	 * @param integer $score
	 */
	function set_rating($score) {
		if ($score > self::rating_score_max) $score = self::rating_score_max;
		if ($score < 1) $score = 1;
		$fields_values = array('argument'=>$this->id, 'member'=>Login::$member->id, 'score'=>$score);
		$keys = array("argument", "member");
		DB::insert_or_update("ratings", $fields_values, $keys);
		$this->update_ratings_cache();
	}


	/**
	 * reset a rating
	 */
	function delete_rating() {
		DB::delete("ratings", "argument=".intval($this->id)." AND member=".intval(Login::$member->id));
		$this->update_ratings_cache();
	}


	/**
	 * sum up all rating scores for an argument
	 */
	private function update_ratings_cache() {

		$sql = "SELECT SUM(score) FROM ratings WHERE argument=".intval($this->id);
		$this->rating = intval( DB::fetchfield($sql) );

		$this->update(array("rating"));

	}


}
