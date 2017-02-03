<?php

/**
 * Parlaparser
 */

require 'vendor/autoload.php';
include_once('inc/config_custom.php');



//$votDco = unserialize(file_get_contents("gitignore/doccache.txt"));
$votDco = unserialize(file_get_contents("gitignore/doccache_7654.txt"));

//foreach ($votDco as $item) {
//    print_r($item);
//    echo "\n";
//}
//die();
//var_dump($votDco); die();


foreach ($votDco as $item) {

    $organization_id = $item[2];
    $session_id = $item[1];
    //$date = $item[0];
    $name = (!empty ($item[5])) ? $item[5] . ' - ' . $item[4] : $item[4];

    if(!validateDate($item[0])){
        continue;
    }
    $date = DateTime::createFromFormat('d.m.Y', $item[0])->format('Y-m-d');

    $motion = findExistingMotion($organization_id, $session_id, $date, $name);

    $motionId = (!empty($motion["id"])) ? $motion["id"] : false;

    if($motionId){
        $id = insertVotingDocument($motionId, $organization_id, $session_id, $date, $name, $item);
        print_r("inserted: ");
        print_r($id);
    }else{
        print_r("nogo");
        //var_dump($item);
    }

    //die();

}

function findExistingMotion($organization_id, $session_id, $date, $name)
{
    global $conn;
    $sql = "
			select * from 
				parladata_motion
			where 
              organization_id = '" . $organization_id . "' and 
			  CAST(date AS DATE) = '" . $date . "' and
			  session_id = '" . $session_id . "' and
			  text = '" . $name . "' and
			  party_id = '" . $organization_id . "'
			;
		";

    print_r($sql);

    $result = pg_query ($conn, $sql);
    $mResultArray = null;
    if ($result) {
        if (pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
    }
    return false;
}

function insertVotingDocument($motionId, $organization_id, $session_id, $date, $name, $items)
{
    global $conn;
    $return = array();

    if(is_array($items[3]) && (count($items[3]) > 0)){
        foreach ($items[3] as $item) {


            if(empty($item['urlName']) && empty($item['urlLink'])){
                continue;
            }

            if(documentLinkExists($motionId, $organization_id, $session_id, $date, $name, $item)){
                print_r("getLinkDocument EXIST");
                continue;
            }
            $name = $item['name'];
            $note = $item['note'];
            $epa = $item['epa'];
            $urlName = $item['urlName'];
            $urlLink = $item['urlLink'];

            $sql = "
					INSERT INTO
						parladata_link
					(created_at, updated_at, url, note, organization_id, date, name, session_id, motion_id )
					VALUES
					(NOW(), NOW(), '" . pg_escape_string($conn, $urlLink) . "', '" . pg_escape_string($conn, $urlName) . "', '" . $organization_id . "', '" . pg_escape_string($conn, $date) . "', '" . pg_escape_string($conn, $name) . "', '" . $session_id . "', '" . $motionId . "')
					RETURNING id
				";
            $result = pg_query($conn, $sql);


            if (pg_affected_rows($result) > 0) {
                $insert_row = pg_fetch_row($result);
                $link_id = $insert_row[0];

                $return[] = $link_id;
            }
        }
    }

return $return;
}

function documentLinkExists($motionId, $organization_id, $session_id, $date, $name, $item){
    global $conn;
    $return = array();

    $urlName = $item['urlName'];
    $urlLink = $item['urlLink'];

    $sql = "
					select * from
						parladata_link
						where 
						url = '" . pg_escape_string($conn, $urlLink) . "' and
						note = '" . pg_escape_string($conn, $urlName) . "' and
						organization_id = '" . $organization_id . "' and 
						date = '" . pg_escape_string($conn, $date) . "' and
						session_id = '" . $session_id . "' and
						motion_id = '" . $motionId . "'
					;
				";
//			name = '" . pg_escape_string($conn, $name) . "' and

    print_r($sql);

    $result = pg_query ($conn, $sql);
    $mResultArray = null;
    if ($result) {
        if (pg_num_rows($result) > 0) {
            return true;
        }
    }
    return false;

}

function deleteExistingDocument($organization_id, $session_id, $date, $name, $item){
    global $conn;
    $return = array();

    $urlName = $item['urlName'];
    $urlLink = $item['urlLink'];

    $sql = "
					delete from
						parladata_link
						where 
						url = '" . pg_escape_string($conn, $urlLink) . "' and
						note = '" . pg_escape_string($conn, $urlName) . "' and
						organization_id = '" . $organization_id . "' and 
						date = '" . pg_escape_string($conn, $date) . "' and
						session_id = '" . $session_id . "'
					;
				";

    print_r($sql);

    $result = pg_query ($conn, $sql);
    $mResultArray = null;
    if ($result) {
        print_r(pg_num_rows($result));
        if (pg_num_rows($result) > 0) {
            return true;
        }
    }
    return false;

}

//need this
/*
$organization_id
$voting['date']
$voting['naslov']
$voting['dokument']
$session_id
*/
die();




