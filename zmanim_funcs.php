<?php
/*
INFORMAITON BLOCK
========================
FILENAME: zmanim_funcs.php
NAME: Function library for sephardic zmanim pages
AUTHOR: Leon Adato
VERSION HISTORY
	0.0.1 - 0.0.x - development and migration from being embedded in the main php files
=======
DESCRIPTION	
Variety of functions used to build pages that show times for tefillot 

USAGE
==========
this page is served from a web server or at the commandline
along with the URL/URI, variables can include:

QUESTIONS/TODO
==============
how to get shitot (GRA, M''A, etc. loaded in a programattic way)

EXTERNAL SOURCE(S)
======================
https://www.hebcal.com/home/developer-apis
http://www.geonames.org/ (using this API requires a login)
*/

function callAPI($method, $url, $data){
   	/**
 	* Summary.
 	* All purpose function to call an external API and return it's data.
 	* Used by several other functions here and in external programs.
 	*
 	*/
   	$curl = curl_init();
   	switch ($method){
    case "POST":
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data)
        	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    	break;
    case "PUT":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        if ($data)
    		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
        break;
      	default:
        	if ($data)
    			$url = sprintf("%s?%s", $url, http_build_query($data));
   	}
   	// OPTIONS:
   	curl_setopt($curl, CURLOPT_URL, $url);
   	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      	'APIKEY: 111111111111111111111',
    	'Content-Type: application/json',
   	));
   	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
   	// EXECUTE:
   	$result = curl_exec($curl);
   	if(!$result){die("Connection Failure");}
	curl_close($curl);
   	return $result;
} # end function callAPI

class set_location {
	/**
 	* Summary.
 	* All purpose function to call an external API and return it's data.
 	* Used by several other functions here and in external programs.
 	*/
 	/* Member Variables:*/ 
    var $loc_type;
    var $locdata1;
    var $locdata2;
    var $tzusername;

    function __construct($loc_type, $locdata1, $locdata2, $tzusername){
	   	/**
 		* Summary.
		* derive lat/long from user input
		* user input can be zipcode, address, or actual lat/log coordinates
		* default is the BKCS synagogue, 
		*/
		if ($loc_type = 'zipcode' && $locdata1 && $locdata2 && $tzusername) {
			$zipcode = $locdata1;
			$country = $locdata2;
			$zipurl = "http://api.geonames.org/postalCodeSearchJSON?postalcode=$zipcode&country=$country&username=$tzusername";
			$get_zipinfo = callAPI('GET', $zipurl, false);
		    $zipresponse = json_decode($get_zipinfo, true);
		    if (!$zipresponse) {
				echo "Bad zipcode information. Please try again.<br>";
				exit(1);
			} else {
			    $latitude = $zipresponse['postalCodes']['0']['lat'];
		    	$longitude = $zipresponse['postalCodes']['0']['lng'];
		    	$tzurl = "http://api.geonames.org/timezoneJSON?lat=$latitude&lng=$longitude&username=$tzusername";
				$get_tzname = callAPI('GET', $tzurl, false);
				$tzresponse = json_decode($get_tzname, true);
				$tzid = $tzresponse['timezoneId'];
				$geostring = "geo=pos&latitude=$latitude&longitude=$longitude&tzid=$tzid";
				$locstring = "Zipcode $zipcode";
			}
		} elseif ($loc_type = 'address' && $locdata1 && $tzusername) {
			$address = $locdata1;
			$addurlencoded = urlencode($address);
			$addurl = "http://api.geonames.org/geoCodeAddressJSON?q=\"$addurlencoded\"&username=$tzusername";
			$get_addinfo = callAPI('GET', $addurl, false);
			$addresponse = json_decode($get_addinfo, true);
			if (!$addresponse) {
				echo "Bad address information. Please try again.<br>";
				exit(1);
			} else {
				$latitude = $addresponse['address']['lat'];
				$longitude = $addresponse['address']['lng'];
				$tzurl = "http://api.geonames.org/timezoneJSON?lat=$latitude&lng=$longitude&username=$tzusername";
				$get_tzname = callAPI('GET', $tzurl, false);
				$tzresponse = json_decode($get_tzname, true);
				$tzid = $tzresponse['timezoneId'];
				$geostring = "geo=pos&latitude=$latitude&longitude=$longitude&tzid=$tzid";
				$locstring = "address: $address";
			}
		} elseif ($loc_type = 'latlong' && $locdata1 && $locdata2 && $tzusername) {
			$latitude = $locdata1;
			$longitude = $locdata2;
			$tzurl = "http://api.geonames.org/timezoneJSON?lat=$latitude&lng=$longitude&username=$tzusername";
			$get_tzname = callAPI('GET', $tzurl, false);
			$tzresponse = json_decode($get_tzname, true);
			$tzid = $tzresponse['timezoneId'];
			$geostring = "geo=pos&latitude=$latitude&longitude=$longitude&tzid=$tzid";
			$locstring = "Lat: $latitude, Long $longitude, Timezone $tzid";
		} else {
			# kollel $latitude = "41.4902062";
			# kollel $longitude = "-81.517477";
			$latitude = "41.4939407";
			$longitude = "-81.516709";
			$tzid = "America/New_York";
			$geostring = "geo=pos&latitude=$latitude&longitude=$longitude&tzid=$tzid";
			$tzurl = "http://api.geonames.org/timezoneJSON?lat=$latitude&lng=$longitude&username=$tzusername";
			$locstring = "BKCS Building";
		}
		$this->tzurl = $tzurl;
		$this->geostring = $geostring;
		$this->locstring = $locstring;
		$this->latitude = $latitude;
		$this->longitude = $longitude;
	}

	function get_locinfo() {
		$geostring = $this->geostring;
		$locstring = $this->locstring;
		$tzurl = $this->tzurl;
		return [$geostring, $locstring, $tzurl];
	}

	function get_latlong() {
		$latitude = $this->latitude;
		$longitude = $this->longitude;
		return [$latitude, $longitude];
	}

} #end class set_location


class set_times {
	/**
 	* Summary.
 	* Class to manage different times for a specific location and date
 	*/
 	/* Member Variables:*/ 
    var $lat;
    var $long;
    var $thisdate;

    function __construct($thisdate, $lat, $long){
	   	/**
 		* Summary.
		* for a given date, automatically figure out:
		* sunrise and sunset for the date plus the upcoming thursday, friday, saturday, and sunday
		* user input can be zipcode, address, or actual lat/log coordinates
		* default is the BKCS synagogue, 
		*/
		//add logic for elevation
		$sun_info = date_sun_info(strtotime($thisdate),$lat,$long);
		$sunrise = date('g:i:s a ', $sun_info['sunrise']);
		$sunset = date('g:i:s a ', $sun_info['sunset']);
		$this->sunrise = $sunrise;
		$this->sunset = $sunset;
 		}

	function get_suntimes() {
		$sunrise = $this->sunrise;
		$sunset = $this->sunset;
		return [$sunrise, $sunset];
	}
} #end class set_times


class set_shabbatinfo {
	/**
 	* Summary.
 	* Class to manage take a JSON object as input and extract relevant Shabbat info
 	* including Torah portion, Molad, Rosh Chodesh, and more
 	*/
 	/* Member Variables:*/ 
    var $hebcalurl;
    var $friday;
    
    function __construct($zmanurl, $friday, $saturday, $nextsaturday) {
	   	/**
 		* Summary.
		* for 
		*/
		$mevarchim = 0;
		$hebrewparashat = $englishparashat = $molad = $chodeshtext = "";
		$get_zmanim = callAPI('GET', $zmanurl, false);
		$zmanresponse = json_decode($get_zmanim, true);
		//$zmanerrors = $zmanresponse['response']['errors'];
		//$zmandata = $zmanresponse['response']['data'][0];
		foreach($zmanresponse['items'] as $zmanitem) {
			if (date('Y-m-d', strtotime($zmanitem['date'])) == $saturday) {
				if ($zmanitem['category'] == "mevarchim") {
					$mevarchim = 1;
					$moladraw = $zmanitem['memo'];
					$moladtext = substr($moladraw,0,strpos($moladraw,"after ",0)+6);
					$moladtime = date('g:ia', strtotime(substr($moladraw,strpos($moladraw,"after ",0)+6)));
					$molad = "$moladtext $moladtime";
				} #end if zmanitem is mevarchim
				if ($zmanitem['category'] == "parashat") {
					$hebrewparashat = $zmanitem['hebrew'];
					$englishparashat = $zmanitem['title'];
				} #end if zmanitem is parashat
			} #end if date == saturday
			if ($mevarchim == 1 and $zmanitem['category'] == "roshchodesh" and strtotime($zmanitem['date']) > strtotime($friday) and 
			strtotime($zmanitem['date']) <= strtotime($nextsaturday) ) {
				//if $mevarchim = 1 and if date is > friday and < next saturday
				//check for Rosh Chodesh and get that info if needed
				if ($chodeshcount == 0) {
					$chodeshtext = $zmanitem['title'] . " will be " . date('D m/d', strtotime($zmanitem['date']));
					$chodeshcount++;
				} else {
					$chodeshtext = $chodeshtext . " and " . date('D m/d', strtotime($zmanitem['date']));
				} #end if $chodeshcount
			} #end if $mevarchim	
		} #end foreach
		if ($englishparashat == "") {
			foreach($zmanresponse['items'] as $zmanitem) {
			if (date('Y-m-d', strtotime($zmanitem['date'])) == $friday) {
				if ($zmanitem['category'] == "candles") {
					$englishparashat = $zmanitem['memo'];
					} #end if zmanitem is candles
				} #end if date is friday
			} #end foreach loop
		} #end if the english parashat is blank
		$this->hebrewparashat = $hebrewparashat;
		$this->englishparashat = $englishparashat;
		$this->molad = $molad;
		$this->chodeshtext = $chodeshtext;
 	} #end set_shabbatinfo constructor

	function get_shabbatinfo() {
		$hebrewparashat = $this->hebrewparashat;
		$englishparashat = $this->englishparashat;
		$molad = $this->molad;
		$chodeshtext = $this->chodeshtext;
		return [$hebrewparashat, $englishparashat, $molad, $chodeshtext];
	} # end get_shabbatinfo function
} #end class set_shabbatinfo


// OTHER SEPARATE FUNCTIONS!!
function get_isearly($frisunset){
   	/**
 	* Summary.
 	* determine if the date is early zman or late
 	*/
	$isearly = 1;
	if(strtotime($frisunset) <= strtotime("7:35pm")) {
		$isearly=0;
	}
	return($isearly);
}

# SIMPLE CALCULATIONS
function get_candles($sunset){
   	/**
 	* Summary.
 	* get candle lighting for specified date
 	* ??DO WE NEED TO SPECIFIED TYPE OF DATE?? (shabbat, chag, etc)
 	*/
	// Shabbat candles = fri shkia - 18
	$candles = date('g:i a', strtotime( $sunset . " -18 minutes"));
	return($candles);
}

function get_tzeit($sunrise, $sunset){
   	/**
 	* Summary.
 	* get tzeit hakochavim for specified time
 	*/
 	$tzeit = date('g:i a', strtotime($sunset . " +45 minutes"));
 	return($tzeit);
}

function get_latemotzei($sunset, $chagtype){
   	/**
 	* Summary.
 	* get late motzei shabbat/chag for specified date
 	* depending on specified type (shabbat, chag, fast, etc)
 	*/
	// Late Motzi Shabbat Shkia+72 
	$latemotzei = date('g:i a', strtotime( $sunset . " +72 minutes"));
	return($latemotzei);
}

function get_alot($sunrise, $sunset){
   	/**
 	* Summary.
 	* get alot hashachar for specified sunrise and sunset
 	*/
	// Alot Hashachar ("alot") = netz-((shkia-netz)/10)
	$alot = date('g:i a', strtotime($sunrise)-((strtotime($sunset) - strtotime($sunrise))/10));
	return($alot);
}

function get_weekdaymincha($sunset) {
	/**
 	* Summary.
 	* get weekday Mincha time for a given sunset time
 	*/
	$weekdaymincha = date('g:ia', strtotime( $sunsunset . " -17 minutes"));
	return($weekdaymincha);
}

# COMPOUND CALCULATIONS
function get_shaa($sunrise, $sunset){
   	/**
 	* Summary.
 	* get a sha'a for specified tzeit and alot times
 	*/
	// Sha'a (halachic hour) = (tzait - Alot) / 12
	$tzeit = get_tzeit($sunrise, $sunset);
	$alot = get_alot($sunrise, $sunset);
	$shaa = (strtotime($tzeit)-strtotime($alot))/12;
	return($shaa);
}

function get_minchaged($sunrise, $sunset){
   	/**
 	* Summary.
 	* get mincha gedola for specified alot and tzeit times
 	*/
 	// Mincha Gedola = 6.5 sha’a after ‘alot 
	$tzeit = get_tzeit($sunrise, $sunset);
	$alot = get_alot($sunrise, $sunset);
	$minchaged = date('g:i a', strtotime($alot)+(((strtotime($tzeit)-strtotime($alot))/12))*6.5);
	return($minchaged);
}

function get_minchakat($sunrise, $sunset){
   	/**
 	* Summary.
 	* get mincha katan for specified alot and tzeit times
 	*/
 	// Mincha ketana = 9.5 sha’a after ‘alot 
	$tzeit = get_tzeit($sunrise, $sunset);
	$alot = get_alot($sunrise, $sunset);
	$minchakat = date('g:i a', strtotime($alot)+(((strtotime($tzeit)-strtotime($alot))/12))*9.5);
	return($minchakat);
}

function get_shema($sunrise, $sunset){
   	/**
 	* Summary.
 	* get the time for sof zman kria shema for specified date
 	*/
    // Sof zman kria shema (latest time for shema in the morning = Alot + (sha'a * 3)
	$tzeit = get_tzeit($sunrise, $sunset);
	$alot = get_alot($sunrise, $sunset);
	$shema = date('g:i a', strtotime($alot)+(((strtotime($tzeit)-strtotime($alot))/12)*3));
	return($shema);
}

function get_plag($sunrise, $sunset) {
   	/**
 	* Summary.
 	* get plag haMincha for specified tzeit and mincha ketana times
 	*/
 	// Plag Hamincha ("plag") = mincha ketana+((tzeit - mincha ketana) / 2)
	$tzeit = get_tzeit($sunrise, $sunset);
	$alot = get_alot($sunrise, $sunset);
	$minchakat = get_minchakat($alot, $tzeit);
	$plag = date('g:i a', strtotime($minchakat)+(((strtotime($tzeit))-strtotime($minchakat))/2));
	return($plag);
}

function get_misheyakir($sunrise, $sunset) {	
	$tzeit = get_tzeit($sunrise, $sunset);
	$alot = get_alot($sunrise, $sunset);
	$misheyakir = date('g:i a', strtotime($sunrise) - ((strtotime($tzeit)-strtotime($alot))/12/60)*66);
	return($misheyakir);
}

