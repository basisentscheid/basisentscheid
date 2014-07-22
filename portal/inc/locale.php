<?
/**
 * Language selection
 *
 * Gettext-Contents are written in UTF-8, can contain HTML and will not be converted to HTML entities. Only doublequotes have to be encoded with &quot;
 *
 * This file is only included in pages, served by the webserver. The output of CLI scripts will not be translated.
 *
 * For the english language, not translation file is needed. For other languages there has to be a directory with the language code in the locale directory. For now there is only a german translation.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common.php
 */


// Available Languages
$languages = array(
	'en' => "en_GB.utf8",
	'de' => "de_DE.utf8"
);
// Default language
$language = "de";

/*
// Detect which language to use
if (isset($_GET['language']) and array_key_exists($_GET['language'], $languages)) {
	// Change language when the link to this language is clicked
	$language = $_GET['language'];
	$_SESSION['language'] = $language;
} elseif (isset($_SESSION['language']) and array_key_exists($_SESSION['language'], $languages)) {
	// Get the language from the session
	$language = $_SESSION['language'];
} elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
	// Detect browser language
	foreach ( explode(";", $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $httplang ) {
		$httplang = substr($httplang, 0, 2);
		if ( array_key_exists($httplang, $languages) ) {
			$language = $httplang;
			break;
		}
	}
}
*/

// Activate language
putenv("LANG=".$language);
$locale = $languages[$language];
if ( setlocale(LC_MESSAGES, $locale) === false ) {
	trigger_error("setlocale to ".$locale." failed", E_USER_WARNING);
}
bindtextdomain("messages", "./locale");
textdomain("messages");
bind_textdomain_codeset("messages", "UTF-8");

/*
// Build link to the other language
parse_str( $_SERVER['QUERY_STRING'], $queryarray );
$queryarray = array_merge( $queryarray, array("language"=>($language=='en'?'de':'en')) );
$languagelink = basename($_SERVER['PHP_SELF']);
if (count($queryarray)) {
	$languagelink .= "?";
	$first = true;
	foreach ($queryarray as $key => $value) {
		if ($first) $first=false; else $languagelink.="&";
		$languagelink .= $key."=".$value;
	}
}
*/
