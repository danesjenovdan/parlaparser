<?php

/**
 * Parlaparser
 */
include_once ('inc/config.php');

// Get people array
$people = getPeople ();
$people_new = array();

// Which URLs to fetch
$url_root = 'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDt/poDt/izbranoDt?idDT=';

// TEST ONLY! Deletes all stuff from database - only what parser creates
//pg_query ('DELETE FROM parladata_speech');
//pg_query ('DELETE FROM parladata_ballot');
//pg_query ('DELETE FROM parladata_vote');
//pg_query ('DELETE FROM parladata_motion');
//pg_query ('DELETE FROM parladata_session');
//exit(); // DECIDE IF YOU WANT TO RUN -> FOR MARKO'S DEV

// Jože požen
parseSessionsDT ($url_root);

// Do things on end
parserShutdown();