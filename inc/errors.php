<?
/**
 * error handling
 *
 * requires PHP >= 5.3
 *
 * recommended .htaccess settings:
 *   php_value error_reporting E_ALL
 *   php_flag  display_errors on/off (depending on whether you want to see errors before the error handler could be set)
 *   php_value log_errors on
 *   php_value error_log var/log/phperrors.log
 *
 * The error log files var/log/phperrors*.log should be rotated by rotatelog for example.
 *
 * crontab examples:
 * # compress backtraces
 * 0 1  * * *  find <path>/var/errors/ -name "*.txt" -exec gzip {} \;
 * # delete backtraces after 30 days
 * 0 2  * * *  find <path>/var/errors/ ! -mtime -30 -exec rm {} \;
 *
 * These functions use only native PHP functions and the functions in this file, because it can not be assumed, that other functions are accessible and work without errors.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common_cli.php
 * @see inc/common_http.php
 */


// configuration

// which errors to display on web pages
// (On the command line all errors will be displayed.)
// examples:
//   all errors:        E_ALL
//   important errors:  E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED
//   no errors:         0
if (!defined("ERROR_DISPLAY"))             define("ERROR_DISPLAY", E_ALL);

// mail address for error mails
// Set to empty string to disable error mails.
if (!defined("ERROR_MAIL"))                define("ERROR_MAIL", "root");

// prefix for the subject of the error mails
if (!defined("ERROR_MAIL_SUBJECT_PREFIX")) define("ERROR_MAIL_SUBJECT_PREFIX", "");

// write backtrace to files
// For the link in the backtrace mails BASE_URL should be set to the absolute URL of the application with trailing slash.
if (!defined("ERROR_BACKTRACE_FILES"))     define("ERROR_BACKTRACE_FILES", false);

// output backtraces to the browser or shell
// (for debugging only!)
if (!defined("ERROR_BACKTRACE_DISPLAY"))   define("ERROR_BACKTRACE_DISPLAY", false);


error_reporting(E_ALL);
if (PHP_SAPI=="cli") {
	// separate files for different users to avoid problems with file permissions
	ini_set("error_log", DOCROOT."var/log/phperrors_cli_".getenv('USER').".log");
} else {
	ini_set("error_log", DOCROOT."var/log/phperrors.log");
}
ini_set("log_errors", "on");
ini_set("display_errors", "off");
set_error_handler("user_error_handler");
register_shutdown_function("fatal_error_handler");


/**
 * user defined error handling function
 *
 * @uses error_send_mail()
 * @param integer $errno
 * @param string  $errstr
 * @param string  $errfile
 * @param integer $errline
 * @param array   $errcontext
 */
function user_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {

	// ignore errors supressed by "@"
	if ( !error_reporting() ) return;

	// ignore notices (like error_reporting = E_ALL & ~E_NOTICE)
	// if ($errno==E_NOTICE) return;

	// ignore strict
	if ( $errno==E_STRICT ) return;

	// ignore deprecated
	if ( $errno==E_DEPRECATED ) return;

	// ignore warnings caused by invalid session ids from bots
	if (
		$errno==E_WARNING and (
			strpos($errstr, "The session id contains illegal characters, valid characters are a-z, A-Z, 0-9 and '-,'") or
			$errstr=="Unknown: Failed to write session data (files). Please verify that the current setting of session.save_path is correct (/var/lib/php5)"
		)
	) return;

	// repeated errors
	static $previous_errors = array();
	$this_error = array($errno, $errstr, $errfile, $errline);
	if ( ! $repeated = in_array($this_error, $previous_errors) ) $previous_errors[] = $this_error;

	error_common($errno, $errstr, $errfile, $errline, $errcontext, false, $repeated);
}


/**
 * Fatal errors are not catched by the user_error_handler, so we use this shutdown function instead.
 */
function fatal_error_handler() {

	$error = error_get_last();

	// The script terminated without an error.
	if ( $error === NULL) return;

	error_common($error['type'], $error['message'], $error['file'], $error['line'], array(), true);
}


/**
 * common part for all types of errors
 *
 * @param integer $errno
 * @param string  $errstr
 * @param string  $errfile
 * @param integer $errline
 * @param array   $errcontext
 * @param boolean $fatal
 * @param boolean $repeated   (optional)
 */
function error_common($errno, $errstr, $errfile, $errline, $errcontext, $fatal, $repeated=false) {

	// names of the errors
	static $errortype = array (
		E_ERROR             => "Error",
		E_WARNING           => "Warning",
		E_PARSE             => "Parse Error",
		E_NOTICE            => "Notice",
		E_CORE_ERROR        => "Core Error",
		E_CORE_WARNING      => "Core Warning",
		E_COMPILE_ERROR     => "Compile Error",
		E_COMPILE_WARNING   => "Compile Warning",
		E_USER_ERROR        => "User Error",
		E_USER_WARNING      => "User Warning",
		E_USER_NOTICE       => "User Notice",
		E_STRICT            => "Strict Notice",
		E_RECOVERABLE_ERROR => "Recoverable Error",
		E_DEPRECATED        => "Deprecated Notice",
		E_USER_DEPRECATED   => "User Deprecated Notice"
	);

	$show = $errno & ERROR_DISPLAY;

	// display error
	if (PHP_SAPI!="cli") {
		if ($show and !$repeated) {
?>
<div class="syserror">
<b><?=$errortype[$errno]?></b>:  <?=$errstr?><br>
in <b><?=$errfile?></b> on line <b><?=$errline?></b>
<?
		}
	} else {
		echo $errortype[$errno].":  ".$errstr." in ".$errfile." on line ".$errline."\n";
	}

	// log error
	$logline = "PHP ".$errortype[$errno].":  ".$errstr." in ".$errfile." on line ".$errline;
	error_log($logline, 0);

	// backtrace
	if ((ERROR_MAIL or ERROR_BACKTRACE_FILES or ERROR_BACKTRACE_DISPLAY) and !$repeated) {

		// We prefer SCRIPT_FILENAME because webservers often change the working directory when calling a shutdown function.
		if ( PHP_SAPI!="cli" and isset($_SERVER['SCRIPT_FILENAME']) ) {
			$absolute_path = dirname($_SERVER['SCRIPT_FILENAME'])."/";
		} else {
			$absolute_path = getcwd()."/";
		}
		if (defined('DOCROOT')) $absolute_path .= DOCROOT;

		$backtrace = date("r")."\n\n";

		$backtrace .= $errortype[$errno].":  ".$errstr."\n";
		$backtrace .= "in ".$errfile." on line ".$errline."\n\n";

		// code section where the error occurred
		$backtrace .= str_repeat("-", 70)."\n";
		if ( is_readable($errfile) and $f = fopen($errfile, "r") ) {
			$line = 1;
			while ( $buffer = fgets($f) and $line <= $errline + 10 ) {
				if ($line >= $errline - 10) {
					$backtrace .= str_pad($line, 5, " ", STR_PAD_LEFT);
					if ($line==$errline) $backtrace .= " > "; else $backtrace .= "   ";
					$backtrace .= $buffer;
				}
				$line++;
			}
			fclose($f);
		} else {
			$backtrace .= "The file could not be read!\n";
		}
		$backtrace .= str_repeat("-", 70)."\n\n";

		// some interesting variables
		if (!empty($_SERVER['SCRIPT_FILENAME'])) {
			$backtrace .= "_SERVER[SCRIPT_FILENAME]: ".$_SERVER['SCRIPT_FILENAME']."\n";
		}
		if (!empty($_SERVER['REQUEST_URI'])) {
			$backtrace .= "_SERVER[REQUEST_URI]: ".$_SERVER['REQUEST_URI']."\n";
		}
		if (!empty($_SERVER['argv'])) {
			$backtrace .= "_SERVER[argv]: ".print_r( $_SERVER['argv'], true)."\n";
		}
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$backtrace .= "_SERVER[HTTP_USER_AGENT]: ".$_SERVER['HTTP_USER_AGENT']."\n";
		}
		$backtrace .= "getcwd(): ".getcwd()."\n\n";

		// actual backtrace
		$debug_backtrace = debug_backtrace();
		// We don't want to know what happened in the error handling functions.
		unset($debug_backtrace[0], $debug_backtrace[1]);
		$backtrace .= "======= debug_backtrace ======= ".print_r($debug_backtrace, true)."\n";

		// sent the first 10000 letters until here also by mail
		if (ERROR_MAIL) {
			if (
				strlen($backtrace) > 10000 and
				// make the last line complete
				$pos = strpos($backtrace, "\n", 10000)
			) {
				$mailbody = substr($backtrace, 0, $pos+10001)."[...]\n";
			} else {
				$mailbody = $backtrace;
			}
		}

		// extended backtrace
		if (ERROR_BACKTRACE_FILES or ERROR_BACKTRACE_DISPLAY) {

			// We list the superglobals separately, so we don't want to have them also in the context and the global variables.
			$superglobals = array(
				'HTTP_POST_VARS',    '_POST',
				'HTTP_GET_VARS',     '_GET',
				'HTTP_COOKIE_VARS',  '_COOKIE',
				'HTTP_SERVER_VARS',  '_SERVER',
				'HTTP_ENV_VARS',     '_ENV',
				'HTTP_SESSION_VARS', '_SESSION',
				'HTTP_POST_FILES',   '_FILES',
				'_REQUEST',
				'GLOBALS',
			);

			$globals = array();
			foreach ( $GLOBALS as $key => $value ) {
				if (!in_array($key, $superglobals)) $globals[$key] = $value;
			}
			$context = array();
			foreach ( $errcontext as $key => $value ) {
				if (!in_array($key, $superglobals)) $context[$key] = $value;
			}
			if ($globals==$context) {
				$backtrace .= "======= global variables = error context (without superglobals) ======= ".print_r($globals, true)."\n";
			} else {
				$backtrace .= "======= error context (without superglobals) ======= "   .print_r($context, true)."\n";
				$backtrace .= "======= global variables (without superglobals) ======= ".print_r($globals, true)."\n";
			}

			$backtrace .= "======= _GET ======= "    .print_r($_GET,    true)."\n";
			$backtrace .= "======= _POST ======= "   .print_r($_POST,   true)."\n";
			$backtrace .= "======= _FILES ======= "  .print_r($_FILES,  true)."\n";
			$backtrace .= "======= _COOKIE ======= " .print_r($_COOKIE, true)."\n";
			$backtrace .= "======= _SESSION ======= ".print_r(( isset($_SESSION) ? $_SESSION : "undefined\n" ), true)."\n";
			$server = $_SERVER;
			// don't expose the http password
			if (isset($server["PHP_AUTH_PW"])) $server["PHP_AUTH_PW"] = "********";
			$backtrace .= "======= _SERVER ======= " .print_r($server,  true)."\n";
			$backtrace .= "======= _ENV ======= "    .print_r($_ENV,    true)."\n";

		}

		// write backtrace file
		if (ERROR_BACKTRACE_FILES) {
			$microtime = microtime();
			$filename = "var/errors/backtrace_".substr($microtime, 11)."_".substr($microtime, 2, 8)."_".rand(100, 999).".txt";
			file_put_contents($absolute_path.$filename, $backtrace);
			if (ERROR_MAIL) {
				/** @noinspection PhpUndefinedVariableInspection */
				$mailbody .= "\ncomplete backtrace:\n";
				if (defined('BASE_URL')) $mailbody .= BASE_URL;
				$mailbody .= $filename."\n";
			}
		}

		// send mail
		if (ERROR_MAIL) {
			$subject = ERROR_MAIL_SUBJECT_PREFIX.$errortype[$errno].": ";
			if (strlen($errstr) > 800) {
				$subject .= substr($errstr, 0, 797)."...";
			} else {
				$subject .= $errstr;
			}
			/** @noinspection PhpUndefinedVariableInspection */
			error_send_mail($subject, $mailbody, $absolute_path);
		}

		// display backtrace
		if (PHP_SAPI!="cli") {
			// display as HTML
			if ($show and !$repeated) {
				if (ERROR_BACKTRACE_DISPLAY) {
?>
<br>
<pre><?=htmlspecialchars($backtrace)?></pre>
<?
				}
?>
</div>
<?
			}
		} else {
			// display as Text
			if (ERROR_BACKTRACE_DISPLAY) {
				echo $backtrace."\n";
			}
		}

	}

	// abort at fatal errors
	if ( $fatal or $errno==E_ERROR or $errno==E_CORE_ERROR or $errno==E_USER_ERROR or $errno==E_RECOVERABLE_ERROR ) {
		if (PHP_SAPI!="cli") {
			// display as HTML
			if (empty($GLOBALS['html_head_issued'])) {
				if (function_exists("html_head")) {
					html_head("System Error");
				} else {
					ob_end_flush();
				}
			}
			if ($show) {
?>
<p class="syserror">Script execution aborted.</p>
<?
			}
			if (function_exists("html_foot")) {
				html_foot();
			}
		} else {
			// display as text
			echo "Script execution aborted.\n";
		}
		exit;
	}

}


/**
 * send error mail as long as the limit is not reached
 *
 * @param string  $subject
 * @param string  $mailbody
 * @param string  $absolute_path
 */
function error_send_mail($subject, $mailbody, $absolute_path) {

	// counter
	$count = 0;
	clearstatcache();
	ignore_user_abort(true);
	$date = date("Y-m-d-a");
	$file = "var/errors/mail_counter_".$date;
	// separate files for different users to avoid problems with file permissions
	if (PHP_SAPI=="cli") {
		$file .= "_cli_".getenv('USER');
	}
	if ( $fh = fopen($absolute_path.$file, 'a+') ) {
		if ( flock($fh, LOCK_EX) ) {
			rewind($fh);
			$count = intval(fgets($fh)) + 1;
			ftruncate($fh, 0);
			fwrite($fh, $count."\n");
			flock($fh, LOCK_UN);
		}
		fclose($fh);
	}
	ignore_user_abort(false);

	// send no more mails after the 100th error
	if ($count > 100) return;

	// send one last mail at the 100th error
	if ($count == 100) {
		$subject = "PHP error mail limit reached!";
		$mailbody = "The error mail limit (100 mails in 12 hours) is reached!\n\n"
			."In ".$date." no error mails will be sent anymore.\n\n"
			."By deleting the file ".$file."\n"
			."the counter can be reset.\n";
	}

	mail(ERROR_MAIL, $subject, $mailbody, "Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n");

}
