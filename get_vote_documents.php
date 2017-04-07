<?php


require 'vendor/autoload.php';
include_once('inc/config.php');

// Get people array
$people = getPeople();
$people_new = array();

/*
//8940
//8972
//9158
$session = getSessionById(8940);
$url = 'http://www.dz-rs.si' . htmlspecialchars_decode($session['gov_id']);
var_dump($url);
$content = file_get_contents($url);
parseSessionsSingleForDoc($content, $session['organization_id'], $session);
die();
*/

$all = (100/2);
$offset = 2;
$limit = 2;
for ($i=0; $i < $all; $i++) {
    $sessions = getAllSessionsByOrganizationId(95, $limit, ($i*$offset));
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


        if ($exists = sessionExists($session_nouid)) {
            $tmp['id'] = $exists['id']; // Set that session exists
            $tmp['review_ext'] = true;
        }
        var_dumpp($tmp);
        var_dumpp($exists);

        $cookiess = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $s) {
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$/', $s, $parts))
                    $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
            }
        }
        $cookiess = substr($cookiess, 0, -2);

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
                                $votes = $votesResults->find('td a.outputLink');

                                $datum = '';
                                if(!empty($votes[0])) {
                                    if (stripos($votes[0]->text(), ".") !== false) {
                                        $datum = $votes[0]->text();
                                    }
                                }
                                $ura = '';
                                if(!empty($votes[1])) {
                                    if (stripos($votes[1]->text(), ":") !== false) {
                                        $ura = $votes[1]->text();
                                    }
                                }
                                $kvorum = '';
                                if(!empty($votes[2])) {
                                    if (stripos($votes[2]->text(), "Kvorum") !== false) {
                                        $kvorum = $votes[2]->text();
                                    }
                                }
                                $epa = '';
                                $epaLink = '';
                                if(!empty($votes[3])) {
                                    if (stripos($votes[3]->text(), "-") !== false) {
                                        $epa = $votes[3]->text();
                                        $epaLink = $votes[3]->href;
                                    }
                                }
                                $dokument = '';
                                if(!empty($votes[4])) {
                                    if (stripos($votes[4]->text(), " ") !== false) {
                                        $dokument = $votes[4]->text();
                                    }
                                }

                                $voteLink = '';
                                foreach ($votes as $vote) {
                                    if (preg_match('/\d{2}\.\d{2}\.\d{4}/is', $vote->text())) {
                                        //$parseVotes = parseVotes(DZ_URL . $vote->href, $epa);
                                        $voteLink = $vote->href;
                                        $voteLinkExists = true;
                                    }
                                }

                                if($voteLinkExists) {

                                    $votesData = array();
                                    $votesData["session_id"] = $tmp['id'];
                                    $votesData["ura"] = $ura;
                                    $votesData["datum"] = $datum;
                                    $votesData["kvorum"] = $kvorum;
                                    $votesData["epa"] = $epa;
                                    $votesData["dokument"] = asciireplace($dokument);
                                    $votesData["vote_link"] = DZ_URL . $voteLink;
                                    $votesData["epa_link"] = DZ_URL . $epaLink;
                                    $votesData["inserted"] = date("YmdHi");
                                    $link_id = insertTmpVotesLinkForDocuments($votesData);

                                    var_dump($link_id);

                                }



                            }

                        }
                    }

                }
            }


        //	Add to DB
        var_dumpp("SAVE:");
        var_dumpp($organization_id);
    }

}

function insertTmpVotesLinkForDocuments($data){
    global $conn;



    $session_id = $data["session_id"];
    $ura = $data["ura"];
    $datum = $data["datum"];
    $kvorum = $data["kvorum"];
    $epa = $data["epa"];
    $dokument = $data["dokument"];
    $voteLink = $data["vote_link"];
    $epaLink = $data["epa_link"];
    $inserted = $data["inserted"];

    $sql = "
    INSERT INTO parladata_tmpvoteslinkdocuments (session_id, ura, datum, kvorum, epa, dokument, vote_link, epa_link, inserted) VALUES 
    ( " . pg_escape_string($conn, $session_id) . ", '" . pg_escape_string($conn, $ura) . "', '" . pg_escape_string($conn, $datum) . "', 
    '" . pg_escape_string($conn, $kvorum) . "', '" . pg_escape_string($conn, $epa) . "', '" . pg_escape_string($conn, $dokument) . "', 
    '" . pg_escape_string($conn, $voteLink) . "', '" . pg_escape_string($conn, $epaLink) . "', '" . pg_escape_string($conn, $inserted) . "')
					RETURNING id
				";
    $result = pg_query($conn, $sql);

    $link_id = 0;
    if (pg_affected_rows($result) > 0) {
        $insert_row = pg_fetch_row($result);
        $link_id = $insert_row[0];

    }

    return $link_id;
}