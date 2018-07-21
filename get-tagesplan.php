<?php

/* 
 * Skript um den aktuellen Speiseplan von der SigFood-Weseite zu holen und diese
 * via POST Request an ein Mattermost-Hook-Adresse zu senden
 * 
 * Version:         0.3
 * Author:          xwolf
 * Author URI:      https://www.xwolf.de
 * License:         GNU General Public License v2
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub URI:      https://github.com/xwolfde/SigFood-Mattermost
 */

$CONST = array(
    // insert here the url you get from Mattermost as URL for incoming webhook
    "webhook_url"	=> '', 
    
    // in which channel
    "channel"		=> "town-square",
    
    // change username if you want another name as those of the user who created the webhook. 
    // Note: Enable integrations to override usernames must be set to true in config.json to override usernames. 
    // Enable them from System Console > Integrations > Custom Integrations or ask your System Administrator to do so. 
    // If not enabled, the username is set to webhook. 
    "username"		=> "FutterBot",
    
    // Insert a URL for a fancy icon if you need this
    "icon_url"		=> "",
    
    // headline of the message
    "text_headline"	=> 'Speiseplan',
    'display-preise'	=> true,
    'display-wertung'	=> true,
    'display-source'	=> true,    
    'sigfood_url'	=> 'https://www.sigfood.de/',
    "sigfood_api_url"	=> 'https://www.sigfood.de/?do=api.gettagesplan',
    "Useragent"		=> 'SigFood-Mattermost/0.01 (Hodor!)',
    'wertung-emoji'	=> array(
	0   => ':scream:',
	1   => ':disappointed:',
	2   => ':expressionless:',
	3   => ':relieved:',
	4   => ':smile:',
	5   => ':smile:',
    )
    
);


/*-----------------------------------------------------------------------------------*/
/* Main
/*-----------------------------------------------------------------------------------*/
 $webhookurl = filter_var($CONST['webhook_url'], FILTER_SANITIZE_URL);
    if (empty($webhookurl)) {
	echo "No valid Webhook-URL!\n";
	exit;
    }

$xmlstr = get_xml_from_url($CONST["sigfood_api_url"]);

$xmlobj=simplexml_load_string($xmlstr, null, LIBXML_NOCDATA); 
$xmlobj = (array)$xmlobj;


if (isset($xmlobj)) {
   $text = create_textpayload($xmlobj);
   if (!empty($text)) {
	send_post2webhook($text);
   }
} else {
    echo "Got no data from ".$CONST["sigfood_api_url"];
}
exit;

/*-----------------------------------------------------------------------------------*/
/* CreateTextPayload
/*-----------------------------------------------------------------------------------*/
function create_textpayload($xmlobj) {
    global $CONST;
    if (!isset($xmlobj)) {
	return;
    }
    
    $out = '';
    if (isset($CONST['text_headline'])) {
	$out .= "#### ".$CONST['text_headline']."\n";
    }
    
   $mensa = $xmlobj['@attributes']['name'];
   $currentday = $xmlobj['firstDate'];
    
    if ((!empty($mensa)) && (!empty($currentday))) {
	if (!empty($mensa)) {
	    $out .= $mensa.", ";
	}
	if (!empty($currentday)) {
	    $out .= $currentday;
	}
	$out .= "\n\n";
    }
    $titlespalte = $linienspalte = $bildspalte = $preisspalte = $bewertung = '';
    
    if ( isset($xmlobj['Tagesmenue']) && is_object($xmlobj['Tagesmenue']) ) {
	
	$tagesmenu = (array) $xmlobj['Tagesmenue'];
	$mensaessen = (array) $tagesmenu['Mensaessen'];

	foreach($mensaessen as $linie) {
	    $linie = (array) $linie;    
	    $lnr = (string) $linie['linie'];
	    
	    
	    $hauptgericht = (array) $linie['hauptgericht'];	    
	    $idgericht = $hauptgericht['@attributes']['id'];
	    $bezeichnung =  $hauptgericht['bezeichnung'];
	    $flag_schweinefleisch = $linie['@attributes']['moslem'];
	    $flag_rindfleisch = $linie['@attributes']['rind'];
	    $flag_vegetarisch = $linie['@attributes']['vegetarisch'];
	    
	    if (is_array($hauptgericht['bild'])) {
		$bild = (array) $hauptgericht['bild'][0];
		$idbild = $bild['@attributes']['id'];
	    } elseif (is_object($hauptgericht['bild'])) {
		$bild = (array) $hauptgericht['bild'];
		$idbild = $bild['@attributes']['id'];
	    }
	
	    
	   
	    $titlespalte .= "| **".$bezeichnung."** ";
	    if ($flag_vegetarisch == 'true') {
		$titlespalte .= " :seedling: :mushroom::ear_of_rice:";	
	    } else {
		if ($flag_schweinefleisch == 'false') {
		    $titlespalte .= " :pig2:";	
		}
		if ($flag_rindfleisch == 'true') {
		    $titlespalte .= " :cow2:";	
		}
	    }
	    $titlespalte .= " ";
	    $linienspalte .= "| Linie ".$lnr." ";
	  
		
	    $bildspalte .= "";
	    $bildspalte .= "| ![Foto](https://www.sigfood.de/?do=getimage&bildid=".$idbild."&width=300) ";
	    
	    $preisspalte .= "| **Preis:** ";
	    if (isset($hauptgericht['preisstud'])) {
		$preisspalte .= number_format($hauptgericht['preisstud']/100,2)." € Stud. / ";
	    }
	    if (isset($hauptgericht['preisbed'])) {	  
		$preisspalte .= number_format($hauptgericht['preisbed']/100,2)." € Bed. / ";
	    }
	    if (isset($hauptgericht['preisgast'])) {
		$preisspalte .= number_format($hauptgericht['preisgast']/100,2)." € Gäste ";
	    }

   
	    $bewertung .= "| **Wertung:** ";
	    $wertung = (array) $hauptgericht['bewertung'];
	    if (isset($wertung['anzahl']) && $wertung['anzahl'] > 0) {
		$bewertung  .= $wertung['schnitt']." von 5 ";
		$bewertung  .= $CONST['wertung-emoji'][round($wertung['schnitt'])];
	    } else {
		$bewertung .= " _Derzeit keine_";
	    }
	    
	}
	
	
	$out .= $linienspalte. "| \n";
	$out .= "|----------|----------|----------|\n";
	$out .= $titlespalte. "| \n";
	$out .= $bildspalte. "| \n";
	if ($CONST['display-preise']) {
	    $out .= $preisspalte. "| \n";
	}
	if ($CONST['display-wertung']) {
	    $out .= $bewertung. "| \n";
	}
	
    }
    if ($CONST['display-source']) {
	$out .= "\n_Dieser inoffizielle Speiseplan wurde befreit mit [SigFood.de](".$CONST['sigfood_url']."). Original Speiseplan und Zutaten auf [werkswelt.de](http://www.werkswelt.de)._\n";
    }
    
   
    return $out;
}

/*-----------------------------------------------------------------------------------*/
/* Send Post Request
/*-----------------------------------------------------------------------------------*/
function send_post2webhook ($text = '') {
    global $CONST;
    
    $payload = array(
	"channel"   => $CONST['channel'],
	"username"  => $CONST['username'],
	"icon_url"  => $CONST['icon_url'],
	"text"	=> urlencode($text),
    );
    
    
    $webhookurl = filter_var($CONST['webhook_url'], FILTER_SANITIZE_URL);
    if (empty($webhookurl)) {
	return;
    }
    $postString = "payload=".json_encode($payload); // http_build_query($data, '', '&');
    $ch = curl_init($webhookurl);
  
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    # Get the response
    $response = curl_exec($ch);
    curl_close($ch);
    
}
/*-----------------------------------------------------------------------------------*/
/* Get XML by URL
/*-----------------------------------------------------------------------------------*/
function get_xml_from_url($url = ''){
    
    global $CONST;
    
    if (empty($url)) {
	$url = $CONST["sigfood_api_url"];
    }
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $CONST["Useragent"]);

    $xmlstr = curl_exec($ch);
    curl_close($ch);

    return $xmlstr;
}
/*-----------------------------------------------------------------------------------*/
/* EOF
/*-----------------------------------------------------------------------------------*/