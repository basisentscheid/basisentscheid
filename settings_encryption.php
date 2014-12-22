<?
/**
 * encryption settings
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common_http.php";

Login::access("member");
if (!GNUPG_SIGN_KEY) error(_("Signing and encryption of emails is not enabled."));

if ($action) {
	switch ($action) {
	case "save":
		action_required_parameters('fingerprint', 'key');

		// save fingerprint
		$fingerprint = trim($_POST['fingerprint']);
		if ( $fingerprint != Login::$member->fingerprint ) {
			Login::$member->set_fingerprint($fingerprint);
			if ( Login::$member->update(['fingerprint']) ) {
				success(_("The PGP public key fingerprint has been saved."));
			}
		}

		// import PGP public key
		if ($_POST['key']) {
			$gnupg = new_gnupg();
			$import = $gnupg->import($_POST['key']);
			if (DEBUG) {
?>
<!--
<?=h(print_r($import, true))?>
-->
<?
			}
			if ($import['imported'] + $import['unchanged'] + $import['newuserids'] + $import['newsubkeys'] > 1) {
				notice(sprintf(_("Multiple keys were uploaded at once. %d keys have been imported and %d keys are unchanged."), $import['imported'], $import['unchanged']));
			} elseif ($import['imported'] or $import['newuserids'] or $import['newuserids'] or $import['newsubkeys']) {
				if ($import['fingerprint'] != Login::$member->fingerprint()) {
					notice(_("The key has been imported, but does not match the fingerprint."));
				} elseif ( !gnupg_keyinfo_matches_email( $gnupg->keyinfo($import['fingerprint']), Login::$member->mail ) ) {
					notice(_("The key has been imported, but does not match the email address."));
				} else {
					success(_("The key has been imported."));
				}
			} elseif ($import['unchanged']) {
				notice(_("The key has already been imported."));
			} else {
				warning(_("The key could not be imported."));
			}
		}

		redirect();

	default:
		warning(_("Unknown action"));
		redirect();
	}
}


html_head(_("Member settings"));

display_nav_settings();

form(BN);
?>
<fieldset class="member">
	<div class="input <?=stripes()?>">
		<label for="mail"><?=_("Email address for notifications")?></label>
		<span class="input"><?=_("confirmed")?>: <?=h(Login::$member->mail)?></span>
	</div>
	<div class="input <?=stripes()?>">
		<label><?=_("PGP Public Key Fingerprint")?></label>
		<span class="input"><input type="text" name="fingerprint" value="<?=h(Login::$member->fingerprint)?>" size="50" maxlength="<?=Member::fingerprint_length?>">
<?
Login::$member->display_fingerprint_info();
?>
		</span>
	</div>
	<div class="input <?=stripes()?>">
		<label><?=_("PGP Public Key import")?></label>
		<span class="input"><textarea name="key" cols="80" rows="15"></textarea></span>
	</div>
	<div class="button th">
		<input type="hidden" name="action" value="save">
		<input type="submit" value="<?=_("Save")?>">
	</div>
</fieldset>
<?
form_end();

html_foot();
