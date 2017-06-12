<?php
require 'vendor/autoload.php';
include_once('inc/config.php');

// Get people array
$people = getPeople();
$people_new = array();

/*
$m1 = file("gitignore/ms_sessionsmissing.html");
$fp1 = fopen('gitignore/ms_sessionsmissing_.csv', 'a');
foreach ($m1 as $msession) {
    $data = getSessionById($msession);
    fwrite($fp1, $data["id"] .',' .'"'. $data["name"] .'"'. ",". '"'. "http://www.dz-rs.si" . htmlspecialchars_decode($data['gov_id']) .'"' ."\r\n");
}
fclose($fp1);


$m1 = file("gitignore/ms_sessionsnospeech.html");
$fp1 = fopen('gitignore/ms_sessionsnospeech_.csv', 'a');
foreach ($m1 as $msession) {
    $data = getSessionById($msession);
    fwrite($fp1, $data["id"] .',' .'"'. $data["name"] .'"'. ",". '"'. "http://www.dz-rs.si" . htmlspecialchars_decode($data['gov_id']) .'"' ."\r\n");
}
fclose($fp1);

die();
*/

$missing_session = array(
    5625,
    5801,
    5983,
    6051,
    6092,
    6096,
    6124,
    6158,
    5746,
    6297,
    6363,
    5791,
    5784,
    5792,
    5789,
    5806,
    5817,
    5811,
    5831,
    5864,
    5921,
    5926,
    5931,
    5942,
    5946,
    5952,
    5957,
    5965,
    5968,
    6030,
    6052,
    6083,
    6061,
    6120,
    6100,
    6118,
    9179,
    6128,
    6127,
    6168,
    6160,
    6159,
    6300,
    6445,
    6422,
    6474,
    6477,
    7612,
    7613,
    7614,
    7615,
    7616,
    7617,
    7618,
    7619,
    7620,
    7621,
    7622,
    7623,
    6493,
    7624,
    7625,
    7626,
    7627,
    7628,
    7630,
    6669,
    6668,
    6665,
    6664,
    6663,
    6662,
    6666,
    6667,
    7632,
    6661,
    7633,
    7634,
    7635,
    6694,
    6693,
    6673,
    6672,
    6671,
    6691,
    7636,
    7637,
    7638,
    7640,
    7641,
    7643,
    7644,
    6692,
    7416,
    6690,
    6689,
    6688,
    6695,
    6687,
    6686,
    7645,
    8910,
    8911,
    8912,
    6660,
    6670,
    5574,
    7650,
    7651,
    7653,
    6121,
    6303,
    6108,
    8915,
    8914,
    8917,
    6139,
    6560,
    5750,
    8921,
    8922,
    6129,
    6138,
    6086,
    6078,
    6073,
    6074,
    5581,
    5749,
    8942,
    8943,
    8945,
    8946,
    8947,
    8948,
    8950,
    8951,
    8952,
    8954,
    8955,
    8956,
    8957,
    8958,
    8959,
    8960,
    8961,
    8962,
    8963,
    8964,
    8965,
    8966,
    8967,
    8968,
    8969,
    8970,
    8971,
    8974,
    8975,
    8976,
    8978,
    8979,
    8981,
    8983,
    8984,
    9003,
    9322,
    9323,
    9324,
    9325,
    9326,
    9327,
    9328,
    9329,
    9330,
    9331,
    9332,
    9333,
    9334,
    9335,
    9337,
    9338,
    9339,
    9340,
    9341,
    9342,
    9343,
    9344,
    9345,
    9347,
    9348,
    9349,
    9350,
    9351,
    9352,
    9353,
    9354,
    9355,
    9356,
    9357,
    9359,
    9360,
    9361,
    9362,
    9363,
    9364,
    9365,
    9366,
    9367,
    9368,
    9369,
    9370,
    9371,
    9372,
    9373,
    9374,
    9375,
    9376,
    9377,
    9381,
    9382,
    9383,
    9385,
    9386,
    9387,
    9388,
    9121,
    9147,
    9148,
    9149,
    9151,
    9152,
    9153,
    9154,
    9156,
    9157,
    9159
);


foreach ($missing_session as $msession) {
    $data = getSessionById($msession);
    $content = file_get_contents('http://www.dz-rs.si' . htmlspecialchars_decode($data['gov_id']));
    parseSessionsMissingSpeech($content, $data['organization_id'], $data);
}


function parseSessionsMissingSpeech($content, $organization_id, $sessionData)
{

    $session = str_get_html($content);

    $session_name = $sessionData['name'];
    $session_link = $sessionData['gov_id'];
    $session_nouid = $sessionData['gov_id'];

    var_dumpp($sessionData);
    $date = new DateTime($sessionData['start_time']);
    $session_date = $date->format('Y-m-d');

    global $http_response_header;

    $tmp = array(
        'name' => trim($session_name),
        'link' => trim($session_link),
        'link_noid' => trim($session_nouid),
        'date' => trim($session_date),
        'review' => false,
        'review_ext' => false
    );

    if ($date > new DateTime('NOW')) {
        //no go
        return false;
    }

    if (sessionDeleted($session_nouid)) {
        return false;
    }

    $tmp['id'] = $sessionData['id']; // Set that session exists

    // Parse data
    $tmp['speeches'] = array();
    $k = 0;
    var_dump("PARSE_SPEECHES");
    if ($session->find('td.vaTop', 3)) {
        $sptable = $session->find('td.vaTop', 3)->find('a.outputLink');

        if (!empty ($sptable)) {
            $parseSpeeches = array();
            foreach ($sptable as $speeches) {
                $in_review = (bool)(stripos($speeches->innerText(), "pregled") !== false);
                if ($in_review) $tmp['review'] = true;

                $datum = '';
                if (preg_match('/(\d{2}\.\d{2}\.\d{4})/is', $speeches->innerText(), $matches)) {
                    $datum = DateTime::createFromFormat('d.m.Y', $matches[1])->format('Y-m-d');
                }

                //check here
                $parseSpeeches[$k]['dateStart'] = $datum;
                $parseSpeeches[$k]['url'] = DZ_URL . $speeches->href;
                $parseSpeeches[$k]['in_review'] = $tmp['review'];
                $parseSpeeches[$k]['sessionId'] = $tmp['id'];

                $k++;


            }
            if (count($parseSpeeches) > 0) {
                foreach ($parseSpeeches as $parseSpeeche) {
                    var_dumpp($parseSpeeche);
                    if (!$parseSpeeche['sessionId']) {

                        $speech = parseSpeeches($parseSpeeche['url'], $parseSpeeche['dateStart']);
                        $tmp['speeches'][$speech['datum']] = $speech;
                        $tmp['speeches'][$speech['datum']]['review'] = $parseSpeeche['in_review'];
                        $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speechinreview';
                        sleep(FETCH_TIMEOUT);

                    } else {


                        $speech = parseSpeeches($parseSpeeche['url'], $parseSpeeche['dateStart']);
                        $tmp['speeches'][$speech['datum']] = $speech;
                        $tmp['speeches'][$speech['datum']]['review'] = $parseSpeeche['in_review'];
                        sleep(FETCH_TIMEOUT);

                        if(isSpeechInReviewStatusChanged($parseSpeeche['sessionId'], $parseSpeeche['in_review'])){
                            $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speech';
                        }else{
                            $tmp['speeches'][$speech['datum']]['insertToDb'] = 'parladata_speechinreview';
                        }

                    }
                }
                //var_dumpp($tmp['speeches'][$speech['datum']]);
            }

            file_put_contents("gitignore/ms_" . $sessionData['id'] . ".txt", print_r($parseSpeeches, true));
            file_put_contents("gitignore/mss_" . $sessionData['id'] . ".txt", serialize($parseSpeeches));
        }else{


        }
    }

    $tmp['documents'] = array();
    $tmp['voting'] = array();
    $tmp['votingDocument'] = array();

    saveSession($tmp, $organization_id);
    var_dumpp("SAVE:");
    var_dumpp($organization_id);

}