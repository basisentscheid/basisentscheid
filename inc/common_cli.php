<?
/**
 * included in every command line script
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require DOCROOT."inc/config.php";
require DOCROOT."inc/errors.php";
require DOCROOT."inc/functions.php";
require DOCROOT."inc/functions_cli.php";

define("BN", basename($_SERVER['PHP_SELF']));

require DOCROOT."inc/locale.php";
