#!/usr/bin/php
<?
/**
 * create a first admin user
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require "../inc/common_cli.php";

$a = new Admin;
$a->username = "test";
$a->password = password_hash("test", PASSWORD_DEFAULT);
$a->create();
