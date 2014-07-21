<?
/**
 * configuration
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 * @see inc/common.php
 */


define("DEBUG", true);

define("DATABASE_CONNECT", "user=basisentscheid dbname=basisentscheid connect_timeout=5");

// with trailing slash
define("BASE_URL", "http://example.com/basisentscheid/portal/");

// for error notifications
define("ADMINMAIL", "root");

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
// quorum for secret voting
define("QUORUM_SECRET_NUM", 1); // 5%
define("QUORUM_SECRET_DEN", 20);

//define("TIME_FORMAT", "j.n.Y G:i:s T"); // with timezone
define("TIME_FORMAT", "j.n.Y G:i"); // without timezone
define("DATE_FORMAT", "j.n.Y");

define("OAUTH2_CLIENT_ID",     'example');
define("OAUTH2_CLIENT_SECRET", 'example');
define("OAUTH2_AUTHORIZATION_ENDPOINT", 'https://oauth2.example.com/authorize/');
define("OAUTH2_TOKEN_ENDPOINT",         'https://oauth2.example.com/token/');
