<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config.php');


$sharedSessions = checkSharedSessions();
//$sharedSessions = unserialize(file_get_contents("gitignore/sharedSessions"));

$similar = array();
foreach ($sharedSessions as $item) {
    $key = md5($item['sharedSessionKey']);
    $key = $item['sharedSessionKey'];
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
foreach ($similar as $item) {
    if($item["st"] > 1){
        //var_dump($item["sessionIds"]);
        var_dump($item["organizationId"]);

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
            makeSessionBackupInDeleted($sessionId);
            deleteSessionRelation($sessionId);
        }


        $i++;
    }

}
var_dump($i);


die();