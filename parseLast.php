<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config_custom.php');

function importerUsage(){
    die("<<<USAGE
Usage:  php parseLast.php what=docs limit=2 organization_id=95 parse_speeches_force=true  > /dev/null &
 
what - speeches / docs / votes
limit - numeber of last sessions
organization_id - if 0, no filter, 95 for DZ

parse_speeches_force - default false
 
USAGE;");
}

if (count($argv) == 1) importerUsage();

$obligatoryFields = array('what', 'limit', 'organization_id', 'parse_speeches_force');

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


define('PARSE_SPEECHES', ($sessionCustomOptions['what'] == 'speeches') ? true : false);
define('PARSE_SPEECHES_FORCE', ($sessionCustomOptions['parse_speeches_force'] == 'true') ? true : false);
define('PARSE_VOTES', ($sessionCustomOptions['what'] == 'votes') ? true : false);
define('PARSE_DOCS', ($sessionCustomOptions['what'] == 'docs') ? true : false);

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
        parseSessionsSingle($content, $session['organization_id'], $session);

    }

}

    die();


    sendReport();
sendSms("DND done");


// Do things on end
parserShutdown();


