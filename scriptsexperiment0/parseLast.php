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



function parseSessionsSingleUpdate($content, $organization_id, $sessionData)
{
    $session = str_get_html($content);

    $session_name = $sessionData['name'];
    $session_link = $sessionData['gov_id'];
    $session_nouid = $sessionData['gov_id'];

    var_dumpp($sessionData);
    $date = new DateTime($sessionData['start_time']);
    $session_date = $date->format('Y-m-d');

    global $http_response_header;

    $tmp = array(
        'name' => trim($session_name),
        'link' => trim($session_link),
        'link_noid' => trim($session_nouid),
        'date' => trim($session_date),
        'review' => false,
        'review_ext' => false
    );

    if ($date > new DateTime('NOW')) {
        //no go
        return false;
    }

    if (sessionDeleted($session_nouid)) {
        return false;
    }

    $tmp['id'] = $sessionData['id']; // Set that session exists


    var_dumpp($tmp);

    //	Retrieve cookies
    $cookiess = '';
    if (isset($http_response_header)) {
        foreach ($http_response_header as $s) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$/', $s, $parts))
                $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
        }
    }
    $cookiess = substr($cookiess, 0, -2);

    // Parse data
    $tmp['speeches'] = array();
    $k = 0;
    if (PARSE_SPEECHES) {
        var_dump("PARSE_SPEECHES");
        if ($session->find('td.vaTop', 3)) {
            $sptable = $session->find('td.vaTop', 3)->find('a.outputLink');

            if (!empty ($sptable)) {
                $parseSpeeches = array();
                foreach ($sptable as $speeches) {
                    $in_review = (bool)(stripos($speeches->innerText(), "pregled") !== false);
                    if ($in_review) $tmp['review'] = true;

                    $datum = '';
                    if (preg_match('/(\d{2}\.\d{2}\.\d{4})/is', $speeches->innerText(), $matches)) {
                        $datum = DateTime::createFromFormat('d.m.Y', $matches[1])->format('Y-m-d');
                    }

                    //check here
                    $parseSpeeches[$k]['dateStart'] = $datum;
                    $parseSpeeches[$k]['url'] = DZ_URL . $speeches->href;
                    $parseSpeeches[$k]['in_review'] = $tmp['review'];
                    $parseSpeeches[$k]['sessionId'] = $tmp['id'];

                    $k++;
                }
                if (count($parseSpeeches) > 0) {
                    foreach ($parseSpeeches as $parseSpeeche) {
                        var_dumpp($parseSpeeche);
                        if (!$parseSpeeche['sessionId']) {

                            $speech = parseSpeeches($parseSpeeche['url'], $parseSpeeche['dateStart']);
                            $tmp['speeches'][$speech['datum']] = $speech;
                            $tmp['speeches'][$speech['datum']]['review'] = $parseSpeeche['in_review'];
                            $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speechinreview';
                            sleep(FETCH_TIMEOUT);

                        } else {


                            $speech = parseSpeeches($parseSpeeche['url'], $parseSpeeche['dateStart']);
                            $tmp['speeches'][$speech['datum']] = $speech;
                            $tmp['speeches'][$speech['datum']]['review'] = $parseSpeeche['in_review'];
                            sleep(FETCH_TIMEOUT);

                            if (isSpeechInReviewStatusChanged($parseSpeeche['sessionId'], $parseSpeeche['in_review'])) {
                                $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speech';
                            } else {
                                $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speechinreview';
                            }

                        }
                    }
                    //var_dumpp($tmp['speeches'][$speech['datum']]);
                }

            }
        }
    }

    $tmp['documents'] = array();
    if (PARSE_DOCS) {
        if ($session->find('td.vaTop', 3)) {
            $doctable = $session->find('td.vaTop', 2)->find('a');

            if (!empty ($doctable)) {
                foreach ($doctable as $doc) {

                    $tmp['documents'][] = parseDocument(DZ_URL . $doc->href);
                    sleep(FETCH_TIMEOUT);

                }
            }
        }
    }

    // Parse voting data
    $tmp['voting'] = array();
    if (PARSE_VOTES | PARSE_VOTES_DOCS) {
        var_dump("VOTES DOCS");

        preg_match('/form id="(.*?):form1"/', $session, $fmatches);
        $form_id = $fmatches[1];
        preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $session, $matches);
        preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $session, $matchess);
        preg_match('/Page 1 of (\d+)/i', $session, $matchesp);

        if (!empty($matchesp[1]) && (int)$matchesp[1] > 0) {


            for ($i = 1; $i <= (int)$matchesp[1]; $i++) {

                var_dumpp($i);

                //	Get next page
                $postdata = http_build_query(
                    array(
                        $form_id . ':form1' => $form_id . ':form1',
                        // $form_id . ':form1:menu1' => CURRENT_SESSION,
                        $form_id . ':form1:tableEx1:goto1__pagerGoButton.x' => 11,
                        $form_id . ':form1:tableEx1:goto1__pagerGoButton.y' => 11,
                        $form_id . ':form1:tableEx1:goto1__pagerGoText' => $i,
                        $form_id . ':form1_SUBMIT' => 1,
                        'javax.faces.ViewState' => $matchess[1],
                        // 'javax.faces.ViewState' => '/wps/PA_DZ-LN-Seje/portlet/SejeIzbranaSejaView.jsp',
                    )
                );
                $opts = array('http' =>
                    array(
                        'method' => 'POST',
                        'header' => 'Cookie: ' . $cookiess . "\r\n" . 'Content-type: application/x-www-form-urlencoded',
                        'content' => $postdata
                    )
                );
                $context = stream_context_create($opts);

                if ($subpage = file_get_contents(DZ_URL . $matches[1], false, $context)) {

                    $votearea = str_get_html($subpage)->find('table.dataTableExHov', 0);
                    if (!empty ($votearea)) {

                        foreach ($votearea->find('tbody tr') as $votesResults) {

                            $voteLinkExists = false;
                            $voteDate = false;
                            $parseVotes = null;

                            $votes = $votesResults->find('td a.outputLink');
                            foreach ($votes as $vote) {
                                if (preg_match('/\d{2}\.\d{2}\.\d{4}/is', $vote->text())) {

                                    $parseVotes = parseVotes(DZ_URL . $vote->href);
                                    $tmp['voting'][] = $parseVotes;

                                    sleep(FETCH_TIMEOUT);
                                    $voteLinkExists = true;
                                    $voteDate = trim($vote->text());
                                }
                            }

                            if(!PARSE_VOTES_DOCS){
                                $voteLinkExists = false;
                            }
                            $voteLinkExists = false;
                            if ($voteLinkExists) {

                                if (stripos($votes[3]->text(), "-") !== false) {
                                    $tmp['votingDocument'][] = parseVotesDocument(DZ_URL . $votes[3]->href, $voteDate,
                                        $tmp['id'], $organization_id, $parseVotes["dokument"], $parseVotes["naslov"]);

                                    sleep(FETCH_TIMEOUT);
                                }
                            }
                        }

                        $votDco = $tmp['votingDocument'];
                        file_put_contents("gitignore/doccache.txt", serialize($votDco));
                        var_dump($tmp['votingDocument']);
                    }
                }
            }

        }

    }

    if(!PARSE_VOTES){
        $tmp['voting'] = array();
    }
    if(!PARSE_VOTES_DOCS){
        $tmp['votingDocument'] = array();
    }

    file_put_contents("gitignore/tmp".date("Ymd_Hi").".txt", serialize($tmp));

    //	Add to DB
    saveSession($tmp, $organization_id, false);
    var_dumpp("SAVE:");
    var_dumpp($organization_id);


}




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


