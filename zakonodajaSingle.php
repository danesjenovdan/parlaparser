<?php
require 'vendor/autoload.php';
include_once('inc/config.php');
include_once('api/functions.php');
include_once('inc/lawFunctions.php');
error_reporting(E_ALL);

$sessionId = 9815;
getSendLawToApi($sessionId);
