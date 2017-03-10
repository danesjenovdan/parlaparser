<?php


require 'vendor/autoload.php';
include_once('inc/config.php');


/*

- Predlogi zakonov
PZ.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/PZ.XML

- Zakoni - konec postopka
PZ7.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/PZ7.XML

- Sprejeti zakoni
SZ.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/SZ.XML

- Predlogi aktov
PA.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/PA.XML

- Akti - konec postopka
PA7.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/PA7.XML

- Sprejeti akti
SA.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/SA.XML

- Predlogi odlokov - konec postopka
XXX
http://XXX

- Prečiščena besedila
PB.xml
http://fotogalerija.dz-rs.si/datoteke/opendata/PB.XML

*/

//search in all

function getRedirectedUid($url){

    $uid = '';

    $ch = curl_init($url);
    curl_exec($ch);
    if (!curl_errno($ch)) {
        $info = curl_getinfo($ch);

        $tmpurl = ($info["redirect_url"]);
        $parts = parse_url($tmpurl);
        parse_str($parts['query'], $query);

        echo ($url) ."\r\n";

        $uid = $query['uid'];


    }

    return $uid;

}

/*
CREATE TABLE parladata_tmpvoteslinkdocuments
(
    id INTEGER PRIMARY KEY NOT NULL,
    session_id INTEGER,
    ura VARCHAR(20),
    datum VARCHAR(20),
    kvorum VARCHAR(50),
    epa VARCHAR(20),
    dokument TEXT,
    vote_link TEXT,
    epa_link TEXT,
    inserted VARCHAR(20)
);

CREATE SEQUENCE parladata_tmpvoteslinkdocuments_id_seq NO MINVALUE NO MAXVALUE NO CYCLE;
ALTER TABLE parladata_tmpvoteslinkdocuments ALTER COLUMN id SET DEFAULT nextval('parladata_tmpvoteslinkdocuments_id_seq');
ALTER SEQUENCE parladata_tmpvoteslinkdocuments_id_seq OWNED BY parladata_tmpvoteslinkdocuments.id;
GRANT ALL PRIVILEGES ON TABLE parladata_tmpvoteslinkdocuments TO parladaddy;
GRANT USAGE, SELECT ON SEQUENCE parladata_tmpvoteslinkdocuments_id_seq TO parladaddy;

 */

function getTmpVotesLinkDocuments(){
    global $conn;

    //SELECT * FROM parladata_tmpvoteslinkdocuments where epa != '' AND session_id = 5572
    //SELECT * FROM parladata_tmpvoteslinkdocuments where id = 158
    $sql = "
	SELECT * FROM parladata_tmpvoteslinkdocuments where epa != '' AND session_id = 5572
	";
    $result = pg_query ($conn, $sql);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {


            $tmpUid = $row["epa_link"];
            $uid = getRedirectedUid($tmpUid);

            if(!empty($uid)) {
                searchInAllSections($uid, $row["session_id"], trim($row["datum"]), $row["dokument"]);
            }
            //die();

        }
    }


}


function searchInAllSections($unid, $session_id, $datum, $dokument){

    global $items;

    $searchInMe = array();

    $searchInMe[] = 'xml/PZ.XML';
    $searchInMe[] = 'xml/PZ7.XML';
    $searchInMe[] = 'xml/SZ.XML';
    $searchInMe[] = 'xml/PA.XML';
    $searchInMe[] = 'xml/PA7.XML';
    $searchInMe[] = 'xml/SA.XML';
    $searchInMe[] = 'xml/PB.XML';

    foreach ($searchInMe as $xmlFile) {

        $xml = simplexml_load_file($xmlFile);

        foreach ($xml->PREDPIS as $predpis) {

            //var_dump((string)$predpis->KARTICA_PREDPISA->UNID);
            if(stripos($predpis->KARTICA_PREDPISA->UNID, $unid) !== false){

                echo ((string)$predpis->KARTICA_PREDPISA->UNID)."\r\n";

                $items = array();

                //POVEZANI_PREDPISI
                foreach ($predpis->POVEZANI_PREDPISI->UNID as $povezani_predpisi_unid) {
                    if(!empty((string)$povezani_predpisi_unid)) {
                        echo ((string)$povezani_predpisi_unid) . "\r\n";
                        searchInOBRAVNAVA_PREDPISA($xmlFile, (string)$povezani_predpisi_unid);
                    }
                }

                //PODDOKUMENTI
                foreach ($predpis->PODDOKUMENTI->UNID as $poddokumenti_unid) {
                    if(!empty((string)$poddokumenti_unid)) {
                        echo ((string)$poddokumenti_unid) . "\r\n";
                        searchInDOKUMENT($xmlFile, (string)$poddokumenti_unid);
                    }
                }

                //PRIPONKA
                if(isset($predpis->PRIPONKA)) {
                    foreach ($predpis->PRIPONKA->PRIPONKA_KLIC as $priponka) {
                        $tmpItem = array(
                            "urlLink" => (string)$priponka,
                            "urlName" => (string)$predpis->KARTICA_PREDPISA->KARTICA_NAZIV
                        );
                        $items[] = $tmpItem;
                    }
                }

                //$motionName = trim((string)$predpis->KARTICA_PREDPISA->KARTICA_NAZIV) .' - '. trim($dokument);
                $motionName = trim($dokument);
                $date = DateTime::createFromFormat('d.m.Y', $datum)->format('Y-m-d');

                $motion = findExistingMotion(95, $session_id, $date, $motionName);

                $motionId = (!empty($motion["id"])) ? $motion["id"] : false;

                var_dump($motion);
                var_dump($motionId);
                print_r($items); echo "\r\n";


//continue;
                if ($motionId) {
                    $id = insertVotingDocument($motionId, 95, $session_id, $date, $motionName, $items);
                    print_r("inserted: ");
                    print_r($id);
                } else {
                    print_r("nogo");
                    //var_dump($item);
                }

                //die("najden");
            }

        }

    }




}


function searchInOBRAVNAVA_PREDPISA($xmlFile, $unid){
    $xml = simplexml_load_file($xmlFile);

    foreach ($xml->OBRAVNAVA_PREDPISA as $predpis) {

        //var_dump((string)$predpis->KARTICA_OBRAVNAVE_PREDPISA->UNID);
        if(stripos($predpis->KARTICA_OBRAVNAVE_PREDPISA->UNID, $unid) !== false){

            echo ("  najdu predpis " . (string)$unid)."\r\n";

            //PRIPONKA
            if(isset($predpis->PRIPONKA)) {
                foreach ($predpis->PRIPONKA->PRIPONKA_KLIC as $priponka) {
                    $tmpItem = array(
                        "urlLink" => (string)$priponka,
                        "urlName" => (string)$predpis->KARTICA_OBRAVNAVE_PREDPISA->KARTICA_NAZIV
                    );
                    $items[] = $tmpItem;
                }
            }

            foreach ($predpis->PODDOKUMENTI->UNID as $poddokumenti_unid) {
                searchInDOKUMENT($xmlFile, (string)$poddokumenti_unid);
            }
        }

    }

}


function searchInDOKUMENT($xmlFile, $unid){
    global $items;

    $xml = simplexml_load_file($xmlFile);


    foreach ($xml->DOKUMENT as $dokument) {

        //var_dump((string)$predpis->KARTICA_DOKUMENTA->UNID);
        if(stripos($dokument->KARTICA_DOKUMENTA->UNID, $unid) !== false){

            echo ("    najdu dokument " . (string)$dokument->KARTICA_DOKUMENTA->UNID)."\r\n";
            //var_dump("najdu dokument " . (string)$dokument->KARTICA_DOKUMENTA->PRIPONKA->PRIPONKA_KLIC);
            //var_dump((string)$dokument->KARTICA_DOKUMENTA->PRIPONKA->PRIPONKA_KLIC);

            if(!empty((string)$dokument->KARTICA_DOKUMENTA->PRIPONKA->PRIPONKA_KLIC)) {

                $tmpItem = array(
                    "urlLink"=>(string)$dokument->KARTICA_DOKUMENTA->PRIPONKA->PRIPONKA_KLIC,
                    "urlName" => (string)$dokument->KARTICA_DOKUMENTA->KARTICA_NAZIV
                );
                $items[] = $tmpItem;

            }

            foreach ($dokument->PODDOKUMENTI->UNID as $poddokumenti_unid) {
                if(!empty((string)$poddokumenti_unid)) {
                    echo ((string)$poddokumenti_unid) . "\r\n";
                    searchInDOKUMENT($xmlFile, (string)$poddokumenti_unid);
                }
            }

        }

    }

}



$items = array();
getTmpVotesLinkDocuments();

/*
 scenarij:

preko epa linka (ki ima redirekt) dobim uid:

http://www.dz-rs.si/wps/PA_DZ-LN-Iskalnik/EpaLinkServlet?epa=1363-VII&amp;pathPrefix=/wps/portal
->
https://www.dz-rs.si/wps/portal/Home/deloDZ/zakonodaja/izbranZakonAkt?uid=C1257A70003EE5D7C125807A004B6F25&db=spr_zak&mandat=VII
uid: C1257A70003EE5D7C125807A004B6F25



 */

/*

<PREDPIS>
    <KARTICA_PREDPISA>
        <UNID>PA7|C1257A70003EE749C125803B004CF557</UNID>
        <KARTICA_EPA>1488-VII</KARTICA_EPA>
        <KARTICA_EVA>ni določena</KARTICA_EVA>
        <KARTICA_MANDAT>7</KARTICA_MANDAT>
        <KARTICA_KONEC_POSTOPKA>1</KARTICA_KONEC_POSTOPKA>
        <KARTICA_KRATICA></KARTICA_KRATICA>
        <KARTICA_NAZIV>Interpelacija o delu Vlade Republike Slovenije </KARTICA_NAZIV>
        <KARTICA_VRSTA>Drugi akti-MAPA</KARTICA_VRSTA>
        <KARTICA_DATUM>2016-09-27</KARTICA_DATUM>
        <KARTICA_PREDLAGATELJ>Skupina poslank in poslancev (prvopodpisani Janez Janša)</KARTICA_PREDLAGATELJ>
        <KARTICA_POSTOPEK>enofazni</KARTICA_POSTOPEK>
        <KARTICA_FAZA_POSTOPKA>konec postopka</KARTICA_FAZA_POSTOPKA>
        <KARTICA_DELOVNA_TELESA></KARTICA_DELOVNA_TELESA>
        <KARTICA_SOP></KARTICA_SOP>
        <KARTICA_OBJAVA></KARTICA_OBJAVA>
        <KARTICA_KLJUCNE_BESEDE>interpelacija</KARTICA_KLJUCNE_BESEDE>
        <KARTICA_KLJUCNE_BESEDE>vlada</KARTICA_KLJUCNE_BESEDE>
        <KARTICA_SEJA></KARTICA_SEJA>
        <KARTICA_KLASIFIKACIJSKA_STEVILKA>020-12/16-0028/</KARTICA_KLASIFIKACIJSKA_STEVILKA>
    </KARTICA_PREDPISA>
    <BESEDILO>0</BESEDILO>
    <POVEZANI_PREDPISI>
        <UNID>PA7|C1257A70003EE749C125803B004E338D</UNID>
    </POVEZANI_PREDPISI>
    <PODDOKUMENTI>
        <UNID>PA7|C1257A70003EE749C125803C00487219</UNID>
        <UNID>PA7|C1257A70003EE749C125803B004E338D</UNID>
        <UNID>PA7|C1257A70003EE749C1258075002A7C51</UNID>
    </PODDOKUMENTI>
</PREDPIS>


<OBRAVNAVA_PREDPISA>
    <KARTICA_OBRAVNAVE_PREDPISA>
        <UNID>PA7|C1257A70003EE749C125803B004E338D</UNID>
        <KARTICA_EPA>1488-VII</KARTICA_EPA>
        <KARTICA_EVA>ni določena</KARTICA_EVA>
        <KARTICA_MANDAT>7</KARTICA_MANDAT>
        <KARTICA_KRATICA></KARTICA_KRATICA>
        <KARTICA_NAZIV>Interpelacija o delu in odgovornosti Vlade Republike Slovenije </KARTICA_NAZIV>
        <KARTICA_VRSTA>Drugi akti-OBRAVNAVA</KARTICA_VRSTA>
        <KARTICA_DATUM>2016-09-27</KARTICA_DATUM>
        <KARTICA_PREDLAGATELJ>Skupina poslank in poslancev (prvopodpisani Janez Janša)</KARTICA_PREDLAGATELJ>
        <KARTICA_POSTOPEK>enofazni</KARTICA_POSTOPEK>
        <KARTICA_FAZA_POSTOPKA>obravnava - DZ</KARTICA_FAZA_POSTOPKA>
        <KARTICA_DELOVNA_TELESA></KARTICA_DELOVNA_TELESA>
        <KARTICA_SOP></KARTICA_SOP>
        <KARTICA_OBJAVA>Poročevalec DZ 28.09.2016; 28.10.2016 - Odgovor</KARTICA_OBJAVA>
        <KARTICA_KLJUCNE_BESEDE></KARTICA_KLJUCNE_BESEDE>
        <KARTICA_SEJA>24. Redna</KARTICA_SEJA>
        <KARTICA_KLASIFIKACIJSKA_STEVILKA>020-12 / 16 - 0028</KARTICA_KLASIFIKACIJSKA_STEVILKA>
    </KARTICA_OBRAVNAVE_PREDPISA>
    <BESEDILO>0</BESEDILO>
    <GLASOVANJE>
        <GLASOVANJE_CAS>2016-11-24T01:20:47.000</GLASOVANJE_CAS>
        <GLASOVANJE_KVORUM>68</GLASOVANJE_KVORUM>
        <GLASOVANJE_ZA>19</GLASOVANJE_ZA>
        <GLASOVANJE_PROTI>48</GLASOVANJE_PROTI>
        <GLASOVANJE_POIMENSKI_SEZNAM>1</GLASOVANJE_POIMENSKI_SEZNAM>
    </GLASOVANJE>
    <PODDOKUMENTI>
        <UNID>PA7|C1257A70003EE749C125803B004E364F</UNID>
        <UNID>PA7|C1257A70003EE749C125803C001FE959</UNID>
        <UNID>PA7|C1257A70003EE749C125805A002423B0</UNID>
        <UNID>PA7|C1257A70003EE749C125805A002D0D3F</UNID>
        <UNID>PA7|C1257A70003EE749C1258074006A8491</UNID>
    </PODDOKUMENTI>
</OBRAVNAVA_PREDPISA>


<DOKUMENT>
    <KARTICA_DOKUMENTA>
        <UNID>PA7|C12565E2005ED694C1257D200034DFC4</UNID>
        <KARTICA_EPA>0001-VII</KARTICA_EPA>
        <KARTICA_EVA></KARTICA_EVA>
        <KARTICA_MANDAT>7</KARTICA_MANDAT>
        <KARTICA_KRATICA></KARTICA_KRATICA>
        <KARTICA_NAZIV>Besedilo Poročila o izidu predčasnih volitev v Državni zbor Republike Slovenij</KARTICA_NAZIV>
        <KARTICA_VRSTA>Dokument</KARTICA_VRSTA>
        <KARTICA_DATUM>2014-07-25</KARTICA_DATUM>
        <KARTICA_AVTOR>Državna volilna komisija</KARTICA_AVTOR>
        <BESEDILO>0</BESEDILO>
        <PRIPONKA>
            <PRIPONKA_KLIC>http://imss.dz-rs.si/IMiS/ImisAdmin.nsf/ImisnetAgent?OpenAgent&amp;2&amp;DZ-MSS-01/ca20e005ca43abc35c98e17cb7a30dd24e7cbf0282f7cddba1a6a4ea5c369794</PRIPONKA_KLIC>
        </PRIPONKA>
    </KARTICA_DOKUMENTA>
    <PODDOKUMENTI>
    </PODDOKUMENTI>
</DOKUMENT>
 */