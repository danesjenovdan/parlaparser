<?php
function prepareEpaDataLocal($epa, $zakon)
{
    $epaData = getEpaDataInXml($epa);
    $zakonArray[] = array($zakon, $epa, $epaData);
    return $zakonArray;
}

function prepareEpaDataApi($epa, $zakon)
{

}

function getLawsFromSession($sessionId)
{
    global $conn;
    $sql = "
		SELECT
			*
		FROM
			parladata_session
	        where organization_id = 95
	        and id = $sessionId
	";
    $sessions = array();
    $result = pg_query($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $sessions[] = $row;
        }
    }
    $sharedS = array();
    foreach ($sessions as $session) {
        $url = html_entity_decode($session["gov_id"]);

        $base = downloadPage(DZ_URL . $url);
        $content = str_get_html($base);

        $ullistlis = $content->find('ul.listNoBtn li');
        foreach ($ullistlis as $ullistli) {

            $sklicSeje = $ullistli->text();

            if (stripos($sklicSeje, "sklic seje") !== false) {
                $urlullistli = $ullistli->find("a", 0);
                $urlSklic = $urlullistli->getAttribute("href");

                $uid = getUIDFromLink($urlSklic);

                $sharedS[] = array("data" => prepareDataZakonodaja($urlSklic, $session["id"]), "uid" => $uid);
                continue;
            }
        }
    }

    return $sharedS;
}

function sendEpaToJuric($sessionId, $uid, $dataZ)
{
    $base_url = DATA_API_URL;
    $apiUsername = DATA_API_USERNAME;
    $apiPassword = DATA_API_PASSWORD;
    $auth = array($apiUsername, $apiPassword, "basic");
    $client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));
    $parserApi = new parserApi($client);
    $what = 'law/';

    foreach ($dataZ as $item) {

        $karticaEpa = $item[2];
        $zakonTitle = $item[0];


        foreach ($karticaEpa as $kartica) {

            if (empty($kartica["date"])) {
                $d = new DateTime();
            } else {
                $d = new DateTime($kartica["date"]);
            }

            $procedure = (empty($kartica["procedure"])) ? "" : $kartica["procedure"];
            $proposer_text = (empty($kartica["proposer_text"])) ? "" : $kartica["proposer_text"];
            $procedure_phase = (empty($kartica["procedure_phase"])) ? "" : $kartica["procedure_phase"];
            $mdt = (empty($kartica["mdt"])) ? "" : $kartica["mdt"];

            $form_params = array(
                'session' => $sessionId,
                'uid' => $uid,
                'epa' => $kartica["epa"],
                'text' => $kartica["naziv"],
                //'text' => $zakonTitle,
                'proposer_text' => $proposer_text,
                'procedure_phase' => $procedure_phase,
                'procedure' => $procedure,
                'mdt' => $mdt,
                'type_of_law' => $kartica["type_of_law"],
                'classification' => $kartica["classification"],
                'procedure_ended' => $kartica["procedure_ended"],
                'status' => "",
                'note' => "",
                'date' => $d->format("Y-m-d H:i:s")
            );

            $data = array(
                'debug' => false,
                'form_params' => $form_params
            );

            $parserApi->apiCreate($what, $data);
        }
    }
}

function getLawsFromApi()
{
    $base_url = DATA_API_URL;
    $apiUsername = DATA_API_USERNAME;
    $apiPassword = DATA_API_PASSWORD;
    $auth = array($apiUsername, $apiPassword, "basic");
    $client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));
    $parserApi = new parserApi($client);
    $what = 'law/';

    $parserApi->apiGetList($what);
    $data = $parserApi->getResponseJsonDecoded();
    var_dumpp($parserApi);
    // MUKI

    $allData = array();
    foreach ($data["results"] as $item) {
        $allData[] = $item;
    }

    do {
        $what = substr($data["next"], strrpos($data["next"], "law/"), strlen($data["next"]));

        $parserApi->apiGetList($what);
        $data = $parserApi->getResponseJsonDecoded();

        foreach ($data["results"] as $item) {
            $allData[] = $item;
        }
    } while ($data["next"] != null);

    return $allData;
}

function getLawsFromApiSingle($sessionId)
{
    $base_url = DATA_API_URL;
    $apiUsername = DATA_API_USERNAME;
    $apiPassword = DATA_API_PASSWORD;
    $auth = array($apiUsername, $apiPassword, "basic");
    $client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));
    $parserApi = new parserApi($client);
    $what = 'law/?session=' . $sessionId;

    $parserApi->apiGetList($what);
    $data = $parserApi->getResponseJsonDecoded();

    //var_dumpp($data);
    return $data;
}

function getLawsFromApiByEpa($epa)
{
    $base_url = DATA_API_URL;
    $apiUsername = DATA_API_USERNAME;
    $apiPassword = DATA_API_PASSWORD;
    $auth = array($apiUsername, $apiPassword, "basic");
    $client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));
    $parserApi = new parserApi($client);
    $what = 'law/?epa=' . $epa;

    $parserApi->apiGetList($what);
    $data = $parserApi->getResponseJsonDecoded();

    //var_dumpp($data);
    return $data;
}

function getLawsAllActiveParladataEpas($sessionId = 0)
{
    $base_url = DATA_API_URL;
    $apiUsername = DATA_API_USERNAME;
    $apiPassword = DATA_API_PASSWORD;
    $auth = array($apiUsername, $apiPassword, "basic");
    $client = new GuzzleHttp\Client(array('base_uri' => $base_url, "auth" => $auth));
    $parserApi = new parserApi($client);
    $what = ($sessionId>0)? 'allActiveEpas/?session='.$sessionId : 'allActiveEpas';
    $whatReplace = ($sessionId>0)? 'allActiveEpas/?session='.$sessionId : 'allActiveEpas';

    $parserApi->apiGetList($what);
    $data = $parserApi->getResponseJsonDecoded();

    $allData = array();
    foreach ($data["results"] as $item) {
        $allData[] = $item;
    }

    do {
        if(is_null($data["next"])){
            continue;
        }
        $what = substr($data["next"], strrpos($data["next"], $whatReplace), strlen($data["next"]));

        $parserApi->apiGetList($what);
        $data = $parserApi->getResponseJsonDecoded();

        foreach ($data["results"] as $item) {
            $allData[] = $item;
        }
    } while ($data["next"] != null);

    return $allData;
}

function getLawsAllActiveEpas()
{
//    $url = "http://analize.knedl.si/v1/s/allActiveEpas/";
//    $data = file_get_html($url);

    $url = ANALIZE_API_URL . "s/allActiveEpas/";


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $data = curl_exec($ch);
    curl_close($ch);

    $epas = json_decode($data);
    return $epas;

    $arrContextOptions=array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
'header' => "User-Agent:MyAgent/1.0\r\n"
    );

    $data = file_get_contents($url, false, stream_context_create($arrContextOptions));
echo "analize: " . ($url);
    $epas = json_decode($data);
    return $epas;
}


use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

function getSendLawToApi($sessionId)
{

    $dataLaws = getLawsFromSession($sessionId);

    if (count($dataLaws) > 0) {
        $existingLaws = getLawsFromApiSingle($sessionId);
        foreach ($dataLaws as $dataLaw) {

            $uid = $dataLaw["uid"];
            foreach ($dataLaw["data"] as $item) {

                $sessionfound = false;
                $lawfound = false;
                foreach ($existingLaws as $existingLaw) {
                    if ($existingLaw["session"] == $sessionId) {
                        $sessionfound = true;

                        if ($existingLaw["epa"] == $item[1]) {
                            $lawfound = true;
                        }
                    }
                }

                if (!$lawfound) {
                    sendEpaToJuric($sessionId, $uid, $item);
                    var_dumpp("sending...");
                }


            }
        }
    }

}


function zakonodajaBySession($sessionId)
{

    global $conn;

    $motionsSql = "select * from parladata_motion where epa != '' and session_id = $sessionId";
    $motions = array();
    $result = pg_query($conn, $motionsSql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $motions[] = $row;
        }
    }
    foreach ($motions as $motion) {

        var_dumpp("session: " . $motion["session_id"]);
        var_dumpp("motion: " . $motion["id"]);

        $uid = 0;
        $epa = trim($motion["epa"]);
        $zakon = $motion["text"];

        $preparedEpaData = prepareEpaDataLocal($epa, $zakon);

        //compare with API !! data

        foreach ($preparedEpaData as $epaDatasSession) {

            foreach ($epaDatasSession["data"] as $sessionData) {

                $zakonTitle = $sessionData[0];
                $epa = $sessionData[1];
                $apiData = getLawsFromApiByEpa($epa);

                $apiDataResults = $apiData["results"];
                $sessionDataResults = $sessionData[2];

                if ((count($apiDataResults) < 1) && (count($sessionDataResults) > 0)) {
                    //send NEW data
                    //die("send NEW data");
                    foreach ($sessionDataResults as $sessionDataResult) {
                        sendEpaToJuric($sessionId, $uid, $sessionDataResult);
                    }
                }

                if ((count($apiDataResults)) == (count($sessionDataResults))) {
                    // same count, ignore result
                }

                if ((count($apiDataResults)) > (count($sessionDataResults))) {
                    // error ???
                    sendReport("za epo $epa so problemi, sparsanih je manj kot v apiju");
                }

                if ((count($apiDataResults)) < (count($sessionDataResults))) {

                    foreach ($sessionDataResults as $sessionDataResult) {

                        $lawfound = false;
                        foreach ($apiDataResults as $result) {

                            $result_date = new DateTime($result["date"]);
                            $sessionDataResult_date = new DateTime($sessionDataResult["date"]);

                            if (
                                ($sessionDataResult["procedure_phase"] == $result["procedure_phase"]) &&
                                ($sessionDataResult["type_of_law"] == $result["type_of_law"]) &&
                                ($sessionDataResult_date->format("Y-m-d") == $result_date->format("Y-m-d"))
                            ) {
                                $lawfound = true;
                            }

                        }

                        if (!$lawfound) {
                            //send item
                            //die("send item");
                            var_dumpp("send item");
                            var_dumpp($sessionDataResult);
                            sendEpaToJuric($sessionId, $uid, array(array($sessionDataResult["naziv"], $epa, array($sessionDataResult))));
                        }
                    }

                }

            }
        }


    }
}

function zakonodajaByMotion($motionId)
{

    global $conn;

    $motionsSql = "select * from parladata_motion where epa != '' and id = " . $motionId;
    $motions = array();
    $result = pg_query($conn, $motionsSql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $motions[] = $row;
        }
    }
    foreach ($motions as $motion) {

        var_dumpp("session: " . $motion["session_id"]);
        var_dumpp("motion: " . $motion["id"]);

        $uid = 0;
        $epa = trim($motion["epa"]);
        $zakon = $motion["text"];

        $preparedEpaData = prepareEpaDataLocal($epa, $zakon);
        $preparedEpaDataApi = prepareEpaDataApi($epa, $zakon);

        if (isEpaDataValid()) {
            sendEpaToJuric($motion["session_id"], $uid, $preparedEpaData);
        }

    }

}

function compareEpaData($localData, $apiData)
{
    $status = "DEFAULT";

    if (count($localData) == $apiData["count"]) {
        $status = "EQUAL";
    }

    if (count($localData) < $apiData["count"]) {
        $status = "LOCAL_LESS_THAN_API";
    }

    if (
    (count($localData) > $apiData["count"])
    ) {
        $status = "LOCAL_MORE_THAN_API_DIFF";
    }

    if (
        (count($localData) > 0) &&
        (($apiData["count"]) < 1)
    ) {
        $status = "LOCAL_MORE_THAN_API_NONE";
    }

    return $status;
}


function history()
{

    global $conn;
    $sql = " select DISTINCT epa, session_id from parladata_motion WHERE epa != '' ORDER BY session_id ASC ";
    $epas = array();
    $result = pg_query($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $epas[] = $row;
        }
    }

    $st = 0;
    $stepas = count($epas);

    foreach ($epas as $epa) {

        $motionsSql = "select * from parladata_motion where epa != '' and epa = '" . $epa["epa"] . "'";
        $motions = array();
        $result = pg_query($conn, $motionsSql);
        if ($result) {
            while ($row = pg_fetch_assoc($result)) {
                $motions[] = $row;
            }
        }

        $motion = $motions[0];
//            var_dumpp("session: " . $motion["session_id"]);
//            var_dumpp("motion: " . $motion["id"]);

        $uid = 0;
        $epa = trim($motion["epa"]);
        $zakon = $motion["text"];

        $preparedEpaData = prepareEpaDataLocal($epa, $zakon);

        if (count($preparedEpaData) > 0) {
            sendEpaToJuric($motion["session_id"], $uid, $preparedEpaData);
            echo $stepas."/".$st."\n";
        }

        $st++;
    }

}


$docXml = array();
$docXml[] = 'xml/PZ.XML';
$docXml[] = 'xml/PZ8.XML';
$docXml[] = 'xml/SZ.XML';
$docXml[] = 'xml/PA.XML';
$docXml[] = 'xml/PA8.XML';
$docXml[] = 'xml/SA.XML';
$docXml[] = 'xml/PB.XML';

function updateXmlsForLawFunctions()
{
    global $docXml;

    foreach ($docXml as $xmlItem) {

        $parts = explode("/", $xmlItem);
        $filecontent = file_get_contents('https://fotogalerija.dz-rs.si/datoteke/opendata/' . $parts[1]);
        file_put_contents($xmlItem, $filecontent);

        var_dumpp($parts);
    }
}


function getEpaDataInXml($epa)
{
    $epaForXmls = str_pad($epa, 8, '0', STR_PAD_LEFT);

    global $docXml;

    global $all_procedure_phase;
    global $all_type_of_law;

    $items = array();
    //var_dumpp($epa);

//    $fillMe = array();
//    $fillMe["proposer_text"] = ""; //KARTICA_PREDLAGATELJ
//    $fillMe["procedure_phase"] = ""; //KARTICA_FAZA_POSTOPKA
//    $fillMe["procedure"] = "";//KARTICA_POSTOPEK
//    $fillMe["mdt"] = "";//KARTICA_DELOVNA_TELESA
//    $fillMe["type_of_law"] = "";//KARTICA_VRSTA
//
//    $fillMe["epa"] = "";//epa
//    $fillMe["naziv"] = "";//KARTICA_NAZIV
//
//    $fillMe["date"] = ""; //datum ? KARTICA_SOP KARTICA_DATUM
//
//    $fillMe["classification"] = ""; // 	... akt ali zakon ali je fajl PA*.xml   ali PZ* xml
//    $fillMe["procedure_ended"] = ""; //	... ali je zaključen ali ne .. ali je PA8.xml / PZ8.xml

    /*
    [x] predlagatelj proposer_text PARSER
    [x] faza postopka procedure_phase PARSER
    [x] postopek procedure PARSER
    [x] mdt PARSER
    [x] vrsta type_of_law PARSER
    */

    foreach ($docXml as $xmlItem) {

        // var_dumpp($xmlItem);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlItem);
        if ($xml === false) {
            echo "Failed loading XML\n";
            foreach (libxml_get_errors() as $error) {
                echo "\t", $error->message;
            }
            continue;
        }

        $classification = "";
        if (stripos($xmlItem, "PA") !== false) {
            $classification = "akt";
        }
        if (stripos($xmlItem, "PZ") !== false) {
            $classification = "zakon";
        }
        if (stripos($xmlItem, "SA") !== false) {
            $classification = "akt";
        }
        if (stripos($xmlItem, "SZ") !== false) {
            $classification = "zakon";
        }

        $procedure_ended = 0;
        if (stripos($xmlItem, "SZ") !== false) {
            $procedure_ended = 1;
        }
        if (stripos($xmlItem, "SA") !== false) {
            $procedure_ended = 1;
        }

        foreach ($xml->DOKUMENT as $dokument) {
            if (stripos($dokument->KARTICA_DOKUMENTA->KARTICA_EPA, $epaForXmls) !== false) {

                $fillMe = array();
                $fillMe["epa"] = $epa;
                $fillMe["naziv"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_NAZIV;

                if (!empty((string)$dokument->KARTICA_DOKUMENTA->KARTICA_PREDLAGATELJ)) {
                    $fillMe["proposer_text"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_PREDLAGATELJ;
                }
                if (!empty((string)$dokument->KARTICA_DOKUMENTA->KARTICA_FAZA_POSTOPKA)) {
                    $fillMe["procedure_phase"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_FAZA_POSTOPKA;
                    $all_procedure_phase[] = $fillMe["procedure_phase"];
                }
                if (!empty((string)$dokument->KARTICA_DOKUMENTA->KARTICA_POSTOPEK)) {
                    $fillMe["procedure"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_POSTOPEK;
                }
                if (!empty((string)$dokument->KARTICA_DOKUMENTA->KARTICA_DELOVNA_TELESA)) {
                    $fillMe["mdt"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_DELOVNA_TELESA;
                }
                if (!empty((string)$dokument->KARTICA_DOKUMENTA->KARTICA_VRSTA)) {
                    $fillMe["type_of_law"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_VRSTA;
                    $all_type_of_law[] = $fillMe["type_of_law"];
                }
                if (!empty((string)$dokument->KARTICA_DOKUMENTA->KARTICA_DATUM)) {
                    $fillMe["date"] = (string)$dokument->KARTICA_DOKUMENTA->KARTICA_DATUM;
                    if (empty($fillMe["date"])) {
                        $fillMe["date"] = "1900-01-01";
                    }
                }

                $fillMe["classification"] = $classification;
                $fillMe["procedure_ended"] = $procedure_ended;

                if (!empty($fillMe["proposer_text"])) {
                    $items[] = $fillMe;
                }

            }
        }
        foreach ($xml->PREDPIS as $dokument) {
            if (stripos($dokument->KARTICA_PREDPISA->KARTICA_EPA, $epaForXmls) !== false) {

                $fillMe = array();
                $fillMe["epa"] = $epa;
                $fillMe["naziv"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_NAZIV;

                if (!empty((string)$dokument->KARTICA_PREDPISA->KARTICA_PREDLAGATELJ)) {
                    $fillMe["proposer_text"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_PREDLAGATELJ;
                }
                if (!empty((string)$dokument->KARTICA_PREDPISA->KARTICA_FAZA_POSTOPKA)) {
                    $fillMe["procedure_phase"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_FAZA_POSTOPKA;
                    $all_procedure_phase[] = $fillMe["procedure_phase"];
                }
                if (!empty((string)$dokument->KARTICA_PREDPISA->KARTICA_POSTOPEK)) {
                    $fillMe["procedure"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_POSTOPEK;
                }
                if (!empty((string)$dokument->KARTICA_PREDPISA->KARTICA_DELOVNA_TELESA)) {
                    $fillMe["mdt"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_DELOVNA_TELESA;
                }
                if (!empty((string)$dokument->KARTICA_PREDPISA->KARTICA_VRSTA)) {
                    $fillMe["type_of_law"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_VRSTA;
                    $all_type_of_law[] = $fillMe["type_of_law"];
                }
                if (!empty((string)$dokument->KARTICA_PREDPISA->KARTICA_DATUM)) {
                    $fillMe["date"] = (string)$dokument->KARTICA_PREDPISA->KARTICA_DATUM;
                    if (empty($fillMe["date"])) {
                        $fillMe["date"] = "1900-01-01";
                    }
                }

                $fillMe["classification"] = $classification;
                $fillMe["procedure_ended"] = $procedure_ended;

                $items[] = $fillMe;

            }
        }
        foreach ($xml->OBRAVNAVA_PREDPISA as $dokument) {
            if (stripos($dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_EPA, $epaForXmls) !== false) {

                $fillMe = array();
                $fillMe["epa"] = $epa;
                $fillMe["naziv"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_NAZIV;

                if (!empty((string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_PREDLAGATELJ)) {
                    $fillMe["proposer_text"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_PREDLAGATELJ;
                }
                if (!empty((string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_FAZA_POSTOPKA)) {
                    $fillMe["procedure_phase"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_FAZA_POSTOPKA;
                    $all_procedure_phase[] = $fillMe["procedure_phase"];
                }
                if (!empty((string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_POSTOPEK)) {
                    $fillMe["procedure"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_POSTOPEK;
                }
                if (!empty((string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_DELOVNA_TELESA)) {
                    $fillMe["mdt"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_DELOVNA_TELESA;
                }
                if (!empty((string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_VRSTA)) {
                    $fillMe["type_of_law"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_VRSTA;
                    $all_type_of_law[] = $fillMe["type_of_law"];
                }
                if (!empty((string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_DATUM)) {
                    $fillMe["date"] = (string)$dokument->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_DATUM;
                    if (empty($fillMe["date"])) {
                        $fillMe["date"] = "1900-01-01";
                    }
                }

                $fillMe["classification"] = $classification;
                $fillMe["procedure_ended"] = $procedure_ended;

                $items[] = $fillMe;

            }
        }
    }

    return $items;


}

function checkIfSessionsEpaExists($sessionId, $epaDatasSessions)
{

    foreach ($epaDatasSessions as $epaDatasSession) {

        $uid = $epaDatasSession["uid"];

        foreach ($epaDatasSession["data"] as $sessionData) {

            $zakonTitle = $sessionData[0];
            $epa = $sessionData[1];
            $apiData = getLawsFromApiByEpa($epa);

            $apiDataResults = $apiData["results"];
            $sessionDataResults = $sessionData[2];

            if ((count($apiDataResults) < 1) && (count($sessionDataResults) > 0)) {
                //send NEW data
                //die("send NEW data");
                foreach ($sessionDataResults as $sessionDataResult) {
                    sendEpaToJuric($sessionId, $uid, $sessionDataResult);
                }
            }

            if ((count($apiDataResults)) == (count($sessionDataResults))) {
                // same count, ignore result
            }

            if ((count($apiDataResults)) > (count($sessionDataResults))) {
                // error ???
                sendReport("za epo $epa so problemi, sparsanih je manj kot v apiju");
            }

            if ((count($apiDataResults)) < (count($sessionDataResults))) {

                foreach ($sessionDataResults as $sessionDataResult) {

                    $lawfound = false;
                    foreach ($apiDataResults as $result) {

                        $result_date = new DateTime($result["date"]);
                        $sessionDataResult_date = new DateTime($sessionDataResult["date"]);

                        if (
                            ($sessionDataResult["procedure_phase"] == $result["procedure_phase"]) &&
                            ($sessionDataResult["type_of_law"] == $result["type_of_law"]) &&
                            ($sessionDataResult_date->format("Y-m-d") == $result_date->format("Y-m-d"))
                        ) {
                            $lawfound = true;
                        }

                    }

                    if (!$lawfound) {
                        //send item
                        //die("send item");
                        var_dumpp("send item");
                        var_dumpp($sessionDataResult);
                        sendEpaToJuric($sessionId, $uid, array(array($sessionDataResult["naziv"], $epa, array($sessionDataResult))));
                    }
                }

            }

        }
    }
}
