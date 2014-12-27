<?
/**
 * configuration
 *
 * @see inc/common_http.php
 * @see inc/common_cli.php
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


// Enter your database name, user and password here.
// See http://php.net/manual/en/function.pg-connect.php for details.
define("DATABASE_CONNECT", "user=basisentscheid dbname=basisentscheid connect_timeout=5");

// first part of html title
define("TITLE", "Basisentscheid");
// head line on the home page
define("HOME_H1", "Basisentscheid example heading");
// with trailing slash
define("BASE_URL", "http://example.com/basisentscheid/portal/");

// This email address will be shown in some error messages to contact the support.
define("MAIL_SUPPORT", "example@example.com");

// for all emails from the server
define("MAIL_FROM", "example@example.com");
define("MAIL_SUBJECT_PREFIX", "[Basisentscheid] ");

// for error handling, see inc/errors.php
// In live environment you should disable the display of errors by setting this to 0.
define("ERROR_DISPLAY", E_ALL);
// Enter a mail address or a local user to get error mails.
define("ERROR_MAIL", "");
define("ERROR_MAIL_SUBJECT_PREFIX", "[Basisentscheid] ");
// Uncomment this line to get backtrace files.
//define("ERROR_BACKTRACE_FILES", true);

// home for gnupg
define("GNUPGHOME", "/www/basisentscheid/var/gnupg");
// identifier of the PGP private key for signing (leave empty to disable signing and encryption)
define("GNUPG_SIGN_KEY", "");

// language, currently supported: "en" and "de"
// See inc/locale.php for a list of available languages and make sure the corresponding locale is installed in your system.
define("LANG", "en");

// date and time format, see http://php.net/manual/en/function.date.php
define("DATEYEAR_FORMAT",     "j.n.Y");
define("DATETIMEYEAR_FORMAT", "j.n.Y G:i");
define("DATE_FORMAT",         "j.n.");
define("DATETIME_FORMAT",     "j.n. G:i");
define("TIME_FORMAT",              "G:i");
define("VOTETIME_FORMAT",     "d.m.Y H:i:s");

// names of areas to create for new ngroups
define("DEFAULT_AREAS", "Politics, Organisation");
// number of proponents required for submission of a proposal
define("REQUIRED_PROPONENTS", 5);
// after this time supporters will not be counted for the quorum anymore
define("SUPPORTERS_VALID_INTERVAL", '12 weeks');
// proposals will be cancelled after this time after submission if they are still not admitted
define("CANCEL_NOT_ADMITTED_INTERVAL", '6 months');
// time between publishing the voting results and clearing raw data
define("CLEAR_INTERVAL", '1 week');
// time of day when ballots close (Opening time is individual for each ballot.)
define("BALLOT_CLOSE_TIME", "18:00");
// quorum for the first proposal
define("QUORUM_SUPPORT_NUM", 10); // 10%
define("QUORUM_SUPPORT_DEN", 100);
// quorum for the alternative proposals
define("QUORUM_SUPPORT_ALTERNATIVE_NUM", 5); // 5%
define("QUORUM_SUPPORT_ALTERNATIVE_DEN", 100);
// quorum for offline voting
define("QUORUM_VOTINGMODE_NUM", 5); // 5%
define("QUORUM_VOTINGMODE_DEN", 100);

// for how long after adding a comment the author may edit it
define("COMMENT_EDIT_INTERVAL", "1 hour");
// how many comments to show on each level
define("COMMENTS_HEAD_0", 8);
define("COMMENTS_FULL_0", 4);
define("COMMENTS_HEAD_1", 4);
define("COMMENTS_FULL_1", 2);
define("COMMENTS_HEAD_2", 2);

// activity value from which the activity icon should be displayed
define("ACTIVITY_THRESHOLD", 3);
// divisor for opacity, should be greater than the threshold
define("ACTIVITY_DIVISOR", 100);

// member status required for certain operations
// see Login::access_allowed() for possible values
define("ACCESS_COMMENT", 4); // write a comment or reply to one
define("ACCESS_RATE",    4); // rate comments

// message of the day (string, comment out to disable)
// will be displayed once for each session and always on the home page
//define("MOTD", "");

// --- Options for development and testing ---
// display system informations in the HTML code of every page and disable gettext caching
define("DEBUG", false);
// log in as any user with this password (string, set to false to disable)
define("MASTER_PASSWORD", false);
// send blind copies of all notifications to this email address
define("NOTIFICATION_BCC", "");
