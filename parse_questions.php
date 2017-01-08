<?php

require 'vendor/autoload.php';
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



function parseQuestionStart($urls)
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
            for ($i = 1; $i <= (int)$matchesp[1]; $i++) {
                $postdata = http_build_query(
                    array(
                        //$form_id . ':form1' => $form_id . ':form1',
                        $form_id . ':form1:menu1' => CURRENT_SESSION,

                        $form_id . ':form1:vprPobude1:goto1__pagerGoButton.x' => 1,
                        $form_id . ':form1:vprPobude1:goto1__pagerGoText' => $i,

                        $form_id . ':form1:selMandati:' => 'ZP\\POS_VPR.NSF',
                        $form_id . ':form1:selAvtorji:' => '',
                        $form_id . ':form1:nazivFilter:' => '',
                        $form_id . ':form1:naslovljenecFilter:' => '',
                        $form_id . ':form1:selSeja:' => '',
                        $form_id . ':form1_SUBMIT' => 1,
                        'javax.faces.ViewState' => $matchess[1],
                        'javax.faces.encodedURL' => '/wps/PA_DZ-LN-VnjaInPobude/portlet/VprasanjaInPobudeView.jsp'
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
                if ($subpageContent = file_get_contents(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', false, $context)) {
                    parseQuestionList($subpageContent, $i, $cookiess);
                }

                var_dump($i);

            }
        }
    }
}


function parseQuestionList($content, $page, $cookiess)
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

    foreach ($data as $link) {

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
                $form_id . ':form1:selSeja:' => '',
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


        if ($subpageContent = file_get_contents(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', false, $context)) {
            parseQuestionData($subpageContent, $cookiess);
        }

        $data = $pobuda[$pobudaKey];
        sendDataToKunst($pobuda, $pobudaKey, $data);
        $pobudaKey++;
    }

}


function parseQuestionData($content, $cookiess)
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

                $form_id . ':form1:selMandati:' => 'ZP\\\\POS_VPR.NSF',
                $form_id . ':form1:selAvtorji:' => '',
                $form_id . ':form1:nazivFilter:' => '',
                $form_id . ':form1:naslovljenecFilter:' => '',
                $form_id . ':form1:selSeja:' => '',

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

        if ($subpage = file_get_contents(DZ_URL . $matches[1] . '#Z7_KIOS9B1A080280I1UOFDUG3081', false, $context)) {

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
        'name' => translateCharacters($findalData[3])
    );
/*
    var_dump($pobuda);
    var_dump($pobuda[$pobudaKey]["links"]);
    die('done 1');
*/
}

function sendDataToKunst($pobuda, $pobudaKey, $data){

    file_put_contents("log/first.txt", print_r($data, true));


    $url = 'https://data.parlameter.si/v1/addQuestion/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    //curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pobuda[$pobudaKey]));
    //curl_setopt($ch, CURLOPT_POSTFIELDS, ($pobuda[$pobudaKey]));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $result = curl_exec($ch);
    curl_close($ch);

    var_dump($pobudaKey);

}

$pobuda = array();
$pobudaKey = 0;

function unichr($dec) {
    if ($dec < 128) {
        $utf = chr($dec);
    } else if ($dec < 2048) {
        $utf = chr(192 + (($dec - ($dec % 64)) / 64));
        $utf .= chr(128 + ($dec % 64));
    } else {
        $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
        $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
        $utf .= chr(128 + ($dec % 64));
    }
    return $utf;
}


function translateCharacters($in){

    $a = htmlentities($in);

    $b = html_entity_decode($a);

    $b = preg_replace("/&#(\d{2,5});/e", "unichr($1);", $b);

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


parseQuestionStart(array('http://www.dz-rs.si/wps/portal/Home/deloDZ/poslanskaVprasanjaInPobude/'));
