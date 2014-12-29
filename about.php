<?
/**
 * about
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

html_head(_("About"));

readfile("locale/about_".LANG.".html");

?>
<p class="version"><?=_("Version")?>: <span><?=version()?></span></p>
<?

html_foot();
