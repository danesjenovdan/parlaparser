<?php


require 'vendor/autoload.php';
include_once('inc/config.php');

$all = (1200/5);
$offset = 5;
for ($i=0; $i < $all; $i++) {
    $sessions = getAllSessions(5, ($i*$offset));
    if (count($sessions) > 0) {
        foreach ($sessions as $session) {

            if (isset($session['gov_id'])) {
                $content = file_get_contents('http://www.dz-rs.si' . htmlspecialchars_decode($session['gov_id']));
                parseSessionsSingleForDoc($content, $session['organization_id'], $session);
            }
        }
    }
}

function parseSessionsSingleForDoc($content, $organization_id, $sessionData)
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

    if ($date < new DateTime('NOW')) {

        if(sessionDeleted($session_nouid)){
            return false;
        }
        // Check if session already imported
        if ($exists = sessionExists($session_nouid)) {
//            if ($exists['in_review'] == 'f' || ($exists['in_review'] == 't' && !UPDATE_SESSIONS_IN_REVIEW)) {
//                return false;
//            }
            $tmp['id'] = $exists['id']; // Set that session exists
            $tmp['review_ext'] = true;
        }
        var_dumpp($tmp);
        var_dumpp($exists);



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


        $tmp['documents'] = array();

        // Parse voting data
        $tmp['voting'] = array();
        //if (PARSE_VOTES) {
        if (true) {
            var_dump("VOTES");
            //  Search on DT page or not TODO: better solution needed
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



                                if($voteLinkExists) {

                                    if (stripos($votes[3]->text(), "-") !== false) {
                                        if(voteLinkExists($tmp['id'], $tmp['link'], $votes[3]->href)){
                                            continue;
                                        }

                                        $tmp['votingDocument'][] = parseVotesDocument(DZ_URL . $votes[3]->href, $voteDate,
                                            $tmp['id'], $organization_id, $parseVotes["dokument"],  $parseVotes["naslov"] );
                                        //var_dump($tmp['votingDocument']);
                                        //die();

                                        sleep(FETCH_TIMEOUT);
                                    }
                                    $voteLinkInsertId = voteLinkInsert($tmp['id'], $tmp['link'], $votes[3]->href);
                                    var_dump($voteLinkInsertId);
                                }

                            }
                            $votDco = $tmp['votingDocument'];
                            file_put_contents("gitignore/doccache_".$tmp['id'].".txt", serialize($votDco));
                            var_dump($tmp['votingDocument']);
                        }
                    }
                }
                //saveVotes($tmp, $organization_id);
            }

        }
        //	Add to DB
        saveSession($tmp, $organization_id);
        var_dumpp("SAVE:");
        var_dumpp($organization_id);
    }

}