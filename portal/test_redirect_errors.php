<?
/**
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

trigger_error("Test warning before action", E_USER_WARNING);
//trigger_error("Test error before action", E_USER_ERROR);

if (@$_POST['action']=="test") {

	success("Success");
	warning("Warning");
	notice("Notice");
	error("Error");

	trigger_error("Test warning before message", E_USER_WARNING);

	trigger_error("Test warning after message", E_USER_WARNING);
	//trigger_error("Test error after message", E_USER_ERROR);
	redirect();

}



html_head(_("Test"));

trigger_error("Test warning after html_head", E_USER_WARNING);
//trigger_error("Test error after html_head", E_USER_ERROR);

?>
<form action="<?=BN?>" method="post">
<input type="hidden" name="action" value="test">
<input type="submit">
</form>
<?


html_foot();
