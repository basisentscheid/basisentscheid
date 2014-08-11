<?
/**
 * common functions required by every script
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common.php
 */


/**
 * load classes on demand
 *
 * @param string  $class_name
 */
function __autoload($class_name) {
	require_once DOCROOT.'inc/classes/'.$class_name.'.php';
}


/**
 * display a text independent of output interface
 *
 * @param string  $text
 */
function out($text) {
	if ( php_sapi_name() != "cli" ) {
?>
<p><? echo nl2br($text)?></p>
<?
	} else {
		echo $text."\n";
	}
}


/**
 * convert UTF-8 to HTML
 *
 * @param string  $string
 * @return string
 */
function h($string) {
	return htmlspecialchars($string, ENT_COMPAT | ENT_HTML5, "UTF-8");
}


/**
 * convert HTML to UTF-8
 *
 * Vor allem für Strings von Gettext, die bereits HTML-Entities enthalten, und nicht ausgegeben, sondern anderweitig verwendet werden sollen.
 *
 * @param string  $string
 * @return string
 */
function hd($string) {
	return html_entity_decode($string, ENT_COMPAT, "utf-8");
}


/**
 * for debugging: display the content of a variable formatted
 *
 * @param mixed   $var  variable to display
 * @param bool    $exit (optional) exit the program after output
 */
function pre_r($var, $exit=false) {
	?><pre><?
	var_dump($var);
	?></pre><?
	if ($exit) exit;
}


/**
 * test if the string begins with something
 *
 * @param string  $string
 * @param string  $part
 * @return boolean
 */
function lefteq($string, $part) {
	return substr($string, 0, strlen($part))==$string;
}


/**
 * test if the string end with something
 *
 * @param string  $string
 * @param string  $part
 * @return boolean
 */
function righteq($string, $part) {
	return substr($string, strlen($part) * -1)==$string;
}


/**
 * reset first()
 */
function resetfirst() {
	global $first;
	$first = true;
}


/**
 * returns true on the first time called
 *
 * @return boolean
 */
function first() {
	global $first;
	if ($first) {
		$first = false;
		return true;
	} else {
		return false;
	}
}


/**
 * format a date human friendly from Postgres (YYYY-dd-mm)
 *
 * @param string  $date
 * @return string
 */
function dateformat($date) {
	if ($date===NULL) return "";
	return date(DATE_FORMAT, strtotime($date));
}


/**
 * format a timestamp human friendly from Postgres (YYYY-dd-mm HH:ii:ss+ZZ)
 *
 * @param string  $time
 * @return string
 */
function datetimeformat($time) {
	if ($time===NULL) return "";
	return date(DATETIME_FORMAT, strtotime($time));
}


/**
 * format a day time without date human friendly from Postgres (HH:ii:ss+ZZ)
 *
 * @param string  $time
 * @return string
 */
function timeformat($time) {
	if ($time===NULL) return "";
	return date(TIME_FORMAT, strtotime($time));
}


/**
 * convert a fraction to a percent value
 *
 * @param array   $numden
 * @return string
 */
function numden2percent(array $numden) {
	list($num, $den) = $numden;
	if (!$den) return 0;
	return round(100 * $num / $den)."%";
}


/**
 * split a string into an array
 *
 * @param string  $delimiter
 * @param string  $string
 * @return array
 */
function explode_no_empty($delimiter, $string) {
	if ($string) return explode($delimiter, $string);
	return array();
}


/**
 * truncate a string and add dots
 *
 * @param string  $string
 * @param integer $length maximum length
 * @return string
 */
function limitstr($string, $length) {
	$string = trim($string);
	if ( mb_strlen($string) > $length ) return mb_substr($string, 0, $length)."...";
	return $string;
}
