<?
/**
 * language selection
 *
 * Gettext contents are written in UTF-8 and should not contain HTML.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common.php
 */


// available Languages
$languages = array(
	'en' => "en_GB.utf8",
	'de' => "de_DE.utf8"
);

// activate language
putenv("LANG=".LANG);
$locale = $languages[LANG];
if ( setlocale(LC_MESSAGES, $locale) === false ) {
	trigger_error("setlocale to ".$locale." failed", E_USER_WARNING);
}

if (DEBUG) {
	// workaround for gettext caching
	$domains = glob("locale/".LANG."/LC_MESSAGES/messages-*.mo");
	$current = basename($domains[0], ".mo");
	bindtextdomain($current, "./locale");
	textdomain($current);
	bind_textdomain_codeset($current, "UTF-8");
} else {
	// In live environment probably the webserver has to be gracefully restarted to load a new gettext file.
	bindtextdomain("messages", "./locale");
	textdomain("messages");
	bind_textdomain_codeset("messages", "UTF-8");
}
