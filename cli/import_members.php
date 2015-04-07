#!/usr/bin/php
<?
/**
 * Import members from CSV file
 *
 * Not yet existing members will be created, existing members will be updated.
 * Having also otherwise created members is possible for testing, but will lead to warnings.
 * Existing groups with case-insensitive matching names will be used, missing groups will be created.
 * Groups may only be renamed simultaneously on both sides if not only casing is changed!
 * If groups change their parent group, warnings will be shown until the parent assignment is updated in the database manually.
 *
 * Format of the CSV file:
 *   Line: <invite_code>;<eligible>;<verified>;<group_level1_name>;<group_level2_name>;<group_level3_name>...
 *   Last line: EOF;<number_of_lines_without_the_eof_line>
 * The invite code must consist of exactly 24 characters, preferably only out of [A-Za-z0-9].
 * Values for <eligible> and <verified> are 1 for true and 0 for false.
 * Groups have to be listed in hierarchical order from top to subgroups. Any number of groups is allowed, even no groups at all.
 * Fields may be enclosed in double quotes ("), but this is only required if a field contains the column delimiter (;).
 * No delimiter is allowed at the end of each line.
 * Charset: UTF-8
 * Line breaks: Unix (LF)
 *
 * Usage:
 * import_members.php <csv_file>
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
const DOCROOT = "../";
require "../inc/common_cli.php";


// number of members
$before = DB::fetchfield("SELECT COUNT(*) FROM member");

$inserted = 0;
$line = 0;
$eof = false;
$ngroup_map = array();

$handle = fopen($_SERVER['argv'][1], "r");
if (!$handle) exit(1);

while ( ($data = fgetcsv($handle, 256, ";")) !== false ) {

	// detect EOF-line
	if ($data[0]=="EOF") {
		$eof = true;
		// check number of lines
		$eof_lines = $data[1];
		if ($eof_lines != $line) {
			echo "WARNING: The number of lines in the CSV file ($line) is not equal to the number of lines stated in the last line ($eof_lines)!\n";
		}
		break;
	}

	$line++;

	// extract invite code
	$invite = $data[0];
	if (!$invite) {
		echo "WARNING: No invite code could be extracted from line $line!\n";
		continue;
	}

	// extract groups and convert convert them to ids
	$ngroups = [];
	$parent = null;
	for ( $index = 3; $index < count($data); $index++ ) {
		$name = trim($data[$index]);
		if (!$name) continue; // skip empty group names
		if (!isset($ngroup_map[$name])) {
			// get matching group
			$result = DB::query("SELECT * FROM ngroup WHERE name ILIKE ".DB::esc($name));
			if ( $ngroup = DB::fetch_object($result, "Ngroup") ) {
				if ( DB::num_rows($result) > 1 ) {
					echo "WARNING: More than one matching groups for '$name' were found!\n";
				}
				if ($ngroup->parent != $parent) {
					echo "WARNING: The parent of group '$name' is '$ngroup->parent' in the database, but '$parent' in line $line!\n";
				}
			} else {
				// create group that does not yet exist
				$ngroup = new Ngroup;
				$ngroup->name   = $name;
				$ngroup->parent = $parent;
				$ngroup->create(['name', 'parent']);
				echo "Created group '$name'\n";
			}
			$ngroup_map[$name] = $ngroup->id;
		}
		$ngroups[] = $ngroup_map[$name];
		$parent    = $ngroup_map[$name];
	}

	$sql = "SELECT * FROM member WHERE invite=".DB::esc($invite);
	DB::transaction_start();
	$result = DB::query($sql);
	if ( $member = DB::fetch_object($result, "Member") ) {
		$member->eligible = (bool) $data[1];
		$member->verified = (bool) $data[2];
		$member->update(['eligible', 'verified']);
	} else {
		$member = new Member;
		$member->invite = $invite;
		$member->eligible = (bool) $data[1];
		$member->verified = (bool) $data[2];
		$member->create(['invite', 'eligible', 'verified'], ['invite_expiry'=>"now() + ".DB::esc(INVITE_EXPIRY)]);
		++$inserted;
	}
	DB::transaction_commit();

	$member->update_ngroups($ngroups);

}

// check for EOF-line
if (!$eof) {
	echo "WARNING: No EOF-line was found at the end of the file!\n";
}

// number of members
$after = DB::fetchfield("SELECT COUNT(*) FROM member");

// additional warnings
if ($after != $line) {
	echo "WARNING: The number of members ($after) is not equal to the number of lines in the CSV file ($line)!\n";
}
if ($after != ($before + $inserted) ) {
	echo "WARNING: The number of members ($after) is not equal to the number before ($before) plus the inserted ($inserted) members!\n";
}

// report
echo "Members before:    $before\n";
echo "Inserted members:  $inserted\n";
echo "Lines in CSV file: $line\n";
echo "Members:           $after\n";
