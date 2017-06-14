<?php




function motionExists($session_id, $organization_id, $date, $name)
{

    global $conn;

    $sql = "
    select * from parladata_motion
    where 
		organization_id = '" . $organization_id . "' AND
		date = '" . $date . "' AND 
		session_id = '" . $session_id . "' AND
		text = '" . pg_escape_string($conn, $name) . "' AND 
		party_id = '" . $organization_id . "'
		;
    ";

    $result = pg_query($conn, $sql);
    if ($result) {
        if (pg_num_rows($result) > 0) {
            return true;
        }
    }
    return false;

}


 */

function voteLinkExists($session_id, $gov_id, $url)
{
    global $conn;

    $sql = "
    select * from parladata_tmp_votelinks
    where 
		session_id = '" . $session_id . "' AND
		gov_id = '" . pg_escape_string($conn, $gov_id) . "' AND
		votedoc_url = '" . pg_escape_string($conn, $url) . "'
		;
    ";

    $result = pg_query($conn, $sql);
    if ($result) {
        if (pg_num_rows($result) > 0) {
            return true;
        }
    }
    return false;
}

function voteLinkInsert($session_id, $gov_id, $url)
{
    global $conn;

    $sql = "
			INSERT INTO
				parladata_tmp_votelinks
			(created_at, updated_at, session_id, gov_id, votedoc_url)
			VALUES
			(NOW(), NOW(), '" . $session_id . "', '" . pg_escape_string($conn, $gov_id) . "', '" . pg_escape_string($conn, $url) . "')
			RETURNING id
    ";

    $voting_id = 0;
    $result = pg_query($conn, $sql);
    if (pg_affected_rows($result) > 0) {
        $insert_row = pg_fetch_row($result);
        $voting_id = $insert_row[0];
    }
    return $voting_id;
}

function checkIfSpeechInsertIsUnnecesaryDb($session_id, $date_start)
{
    global $conn;

    $sql = "
    SELECT speaker_id, content, \"order\", start_time, party_id
FROM 					parladata_speech
WHERE session_id = $session_id
    AND valid_to = 'infinity'
    and CAST(start_time as DATE) = '$date_start'
    
ORDER BY \"order\"
";
    //AND valid_to = 'infinity'
//GROUP BY content, speaker_id, "order", start_time, party_id
    //var_dump($sql);

    $hash = '';
    $result = pg_query($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {

            $date = new DateTime($row["start_time"]);
            $starttime = $date->format('Y-m-d');

            $rrow = array($row["speaker_id"] . $row["content"] . $row["order"] . $starttime . $row["party_id"]);
            file_put_contents("gitignore/checkIfSpeechInsertIsUnnecesaryDb.txt", print_r($rrow, true), FILE_APPEND);
            $hash .= md5($row["speaker_id"] . $row["content"] . $row["order"] . $starttime . $row["party_id"]);

        }
    }


    return md5($hash);
}

function checkIfSpeechInsertIsUnnecesaryParsed($speech_date, $speech)
{
    $order = 0;

    $hash = '';
    foreach ($speech['talks'] as $talk) {
        $order += 10;

        if ($talk['id'] == 0) {
            $person_id = addPerson($talk['ime']);
            if (!empty ($person_id)) {
                $talk['id'] = $person_id;
            } else {
                continue;
            }
        }

        $row = array($talk['id'] . $talk['vsebina'] . $order . $speech_date . getPersonOrganization($talk['id']));
        file_put_contents("gitignore/checkIfSpeechInsertIsUnnecesaryParsed.txt", print_r($row, true), FILE_APPEND);

        $hash .= md5($talk['id'] . $talk['vsebina'] . $order . $speech_date . getPersonOrganization($talk['id']));
    }

    return md5($hash);

}