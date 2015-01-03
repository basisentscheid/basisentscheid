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
<section class="help" id="manual">
<?
readfile("locale/manual_".LANG.".html");
?>
</section>
<script>
// highlight anchor
var hash;
if ( window.location.hash ) {
	hash = window.location.hash.substring(1);
	document.getElementById(hash).className += " anchor";
}
// change highlighted anchor when jumping within the page
var manual = document.getElementById("manual");
var links = manual.getElementsByTagName("a");
for (var i = 0; i < links.length; i++) {
	links[i].onclick = function () {
		if (hash) document.getElementById(hash).className -= " anchor";
		hash = this.href.split("#")[1];
		if (hash) document.getElementById(hash).className += " anchor";
	}
}
</script>
<?

html_foot();
