<?php

/**
 * Parser. Yes, it's what this is. It parses stuff. Pretty impressive, I know. It crawls pages from a wonderful website
 * of Slovenian Government, finds data about eeeeeeverything our MPs do.
 *
 * @package    Parlaparser
 * @author     Marko Bratkovič <marko@example.com>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    Release: 1.0
 */

//	Env settings
ini_set ('max_execution_time', 3600);
date_default_timezone_set ("Europe/Ljubljana");
setlocale (LC_ALL, 'sl_SI.UTF8');

define ('LOG_PATH', 'log/errors.txt');

//	Includes
include_once 'simple_html_dom.php';
include_once 'functions.php';

//  [SETTING] Download attached documents
define ('DOC_DOWNLOAD',	false);

//  [SETTING] Downloads location ONLY IF ABOVE IS TRUE
define ('DOC_LOCATION',	'doc/');

//  [SETTING] Skip session if any draft message is found
define ('SKIP_WHEN_REVIEWS', false);

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
