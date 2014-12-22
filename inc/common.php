<?
/**
 * included by every script
 *
 * @see inc/common_http.php
 * @see inc/common_cli.php
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


define("VERSION", "development");

define("BN", basename($_SERVER['PHP_SELF']));

// autoload classes on demand
set_include_path(DOCROOT."inc/classes/");
spl_autoload_extensions('.php');
spl_autoload_register();
