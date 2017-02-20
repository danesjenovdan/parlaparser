<?php


/**
 * Return list of all people
 *
 * @return array Array of people
 */
function getPeople ()
{
    global $conn;

    $array = array ();
    $sql = "
		SELECT
			id,
			name,
			family_name,
			given_name,
			additional_name,
			name_parser
		FROM
			parladata_person
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[$row['id']] = $row;
        }
    }
    return $array;
}

/**
 * Return person's organization ID
 *
 * @param int $person_id Person ID
 * @return int Organization ID
 */
function getPersonOrganization ($person_id)
{
    global $conn;

    $sql = "
		SELECT
			m.organization_id,
            CASE WHEN o.classification IN ('poslanska skupina','nepovezani poslanec') THEN 1 ELSE 0 END AS clas
		FROM
			parladata_membership m
		LEFT JOIN
            parladata_organization o
            ON
			o.id = m.organization_id
		WHERE
			m.person_id = '" . (int)$person_id . "'
		ORDER BY
			clas DESC,
			m.id DESC
		LIMIT 1
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        $row = pg_fetch_assoc($result);
        if (!empty ($row)) return $row['organization_id'];
    }
    return 96;	 //	Ostali
}

/**
 * Checks for session existance in database
 *
 * @param int $session_id Session ID
 * @return bool
 */
function sessionExists ($session_id)
{
    global $conn;

    $sql = "
		SELECT
			id,
			in_review
		FROM
			parladata_session
		WHERE
			gov_id = '" . pg_escape_string ($conn, $session_id) . "'
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return pg_fetch_assoc($result);
        }
    }
    return false;
}
/**
 * Checks for session existance in database
 * and if it has motions.
 *
 * @param int $session_id Session ID
 * @return bool
 */
function sessionExistsWithMotions ($session_id)
{
    global $conn;

    $sql = "
        SELECT
            parladata_session.id,
            parladata_session.in_review,
            array_agg(parladata_motion.id) AS motions
        FROM
            parladata_session

        LEFT JOIN parladata_motion ON parladata_motion.session_id = parladata_session.id
        WHERE
            parladata_session.gov_id = '" . pg_escape_string ($conn, $session_id) . "'
        GROUP BY parladata_session.id;
    ";

    $result = pg_query ($conn, $sql);

    $mResultArray = null;

    if ($result) {

        while ($row = pg_fetch_assoc($result)) {
            $mResultArray = $row;
        }

        if (pg_num_rows ($result) > 0 && $mResultArray["motions"] !== "{NULL}") {
            return $mResultArray;
        }
    }
    return false;
}

/**
 * Matches person's name with existing
 *
 * @param string $name Person's name
 * @return int|mixed Person ID
 */
function getPersonIdByName ($name)
{
    global $people, $people_new;

    //  Check if this new was added
    if (!empty($people_new[$name])) return $people_new[$name];

    $tmparr = array ();

    foreach ($people as $key => $item) {

        $namelist = preg_split ('/,/i', $item['name_parser']);

        $score = 999;
        foreach ($namelist as $cmpname) {
            $s = levenshtein (mb_strtolower ($name, 'UTF-8'), mb_strtolower ($cmpname, 'UTF-8'), 1, 2, 2);
            if ($s < $score) {
                $score = $s;
                $tmparr[$key] = $s;
            }
        }
    }
    asort ($tmparr);

    $num = current ($tmparr);

    if ($num <= 6 && !empty($people)) {	//	Should work :)
        $people_new[$name] = key ($tmparr);
        return key ($tmparr);	//	NAME: $people[key ($tmparr)]
    } else {
        return addPerson ($name);
    }
}


/**
 * Parse DT organizations
 *
 * @return array Array of IDs
 */
function getDTs ()
{
    global $conn;

    $array = [];
    $sql = "
		SELECT
			id,
			gov_id
		FROM
			parladata_organization
		WHERE
			classification IN ('" . implode("','", json_decode(DT_CLASSIF)) . "')
			AND
			gov_id IS NOT NULL
	";

    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[$row['id']] = $row['gov_id'];
        }
    }
    return $array;
}




/* TAGS SECTION */

/**
 * Get all Tags
 * @return array of available tags
 */
function getTags ()
{
    global $conn;

    $array = array ();
    $sql = "
		SELECT
			*
		FROM
			taggit_tag
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[$row['id']] = $row;
        }
    }
    return $array;
}

/**
 * Get all votes with tags
 */
function getAllVotes()
{
    global $conn;

    $array = array ();
    $sql = "
		SELECT
			pv.id,
			pv.name,
			pv.start_time,
			array_to_string(array_agg(t.id), ',') AS tags
		FROM
			parladata_vote pv
		LEFT JOIN
			taggit_taggeditem t
			ON
			t.object_id = pv.id
			AND
			t.content_type_id = 22
		GROUP BY
			pv.id
		ORDER BY
			pv.start_time DESC
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[$row['id']] = $row;
        }
    }
    return $array;
}


/**
 * @param $session_id
 * @return array|bool
 */
function getSessionById($session_id){
    global $conn;

    $sql = "
		SELECT
			*
		FROM
			parladata_session
		WHERE
			id = '" . pg_escape_string ($conn, $session_id) . "'
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return pg_fetch_assoc($result);
        }
    }
    return false;
}


/**
 * @param $session_id
 * @return array|bool
 */
function getSessionsOrderByDate($limit, $org_id){
    global $conn;

    $org_sql = (intval($org_id) > 0) ? "WHERE organization_id = '" . pg_escape_string ($conn, $org_id) . "'" : "";

    $array = array();
    $sql = "
		SELECT
			*
		FROM
			parladata_session
		$org_sql
		ORDER BY id DESC
		  limit '" . pg_escape_string ($conn, $limit) . "'
		  offset 0
		;
	";

    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[] = $row;
        }
    }
    return $array;
}

function getSessionsShared()
{
    global $conn;

    $sql = "
		SELECT
			*
		FROM
			parladata_session
		WHERE
			name like '%skupna seja%'
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[] = $row;
        }
    }
    return $array;
}

function sessionDeleted($session_id)
{
    global $conn;

    $sql = "
		SELECT
			id,
			in_review
		FROM
			parladata_session_deleted
		WHERE
			gov_id = '" . pg_escape_string ($conn, $session_id) . "'
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;
    /*
CREATE TABLE parladata_session_deleted
(
    id INTEGER PRIMARY KEY NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL,
    name VARCHAR(255),
    gov_id VARCHAR(255),
    start_time TIMESTAMP WITH TIME ZONE,
    end_time TIMESTAMP WITH TIME ZONE,
    organization_id INTEGER,
    classification VARCHAR(128),
    mandate_id INTEGER,
    in_review BOOLEAN NOT NULL
);
CREATE SEQUENCE parladata_session_deleted_id_seq NO MINVALUE NO MAXVALUE NO CYCLE;
ALTER TABLE parladata_session_deleted ALTER COLUMN id SET DEFAULT nextval('parladata_session_deleted_id_seq');
ALTER SEQUENCE parladata_session_deleted_id_seq OWNED BY parladata_session_deleted.id;
GRANT ALL PRIVILEGES ON TABLE parladata_session_deleted TO parladaddy;

     */
}
function sessionDeletedById($session_id)
{
    global $conn;

    $sql = "
		SELECT
			id,
			in_review
		FROM
			parladata_session_deleted
		WHERE
			id = '" . pg_escape_string ($conn, $session_id) . "'
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        if (pg_num_rows ($result) > 0) {
            return true;
        }
    }
    return false;
}



function getAllSessions($limit, $offset)
{
    global $conn;

    $sql = "
	SELECT
			*
		FROM
			parladata_session
      order by id ASC
limit $limit OFFSET $offset;
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[] = $row;
        }
    }
    return $array;
}
function getAllSessionsByOrganizationId($orgId, $limit, $offset)
{
    global $conn;

    $sql = "
	SELECT
			*
		FROM
			parladata_session
			WHERE organization_id = $orgId
      order by id ASC
limit $limit OFFSET $offset;
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $array[] = $row;
        }
    }
    return $array;
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
