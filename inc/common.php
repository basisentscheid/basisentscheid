<?
/**
 * included by every script
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common_cli.php
 * @see inc/common_http.php
 */


define("BN", basename($_SERVER['PHP_SELF']));


/**
 * load classes on demand
 *
 * @param string  $class_name
 */
function __autoload($class_name) {
	/** @noinspection PhpIncludeInspection */
	require_once DOCROOT.'inc/classes/'.$class_name.'.php';
}
