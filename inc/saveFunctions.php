<?php
/**
 * Save vote to database
 *
 * @param array $session Session data
 * @param int $organization_id Organization ID
 */
function saveVotes ($session, $organization_id = 95) {

    global $conn, $_global_oldest_date, $people, $reportData;

    // Log
    var_dumpp($session['id']);

    $session_id = $session['id'];

    logger ('SAVING VOTES FOR SESSION: ' . $session['date']);

    //	Save votes
    foreach ($session['voting'] as $voting) {

        //	Set name to "dokument" when "naslov" is empty
        $name = (!empty ($voting['naslov'])) ? $voting['naslov'] . ' - ' . $voting['dokument'] : $voting['dokument'];

        $sql = "
			INSERT INTO
				parladata_motion
			(created_at, updated_at, organization_id, date, session_id, text, party_id)
			VALUES
			(NOW(), NOW(), '" . $organization_id . "', '" . $voting['date'] . "', '" . $session_id . "', '" . pg_escape_string ($conn, $name) . "', '" . $organization_id . "')
			RETURNING id
		";

        $reportData["parladata_motion"][] = array($session_id, $voting['date'], $organization_id);
        $result = pg_query ($conn, $sql);
        if (pg_affected_rows ($result) > 0) {
            $insert_row = pg_fetch_row ($result);
            $motion_id = $insert_row[0];

            $faza = (!empty ($array['faza'])) ? $array['faza'] : '-';

            //	Parse votes etc.
            $sql = "
				INSERT INTO
					parladata_vote
				(created_at, updated_at, name, motion_id, organization_id, session_id, start_time, result)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string ($conn, $name) . "', '" . $motion_id . "', '" . $organization_id . "', '" . $session_id . "', '" . $voting['date'] . ' ' . $voting['time'] . "', '" . $faza . "')
				RETURNING id
			";
            $reportData["parladata_vote"][] = array($session_id, $voting['date']);

            $result = pg_query ($conn, $sql);
            if (pg_affected_rows ($result) > 0) {
                $insert_row = pg_fetch_row ($result);
                $voting_id = $insert_row[0];

                $order = 0;
                foreach ($voting['votes'] as $vote) {
                    $order+=10;

                    if ($vote[4] == 0) {
                        $person_id = addPerson ($vote[1]);
                        if (!empty ($person_id)) {
                            $vote[4] = $person_id;
                        } else {
                            continue;
                        }
                    }

                    if (strtolower ($vote[3]) == 'ni') {
                        $realvote = (!empty ($vote[2])) ? 'kvorum' : 'ni';
                    } else {
                        $realvote = strtolower ($vote[3]);
                    }

                    $sql = "
						INSERT INTO
							parladata_ballot
						(created_at, updated_at, vote_id, voter_id, option, voterparty_id)
						VALUES
						(NOW(), NOW(), '" . $voting_id . "', '" . $vote[4] . "', '" . pg_escape_string ($conn, mb_strtolower($realvote)) . "', '" . getPersonOrganization ($vote[4]) . "')
					";
                    pg_query ($conn, $sql);
                    $reportData["parladata_ballot"][] = array($voting_id);
                }
            }
        }
    }
}


/**
 * Save session to database
 *
 * @param array $session Session data
 * @param int $organization_id Organization ID
 */
function saveSession ($session, $organization_id = 95)
{
    global $conn, $_global_oldest_date, $people, $reportData;

    // Log
    var_dumpp($session['id']);

    logger ('SAVING SESSION: ' . $session['date']);
    if (empty($session['speeches'])) {

        $then = new DateTime($session['date']);
        if ((int)$then->diff(date_create('now'))->format('%a') < NOTIFY_NOSPEECH) {
            // Log
            logger ('SAVING SESSION FAILED: NO SPEECHES');
            return false;
        }
    }

    if (!empty($session['id'])) {
        if ($session['review_ext'] == 1 && $session['review'] == 0) {
            $sql = "
				UPDATE
					parladata_session
				SET
					in_review = FALSE 
				WHERE
					id = " . (int)$session['id'];
            pg_query ($conn, $sql);
        }

        $session_id = $session['id'];

//            $sql = "
//			DELETE FROM
//				parladata_speech
//			WHERE
//				session_id = '" . (int)$session_id . "'
//		";
//            pg_query($conn, $sql);

    } else {
        $sql = "
			INSERT INTO
				parladata_session
			(created_at, updated_at, name, gov_id, organization_id, start_time, in_review)
			VALUES
			(NOW(), NOW(), '" . pg_escape_string ($conn, $session['name']) . "', '" . pg_escape_string ($conn, $session['link_noid']) . "', '" . $organization_id . "', '" . $session['date'] . "', '" . (int)(bool)$session['review'] . "')
			RETURNING id
		";

        $result = pg_query ($conn, $sql);
        if (pg_affected_rows ($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $session_id = $insert_row[0];

            $reportData["parladata_session"][] = array($session_id, $session['date'], $session['name']);

        } else {
            return false;
        }
    }

    // Log
    logger ('SAVED SESSION: ' . $session['date']);

    $_global_oldest_date = $session['date'];

    //	Save speeches
    foreach ($session['speeches'] as $speech_date => $speech) {
        $order = 0;
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

            $speechChange = ($speech['insertToDb'])?'parladata_speechinreview':'parladata_speech';


            /*
            1.1. valid_from - date_start
            1.2. valid_to - infinity

            after changed status, in_review = false

            1.2. valid_to - date_start
            2.1. valid_from - date_start
            2.2. valid_to - infinity
             * */

            if ($session['review_ext'] == 1 && $session['review'] == 0) {

                $sqlUpdate = "
                UPDATE parladata_speech set valid_to = NOW(), updated_at = NOW() WHERE session_id = $session_id
                ";
                pg_query($conn, $sqlUpdate);

                $sqlInsertAgain = "
				INSERT INTO
					parladata_speech
				(created_at, updated_at, speaker_id, content, \"order\", session_id, start_time, party_id, valid_from, valid_to)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string($conn, $talk['id']) . "', '" . pg_escape_string($conn, @$talk['vsebina']) . "', '" . $order . "', '" . $session_id . "', '" . $speech_date . "', '" . getPersonOrganization($talk['id']) . "', NOW(), 'infinity')
			";
                pg_query($conn, $sqlInsertAgain);

            }else if($session['review_ext'] == 1 && $session['review'] == 1){

            }else{
                $sql = "
				INSERT INTO
					parladata_speech
				(created_at, updated_at, speaker_id, content, \"order\", session_id, start_time, party_id, valid_from, valid_to)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string($conn, $talk['id']) . "', '" . pg_escape_string($conn, @$talk['vsebina']) . "', '" . $order . "', '" . $session_id . "', '" . $speech_date . "', '" . getPersonOrganization($talk['id']) . "', NOW(), 'infinity')
			";
                pg_query($conn, $sql);
            }


            $reportData["parladata_speech"][] = array($talk['id'], $speech_date);
        }
    }



    if (empty($session['id'])) {

        //	Save votes
        foreach ($session['voting'] as $voting) {

            //	Set name to "dokument" when "naslov" is empty
            $name = (!empty ($voting['naslov'])) ? $voting['naslov'] . ' - ' . $voting['dokument'] : $voting['dokument'];

            $sql = "
				INSERT INTO
					parladata_motion
				(created_at, updated_at, organization_id, date, session_id, text, party_id)
				VALUES
				(NOW(), NOW(), '" . $organization_id . "', '" . $voting['date'] . "', '" . $session_id . "', '" . pg_escape_string ($conn, $name) . "', '" . $organization_id . "')
				RETURNING id
			";

            $reportData["parladata_motion"][] = array($session_id, $voting['date'], $organization_id);
            $result = pg_query ($conn, $sql);
            if (pg_affected_rows ($result) > 0) {
                $insert_row = pg_fetch_row ($result);
                $motion_id = $insert_row[0];

                $faza = (!empty ($array['faza'])) ? $array['faza'] : '-';

                //	Parse votes etc.
                $sql = "
					INSERT INTO
						parladata_vote
					(created_at, updated_at, name, motion_id, organization_id, session_id, start_time, result)
					VALUES
					(NOW(), NOW(), '" . pg_escape_string ($conn, $name) . "', '" . $motion_id . "', '" . $organization_id . "', '" . $session_id . "', '" . $voting['date'] . ' ' . $voting['time'] . "', '" . $faza . "')
					RETURNING id
				";
                $reportData["parladata_vote"][] = array($session_id, $voting['date']);

                $result = pg_query ($conn, $sql);
                if (pg_affected_rows ($result) > 0) {
                    $insert_row = pg_fetch_row ($result);
                    $voting_id = $insert_row[0];

                    $order = 0;
                    foreach ($voting['votes'] as $vote) {
                        $order+=10;

                        if ($vote[4] == 0) {
                            $person_id = addPerson ($vote[1]);
                            if (!empty ($person_id)) {
                                $vote[4] = $person_id;
                            } else {
                                continue;
                            }
                        }

                        if (strtolower ($vote[3]) == 'ni') {
                            $realvote = (!empty ($vote[2])) ? 'kvorum' : 'ni';
                        } else {
                            $realvote = strtolower ($vote[3]);
                        }

                        $sql = "
							INSERT INTO
								parladata_ballot
							(created_at, updated_at, vote_id, voter_id, option, voterparty_id)
							VALUES
							(NOW(), NOW(), '" . $voting_id . "', '" . $vote[4] . "', '" . pg_escape_string ($conn, mb_strtolower($realvote)) . "', '" . getPersonOrganization ($vote[4]) . "')
						";
                        pg_query ($conn, $sql);
                        $reportData["parladata_ballot"][] = array($voting_id);
                    }
                }
            }
        }

        //	Save documents
        foreach ($session['documents'] as $document) {
            if (!empty($document['link'])) {
                $sql = "
					INSERT INTO
						parladata_link
					(created_at, updated_at, url, note, organization_id, date, name, session_id)
					VALUES
					(NOW(), NOW(), '" . pg_escape_string ($conn, $document['link']) . "', '" . pg_escape_string ($conn, $document['filename']) . "', '" . $organization_id . "', '" . pg_escape_string ($conn, $document['date']) . "', '" . pg_escape_string ($conn, $document['title']) . "', '" . $session_id . "')
				";
                pg_query ($conn, $sql);

                $reportData["parladata_link"][] = array($document['title']);

                //  Download documents
                if (DOC_DOWNLOAD) {
                    file_put_contents(DOC_LOCATION . $document['filename'], fopen($document['link'], 'r'));
                }
            }
        }
    }
}

/**
 * Add a person to database
 *
 * @param string $name Name
 * @return int Person's ID
 */
function addPerson ($name)
{
    global $conn, $people, $people_new;

    // Log
    logger ('NEW PERSON: ' . $name);

    $sql = "
		INSERT INTO
			parladata_person
		(created_at, updated_at, name, name_parser, active)
		VALUES
		(NOW(), NOW(), '" . pg_escape_string ($conn, mb_convert_case ($name, MB_CASE_TITLE, "UTF-8")) . "', '" . pg_escape_string ($conn, mb_convert_case ($name, MB_CASE_TITLE, "UTF-8")) . "', 'true')
		RETURNING id
	";
    $result = pg_query ($conn, $sql);
    if (pg_affected_rows ($result) > 0) {
        $insert_row = pg_fetch_row ($result);
        $person_id = $insert_row[0];

        $people[$person_id] = array(
            'id' => $person_id,
            'name' => mb_convert_case ($name, MB_CASE_TITLE, "UTF-8"),
            'name_parser' => mb_convert_case ($name, MB_CASE_TITLE, "UTF-8")
        );
        $people_new[$name] = $person_id;
//		$people = getPeople ();

        $reportData["person"][] = array($person_id, $name);

        return $person_id;
    }
    return 0;
}