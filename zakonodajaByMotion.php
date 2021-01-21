<?php
require 'vendor/autoload.php';
include_once('inc/config.php');
include_once 'inc/lawFunctions.php';
include_once('api/functions.php');
error_reporting(E_ALL);


//updateXmlsForLawFunctions();
//die();

$motionId = $argv[1];
$motionId = 7261;

if ((int)$motionId < 1) {
    echo "\n";
    echo "Error:";
    echo "\n";
    echo "php zakonodaja.php 666";
    echo "666 - motionId";
    echo "\n";
    echo "\n";
    die();
}

zakonodajaByMotion($motionId);
