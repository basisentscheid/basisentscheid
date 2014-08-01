#!/usr/bin/php
<?
/**
 * test error handling
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";


trigger_error("Test", E_USER_NOTICE);
trigger_error("Test", E_USER_WARNING);
trigger_error("Test", E_USER_ERROR);


// PHP Parse error
//echo Hello World!

// PHP Warning:  Division by zero
// echo 1 / 0;
