<?
/**
 * manual
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

html_head(_("Manual"));

?>
<div class="help">
<?
readfile("locale/manual_".LANG.".html");
?>
</div>
<?

html_foot();
