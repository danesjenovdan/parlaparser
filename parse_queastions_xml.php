<?php

include_once('inc/config.php');

error_reporting(E_ERROR | E_WARNING);

function sendDataToKunst($data){

    file_put_contents("log/first.txt", print_r($data, true), FILE_APPEND);

    $url = 'https://data.parlameter.si/v1/addQuestion/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

    $result = curl_exec($ch);
    curl_close($ch);

    file_put_contents("log/first.txt", print_r($result, true), FILE_APPEND);


}


function translateCharacters($in){

    mb_internal_encoding('utf-8');
    $a = mapperSumniki($in);
    $b = html_entity_decode($a, ENT_COMPAT, 'UTF-8');

    return $b;
}

function translatePS($in){

    $search = array();
    $search[] = 'Poslanska skupina Slovenske demokratske stranke';
    $search[] = 'Poslanska skupina Stranke modernega centra';
    $search[] = 'Poslanska skupina Združena levica';
    $search[] = 'AČ - Nepovezani poslanec';
    $search[] = 'Poslanska skupina Demokratične stranke upokojencev Slovenije';
    $search[] = 'Poslanska skupina nepovezanih poslancev';

    $replace = array();
    $replace[] = 'PS Slovenska Demokratska Stranka';
    $replace[] = 'PS Stranka modernega centra';
    $replace[] = 'PS Združena Levica';
    $replace[] = 'Nepovezani poslanec Andrej Čuš';
    $replace[] = 'PS Demokratska Stranka Upokojencev Slovenije';
    $replace[] = 'PS nepovezanih poslancev ';

    if(!in_array($in, $search)){
        return $in;
    }

    return str_replace($search, $replace, $in);

}

function mapperSumniki($in){
    $search = array('&#160;');
    $replace = array(' ');

    return str_replace($search, $replace, $in);
}


function toDate($in){
    $date = new DateTime(trim($in));
    return $date->format('d.m.Y');
}

function findDokument($dokument, $id)
{

    $data = array();
    foreach ($dokument as $doc) {

        if(trim($doc->KARTICA_DOKUMENTA->UNID) == $id) {
            $data = array(
                'date' => translateCharacters(toDate($doc->KARTICA_DOKUMENTA->KARTICA_DATUM)),
                'url' => translateCharacters($doc->PRIPONKA->PRIPONKA_KLIC),
                'name' => translateCharacters($doc->KARTICA_DOKUMENTA->KARTICA_NASLOV)
            );
        }

    }

    return $data;

}


$questionxml = 'http://fotogalerija.dz-rs.si/datoteke/opendata/VPP.XML';
$questionxml = 'questions/VPP.XML';
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
    foreach ($dokumenti->UNID as $dokumentUniId) {
        $pobuda["links"][] = findDokument($dokument, $dokumentUniId);
    }

    sendDataToKunst($pobuda);
    var_dump($pobuda);
}

