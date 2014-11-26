<?
/**
 * configuration
 *
 * @see inc/common.php
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


// --- Options for development and testing ---
// display system informations in the HTML code of every page and disable gettext caching
define("DEBUG", false);
// log in as any user with this password (string, set to false to disable)
define("MASTER_PASSWORD", false);

// with trailing slash
define("BASE_URL", "http://example.com/basisentscheid/portal/");

// for error notifications, see inc/errors.php
// Enter a mail address or a local user to get error mails.
define("ERROR_MAIL", "");
define("ERROR_MAIL_SUBJECT_PREFIX", "[Basisentscheid] ");
// Uncomment these lines to get backtrace files.
//define("ERROR_BACKTRACE_PATH", DOCROOT."var/errors/");
//define("ERROR_BACKTRACE_URL", BASE_URL."var/errors/");

define("MAIL_FROM", "example@example.com");
define("MAIL_SUBJECT_PREFIX", "[Basisentscheid] ");

define("MAIL_SUPPORT", "example@example.com");

// home for gnupg
define("GNUPGHOME", "/www/basisentscheid/var/gnupg");
// identifier of the PGP private key for signing (leave empty to disable signing and encryption)
define("GNUPG_SIGN_KEY", "");

define("DATABASE_CONNECT", "user=basisentscheid dbname=basisentscheid connect_timeout=5");

// names of areas to create for new ngroups
define("DEFAULT_AREAS", "Politics, Organisation");
// number of proponents required for submission of a proposal
define("REQUIRED_PROPONENTS", 5);
// after this time supporters will not be counted for quorum anymore
define("SUPPORTERS_VALID_INTERVAL", '84 days'); // 12 weeks are 84 days
// proposals will be cancelled after this time after submission if they are still not admitted
define("CANCEL_NOT_ADMITTED_INTERVAL", '6 months');
// time between publishing the voting results and clearing raw data
define("CLEAR_INTERVAL", '1 week');
// time of day when ballots close (Opening time is individual for each ballot.)
define("BALLOT_CLOSE_TIME", "18:00");
// number used for quorum calculation if there are less than this area participants
define("MINIMUM_POPULATION", 500);
// quorum for the first proposal
define("QUORUM_SUPPORT_NUM", 1); // 10%
define("QUORUM_SUPPORT_DEN", 10);
// quorum for the alternative proposals
define("QUORUM_SUPPORT_ALTERNATIVE_NUM", 1); // 5%
define("QUORUM_SUPPORT_ALTERNATIVE_DEN", 20);
// quorum for ballot voting
define("QUORUM_BALLOT_VOTING_NUM", 1); // 5%
define("QUORUM_BALLOT_VOTING_DEN", 20);

// language, currently supported: "en" and "de"
define("LANG", "en");

// date and time format, see http://php.net/manual/en/function.date.php
define("DATEYEAR_FORMAT",     "j.n.Y");
define("DATETIMEYEAR_FORMAT", "j.n.Y G:i");
define("DATE_FORMAT",         "j.n.");
define("DATETIME_FORMAT",     "j.n. G:i");
define("TIME_FORMAT",              "G:i");

define("ARGUMENT_EDIT_INTERVAL", "1 hour");
// how many arguments to show on each level
define("ARGUMENTS_LIMIT_0", 5);
define("ARGUMENTS_LIMIT_1", 2);
define("ARGUMENTS_LIMIT_2", 1);
define("ARGUMENTS_LIMIT_3", 1);
