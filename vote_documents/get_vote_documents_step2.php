<?php


require 'vendor/autoload.php';
include_once('inc/config.php');

// Get people array
$people = getPeople();
$people_new = array();


$docList = getTmpVotesLinkForDocumentsList();
foreach ($docList as $item) {

    $data = parseSessionsSingleDataForDoc($item);
    $updatedId = updateTmpVotesLinkForDocumentsSingle($item["id"], $data);
    var_dump($updatedId);

}



function parseSessionsSingleDataForDoc($data)
{

    $content = file_get_contents(htmlspecialchars_decode($data['vote_link']));
    $data = str_get_html($content);
    $array = array();


    $info = $data->find('.panelGrid', 0)->find('tr');
    foreach ($info as $row) {
        foreach ($row->find('td') as $td) {
            $tdinfo = trim($td->text());

            if (strtolower($tdinfo) == 'naslov:') {
                $array['naslov'] = html_entity_decode(trim($td->next_sibling()->text()));
            }
            if (strtolower($tdinfo) == 'dokument:') {
                $array['dokument'] = html_entity_decode(trim($td->next_sibling()->text()));
            }
            if (strtolower($tdinfo) == 'glasovanje dne:' && preg_match('/([0-9\.]{10}).*?([0-9\:]{8})/is', $td->next_sibling()->text(), $tmp)) {
                $array['date'] = DateTime::createFromFormat('d.m.Y', $tmp[1])->format('Y-m-d');
                $array['time'] = $tmp[2];
            }
            if (strtolower($tdinfo) == 'epa:') {
                $array['epa'] = $td->next_sibling()->text();
                $array['epa_link'] = $td->next_sibling()->find('a', 0)->href;
            }
            if (strtolower($tdinfo) == 'faza postopka:') {
                $array['faza'] = $td->next_sibling()->text();
            }
            if (strtolower($tdinfo) == 'za:') {
                $array['za'] = $td->next_sibling()->text();
            }
            if (strtolower($tdinfo) == 'proti:') {
                $array['proti'] = $td->next_sibling()->text();
            }
            if (strtolower($tdinfo) == 'kvorum:') {
                $array['kvorum'] = $td->next_sibling()->text();
            }
        }
    }

    return $array;


}


function getTmpVotesLinkForDocumentsList(){
    global $conn;

    $array = array ();
    $sql = "
		SELECT
			*
		FROM
			parladata_tmpvoteslinkdocuments
			order by id asc
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row =   pg_fetch_assoc($result)) {
            $array[] = $row;
        }
    }
    return $array;

}

function updateTmpVotesLinkForDocumentsSingle($id, $data){
    global $conn;

    $dokument2 = $data["dokument"];
    $naslov2 = $data["naslov"];

    $sql = "
    UPDATE parladata_tmpvoteslinkdocuments set dokument2 = '" . pg_escape_string($conn, $dokument2) . "',
     naslov2 = '" . pg_escape_string($conn, $naslov2) . "'
     WHERE id = $id
				";

var_dump($sql);

    $result = pg_query($conn, $sql);
    $link_id = 0;
    if (pg_affected_rows($result) > 0) {
        $insert_row = pg_fetch_row($result);
        $link_id = $insert_row[0];

    }

    return $link_id;
}