<?php
/**
 * Save vote to database
 *
 * @param array $session Session data
 * @param int $organization_id Organization ID
 */
/*
function saveVotes($session, $organization_id = 95)
{

    global $conn, $_global_oldest_date, $people, $reportData;

    // Log
    var_dumpp($session['id']);

    $session_id = $session['id'];

    logger('SAVING VOTES FOR SESSION: ' . $session['date']);

    //	Save votes
    foreach ($session['voting'] as $voting) {

        //	Set name to "dokument" when "naslov" is empty
        $name = (!empty ($voting['naslov'])) ? $voting['naslov'] . ' - ' . $voting['dokument'] : $voting['dokument'];

        $sql = "
			INSERT INTO
				parladata_motion
			(created_at, updated_at, organization_id, date, session_id, text, party_id, epa)
			VALUES
			(NOW(), NOW(), '" . $organization_id . "', '" . $voting['date'] . "', '" . $session_id . "', '" . pg_escape_string($conn, $name) . "', '" . $organization_id . "', '" . pg_escape_string($conn, $voting["epa"]) . "')
			RETURNING id
		";

        $reportData["parladata_motion"][] = array($session_id, $voting['date'], $organization_id);
        $result = pg_query($conn, $sql);
        if (pg_affected_rows($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $motion_id = $insert_row[0];

            $faza = (!empty ($array['faza'])) ? $array['faza'] : '-';

            //	Parse votes etc.
            $sql = "
				INSERT INTO
					parladata_vote
				(created_at, updated_at, name, motion_id, organization_id, session_id, start_time, result)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string($conn, $name) . "', '" . $motion_id . "', '" . $organization_id . "', '" . $session_id . "', '" . $voting['date'] . ' ' . $voting['time'] . "', '" . $faza . "')
				RETURNING id
			";
            $reportData["parladata_vote"][] = array($session_id, $voting['date']);

            $result = pg_query($conn, $sql);
            if (pg_affected_rows($result) > 0) {
                $insert_row = pg_fetch_row($result);
                $voting_id = $insert_row[0];

                $order = 0;
                foreach ($voting['votes'] as $vote) {
                    $order += 10;

                    if ($vote[4] == 0) {
                        $person_id = addPerson($vote[1]);
                        if (!empty ($person_id)) {
                            $vote[4] = $person_id;
                        } else {
                            continue;
                        }
                    }

                    if (strtolower($vote[3]) == 'ni') {
                        $realvote = (!empty ($vote[2])) ? 'kvorum' : 'ni';
                    } else {
                        $realvote = strtolower($vote[3]);
                    }

                    $sql = "
						INSERT INTO
							parladata_ballot
						(created_at, updated_at, vote_id, voter_id, option, voterparty_id)
						VALUES
						(NOW(), NOW(), '" . $voting_id . "', '" . $vote[4] . "', '" . pg_escape_string($conn, mb_strtolower($realvote)) . "', '" . getPersonOrganization($vote[4]) . "')
					";
                    pg_query($conn, $sql);
                    $reportData["parladata_ballot"][] = array($voting_id);
                }
            }
        }
    }
}
*/
/**
 * Save session to database
 *
 * @param array $session Session data
 * @param int $organization_id Organization ID
 */
function saveSession($session, $organization_id = 95, $updateSessionStatus = true)
{
    global $conn, $_global_oldest_date, $people, $reportData;

    // Log
    var_dumpp($session['id']);

    logger('SAVING SESSION: ' . $session['date']);
    if (empty($session['speeches'])) {

        $then = new DateTime($session['date']);
        if ((int)$then->diff(date_create('now'))->format('%a') < NOTIFY_NOSPEECH) {
            // Log
            logger('SAVING SESSION FAILED: NO SPEECHES');
            //    return false;
        }
    }

    if (!empty($session['id'])) {
        if($updateSessionStatus) {
            if ($session['review_ext'] == 1 && $session['review'] == 0) {
                $sql = "
				UPDATE
					parladata_session
				SET
					in_review = FALSE 
				WHERE
					id = " . (int)$session['id'];
                pg_query($conn, $sql);
            }
        }

        $session_id = $session['id'];


    } else {
        $sql = "
			INSERT INTO
				parladata_session
			(created_at, updated_at, name, gov_id, organization_id, start_time, in_review)
			VALUES
			(NOW(), NOW(), '" . pg_escape_string($conn, $session['name']) . "', '" . pg_escape_string($conn, $session['link_noid']) . "', '" . $organization_id . "', '" . $session['date'] . "', '" . (int)(bool)$session['review'] . "')
			RETURNING id
		";

        $result = pg_query($conn, $sql);
        if (pg_affected_rows($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $session_id = $insert_row[0];

            $reportData["parladata_session"][] = array($session_id, $session['date'], $session['name']);

        } else {
            return false;
        }
    }

    // Log
    logger('SAVED SESSION: ' . $session['date']);

    $_global_oldest_date = $session['date'];

    handleSessionSpeeches($session, $session_id);

    //if (empty($session['id'])) {
    //	Save votes
    handleSessionVotes($session, $session_id, $organization_id);
    //}

    handleSessionDocs($session, $session_id, $organization_id);

    handleSessionVotesDocs($session, $session_id, $organization_id);

}

/**
 * Add a person to database
 *
 * @param string $name Name
 * @return int Person's ID
 */
function addPerson($name)
{
    global $conn, $people, $people_new;

    // Log
    logger('NEW PERSON: ' . $name);

    $sql = "
		INSERT INTO
			parladata_person
		(created_at, updated_at, name, name_parser, active)
		VALUES
		(NOW(), NOW(), '" . pg_escape_string($conn, mb_convert_case($name, MB_CASE_TITLE, "UTF-8")) . "', '" . pg_escape_string($conn, mb_convert_case($name, MB_CASE_TITLE, "UTF-8")) . "', 'true')
		RETURNING id
	";
    $result = pg_query($conn, $sql);
    if (pg_affected_rows($result) > 0) {
        $insert_row = pg_fetch_row($result);
        $person_id = $insert_row[0];

        $people[$person_id] = array(
            'id' => $person_id,
            'name' => mb_convert_case($name, MB_CASE_TITLE, "UTF-8"),
            'name_parser' => mb_convert_case($name, MB_CASE_TITLE, "UTF-8")
        );
        $people_new[$name] = $person_id;

        $reportData["person"][] = array($person_id, $name);

        return $person_id;
    }
    return 0;
}

function insertToSessionOrganizations($session_id, $organization_id)
{
    global $conn;
    $sessionOrganizationId = null;

    $sql = "
			INSERT INTO
				parladata_session_organizations
			(session_id, organization_id)
			VALUES
			('" . $session_id . "', '" . $organization_id . "' )
			RETURNING id
		";

    $result = pg_query($conn, $sql);
    if ($result) {
        if (pg_affected_rows($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $sessionOrganizationId = $insert_row[0];
        }
    }

    return $sessionOrganizationId;
}

function deleteSessionRelation($sessionId)
{
    global $conn;

    $sql = "DELETE from parladata_ballot WHERE
parladata_ballot.vote_id IN (SELECT parladata_vote.id FROM parladata_vote WHERE session_id = '" . $sessionId . "');";
    $result = pg_query($conn, $sql);

    $sql = "DELETE FROM parladata_vote WHERE session_id = '" . $sessionId . "';";
    $result = pg_query($conn, $sql);

    $sql = "DELETE from parladata_motion WHERE  session_id = '" . $sessionId . "';";
    $result = pg_query($conn, $sql);

    $sql = "DELETE FROM parladata_link WHERE session_id = '" . $sessionId . "';";
    $result = pg_query($conn, $sql);

    $sql = "DELETE from parladata_speech WHERE session_id = '" . $sessionId . "';";
    $result = pg_query($conn, $sql);

    $sql = "DELETE from parladata_session_organizations WHERE session_id = '" . $sessionId . "';";
    $result = pg_query($conn, $sql);

    $sql = "DELETE from parladata_session WHERE id = '" . $sessionId . "';";
    $result = pg_query($conn, $sql);

}

function makeSessionBackupInDeleted($sessionId)
{
    global $conn;

    $sql = "INSERT INTO parladata_session_deleted (id, created_at, updated_at, name, gov_id, start_time, end_time, organization_id, classification, mandate_id, in_review)
    SELECT id, created_at, updated_at, name, gov_id, start_time, end_time, organization_id, classification, mandate_id, in_review from parladata_session
    WHERE parladata_session.id = " . $sessionId . "
    RETURNING id
    ;
";
    var_dump($sql);
    $result = pg_query($conn, $sql);

    $sessionInsertedId = 0;
    if ($result) {
        if (pg_affected_rows($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $sessionInsertedId = $insert_row[0];
        }
    }

    var_dump($sessionInsertedId);
    return $sessionInsertedId;
}

/*
CREATE TABLE parladata_parsererrorreport
(
    id SERIAL PRIMARY KEY,
    function VARCHAR(255),
    text TEXT,
    datetime DATE DEFAULT now(),
    comment VARCHAR(255),
    session_id INT,
    speech_id INT,
    link_id INT,
    motion_id INT,
    vote_id INT,
    ballot_id INT
);
 */
function parserReport($function, $text='', $comment='', $session_id=0, $speech_id=0, $link_id=0, $motion_id=0, $vote_id=0, $ballot_id=0){

    global $conn;

    $sql = "
    INSERT INTO parladata_parsererrorreport
    (function, text, comment, session_id, speech_id, link_id, motion_id, vote_id, ballot_id) VALUES
    (
    '" . pg_escape_string($conn, $function) . "', 
    '" . pg_escape_string($conn, $text) . "', 
    '" . pg_escape_string($conn, $comment) . "', 
    '" . pg_escape_string($conn, $session_id) . "', 
    '" . pg_escape_string($conn, $speech_id) . "', 
    '" . pg_escape_string($conn, $link_id) . "', 
    '" . pg_escape_string($conn, $motion_id) . "', 
    '" . pg_escape_string($conn, $vote_id) . "', 
    '" . pg_escape_string($conn, $ballot_id) . "'
    )
    RETURNING id
    ;
    ";

    var_dump($sql);
    $result = pg_query($conn, $sql);

    $id = 0;
    if ($result) {
        if (pg_affected_rows($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $id = $insert_row[0];
        }
    }

    var_dump($id);
    return $id;
}


function handleSessionSpeeches($session, $session_id){

    global $conn, $_global_oldest_date, $people, $reportData;


    //	Save speeches
    if (count($session['speeches']) > 0) {
        if ($session['review_ext'] == 1 && $session['review'] == 0) {
            $sqlUpdate = "
                UPDATE parladata_speech set valid_to = NOW(), updated_at = NOW() WHERE session_id = $session_id
                ";
            pg_query($conn, $sqlUpdate);
        } else if ($session['review_ext'] == 1 && $session['review'] == 1) {
            if (PARSE_SPEECHES_FORCE) {
                $sqlUpdate = "
                UPDATE parladata_speech set valid_to = NOW(), updated_at = NOW() WHERE session_id = $session_id
                ";
                pg_query($conn, $sqlUpdate);
            }
        } else {

        }
    }

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

            $speechChange = ($speech['insertToDb']) ? 'parladata_speechinreview' : 'parladata_speech';

            if ($session['review_ext'] == 1 && $session['review'] == 0) {
                $sqlInsertAgain = "
				INSERT INTO
					parladata_speech
				(created_at, updated_at, speaker_id, content, \"order\", session_id, start_time, party_id, valid_from, valid_to)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string($conn, $talk['id']) . "', '" . pg_escape_string($conn, @$talk['vsebina']) . "', '" . $order . "', '" . $session_id . "', '" . $speech_date . "', '" . getPersonOrganization($talk['id']) . "', NOW(), 'infinity')
			";
                pg_query($conn, $sqlInsertAgain);

            } else if ($session['review_ext'] == 1 && $session['review'] == 1) {

                if (PARSE_SPEECHES_FORCE) {
                    $sqlInsertAgain = "
				INSERT INTO
					parladata_speech
				(created_at, updated_at, speaker_id, content, \"order\", session_id, start_time, party_id, valid_from, valid_to)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string($conn, $talk['id']) . "', '" . pg_escape_string($conn, @$talk['vsebina']) . "', '" . $order . "', '" . $session_id . "', '" . $speech_date . "', '" . getPersonOrganization($talk['id']) . "', NOW(), 'infinity')
			";
                    pg_query($conn, $sqlInsertAgain);
                }
            } else {
                $sql = "
				INSERT INTO
					parladata_speech
				(created_at, updated_at, speaker_id, content, \"order\", session_id, start_time, party_id, valid_from, valid_to)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string($conn, $talk['id']) . "', '" . pg_escape_string($conn, @$talk['vsebina']) . "', '" . $order . "', '" . $session_id . "', '" . $speech_date . "', '" . getPersonOrganization($talk['id']) . "', '" . $speech_date . "', 'infinity')
			";
                pg_query($conn, $sql);
            }

            $reportData["parladata_speech"][] = array($talk['id'], $speech_date);
        }
    }

}
function handleSessionDocs($session, $session_id, $organization_id){
    global $conn, $_global_oldest_date, $people, $reportData;

    if (empty($session['id'])) {
        var_dump("documetn save");
        //	Save documents
        foreach ($session['documents'] as $document) {
            if (!empty($document['link'])) {
                $sql = "
					INSERT INTO
						parladata_link
					(created_at, updated_at, url, note, organization_id, date, name, session_id)
					VALUES
					(NOW(), NOW(), '" . pg_escape_string($conn, $document['link']) . "', '" . pg_escape_string($conn, $document['filename']) . "', '" . $organization_id . "', '" . pg_escape_string($conn, $document['date']) . "', '" . pg_escape_string($conn, $document['title']) . "', '" . $session_id . "')
				";
                pg_query($conn, $sql);

                $reportData["parladata_link"][] = array($document['title']);
                var_dump("documetn saveok");
                //  Download documents
                if (DOC_DOWNLOAD) {
                    file_put_contents(DOC_LOCATION . $document['filename'], fopen($document['link'], 'r'));
                }
            }
        }
    }
}

function handleSessionVotes($session, $session_id, $organization_id){
    global $conn, $_global_oldest_date, $people, $reportData;

    foreach ($session['voting'] as $voting) {

        //	Set name to "dokument" when "naslov" is empty
        $name = (!empty ($voting['naslov'])) ? $voting['naslov'] . ' - ' . $voting['dokument'] : $voting['dokument'];

        if(motionExists($session_id, $organization_id, $voting['date'], $name)){
            continue;
        }

        $sql = "
				INSERT INTO
					parladata_motion
				(created_at, updated_at, organization_id, date, session_id, text, party_id, epa)
				VALUES
				(NOW(), NOW(), '" . $organization_id . "', '" . $voting['date'] . "', '" . $session_id . "', '" . pg_escape_string($conn, $name) . "', '" . $organization_id . "', '" . pg_escape_string($conn, $voting["epa"]) . "')
				RETURNING id
			";

        $reportData["parladata_motion"][] = array($session_id, $voting['date'], $organization_id);
        $result = pg_query($conn, $sql);
        if (pg_affected_rows($result) > 0) {
            $insert_row = pg_fetch_row($result);
            $motion_id = $insert_row[0];

            $faza = (!empty ($array['faza'])) ? $array['faza'] : '-';

            //	Parse votes etc.
            $sql = "
					INSERT INTO
						parladata_vote
					(created_at, updated_at, name, motion_id, organization_id, session_id, start_time, result)
					VALUES
					(NOW(), NOW(), '" . pg_escape_string($conn, $name) . "', '" . $motion_id . "', '" . $organization_id . "', '" . $session_id . "', '" . $voting['date'] . ' ' . $voting['time'] . "', '" . $faza . "')
					RETURNING id
				";
            $reportData["parladata_vote"][] = array($session_id, $voting['date']);

            $result = pg_query($conn, $sql);
            if (pg_affected_rows($result) > 0) {
                $insert_row = pg_fetch_row($result);
                $voting_id = $insert_row[0];

                $order = 0;
                foreach ($voting['votes'] as $vote) {
                    $order += 10;

                    if ($vote[4] == 0) {
                        $person_id = addPerson($vote[1]);
                        if (!empty ($person_id)) {
                            $vote[4] = $person_id;
                        } else {
                            continue;
                        }
                    }

                    if (strtolower($vote[3]) == 'ni') {
                        $realvote = (!empty ($vote[2])) ? 'kvorum' : 'ni';
                    } else {
                        $realvote = strtolower($vote[3]);
                    }

                    $sql = "
							INSERT INTO
								parladata_ballot
							(created_at, updated_at, vote_id, voter_id, option, voterparty_id)
							VALUES
							(NOW(), NOW(), '" . $voting_id . "', '" . $vote[4] . "', '" . pg_escape_string($conn, mb_strtolower($realvote)) . "', '" . getPersonOrganization($vote[4]) . "')
						";
                    pg_query($conn, $sql);
                    $reportData["parladata_ballot"][] = array($voting_id);
                }
            }
        }
    }

}
function handleSessionVotesDocs($session, $session_id, $organization_id){
    global $conn, $_global_oldest_date, $people, $reportData;


    foreach ($session['votingDocument'] as $item) {

        //$organization_id = $item[2];
        //$session_id = $item[1];
        //$date = $item[0];
        $name = (!empty ($item[5])) ? $item[5] . ' - ' . $item[4] : $item[4];

        if (!validateDate($item[0])) {
            continue;
        }
        $date = DateTime::createFromFormat('d.m.Y', $item[0])->format('Y-m-d');

        $motion = findExistingMotion($organization_id, $session_id, $date, $name);

        $motionId = (!empty($motion["id"])) ? $motion["id"] : false;

        if ($motionId) {
            $id = insertVotingDocument($motionId, $organization_id, $session_id, $date, $name, $item);
            print_r("inserted: ");
            print_r($id);
        } else {
            print_r("nogo");
            //var_dump($item);
        }

        //die();

    }

}


function insertVotingDocument($motionId, $organization_id, $session_id, $date, $name, $items)
{
    global $conn;
    $return = array();


        foreach ($items as $item) {


            if(documentLinkExists($motionId, $organization_id, $session_id, $date, $name, $item)){
                print_r("getLinkDocument EXIST");
                continue;
            }
            $name = $item['urlName'];

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


    return $return;
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