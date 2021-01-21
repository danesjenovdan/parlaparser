<?php


function getMissingSharedSessions($date)
{

    $sharedSession = array();
    $sessionShared = getSessionsShared();

    $stAll = count($sessionShared);
    $i = 0;

    foreach ($sessionShared as $item) {
        ++$i;
        if (sessionDeletedById($item["id"])) {
            continue;
        }

        $url = html_entity_decode($item["gov_id"]);
        var_dumpp($url);

        $base = downloadPage(DZ_URL . $url);
        $content = str_get_html($base);

        $ullistlis = $content->find('ul.listNoBtn');
        foreach ($ullistlis as $ullistli) {

            $sklicSeje = $ullistli->text();
            if (stripos($sklicSeje, "sklic seje") !== false) {
                $urlullistli = $ullistli->find("a", 0);
                $urlSklic = $urlullistli->getAttribute("href");

                $sharedS = prepareDataSharedSession($urlSklic, $date);
                $sharedSessionTmp = array("sessionId" => $item["id"], "organizationId" => $item["organization_id"], "data" => $sharedS, "sharedSessionKey" => implode("", $sharedS));

                file_put_contents("gitignore/sharedSession" . $date, print_r($sharedSessionTmp, true), FILE_APPEND);
                var_dumpp($sharedSessionTmp);
                $sharedSession[] = $sharedSessionTmp;

                continue;
            }

        }

        var_dumpp(($stAll - $i));
    }

    file_put_contents("gitignore/sharedSessions" . $date, serialize($sharedSession), FILE_APPEND);
    return $sharedSession;
}

function prepareDataSharedSession($url, $date)
{

    $datum = '';
    $ura = '';
    $prostor = '';

    $base = downloadPage(DZ_URL . $url);
    $content = str_get_html($base);

    $tableTr = $content->find('.form table tr');
    foreach ($tableTr as $item) {


        if (stripos($item->text(), 'Datum:') !== false) {
            $t = $item->find(".outputText", 0);
            $datum = html_entity_decode(trim($t->text()));
        }

        if (stripos($item->text(), 'Ura:') !== false) {
            $t = $item->find(".outputText", 0);
            $ura = html_entity_decode(trim($t->text()));
        }

        if (stripos($item->text(), 'Prostor') !== false) {
            $t = $item->find(".outputText", 0);
            $prostor = trim($t->text());
        }
    }

    if (
        empty($datum) |
        empty($ura) |
        empty($prostor)
    ) {
        file_put_contents("gitignore/sharedSessionsERRORS" . $date, print_r(array($datum, $ura, $prostor), true), FILE_APPEND);
    }

    return array($datum, $ura, $prostor);

}

function insertMissingSharedSessions($sharedSessions){

    $similar = array();
    foreach ($sharedSessions as $item) {
        //$key = md5($item['sharedSessionKey']);
        $key = $item['sharedSessionKey'];

        print $key . " " . $item["sessionId"] ."\n";

        if(!array_key_exists($key, $similar)){
            $similar[$key]["st"] = 1;
            $similar[$key]["sessionIds"] = $item["sessionId"];
            $similar[$key]["organizationId"] = $item["organizationId"];
        }else{
            $similar[$key]["st"] = $similar[$key]["st"] + 1;
            $similar[$key]["sessionIds"] = $similar[$key]["sessionIds"] .','. $item["sessionId"];
            $similar[$key]["organizationId"] = $similar[$key]["organizationId"] .','. $item["organizationId"];
        }
    }

    $i = 1;
    $deletedSEssions = array();
    foreach ($similar as $item) {
        if($item["st"] > 1){

            var_dumpp($item["sessionIds"]);
            var_dumpp($item["organizationId"]);
            $sessionIds = explode(",", $item["sessionIds"]);
            $sessionId = $sessionIds[0];
            $orgIds = explode(",", $item["organizationId"]);
            foreach ($orgIds as $orgId) {
                insertToSessionOrganizations($sessionId, $orgId);
            }

            $sessionIdsDelete = array_shift($sessionIds);

            foreach ($sessionIds as $sessionId) {
                if(sessionDeletedById($sessionId)){
                    continue;
                }
                $insertedId = makeSessionBackupInDeleted($sessionId);
                if($insertedId > 0){
                    var_dumpp("insertedId " . $insertedId);
                    deleteSessionRelation($sessionId);
                    $deletedSEssions[] = $sessionId;
                }
            }

            $i++;
        }else{

            $sessionIds = explode(",", $item["sessionIds"]);

            print($sessionIds[0])."\n";
        }

    }
    file_put_contents("gitignore/sharedSession", serialize($deletedSEssions), FILE_APPEND);
    var_dumpp($i);
}
