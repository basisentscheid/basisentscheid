<?
/**
 * display arguments
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class Arguments {

	/** @var Proposal $proposal */
	public static $proposal;

	public static $open = array();
	public static $show = array();


	/**
	 *
	 * @param Proposal $proposal
	 */
	public function display(Proposal $proposal) {

		self::$proposal = $proposal;

		if (isset($_GET['open']) and is_array($_GET['open'])) self::$open = $_GET['open'];
		if (isset($_GET['show']) and is_array($_GET['show'])) self::$show = $_GET['show'];
?>
<div class="arguments">
	<div class="arguments_side arguments_pro">
<?
		if (Login::$member and @$_GET['argument_parent']!="pro" and self::$proposal->allowed_add_arguments()) {
?>
		<div class="add"><a href="<?=URI::append(['argument_parent'=>"pro"])?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new pro argument")?></a></div>
<?
		}
?>
		<h2><?=_("Pro")?></h2>
<? self::display_arguments("pro", "pro", 0); ?>
	</div>
	<div class="arguments_side arguments_contra">
<?
		if (Login::$member and @$_GET['argument_parent']!="contra" and self::$proposal->allowed_add_arguments()) {
?>
		<div class="add"><a href="<?=URI::append(['argument_parent'=>"contra"])?>#form" class="icontextlink"><img src="img/plus.png" width="16" height="16" alt="<?=_("plus")?>"><?=_("Add new contra argument")?></a></div>
<?
		}
?>
		<h2><?=_("Contra")?></h2>
<? self::display_arguments("contra", "contra", 0); ?>
	</div>
	<div class="clearfix"></div>
</div>

<?
	}


	/**
	 * list the sub-arguments for one parent-argument
	 *
	 * @param string  $side   "pro" or "contra"
	 * @param mixed   $parent ID of parent argument or "pro" or "contra"
	 * @param integer $level
	 * @param boolean $full   (optional)
	 */
	private function display_arguments($side, $parent, $level, $full=true) {

		$sql = "SELECT arguments.*";
		if (Login::$member) {
			$sql .= ", ratings.score
			FROM arguments
			LEFT JOIN ratings ON ratings.argument = arguments.id AND ratings.member = ".intval(Login::$member->id);
		} else {
			$sql .= "
			FROM arguments";
		}
		// intval($parent) gives parent=0 for "pro" and "contra"
		$sql .= "	WHERE arguments.proposal=".intval(self::$proposal->id)."
			AND side=".DB::esc($side)."
			AND parent=".intval($parent)."
		ORDER BY removed, rating DESC, created";
		$result = DB::query($sql);
		$num_rows = DB::num_rows($result);
		if (!$num_rows and @$_GET['argument_parent']!=$parent) return;

		// don't even show the <ul>
		if ( !defined('ARGUMENTS_HEAD_'.$level) or !constant('ARGUMENTS_HEAD_'.$level) ) return;

?>
<ul>
<?

		$i = 0;
		while ( $argument = DB::fetch_object($result, "Argument") ) {
			/** @var Argument $argument */
			$i++;

			if ( in_array($parent, self::$open) ) {
				//
			} elseif ( !defined('ARGUMENTS_HEAD_'.$level) or $i > constant('ARGUMENTS_HEAD_'.$level) ) {
				// show links to remaining arguments only under fully shown arguments
				if ($full) {
					$open = self::$open;
					$show = self::$show;
					$open[] = $parent;
					$open = array_unique($open);
?>
<li><a href="<?=URI::append(['open'=>$open, 'show'=>$show])?>#argument<?=$argument->id?>"><?
					$remaining = $num_rows - $i + 1;
					if (!intval($parent)) {
						if ($remaining==1) {
							echo _("show remaining 1 argument");
						} else {
							printf(_("show remaining %d arguments"), $remaining);
						}
					} else {
						if ($remaining==1) {
							if ($i==1) echo _("show 1 reply");
							else echo _("show remaining 1 reply");
						} else {
							if ($i==1) printf(_("show %d replys"), $remaining);
							else printf(_("show remaining %d replys"), $remaining);
						}
					}
					?></a></li>
<?
				}
				break; // break while loop
			}

			self::display_argument($argument, $side, $i, $level, $full);

		}

		if (Login::$member and @$_GET['argument_parent']==$parent and self::$proposal->allowed_add_arguments()) {
?>
<li>
	<div class="argument">
<?
			form(URI::append(['argument_parent'=>$parent]), 'class="argument" id="form"');
?>
<div class="time"><?=(intval($parent)?_("New reply"):_("New argument"))?>:</div>
<input name="title" type="text" maxlength="<?=Argument::title_length?>" value="<?=h(@$_POST['title'])?>"><br>
<textarea name="content" rows="5" maxlength="<?=Argument::content_length?>"><?=h(@$_POST['content'])?></textarea><br>
<input type="hidden" name="action" value="add_argument">
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
	 * display the list item with one argument and its children
	 *
	 * @param Argument $argument
	 * @param string  $side     "pro" or "contra"
	 * @param integer $i
	 * @param integer $level
	 * @param boolean $full
	 */
	private function display_argument(Argument $argument, $side, $i, $level, $full) {

		// on open restart rules
		if ( in_array($argument->id, self::$show) ) {
			$level = 0;
			$full = true;
		}

?>
<li>
	<div class="argument">
<?
		// author and form
		$member = new Member($argument->member);
		if (
			Login::$member and $member->id==Login::$member->id and
			@$_GET['argument_edit']==$argument->id and
			!$argument->removed
		) {
?>
		<div class="author"><?=$member->link()?> <?=datetimeformat($argument->created)?></div>
<?
			if (strtotime($argument->created) > Argument::edit_limit()) {
?>
		<div class="time"><?printf(_("This argument can be updated until %s."), datetimeformat($argument->created." + ".ARGUMENT_EDIT_INTERVAL))?></div>
<?
				form(URI::append(['argument_edit'=>$argument->id]), 'class="argument"');
?>
<input id="argument<?=$argument->id?>" name="title" type="text" maxlength="<?=Argument::title_length?>" value="<?=h(!empty($_POST['title'])?$_POST['title']:$argument->title)?>"><br>
<textarea name="content" rows="5" maxlength="<?=Argument::content_length?>"><?=h(!empty($_POST['content'])?$_POST['content']:$argument->content)?></textarea><br>
<input type="hidden" name="action" value="update_argument">
<input type="hidden" name="id" value="<?=$argument->id?>">
<input type="submit" value="<?=_("apply changes")?>">
<?
				form_end();
				$display_content = false;
			} else {
?>
		<div class="time"><?=_("This argument may not be updated any longer!")?></div>
<?
				$display_content = true;
			}
		} else {
?>
		<div class="author<?=$argument->removed?' removed':''?>"><?
			if (
				Login::$member and $member->id==Login::$member->id and
				strtotime($argument->created) > Argument::edit_limit() and
				!$argument->removed and
				self::$proposal->allowed_add_arguments()
			) {
				?><a href="<?=URI::append(['argument_edit'=>$argument->id])?>#argument<?=$argument->id?>" class="iconlink"><img src="img/edit.png" width="16" height="16" <?alt(_("edit"))?>></a> <?
			}
			echo $member->link()?> <?=datetimeformat($argument->created)?></div>
<?
			$display_content = true;
		}

		// title and content
		$full_children = $full;
		if ($display_content) {
			if ($argument->updated) {
?>
		<div class="author<?=$argument->removed?' removed':''?>"><?=_("updated")?> <?=datetimeformat($argument->updated)?></div>
<?
			}
			if ($argument->removed) {
?>
		<h3 id="argument<?=$argument->id?>" class="removed">&mdash; <?=_("argument removed by admin")?> &mdash;</h3>
<?
			} else {
				// hide content
				if ( !defined('ARGUMENTS_FULL_'.$level) or $i > constant('ARGUMENTS_FULL_'.$level) or !$full ) {
					$open = self::$open;
					$show = self::$show;
					$show[] = $argument->id;
					$show = array_unique($show);
?>
		<h3 id="argument<?=$argument->id?>"><a href="<?=URI::append(['open'=>$open, 'show'=>$show])?>#argument<?=$argument->id?>" title="<?=_("show text and replys")?>"><?=h($argument->title)?></a></h3>
<?
					$full_children = false;
				} else {
?>
		<h3 id="argument<?=$argument->id?>"><?=h($argument->title)?></h3>
<?
					self::display_argument_content($argument);
				}
			}
		}

?>
		<div class="clearfix"></div>
	</div>
<?
		self::display_arguments($side, $argument->id, $level+1, $full_children);
?>
</li>
<?
	}


	/**
	 * content area of one argument
	 *
	 * @param Argument $argument
	 */
	function display_argument_content(Argument $argument) {
?>
		<p><?=content2html($argument->content)?></p>
<?

		// reply
		if (
			Login::$member and
			@$_GET['argument_parent']!=$argument->id and
			!$argument->removed and
			self::$proposal->allowed_add_arguments()
		) {
?>
		<div class="reply"><a href="<?=URI::append(['argument_parent'=>$argument->id])?>#form" class="iconlink"><img src="img/reply.png" width="16" height="16" <?alt(_("reply"))?>></a></div>
<?
		}

		// rating and remove/restore
		if ($argument->rating) {
			?><span class="rating">+<?=$argument->rating?></span> <?
		}
		if (Login::$member) {
			if (
				// don't allow to rate ones own arguments
				$argument->member!=Login::$member->id and
				// don't allow to rate removed arguments
				!$argument->removed and
				self::$proposal->allowed_add_arguments()
			) {
				$uri = URI::same()."#argument".$argument->id;
				if ($argument->score) {
					form($uri, 'class="button rating reset"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
<input type="hidden" name="action" value="reset_rating">
<input type="submit" value="0">
<?
					form_end();
				}
				for ($score=1; $score <= Argument::rating_score_max; $score++) {
					form($uri, 'class="button rating'.($score <= $argument->score?' selected':'').'"');
?>
<input type="hidden" name="argument" value="<?=$argument->id?>">
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
<input type="hidden" name="id" value="<?=$argument->id?>">
<?
			if ($argument->removed) {
?>
<input type="hidden" name="action" value="restore_argument">
<input type="submit" value="<?=_("restore")?>">
<?
			} else {
?>
<input type="hidden" name="action" value="remove_argument">
<input type="submit" value="<?=_("remove")?>">
<?
			}
			form_end();
		}

	}


}
