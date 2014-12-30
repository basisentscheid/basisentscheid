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
<div class="help" id="manual">
<?
readfile("locale/manual_".LANG.".html");
?>
</div>
<script type="text/javascript">
// highlight anchor
if ( window.location.hash ) {
	var hash = window.location.hash.substring(1);
	document.getElementById(hash).className += " anchor";
}
// change highlighted anchor when jumping inside the page
var manual = document.getElementById("manual");
var links = manual.getElementsByTagName("a");
for (var i = 0; i < links.length; i++) {
	links[i].onclick = function () {
		document.getElementById(hash).className -= " anchor";
		hash = this.href.split("#")[1];
		document.getElementById(hash).className += " anchor";
	}
}
</script>
<?

html_foot();
