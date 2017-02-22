<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config_custom.php');

//$e = unserialize(file_get_contents('gitignore/tmpparset345.txt'));
//var_dump($e);
//foreach ($e as $k => $item) {
//
////    var_dump($item);
//
//    if($k == 'voting') {
//
//        //var_dump($item[0]);
//
//        foreach ($item as $kk => $itemm) {
//            //var_dump($kk);
//            //var_dump($itemm);
//        }
//    }
//
// if($k == 'votingDocument') {
//
//        var_dump($itemm[0]);
//
//        foreach ($item as $kk => $itemm) {
//            //var_dump($kk);
//            //var_dump($itemm);
//        }
//    }
//
//}
//die();


//////$votDco = unserialize(file_get_contents("gitignore/doccache.txt"));
$votDco = unserialize(file_get_contents("gitignore/doccache_5572.txt"));
//
//var_dump(count($votDco));die();
//
//foreach ($votDco as $item) {
//    print_r($item);
//    echo "\n";
//}
//die();

//foreach ($votDco as $item) {
//    if($item[5] == 'Zakon o spremembi in dopolnitvah Zakona o tujcih'){
//        var_dump($item);
        readCacheFromFile($votDco);
//    }
///}
die();

var_dump($votDco); die();

$dir = 'gitignore/';
$doccacheFiles = scandir($dir);
foreach ($doccacheFiles as $doccacheFile) {

    if(stripos($doccacheFile, "doccache_") !== false){

        $votDco = unserialize(file_get_contents($dir.$doccacheFile));
        readCacheFromFile($votDco);
    }

}


function readCacheFromFile($votDco)
{

    foreach ($votDco as $item) {

        $organization_id = $item[2];
        $session_id = $item[1];
        //$date = $item[0];
        $name = (!empty ($item[5])) ? $item[5] . ' - ' . $item[4] : $item[4];

        if (!validateDate($item[0])) {
            continue;
        }
        $date = DateTime::createFromFormat('d.m.Y', $item[0])->format('Y-m-d');

        $motion = findExistingMotion($organization_id, $session_id, $date, $name);

        $motionId = (!empty($motion["id"])) ? $motion["id"] : false;

        if ($motionId) {
            $id = insertVotingDocument($motionId, $organization_id, $session_id, $date, $name, $item);
            print_r("inserted: ");
            print_r($id);
        } else {
            print_r("nogo");
            //var_dump($item);
        }

        //die();

    }
}







//need this
/*
$organization_id
$voting['date']
$voting['naslov']
$voting['dokument']
$session_id
*/
die();




