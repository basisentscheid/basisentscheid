<?
/**
 * test mail signing, encryption and sending
 *
 * See cli/test_mail.php for testing on command line.
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if (PHP_SAPI != "cli") {
	require "inc/common_http.php";
	html_head("Test mail signing, encryption and sending");
}


// test permissions
if ( !is_dir(GNUPGHOME) ) error("The gnupg home directory '".GNUPGHOME."' does not exist.");
foreach ( array("pubring.gpg", "random_seed", "secring.gpg", "trustdb.gpg") as $file ) {
	if ( !is_file(GNUPGHOME."/".$file) ) error("The file $file does not exist.");
}
foreach ( glob(GNUPGHOME."/*") as $file ) {
	if ( !is_readable($file)  ) error("The file $file is not readable.");
	if ( !is_writeable($file) ) error("The file $file is not writeable.");
}


$to = ERROR_MAIL;
$subject = "Test mail signing, encryption and sending";
$headers = array();
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "Content-Transfer-Encoding: 8bit";
if (MAIL_FROM) $headers[] = "From: ".MAIL_FROM;
$body = "Test";

// public key for encryption
$keydata = <<<EOT
-----BEGIN PGP PUBLIC KEY BLOCK-----
Version: GnuPG v1.4.1 (GNU/Linux)

mQGiBDxLZngRBADdIKSQVAPoJwjqjfVqDGGnkaoMROIYsuQKsumQ7+Mcuuivfh3Z
zUmjDEzYLRYX9dz/hkPnAF438Zkk1UV783EkBXyaqHuGid3ka6Zq1npZjXWpWFEP
3nTu7EBFlt/70c6NeS5Y594FMol6dW7YugTuNjv7EBm1zp5/rEzFJuRPzwCgwuvt
9/dnVNrKUNiP/r9ocuBC8T0D/2Sf2/mjpCiDxa6kZfaL9BkLF8pM4HSsdDob851t
E8jMplxW6X+MDr5qgNtX4ugFn5Tv9cyEaVb080B4lTE/6FHvrv2Khkdz/VQOuVJt
H9dKbdRNTlwuK0ONxGFe0OwogNvB4UyLFo2SLS40FsP4OrkeyYduggKrO4GC8sir
1Y7HA/0RLrOoxgAAckcFocM+VEPkROqVIJgTh9J8TYzbt1Lw1HWbVihgJD2wOeRr
27AELnadQINgJbylRYRIEIRFbULdzCSWR/hPaWaSQ/fG794lffstkobTBPyYG00v
xXrZBw/J3Cw9xRHz7cBc7B/vO3Uv1IABFX+mmSQhAy2QfJ1PV7Q2TWFnbnVzIFJv
c2VuYmF1bSA8cm9zZW5iYXVAaW5mb3JtYXRpay51bmktbXVlbmNoZW4uZGU+iFcE
ExECABcFAjxLZngFCwcKAwQDFQMCAxYCAQIXgAAKCRDCaDaQDqF2QTosAJ9cYiVm
IcGWucfAfxIVhzhNgBCQ2ACdFfwYks3LO+iVKwiryf0fjDGGsF60J01hZ251cyBS
b3NlbmJhdW0gPGNtckBmb3Jlc3RmYWN0b3J5LmRlPoheBBMRAgAeBQJDnJLEAhsj
BgsJCAcDAgMVAgMDFgIBAh4BAheAAAoJEMJoNpAOoXZBfEQAnirp7e965LU+HcX7
laqRJMILLh17AJ985QR/s0oYoukK5xjjUCqobIaVFrkBDQQ8S2aEEAQA0wiSyPIs
GWPFWBMjjWohDdbLGu1lvedUn/pgleDkZIjpVJXO9F8M2kISTPv3h7a0lsSfmb3G
ov2SzN2tVxLoBTKDeNgURJ4n4KdpIPst+OGxsvpW9ybvFq/r9cgdWtER57LIlLJn
w91vGa7JohVhm4nI5e2TA0aj1KJyDzZvu9cAAwUD/iu5H25AuZh0dB70yXnYeFHl
mozuG2s8aJAd0eImAJkpeYgnt0FIlFejRxOy8LGyeWrDR7ilghU2OWCnDC1yU/uf
pG/IjKNXs2Ne/pEasVSO+Bz9wAFezxtH3HJ3Tc6/VEd8mZRTggc9lhg38C04HEEp
iS+IzAuPMrNLnTOQBI8NiEYEGBECAAYFAjxLZoQACgkQwmg2kA6hdkEn2ACcDOO8
2iJCxvSsZohhjDX1YiNExU0AniM2Vo5URPHNReGWMTG0glKqSH1H
=S/eT
-----END PGP PUBLIC KEY BLOCK-----
EOT;
$fingerprint = "DEBC3C99EF1D74F0D4C7EFF5C26836900EA17641";

$gnupg = new_gnupg();

$info = $gnupg->import($keydata);
//var_dump($info);

$info = $gnupg->keyinfo("");
//var_dump($info);

send_mail($to, $subject, $body, $headers, true, $fingerprint);


if (PHP_SAPI != "cli") {
	html_foot();
}
