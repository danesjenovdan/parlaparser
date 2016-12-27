<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config_custom.php');


// php importer_seje_custom.php session_id=0 skip_when_reviews=true update_sessions_in_review=false parse_speeches=false parse_votes=false
if (count($argv) == 0) exit;


$obligatoryFields = array('session_id', 'skip_when_reviews', 'update_sessions_in_review', 'parse_speeches', 'parse_votes');


$sessionCustomOptions = array();
foreach ($argv as $arg) {
    if (stripos($arg, "=") === false) {
        continue;
    }
    list($x, $y) = explode('=', $arg);

    if (!in_array($x, $obligatoryFields)) {
        die("<<<USAGE
Usage:  php importer_seje_custom.php session_id=0 skip_when_reviews=true update_sessions_in_review=false parse_speeches=false parse_votes=false  > /dev/null &
 
session_id default = 0 .. if greater than 0, only this session ID will be parsed!
skip_when_reviews = true/false
update_sessions_in_review = true/false
parse_speeches = true/false
parse_votes = true/false
 
  
 
USAGE;");
    }

    $sessionCustomOptions["$x"] = $y;

}

var_dump($argv);
var_dump($sessionCustomOptions);

define('SKIP_WHEN_REVIEWS', filter_var($sessionCustomOptions['skip_when_reviews'], true));
define('UPDATE_SESSIONS_IN_REVIEW', filter_var($sessionCustomOptions['update_sessions_in_review'], true));
define('PARSE_SPEECHES', filter_var($sessionCustomOptions['parse_speeches'], true));
define('PARSE_VOTES', filter_var($sessionCustomOptions['parse_votes'], true));
define('PARSE_DOCS', false);


// Get people array
$people = getPeople();
$people_new = array();

//sendReport(date("Ym-m-d H:i:s"));


if ($sessionCustomOptions['session_id'] > 0) {

    $data = getSessionById($sessionCustomOptions['session_id']);
    if (isset($data['gov_id'])) {

        $content = file_get_contents('http://www.dz-rs.si' . htmlspecialchars_decode($data['gov_id']));

        parseSessionsSingle($content, $data['organization_id'], $data);

    }
    die();

} else {
    die('all');
    $urls = array(
        'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/redne',
        'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/izredne'
    );
    parseSessions($urls, 95);
// Delovna telesa
    $url_dt = 'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDt/poDt/izbranoDt?idDT=';
    parseSessionsDT($url_dt);

}

sendReport();
sendSms("DND done");


// Do things on end
parserShutdown();


