<?
/**
 * functions used on every page, which is called by http
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common.php
 */


/**
 * http redirect
 *
 * @param string  $target (optional)
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

	// switch between stylesheets
	if (isset($_GET['style'])) $_SESSION['style'] = $_GET['style'];

	$output = ob_get_clean();

	// we use HTML 5
?>
<!DOCTYPE html>
<html>
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
 <link rel="stylesheet" media="all" type="text/css" href="style<?=@$_SESSION['style']?>.css">
<? if (Login::$admin) { ?>
 <link rel="stylesheet" media="all" type="text/css" href="admin<?=@$_SESSION['style']?>.css">
<? } ?>
 <link rel="icon" href="/favicon.ico" type="image/x-icon">
 <title><?=h($title)?></title>
</head>
<body>

<?
	if (DEBUG) {
?>
<!--
GET: <? print_r($_GET); echo "\n"; ?>
POST: <? print_r($_POST); echo "\n"; ?>
SESSION: <? if (isset($_SESSION)) { print_r($_SESSION); } echo "\n"; ?>
BN: <?=BN."\n"?>
-->
<?
	}
?>

<div id="head">
	<div id="logo"><a href="<?=DOCROOT?>index.php">Basisentscheid</a></div>
	<div id="nav">
<? html_navigation(); ?>
	</div>
	<div id="user">
<? html_user(); ?>
	</div>
	<div class="clearfix"></div>
</div>

<h1><?=h($title)?></h1>

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
 * display the navigation
 */
function html_navigation() {
	navlink('proposals.php', _("Proposals"));
	if (Login::$admin) {
		navlink( 'admin_areas.php', _("Areas"));
	} else {
		navlink( 'areas.php', _("Areas"));
	}
	navlink('periods.php', _("Periods"));
}


/**
 * one navigation link
 *
 * @param string  $file
 * @param string  $title
 */
function navlink($file, $title) {
?>
		<a href="<?=$file?>"<? if (BN==$file) { ?> class="active"<? } ?>><?=$title?></a>
<?
}


/**
 * display the user/login area
 */
function html_user() {
	if (Login::$member or Login::$admin) {
		if (Login::$member) {
?>
	<?=_("Logged in as")?> <a href="member.php"><?=Member::username_static(Login::$member->username)?></a>
<?
		} else {
?>
	<?=_("Logged in as Admin")?> <a href="admin_.php"><?=Login::$admin->username?></a>
<?
		}
?>
	<form action="<?=URI::$uri?>" method="post" class="button" style="margin-left: 10px">
	<input type="hidden" name="action" value="logout">
	<input type="submit" value="<?=_("Logout")?>">
	</form>
	<?
	} else {
		// These two links are for development and demonstration purposes only and have to be removed in live environment!
?>
	<a href="admin.php">Login as admin</a>
	<a href="local_member_login.php">Login as local member</a>
<?
		// login as member via ID server
?>
	<form action="login.php" method="post" class="button">
	<input type="hidden" name="origin" value="<?=h($_SERVER['REQUEST_URI'])?>">
	<input type="submit" value="<?=_("Login")?>">
	</form>
<?
	}
}


/**
 * foot part of the page
 */
function html_foot() {
?>

<div id="foot"><a href="about.php"><?=_("About")?></a></div>
</body>
</html>
<?
}


/**
 * message, that an action was successful
 *
 * @param unknown $text
 */
function success($text) {
?>
<p class="success">&#10003; <?=h($text)?></p>
<?
}


/**
 * notice
 *
 * @param unknown $text
 */
function notice($text) {
?>
<p class="notice">&#10148; <?=h($text)?></p>
<?
}


/**
 * a non fatal user error
 *
 * @param unknown $text
 */
function warning($text) {
?>
<p class="warning">&#9747; <?=h($text)?></p>
<?
}


/**
 * a fatal user error
 *
 * @param unknown $text
 */
function error($text) {
	if (empty($GLOBALS['html_head_issued'])) {
		html_head(_("Error"));
	}
?>
<p class="error">&#9747; <?=h($text)?></p>
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
 * @param string  $selected (optional)
 */
function input_select($name, $options, $selected=false) {
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
 * display signs for true or false
 *
 * @param boolean $value
 */
function display_checked($value) {
	if ($value) echo "&#10003;"; else echo "&#9711;";
}


/**
 *
 * @param unknown $value
 * @param unknown $required
 * @param unknown $title
 * @param unknown $color
 */
function bargraph($value, $required, $title, $color) {

	$width = 100;
	$width_filled = round( min($value, $required) / $required * $width );
	$width_empty = $width - $width_filled;

?>
<div class="bargraph" title="<?=$title?>"><div class="bar" style="background-color:<?=$color?>; width:<?=$width_filled?>px">&nbsp;</div><div class="bar" style="background-color:#FFFFFF; width:<?=$width_empty?>px">&nbsp;</div><div class="clear"></div></div>
<?

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
 * @param string  $text
 */
function help($text) {
?>
<div class="help">
<?=strtr($text, array("\n\n"=>"<p>"))?>
</div>
<?
}
