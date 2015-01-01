<?
/**
 * common functions required by every script
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common_cli.php
 * @see inc/common_http.php
 */


/**
 * read version from file
 *
 * @return string
 */
function version() {
	static $version;
	if (!$version) $version = trim(file_get_contents(DOCROOT."VERSION"));
	return $version;
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
 * message, that an action was successful
 *
 * @param string  $text
 */
function success($text) {
	// In tests or cron jobs we're not interested in this.
	if (PHP_SAPI=="cli") return;
?>
<p class="success">&#10003; <?=h($text)?></p>
<?
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
 * @param boolean $content2html (optional) format content
 */
function warning($text, $content2html=false) {
	if (PHP_SAPI=="cli") {
		// for tests
		trigger_error($text, E_USER_WARNING);
	} else {
?>
<p class="warning">&#9747; <?= $content2html ? content2html($text) : h($text) ?></p>
<?
	}
}


/**
 * a fatal user error
 *
 * @param string  $text
 * @param boolean $content2html (optional) format content
 */
function error($text, $content2html=false) {
	if (PHP_SAPI=="cli") {
		// for tests
		trigger_error($text, E_USER_ERROR);
	} else {
		if (empty($GLOBALS['html_head_issued'])) {
			html_head(_("Error"));
		}
?>
<p class="error">&#9747; <?= $content2html ? content2html($text) : h($text) ?></p>
<?
		html_foot();
	}
	exit;
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
 * convert acceptance value for display
 *
 * @param integer $value
 * @return string
 */
function acceptance($value) {
	if ($value ===  1) return _("Yes");
	if ($value ===  0) return _("No");
	if ($value === -1) return _("Abstention");
	return "illegal value: ".h($value);
}


/**
 * convert score value for display
 *
 * @param integer $value
 * @return string
 */
function score($value) {
	if ($value === -1) return _("Abstention");
	return h($value);
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
 * translate a time interval
 *
 * @param string  $interval
 * @return string
 */
function translate_time_interval($interval) {
	return str_ireplace(
		['days',    'day',    'hours',    'hour',    'minutes',    'minute'   ],
		[_("days"), _("day"), _("hours"), _("hour"), _("minutes"), _("minute")],
		$interval
	);
}


/**
 * prepare a fraction for display
 *
 * @param array   $numden
 * @return string
 */
function numden(array $numden) {
	list($num, $den) = $numden;
	if (!$den) return 0;
	if ($den==100) return $num."%";
	return $num."/".$den;
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
 * remove a value from an array
 *
 * @param array   $array (reference)
 * @param mixed   $value
 */
function array_remove_value(&$array, $value) {
	foreach ( array_keys($array, $value) as $key ) unset($array[$key]);
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
 * replacement for wordwrap(), which is not multi byte safe
 *
 * @param string  $str
 * @param integer $width
 * @param string  $break
 * @param boolean $cut
 * @return string
 */
function mb_wordwrap($str, $width = 75, $break = "\n", $cut = false) {
	$lines = explode($break, $str);
	foreach ($lines as &$line) {
		$line = rtrim($line);
		if (mb_strlen($line) <= $width) continue;
		$words = explode(" ", $line);
		$line = "";
		$actual = "";
		foreach ($words as $word) {
			if (mb_strlen($actual.$word) <= $width) {
				$actual .= $word." ";
			} else {
				if ($actual != "") $line .= rtrim($actual).$break;
				$actual = $word;
				if ($cut) {
					while (mb_strlen($actual) > $width) {
						$line .= mb_substr($actual, 0, $width).$break;
						$actual = mb_substr($actual, $width);
					}
				}
				$actual .= " ";
			}
		}
		$line .= trim($actual);
	}
	return implode($break, $lines);
}


/**
 * wrapper for mail()
 *
 * @param string  $to
 * @param string  $subject
 * @param string  $body
 * @param array   $headers     (optional)
 * @param string  $fingerprint (optional) encrypt mail with the public key with this fingerprint
 * @return bool
 */
function send_mail($to, $subject, $body, array $headers=array(), $fingerprint="") {

	$subject = MAIL_SUBJECT_PREFIX.$subject;

	$headers[] = "Content-Type: text/plain; charset=UTF-8";
	$headers[] = "Content-Transfer-Encoding: 8bit";
	if (MAIL_FROM) $headers[] = "From: ".MAIL_FROM;

	$body = mb_wordwrap($body);

	if (GNUPG_SIGN_KEY) {
		$gnupg = new_gnupg();
		if ( $gnupg->addsignkey(GNUPG_SIGN_KEY) ) {
			if ($fingerprint) {
				if ( gnupg_keyinfo_matches_email($gnupg->keyinfo($fingerprint), $to) and $gnupg->addencryptkey($fingerprint) ) {
					$body = $gnupg->encryptsign($body);
				} else {
					$body .= "\n\n".mb_wordwrap(_("This email should be encrypted, but no available key matching your fingerprint and email address was found! Please check your settings:")." ".BASE_URL."settings_encryption.php");
					$body = $gnupg->sign($body);
				}
			} else {
				$body = $gnupg->sign($body);
			}
		} else {
			trigger_error("Gnupg sign key cound not be added", E_USER_WARNING);
		}
	}

	return mail($to, $subject, $body, join("\r\n", $headers));
}


/**
 * new gnupg object
 *
 * @return object
 */
function new_gnupg() {
	/** @noinspection PhpUndefinedClassInspection */
	$gnupg = new gnupg();
	putenv('GNUPGHOME='.GNUPGHOME);
	if (DEBUG) {
		/** @noinspection PhpUndefinedMethodInspection PhpUndefinedConstantInspection */
		$gnupg->seterrormode(GNUPG_ERROR_WARNING);
	}
	return $gnupg;
}


/**
 * check if the email address is among the key uids
 *
 * @param array   $info return of gnupg::keyinfo()
 * @param string  $mail
 * @return bool
 */
function gnupg_keyinfo_matches_email(array $info, $mail) {
	if (!isset($info[0]["uids"])) return false;
	foreach ( $info[0]["uids"] as $uid ) {
		if ( $uid['email'] == $mail and !$uid["revoked"] and !$uid["invalid"] ) {
			return true;
		}
	}
	return false;
}
