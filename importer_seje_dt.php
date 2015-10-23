<?php

/**
 * Parlameter Parser
 * by Marko Bratkovič, 2015
 * This is NOT opensource, it's beer source - you take something you gimme a beer
 *
 */
//echo levenshtein (mb_strtolower ('MIRIJAM BON KLANJŠČEK', 'UTF-8'), mb_strtolower ('MIRJAM BON KLANJŠČEK', 'UTF-8'), 1, 1, 1);
//exit();
include_once ('inc/config.php');

// Get people array
$people = getPeople ();

// Which URLs to fetch
$url_root = 'http://www.dz-rs.si/wps/portal/Home/deloDZ/seje/sejeDt/poDt/izbranoDt?idDT=';

// TEST ONLY! Deletes all stuff from database - only what parser creates
//pg_query ('DELETE FROM parladata_speech');
//pg_query ('DELETE FROM parladata_ballot');
//pg_query ('DELETE FROM parladata_vote');
//pg_query ('DELETE FROM parladata_motion');
//pg_query ('DELETE FROM parladata_session');
exit();

// Jože požen
parseSessionsDT ($url_root);
