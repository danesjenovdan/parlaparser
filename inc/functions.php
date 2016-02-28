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
 * Parse single session page
 *
 * @param string $content Content of grabbed page
 * @param int $organization_id Organization ID
 */
function parseSessionsList ($content, $organization_id)
{
	$data = str_get_html ($content);

	//	Check for single sessions
	$islist = $data->find('table.dataTableExHov', 0);
	if (!empty ($islist)) {
		$content = $data->find('table.dataTableExHov', 0)->find ('tbody a.outputLink');
	} else {
		return false;
	}

	foreach ($content as $link) {
		$session_name = $link->text();

		$session_link = $link->href;
		$session_nouid = preg_replace ('/\&uid.*$/is', '', $session_link);

		// Date
		if ($organization_id != 95) {
			$date = trim ($link->parent()->next_sibling()->next_sibling()->next_sibling()->text());
		} else {
			$date = trim ($link->parent()->next_sibling()->next_sibling()->text());
		}
		$date = str_replace ('(', '', $date);
		$date = str_replace (')', '', $date);
		$date = DateTime::createFromFormat ('d.m.Y', $date);
		$session_date = $date->format ('Y-m-d');

		$tmp = array (
				'name'		=> trim ($session_name),
				'link'		=> trim ($session_link),
				'link_noid'	=> trim ($session_nouid),
				'date'		=> trim ($session_date),
				'review'    => false,
				'review_ext'=> false
		);

		if ($date < new DateTime('NOW')) {

			// Check if session already imported
			if ($exists = sessionExists ($session_nouid)) {
				if ($exists['in_review'] == 'f' || ($exists['in_review'] == 't' && !UPDATE_SESSIONS_IN_REVIEW)) continue;
				$tmp['id'] = $exists['id']; // Set that session exists
				$tmp['review_ext'] = true;
			}

			// Log
			logger ('FETCH SESSION: ' . DZ_URL . $session_link);

			// Get session
			$session_link = str_replace('&amp;', '&', $session_link); // Some weird shit changed recently on DZRS server
			$session = str_get_html(downloadPage(DZ_URL . $session_link));

			// Parse data
			$tmp['speeches'] = array ();
			if (PARSE_SPEECHES) {
				if ($session->find('td.vaTop', 3)) {
					$sptable = $session->find('td.vaTop', 3)->find('a.outputLink');

					if (!empty ($sptable)) {
						foreach ($sptable as $speeches) {
							$in_review = (bool)(stripos ($speeches->innerText(), "pregled") !== false);
							if ($in_review && SKIP_WHEN_REVIEWS) continue 2;

							if ($in_review) $tmp['review'] = true;

							$datum = '';
							if (preg_match('/(\d{2}\.\d{2}\.\d{4})/is', $speeches->innerText(), $matches)) {
								$datum = DateTime::createFromFormat ('d.m.Y', $matches[1])->format ('Y-m-d');
							}

							$speech = parseSpeeches (DZ_URL . $speeches->href, $datum);
							$tmp['speeches'][$speech['datum']] = $speech;
							sleep(FETCH_TIMEOUT);
						}
					}
				}
			}

			$tmp['documents'] = array ();
			if (PARSE_DOCS) {
				if ($session->find('td.vaTop', 3)) {
					$doctable = $session->find('td.vaTop', 2)->find('a');

					if (!empty ($doctable)) {
						foreach ($doctable as $doc) {
							if (stripos($doc->innerText(), "pregled") === false) {
								$tmp['documents'][] = parseDocument(DZ_URL . $doc->href);
								sleep(FETCH_TIMEOUT);

							} else {
								if (SKIP_WHEN_REVIEWS) continue 2;
							}
						}
					}
				}
			}

			// Parse voting data
			$tmp['voting'] = array ();
			if (PARSE_VOTES) {
				$votearea = $session->find('table.dataTableExHov', 0);
				if (!empty ($votearea)) {
					foreach ($votearea->find('tbody td a.outputLink') as $votes) {
						if (preg_match('/\d{2}\.\d{2}\.\d{4}/is', $votes->text())) {
							$tmp['voting'][] = parseVotes(DZ_URL . $votes->href);
							sleep(FETCH_TIMEOUT);
						}
					}
				}
			}

			//	Test: Izpis podatkov celotne seje
//			print_r ($tmp);
//			exit();

			//	Add to DB
			saveSession ($tmp, $organization_id);
		}
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

/**
 * Parse DT pages
 *
 * @param string $url DT sessions URL root
 */
function parseSessionsDT ($url)
{
	$dts = getDTs ();

	foreach ($dts as $gov_key => $gov_id) {
		parseSessions (array (
				$url . $gov_id
		), $gov_key);
	}
}

/**
 * Parses sessions, fetching speeches, votes, documents
 *
 * @param array $urls URLs of sessions lists
 * @param int $organization_id Organization ID
 */
function parseSessions ($urls, $organization_id)
{
	foreach ($urls as $url) {

		//	Get main page
		$base = downloadPage($url);

		//	Retrieve cookies
		$cookiess = '';
		foreach ($http_response_header as $s){
			if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|',$s,$parts))
				$cookiess.= $parts[1] . '=' . $parts[2] . '; ';
		}
		$cookiess = substr ($cookiess, 0, -2);

		//	Parse main page
		parseSessionsList ($base, $organization_id);

		//  Search on DT page or not TODO: better solution needed
		preg_match('/form id="(.*?):sf:form1"/', $base, $fmatches);
		$form_id = $fmatches[1];

		//	Retreive pager form action
		preg_match('/form id="' . $form_id . ':sf:form1".*?action="(.*?)"/', $base, $matches);

		//	Retreive some ViewState I have no fucking clue what it is for, but it must be present in POST
		preg_match('/id="javax\.faces\.ViewState" value="(.*?)"/', $base, $matchess);

		//	Retreive number of pages
		preg_match('/Page 1 of (\d+)/i', $base, $matchesp);

		if (!empty($matchesp[1]) && (int)$matchesp[1] > 1) {

			for ($i = 2; $i <= (int)$matchesp[1]; $i++) {
				//	Get next page
				$postdata = http_build_query(
						array(
								$form_id . ':sf:form1' => $form_id . ':sf:form1',
								$form_id . ':sf:form1:menu1' => CURRENT_SESSION,
								$form_id . ':sf:form1:tableEx1:goto1__pagerGoButton.x' => 1,
								$form_id . ':sf:form1:tableEx1:goto1__pagerGoText' => $i,
								$form_id . ':sf:form1_SUBMIT' => 1,
								'javax.faces.ViewState' => $matchess[1]
						)
				);
				$opts = array('http' =>
						array(
								'method'  => 'POST',
								'header'  => 'Cookie: ' . $cookiess . "\r\n" . 'Content-type: application/x-www-form-urlencoded',
								'content' => $postdata
						)
				);
				$context  = stream_context_create($opts);
				$subpage = file_get_contents(DZ_URL . $matches[1], false, $context);

				//	Parse sub page
				parseSessionsList ($subpage, $organization_id);
			}

		}
	}
}

/**
 * Find speeches on URL
 *
 * @param string $url URL to fetch
 * @return array Array of speeches
 */
function parseSpeeches ($url, $datum)
{
	global $benchmark;

	$data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));

	// Log
	logger ('FETCH SPEECH: ' . $url);

	// Info
	$array = [
			'naziv' => ($dtit = $data->find('.wpthemeOverflowAuto table tr', 0)) ? html_entity_decode($dtit->find('td', 1)->text()) : 'Ni naziva',
			'datum'	=> $datum,	// $data->find('.wpthemeOverflowAuto table tr', 2)->find('td', 1)->text()
			'talks'	=> []
	];

	// Data
	$content = $data->find('.fieldset', 0)->find('span.outputText', 0);
	$content = html_entity_decode ($content->innertext);
	$content = strip_tags($content, '<br><b>');

	$content = preg_replace ('/<br>/', "\n", $content);

	// Umik "TRAK ..."
	$content = preg_replace ('/[\n\r](<b>)?(\d+\. TR.*?)(<\/b>)?[\n\r]/s', '', $content);

	// Umik "(nadaljevanje")
	$content = preg_replace ('/([\n\r]+\t\(nadaljevanje\)? )/is', '', $content);

	// Umik prekinitev sej
	$content = preg_replace ('/(\t(<b>)?\(Seja.*?ob [\d\.]{5,6}\)(<\/b>)?[\n\r]+)/si', '', $content);

	// Umik ure konca
	$content = preg_replace ('/([\n\r](<b>)?\(?Seja je bila končana \d+\..*?ob.*\)?(<\/b>)?)/si', '', $content);
	$content = preg_replace ('/([\n\r](<b>)?\(?Seja se je končala \d+\..*?ob.*\)?(<\/b>)?)/si', '', $content);
	$content = preg_replace ('/([\n\r](<b>)?\(?Seja je bila prekinjena \d+\..*?ob.*\)?(<\/b>)?)/si', '', $content);

	// Umik dvojnih presledkov
	$content = str_replace ('  ', ' ', trim ($content));

	$content = preg_replace ('/(<b>[ \t]*<\/b>)/s', ' ', trim ($content));
	$content = preg_replace ('/(<\/b>[ \t]*<b>)/s', ' ', trim ($content));

	// Umik dvojnih breakov
	$content = preg_replace ('/([\n\r]+)/', "\n", trim ($content));

	// Split govorov na posamezne dele
	$parts = preg_split ('/[\n\r][\t ]?<b>[\t ]*([<\/b>\tA-ZČŠŽĐÖĆÜ,\.\(\) ]{4,40}?(\([\w ]*?\))??)[: <\/b>]{2,6}/s', $content, null, PREG_SPLIT_DELIM_CAPTURE); // old: '/[\n\r]<b>([A-ZČŠŽĐÖĆÜ,\.]{2,}[A-ZČŠŽĐÖĆÜ,\. \(\)]{3,}(\(.*?\))??)[: <\/b>]{2,6}/s'

	if (!empty ($parts)) {
		if (strpos (strip_tags ($parts[0]), 'REPUBLIKA SLOVENIJA') === 0) {
			unset ($parts[0]);
		}
		$cnt = key ($parts);
		$end = sizeof ($parts);

		for ($i = $cnt; $i <= $end; $i++) {

			//	Iskanje osebe/imena
			if (isset ($parts[$i]) && preg_match ('/^[A-ZČŠŽĐÖĆÜ,\.]{2,}[A-ZČŠŽĐÖĆÜ,\. \(\)]{6,40}(\(.*?\))?/s', $parts[$i])) {

				$name = trim ($parts[$i]);
				if (strpos ($name, '(') > 0) $name = substr ($name, 0, strrpos ($name, '('));

				//  Remove prefixes, some common typos
				$replaces = array (
						'podpredsednik',
						'predsednik',
						'podpredsednica',
						'predsednica',
						'predsedujoči',
						'predsedujoča',
						'predsedujoč',
						'prof.',
						'dr.',
						'mag.',

					//  Tyops
						'podpredsendica',
						'podpredsedica',
						'podpredsedik',
						'predsednk',
						'predsedik',
						'predsenik'
				);
				$name = str_ireplace ($replaces, '', $name);

				$name = preg_replace ('/^(\.) ?/s', '', trim ($name));
				$name = str_replace ('  ', ' ', trim ($name));

				//	Remove some more trash
				if (preg_match ('/REPUBLI|ODBOR|DRŽAVN|KOMISIJ|JAVNO|DRUŠTV|SINDIKA/s', $name)) continue;

				$tmp = array (
						'id' => getPersonIdByName ($name),
						'ime' => $name
				);

				// Preveri, če je pred tekstom še ime stranke v ()
				if (isset ($parts[$i + 1]) && preg_match ('/\(.*?\)$/s', trim ($parts[$i + 1]))) {
					$tmp['stranka'] = substr (trim ($parts[$i + 1]), strrpos (trim ($parts[$i + 1]), '(') + 1, -1);
					$i++;

					if (isset ($parts[$i + 1])) {
						$tmp['vsebina'] = preg_replace ('/\t+/is', '', trim ($parts[$i + 1]));
						$tmp['vsebina'] = strip_tags ($tmp['vsebina']);
						$i++;
					}

				} elseif (isset ($parts[$i + 1])) {
					$tmp['vsebina'] = preg_replace ('/\t+/is', '', trim ($parts[$i + 1]));
					$tmp['vsebina'] = strip_tags ($tmp['vsebina']);
					$i++;
				}

				$array['talks'][] = $tmp;
			}
		}
	}
	return $array;
}

/**
 * Find votes on URL
 *
 * @param string $url URL to fetch
 * @return array Array of votes
 */
function parseVotes ($url)
{
	$array = array ();

	$data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));

	// Log
	logger ('FETCH VOTES: ' . $url);

	$info = $data->find('.panelGrid', 0)->find('tr');
	foreach ($info as $row) {
		foreach ($row->find('td') as $td) {
			$tdinfo = trim($td->text());

			if (strtolower($tdinfo) == 'naslov:') {
				$array['naslov'] = html_entity_decode(trim ($td->next_sibling ()->text()));
			}
			if (strtolower($tdinfo) == 'dokument:') {
				$array['dokument'] = html_entity_decode(trim ($td->next_sibling ()->text()));
			}
			if (strtolower($tdinfo) == 'glasovanje dne:' && preg_match ('/([0-9\.]{10}).*?([0-9\:]{8})/is', $td->next_sibling ()->text(), $tmp)) {
				$array['date'] = DateTime::createFromFormat ('d.m.Y', $tmp[1])->format ('Y-m-d');
				$array['time'] = $tmp[2];
			}
			if (strtolower ($tdinfo) == 'epa:') {
				$array['epa'] = $td->next_sibling ()->text();
				$array['epa_link'] = $td->next_sibling ()->find('a', 0)->href;
			}
			if (strtolower ($tdinfo) == 'faza postopka:') {
				$array['faza'] = $td->next_sibling ()->text();
			}
			if (strtolower ($tdinfo) == 'za:') {
				$array['za'] = $td->next_sibling ()->text();
			}
			if (strtolower($tdinfo) == 'proti:') {
				$array['proti'] = $td->next_sibling ()->text();
			}
			if (strtolower($tdinfo) == 'kvorum:') {
				$array['kvorum'] = $td->next_sibling ()->text();
			}
		}
	}

	$content = $data->find('.fieldset', 0)->find('span.outputText', 0);

	$cnt = 0;
	$array['votes'] = array ();
	foreach ($content->find('tr') as $t) {
		if ($cnt > 0) {
			if (preg_match ('/<td.*?>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td/', html_entity_decode($t->innertext), $matches)) {
				unset ($matches[0]);
				$matches[4] = getPersonIdByName (trim ($matches['1']), true);
				//  Test
				//$matches[5] = $people[$matches[4]]['name'];
				$array['votes'][] = $matches;
			}
		}
		$cnt++;
	}

	return $array;
}

/**
 * Find documents on URL
 *
 * @param string $url URL to fetch
 * @return array Array of documents
 */
function parseDocument ($url)
{
	$array = array ();
	$data = str_get_html(downloadPage(str_replace('&amp;', '&', $url)));

	// Log
	logger ('FETCH DOC: ' . $url);

	$info = $data->find('.wpthemeOverflowAuto form table tr');
	foreach ($info as $key => $item) {
		if (stripos ($item->text(), 'Polni nazi') !== false) {
			$array['session'] = html_entity_decode($info[$key]->find('td', 1)->text());
		}
		if (stripos ($item->text(), 'Naslov dokumenta') !== false) {
			$array['title'] = html_entity_decode($info[$key]->find('td', 1)->text());
		}
		if (stripos ($item->text(), 'Datum dokumenta') !== false) {
			$array['date'] = DateTime::createFromFormat ('d.m.Y', trim ($info[$key]->find('td', 1)->text()))->format ('Y-m-d');
		}
	}
	if (empty($array['title'])) $array['title'] = 'Brez naziva';

	if ($files = $data->find('.panelGrid', 1)) {
		foreach ($files->find('tr') as $row) {
			foreach ($row->find('td a') as $td) {
				$tdinfo = trim($td->text());

				if (preg_match ('/\'(http:.*?)\'/s', (string)$td->onclick, $matches)) {

					$filet = downloadPage($matches[1]);
					if (preg_match ('/url=(.*?)"/s', trim ($filet), $matches2)) {
						$array['filename_orig'] = $tdinfo;
						$array['link'] = $matches2[1];
						$array['filename'] = substr($array['link'], strrpos($array['link'], '/') + 1);
					}
				}

			}
		}
	} else {
		$array['filename'] = '';
		$array['link'] = '';
	}

	return $array;
}

/**
 * Save session to database
 *
 * @param array $session Session data
 * @param int $organization_id Organization ID
 */
function saveSession ($session, $organization_id = 95)
{
	global $conn, $_global_oldest_date, $people;

	// Log
	logger ('SAVE SESSION: ' . $session['date']);
	if (empty($session['speeches'])) return false;

	if (!empty($session['id'])) {
		if ($session['review_ext'] == 1 && $session['review'] == 0) {
			$sql = "
				UPDATE
					parladata_session
				SET
					in_review = 0
				WHERE
					id = " . (int)$session['id'];
			pg_query ($conn, $sql);
		}

		$session_id = $session['id'];

		$sql = "
			DELETE FROM
				parladata_speech
			WHERE
				session_id = '" . (int)$session_id . "'
		";
		pg_query ($conn, $sql);

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
		} else {
			return false;
		}
	}

	$_global_oldest_date = $session['date'];

	//	Save speeches
	foreach ($session['speeches'] as $speech_date => $speech) {
		$order = 0;
		foreach ($speech['talks'] as $talk) {
			$order+=10;

			if ($talk['id'] == 0) {
				$person_id = addPerson ($talk['ime']);
				if (!empty ($person_id)) {
					$talk['id'] = $person_id;
				} else {
					continue;
				}
			}

			$sql = "
				INSERT INTO
					parladata_speech
				(created_at, updated_at, speaker_id, content, \"order\", session_id, start_time, party_id)
				VALUES
				(NOW(), NOW(), '" . pg_escape_string ($conn, $talk['id']) . "', '" . pg_escape_string ($conn, @$talk['vsebina']) . "', '" . $order . "', '" . $session_id . "', '" . $speech_date . "', '" . getPersonOrganization ($talk['id']) . "')
			";
			pg_query ($conn, $sql);
		}
	}

	if (empty($session['id'])) {

		//	Save votes
		foreach ($session['voting'] as $voting) {

			//	Set name to "dokument" when "naslov" is empty
			$name = (!empty ($voting['naslov'])) ? $voting['naslov'] : $voting['dokument'];

			$sql = "
				INSERT INTO
					parladata_motion
				(created_at, updated_at, organization_id, date, session_id, text, party_id)
				VALUES
				(NOW(), NOW(), '" . $organization_id . "', '" . $voting['date'] . "', '" . $session_id . "', '" . pg_escape_string ($conn, $name) . "', '" . $organization_id . "')
				RETURNING id
			";
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

		return $person_id;
	}
	return 0;
}

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

	if (ON_IMPORT_EXEC_SCRIPT) exec(sprintf('%s%s', ON_IMPORT_EXEC_SCRIPT, $_global_oldest_date));
}

/**
 * Downloads a page from URL
 * @param string $url URL to fetch
 * @return mixed Fetched content or false
 */
function downloadPage ($url)
{
	$content = false;
	$errcnt = 0;
	$ctx = stream_context_create(array('http'=>
		array(
			'timeout' => 5,
		)
	));
	while($content == false && $errcnt < 10)
	{
		if ($errcnt > 0) {
			// Log
			logger ('DOWNLOAD RETRY ' . $errcnt . ': ' . $url);
		}
		$content = file_get_contents($url, false, $ctx);
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

