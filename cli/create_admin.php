#!/usr/bin/php
<?
/**
 * create a first admin user
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

$a = new Admin;
$a->username = "test";
$a->password = crypt("test");
$a->create();
