#!/usr/bin/php
<?
/**
 * test import_members.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require "../inc/common_cli.php";


// first import
$data = <<<EOT
TESTieweedeeXailahzupa8v;1;1;Import group 1;Import group 2
TESTMeevuighohda4ungai0d;1;0;Import group 1
TESTed1cu5ohNg3majahY0qu;0;1;Import group 1;Import group 3
TESToThenedaiwiek0aiRi2A;0;0
TESTcilie3quiemi5vo9Iw4H;1;1;Import group 1;Import group 2
EOF;5
EOT;
file_put_contents("import_members_test.csv", $data);
system("./import_members.php import_members_test.csv");

// second import with updates
$data = <<<EOT
TESTieweedeeXailahzupa8v;0;0;Import group 1;Import group 2
TESTMeevuighohda4ungai0d;0;1;Import group 1;Import group 2
TESTed1cu5ohNg3majahY0qu;1;0;Import group 1
TESToThenedaiwiek0aiRi2A;0;0
TESTphoo0poh3feYoo3uwoox;1;1
TESThieroleJohgh8Die3oci;1;1;Import group 1;Import group 3
EOF;6
EOT;
file_put_contents("import_members_test.csv", $data);
system("./import_members.php import_members_test.csv");
