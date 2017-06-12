<?php

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


function questionExists($dateString, $title, $applicant, $addressee, $url, $docname)
{
    global $conn;

    $sql = "
					select * from
						parladata_tmp_questions
						where 
						datum = '" . pg_escape_string($conn, $dateString) . "' and
						naslov = '" . pg_escape_string($conn, $title) . "' and
						vlagatelj = '" . pg_escape_string($conn, $applicant) . "' and 
						naslovljenec = '" . pg_escape_string($conn, $addressee) . "' and
						url = '" . pg_escape_string($conn, $url) . "' and
						docname = '" . pg_escape_string($conn, $docname) . "'
					;
				";

    var_dump($sql);

    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;
}
function questionInsert($dateString, $title, $applicant, $addressee, $url, $docname)
{
    global $conn;

    $sql = "INSERT INTO parladata_tmp_questions (datum, naslov, vlagatelj, naslovljenec, url, docname) VALUES (
'" . pg_escape_string($conn, $dateString) . "', 
						'" . pg_escape_string($conn, $title) . "', 
						'" . pg_escape_string($conn, $applicant) . "',  
						'" . pg_escape_string($conn, $addressee) . "', 
						'" . pg_escape_string($conn, $url) . "', 
						'" . pg_escape_string($conn, $docname) . "'
);";

    var_dump($sql);

    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;


}


function sendDataToQuestionApi($data){

    file_put_contents("log/sendDataToQuestionApi.txt", print_r($data, true), FILE_APPEND);

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

    file_put_contents("log/sendDataToQuestionApi.txt", print_r($result, true), FILE_APPEND);


}


function translateCharacters($in){

    mb_internal_encoding('utf-8');
    $a = mapperSumniki($in);
    $b = html_entity_decode($a, ENT_COMPAT, 'UTF-8');

    return $b;
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

function findDocument($dokument, $id)
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