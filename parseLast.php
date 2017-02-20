<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config_custom.php');

function importerUsage(){
    die("<<<USAGE
Usage:  php parseLast.php limit=2 organization_id=95 PARSE_SPEECHES=true PARSE_SPEECHES_FORCE=true PARSE_VOTES=true PARSE_VOTES_DOCS=true PARSE_DOCS=true > /dev/null &

php parseLast.php limit=2 organization_id=95 PARSE_SPEECHES=true PARSE_SPEECHES_FORCE=true PARSE_VOTES=true PARSE_VOTES_DOCS=true PARSE_DOCS=true > /log/parseLastLog_20170217
 
limit - numeber of last sessions
organization_id - if 0, no filter, 95 for DZ

PARSE_SPEECHES - default false
PARSE_SPEECHES_FORCE - default false
PARSE_VOTES - default false
PARSE_VOTES_DOCS - default false
PARSE_DOCS - default false
 
USAGE;");
}

if (count($argv) == 1) importerUsage();

$obligatoryFields = array('limit', 'organization_id', 'PARSE_SPEECHES', 'PARSE_SPEECHES_FORCE', 'PARSE_VOTES', 'PARSE_VOTES_DOCS', 'PARSE_DOCS');

$argvCount = 0;
$sessionCustomOptions = array();
foreach ($argv as $arg) {
    if (stripos($arg, "=") === false) {
        continue;
    }
    list($x, $y) = explode('=', $arg);

    if (!in_array($x, $obligatoryFields)) {
        importerUsage();
    }

    if (in_array($x, $obligatoryFields)) {
        ++$argvCount;
    }

    $sessionCustomOptions["$x"] = $y;

}
if(count($obligatoryFields) != $argvCount){
    importerUsage();
}


define('PARSE_SPEECHES', ($sessionCustomOptions['PARSE_SPEECHES'] == 'true') ? true : false);
define('PARSE_SPEECHES_FORCE', ($sessionCustomOptions['PARSE_SPEECHES_FORCE'] == 'true') ? true : false);
define('PARSE_VOTES', ($sessionCustomOptions['PARSE_VOTES'] == 'true') ? true : false);
define('PARSE_VOTES_DOCS', ($sessionCustomOptions['PARSE_VOTES_DOCS'] == 'true') ? true : false);
define('PARSE_DOCS', ($sessionCustomOptions['PARSE_DOCS'] == 'true') ? true : false);

define('SKIP_WHEN_REVIEWS', false);
define('UPDATE_SESSIONS_IN_REVIEW', true);

define('FORCE_UPDATE', true);




// Get people array
$people = getPeople();
$people_new = array();

$sessions = getSessionsOrderByDate($sessionCustomOptions['limit'], $sessionCustomOptions['organization_id']);
$sessions = array_reverse($sessions);

foreach ($sessions as $session) {

    if (isset($session['gov_id'])) {
var_dump("session Id " . $session["id"]);
        $content = file_get_contents('http://www.dz-rs.si' . htmlspecialchars_decode($session['gov_id']));
        parseSessionsSingleUpdate($content, $session['organization_id'], $session);

    }

}

    die();


    sendReport();
sendSms("DND done");


// Do things on end
parserShutdown();


