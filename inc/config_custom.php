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
ini_set ('memory_limit', '2048M');
date_default_timezone_set ("Europe/Ljubljana");
setlocale (LC_ALL, 'sl_SI.UTF8');
error_reporting(E_ALL);
//  [SETTING] Logging
define ('LOG_PATH', '/home/parladaddy/parlaparser/log/trace.log');
define ('FILTE_PATH', 'file/file.log');
define ('LOGGING', true);
ini_set('error_log', '/home/parladaddy/parlaparser/log/php.log');

//	Includes
include_once 'simple_html_dom.php';
include_once 'checkSpeeches.php';
include_once 'getFunctions.php';
include_once 'parseFunctions.php';
include_once 'parseVotesBase.php';
include_once 'saveFunctions.php';
include_once 'checkFunctions.php';
include_once 'functions.php';

//	[SETTING] Database settings
define ('PG_HOST',	'192.168.110.31');
define ('PG_PORT',	5432);
define ('PG_USER',	'parladaddy');
define ('PG_PASS',	'razvrat');
define ('PG_NAME',	'parladata-sl-2');

//  [SETTING] Notification/admin mail address
define ('MAIL_NOTIFY',	'filip@danesjenovdan.si');

//  [SETTING] Session without speech is only added after how many days?
define ('NOTIFY_NOSPEECH',	60);

//  [SETTING] Download attached documents
define ('DOC_DOWNLOAD',	false);

//  [SETTING] Downloads location ONLY IF ABOVE IS TRUE
define ('DOC_LOCATION',	'/home/parladaddy/parlacdn/v1/dokumenti/');

//  [SETTING] Execute script after finish - script to execute using exec() function. Careful!
define ('ON_IMPORT_EXEC_SCRIPT', ''); // it uses sprintf() with $_global_oldest_date as second variable
// da se ne pozene fast update // define ('EXEC_SCRIPT_RUNNER', '/home/parladaddy/parlalize/fast_runner.sh');
//define ('EXEC_SCRIPT_RUNNER', '/home/parladaddy/parlalize/test.sh');
$_global_oldest_date = null;

//  [SETTING] Classifications for DTs
$dtclassifs = ['odbor','komisija','kolegij'];
define ('DT_CLASSIF', json_encode($dtclassifs));

//  [SETTING] Optional delay between requests in seconds
define ('FETCH_TIMEOUT', 0);

//  Source URL
define ('DZ_URL',	'http://www.dz-rs.si');

//	[SETTING] Session setting
define ('CURRENT_SESSION', 'VIII');

//  For benchmark purposes
//$benchmark = microtime(true);

//	Open connection to DB
$conn = pg_connect("host=".PG_HOST." port=".PG_PORT." dbname=".PG_NAME." user=".PG_USER." password=".PG_PASS);
if (!$conn) die ('Cannot connect to DB');


$http_response_header = null;

define('SMS_USER', 'primoz_klemensek');
define('SMS_PASS', 'f85473526eff3d96622751178f57886bfc29db51ea');
define('SMS_FROM', "031583610");
$SMS_TO = array("031583610");

define('MAILGUN_KEY', 'key-0rr45z7eu1hy1icm645xz7fn5xegbcf0');
define('MAILGUN_DOMAIN', 'mg.v9.si');
define('MAILGUN_FROM', 'ParlaParser <klemensek@gmail.com>'); //Excited User <YOU@YOUR_DOMAIN_NAME>
//$MAILGUN_TO = array('klemensek@gmail.com', 'nmahnich@gmail.com', 'tadej.strok@gmail.com', 'cofek0@gmail.com');
$MAILGUN_TO = array('klemensek@gmail.com', 'cofek0@gmail.com');

$reportData = array();

define ('VAR_DUMP', false);
