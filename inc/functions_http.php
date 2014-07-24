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

	$output = ob_get_clean();

	// we use HTML 5
?>
<!DOCTYPE html>
<html>
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
 <meta http-equiv="Content-Style-Type" content="text/css">
 <link rel="stylesheet" media="all" type="text/css" href="style.css">
<? if (Login::$admin) { ?>
 <link rel="stylesheet" media="all" type="text/css" href="admin.css">
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
BN: <? print_r(BN); echo "\n"; ?>
-->
<?
	}
?>

<div id="head">
	<div id="logo">
		Basisentscheid
	</div>
	<div id="nav">
		<a href="proposals.php"<? if (BN=="proposals.php") { ?> class="active"<? } ?>><?=_("Proposals")?></a>
		<a href="areas.php"<?     if (BN=="areas.php") { ?> class="active"<? } ?>><?=_("Areas")?></a>
		<a href="periods.php"<?   if (BN=="periods.php") { ?> class="active"<? } ?>><?=_("Periods")?></a>
	</div>
	<div id="user">
<?
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
<form action="<?=BN?>" method="post" class="button" style="margin-left: 10px">
<input type="hidden" name="action" value="logout">
<input type="submit" value="<?=_("Logout")?>">
</form>
<?
	} else {
?>
<a href="admin.php">Login as Admin</a>
<form action="login.php" method="post" class="button">
<input type="hidden" name="origin" value="<?=h($_SERVER['REQUEST_URI'])?>">
<input type="submit" value="<?=_("Login")?>">
</form>
<?
	}
?>
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
<?
		}
		unset($_SESSION['output']);
	}

	// output from before the html head
	if ($output) {
?>
<div class="messages"><?=$output?></div>
<?
	}

	$GLOBALS['html_head_issued'] = true;
}


/**
 * foot part of the page
 */
function html_foot() {
?>

<hr>
</body>
</html>
<?
}


/**
 *
 */
function html_foot_exit() {
	html_foot();
	exit;
}


/**
 *
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
 *
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

	// TODO: restrict available periods

	$issue->period = $period->id;
	$issue->update(array("period"));

	redirect();
}


/**
 * build URI
 *
 * @param array   $params
 * @return string
 */
function uri(array $params) {
	$uri = BN;
	if ($query = http_build_query($params)) $uri .= "?".$query;
	return $uri;
}


/**
 * add or replace parameters to the current URI
 *
 * @param array   $params
 * @return string
 */
function uri_append(array $params) {
	parse_str($_SERVER['QUERY_STRING'], $query_array);
	foreach ( $params as $key => $value ) {
		$query_array[$key] = $value;
	}
	return uri($query_array);
}


/**
 * remove parameters from the current URI
 *
 * @param array   $keys
 * @return string
 */
function uri_strip(array $keys) {
	parse_str($_SERVER['QUERY_STRING'], $query_array);
	foreach ( $keys as $key ) {
		unset($query_array[$key]);
	}
	// if all elements are unset, the array behaves not as an array anymore
	if (!count($query_array)) $query_array = array();
	return uri($query_array);
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
 *
 * @param unknown $text
 */
function notice($text) {
?>
<p class="notice"><?=h($text)?></p>
<?
}


/**
 * a non fatal user error
 *
 * @param unknown $text
 */
function warning($text) {
?>
<p class="warning"><?=h($text)?></p>
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
<p class="error"><?=h($text)?></p>
<?
	html_foot();
	exit;
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
		if ($key==$selected) { ?> selected<? }
		?>><?=$value?></option>
<?
	}
?>
</select>
<?
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
