<?php

/**
 * Parlameter Parser
 * by Marko Bratkovič, 2015
 * This is NOT opensource, it's beer source - you take something you gimme a beer
 *
 */

//	Env settings
ini_set ('max_execution_time', 3600);
date_default_timezone_set ("Europe/Ljubljana");
setlocale (LC_ALL, 'sl_SI.UTF8');

define ('LOG_PATH', 'log/errors.txt');

//	Includes
include_once 'simple_html_dom.php';
include_once 'functions.php';

define ('DOC_DOWNLOAD',	false);
define ('DOC_LOCATION',	'doc/');
define ('SKIP_WHEN_REVIEWS',	false);

//	DB settings
define ('PG_HOST',	'localhost');
define ('PG_PORT',	5432);
define ('PG_USER',	'postgres');
define ('PG_PASS',	'postgres');
define ('PG_NAME',	'p5');

//define ('PG_HOST',	'192.168.1.8');
//define ('PG_PORT',	5432);
//define ('PG_USER',	'muki');
//define ('PG_PASS',	'');
//define ('PG_NAME',	'muki');


//	Session setting
define ('CURRENT_SESSION', 'VII');

//	Open connection to DB
$conn = pg_connect("host=".PG_HOST." port=".PG_PORT." dbname=".PG_NAME." user=".PG_USER." password=".PG_PASS);
if (!$conn) die ('Cannot connect to DB');
