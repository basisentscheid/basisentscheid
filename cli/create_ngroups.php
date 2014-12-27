#!/usr/bin/php
<?
/**
 * create ngroups for testing
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require DOCROOT."inc/common_cli.php";

DB::query("INSERT INTO ngroup (id, parent, name, active) VALUES (1, null, 'Bund', true)");
DB::query("INSERT INTO ngroup (id, parent, name, active) VALUES (2, 1, 'Bayern', true)");
DB::query("INSERT INTO ngroup (id, parent, name, active) VALUES (3, 2, 'Oberbayern', false)");
DB::query("INSERT INTO ngroup (id, parent, name, active) VALUES (4, 2, 'Niederbayern', false)");
DB::query("INSERT INTO ngroup (id, parent, name, active) VALUES (5, 3, 'München', true)");
DB::query("INSERT INTO ngroup (id, parent, name, active) VALUES (6, 3, 'Gauting', false)");
