#!/usr/bin/php
<?
/**
 * test locale
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require "../inc/common_cli.php";


echo "LANG: ".LANG."\n";
echo _("Proposal")."\n";
