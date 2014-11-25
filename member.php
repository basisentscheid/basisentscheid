<?
/**
 * member.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

Login::access("member");

if (empty($_GET['id'])) error(_("Missing parameter"));

$member = new Member(intval($_GET['id']));

if (!$member) error(_("The requested member does not exist."));


html_head(sprintf(_("Member %s"), $member->username()));

?>
<p><?=content2html($member->profile)?></p>
<?

html_foot();
