<?php

require '../vendor/autoload.php';
require "../inc/config.php";
define('STARTTIME', time() + microtime());
function debug($d){
    $k = (time() + microtime() - STARTTIME);
    $txt = sprintf("time: %.4f\n", $k);
    var_dump($d . ' - '.sprintf("%.4f", STARTTIME).' - ' .$txt);
}

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

$base_url = "https://data.parlameter.si/v1/";

$apiUsername = "parserKralj";
$apiPassword = "1123581321";

$auth = array($apiUsername, $apiPassword, "basic");

$client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));

$parserApi = new parserApi($client);

$what = 'person/3989/';
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
/*
$what = 'https://data.parlameter.si/v1/persons
https://data.parlameter.si/v1/sessions
https://data.parlameter.si/v1/motions
https://data.parlameter.si/v1/links
https://data.parlameter.si/v1/ballots
https://data.parlameter.si/v1/votes
https://data.parlameter.si/v1/speechs
https://data.parlameter.si/v1/organizations';
*/

//$what = 'person/';
//$result = apiGetList($client, $what);
//var_dump($result);
//die();

//$what = 'person/1331/';
//$result = apiGetSingle($client, $what);

//$what = 'person/';
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

//$what = 'person/3986/';
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
//$what = 'person/3985/';
//$result = apiDelete($client, $what);

var_dump($result);
die();




