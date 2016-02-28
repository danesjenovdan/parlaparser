<?php

/**
 * Parlaparser
 */
include_once('inc/config.php');

// Get people array
$people = getPeople ();
$people_new = array();

// Which URLs to fetch
$urls = array (
		'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/redne',
		'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/izredne'
);
$url_dt = 'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDt/poDt/izbranoDt?idDT=';

// Jože požen
parseSessions ($urls, 95);

// Delovna telesa
parseSessionsDT ($url_dt);

// Do things on end
parserShutdown();
