<?php
/**
 * @param $dateStart
 * @param $sessionId
 * @param $review
 * @return bool
 */
function isSpeechInDb($dateStart, $sessionId, $review){
    global $conn;

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