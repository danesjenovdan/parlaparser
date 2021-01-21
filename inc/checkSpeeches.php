<?php
/**
 * @param $dateStart
 * @param $sessionId
 * @param $review
 * @return bool
 */
function isSpeechInDb($dateStart, $sessionId, $review){
    global $conn;

    var_dumpp($dateStart);
    var_dumpp($sessionId);

    if(!$sessionId){
        return false;
    }

    $speechTable = ($review)?'parladata_speechinreview':'parladata_speech';

    $sql = "SELECT * FROM $speechTable WHERE
			session_id = '" . $sessionId . "'
			AND
			CAST(start_time AS DATE) = '" . $dateStart . "'
	";

    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;
}

/**
 * @param $sessionId
 * @param $review
 * @return bool
 */
function isSpeechInReviewStatusChanged($sessionId, $review){
    global $conn;

    if(!$sessionId){
        return false;
    }

    $sql = "SELECT * FROM parladata_session WHERE id = '" . $sessionId . "'	";

    $result = pg_query ($conn, $sql);
    if ($result) {

        $row = pg_fetch_assoc($result);
        $in_review = ($row['in_review'] == 't') ? true : false;
        if($in_review == $review){
            return false;
        }else{
            return true;
        }
    }

    return true;
}
/*
function motionExists($session_id, $organization_id, $date, $name)
{

    global $conn;

    $sql = "
    select * from parladata_motion
    where 
		organization_id = '" . $organization_id . "' AND
		date = '" . $date . "' AND 
		session_id = '" . $session_id . "' AND
		text = '" . pg_escape_string ($conn, $name) . "' AND 
		party_id = '" . $organization_id . "'
		;
    ";

    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;

}
*/