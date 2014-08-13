<?
/**
 * configuration
 *
 * @see inc/common.php
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


define("DEBUG", false);

// with trailing slash
define("BASE_URL", "http://example.com/basisentscheid/portal/");

// for error notifications, see inc/errors.php
// Enter a mail address or a local user to get error mails.
define("ERROR_MAIL", "");
define("ERROR_MAIL_SUBJECT_PREFIX", "[Basisentscheid] ");
// Uncomment these lines to get backtrace files.
//define("ERROR_BACKTRACE_PATH", DOCROOT."var/errors/");
//define("ERROR_BACKTRACE_URL", BASE_URL."var/errors/");

define("DATABASE_CONNECT", "user=basisentscheid dbname=basisentscheid connect_timeout=5");

define("MINIMUM_POPULATION", 500);
define("SUPPORTERS_VALID_INTERVAL", '84 days'); // 12 weeks are 84 days
define("CANCEL_NOT_ADMITTED_INTERVAL", '6 months');
define("CLEAR_INTERVAL", '1 week');

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

define("OAUTH2_CLIENT_ID",     'example');
define("OAUTH2_CLIENT_SECRET", 'example');
define("OAUTH2_AUTHORIZATION_ENDPOINT", 'https://oauth2.example.com/authorize/');
define("OAUTH2_TOKEN_ENDPOINT",         'https://oauth2.example.com/token/');

define("SHARE_URL", "https://beoapi.example.com/shares/portal/");
define("CAINFO",  DOCROOT."ssl/cacerts.pem");
define("SSLCERT", DOCROOT."ssl/example.pem");
define("SSLKEY",  DOCROOT."ssl/example_priv.pem");
