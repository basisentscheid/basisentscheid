<?

/**
 * Comment
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Comment extends Relation {

	const rating_score_max = 2;
	const title_length = 100;
	const content_length = 2000;

	// database table
	public $rubric;
	public $parent;
	public $proposal;
	public $created;
	public $removed;
	public $updated;
	public $rating;
	public $title;
	public $content;
	public $member;
	public $session;

	public $score;
	public $seen;

	protected $boolean_fields = array("removed");
	protected $create_fields = array("title", "content", "proposal", "parent", "rubric", "member", "session");


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


	/**
	 * check if the current user is the author of this comment
	 *
	 * @return bool
	 */
	public function is_author() {
		return ( Login::$member and $this->member==Login::$member->id ) or ( $this->session and $this->session==session_id() );
	}


	/**
	 * get and check session id
	 *
	 * @return string|null
	 */
	private function session_id() {
		if (PHP_SAPI=="cli") return null;
		$session = session_id();
		if ($session) return $session;
		trigger_error("Empty session id", E_USER_WARNING);
		return null;
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

		if (Login::$member) $this->member = Login::$member->id;
		$this->session = self::session_id();
		$this->create();

		// notification to authors of all parent comments
		$recipients = array();
		$parent = $this->parent;
		while ( $parent > 0 ) { // "pro"/"contra"/"discussion" will be converted to 0
			$comment = new Comment($parent);
			$recipients[] = $comment->member;
			$parent = $comment->parent;
		}
		$notification = new Notification("reply");
		$notification->proposal = $proposal;
		$notification->comment = $this;
		$notification->send($recipients);

		// notification according to notify settings
		$notification = new Notification("comment");
		$notification->proposal = $proposal;
		$notification->comment = $this;
		$notification->send([], $recipients);

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
		if (!$this->is_author()) {
			warning(_("You are not the author of the comment."));
			return false;
		}
		if (Login::$member) {
			// if a member wrote a comment without login then logges in and edits the comment, set the author subsequently
			$this->member = Login::$member->id;
			// update the session if it changes
			$this->session = self::session_id();
		}
		$this->update(["title", "content", "member", "session"], "updated=now()");
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
		if ($this->is_author()) {
			warning(_("Rating your own comments is not allowed."));
			return false;
		}
		$fields_values = array(
			'comment' => $this->id,
			'score'   => min(max($score, 1), self::rating_score_max),
			'session' => self::session_id()
		);
		if (Login::$member) {
			$fields_values['member'] = Login::$member->id;
			$keys = array("comment", "member");
		} else {
			$keys = array("comment", "session");
		}
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
		$where = "comment=".intval($this->id);
		if (Login::$member) {
			$where .= " AND member=".intval(Login::$member->id);
		} else {
			$session = self::session_id();
			if (!$session) return false;
			$where .= " AND session=".DB::esc($session);
		}
		DB::delete("rating", $where);
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
