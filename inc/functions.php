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
 *
 * @param unknown $value
 * @return unknown
 */
function m($value) {
	return DB::m($value);
}


/**
 *
 * @param unknown $text
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
 * Prüfen, ob der Sting mit was bestimmtem beginnt
 *
 * @param mixed   $var
 * @param mixed   $content
 * @return mixed
 */
function lefteq($var, $content) {
	return substr($var, 0, strlen($content))==$content;
}


/**
 * Prüfen, ob der String mit was bestimmtem endet
 *
 * @param mixed   $var
 * @param mixed   $content
 * @return mixed
 */
function righteq($var, $content) {
	return substr($var, strlen($content) * -1)==$content;
}



/**
 *
 */
function resetfirst() {
	global $first;
	$first = true;
}


/**
 *
 * @return unknown
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
	// intval(substr($d, 8, 2)).".".intval(substr($d, 5, 2)).".".substr($d, 0, 4);
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
 * Die Schlüssel eines Arrays auf die entsprechenden Werte setzen
 *
 * @param array   $in
 * @return array
 */
function array_mirror($in) {
	$out = array();
	foreach ( $in as $value ) {
		$out[$value] = $value;
	}
	return $out;
}


/**
 *
 * @param array   $numden
 * @return unknown
 */
function numden2percent(array $numden) {
	list($num, $den) = $numden;
	if (!$den) return 0;
	return round(100 * $num / $den)."%";
}


/**
 *
 * @param unknown $value
 * @return unknown
 */
function boolean($value) {
	if ($value=="t") return "&#10003;";
}
