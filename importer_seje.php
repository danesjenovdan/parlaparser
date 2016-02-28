<?php

/**
 * Parlaparser
 */
include_once ('inc/config.php');

// Get people array
$people = getPeople ();

// Which URLs to fetch
$urls = array (
		'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/redne',
		'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDrzavnegaZbora/PoVrstiSeje/izredne'
);

// TEST ONLY! Deletes all stuff from database - only what parser creates
// pg_query ('DELETE FROM parladata_speech');
// pg_query ('DELETE FROM parladata_ballot');
// pg_query ('DELETE FROM parladata_vote');
// pg_query ('DELETE FROM parladata_motion');
// pg_query ('DELETE FROM parladata_session');
//exit(); // DECIDE IF YOU WANT TO RUN -> FOR MARKO'S DEV

// Jože požen
parseSessions ($urls, 95);

// Do things on end
parserShutdown();
