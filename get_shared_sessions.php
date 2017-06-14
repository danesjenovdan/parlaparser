<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config.php');
include_once('inc/sharedsessionsFunctions.php');



$sharedSessions = getMissingSharedSessions(date("Ymd"));
//$sharedSessions = unserialize(file_get_contents("gitignore/sharedSessions".date("Ymd")));
insertMissingSharedSessions($sharedSessions);
