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
 * a non fatal user error
 *
 * @param string  $text
 */
function warning($text) {
	if (PHP_SAPI=="cli") {
		trigger_error($text, E_USER_WARNING);
	} else {
?>
<p class="warning">&#9747; <?=h($text)?></p>
<?
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
	return substr($string, 0, strlen($part))==$part;
}


/**
 * test if the string end with something
 *
 * @param string  $string
 * @param string  $part
 * @return boolean
 */
function righteq($string, $part) {
	return substr($string, strlen($part) * -1)==$part;
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
	return date(DATEYEAR_FORMAT, strtotime($date));
}


/**
 * format a date smart
 *
 * @param integer $time
 * @return string
 */
function dateformat_smart($time) {
	$time = strtotime($time);
	if ($time < time() - 180 * 86400) {
		// add year if more than half a year in the past
		return date(DATEYEAR_FORMAT, $time);
	} else {
		return date(DATE_FORMAT, $time);
	}
}


/**
 * format a timestamp human friendly from Postgres (YYYY-dd-mm HH:ii:ss+ZZ)
 *
 * @param string  $time
 * @return string
 */
function datetimeformat($time) {
	if ($time===NULL) return "";
	return date(DATETIMEYEAR_FORMAT, strtotime($time));
}


/**
 * format a timestamp smart
 *
 * @param integer $time
 * @return string
 */
function datetimeformat_smart($time) {
	$time = strtotime($time);
	if ($time > time() + 3 * 86400) {
		// ommit time if more than 3 days in the future
		return date(DATE_FORMAT, $time);
	} elseif ($time < time() - 180 * 86400) {
		// add year if more than half a year in the past
		return date(DATETIMEYEAR_FORMAT, $time);
	} else {
		return date(DATETIME_FORMAT, $time);
	}
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


/**
 * fetch something from the ID server
 *
 * @param string  $url
 * @return array
 */
function curl_fetch($url) {

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);

	// https handling
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_CAINFO,  "ssl/cacerts.pem");
	curl_setopt($ch, CURLOPT_SSLCERT, "ssl/cmr.cx.pem");
	curl_setopt($ch, CURLOPT_SSLKEY,  "ssl/cmr.cx_priv.pem");

	$result = curl_exec($ch);

	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	if ($curl_error = curl_error($ch)) {
		throw new Exception($curl_error, Exception::CURL_ERROR);
	} else {
		$json_decode = json_decode($result, true);
	}
	curl_close($ch);

	return array(
		'result' => (null === $json_decode) ? $result : $json_decode,
		'code' => $http_code,
		'content_type' => $content_type
	);
}
