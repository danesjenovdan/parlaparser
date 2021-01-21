<?php


require 'vendor/autoload.php';
include_once('inc/config.php');
error_reporting(E_ALL);

$docXml = array();
$docXml[] = 'xml/PZ.XML';
$docXml[] = 'xml/PZ7.XML';
$docXml[] = 'xml/SZ.XML';
$docXml[] = 'xml/PA.XML';
$docXml[] = 'xml/PA7.XML';
$docXml[] = 'xml/SA.XML';
$docXml[] = 'xml/PB.XML';

function updateXmls(){
    global $docXml;

    foreach ($docXml as $xmlItem) {

        $parts = explode("/", $xmlItem);
        $filecontent = file_get_contents('https://fotogalerija.dz-rs.si/datoteke/opendata/' . $parts[1]);
        file_put_contents($xmlItem, $filecontent);

        // var_dumpp($parts);
    }
}


function fillEpa()
{
    global $conn;

    $sql = "SELECT * FROM parladata_tmpvoteslinkdocuments WHERE epa != '';";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {

            $epa = trim($row["epa"]);
            $session_id = trim($row["session_id"]);
            $datum = trim($row["datum"]);

                $date = DateTime::createFromFormat('d.m.Y', $datum)->format('Y-m-d');

                $motionName = (!empty ($row['naslov2'])) ? $row['naslov2'] . ' - ' . $row['dokument2'] : $row['dokument2'];
                // var_dumpp($motionName);
                $motion = findExistingMotion(1, $session_id, $date, $motionName);

                $motionId = (!empty($motion["id"])) ? $motion["id"] : false;
                if ($motionId) {
$sql2 = "update parladata_motion set epa = '".$epa."' where id = $motionId";
                    $result2 = pg_query ($conn, $sql2);
                    if ($result2) {
                        // var_dumpp("updated $motionId");
                    }
                } else {

                }


        }
    }

}

function getEpaData(){
    global $conn;

    $sql = "SELECT * FROM parladata_motion WHERE epa != '';";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {

            $epa = trim($row["epa"]);
            $epa = str_pad($epa, 8, '0', STR_PAD_LEFT);

            $session_id = trim($row["session_id"]);
            $datum = trim($row["date"]);


            $docs = searchInXml($epa);
            if(count($docs)> 0){

                $date = new DateTime($datum);
                $date = $date->format('Y-m-d');
                //$date = DateTime::createFromFormat('Y-m-d', $datum)->format('Y-m-d');

                $motionName = $row["text"];
                // var_dumpp($motionName);


                $motionId = (!empty($row["id"])) ? $row["id"] : false;
                print_r($docs); echo "\r\n";

                if ($motionId) {
                    $id = insertVotingDocument($motionId, 1, $session_id, $date, $motionName, $docs);
                    print_r("inserted: ");
                    print_r($id);
                } else {
                    print_r("nogo");
                    //var_dumpp($item);
                }

            }
        }
    }

}

function searchInXml($epa)
{
    global $docXml;

    $items = array();
    // var_dumpp($epa);

    foreach ($docXml as $xmlItem) {

        // var_dumpp($xmlItem);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlItem);
        if ($xml === false) {
            //echo "Failed loading XML\n";
            foreach(libxml_get_errors() as $error) {
              //  echo "\t", $error->message;
            }
            continue;
        }


        foreach ($xml->DOKUMENT as $dokument) {
            if(stripos($dokument->KARTICA_DOKUMENTA->KARTICA_EPA, $epa) !== false){
                if(!empty((string)$dokument->KARTICA_DOKUMENTA->PRIPONKA->PRIPONKA_KLIC)) {
                    $tmpItem = array(
                        "urlLink" => (string)$dokument->KARTICA_DOKUMENTA->PRIPONKA->PRIPONKA_KLIC,
                        "urlName" => (string)$dokument->KARTICA_DOKUMENTA->KARTICA_NAZIV
                    );
                    $items[] = $tmpItem;
                }
            }
        }
        foreach ($xml->PREDPIS as $dokument) {
            if(stripos($dokument->KARTICA_PREDPISA->KARTICA_EPA, $epa) !== false){
                if(!empty((string)$dokument->KARTICA_PREDPISA->PRIPONKA->PRIPONKA_KLIC)) {
                    $tmpItem = array(
                        "urlLink" => (string)$dokument->KARTICA_PREDPISA->PRIPONKA->PRIPONKA_KLIC,
                        "urlName" => (string)$dokument->KARTICA_PREDPISA->KARTICA_NAZIV
                    );
                    $items[] = $tmpItem;
                }
            }
        }
        foreach ($xml->OBRAVNAVA_PREDPISA as $dokument) {
            if(stripos($dokument->KARTICA_EPA, $epa) !== false){
                if(!empty((string)$dokument->PRIPONKA->PRIPONKA_KLIC)) {
                    $tmpItem = array(
                        "urlLink" => (string)$dokument->PRIPONKA->PRIPONKA_KLIC,
                        "urlName" => (string)$dokument->KARTICA_NAZIV
                    );
                    $items[] = $tmpItem;
                }
            }
        }
    }

    return $items;

}



function findExistingEPA($epa)
{
    global $conn;

    $sql = "
			select * from 
				parladata_motion
			where 
              epa = '" . $epa . "'
			;
		";
    print_r($sql);

    $result = pg_query ($conn, $sql);
    $mResultArray = null;
    if ($result) {
        if (pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
    }
    return false;
}


//fillEpa();die();

updateXmls();

getEpaData();
