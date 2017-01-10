<?php

//require 'vendor/autoload.php';
include_once('inc/config.php');

error_reporting(E_ERROR | E_WARNING);


/*
http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/!ut/p/z1/04_Sj9CPykssy0xPLMnMz0vMAfIjo8zivT39gy2dDB0N3F0NXQw8DX09PTz9HI0MTMz1wwkpiAJKG-AAjgb6BbmhigA0Oc23/dz/d5/L2dBISEvZ0FBIS9nQSEh/
http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/!ut/p/z1/lU-9CsIwGHwWnyD3JTUlY4o1pmkMaIs2i2SSglYH8fnNbFH0tuP-OBbZkcUpPcdzeoy3KV0yH6I8ORv2qiINU9MKlrzd2K3mKEp2eDPsCp4NTnWtbwlGspjlRjndOEMIJCvYpRGqrAnALB8UrWF7YQrhO8Dz3_L4AI0_9-cH4_f6-7XP0IsXnBSMsA!!/dz/d5/L2dBISEvZ0FBIS9nQSEh/


http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/


*/

// 0. open list
// 1. parse base
// 2. parse  // Izbrano poslansko vprašanje / pobuda
// 3. parse // Poslanska vprašanja in pobude
//    3.1 parse Datum, Avtor, Dokument

function countSessionsQuestions($urls, $selSeja)
{
    global $http_response_header;

    foreach ($urls as $url) {

        //	Get main page
        $base = downloadPage($url);

        //	Retrieve cookies
        $cookiess = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $s) {
                if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts))
                    $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
            }
        }
        $cookiess = substr($cookiess, 0, -2);


        preg_match('/form id="(.*?):form1"/', $base, $fmatches);
        $form_id = $fmatches[1];

        preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $base, $matches);
        preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $base, $matchess);


        $postdata = http_build_query(
            array(
                //$form_id . ':form1' => $form_id . ':form1',
                //$form_id . ':form1:menu1' => CURRENT_SESSION,

                $form_id . ':form1:vprPobude1:goto1__pagerGoButton.x' => 1,
                $form_id . ':form1:vprPobude1:goto1__pagerGoText' => 1,

                $form_id . ':form1:selMandati:' => 'ZP\\POS_VPR.NSF',
                $form_id . ':form1:selAvtorji:' => '',
                $form_id . ':form1:nazivFilter:' => '',
                $form_id . ':form1:naslovljenecFilter:' => '',
                $form_id . ':form1:selSeja:' => $selSeja,
                $form_id . ':form1_SUBMIT' => 1,
                $form_id . ':form1:cbFilter' => "IŠČI",
                'javax.faces.ViewState' => $matchess[1]
            ,'javax.faces.encodedURL' => '/wps/PA_DZ-LN-VnjaInPobude/portlet/VprasanjaInPobudeView.jsp'
            )
        );
        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Cookie: ' . $cookiess . "\r\n" . 'Content-type: application/x-www-form-urlencoded',
                'timeout' => 2,
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);
        if ($subpageContent = downloadPage(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', $context)) {


            preg_match('/Page 1 of (\d+)/i', $subpageContent, $matchesp);


            file_put_contents("log/selSeja.txt", $selSeja . ' - ' . (int)$matchesp[1] . "\n", FILE_APPEND);
        }


        return false;
    }
}


function parseQuestionStart($urls, $startPage, $series, $selSeja)
{
    global $http_response_header, $pobuda;

    foreach ($urls as $url) {

        //	Get main page
        $base = downloadPage($url);

        //	Retrieve cookies
        $cookiess = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $s) {
                if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts))
                    $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
            }
        }
        $cookiess = substr($cookiess, 0, -2);


        preg_match('/form id="(.*?):form1"/', $base, $fmatches);
        $form_id = $fmatches[1];

        preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $base, $matches);
        preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $base, $matchess);
        preg_match('/Page 1 of (\d+)/i', $base, $matchesp);

        if (!empty($matchesp[1]) && (int)$matchesp[1] > 0) {
            $i = $startPage;
            //for ($i = $startPage; $i <= (int)$matchesp[1]; $i++) {
                $postdata = http_build_query(
                    array(
                        //$form_id . ':form1' => $form_id . ':form1',
                        //$form_id . ':form1:menu1' => CURRENT_SESSION,

                        $form_id . ':form1:vprPobude1:goto1__pagerGoButton.x' => 1,
                        $form_id . ':form1:vprPobude1:goto1__pagerGoText' => $i,

                        $form_id . ':form1:selMandati:' => 'ZP\\POS_VPR.NSF',
                        $form_id . ':form1:selAvtorji:' => '',
                        $form_id . ':form1:nazivFilter:' => '',
                        $form_id . ':form1:naslovljenecFilter:' => '',
                        $form_id . ':form1:selSeja:' => $selSeja,
                        $form_id . ':form1_SUBMIT' => 1,
                        'javax.faces.ViewState' => $matchess[1]
                        ,'javax.faces.encodedURL' => '/wps/PA_DZ-LN-VnjaInPobude/portlet/VprasanjaInPobudeView.jsp'
                    )
                );
                $opts = array('http' =>
                    array(
                        'method' => 'POST',
                        'header' => 'Cookie: ' . $cookiess . "\r\n" . 'Content-type: application/x-www-form-urlencoded',
                        'timeout' => 2,
                        'content' => $postdata
                    )
                );

                $context = stream_context_create($opts);
                if ($subpageContent = downloadPage(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', $context)) {


                    parseQuestionList($subpageContent, $i, $cookiess, $series, $selSeja);
                }

                var_dump("page: " . $i ." out of: ".(int)$matchesp[1]);
return false;
            //}
        }
    }
}


function parseQuestionList($content, $page, $cookiess, $series, $selSeja)
{

    global $http_response_header, $pobuda, $pobudaKey;

    $data = str_get_html($content);

    //	Check for single sessions
    $islist = $data->find('table.dataTableExHov', 0);
    if (!empty ($islist)) {
        $data = $data->find('table.dataTableExHov', 0)->find('tbody .rowClass1Hov, tbody .rowClass2Hov ');
    } else {
        return false;
    }

    $listPager = 1;
    foreach ($data as $link) {

        if($series == 1){
            if($listPager == 6){
                break;
            }
        }
        if($series == 2){
            if($listPager < 6){
                $listPager++;
                continue;
            }
            if($listPager == 11){
                break;
            }
        }
        if($series == 3){
            if($listPager < 11){
                $listPager++;
                continue;
            }

        }


        $innerData = $link->find('td');

        $datum = (trim($innerData[0]->text()));
        $naslov = (trim($innerData[1]->text()));
        $vlagatelj = (trim($innerData[2]->text()));
        $ps = (trim($innerData[3]->text()));
        $naslovljenec = (trim($innerData[4]->text()));

        $pobuda[$pobudaKey]["datum"] = translateCharacters($datum);
        $pobuda[$pobudaKey]["naslov"] = translateCharacters($naslov);
        $pobuda[$pobudaKey]["vlagatelj"] = translateCharacters($vlagatelj);
        $pobuda[$pobudaKey]["ps"] = translatePS(translateCharacters($ps));
        $pobuda[$pobudaKey]["naslovljenec"] = translateCharacters($naslovljenec);

        $row_id = $innerData[1]->find('input', 0)->getAttribute('name');

        //	Retrieve cookies
/*        $cookiess = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $s) {
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$/', $s, $parts))
                    $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
            }
        }
        $cookiess = substr($cookiess, 0, -2);
*/

        preg_match('/form id="(.*?):form1"/', $content, $fmatches);
        $form_id = $fmatches[1];

        preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $content, $matches);
        preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $content, $matchess);
        preg_match('/Page 1 of (\d+)/i', $content, $matchesp);

//        if (!empty($matchesp[1]) && (int)$matchesp[1] > 0) {
//            for ($i = 1; $i <= (int)$matchesp[1]; $i++) {
//                var_dumpp($i);

        //	Get next page
        $postdata = http_build_query(
            array(
                //$form_id . ':form1' => $form_id . ':form1',
                // $form_id . ':form1:menu1' => CURRENT_SESSION,

                $form_id . ':form1:selMandati:' => 'ZP\\POS_VPR.NSF',
                $form_id . ':form1:vprPobude1:goto1__pagerGoButton.x' => 11,
                $form_id . ':form1:vprPobude1:goto1__pagerGoButton.y' => 11,
                $form_id . ':form1:vprPobude1:goto1__pagerGoText' => $page,
                $form_id . ':form1_SUBMIT' => 1,
                'javax.faces.ViewState' => $matchess[1],
                'javax.faces.encodedURL' => '/wps/PA_DZ-LN-VnjaInPobude/portlet/VprasanjaInPobudeView.jsp',
                $form_id . ':form1:selAvtorji:' => '',
                $form_id . ':form1:nazivFilter:' => '',
                $form_id . ':form1:naslovljenecFilter:' => '',
                $form_id . ':form1:selSeja:' => $selSeja,
                $row_id => 'true'
            )
        );
        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Cookie: ' . $cookiess . "\r\n" . 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context = stream_context_create($opts);


        if ($subpageContent = downloadPage(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', $context)) {

//            $cookiess = '';
//            if (isset($http_response_header)) {
//                foreach ($http_response_header as $s) {
//                    if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts))
//                        $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
//                }
//            }
//            $cookiess = substr($cookiess, 0, -2);
//            var_dump($cookiess);

            parseQuestionData($subpageContent, $cookiess, $selSeja);
        }

        $data = $pobuda[$pobudaKey];
        sendDataToKunst($pobuda, $pobudaKey, $data);
        $pobudaKey++;
        $listPager++;
    }

}


function parseQuestionData($content, $cookiess, $selSeja)
{

    $data = str_get_html($content);

    $islist = $data->find('form.form', 0);
    if (!empty ($islist)) {
        $data = $data->find('form.form', 0)->find('.buttonLink');
    } else {
        return false;
    }


    global $http_response_header, $pobuda, $pobudaKey;
    foreach ($data as $link) {


        $row_name = $link->getAttribute('name');

/*
        $cookiess = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $s) {
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$/', $s, $parts))
                    $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
            }
        }
        $cookiess = substr($cookiess, 0, -2);
        */


        preg_match('/form id="(.*?):form1"/', $content, $fmatches);
        $form_id = $fmatches[1];

        preg_match('/form id="' . $form_id . ':form1".*?action="(.*?)"/', $content, $matches);
        preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $content, $matchess);
        preg_match('/Page 1 of (\d+)/i', $content, $matchesp);

//    if (!empty($matchesp[1]) && (int)$matchesp[1] > 0) {
//        for ($i = 1; $i <= (int)$matchesp[1]; $i++) {
//            var_dumpp($i);

        //	Get next page
        $postdata = http_build_query(
            array(
                //$form_id . ':form1' => $form_id . ':form1',

                $form_id . ':form1:selMandati:' => 'ZP\\POS_VPR.NSF',
                $form_id . ':form1:selAvtorji:' => '',
                $form_id . ':form1:nazivFilter:' => '',
                $form_id . ':form1:naslovljenecFilter:' => '',
                $form_id . ':form1:selSeja:' => $selSeja,

                // $form_id . ':form1:menu1' => CURRENT_SESSION,
                //$form_id . ':form1:tableEx1:goto1__pagerGoButton.x' => 11,
                //$form_id . ':form1:tableEx1:goto1__pagerGoButton.y' => 11,
                //$form_id . ':form1:tableEx1:goto1__pagerGoText' => $i,
                $form_id . ':form1_SUBMIT' => 1,
                'javax.faces.ViewState' => $matchess[1],
                'javax.faces.encodedURL' => '/wps/PA_DZ-LN-VnjaInPobude/portlet/VprasanjaInPobudeView.jsp',
                $row_name => $row_name
            )
        );
        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Cookie: ' . $cookiess . "\r\n" . 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context = stream_context_create($opts);

        if ($subpage = downloadPage(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', $context)) {

//            $cookiess = '';
//            if (isset($http_response_header)) {
//                foreach ($http_response_header as $s) {
//                    if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts))
//                        $cookiess .= $parts[1] . '=' . $parts[2] . '; ';
//                }
//            }
//            $cookiess = substr($cookiess, 0, -2);
//            var_dump($cookiess);

            $subData = str_get_html($subpage);

            parseData($subData, $cookiess);


        }
//        }
//    }

    }

}

function parseData($data, $cookiess)
{

    global $pobuda, $pobudaKey;

    $findalData = array();
    $outputText = $data->find('.form .outputText');

    $pobudaNaslov = $data->find('.form .outputText', 1)->text();

    $removeData = array('Datum:&#160;', 'Avtor:&#160;', 'Dokument:&#160;','Datum:&nbsp;', 'Avtor:&nbsp;', 'Dokument:&nbsp;');

    foreach ($outputText as $item) {
        $d = trim($item->text());
        if(strlen($d)< 2){
            continue;
        }
        if (!in_array($d, $removeData)) {
            $findalData[] = $d;

        }
    }
    $outputLinkEx = $data->find('.form .outputLinkEx', 0);
    $url = $outputLinkEx->getAttribute('href');

    //var_dump($findalData);

    $pobuda[$pobudaKey]["links"][] = array(
        'date' => translateCharacters($findalData[1]),
        'url' => translateCharacters($url),
        'name' => translateCharacters($pobudaNaslov)
    );
/*
    var_dump($pobuda);
    var_dump($pobuda[$pobudaKey]["links"]);
    die('done 1');
*/
}

function sendDataToKunst($pobuda, $pobudaKey, $data){

    file_put_contents("log/first.txt", print_r($data, true), FILE_APPEND);

return false;
    $url = 'https://data.parlameter.si/v1/addQuestion/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

    $result = curl_exec($ch);
    curl_close($ch);

    file_put_contents("log/first.txt", print_r($result, true), FILE_APPEND);

    var_dump($pobudaKey);

}


function translateCharacters($in){

    mb_internal_encoding('utf-8');
    $a = mapperSumniki($in);
    $b = html_entity_decode($a, ENT_COMPAT, 'UTF-8');

    return $b;
}

function translatePS($in){

    $search = array();
    $search[] = 'Poslanska skupina Slovenske demokratske stranke';
    $search[] = 'Poslanska skupina Stranke modernega centra';
    $search[] = 'Poslanska skupina Združena levica';
    $search[] = 'AČ - Nepovezani poslanec';
    $search[] = 'Poslanska skupina Demokratične stranke upokojencev Slovenije';
    $search[] = 'Poslanska skupina nepovezanih poslancev';

    $replace = array();
    $replace[] = 'PS Slovenska Demokratska Stranka';
    $replace[] = 'PS Stranka modernega centra';
    $replace[] = 'PS Združena Levica';
    $replace[] = 'Nepovezani poslanec Andrej Čuš';
    $replace[] = 'PS Demokratska Stranka Upokojencev Slovenije';
    $replace[] = 'PS nepovezanih poslancev ';

    if(!in_array($in, $search)){
        return $in;
    }

    return str_replace($search, $replace, $in);

}

function mapperSumniki($in){
    $search = array('&#160;');
    $replace = array(' ');

    return str_replace($search, $replace, $in);
}

$pobuda = array();
$pobudaKey = 0;

//
//for ($i = 1; $i<36; $i++){
//    parseQuestionStart(array('http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/'), $i, 1);
//    parseQuestionStart(array('http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/'), $i, 2);
//    parseQuestionStart(array('http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/'), $i, 3);
//    //die();
//}

for ($sejaNr = 1; $sejaNr < 28; $sejaNr++){

    $selSeja = $sejaNr;
    if($sejaNr < 10){
        $selSeja = '0'.$sejaNr;
    }

    countSessionsQuestions(array('http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/'), $selSeja);

}

function findDokument()
{

}


$questionxml = 'http://fotogalerija.dz-rs.si/datoteke/opendata/VPP.XML';
$xml = simplexml_load_file($questionxml);

$pobuda[$pobudaKey]["datum"] = translateCharacters($datum);
$pobuda[$pobudaKey]["naslov"] = translateCharacters($naslov);
$pobuda[$pobudaKey]["vlagatelj"] = translateCharacters($vlagatelj);
$pobuda[$pobudaKey]["ps"] = translatePS(translateCharacters($ps));
$pobuda[$pobudaKey]["naslovljenec"] = translateCharacters($naslovljenec);

$pobuda[$pobudaKey]["links"][] = array(
    'date' => translateCharacters($findalData[1]),
    'url' => translateCharacters($url),
    'name' => translateCharacters($pobudaNaslov)
);

$pobuda = array();

foreach ($xml->VPP->VPRASANJE as $vprasanje) {

    $kartica = $vprasanje->KARTICA_VPRASANJA;

    $pobuda["datum"] = $kartica->KARTICA_DATUM;
    $pobuda["naslov"] = trim($kartica->KARTICA_NASLOV);
    $pobuda["vlagatelj"] = $kartica->KARTICA_VLAGATELJ;
    $pobuda["ps"] = $kartica->KARTICA_POSLANSKA_SKUPINA;
    $pobuda["naslovljenec"] = trim($kartica->KARTICA_NASLOVLJENEC);

    $dokumenti = $vprasanje->PODDOKUMENTI;
    foreach ($dokumenti->UNID as $dokumentUniId) {

        //$dokumentUniId

    }

}


//    <KARTICA_VPRASANJA>
//<UNID>VPP|C549F14114499E22C1257D4200486F60</UNID>
//<KARTICA_VLAGATELJ>Veber Janko</KARTICA_VLAGATELJ>
//<KARTICA_POSLANSKA_SKUPINA>SD - Poslanska skupina Socialnih demokratov</KARTICA_POSLANSKA_SKUPINA>
//<KARTICA_VRSTA>PP</KARTICA_VRSTA>
//<KARTICA_NACIN_VLAGANJA/>
//<KARTICA_NASLOV>
//    v zvezi z spremenjenimi pravili za opravljanje vozniških izpitov
//    </KARTICA_NASLOV>
//<KARTICA_DATUM>2014-08-27</KARTICA_DATUM>
//<KARTICA_STATUS>brezpredmetno</KARTICA_STATUS>
//<KARTICA_SEJA_DZ/>
//<KARTICA_DATUM_SEJE_DZ/>
//<KARTICA_NASLOVLJENEC>
//    minister za infrastrukturo in prostor, ki opravlja tekoče posle
//    </KARTICA_NASLOVLJENEC>
//</KARTICA_VPRASANJA>
//<BESEDILO>1</BESEDILO>
//<PODDOKUMENTI>
//<UNID>VPP|A7609BA7B80ACB67C1257D410043A796</UNID>
//<UNID>VPP|F2F68871359B821DC1257D420048B66C</UNID>
//<UNID>VPP|73ABA21C3620D6B2C1257D58002DA489</UNID>
//</PODDOKUMENTI>


    <DOKUMENT>
<KARTICA_DOKUMENTA>
<UNID>VPP|A7609BA7B80ACB67C1257D410043A796</UNID>
<KARTICA_NASLOV>Besedilo</KARTICA_NASLOV>
<KARTICA_DATUM>2014-08-27</KARTICA_DATUM>
<KARTICA_AVTOR>Veber Janko</KARTICA_AVTOR>
</KARTICA_DOKUMENTA>
<BESEDILO>1</BESEDILO>
<PRIPONKA>
<PRIPONKA_URL>http://imss.dz-rs.si/IMiS/384afed907db7a549169.PDF</PRIPONKA_URL>
<PRIPONKA_KLIC>
    http://imss.dz-rs.si/IMiS/ImisAdmin.nsf/ImisnetAgent?OpenAgent&2&DZ-MSS-01/ca20e0051c03fcabad65a8c60e1ab07b2f598715f9b4384afed907db7a549169
</PRIPONKA_KLIC>
</PRIPONKA>
<PODDOKUMENTI></PODDOKUMENTI>
</DOKUMENT>

}