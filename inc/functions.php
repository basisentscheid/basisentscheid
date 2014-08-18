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
 * a notice to the user
 *
 * @param string  $text
 */
function notice($text) {
	// In tests or cron jobs we're not interested in this.
	if (PHP_SAPI=="cli") return;
?>
<p class="notice">&#10148; <?=h($text)?></p>
<?
}


/**
 * a non fatal user error
 *
 * @param string  $text
 */
function warning($text) {
	if (PHP_SAPI=="cli") {
		// for tests
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
 * wrapper for mail()
 *
 * @param string  $to
 * @param string  $subject
 * @param string  $body
 * @param array   $headers (optional)
 * @return boolean
 */
function send_mail($to, $subject, $body, array $headers=array()) {

	$headers[] = "Content-Type: text/plain; charset=UTF-8";
	$headers[] = "Content-Transfer-Encoding: 8bit";
	if (MAIL_FROM) $headers[] = "From: ".MAIL_FROM;

	//$to = ERROR_MAIL;

	return mail($to, $subject, $body, join("\r\n", $headers));
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
	curl_setopt($ch, CURLOPT_CAINFO,  DOCROOT."ssl/cacerts.pem");
	curl_setopt($ch, CURLOPT_SSLCERT, DOCROOT."ssl/cmr.cx.pem");
	curl_setopt($ch, CURLOPT_SSLKEY,  DOCROOT."ssl/cmr.cx_priv.pem");

	if ( $result = curl_exec($ch) ) {
		$result = json_decode($result, true);
	} else {
		trigger_error(curl_error($ch), E_USER_NOTICE);
	}
	curl_close($ch);

	return $result;
}


/**
 * get new ngroups and update existing ngroups
 *
 * We never delete any ngroups.
 */
function update_ngroups() {

	$result_ngroups = curl_fetch(NGROUPS_URL);
	/*
	["results"]=>
	array(5) {
		[0]=>
		object(stdClass)#2 (5) {
			["id"]=>
			int(1)
			["name"]=>
			string(4) "Bund"
			["parent"]=>
			NULL
			["depth"]=>
			int(0)
			["description"]=>
			string(0) ""
		}
		[1]=>
		object(stdClass)#3 (5) {
			["id"]=>
			int(2)
			["name"]=>
			string(6) "Bayern"
			["parent"]=>
			string(59) "https://example.com/api/nested_groups/1/"
			["depth"]=>
			int(1)
			["description"]=>
			string(0) ""
		}
		[2]=>
		object(stdClass)#4 (5) {
			["id"]=>
			int(5)
			["name"]=>
			string(12) "Niederbayern"
			["parent"]=>
			string(59) "https://example.com/api/nested_groups/2/"
			["depth"]=>
			int(2)
			["description"]=>
			string(0) ""
		}
		[3]=>
		object(stdClass)#5 (5) {
			["id"]=>
			int(3)
			["name"]=>
			string(10) "Oberbayern"
			["parent"]=>
			string(59) "https://example.com/api/nested_groups/2/"
			["depth"]=>
			int(2)
			["description"]=>
			string(0) ""
		}
		[4]=>
		object(stdClass)#6 (5) {
			["id"]=>
			int(4)
			["name"]=>
			string(6) "Hessen"
			["parent"]=>
			string(59) "https://example.com/api/nested_groups/1/"
			["depth"]=>
			int(1)
			["description"]=>
			string(0) ""
		}
	}
	*/

	if (!isset($result_ngroups['results'])) {
		trigger_error("Fetching ngroups from ID server failed", E_USER_WARNING);
		return;
	}

	// use ids as index
	foreach ( $result_ngroups['results'] as $ng ) {
		// convert parents from urls to ids
		if ($ng['parent']!==null) {
			if ( preg_match("#/nested_groups/(\d+)/$#", $ng['parent'], $matches) ) {
				$ng['parent'] = $matches[1];
			} else {
				trigger_error("Ngroup parent ".$ng['parent']." does not match expression", E_USER_WARNING);
				return;
			}
		}
		$fields_values = array(
			'id'     => $ng['id'],
			'name'   => $ng['name'],
			'parent' => $ng['parent']
		);
		DB::insert_or_update("ngroups", $fields_values, array('id'));
	}

}
