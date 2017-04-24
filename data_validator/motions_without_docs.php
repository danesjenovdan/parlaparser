<?php

require '../vendor/autoload.php';
include_once('../inc/config.php');

$fname = 'data/motions_without_docs.csv';
//$csv = array_map('str_getcsv', file($fname));

$csv = array();

$fp = fopen('data/missing docs - docs.tsv', 'r');

while ( !feof($fp) )
{
    $line = fgets($fp, 2048);

    $data_txt = str_getcsv($line, "\t");

    //Get First Line of Data over here
    //print_r($data_txt);
    $tmp  =array();
    $tmp[] = $data_txt[0];
    $tmp[] = $data_txt[1];
    $tmp[] = $data_txt[2];
    $tmp[] = $data_txt[3];
    $csv[] = $tmp;

}
//var_dumpp($csv);
fclose($fp);
//die();


$fnameReport = 'data/motions_without_docs_report.csv';
$fp = fopen($fnameReport, 'w');
$reportcsv = array();

foreach ($csv as $item) {

    $ress = getMotionById($item[0]);
    $res = findTmpVotesLinkForDocumentsSingle($ress["text"]);

    if (!$res){
        //var_dumpp($item);die();
        $item[] = "x";
        $item[] = "x";
        $item[] = "x";
    }else{
        continue;
        $item[] = "F";

        $item[] = $res["epa"];
        $item[] = $res["epa_link"];

/*
        if(!empty($res["epa"])){
            //$item[] = $res["epa"];
            $item[] = $res["epa_link"];
        }else{
            $item[] = "YYY";
        }
*/
    }
    $reportcsv[] = $item;
    fputcsv($fp, $item, ",", '"');

}
fclose($fp);

function getMotionById($id){
    global $conn;
    $sql = " select * from parladata_motion WHERE id = $id";

    //var_dump($sql);

    $result = pg_query($conn, $sql);
    $insert_row = false;
    if (pg_affected_rows($result) > 0) {
        $insert_row = pg_fetch_assoc($result);
        var_dumpp($insert_row);

    }else{
        var_dumpp($sql);
    }

    return $insert_row;
}

function findTmpVotesLinkForDocumentsSingle($text){
    global $conn;

    $sql = "
    select * from parladata_tmpvoteslinkdocuments 
     WHERE dokument2 like '%" . pg_escape_string($conn, $text) . "%'
				";

    //var_dump($sql);

    $result = pg_query($conn, $sql);
    $insert_row = false;
    if (pg_affected_rows($result) > 0) {
        $insert_row = pg_fetch_assoc($result);
        var_dumpp($insert_row);

    }else{
        var_dumpp($sql);
    }

    return $insert_row;
}