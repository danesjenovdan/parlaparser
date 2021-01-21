<?php
require 'vendor/autoload.php';
include_once('inc/config.php');
include_once 'inc/lawFunctions.php';
include_once('api/functions.php');
error_reporting(E_ALL);


//updateXmlsForLawFunctions();
//die();

$sessionId = $argv[1];
$sessionId = 9743;

if ((int)$sessionId < 1) {
    echo "\n";
    echo "Error:";
    echo "\n";
    echo "php zakonodaja.php 666";
    echo "666 - sessionId";
    echo "\n";
    echo "\n";
    die();
}

zakonodajaBySession($sessionId);
