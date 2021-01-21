<?php

function parseVotesBase($session, $cookiess, $tmp)
{
    var_dumpp("VOTES");
    //  Search on DT page or not TODO: better solution needed
    preg_match('/form id="(.*?):form1"/', $session, $fmatches);
    $form_id = $fmatches[1];

    //	Retreive pager form action
    preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $session, $matches);

    //	Retreive some ViewState I have no fucking clue what it is for, but it must be present in POST
    preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $session, $matchess);

    //	Retreive number of pages
    preg_match('/Stran 1 od (\d+)/i', $session, $matchesp);

    if (!empty($matchesp[1]) && (int)$matchesp[1] > 0) {


        for ($i = 1; $i <= (int)$matchesp[1]; $i++) {
            var_dumpp("PAGE" . $i);

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

            var_dumpp(DZ_URL . $matches[1]);

            if ($subpage = file_get_contents(DZ_URL . $matches[1], false, $context)) {

                file_put_contents("s1.html", $subpage);

                $votearea = str_get_html($subpage)->find('table.dataTableExHov', 0);
                if (!empty ($votearea)) {

                    $votes = $votearea->find('tbody tr');
                    foreach ($votes as $vote) {

                        $votee = $vote->find('td a.outputLink');

                        $voteEpa = '';
                        if (isset($votee[3])) {
                            if (stripos($votee[3]->text(), "-V") !== false) {
                                $voteEpa = trim($votee[3]->text());
				var_dump($voteEpa);
                            }
                        }

                        $voteHref = '';
                        if (isset($votee[0])) {
                            if (preg_match('/\d{2}\.\d{2}\.\d{4}/is', $votee[0]->text())) {
                                $voteHref = $votee[0]->href;
                            }
                        }

                        if (!empty($voteHref)) {
                            $tmp['voting'][] = parseVotes(DZ_URL . $voteHref, $voteEpa);
                            sleep(FETCH_TIMEOUT);
                        }
                    }
                }

            }
        }

    }

    // var_dump($tmp);
    return $tmp;

}
