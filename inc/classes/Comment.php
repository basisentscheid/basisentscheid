<?

/**
 * Comment
 *
 * @property  score
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Comment extends Relation {

	const rating_score_max = 2;

	public $rubric;
	public $parent;
	public $proposal;
	public $created;
	public $removed;
	public $updated;
	public $rating;
	public $title;
	const title_length = 100;
	public $content;
	const content_length = 2000;
	public $member;

	protected $boolean_fields = array("removed");
	protected $create_fields = array("title", "content", "proposal", "parent", "rubric", "member");


	/**
	 * get a list of all parent comments
	 *
	 * Starts with the rubric as the top level and ends with the parent of this comment.
	 *
	 * @return array
	 */
	public function parents() {
		if (!$this->parent) return array($this->rubric);
		$parent = new Comment($this->parent);
		$parents = $parent->parents();
		$parents[] = $this->parent;
		return $parents;
	}


	/**
	 * comments older than this may not be edited anymore
	 *
	 * @return integer
	 */
	static function edit_limit() {
		static $edit_limit = false;
		if ($edit_limit===false) $edit_limit = strtotime("- ".COMMENT_EDIT_INTERVAL);
		return $edit_limit;
	}


	// action


	/**
	 * wrapper for create()
	 *
	 * @param Proposal $proposal
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

		// notification to proponents and to authors of all parent comments
		$recipients = $proposal->proponents();
		$parent = $this->parent;
		while ( $parent > 0 ) { // "pro"/"contra"/"discussion" will be converted to 0
			$comment = new Comment($parent);
			$recipients[] = $comment->member;
			$parent = $comment->parent;
		}
		$notification = new Notification("comment");
		$notification->proposal = $proposal;
		$notification->comment = $this;
		$notification->send($recipients);

	}


	/**
	 * wrapper for update()
	 *
	 * @return boolean
	 */
	function apply_changes() {
		if (strtotime($this->created) < self::edit_limit()) {
			warning(_("This comment may not be updated any longer."));
			return false;
		}
		$this->update(["title", "content"], "updated=now()");
	}


	/**
	 * set or update a rating score
	 *
	 * @param integer $score
	 * @return boolean
	 */
	function set_rating($score) {
		if ($this->removed) {
			warning(_("The comment has been removed."));
			return false;
		}
		if ($score > self::rating_score_max) $score = self::rating_score_max;
		if ($score < 1) $score = 1;
		$fields_values = array('comment'=>$this->id, 'member'=>Login::$member->id, 'score'=>$score);
		$keys = array("comment", "member");
		DB::insert_or_update("rating", $fields_values, $keys);
		$this->update_ratings_cache();
	}


	/**
	 * reset a rating
	 *
	 * @return boolean
	 */
	function delete_rating() {
		if ($this->removed) {
			warning(_("The comment has been removed."));
			return false;
		}
		DB::delete("rating", "comment=".intval($this->id)." AND member=".intval(Login::$member->id));
		$this->update_ratings_cache();
	}


	/**
	 * sum up all rating scores for a comment
	 */
	private function update_ratings_cache() {

		$sql = "SELECT SUM(score) FROM rating WHERE comment=".intval($this->id);
		$this->rating = intval( DB::fetchfield($sql) );

		$this->update(["rating"]);

	}


}
