<?php

/**
 * Parlaparser
 */

//if(file_get_contents('/home/parladaddy/parlalize/parser.lock') != "UNLOCKED"){
//	die("parlalize in progress, skipping parser");
//}

require 'vendor/autoload.php';
include_once('inc/config.php');


//for ($i=10;$i<23;$i++){
//    deleteSessionRelation($i);
//}


$guzzle = new \GuzzleHttp\Client([
    'verify' => false,
]);
$settings = [
    'username' => 'ParlaParser',
    'channel' => '#parlalize_notif',
    'link_names' => true
];
$endpoint = '';
$slackClient = new Maknz\Slack\Client($endpoint, $settings, $guzzle);

$slackClient->to('#parlalize_notif')->withIcon(':cat2:')->send(date("Y-m-d H:i:s") . " - Start");


// Get people array
$people = getPeople();
$people_new = array();

//sendReport(date("Ym-m-d H:i:s"));

//var_dumpp($people);

// Jože požen
$urls = array (
    'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/redne',
    'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/izredne'
);
parseSessions ($urls, 1);

$slackClient->to('#parlalize_notif')->withIcon(':kissing_cat:')->send(date("Y-m-d H:i:s") . " - redne + izredne");

// Delovna telesa
$url_dt = 'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDt/poDt/izbranoDt?idDT=';
parseSessionsDT ($url_dt);

$slackClient->to('#parlalize_notif')->withIcon(':camel:')->send(date("Y-m-d H:i:s") . " - delovna telesa");

//sendReport();

include_once('inc/sharedsessionsFunctions.php');
$sharedSessions = getMissingSharedSessions(date("Ymd"));
insertMissingSharedSessions($sharedSessions);

$slackClient->to('#parlalize_notif')->withIcon(':ses_no_evil:')->send(date("Y-m-d H:i:s") . " - podvojene seje");

//sendSms("DND done");

// Do things on end
parserShutdown();


$slackClient->to('#parlalize_notif')->withIcon(':aw_yeah:')->send(date("Y-m-d H:i:s") . " - parser stop");
