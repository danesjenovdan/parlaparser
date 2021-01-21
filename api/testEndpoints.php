<?php
require '../vendor/autoload.php';
require "../inc/config.php";

$base_url = "https://data.parlameter.si/v1/";

$apiUsername = "parserKralj";
$apiPassword = "1123581321";

$auth = array($apiUsername, $apiPassword, "basic");

$client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));

$parserApi = new parserApi($client);

$endpoints = array(
    "persons/",
    "sessions/",
    "motions/",
    "links/",
    //"ballots/",
    "votes/",
    //"speechs/",
    "organizations/"
);

foreach ($endpoints as $endpoint) {
    var_dump($endpoint);
    $res = $parserApi->apiGetList($endpoint);
    var_dump($res);
    var_dump($parserApi->getResponseJsonDecoded()[0]);
}

die();
$what = 'persons/3989/';
$data = array(
    'debug' => true,
    'form_params' => array(
        'name' => 'primoz',
        'name_parser' => 'primoz',
        "classification" => 'sef',
        "family_name" => 'klemensek',
        "given_name" => "hopsas",
        "additional_name" => 'add_name',
        "honorific_prefix" => null,
        "honorific_suffix" => null,
        "patronymic_name" => null,
        "sort_name" => null,
        "previous_occupation" => 'klosar',
        "education" => 'edu',
        "education_level" => 'edulevel',
        "mandates" => null,
        'email' => 'klemensek@gmail.om',
        'gender' => 'm',
        "birth_date" => '1983-05-19T11:12',
        "death_date" => null,
        "summary" => null,
        "biography" => null,
        "image" => null,
        "gov_id" => null,
        "gov_picture_url" => null,
        "voters" => null,
        "active" => false,
        "gov_url" => null,
        "districts" => array("lublana", "svet")
    ));

$parserApi->apiEdit($what, $data);
var_dump($parserApi->getResponseJsonDecoded());
var_dump($parserApi->getElementId());

//$what = 'persons/';
//$result = apiGetList($client, $what);
//var_dump($result);
//die();

//$what = 'persons/1331/';
//$result = apiGetSingle($client, $what);

//$what = 'persons/';
//$data = array(
//    'debug' => true,
//    'form_params' => array(
//    'name' => 'primoz',
//    'name_parser' => 'primoz',
//    "classification" => null,
//    "family_name" => null,
//    "given_name" => "hopsas",
//    "additional_name" => null,
//    "honorific_prefix" => null,
//    "honorific_suffix" => null,
//    "patronymic_name" => null,
//    "sort_name" => null,
//    "previous_occupation" => null,
//    "education" => null,
//    "education_level" => null,
//    "mandates" => null,
//    'email' => 'klemensek@gmail.om',
//    'gender' => 'm',
//    "birth_date" => null,
//    "death_date" => null,
//    "summary" => null,
//    "biography" => null,
//    "image" => null,
//    "gov_id" => null,
//    "gov_picture_url" => null,
//    "voters" => null,
//    "active" => false,
//    "gov_url" => null,
//    "districts" => array("lublana", "svet")
//));
//$result = apiCreate($client, $what, $data);

//$what = 'persons/3986/';
//$data = array(
//    'debug' => true,
//    'form_params' => array(
//    'name' => 'primoz',
//    'name_parser' => 'primoz',
//    "classification" => null,
//    "family_name" => 'klemensek',
//    "given_name" => "hopsas klemensek",
//    "additional_name" => "primo klemensek",
//    "honorific_prefix" => null,
//    "honorific_suffix" => null,
//    "patronymic_name" => null,
//    "sort_name" => null,
//    "previous_occupation" => null,
//    "education" => null,
//    "education_level" => null,
//    "mandates" => null,
//    'email' => 'klemensek@gmail.om',
//    'gender' => 'm',
//    "birth_date" => null,
//    "death_date" => null,
//    "summary" => null,
//    "biography" => null,
//    "image" => null,
//    "gov_id" => null,
//    "gov_picture_url" => null,
//    "voters" => null,
//    "active" => false,
//    "gov_url" => null,
//    "districts" => array("lublana", "svet")
//));
//$result = apiEdit($client, $what, $data);


//
//$what = 'persons/3985/';
//$result = apiDelete($client, $what);

var_dump($result);
die();




