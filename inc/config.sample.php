<?php

/**
 * Parser. Yes, it's what this is. It parses stuff. Pretty impressive, I know. It crawls pages from a wonderful website
 * of Slovenian Government, finds data about eeeeeeverything our MPs do.
 *
 * @package    Parlaparser
 * @author     Marko <marko@example.com>
 * @version    Release: 1.0
 */

//	Env settings
ini_set ('max_execution_time', 7200);
ini_set ('default_socket_timeout', 180);
date_default_timezone_set ("Europe/Ljubljana");
setlocale (LC_ALL, 'sl_SI.UTF8');

//  [SETTING] Logging
define ('LOG_PATH', 'log/trace.log');
define ('LOGGING', true);
ini_set('error_log', 'log/php.log');

//	Includes
include_once 'simple_html_dom.php';
include_once 'functions.php';

//	[SETTING] Database settings
define ('PG_HOST',	'127.0.0.1');
define ('PG_PORT',	5432);
define ('PG_USER',	'postgres');
define ('PG_PASS',	'postgres');
define ('PG_NAME',	'postgres');

//  [SETTING] Notification/admin mail address
define ('MAIL_NOTIFY',	''); // Who should be notified of any errors? name@email.com

//  [SETTING] Session without speech is only added after how many days?
define ('NOTIFY_NOSPEECH',	60);

//  [SETTING] Download attached documents
define ('DOC_DOWNLOAD',	false);

//  [SETTING] Downloads location ONLY IF ABOVE IS TRUE
define ('DOC_LOCATION',	''); // Where should the documents be stored? Absolute path pls. /home/user/documents

define ('LOCK_LOCATION', ''); // Where should the lockfile reside? (To lock the parser when actions are performed on the database)

//  [SETTING] Execute script after finish - script to execute using exec() function. Careful!
define ('ON_IMPORT_EXEC_SCRIPT', ''); // it uses sprintf() with $_global_oldest_date as second variable
define ('EXEC_SCRIPT_RUNNER', '');
$_global_oldest_date = null;

//  [SETTING] Skip session if any draft message is found
define ('SKIP_WHEN_REVIEWS', false);

//  [SETTING] Want to recrawl speaches for sessions that were in review last time?
define ('UPDATE_SESSIONS_IN_REVIEW', false);

//  [SETTING] Classifications for DTs
$dtclassifs = ['odbor','komisija','kolegij'];
define ('DT_CLASSIF', json_encode($dtclassifs));

//  [SETTINGS] Parser settings
define ('PARSE_SPEECHES', true);
define ('PARSE_SPEECHES_FORCE', false);
define ('PARSE_VOTES', true);
define ('PARSE_DOCS', true);

define('FORCE_UPDATE', true);


//  [SETTING] Optional delay between requests in seconds
define ('FETCH_TIMEOUT', 0);

//  Source URL
define ('DZ_URL',	'http://www.dz-rs.si');

//	[SETTING] Session setting
define ('CURRENT_SESSION', 'VII');

//  For benchmark purposes
//$benchmark = microtime(true);

//	Open connection to DB
$conn = pg_connect("host=".PG_HOST." port=".PG_PORT." dbname=".PG_NAME." user=".PG_USER." password=".PG_PASS);
if (!$conn) die ('Cannot connect to DB');

$http_response_header = null;

define('SMS_USER', '');
define('SMS_PASS', '');
define('SMS_FROM', "031583610");
$SMS_TO = array("031583610");

define('MAILGUN_KEY', '');
define('MAILGUN_DOMAIN', '');
define('MAILGUN_FROM', ''); //Excited User <YOU@YOUR_DOMAIN_NAME>
$MAILGUN_TO = array(''); //Who should be notified? name@email.com

$reportData = array();

define ('VAR_DUMP', true); // DEBUG?