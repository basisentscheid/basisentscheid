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
 * Return CSS classes with alternating background colors
 *
 * @param mixed   $change (optional) if this value changes, the color changes
 * @param mixed   $suffix (optional) for subclasses
 * @return string
 */
function stripes($change=false, $suffix="") {
	static $colorid = 0;
	static $change_last = null;
	if ($change===false or $change_last != $change) {
		$colorid = ($colorid + 1) % 2;
	}
	$change_last = $change;
	return ' class="td'.$colorid.$suffix.'"';
}


/**
 *
 * @param unknown $date
 * @return unknown
 */
function combine_date($date) {
	return $date['year']."-".str_pad($date['month'], 2, "0", STR_PAD_LEFT)."-".str_pad($date['day'], 2, "0", STR_PAD_LEFT);
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
 *
 * @param unknown $datum
 * @return unknown
 */
function datum_empty($datum) {
	if ($datum=="0.0.0000") return "";
	return $datum;
}


/**
 * format a timestamp human friendly from Postgres (YYYY-dd-mm HH:ii:ss+ZZ)
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
 * human friendly state names
 *
 * @param string  $issue_state
 * @param string  $proposal_state
 * @return string
 */
function state_name($issue_state, $proposal_state) {
	static $proposal_states_admission, $proposal_states_cancelled, $issue_states;

	if ($issue_state=="admission") {
		if (!$proposal_states_admission) {
			$proposal_states_admission = array(
				'draft'     => _("Draft"),
				'submitted' => _("Submitted"),
				'admitted'  => _("Admitted")
			);
		}
		return $proposal_states_admission[$proposal_state];
	}

	if ($issue_state=="cancelled") {
		if (!$proposal_states_cancelled) {
			$proposal_states_cancelled = array(
				'revoke'    => _("Revoked"),
				'cancelled' => _("Cancelled"),
				'done'      => _("Done otherwise")
			);
		}
		return $proposal_states_cancelled[$proposal_state];
	}

	if (!$issue_states) {
		$issue_states = array(
			'debate'      => _("Debate"),
			'preparation' => _("Voting preparation"),
			'voting'      => _("Voting"),
			'counting'    => _("Counting"),
			'finished'    => _("Finished"),
			'cleared'     => _("Finished and cleared")
		);
	}
	return $issue_states[$issue_state];

}


/**
 *
 * @param unknown $value
 * @return unknown
 */
function boolean($value) {
	if ($value=="t") return "&#10003;";
}
