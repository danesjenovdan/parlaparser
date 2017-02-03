<?php
/**
 * Translate string to ascii
 *
 * @param string $str String to change
 * @param string $delimiter Whitespace delimiter
 * @return mixed|string Ascii translated string
 */
function toAscii ($str, $delimiter='-') {
	$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
	$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
	$clean = strtolower(trim($clean, '-'));
	$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

	return $clean;
}

/**
 * Simple Logger
 *
 * @param string $message Message to log
 */
function logger ($message)
{
	if (LOGGING)
		error_log (date('D, d M Y H:i:s') . ' - ' . $message . "\n", 3, LOG_PATH);
}

/**
 * Shutdown events
 */
function parserShutdown ()
{
	global $_global_oldest_date;

    if (EXEC_SCRIPT_RUNNER) exec(sprintf('%s', EXEC_SCRIPT_RUNNER));
	if (ON_IMPORT_EXEC_SCRIPT) exec(sprintf('%s%s', ON_IMPORT_EXEC_SCRIPT, $_global_oldest_date));
}

/**
 * Downloads a page from URL
 * @param string $url URL to fetch
 * @return mixed Fetched content or false
 */
function downloadPage ($url, $ctx = null)
{
    global $http_response_header;
	$content = false;
	$errcnt = 0;
	if(is_null($ctx)) {
        $ctx = stream_context_create(array('http' =>
            array(
                'timeout' => 5,
            )
        ));
    }
	while($content == false && $errcnt < 10)
	{
		if ($errcnt > 0) {
			// Log
			logger ('DOWNLOAD RETRY ' . $errcnt . ': ' . $url);
		}
		$content = @file_get_contents($url, false, $ctx); // Sorry for @
		$errcnt++;
		usleep(1);
	}

	if ($content == false) {
		// Log
		logger ('TIMEOUT: ' . (string)$url);

		if (MAIL_NOTIFY)
			mail(MAIL_NOTIFY, '[OMFG PANIC!!1!] DZ-RS unreachable', 'See Subject');

		die('Shutdown: getting timeouts.');
	}
	return $content;
}


function sendReport($customText=null){

    global $MAILGUN_TO, $reportData;
    $domain = MAILGUN_DOMAIN;

    $client = new \GuzzleHttp\Client([
        'verify' => false,
    ]);
    $adapter = new \Http\Adapter\Guzzle6\Client($client);
    $mailgun = new \Mailgun\Mailgun(MAILGUN_KEY, $adapter);

    $html = '';
    if(strlen($customText)>0){
        $html .= '<h1>parser </h1>';
        $html .= '<p>'.$customText.'</p>';

    }else {
       if (count($reportData) > 0) {
            $html .= '<a href="https://data.parlameter.si/tags/">tag me here</a> <br><br>';
            $html .= 'new session:<br>';
            $html .= '<pre>' . print_r($reportData, true) . '</pre>';
        } else {
            $html .= '<h1>parser done</h1>';
            $html .= '<p>NO new data parsed</p>';
        }
    }

    foreach ($MAILGUN_TO as $item) {
        $result = $mailgun->sendMessage($domain, array(
            'from' => MAILGUN_FROM,
            'to' => $item,
            'subject' => 'ParlameterParser Report',
            'text' => strip_tags($html),
            'html' => $html
        ));
    }
}


function sendSms($message){
    global $SMS_TO;
    $url = "http://www.smsapi.si/poslji-sms";
    $data = array("un" => urlencode(SMS_USER),
        "ps" => urlencode(SMS_PASS),
        "from" => urlencode(SMS_FROM),
        "m" => urlencode($message),
        "cc" => urlencode("386"),
        "dr" => urlencode("1"),
        //	'unicode' => urlencode('1'),
    );

    $result = null;
    foreach ($SMS_TO as $to) {

        $data["to"] = urlencode($to);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result[] = curl_exec($ch);
        curl_close($ch);
    }
    return $result;
}

function var_dumpp($in){
    if(VAR_DUMP){
        var_dump($in);
    }
}

function validateDate($date)
{
    $d = DateTime::createFromFormat('d.m.Y', $date);
    return $d && $d->format('d.m.Y') === $date;
}

function asciireplace($in){
    $search = array();
    $replace = array();
    $search[] = '&#382;';
    $replace[] = 'ž';
    $search[] = '&#353;';
    $replace[] = 'š';
    $search[] = '&#269;';
    $replace[] = 'č';
    $search[] = '&#171;';
    $replace[] = 'č';
}