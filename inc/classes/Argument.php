<?
/**
 * Argument
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Argument extends Relation {

	const rating_score_max = 2;

	public $side;
	public $parent;
	public $proposal;
	public $created;
	public $removed;
	public $rating;
	public $title;
	const title_length = 100;
	public $content;
	const content_length = 2000;
	public $member;

	protected $boolean_fields = array("removed");
	protected $create_fields = array("title", "content", "proposal", "parent", "side", "member");


	/**
	 * arguments older than this may not be edited anymore
	 *
	 * @return integer
	 */
	static function edit_limit() {
		static $edit_limit = false;
		if ($edit_limit===false) $edit_limit = strtotime("- ".ARGUMENT_EDIT_INTERVAL);
		return $edit_limit;
	}


	/**
	 * wrapper for create()
	 *
	 * @param object  $proposal
	 */
	function add(Proposal $proposal) {
		if (mb_strlen($this->title) > self::title_length) {
			$this->title = limitstr($this->title, self::title_length);
			warning(sprintf(_("The title has been truncated to the maximum allowed length of %d characters!"), self::title_length));
		}
		if (mb_strlen($this->content) > self::content_length) {
			$this->content = limitstr($this->content, self::content_length);
			warning(sprintf(_("The content has been truncated to the maximum allowed length of %d characters!"), self::content_length));
		}
		$this->create();

		// notification to proponents and to authors of all parent arguments
		$recipients = $proposal->proponents();
		$parent = $this->parent;
		while ( $parent > 0 ) { // "pro" and "contra" will be converted to 0
			$argument = new Argument($parent);
			$recipients[] = $argument->member;
			$parent = $argument->parent;
		}
		$notification = new Notification("argument");
		$notification->proposal = $proposal;
		$notification->argument = $this;
		$notification->send($recipients);

	}


	/**
	 * wrapper for update()
	 *
	 * @return boolean
	 */
	function apply_changes() {
		if (strtotime($this->created) < self::edit_limit()) {
			warning(_("This argument may not be updated any longer."));
			return false;
		}
		$this->update(array("title", "content"), "updated=now()");
	}


	/**
	 * set or update a rating score
	 *
	 * @param integer $score
	 * @return boolean
	 */
	function set_rating($score) {
		if ($this->removed) {
			warning(_("The argument has been removed."));
			return false;
		}
		if ($score > self::rating_score_max) $score = self::rating_score_max;
		if ($score < 1) $score = 1;
		$fields_values = array('argument'=>$this->id, 'member'=>Login::$member->id, 'score'=>$score);
		$keys = array("argument", "member");
		DB::insert_or_update("ratings", $fields_values, $keys);
		$this->update_ratings_cache();
	}


	/**
	 * reset a rating
	 *
	 * @return boolean
	 */
	function delete_rating() {
		if ($this->removed) {
			warning(_("The argument has been removed."));
			return false;
		}
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
