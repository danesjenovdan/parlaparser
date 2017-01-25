<?php


function checkSharedSessions()
{

    $sharedSession = array();
    $sessionShared = getSessionsShared();

    $stAll = count($sessionShared);
    $i = 0;

    foreach ($sessionShared as $item) {
        $url = html_entity_decode($item["gov_id"]);
        var_dump($url);

        $base = downloadPage(DZ_URL .$url);
        $content = str_get_html($base);

        $ullistlis = $content->find('ul.listNoBtn');
        foreach ($ullistlis as $ullistli) {

            $sklicSeje = $ullistli->text();
            if(stripos($sklicSeje, "sklic seje") !== false){
                $urlullistli = $ullistli->find("a", 0);
                $urlSklic = $urlullistli->getAttribute("href");


                $sharedS = prepareDataSharedSession($urlSklic);
                $sharedSessionTmp = array("sessionId" => $item["id"], "organizationId" => $item["organization_id"], "data" => $sharedS, "sharedSessionKey"=> implode("", $sharedS));

                file_put_contents("gitignore/sharedSessions", print_r($sharedSessionTmp, true), FILE_APPEND);

                $sharedSession[] = $sharedSessionTmp;

                continue;
            }

        }
        //die();
++$i;
        var_dump(($stAll-$i));
    }

    file_put_contents("gitignore/sharedSessions", serialize($sharedSession), FILE_APPEND);
    return $sharedSession;
}

function prepareDataSharedSession($url)
{

    $base = downloadPage(DZ_URL .$url);
    $content = str_get_html($base);

    $tableTr = $content->find('.form table tr');
    foreach ($tableTr as $item) {

        //var_dump($item->text());

        if(stripos($item->text(), 'Datum:') !== false) {
            $t = $item->find(".outputText", 0);
            $datum = html_entity_decode(trim($t->text()));
        }

        if(stripos($item->text(), 'Ura:') !== false) {
            $t = $item->find(".outputText", 0);
            $ura = html_entity_decode(trim($t->text()));
        }

        if(stripos($item->text(), 'Prostor') !== false) {
            $t = $item->find(".outputText", 0);
            $prostor = trim($t->text());
        }
    }

    return array($datum, $ura, $prostor);

}