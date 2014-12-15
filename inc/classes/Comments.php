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
	 *
	 * @param Comment $comment
	 */
	public static function redirect_append_show($comment) {
		$show = self::$show;
		$show[] = $comment->id;
		$show = array_unique($show);
		redirect(URI::append(['open'=>self::$open, 'show'=>$show], true)."#comment".$comment->id);
	}


	/**
	 *
	 * @param Proposal $proposal
	 */
	public static function display(Proposal $proposal) {
		self::$proposal = $proposal;

?>
<div class="comments" id="comments">
<?

		$discussion = ($proposal->state == "draft" or !empty($_GET['discussion']));
		if ($proposal->state != "draft") {
?>
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

		if ($discussion) {
?>
	<div class="comments_rubric">
<?
			if (Login::$member and self::$proposal->allowed_add_comments("discussion")) {
?>
		<div class="add"><a href="<?=URI::append(['discussion'=>1, 'parent'=>"discussion"])?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new comment")?></a></div>
<?
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
	<div class="comments_rubric arguments_pro">
<?
			if (Login::$member and self::$proposal->allowed_add_comments("pro")) {
?>
		<div class="add"><a href="<?=URI::append(['parent'=>"pro"])?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new pro argument")?></a></div>
<?
			}
?>
		<h2><?=_("Pro")?></h2>
<?
			$comments = new Comments("pro");
			$comments->display_comments("pro");
?>
	</div>
	<div class="comments_rubric arguments_contra">
<?
			if (Login::$member and self::$proposal->allowed_add_comments("contra")) {
?>
		<div class="add"><a href="<?=URI::append(['parent'=>"contra"])?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new contra argument")?></a></div>
<?
			}
?>
		<h2><?=_("Contra")?></h2>
<?
			$comments = new Comments("contra");
			$comments->display_comments("contra");
?>
	</div>
	<div class="clearfix"></div>
<?
		}
?>
</div>
<?

		// highlight anchor
		if ( empty($_GET['openhl']) ) {
?>
<script type="text/javascript">
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

		$sql = "SELECT comment.*";
		if (Login::$member) {
			$sql .= ", rating.score, seen.comment AS seen
			FROM comment
			LEFT JOIN rating ON rating.comment = comment.id AND rating.member = ".intval(Login::$member->id)."
			LEFT JOIN seen   ON seen.comment   = comment.id AND seen.member   = ".intval(Login::$member->id);
		} else {
			$sql .= "
			FROM comment";
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
					if ($remaining==1) {
						if ($new) echo _("show remaining 1 new comment");
						else echo _("show remaining 1 comment");
					} else {
						if ($new) printf(_("show remaining %d comments, %d of them new"), $remaining, $new);
						else printf(_("show remaining %d comments"), $remaining);
					}
				} else {
					if ($remaining==1) {
						if ($new) echo _("show remaining 1 new argument");
						else echo _("show remaining 1 argument");
					} else {
						if ($new) printf(_("show remaining %d arguments, %d of them new"), $remaining, $new);
						else printf(_("show remaining %d arguments"), $remaining);
					}
				}
			} else {
				if ($remaining==1) {
					if ($position==1) {
						if ($new) echo _("show 1 new reply");
						else echo _("show 1 reply");
					} else {
						if ($new) echo _("show remaining 1 new reply");
						else echo _("show remaining 1 reply");
					}
				} else {
					if ($position==1) {
						if ($new) printf(_("show %d replys, %d of them new"), $remaining, $new);
						else printf(_("show %d replys"), $remaining);
					} else {
						if ($new) printf(_("show remaining %d replys, %d of them new"), $remaining, $new);
						else printf(_("show remaining %d replys"), $remaining);
					}
				}
			}
			?></a></li>
<?
		}

		if (
			Login::$member and
			isset($_GET['parent']) and $_GET['parent']==$parent and
			self::$proposal->allowed_add_comments($this->rubric)
		) {
?>
<li id="form" class="anchor">
	<div class="comment">
<?
			form(URI::append(['parent'=>$parent]), 'class="comment"');
?>
<div class="time"><?
			if (intval($parent)) echo _("New reply");
			elseif ($this->rubric=="discussion") echo _("New comment");
			else echo _("New argument");
			?>:</div>
<input name="title" type="text" maxlength="<?=Comment::title_length?>" value="<?=h(isset($_POST['title'])?$_POST['title']:"")?>"><br>
<textarea name="content" rows="5" maxlength="<?=Comment::content_length?>"><?=h(isset($_POST['content'])?$_POST['content']:"")?></textarea><br>
<input type="hidden" name="action" value="add_comment">
<input type="hidden" name="parent" value="<?=$parent?>">
<input type="submit" value="<?=_("save")?>">
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
<?
		$author = new Member($comment->member);
		if (
			Login::$member and $author->id==Login::$member->id and
			isset($_GET['comment_edit']) and $_GET['comment_edit']==$comment->id and
			!$comment->removed
		) {
			// edit existing comment
?>
		<div class="author"><?=$author->link()?> <?=datetimeformat($comment->created)?></div>
<?
			if (strtotime($comment->created) > Comment::edit_limit()) {
?>
		<div class="time"><?printf(_("This comment can be updated until %s."), datetimeformat($comment->created." + ".COMMENT_EDIT_INTERVAL))?></div>
<?
				form(URI::append(['comment_edit'=>$comment->id]), 'class="comment"');
?>
<input id="comment<?=$comment->id?>" name="title" type="text" maxlength="<?=Comment::title_length?>" value="<?=h(!empty($_POST['title'])?$_POST['title']:$comment->title)?>"><br>
<textarea name="content" rows="5" maxlength="<?=Comment::content_length?>"><?=h(!empty($_POST['content'])?$_POST['content']:$comment->content)?></textarea><br>
<input type="hidden" name="action" value="update_comment">
<input type="hidden" name="id" value="<?=$comment->id?>">
<input type="submit" value="<?=_("apply changes")?>">
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
				Login::$member and $author->id==Login::$member->id and
				strtotime($comment->created) > Comment::edit_limit() and
				!$comment->removed and
				self::$proposal->allowed_add_comments($this->rubric)
			) {
				?><a href="<?=URI::append(['comment_edit'=>$comment->id])?>#comment<?=$comment->id?>" class="iconlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>></a> <?
			}
			// author and time
			echo $author->link()?> <?=datetimeformat($comment->created)?></div>
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
			Login::$member and
			self::$parent!=$comment->id and
			!$comment->removed and
			self::$proposal->allowed_add_comments($this->rubric)
		) {
?>
		<div class="reply"><a href="<?=URI::append(['parent'=>$comment->id])?>#form" class="iconlink"><img src="img/reply.png" width="16" height="16" <?alt(_("reply"))?>></a></div>
<?
		}

		// rating and remove/restore
		if ($comment->rating) {
			?><span class="rating">+<?=$comment->rating?></span> <?
		}
		if (Login::$member) {
			if (
				// don't allow to rate ones own comments
				$comment->member!=Login::$member->id and
				// don't allow to rate removed comments
				!$comment->removed and
				self::$proposal->allowed_add_comments($this->rubric)
			) {
				$uri = URI::same()."#comment".$comment->id;
				if ($comment->score) {
					form($uri, 'class="button rating reset"');
?>
<input type="hidden" name="comment" value="<?=$comment->id?>">
<input type="hidden" name="action" value="reset_rating">
<input type="submit" value="0">
<?
					form_end();
				}
				for ($score=1; $score <= Comment::rating_score_max; $score++) {
					form($uri, 'class="button rating'.($score <= $comment->score?' selected':'').'"');
?>
<input type="hidden" name="comment" value="<?=$comment->id?>">
<input type="hidden" name="action" value="set_rating">
<input type="hidden" name="rating" value="<?=$score?>">
<input type="submit" value="+<?=$score?>">
<?
					form_end();
				}

			}
		} elseif (Login::$admin) {
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
