<?php

include_once('inc/config.php');
include_once('inc/questionFunctions.php');

error_reporting(E_ERROR | E_WARNING);

$questionxml = 'http://fotogalerija.dz-rs.si/datoteke/opendata/VPP.XML';
//$questionxml = 'questions/VPP.XML';
$xml = simplexml_load_file($questionxml);

$dokument = $xml->DOKUMENT;


foreach ($xml->VPRASANJE as $vprasanje) {

    $kartica = $vprasanje->KARTICA_VPRASANJA;

    $pobuda = array();
    $pobuda["datum"] = toDate($kartica->KARTICA_DATUM);
    $pobuda["naslov"] = trim($kartica->KARTICA_NASLOV);
    $pobuda["vlagatelj"] = trim($kartica->KARTICA_VLAGATELJ);
    $pobuda["ps"] = trim($kartica->KARTICA_POSLANSKA_SKUPINA);
    $pobuda["naslovljenec"] = trim($kartica->KARTICA_NASLOVLJENEC);

    $dokumenti = $vprasanje->PODDOKUMENTI;

    $allDocs = 0;
    foreach ($dokumenti->UNID as $dokumentUniId) {

        $doc = findDocument($dokument, $dokumentUniId);

        if(!questionExists($pobuda["datum"], $pobuda["naslov"], $pobuda["vlagatelj"], $pobuda["naslovljenec"], $doc['url'], $doc['name'])){
            $pobuda["links"][] = $doc;

            questionInsert($pobuda["datum"], $pobuda["naslov"], $pobuda["vlagatelj"], $pobuda["naslovljenec"], $doc['url'], $doc['name']);
            ++$allDocs;
        }
    }

    if($allDocs>0) {
        sendDataToQuestionApi($pobuda);
        var_dump($pobuda);
    }
}

