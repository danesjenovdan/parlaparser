<?php

include_once('inc/config.php');

error_reporting(E_ERROR | E_WARNING);

function sendDataToKunst($data){

    file_put_contents("log/first.txt", print_r($data, true), FILE_APPEND);

    $url = 'http://data.nov.parlameter.si/v1/addQuestion/'.DATA_API_QUESTIONS_KEY;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);
    echo "TO JE RESULT OD POSTA".$body;
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


$questionxml = 'https://fotogalerija.dz-rs.si/datoteke/opendata/VPP.XML';
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

    $pobuda_date = new DateTime($pobuda["datum"]);
    $date = new DateTime('2017-07-12');
    if($pobuda_date > $date){
        echo "it is";
    }else{
        echo "continue";
      continue;
    }

    $allDocs = 0;
    foreach ($dokumenti->UNID as $dokumentUniId) {

        $doc = findDokument($dokument, $dokumentUniId);
        echo "\n".questionExists($pobuda["datum"], $pobuda["naslov"], $pobuda["vlagatelj"], $pobuda["naslovljenec"], $doc['url'], $doc['name'])."\n";
        if(!questionExists($pobuda["datum"], $pobuda["naslov"], $pobuda["vlagatelj"], $pobuda["naslovljenec"], $doc['url'], $doc['name']) or 1){
            echo "LINKJZ";
            $pobuda["links"][] = $doc;

            questionInsert($pobuda["datum"], $pobuda["naslov"], $pobuda["vlagatelj"], $pobuda["naslovljenec"], $doc['url'], $doc['name']);
            ++$allDocs;
        }
    }
    var_dump($doc);
    //var_dump($pobuda);
    if($allDocs>0) {
        sendDataToKunst($pobuda);
        var_dumpp($pobuda);
    }
}

/*
CREATE TABLE parladata_tmp_questions
(
    id INTEGER PRIMARY KEY NOT NULL,
    datum VARCHAR(20),
    naslov TEXT,
    vlagatelj TEXT,
    naslovljenec TEXT,
    url TEXT,
    docname TEXT
);

CREATE SEQUENCE parladata_tmp_questions_id_seq NO MINVALUE NO MAXVALUE NO CYCLE;
ALTER TABLE parladata_tmp_questions ALTER COLUMN id SET DEFAULT nextval('parladata_tmp_questions_id_seq');
ALTER SEQUENCE parladata_tmp_questions_id_seq OWNED BY parladata_tmp_questions.id;
GRANT ALL PRIVILEGES ON TABLE parladata_tmp_questions TO parladaddy;
GRANT USAGE, SELECT ON SEQUENCE parladata_tmp_questions_id_seq TO parladaddy;
 */
function questionExists($datum, $naslov, $vlagatelj, $naslovljenec, $url, $docname)
{
    global $conn;

    $sql = "
					select * from
						parladata_tmp_questions
						where 
						datum = '" . pg_escape_string($conn, $datum) . "' and
						naslov = '" . pg_escape_string($conn, $naslov) . "' and
						vlagatelj = '" . pg_escape_string($conn, $vlagatelj) . "' and 
						naslovljenec = '" . pg_escape_string($conn, $naslovljenec) . "' and
						url = '" . pg_escape_string($conn, $url) . "' and
						docname = '" . pg_escape_string($conn, $docname) . "'
					;
				";

    var_dumpp($sql);

    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;
}
function questionInsert($datum, $naslov, $vlagatelj, $naslovljenec, $url, $docname)
{
    global $conn;

    $sql = "INSERT INTO parladata_tmp_questions (datum, naslov, vlagatelj, naslovljenec, url, docname) VALUES (
'" . pg_escape_string($conn, $datum) . "', 
						'" . pg_escape_string($conn, $naslov) . "', 
						'" . pg_escape_string($conn, $vlagatelj) . "',  
						'" . pg_escape_string($conn, $naslovljenec) . "', 
						'" . pg_escape_string($conn, $url) . "', 
						'" . pg_escape_string($conn, $docname) . "'
);";

    var_dumpp($sql);

    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;


}
