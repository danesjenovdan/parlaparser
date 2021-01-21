<?php
require 'vendor/autoload.php';
include_once('inc/config.php');
include_once 'inc/lawFunctions.php';
include_once('api/functions.php');
error_reporting(E_ALL);

$testis = 'asd';
var_dumpp($testis);

updateXmlsForLawFunctions();
//die();


function zakonodajaByActiveEpas($sessionId = 0)
{
    global $conn;

    if($sessionId>0){
        $allEpas = getLawsAllActiveParladataEpas($sessionId);
        var_dumpp(count($allEpas));
    }else{
        $epas0 = getLawsAllActiveParladataEpas();
        $epas1 = getLawsAllActiveEpas();

        var_dumpp(count($epas0));
        var_dumpp(count($epas1));

        $allEpas = array_merge($epas0, $epas1);
        var_dumpp(count($allEpas));
        $allEpas = array_unique($allEpas);
        var_dumpp(count($allEpas));
    }

    $counter = array(
        "DEFAULT" => 0,
        "EQUAL" => 0,
        "LOCAL_LESS_THAN_API" =>
            array(
                "st" => 0,
                "epa" => array()
            ),
        "LOCAL_MORE_THAN_API_DIFF" =>
            array(
                "st" => 0,
                "epa" => array()
            ),
        "LOCAL_MORE_THAN_API_NONE" =>
            array(
                "st" => 0,
                "epa" => array()
            )
    );

    echo "\n";
    echo "\n";

    foreach ($allEpas as $epa) {

        if(empty($epa)){
            continue;
        }

//        if($epa != '2283-VII'){
//            continue;
//        }


        $uid = 0;
        $zakon = "";
        $sessionId = "";


        $localData = getEpaDataInXml($epa);

        $apiData = getLawsFromApiByEpa($epa);

        echo '.';

        $isValid = compareEpaData($localData, $apiData);
        //var_dumpp($epa, $isValid);

        if ($isValid == "DEFAULT") {
            //sendEpaToJuric($sessionId, $uid, $sharedS);
            //var_dumpp(count($localData));
            //var_dumpp(($apiData["count"]));

            $counter["DEFAULT"] = $counter["DEFAULT"]+1;

        }
        if ($isValid == "EQUAL") {
            // count je isti, move on
            $counter["EQUAL"] = $counter["EQUAL"]+1;
        }

        if ($isValid == "LOCAL_LESS_THAN_API") {
            //local manj kot v apiju
            //error ? ... duplicates in parladata_law
            // move on

            $counter["LOCAL_LESS_THAN_API"]["st"] = $counter["LOCAL_LESS_THAN_API"]["st"]+1;
            $counter["LOCAL_LESS_THAN_API"]["epa"][] = $epa;
        }

        if ($isValid == "LOCAL_MORE_THAN_API_DIFF") {
            //local več kot v apiju
            //send missing
            //var_dump($localData);
            sendMissingEpas($localData, $apiData);

            $counter["LOCAL_MORE_THAN_API_DIFF"]["st"] = $counter["LOCAL_MORE_THAN_API_DIFF"]["st"]+1;
            $counter["LOCAL_MORE_THAN_API_DIFF"]["epa"][] = $epa;
        }

        if ($isValid == "LOCAL_MORE_THAN_API_NONE") {
            //local več kot v apiju, v apiju je 0
            //send missing
            $counter["LOCAL_MORE_THAN_API_NONE"]["st"] = $counter["LOCAL_MORE_THAN_API_NONE"]["st"]+1;
            $counter["LOCAL_MORE_THAN_API_NONE"]["epa"][] = $epa;

            foreach ($localData as $localDataSingle) {

                $sessionId = findSessionByEpa($localDataSingle["epa"]);
                $motionTitle = findMotionByEpa($localDataSingle["epa"]);
                $uid = 0;
                //echo($sessionId);
                sendEpaToJuric($sessionId, $uid, array(array($motionTitle, $epa, array($localDataSingle))));
            }
        }

        //die();
    }


    echo "\n";
    echo "\n";
    var_dumpp($counter);

}


function sendMissingEpas($localDatas, $apiData){

    //var_dumpp($localDatas);

    $epa = $localDatas[0]["epa"];
    $sessionId = findSessionByEpa($localDatas[0]["epa"]);
    $motionTitle = findMotionByEpa($localDatas[0]["epa"]);
    $uid = 0;

//    if($sessionId<1){
//        echo "\n" . "skipping .. ".$epa . "\n";
//        var_dumpp($localDatas);
//        var_dumpp($apiData);
//        return false;
//    }

    if($sessionId<1){
        $sessionId = NULL;
    }
    if(strlen($motionTitle) < 2){
        $motionTitle = $localDatas[0]["naziv"];
    }

    foreach ($localDatas as $localData) {


        $apiDataResults = $apiData["results"];

        $lawfound = false;
        foreach ($apiDataResults as $result) {

            $result_date = new DateTime($result["date"]);
            $sessionDataResult_date = new DateTime($localData["date"]);

            if (
                ($localData["procedure_phase"] == $result["procedure_phase"]) &&
                ($localData["type_of_law"] == $result["type_of_law"]) &&
                ($sessionDataResult_date->format("Y-m-d") == $result_date->format("Y-m-d"))
            ) {
                $lawfound = true;
            }

        }

        if (!$lawfound) {
            //send item
            //die("send item");
            sendEpaToJuric($sessionId, $uid, array(array($motionTitle, $epa, array($localData))));
        }
    }
}


zakonodajaByActiveEpas();
