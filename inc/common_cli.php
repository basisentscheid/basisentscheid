<?
/**
 * included in every command line script
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require DOCROOT."inc/config.php";
require DOCROOT."inc/errors.php";

ini_set('default_charset', 'UTF-8');
mb_internal_encoding("UTF-8");

require DOCROOT."inc/common.php";
require DOCROOT."inc/functions.php";
require DOCROOT."inc/functions_cli.php";

require DOCROOT."inc/locale.php";
