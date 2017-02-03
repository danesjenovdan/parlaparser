<?php


function checkSharedSessions()
{

    $sharedSession = array();
    $sessionShared = getSessionsShared();

    $stAll = count($sessionShared);
    $i = 0;

    foreach ($sessionShared as $item) {
        $url = html_entity_decode($item["gov_id"]);
        var_dump($url);

        $base = downloadPage(DZ_URL .$url);
        $content = str_get_html($base);

        $ullistlis = $content->find('ul.listNoBtn');
        foreach ($ullistlis as $ullistli) {

            $sklicSeje = $ullistli->text();
            if(stripos($sklicSeje, "sklic seje") !== false){
                $urlullistli = $ullistli->find("a", 0);
                $urlSklic = $urlullistli->getAttribute("href");


                $sharedS = prepareDataSharedSession($urlSklic);
                $sharedSessionTmp = array("sessionId" => $item["id"], "organizationId" => $item["organization_id"], "data" => $sharedS, "sharedSessionKey"=> implode("", $sharedS));

                file_put_contents("gitignore/sharedSessions", print_r($sharedSessionTmp, true), FILE_APPEND);

                $sharedSession[] = $sharedSessionTmp;

                continue;
            }

        }
        //die();
++$i;
        var_dump(($stAll-$i));
    }

    file_put_contents("gitignore/sharedSessions", serialize($sharedSession), FILE_APPEND);
    return $sharedSession;
}

function prepareDataSharedSession($url)
{

    $base = downloadPage(DZ_URL .$url);
    $content = str_get_html($base);

    $tableTr = $content->find('.form table tr');
    foreach ($tableTr as $item) {

        //var_dump($item->text());

        if(stripos($item->text(), 'Datum:') !== false) {
            $t = $item->find(".outputText", 0);
            $datum = html_entity_decode(trim($t->text()));
        }

        if(stripos($item->text(), 'Ura:') !== false) {
            $t = $item->find(".outputText", 0);
            $ura = html_entity_decode(trim($t->text()));
        }

        if(stripos($item->text(), 'Prostor') !== false) {
            $t = $item->find(".outputText", 0);
            $prostor = trim($t->text());
        }
    }

    return array($datum, $ura, $prostor);

}


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

/*
 * //DROP TABLE parladata_tmp_votelinks;
CREATE TABLE parladata_tmp_votelinks
(
    id INTEGER PRIMARY KEY NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL,
    session_id VARCHAR(255),
    gov_id VARCHAR(255),
    votedoc_url VARCHAR(255)
);
CREATE SEQUENCE parladata_tmp_votelinks_id_seq NO MINVALUE NO MAXVALUE NO CYCLE;
ALTER TABLE parladata_tmp_votelinks ALTER COLUMN id SET DEFAULT nextval('parladata_tmp_votelinks_id_seq');
ALTER SEQUENCE parladata_tmp_votelinks_id_seq OWNED BY parladata_tmp_votelinks.id;
GRANT ALL PRIVILEGES ON TABLE parladata_tmp_votelinks TO parladaddy;


 */

function voteLinkExists($session_id, $gov_id, $url)
{
    global $conn;

    $sql = "
    select * from parladata_tmp_votelinks
    where 
		session_id = '" . $session_id . "' AND
		gov_id = '" . pg_escape_string ($conn, $gov_id) . "' AND
		votedoc_url = '" . pg_escape_string ($conn, $url) . "'
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
function voteLinkInsert($session_id, $gov_id, $url)
{
    global $conn;

    $sql = "
			INSERT INTO
				parladata_tmp_votelinks
			(created_at, updated_at, session_id, gov_id, votedoc_url)
			VALUES
			(NOW(), NOW(), '" . $session_id . "', '" . pg_escape_string ($conn, $gov_id) . "', '" . pg_escape_string ($conn, $url) . "')
			RETURNING id
    ";

    $voting_id = 0;
    $result = pg_query ($conn, $sql);
    if (pg_affected_rows ($result) > 0) {
        $insert_row = pg_fetch_row($result);
        $voting_id = $insert_row[0];
    }
    return $voting_id;
}