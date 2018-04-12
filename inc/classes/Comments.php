<?
/**
 * display comments
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Comments {

	/** @var Proposal $proposal */
	private static $proposal;

	// GET parameters
	// show remaining comments
	private static $open = array();
	// show text and children
	private static $show = array();
	// reply to this comment
	private static $parent = 0;

	// comments and parents to open
	private static $open_ids = null;

	// "pro"/"contra"/"discussion"
	private $rubric;


	/**
	 *
	 * @param string  $rubric "pro"/"contra"/"discussion"
	 */
	public function __construct($rubric) {
		$this->rubric = $rubric;

		// We do this here and not in __static(), because we need it only on display, not on action.
		if ( is_null(self::$open_ids) ) {
			self::$open_ids = self::$show;
			foreach ( self::$show as $comment_id ) {
				$comment = new Comment($comment_id);
				self::$open_ids = array_merge(self::$open_ids, $comment->parents());
			}
			self::$open_ids = array_unique(self::$open_ids);
		}

	}


	/**
	 * class constructor
	 */
	public static function __static() {
		if (isset($_GET['open']) and is_array($_GET['open'])) self::$open = $_GET['open'];
		if (isset($_GET['show']) and is_array($_GET['show'])) self::$show = $_GET['show'];
		if (isset($_GET['parent'])) self::$parent = $_GET['parent'];
	}


	/**
	 * read configuration
	 *
	 * @param integer $level folding level, top level is 0
	 * @return integer
	 */
	private static function comments_head($level) {
		if ( defined('COMMENTS_HEAD_'.$level) ) return constant('COMMENTS_HEAD_'.$level);
		return 0;
	}


	/**
	 * redirect to show a comment
	 *
	 * @param integer $comment
	 */
	public static function redirect_append_show($comment) {
		$show = self::$show;
		$show[] = $comment;
		$show = array_unique($show);
		redirect(URI::append(['open'=>self::$open, 'show'=>$show], true)."#comment".$comment);
	}


	/**
	 * redirect to reply to a comment
	 *
	 * @param integer $reply
	 */
	public static function redirect_append_reply($reply) {
		$open = self::$open;
		$open[] = $reply;
		$open = array_unique($open);
		$show = self::$show;
		$show[] = $reply;
		$show = array_unique($show);
		redirect(URI::append(['open'=>$open, 'show'=>$show, 'parent'=>$reply], true)."#form");
	}


	/**
	 *
	 * @param Proposal $proposal
	 */
	public static function display(Proposal $proposal) {
		self::$proposal = $proposal;

?>
</div>
</div>
<div class="row">
<div class="col-md-9 col-md-offset-3">
<?

		help("comments");
?>

</div>
</div>
<div class="row">
<div class="col-md-2 col-md-push-10">
	
</div>
<div class="col-md-10 col-md-pull-2">
<?
		$discussion = ($proposal->state == "draft" or !empty($_GET['discussion']));
		if ($proposal->state != "draft") {
?>
<section class="comments" id="comments">

	<div class="filter"> 
		<a href="<?=URI::append(['discussion'=>1, 'open'=>null, 'show'=>null])?>#comments"<?
			if ($discussion) { ?> class="active"<? }
			?>><?=_("Discussion")?></a>
		<a href="<?=URI::append(['discussion'=>null, 'open'=>null, 'show'=>null])?>#comments"<?
			if (!$discussion) { ?> class="active"<? }
			?>><?=_("Arguments")?></a>
	</div>
<?
		}

?>
	<div class="clearfix"></div>
<?

		if ($discussion) {
?>
	<div class="comments_rubric comments_body">
<?
			if (self::$proposal->allowed_add_comments("discussion")) {
				if (Login::access_allowed("comment")) {
?>
		<div class="add"><a href="<?=URI::append(['discussion'=>1, 'parent'=>"discussion"])?>#form" class="icontextlink"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=_("Add new comment")?></a></div>
<?
				} else {
?>
		<div class="add icontextlink disabled"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=_("Add new comment")?></div>
<?
				}
			}
?>
		<h2><?=_("Discussion")?></h2>
<?
			$comments = new Comments("discussion");
			$comments->display_comments("discussion");
?>
	</div>
<?
		} else {
?>
<div class="comments_body">
<div class="row">

<div class="col-md-6">
						
	<div class="comments_rubric arguments_pro">
<?
			if (self::$proposal->allowed_add_comments("pro")) {
				if (Login::access_allowed("comment")) {
?>
		<div class="add"><a href="<?=URI::append(['parent'=>"pro"])?>#form" class="icontextlink"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=_("Add new pro argument")?></a></div>
<?
				} else {
?>
		<div class="add icontextlink disabled"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=_("Add new pro argument")?></div>
<?
				}
			}
?>
		<h2><?=_("Pro")?></h2>
<?
			$comments = new Comments("pro");
			$comments->display_comments("pro");
?>
	</div>
	</div>
	<div class="col-md-6">
	<div class="comments_rubric arguments_contra">
<?
			if (self::$proposal->allowed_add_comments("contra")) {
				if (Login::access_allowed("comment")) {
?>
		<div class="add"><a href="<?=URI::append(['parent'=>"contra"])?>#form" class="icontextlink"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=_("Add new contra argument")?></a></div>
<?
				} else {
?>
		<div class="add icontextlink disabled"><img src="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAMAAADzN3VRAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAABDlBMVEX/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/kwD/mw//sWH+/v7/zZ//2rr/q03/5tIAAABbgI6WAAAAUXRSTlMAHTxEORUzm+bdjiMgpv75khJI7+EwTvrwMiz15RYDzahbNMmi8VgxflWJZoVdajj9EwTqxoxlGfPfBm/8RwGkdauHAnn4JJ727o0OUKWqRgdR4XveAAAAAWJLR0RZmrL0GAAAAAlwSFlzAAALEgAACxIB0t1+/AAAAAd0SU1FB+ECCQwIH1rPAPQAAADlSURBVCjPY2CAA0YmZhZWBgzAxs4RCAKcXNwo4jy8fIEwwC8giJAQEg5EBiKiMAkx8UBUICEJkZCSDkQHMrIgCTn5QEygAJJRhHODgoODYGwloIwyXCY4JCQYxlYB+i8Qq4yqHIMadplAdQYNKCs0NDQsJCQMSEH4mgxaUJkQOIDwtRl0cMjoMujhsIeZQR+7jIEhg5ExVhkToE9N4TLhERHhMLYZUMbcAku4WVqBAs4aU8LGFhwLdvYYMg7QmLNyRJNwgse2s4srkriwInIScXP3gIp7eqGnLG8fHV8/FQ3/AJgAAGjHj47rKTKwAAAAAElFTkSuQmCC" alt="<?=_("plus")?>"><?=_("Add new contra argument")?></div>
<?
				}
			}
?>
		<h2><?=_("Contra")?></h2>
<?
			$comments = new Comments("contra");
			$comments->display_comments("contra");
?>
	</div>
	</div>
	</div>
	</div>
	<div class="clearfix"></div>
<?
		}
?>
</section>

<?

		// highlight anchor
		if ( empty($_GET['openhl']) ) {
?>
<script>
if ( window.location.hash ) {
	var hash = window.location.hash.substring(1);
	document.getElementById(hash).className += " anchor";
}
</script>
<?
		}

	}


	/**
	 * list the child comments for one parent-comment
	 *
	 * @param mixed   $parent ID of parent comment or "pro"/"contra"/"discussion"
	 * @param integer $level  (optional) folding level, top level is 0
	 * @param boolean $full   (optional) allow showing full text
	 */
	private function display_comments($parent, $level=0, $full=true) {

		$sql = "SELECT comment.*, rating.score";
		if (Login::$member) {
			$sql .= ", seen.comment AS seen
			FROM comment
			LEFT JOIN rating ON rating.comment = comment.id AND rating.member = ".intval(Login::$member->id)."
			LEFT JOIN seen   ON seen.comment   = comment.id AND seen.member   = ".intval(Login::$member->id);
		} else {
			$sql .= "
			FROM comment
			LEFT JOIN rating ON rating.comment = comment.id AND rating.session = ".DB::esc(session_id());
		}
		// intval($parent) gives parent=0 for "pro"/"contra"/"discussion"
		$sql .= "
			WHERE proposal=".intval(self::$proposal->id)."
				AND rubric=".DB::esc($this->rubric)."
				AND parent=".intval($parent)."
			ORDER BY removed, rating DESC, created";
		$result = DB::query($sql);

		$comments = array();
		$open_ids = array();
		while ( $comment = DB::fetch_object($result, "Comment") ) {
			/** @var Comment $comment */
			$comments[] = $comment;
			if (in_array($comment->id, self::$open_ids)) $open_ids[] = $comment->id;
		}

		if (!$comments and self::$parent!=$parent) return;

?>
<ul>
<?

		$position = 1;
		$remaining = 0;
		$new       = 0;
		$highlight_started = false;
		$comments_head = self::comments_head($level);
		$open = in_array($parent, self::$open);
		foreach ( $comments as $comment ) {
			$limit_reached = $position > $comments_head;
			if (
				$limit_reached and
				!$open and
				!$open_ids // display comments until all to be open have been displayed
			) {
				$remaining++;
				if (Login::$member and !$comment->seen) $new++;
			} else {
				// highlight
				if (
					$limit_reached and
					isset($_GET['openhl']) and $_GET['openhl']==$parent and
					!$highlight_started
				) {
?>
<div id="openhl">
<?
					$highlight_started = true;
				}
				// display one comment and its children
				$this->display_comment($comment, $position, $level, $full);
				array_remove_value($open_ids, $comment->id);
			}
			$position++;
		}

		if ($highlight_started) {
?>
</div>
<?
		}

		// links to remaining comments only under fully shown comments
		if ($remaining and $full) {
			$open = self::$open;
			$show = self::$show;
			$open[] = $parent;
			$open = array_unique($open);
?>
<li><a href="<?=URI::append(['open'=>$open, 'show'=>$show, 'openhl'=>$parent])
			?>#openhl"><?
			if (!intval($parent)) {
				if ($this->rubric=="discussion") {
					if ($new) printf(ngettext("show remaining 1 new comment", "show remaining %d comments, %d of them new", $remaining), $remaining, $new);
					else printf(ngettext("show remaining 1 comment", "show remaining %d comments", $remaining), $remaining);
				} else {
					if ($new) printf(ngettext("show remaining 1 new argument", "show remaining %d arguments, %d of them new", $remaining), $remaining, $new);
					else printf(ngettext("show remaining 1 argument", "show remaining %d arguments", $remaining), $remaining);
				}
			} else {
				if ($position==1) {
					if ($new) printf(ngettext("show 1 new reply", "show %d replys, %d of them new", $remaining), $remaining, $new);
					else printf(ngettext("show 1 reply", "show %d replys", $remaining), $remaining);
				} else {
					if ($new) printf(ngettext("show remaining 1 new reply", "show remaining %d replys, %d of them new", $remaining), $remaining, $new);
					else printf(ngettext("show remaining 1 reply", "show remaining %d replys", $remaining), $remaining);
				}
			}
			?></a></li>
<?
		}

		if (
			isset($_GET['parent']) and $_GET['parent']==$parent and
			Login::access_allowed("comment") and
			self::$proposal->allowed_add_comments($this->rubric)
		) {
?>
<li id="form" class="anchor">
	<div class="comment">
<?
			form(URI::append(['parent'=>$parent]), "", "comment", "comment", true);
?>
<div class="time"><?
			if (intval($parent)) echo _("New reply");
			elseif ($this->rubric=="discussion") echo _("New comment");
			else echo _("New argument");
			?>:</div>
<input name="title" type="text" maxlength="<?=Comment::title_length?>" value="<?=h(isset($_POST['title'])?$_POST['title']:"")?>" required><br>
<textarea name="content" rows="5" maxlength="<?=Comment::content_length?>" required><?=h(isset($_POST['content'])?$_POST['content']:"")?></textarea><br>
<input type="hidden" name="action" value="add_comment">
<input type="hidden" name="parent" value="<?=$parent?>">
<input type="submit" class="orange_but first" value="<?=_("save")?>">
<?
			form_end();
?>
	</div>
</li>
<?
		}

?>
</ul>
<?
	}


	/**
	 * display the list item with one comment and its children
	 *
	 * @param Comment $comment
	 * @param integer $position position on this level, first comment has position 1
	 * @param integer $level    folding level, top level is 0
	 * @param boolean $full     allow showing full text
	 */
	private function display_comment(Comment $comment, $position, $level, $full) {

		// on show restart rules
		if ( in_array($comment->id, self::$show) ) {
			$level = 0;
			$full = true;
			$show = true;
		} else {
			$show = false;
		}

?>
<li id="comment<?=$comment->id?>">
	<div class="comment<?
		if (Login::$member) {
			if (!$comment->seen) {
				?> new<?
			} elseif (
				!self::comments_head($level+1) and
				$this->has_new_children($comment->id)
			) {
				?> new_children<?
			}
		}
		?>">
		<div class="hd">
<?
		if ($comment->member) {
			$author = new Member($comment->member);
			$author_link = $author->link();
		} else {
			$author_link = "";
		}
		if (
			$comment->is_author() and
			isset($_GET['comment_edit']) and $_GET['comment_edit']==$comment->id and
			!$comment->removed
		) {
			// edit existing comment
?>
		<div class="author"><?=$author_link?> <?=datetimeformat($comment->created)?></div>
<?
			if (strtotime($comment->created) > Comment::edit_limit()) {
?>
		<div class="time"><?printf(_("This comment can be updated until %s."), datetimeformat($comment->created." + ".COMMENT_EDIT_INTERVAL))?></div>
<?
				form(URI::append(['comment_edit'=>$comment->id]), "", "comment", "comment", true);
?>
<input id="comment<?=$comment->id?>" name="title" type="text" maxlength="<?=Comment::title_length?>" value="<?=h(!empty($_POST['title'])?$_POST['title']:$comment->title)?>" required><br>
<textarea name="content" rows="5" maxlength="<?=Comment::content_length?>" required><?=h(!empty($_POST['content'])?$_POST['content']:$comment->content)?></textarea><br>
<input type="hidden" name="action" value="update_comment">
<input type="hidden" name="id" value="<?=$comment->id?>">
<input type="submit" class="orange_but first" value="<?=_("apply changes")?>">
<?
				form_end();
				$display_content = false;
			} else {
?>
		<div class="time"><?=_("This comment may not be updated any longer!")?></div>
<?
				$display_content = true;
			}
		} else {
?>
		<div class="author<?=$comment->removed?' removed':''?>"><?
			// edit link
			if (
				$comment->is_author() and
				strtotime($comment->created) > Comment::edit_limit() and
				!$comment->removed and
				Login::access_allowed("comment") and
				self::$proposal->allowed_add_comments($this->rubric)
			) {
				?><a href="<?=URI::append(['comment_edit'=>$comment->id])?>#comment<?=$comment->id?>" class="iconlink"><img src="img/edit.png" <?alt(_("edit"))?>></a> <?
			}
			// author and time
			echo "<strong>"; echo $author_link?></strong>   <?=datetimeformat($comment->created)?></div>
<?
			$display_content = true;
		}

		// title and content
		if ($display_content) {
			if ($comment->removed) {
?>
		<h3 class="removed">&mdash; <?=_("comment removed by admin")?> &mdash;</h3>
<?
			} elseif (
				// show because title was clicked
				$show or
				// show because of position
				( defined('COMMENTS_FULL_'.$level) and $position <= constant('COMMENTS_FULL_'.$level) and $full )
			) {
				// display full text
				if ($comment->updated) {
?>
		<div class="author"><?=_("updated")?> <?=datetimeformat($comment->updated)?></div>
<?
				}
?>
		<h3><?=h($comment->title)?></h3>
		</div>
<?
				$this->display_comment_content($comment);

				// don't show the comment as new next time
				if (Login::$member and !$comment->seen) {
					// simulate INSERT IGNORE
					DB::query_ignore("INSERT INTO seen (comment, member) VALUES (".intval($comment->id).", ".intval(Login::$member->id).")");
				}

			} else {
				// display only head
				$open = self::$open;
				$show = self::$show;
				$show[] = $comment->id;
				$show = array_unique($show);
?>
		<h3><a href="<?=URI::append(['open'=>$open, 'show'=>$show])?>#comment<?=$comment->id?>" title="<?=_("show text and replys")?>"><?=h($comment->title)?></a></h3>
<?
				// display all children without full text
				$full = false;
			}
		}

?>
		<div class="clearfix"></div>
	</div>
<?
		// display children
		$level++;
		if (
			self::comments_head($level) or
			in_array($comment->id, self::$open_ids)
		) $this->display_comments($comment->id, $level, $full);
?>
</li>
<?
	}


	/**
	 * content area of one comment
	 *
	 * @param Comment $comment
	 */
	private function display_comment_content(Comment $comment) {
?>
		<p><?=content2html($comment->content)?></p>
<?

		// reply
		if (
			self::$proposal->allowed_add_comments($this->rubric) and
			self::$parent!=$comment->id and
			!$comment->removed
		) {
			if (Login::access_allowed("comment")) {
				$open = self::$open;
				$open[] = $comment->id;
				$open = array_unique($open);
?>
		<div class="reply"><a href="<?=URI::append(['open'=>$open, 'parent'=>$comment->id])?>#form" class="iconlink"><img src="img/reply.png" width="16" height="16" <?alt(_("reply"))?>></a></div>
<?
			} else {
?>
		<div class="reply iconlink disabled"><img src="img/reply.png" width="16" height="16" <?alt(_("reply"))?>></div>
<?
			}
		}

		// rating and remove/restore
		if ($comment->rating) {
			?><span class="rating" title="<?=_("sum of ratings")?>">+<?=$comment->rating?></span> <?
		}
		if (Login::$admin) {
			form(URI::same(), 'class="button"');
?>
<input type="hidden" name="id" value="<?=$comment->id?>">
<?
			if ($comment->removed) {
?>
<input type="hidden" name="action" value="restore_comment">
<input type="submit" value="<?=_("restore")?>">
<?
			} else {
?>
<input type="hidden" name="action" value="remove_comment">
<input type="submit" value="<?=_("remove")?>">
<?
			}
			form_end();
		} elseif (
			self::$proposal->allowed_add_comments($this->rubric) and
			// don't allow to rate ones own comments
			!$comment->is_author() and
			// don't allow to rate removed comments
			!$comment->removed
		) {
			if (Login::access_allowed("rate")) {
				$disabled = "";
				$uri = URI::same()."#comment".$comment->id;
			} else {
				$disabled = " disabled";
				$uri = "";
			}
			if ($comment->score) {
				form($uri, 'class="button rating reset"');
?>
<input type="hidden" name="comment" value="<?=$comment->id?>">
<input type="hidden" name="action" value="reset_rating">
<input type="submit" value="0"<?=$disabled?>>
<?
				form_end();
			}
			for ($score=1; $score <= Comment::rating_score_max; $score++) {
				form($uri, 'class="button rating'.($score <= $comment->score?' selected':'').'"');
?>
<input type="hidden" name="comment" value="<?=$comment->id?>">
<input type="hidden" name="action" value="set_rating">
<input type="hidden" name="rating" value="<?=$score?>">
<input type="submit" class="but_<?=$score?>" value="+<?=$score?>"<?=$disabled?>>
<?
				form_end();
			}
		}

	}


	/**
	 * check if a comment has at least one new child
	 *
	 * @param integer $parent
	 * @return boolean
	 */
	private function has_new_children($parent) {
		$sql = "SELECT id, seen.comment AS seen
			FROM comment
			LEFT JOIN seen ON seen.comment = comment.id AND seen.member = ".intval(Login::$member->id)."
			WHERE comment.proposal=".intval(self::$proposal->id)."
				AND rubric=".DB::esc($this->rubric)."
				AND parent=".intval($parent);
		$result = DB::query($sql);
		$children = array();
		while ( $row = DB::fetch_row($result) ) {
			if ( !$row[1] ) return true;
			$children[] = $row[0];
		}
		foreach ($children as $child) {
			if ( $this->has_new_children($child) ) return true;
		}
	}


}


Comments::__static();
