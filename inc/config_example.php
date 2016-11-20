<?
/**
 * configuration
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common_http.php
 * @see inc/common_cli.php
 */


// Enter your database name, user and password here.
// See http://php.net/manual/en/function.pg-connect.php for details.
const DATABASE_CONNECT = "user=basisentscheid dbname=basisentscheid connect_timeout=5";

// first part of html title
const TITLE = "Basisentscheid";
// head line on the home page
const HOME_H1 = "Basisentscheid example heading";
// with trailing slash
const BASE_URL = "http://example.com/basisentscheid/";
// session lifetime in seconds
const SESSION_LIFETIME = 2592000; // 30 days

// This email address will be shown in some error messages to contact the support.
const MAIL_SUPPORT = "example@example.com";

// for all emails from the server
const MAIL_FROM = "example@example.com";
const MAIL_SUBJECT_PREFIX = "[Basisentscheid] ";

// for error handling, see inc/errors.php
// In live environment you should disable the display of errors by setting this to 0.
const ERROR_DISPLAY = E_ALL;
// Enter a mail address or a local user to get error mails.
const ERROR_MAIL = "";
const ERROR_MAIL_SUBJECT_PREFIX = "[Basisentscheid] ";
// Uncomment this line to get backtrace files.
//const ERROR_BACKTRACE_FILES = true;

// home for gnupg
const GNUPGHOME = "/www/basisentscheid/var/gnupg";
// identifier of the PGP private key for signing (leave empty to disable signing and encryption)
const GNUPG_SIGN_KEY = "";

// language, currently supported: "en" and "de"
// See inc/locale.php for a list of available languages and make sure the corresponding locale is installed in your system.
const LANG = "en";

// date and time format, see http://php.net/manual/en/function.date.php
const DATEYEAR_FORMAT     = "j.n.Y";
const DATETIMEYEAR_FORMAT = "j.n.Y G:i";
const DATE_FORMAT         = "j.n.";
const DATETIME_FORMAT     = "j.n. G:i";
const TIME_FORMAT         =      "G:i";
const VOTETIME_FORMAT     = "d.m.Y H:i:s";

// names of areas to create for new ngroups
const DEFAULT_AREAS = "Politics, Organisation";
// number of proponents required for submission of a proposal
const REQUIRED_PROPONENTS = 5;
// after this time supporters will not be counted for the quorum anymore
const SUPPORTERS_VALID_INTERVAL = '12 weeks';
// proposals will be cancelled after this time after submission if they are still not admitted
const CANCEL_NOT_ADMITTED_INTERVAL = '6 months';
// time between publishing the voting results and clearing raw data
const CLEAR_INTERVAL = '1 week';
// time of day when ballots close (Opening time is individual for each ballot.)
const BALLOT_CLOSE_TIME = "18:00";
// quorum for the first proposal
const QUORUM_SUPPORT_NUM = 10; // 10%
const QUORUM_SUPPORT_DEN = 100;
// quorum for the alternative proposals
const QUORUM_SUPPORT_ALTERNATIVE_NUM = 5; // 5%
const QUORUM_SUPPORT_ALTERNATIVE_DEN = 100;
// quorum for offline voting
const QUORUM_VOTINGMODE_NUM = 5; // 5%
const QUORUM_VOTINGMODE_DEN = 100;

// for how long after adding a comment the author may edit it
const COMMENT_EDIT_INTERVAL = "1 hour";
// how many comments to show on each level
const COMMENTS_HEAD_0 = 8;
const COMMENTS_FULL_0 = 4;
const COMMENTS_HEAD_1 = 4;
const COMMENTS_FULL_1 = 2;
const COMMENTS_HEAD_2 = 2;

// activity value from which the activity icon should be displayed
const ACTIVITY_THRESHOLD = 3;
// divisor for opacity, should be greater than the threshold
const ACTIVITY_DIVISOR = 100;

// member status required for certain operations
// see Login::access_allowed() for possible values
const ACCESS_COMMENT = 4; // write a comment or reply to one
const ACCESS_RATE    = 4; // rate comments

// import members and groups instead of manual administration
const IMPORT_MEMBERS = false;
// period after which an invite code can't be used anymore
const INVITE_EXPIRY = "1 month";

// message of the day (string, comment out to disable)
// will be displayed once for each session and always on the home page
//const MOTD = "";

// --- Options for vvvote ---
// comma separated list of servers with trailing slash
const VVVOTE_SERVERS = "";
// configId to be sent to vvvote to identify configuration
const VVVOTE_CONFIG_ID = "";
// comma separated list of passwords to access vvvote_check_token.php
const VVVOTE_CHECK_TOKEN_PASSWORDS = "";
// time interval between voting start times
const VVVOTE_VOTING_START_INTERVAL = "1 day";
// time interval between last voting start time and voting end
const VVVOTE_LAST_VOTING_INTERVAL = "1 hour";

// --- Options for development and testing ---
// display system informations in the HTML code of every page and disable gettext caching
const DEBUG = false;
// log in as any user with this password (string, set to false to disable)
const MASTER_PASSWORD = false;
// send blind copies of all notifications to this email address
const NOTIFICATION_BCC = "";
