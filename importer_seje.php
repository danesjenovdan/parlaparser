<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config.php');

// Get people array
$people = getPeople();
$people_new = array();

// Jože požen
$urls = array (
    'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/redne',
    'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/izredne'
);
parseSessions ($urls, 95);

// Delovna telesa
$url_dt = 'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDt/poDt/izbranoDt?idDT=';
parseSessionsDT ($url_dt);

sendReport();
sendSms("DND done");

// Do things on end
parserShutdown();