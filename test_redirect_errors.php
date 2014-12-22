<?
/**
 * test redirection and error reporting
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

trigger_error("Test notice before action", E_USER_NOTICE);
trigger_error("Test warning before action", E_USER_WARNING);
if (@$_POST['fatal_php_error_before_action']) trigger_error("Test error before action", E_USER_ERROR);

if ($action=="test") {

	success("Success");
	warning("Warning");
	notice("Notice");
	if (@$_POST['fatal_error_msg']) error("Error");

	trigger_error("Test notice after message", E_USER_NOTICE);
	trigger_error("Test warning after message", E_USER_WARNING);
	if (@$_POST['fatal_php_error_after_msg']) trigger_error("Test error after message", E_USER_ERROR);

	redirect();

}

html_head(_("Test"));

trigger_error("Test notice after html_head", E_USER_NOTICE);
trigger_error("Test warning after html_head", E_USER_WARNING);
// trigger_error("Test error after html_head", E_USER_ERROR);

form(BN);
?>
<input type="checkbox" name="fatal_php_error_before_action" value="1"> Fatal PHP error before action<br>
<input type="checkbox" name="fatal_error_msg" value="1"> Fatal error message<br>
<input type="checkbox" name="fatal_php_error_after_msg" value="1"> Fatal PHP error after message<br>
<input type="hidden" name="action" value="test">
<input type="submit">
<?
form_end();

html_foot();
