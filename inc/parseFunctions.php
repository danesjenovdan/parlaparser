<?php
/**
 * Parse single session page
 *
 * @param string $content Content of grabbed page
 * @param int $organization_id Organization ID
 */
function parseSessionsList($content, $organization_id)
{

    $data = str_get_html($content);

    //	Check for single sessions
    $islist = $data->find('table.dataTableExHov', 0);
    if (!empty ($islist)) {
        $content = $data->find('table.dataTableExHov', 0)->find('tbody a.outputLink');
    } else {
        return false;
    }

    global $http_response_header;
    foreach ($content as $link) {

        $session_name = $link->text();
        var_dumpp($session_name);
        $session_link = $link->href;
        $session_nouid = preg_replace('/\&uid.*$/is', '', $session_link);

        // Date
        if ($organization_id != 95) {
            $date = trim($link->parent()->next_sibling()->next_sibling()->next_sibling()->text());
        } else {
            $date = trim($link->parent()->next_sibling()->next_sibling()->text());
        }
        $date = str_replace('(', '', $date);
        $date = str_replace(')', '', $date);
        $date = DateTime::createFromFormat('d.m.Y', $date);
        $session_date = $date->format('Y-m-d');

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
                continue;
            }
            // Check if session already imported
            if ($exists = sessionExists($session_nouid)) {
                if ($exists['in_review'] == 'f' || ($exists['in_review'] == 't' && !UPDATE_SESSIONS_IN_REVIEW)) continue;
                $tmp['id'] = $exists['id']; // Set that session exists
                $tmp['review_ext'] = true;
            }
            var_dumpp($tmp);
            var_dumpp($exists);

            // Log
            logger('FETCH SESSION: ' . DZ_URL . $session_link);

            // Get session
            $session_link = str_replace('&amp;', '&', $session_link); // Some weird shit changed recently on DZRS server
            $session = str_get_html(downloadPage(DZ_URL . $session_link));

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
                if ($session->find('td.vaTop', 3)) {
                    $sptable = $session->find('td.vaTop', 3)->find('a.outputLink');

                    if (!empty ($sptable)) {
                        $parseSpeeches = array();
                        foreach ($sptable as $speeches) {
                            $in_review = (bool)(stripos($speeches->innerText(), "pregled") !== false);
                            if ($in_review && SKIP_WHEN_REVIEWS) continue 2;

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

                                    if(isSpeechInReviewStatusChanged($parseSpeeche['sessionId'], $parseSpeeche['in_review'])){
                                        $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speech';
                                    }else{
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
                            if (stripos($doc->innerText(), "pregled") === false) {
                                $tmp['documents'][] = parseDocument(DZ_URL . $doc->href);
                                sleep(FETCH_TIMEOUT);

                                if(empty($tmp['id'])){
                                    var_dump($tmp['link_noid']);
                                    //continue 2;
                                }
                                var_dump("documetn parse" . $tmp['id']);


                            } else {
                                if (SKIP_WHEN_REVIEWS) continue 2;
                            }
                        }
                    }
                }
            }

            // Parse voting data
            $tmp['voting'] = array();
            if (PARSE_VOTES) {
                var_dump("VOTES");
                //  Search on DT page or not TODO: better solution needed
                preg_match('/form id="(.*?):form1"/', $session, $fmatches);
                $form_id = $fmatches[1];

                //	Retreive pager form action
                preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $session, $matches);

                //	Retreive some ViewState I have no fucking clue what it is for, but it must be present in POST
                preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $session, $matchess);

                //	Retreive number of pages
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
                                            $tmp['votingDocument'][] = parseVotesDocument(DZ_URL . $votes[3]->href, $voteDate,
                                                $tmp['id'], $organization_id, $parseVotes["dokument"],  $parseVotes["naslov"] );


                                            //var_dump($tmp['votingDocument']);                                        die();


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

                    //saveVotes($tmp, $organization_id);

                }

            }

            //	Test: Izpis podatkov celotne seje
            //print_r ($tmp);
            //exit();


            //	Add to DB
            saveSession($tmp, $organization_id);
            var_dumpp("SAVE:");
            var_dumpp($organization_id);
        }
    }
}

/**
 * Parse DT pages
 *
 * @param string $url DT sessions URL root
 */
function parseSessionsDT($url)
{
    $dts = getDTs();

    foreach ($dts as $gov_key => $gov_id) {
        var_dumpp($gov_id);
        parseSessions(array(
            $url . $gov_id
        ), $gov_key);
    }
}

/**
 * Parses sessions, fetching speeches, votes, documents
 *
 * @param array $urls URLs of sessions lists
 * @param int $organization_id Organization ID
 */
function parseSessions($urls, $organization_id)
{
    global $http_response_header;
    foreach ($urls as $url) {

        // Log
        logger('SESSIONS: ' . $url);

        //	Get main page
        $base = downloadPage($url);

        //	Retrieve cookies
        $cookiess = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $s) {
                if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts))
                    $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
            }
        }
        $cookiess = substr($cookiess, 0, -2);


        //	Parse main page
//		parseSessionsList ($base, $organization_id);

        //  Search on DT page or not TODO: better solution needed
        preg_match('/form id="(.*?):sf:form1"/', $base, $fmatches);
        $form_id = $fmatches[1];

        //	Retreive pager form action
        preg_match('/form id="' . $form_id . ':sf:form1".*?action="(.*?)"/', $base, $matches);

        //	Retreive some ViewState I have no fucking clue what it is for, but it must be present in POST
        preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $base, $matchess);

        //	Retreive number of pages
        preg_match('/Page 1 of (\d+)/i', $base, $matchesp);

        if (!empty($matchesp[1]) && (int)$matchesp[1] > 0) {

            for ($i = 1; $i <= (int)$matchesp[1]; $i++) {

                //	Get next page
                $postdata = http_build_query(
                    array(
                        $form_id . ':sf:form1' => $form_id . ':sf:form1',
                        $form_id . ':sf:form1:menu1' => CURRENT_SESSION,
                        $form_id . ':sf:form1:tableEx1:goto1__pagerGoButton.x' => 1,
                        $form_id . ':sf:form1:tableEx1:goto1__pagerGoText' => $i,
                        $form_id . ':sf:form1_SUBMIT' => 1,
                        'javax.faces.ViewState' => $matchess[1]
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

                    //	Parse sub page
                    parseSessionsList($subpage, $organization_id);
                }
            }

        }
    }
}

/**
 * Find speeches on URL
 *
 * @param string $url URL to fetch
 * @return array Array of speeches
 */
function parseSpeeches($url, $datum)
{
    global $benchmark;

    $data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));

    // Log
    logger('FETCH SPEECH: ' . $url);

    // Info
    $array = [
        'naziv' => ($dtit = $data->find('.wpthemeOverflowAuto table tr', 0)) ? html_entity_decode($dtit->find('td', 1)->text()) : 'Ni naziva',
        'datum' => $datum,    // $data->find('.wpthemeOverflowAuto table tr', 2)->find('td', 1)->text()
        'talks' => []
    ];

    // Data
    $content = $data->find('.fieldset', 0)->find('span.outputText', 0);
    $content = html_entity_decode($content->innertext);
    $content = strip_tags($content, '<br><b>');

    $content = preg_replace('/<br>/', "\n", $content);

    // Umik "TRAK ..."
    $content = preg_replace('/[\n\r](<b>)?(\d+\. TR.*?)(<\/b>)?[\n\r]/s', '', $content);

    // Umik "(nadaljevanje")
    $content = preg_replace('/([\n\r]+\t\(nadaljevanje\)? )/is', ' ', $content);

    // Umik prekinitev sej
    $content = preg_replace('/(\t(<b>)?\(Seja.*?ob [\d\.]{5,6}\)(<\/b>)?[\n\r]+)/si', '', $content);

    // Umik ure konca
    $content = preg_replace('/([\n\r](<b>)?\(?Seja je bila končana \d+\..*?ob.*\)?(<\/b>)?)/si', '', $content);
    $content = preg_replace('/([\n\r](<b>)?\(?Seja se je končala \d+\..*?ob.*\)?(<\/b>)?)/si', '', $content);
    $content = preg_replace('/([\n\r](<b>)?\(?Seja je bila prekinjena \d+\..*?ob.*\)?(<\/b>)?)/si', '', $content);

    // Umik dvojnih presledkov
    $content = str_replace('  ', ' ', trim($content));

    $content = preg_replace('/(<b>[ \t]*<\/b>)/s', ' ', trim($content));
    $content = preg_replace('/(<\/b>[ \t]*<b>)/s', ' ', trim($content));

    // Umik dvojnih breakov
    $content = preg_replace('/([\n\r]+)/', "\n", trim($content));

    // Split govorov na posamezne dele
    $parts = preg_split('/[\n\r][\t ]?<b>[\t ]*([<\/b>\tA-ZČŠŽĐÖĆÜ,\.\(\) ]{4,40}?(\([\w ]*?\))??)[: <\/b>]{2,6}/s', $content, null, PREG_SPLIT_DELIM_CAPTURE); // old: '/[\n\r]<b>([A-ZČŠŽĐÖĆÜ,\.]{2,}[A-ZČŠŽĐÖĆÜ,\. \(\)]{3,}(\(.*?\))??)[: <\/b>]{2,6}/s'

    if (!empty ($parts)) {
        if (strpos(strip_tags($parts[0]), 'REPUBLIKA SLOVENIJA') === 0) {
            unset ($parts[0]);
        }
        $cnt = key($parts);
        $end = sizeof($parts);

        for ($i = $cnt; $i <= $end; $i++) {

            //	Iskanje osebe/imena
            if (isset ($parts[$i]) && preg_match('/^[A-ZČŠŽĐÖĆÜ,\.]{2,}[A-ZČŠŽĐÖĆÜ,\. \(\)]{6,40}(\(.*?\))?/s', $parts[$i])) {

                $name = trim($parts[$i]);
                if (strpos($name, '(') > 0) $name = substr($name, 0, strrpos($name, '('));

                //  Remove prefixes, some common typos
                $replaces = array(
                    'podpredsednik',
                    'predsednik',
                    'podpredsednica',
                    'predsednica',
                    'predsedujoči',
                    'predsedujoča',
                    'predsedujoč',
                    'prof.',
                    'dr.',
                    'mag.',

                    //  Tyops
                    'podpredsendica',
                    'podpredsedica',
                    'podpredsedik',
                    'predsednk',
                    'predsedik',
                    'predsenik'
                );
                $name = str_ireplace($replaces, '', $name);

                $name = preg_replace('/^(\.) ?/s', '', trim($name));
                $name = str_replace('  ', ' ', trim($name));

                //	Remove some more trash
                if (preg_match('/REPUBLI|ODBOR|DRŽAVN|KOMISIJ|JAVNO|DRUŠTV|SINDIKA/s', $name)) continue;

                $tmp = array(
                    'id' => getPersonIdByName($name),
                    'ime' => $name
                );

                // Preveri, če je pred tekstom še ime stranke v ()
                if (isset ($parts[$i + 1]) && preg_match('/\(.*?\)$/s', trim($parts[$i + 1]))) {
                    $tmp['stranka'] = substr(trim($parts[$i + 1]), strrpos(trim($parts[$i + 1]), '(') + 1, -1);
                    $i++;

                    if (isset ($parts[$i + 1])) {
                        $tmp['vsebina'] = preg_replace('/\t+/is', '', trim($parts[$i + 1]));
                        $tmp['vsebina'] = strip_tags($tmp['vsebina']);
                        $i++;
                    }

                } elseif (isset ($parts[$i + 1])) {
                    $tmp['vsebina'] = preg_replace('/\t+/is', '', trim($parts[$i + 1]));
                    $tmp['vsebina'] = strip_tags($tmp['vsebina']);
                    $i++;
                }

                $array['talks'][] = $tmp;
            }
        }
    }
    return $array;
}

/**
 * Find votes on URL
 *
 * @param string $url URL to fetch
 * @return array Array of votes
 */
function parseVotes($url)
{
    $array = array();

    $data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));

    // Log
    logger('FETCH VOTES: ' . $url);

    $info = $data->find('.panelGrid', 0)->find('tr');
    foreach ($info as $row) {
        foreach ($row->find('td') as $td) {
            $tdinfo = trim($td->text());

            if (strtolower($tdinfo) == 'naslov:') {
                $array['naslov'] = html_entity_decode(trim($td->next_sibling()->text()));
            }
            if (strtolower($tdinfo) == 'dokument:') {
                $array['dokument'] = html_entity_decode(trim($td->next_sibling()->text()));
            }
            if (strtolower($tdinfo) == 'glasovanje dne:' && preg_match('/([0-9\.]{10}).*?([0-9\:]{8})/is', $td->next_sibling()->text(), $tmp)) {
                $array['date'] = DateTime::createFromFormat('d.m.Y', $tmp[1])->format('Y-m-d');
                $array['time'] = $tmp[2];
            }
            if (strtolower($tdinfo) == 'epa:') {
                $array['epa'] = $td->next_sibling()->text();
                $array['epa_link'] = $td->next_sibling()->find('a', 0)->href;
            }
            if (strtolower($tdinfo) == 'faza postopka:') {
                $array['faza'] = $td->next_sibling()->text();
            }
            if (strtolower($tdinfo) == 'za:') {
                $array['za'] = $td->next_sibling()->text();
            }
            if (strtolower($tdinfo) == 'proti:') {
                $array['proti'] = $td->next_sibling()->text();
            }
            if (strtolower($tdinfo) == 'kvorum:') {
                $array['kvorum'] = $td->next_sibling()->text();
            }
        }
    }

    $content = $data->find('.fieldset', 0)->find('span.outputText', 0);

    $cnt = 0;
    $array['votes'] = array();
    foreach ($content->find('tr') as $t) {
        if ($cnt > 0) {
            if (preg_match('/<td.*?>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td/', html_entity_decode($t->innertext), $matches)) {
                unset ($matches[0]);
                $matches[4] = getPersonIdByName(trim($matches['1']), true);
                //  Test
                //$matches[5] = $people[$matches[4]]['name'];
                $array['votes'][] = $matches;
            }
        }
        $cnt++;
    }

    return $array;
}


function parseVotesDocument($url, $voteDate, $sessionId, $organizationId, $naslov, $dokument)
{
    $data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));
    // Log
    logger('FETCH VOTES: ' . $url);

    $documentsData = array();

    if($data->find('.listNoBtn')){
        $documentsData = parseVotesDocumentList($data);
    }else{
        $documentsData[] = parseVotesDocumentSingle($data, null);
    }

    return array($voteDate, $sessionId, $organizationId, $documentsData, $naslov, $dokument);
}


function parseVotesDocumentList($documentsData)
{
    $data = array();
    $potencialLinks = $documentsData->find('.listNoBtn');
    foreach ($potencialLinks as $potencialLink) {
        $links = $potencialLink->find('a');
        foreach ($links as $link) {
            $url = $link->href;
            $data[] = parseVotesDocumentSingle(null, $url);

        }
        break;
    }

    return $data;
}
function parseVotesDocumentSingle($data, $url = null)
{
    sleep(FETCH_TIMEOUT);
    if(!is_null($url)) {
        $data = str_get_html(downloadPage(DZ_URL . str_replace('&amp;', '&', $url)));
        // Log
        logger('FETCH VOTES: ' . $url);
    }

    if(is_null($data)){
        return false;
    }

    if(!($data->find('h2', 0))){
        return false;
    }

    var_dump($url);
    $note = $data->find('h2', 0)->text();
    $epa = null;
    $naslov1 = null;
    $naslov2 = null;
    $urlName = null;
    $urlLink = null;

    $tableTr = $data->find('.form table tr td');
    foreach ($tableTr as $item) {

        var_dump($item->text());

        if(stripos($item->text(), 'EPA:') !== false) {
            $t = explode(': ', $item->text());
            //var_dump($t);die();
            $epa = html_entity_decode(trim($t[1]));
        }

        if(stripos($item->text(), 'Naslov:') !== false) {
            $t = explode(': ', $item->text());
            $naslov1 = html_entity_decode(trim($t[1]));
        }

        if(stripos($item->text(), 'Naslov zadeve') !== false) {
            $t = explode(': ', $item->text());
            $naslov2 = trim($t[1]);
        }

        if(stripos($item->innertext(), 'window.open') !== false) {
            $a = $item->find('a', 0);
            $urlName = $a->text();
            $urlLink = str_replace('window.open(\'', '', $a->onclick);
            $urlLink = substr($urlLink, 0, strpos($urlLink, "'"));
        }

    }

    var_dump("end of vote doc");

    var_dump(array(
        'name' => $naslov1 . ' | ' .$naslov2,
        'note' => $note,
        'epa' => $epa,
        'urlName' => $urlName,
        'urlLink' => $urlLink
    ));


    return array(
        'name' => $naslov1 . ' | ' .$naslov2,
        'note' => $note,
        'epa' => $epa,
        'urlName' => $urlName,
        'urlLink' => $urlLink
    );
}

/**
 * Find documents on URL
 *
 * @param string $url URL to fetch
 * @return array Array of documents
 */
function parseDocument($url)
{
    $array = array();
    $data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));

    // Log
    logger('FETCH DOC: ' . $url);

    $info = $data->find('.wpthemeOverflowAuto form table tr');
    foreach ($info as $key => $item) {
        if (stripos($item->text(), 'Polni nazi') !== false) {
            $array['session'] = html_entity_decode($info[$key]->find('td', 1)->text());
        }
        if (stripos($item->text(), 'Naslov dokumenta') !== false) {
            $array['title'] = html_entity_decode($info[$key]->find('td', 1)->text());
        }
        if (stripos($item->text(), 'Datum dokumenta') !== false) {
            $array['date'] = DateTime::createFromFormat('d.m.Y', trim($info[$key]->find('td', 1)->text()))->format('Y-m-d');
        }
    }
    if (empty($array['title'])) $array['title'] = 'Brez naziva';

    if ($files = $data->find('.panelGrid', 1)) {
        foreach ($files->find('tr') as $row) {
            foreach ($row->find('td a') as $td) {
                $tdinfo = trim($td->text());

                if (preg_match('/\'(http:.*?)\'/s', (string)$td->onclick, $matches)) {

                    $filet = downloadPage($matches[1]);
                    if (preg_match('/url=(.*?)"/s', trim($filet), $matches2)) {
                        $array['filename_orig'] = $tdinfo;
                        $array['link'] = $matches2[1];
                        $array['filename'] = substr($array['link'], strrpos($array['link'], '/') + 1);
                    }
                }

            }
        }
    } else {
        $array['filename'] = '';
        $array['link'] = '';
    }

    return $array;
}


/**
 * @param $content
 * @param $organization_id
 * @param $sessionData
 * @return bool
 */
function parseSessionsSingle($content, $organization_id, $sessionData)
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
            if ($exists['in_review'] == 'f' || ($exists['in_review'] == 't' && !UPDATE_SESSIONS_IN_REVIEW)) {
                return false;
            }
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
        if (PARSE_SPEECHES) {
            if ($session->find('td.vaTop', 3)) {
                $sptable = $session->find('td.vaTop', 3)->find('a.outputLink');

                if (!empty ($sptable)) {
                    $parseSpeeches = array();
                    foreach ($sptable as $speeches) {
                        $in_review = (bool)(stripos($speeches->innerText(), "pregled") !== false);
                        if ($in_review && SKIP_WHEN_REVIEWS) continue;

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

                                if(isSpeechInReviewStatusChanged($parseSpeeche['sessionId'], $parseSpeeche['in_review'])){
                                    $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speech';
                                }else{
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
                        if (stripos($doc->innerText(), "pregled") === false) {
                            $tmp['documents'][] = parseDocument(DZ_URL . $doc->href);
                            sleep(FETCH_TIMEOUT);

                        } else {
                            if (SKIP_WHEN_REVIEWS) continue;
                        }
                    }
                }
            }
        }

        // Parse voting data
        $tmp['voting'] = array();
        if (PARSE_VOTES) {
            var_dump("VOTES");
            //  Search on DT page or not TODO: better solution needed
            preg_match('/form id="(.*?):form1"/', $session, $fmatches);
            $form_id = $fmatches[1];

            //	Retreive pager form action
            preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $session, $matches);

            //	Retreive some ViewState I have no fucking clue what it is for, but it must be present in POST
            preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $session, $matchess);

            //	Retreive number of pages
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
                                        $tmp['votingDocument'][] = parseVotesDocument(DZ_URL . $votes[3]->href, $voteDate,
                                            $tmp['id'], $organization_id, $parseVotes["dokument"],  $parseVotes["naslov"] );


                                        //var_dump($tmp['votingDocument']);                                        die();


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

                //saveVotes($tmp, $organization_id);

            }

        }

        die('sdf');

        //	Test: Izpis podatkov celotne seje
        //print_r ($tmp);
        //exit();


        //	Add to DB
        saveSession($tmp, $organization_id);
        var_dumpp("SAVE:");
        var_dumpp($organization_id);
    }

}