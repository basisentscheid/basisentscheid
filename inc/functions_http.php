<?
/**
 * functions used on every page, which is called by http
 *
 * @see inc/common.php
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


/**
 * http redirect
 *
 * @param string  $target (optional) relative or absolute URI
 */
function redirect($target="") {

	if ($target) {
		if (!lefteq($target, "/") and !lefteq($target, "http")) {
			// make relative paths absolute
			$dirname = dirname($_SERVER['PHP_SELF']);
			if ($dirname=="/") $target = "/".$target; else $target = $dirname."/".$target;
		}
	} else {
		// reload the page to get rid of POST data
		$target = $_SERVER['REQUEST_URI'];
	}

	if (DEBUG) {
		// save page infos to show them in the debug output on the next page
		if (!isset($_SESSION['redirects'])) $_SESSION['redirects'] = array();
		$_SESSION['redirects'][] = array(
			'BN'          => BN,
			'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			'GET'         => $_GET,
			'POST'        => $_POST
		);
	}

	// save not yet displayed output to display it on the next page
	if (isset($_SESSION['output'])) {
		$_SESSION['output'] .= ob_get_clean();
	} else {
		$_SESSION['output'] = ob_get_clean();
	}

	header("Location: ".$target);
	exit;
}


/**
 * head part of the page and not yet displayed output
 *
 * @param string  $title
 */
function html_head($title) {

	$output = ob_get_clean();

	// we use HTML 5
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="stylesheet" media="all" type="text/css" href="style.css?<?=VERSION?>">
<? if (Login::$admin) { ?>
	<link rel="stylesheet" media="all" type="text/css" href="admin.css?<?=VERSION?>">
<? } ?>
	<link rel="icon" href="/favicon.ico" type="image/x-icon">
	<title><?=h($title)?></title>
</head>
<body>

<?
	if (DEBUG) {
?>
<!--
<?=strtr( print_r(
				array(
					'BN'          => BN,
					'REQUEST_URI' => $_SERVER['REQUEST_URI'],
					'GET'         => $_GET,
					'POST'        => $_POST,
					'SESSION'     => $_SESSION
				),
				true ), array("<!--"=>"<!-", "-->"=>"->") );
		unset($_SESSION['redirects']);
?>
-->
<?
	}
?>

<header>
	<a href="<?=DOCROOT?>index.php" id="logo"><img src="img/logo.png" width="58" height="50" alt="<?=_("Logo")?>"></a>
	<div id="header">
		<div id="user">
			<? html_user(); ?>
		</div>
		<nav>
			<ul>
				<li><a href="<?=DOCROOT?>index.php" id="home">Basisentscheid</a></li>
				<li>
					<form method="GET" action="<?
	switch (BN) {
		// jump to different page if the same page doesn't show the equivalent content in other groups
	case "proposal.php":
	case "proposal_edit.php":
	case "draft.php":
		echo "proposals.php";
		$hidden = false;
		break;
	default:
		echo BN;
	case "periods.php":
	case "admin_areas.php":
		$hidden = array(
			'ngroup' => null, // remove ngroup, because the new ngroup comes from the drop down menu
			'id'     => null  // remove id to go back from edit to list mode
		);
	}
	?>">
						<select name="ngroup" onchange="this.form.submit()">
<?
	$entitled = ( Login::$member and Login::$member->entitled );
	if ($entitled) {
		$sql = "SELECT ngroups.*, member FROM ngroups
			LEFT JOIN members_ngroups ON members_ngroups.ngroup = ngroups.id AND members_ngroups.member = ".intval(Login::$member->id);
	} else {
		$sql = "SELECT * FROM ngroups";
	}
	$sql .= " ORDER BY name";
	$result = DB::query($sql);
	$ngroups = array();
	while ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
		$ngroups[] = $ngroup;
	}
	$ngroups = Ngroup::parent_sort_active($ngroups);
	// entitled ngroups
	foreach ($ngroups as $ngroup) {
		if (!$entitled or !$ngroup->member) continue;
		// use the first ngroup as default
		if ($_SESSION['ngroup']==0) $_SESSION['ngroup'] = $ngroup->id;
?>
							<option value="<?=$ngroup->id?>"<?
		if ($ngroup->id==$_SESSION['ngroup']) { ?> selected class="selected"<? }
		?>><?=$ngroup->name?> &#10003;</option>
<?
	}
	// not entitled ngroups
	foreach ($ngroups as $ngroup) {
		if ($entitled and $ngroup->member) continue;
		// use the first ngroup as default
		if ($_SESSION['ngroup']==0) $_SESSION['ngroup'] = $ngroup->id;
?>
							<option value="<?=$ngroup->id?>"<?
		if ($ngroup->id==$_SESSION['ngroup']) { ?> selected class="selected"<? }
		?>><?=$ngroup->name?></option>
<?
	}
?>
						</select>
<?
	// add the hidden fields after the drop down menu to have ngroup always in the first place of the GET parameters
	if ($hidden) URI::hidden($hidden);
?>
					</form>
				</li>
<?
	navlink('proposals.php', _("Proposals"), true);
	navlink('periods.php', _("Periods"), true);
	if (Login::$admin) {
		navlink('admin_areas.php', _("Areas"), true);
	} else {
		navlink('areas.php', _("Areas"), true);
	}
?>
			</ul>
<?
	if (Login::$admin) {
?>
			<ul class="admin">
<?
		navlink('admin_members.php', _("Members"));
		navlink('admins.php', _("Admins"));
		navlink('admin_ngroups.php', _("Groups"));
?>
			</ul>
<?
	}
?>
		</nav>
		<div style="clear:right"></div>
	</div>
</header>

<h1><?=$title?></h1>

<?

	// not yet displayed output from previous page with redirect
	if (isset($_SESSION['output'])) {
		if ($_SESSION['output']) {
?>
<div class="messages"><?=$_SESSION['output']?></div>
<div class="clearfix"></div>
<?
		}
		unset($_SESSION['output']);
	}

	// output from before the html head
	if ($output) {
?>
<div class="messages"><?=$output?></div>
<div class="clearfix"></div>
<?
	}

	$GLOBALS['html_head_issued'] = true;
}


/**
 * one navigation link
 *
 * @param string  $file
 * @param string  $title
 * @param boolean $add_ngroup (optional)
 */
function navlink($file, $title, $add_ngroup=false) {
?>
				<li><a href="<?=$file;
	if ($add_ngroup) { ?>?ngroup=<?=$_SESSION['ngroup']; }
	?>"<? if (BN==$file) { ?> class="active"<? } ?>><?=$title?></a></li>
<?
}


/**
 * display the user/login area
 */
function html_user() {
	if (Login::$member or Login::$admin) {
		if (Login::$member) {
			printf(
				_("logged in as %s"),
				'<a href="settings.php" class="user">'.Login::$member->username().'</a>'
			);
		} else {
			printf(
				_("logged in as admin %s"),
				'<span class="admin">'.Login::$admin->username.'</span>'
			);
		}
		form(URI::$uri, 'class="button" style="margin-left: 10px"');
?>
<input type="hidden" name="action" value="logout">
<input type="submit" value="<?=_("Logout")?>">
<?
		form_end();
	} else {
		// login as member via ID server
		form("login.php", 'class="button"');
?>
<input type="hidden" name="origin" value="<?=URI::$uri?>">
<input type="submit" value="<?=_("login")?>">
<?
		form_end();
	}
}


/**
 * foot part of the page
 */
function html_foot() {
?>

<footer><a href="about.php"><?=_("About")?></a></footer>
</body>
</html>
<?
}


/**
 * a fatal user error
 *
 * @param string  $text
 * @param boolean $content2html (optional) format content
 */
function error($text, $content2html=false) {
	if (empty($GLOBALS['html_head_issued'])) {
		html_head(_("Error"));
	}
?>
<p class="error">&#9747; <?= $content2html ? content2html($text) : h($text) ?></p>
<?
	html_foot();
	exit;
}


/**
 * check if required POST parameters are set
 *
 * example:
 * action_required_parameters('id', 'name');
 */
function action_required_parameters() {
	foreach ( func_get_args() as $arg ) {
		if ( !isset($_POST[$arg]) ) {
			warning("Parameter missing.");
			redirect();
		}
	}
}


/**
 * used by proposals.php and proposal.php
 */
function action_proposal_select_period() {

	Login::access_action("admin");
	action_required_parameters('issue', 'period');

	$issue = new Issue($_POST['issue']);
	if (!$issue) {
		warning("The requested issue does not exist!");
		redirect();
	}

	$period = new Period($_POST['period']);
	if (!$period) {
		warning("The selected period does not exist!");
		redirect();
	}

	$available =& $issue->available_periods();
	if (!isset($available[$period->id])) {
		warning("The selected period is not available for the issue!");
		redirect();
	}

	$issue->period = $period->id;
	$issue->update(array("period"));

	redirect();
}


/**
 * POST form open tag with CSRF token
 *
 * @param string  $url
 * @param string  $attributes (optional)
 */
function form($url="", $attributes="") {
	if ($url=="") $url = BN;
?>
<form action="<?=$url?>" method="POST"<?
	if ($attributes) { ?> <?=$attributes; }
	?>>
<input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
<?
}


/**
 * hidden input field
 *
 * @param string  $name
 * @param string  $value
 */
function input_hidden($name, $value) {
?>
<input type="hidden" name="<?=$name?>" value="<?=h($value)?>">
<?
}


/**
 * text input field
 *
 * @param string  $name
 * @param string  $value
 * @param boolean $disabled   (optional)
 * @param string  $attributes (optional)
 */
function input_text($name, $value, $disabled=false, $attributes="") {
?>
<input type="text" name="<?=$name?>" value="<?=h($value)?>"<?
	if ($disabled) { ?> disabled<? }
	if ($attributes) { ?> <?=$attributes; }
	?>>
<?
}


/**
 * textarea
 *
 * @param string  $name
 * @param string  $value
 * @param boolean $disabled   (optional)
 * @param string  $attributes (optional)
 */
function input_textarea($name, $value, $disabled=false, $attributes="") {
?>
<textarea name="<?=$name?>"<?
	if ($disabled) { ?> disabled<? }
	if ($attributes) { ?> <?=$attributes; }
	?>><?=h($value)?></textarea>
<?
}


/**
 * checkbox
 *
 * @param string  $name
 * @param string  $value
 * @param boolean $checked    (optional)
 * @param boolean $disabled   (optional)
 * @param string  $attributes (optional)
 */
function input_checkbox($name, $value, $checked=false, $disabled=false, $attributes="") {
?>
<input type="checkbox" name="<?=$name?>" value="<?=h($value)?>"<?
	if ($checked) { ?> checked<? }
	if ($disabled) { ?> disabled<? }
	if ($attributes) { ?> <?=$attributes; }
	?>>
<?
}


/**
 * drop down menu
 *
 * @param string  $name
 * @param array   $options
 * @param mixed   $selected (optional)
 */
function input_select($name, array $options, $selected=false) {
?>
<select name="<?=$name?>">
<?
	foreach ( $options as $key => $value ) {
?>
 <option value="<?=$key?>"<?
		if ($key==$selected) { ?> selected class="selected"<? }
		?>><?=$value?></option>
<?
	}
?>
</select>
<?
}


/**
 * submit button
 *
 * @param string  $value
 */
function input_submit($value) {
?>
	<input type="submit" value="<?=h($value)?>">
<?
}


/**
 *
 */
function form_end() {
?>
</form>
<?
}


/**
 * display signs for true or false
 *
 * @param boolean $value
 */
function display_checked($value) {
	if ($value) echo "&#10003;"; else echo "&#9711;";
}


/**
 * return CSS classes with alternating background colors
 *
 * @param mixed   $change (optional) if this value changes, the color changes
 * @param mixed   $suffix (optional) for subclasses
 * @return string
 */
function stripes($change=false, $suffix="") {
	static $colorid = 1; // start with td0
	static $change_last = null;
	if ($change===false or $change_last != $change) {
		$colorid = ($colorid + 1) % 2;
	}
	$change_last = $change;
	return "td".$colorid.$suffix;
}


/**
 * display help text
 *
 * default for logged in members:      show all help
 * default for not logged in sessions: hide all help
 *
 * This function should not be used on pages with forms, because users will lose their already filled in data if they click on the help buttons.
 */
function help() {
	if (Login::$member) {
		$show = !in_array(BN, Login::$member->hide_help());
	} else {
		if ( isset($_SESSION['show_help']) ) {
			$show = in_array(BN, $_SESSION['show_help']);
		} else {
			$show = false;
		}
	}
	if ($show) {
?>
<div class="help">
<?
		form(URI::same(), 'class="hide_help"');
?>
<input type="hidden" name="action" value="hide_help">
<input type="submit" value="<?=_("hide help")?>">
<?
		form_end();
		// read help content
		$display = false;
		foreach ( file(DOCROOT."locale/help_".LANG.".txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line ) {
			if ($display) {
				if (substr($line, 0, 3) == "===") break;
?>
<p><?=$line?></p>
<?
			} elseif ($line == "===".BN) {
				$display = true;
			}
		}
?>
</div>
<?
	} else {
		form(URI::same(), 'class="show_help"');
?>
<input type="hidden" name="action" value="show_help">
<input type="submit" value="<?=_("show help")?>">
<?
		form_end();
	}
}


/**
 * hide help on a page
 */
function hide_help() {
	if (Login::$member) {
		$hide = Login::$member->hide_help();
		if (!in_array(BN, $hide)) {
			$hide[] = BN;
			Login::$member->update_help($hide);
		}
	} else {
		if (!empty($_SESSION['show_help'])) array_remove_value($_SESSION['show_help'], BN);
	}
}


/**
 * show help on a page
 */
function show_help() {
	if (Login::$member) {
		$hide = Login::$member->hide_help();
		array_remove_value($hide, BN);
		Login::$member->update_help($hide);
	} else {
		if (empty($_SESSION['show_help'])) {
			$_SESSION['show_help'] = array(BN);
		} elseif (!in_array(BN, $_SESSION['show_help'])) {
			$_SESSION['show_help'][] = BN;
		}
	}
}


/**
 * format content without changing it
 *
 * @param string  $text
 * @return string
 */
function content2html($text) {
	return preg_replace(
		array('#https?://\S+#i',     '#\S+@\S+\.[a-z]+#i',         "#''[^'\n]+''#"),
		array('<a href="$0">$0</a>', '<a href="mailto:$0">$0</a>', '<i>$0</i>'    ),
		nl2br(h($text), false)
	);
}


/**
 * output alt and title attributes for images at once
 *
 * @param string  $text
 */
function alt($text) {
	?>alt="<?=$text?>" title="<?=$text?>"<?
}
