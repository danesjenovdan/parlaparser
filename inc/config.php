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
ini_set ('max_execution_time', 3600);
date_default_timezone_set ("Europe/Ljubljana");
setlocale (LC_ALL, 'sl_SI.UTF8');

//  [SETTING] Logging
define ('LOG_PATH', 'log/trace.log');
define ('LOGGING', true);
ini_set('error_log', 'log/php.log');

//	Includes
include_once 'simple_html_dom.php';
include_once 'functions.php';

//  [SETTING] Download attached documents
define ('DOC_DOWNLOAD',	true);

//  [SETTING] Downloads location ONLY IF ABOVE IS TRUE
define ('DOC_LOCATION',	'/home/parladaddy/parlacdn/documents/');

//  [SETTING] Skip session if any draft message is found
define ('SKIP_WHEN_REVIEWS', false);

//  [SETTING] Classifications for DTs
$dtclassifs = ['odbor','komisija','kolegij'];
define ('DT_CLASSIF', json_encode($dtclassifs));

//  [SETTINGS] Parser settings
define ('PARSE_SPEECHES', true);
define ('PARSE_VOTES', true);
define ('PARSE_DOCS', true);

//	[SETTING] Database settings
define ('PG_HOST',	'***REMOVED***');
define ('PG_PORT',	5432);
define ('PG_USER',	'parladaddy');
define ('PG_PASS',	'***REMOVED***');
define ('PG_NAME',	'parladata');

//  Source URL
define ('DZ_URL',	'http://www.dz-rs.si');

//	[SETTING] Session setting
define ('CURRENT_SESSION', 'VII');

//	Open connection to DB
$conn = pg_connect("host=".PG_HOST." port=".PG_PORT." dbname=".PG_NAME." user=".PG_USER." password=".PG_PASS);
if (!$conn) die ('Cannot connect to DB');
