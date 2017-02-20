<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config.php');

function forceDeleteBackupSessions(){

    global $conn;

    $sql = "
		SELECT
			*
		FROM
			parladata_session_deleted
	";
    $result = pg_query ($conn, $sql);

    if ($result) {
        while ($row = pg_fetch_assoc($result)) {

            var_dump($row["id"]);

            //deleteSessionRelation($row["id"]);


        }
    }
}

function forceDeleteDuplicatedSessions(){
    $delete = array(
        9160,
        9160,
        9161,
        9161,
        9162,
        9162,
        9163,
        9163,
        9164,
        9164,
        9165,
        9165,
        9166,
        9166,
        9167,
        9167,
        9168,
        9168,
        9169,
        9169,
        9170,
        9170,
        9171,
        9171,
        9172,
        9172,
        9173,
        9173,
        9174,
        9174,
        9175,
        9175,
        9176,
        9176,
        9177,
        9177,
        9178,
        9180,
        9180,
        9181,
        9181,
        9182,
        9182,
        9183,
        9183,
        9184,
        9184,
        9185,
        9185,
        9186,
        9186,
        9187,
        9187,
        9188,
        9188,
        9189,
        9189,
        9190,
        9190,
        9191,
        9191,
        9192,
        9192,
        9193,
        9193,
        9194,
        9194,
        9195,
        9195,
        9196,
        9196,
        9197,
        9197,
        9198,
        9198,
        9199,
        9199,
        9200,
        9201,
        9201,
        9202,
        9202,
        9203,
        9203,
        9204,
        9204,
        9205,
        9205,
        9206,
        9206,
        9207,
        9207,
        9208,
        9208,
        9209,
        9209,
        9210,
        9210,
        9211,
        9211,
        9212,
        9212,
        9213,
        9213,
        9214,
        9214,
        9215,
        9215,
        9216,
        9216,
        9217,
        9217,
        9218,
        9218,
        9219,
        9219,
        9220,
        9220,
        9221,
        9221,
        9222,
        9222,
        9223,
        9223,
        9224,
        9224,
        9225,
        9225,
        9226,
        9226,
        9227,
        9227,
        9228,
        9228,
        9229,
        9229,
        9230,
        9230,
        9231,
        9231,
        9232,
        9232,
        9233,
        9233,
        9234,
        9234,
        9235,
        9235,
        9236,
        9236,
        9237,
        9237,
        9238,
        9238,
        9239,
        9239,
        9240,
        9240,
        9241,
        9241,
        9242,
        9242,
        9243,
        9243,
        9244,
        9244,
        9245,
        9245,
        9246,
        9246,
        9247,
        9247,
        9248,
        9248,
        9249,
        9249,
        9250,
        9250,
        9251,
        9251,
        9252,
        9252,
        9253,
        9253,
        9254,
        9254,
        9255,
        9255,
        9256,
        9256,
        9257,
        9257,
        9258,
        9258,
        9259,
        9259,
        9260,
        9260,
        9261,
        9261,
        9262,
        9262,
        9263,
        9263,
        9264,
        9264,
        9265,
        9265,
        9266,
        9266,
        9267,
        9267,
        9268,
        9268,
        9269,
        9269,
        9270,
        9270,
        9271,
        9271,
        9272,
        9272,
        9273,
        9273,
        9274,
        9274,
        9275,
        9275,
        9276,
        9276,
        9277,
        9277,
        9278,
        9278,
        9279,
        9279,
        9280,
        9280,
        9281,
        9281,
        9282,
        9282,
        9283,
        9283,
        9284,
        9284,
        9285,
        9285,
        9286,
        9286,
        9287,
        9287,
        9288,
        9288,
        9289,
        9289,
        9290,
        9290,
        9291,
        9291,
        9292,
        9292,
        9293,
        9293,
        9294,
        9294,
        9295,
        9295,
        9296,
        9296,
        9297,
        9297,
        9298,
        9298,
        9299,
        9299,
        9300,
        9300,
        9301,
        9301,
        9302,
        9302,
        9303,
        9303,
        9304,
        9304,
        9305,
        9305,
        9306,
        9306,
        9307,
        9307,
        9308,
        9308,
        9309,
        9309,
        9310,
        9310,
        9311,
        9311,
        9312,
        9312,
        9313,
        9313,
        9314,
        9314,
        9315,
        9315,
        9316,
        9316,
        9317,
        9317,
        9318,
        9318,
        9319,
        9319,
        9320,
        9320,
        9321,
        9321
    );

    foreach ($delete as $item) {
        var_dump($item);
        deleteSessionRelation($item);
    }

}

//forceDeleteBackupSessions();
forceDeleteDuplicatedSessions();
die();


function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
    $sort_col = array();
    foreach ($arr as $key=> $row) {
        $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
}

function insertMissingSharedSessions($sharedSessions){
    //array_sort_by_column($sharedSessions, 'sharedSessionKey');

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

            var_dump($item["sessionIds"]);
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
                $insertedId = makeSessionBackupInDeleted($sessionId);
                if($insertedId > 0){
                    var_dump("insertedId " . $insertedId);
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
    file_put_contents("gitignore/sharedSession", serialize($deletedSEssions, true), FILE_APPEND);
    var_dump($i);
}


$sharedSessions = getMissingSharedSessions(date("Ymd"));
//$sharedSessions = unserialize(file_get_contents("gitignore/sharedSessions".date("Ymd")));
insertMissingSharedSessions($sharedSessions);
