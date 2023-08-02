<?php
//this the username for http://www.geonames.org/, used to locate time zone based on lat/long information
//you MUST update zmansettings.ini with your geonames account or the script won't work
$ini_array = parse_ini_file("zmansettings.ini");
$tzusername = $ini_array['tzusername'];

/*

INFORMAITON BLOCK
========================
FILENAME: sephardic_zmanim.php
AUTHOR: Leon Adato
=======
DESCRIPTION	
Pulls information from external sites via API
uses statically assigned items (lat/long, zman calculations)
Formats output as HTML page
    
USAGE
==========
this page is served from a web server or at the commandline
along with the URL/URI, variables can include:

shabbat=1
	Go to the next upcoming Friday and pull dates
debug=1
	include all calculations and outputs for troubleshooting.
date=yyyy-mm-dd
	the date you want zmanim for. if you couple this with shabbat=1/-s, this date must be a friday
lat=##.###
	latitude. Must also include longitude and tzid. Mutually exclusive from zip, city, or geoname.
long=##.###
	longitude. Must also include latitude and tzid. Mutually exclusive from zip, city, or geoname.
zip=#####
	zip code. Mutually exclusive from lat and long. Mutually exclusive from lat/long, city, or geoname.
geoname=######
	location specified by GeoNames.org numeric ID (See cities5000.zip from https://download.geonames.org/export/dump/.). Mutually exclusive from zip, city, or lat/long.
city=city name
	location specified by one of the Hebcal.com legacy city identifiers (https://github.com/hebcal/dotcom/blob/master/hebcal.com/dist/cities2.txt). Mutually exclusive from zip, geoname, or lat/long.

EXTERNAL SOURCE(S)
======================
https://www.hebcal.com/home/developer-apis
http://www.geonames.org/ (using this API requires a login)
*/

# Library of functions and classes
include 'zmanim_funcs.php';

//this the username for http://www.geonames.org/, used to locate time zone based on lat/long information
//you MUST update zmansettings.ini with your geonames account or the script won't work
$ini_array = parse_ini_file("zmansettings.ini");
$tzusername = $ini_array['tzusername'];

//initial variables
date_default_timezone_set('America/New_York');

// numeric URL variables
$date = $zipcode = $latitude = $longitude = $lat = $long = $debug = $shabbat = 0;
// string URL variables
$country = $city = $address = $geostring = $locstring = "";

//other yes/no variables
$mevarchim = $chodeshcount = $setdate = $molad = 0;
$isearly = 1;

// date variables
$usedate = $zmanday = $friday = $nextfriday = $nextsaturday = $friyr = $frimo = $frid = $hebyear = $PesachDate = $SukkotDate = "";

//get commandline variables
if(isset($_GET['date'])) {$usedate=stripcslashes($_GET['date']);}
if(isset($_GET['zipcode'])) {$zipcode=stripcslashes($_GET['zipcode']); }
if(isset($_GET['country'])) {$country=stripcslashes($_GET['country']); }
if(isset($_GET['address'])) {$address=stripcslashes($_GET['address']); }
if(isset($_GET['lat'])) {$lat=stripcslashes($_GET['lat']); }
if(isset($_GET['long'])) {$long=stripcslashes($_GET['long']); }
if(isset($_GET['debug'])) {$debug=stripcslashes($_GET['debug']); }
if(isset($_GET['shabbat'])) {$shabbat=stripcslashes($_GET['shabbat']); }

//sanitize and initialize initial inputs
if ($usedate) {
	if (strtotime($usedate) == '') {
		echo "<H2>This is not a valid date format.</H2>";
		exit(0);
	}
	$usedate=date('Y-m-d', strtotime($usedate));
} else {
	$usedate=date('Y-m-d');
}

if ($debug == 1 || $debug == 0) {
} else {
    echo("<H2>Debug must be 0 or 1</h2>\n");
    exit(1);
}

if ($shabbat == 1 || $shabbat == 0) {
} else {
    echo("<H2>Shabbat must be 0 or 1</h2>\n");
    exit(1);
}

if ($zipcode){
	if (!$country) {
		echo("<H2>Zip Code also requires a valid <A HREF=\"https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes\">ISO-3166 Country code</a></h2>\n");	
		exit(1);
	} else {
		if (preg_match('/^[a-z,A-Z]{2}$/', $country)) {
		} else {
    		echo("<H2>not a valid 2 letter <A HREF=\"https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes\">ISO-3166 Country code</a>.</h2>\n");
    		exit(1);
		}
	}
	if (preg_match('/^[0-9]{5}$/', $zipcode)) {
		$setlocation = new set_location('zipcode', $zipcode, $country, $tzusername);
		[$geostring, $locstring, $tzurl] = $setlocation->get_locinfo();
		[$lat, $long] = $setlocation->get_latlong();
	} else {
    	echo("<H2>not a valid 5 digit zip code</h2>\n");
    	exit(1);
	}
}

if ($address) {
	$address = htmlspecialchars($address);
    $address = stripslashes($address);
    $address = trim($address);
    $setlocation = new set_location('address', $address, '', $tzusername);
	[$geostring, $locstring, $tzurl] = $setlocation->get_locinfo();
	[$lat, $long] = $setlocation->get_latlong();
}

if ($latitude){
	if ($latitude >= -90 && $latitude <= 90) {
	} else {
    	echo("<H2>Not a valid latitude coordinate</h2>\n");
    	exit(1);
	}
	if ($longitude){
		if ($longitude >= -180 && $longitude <= 180) {
		} else {
	    	echo("<H2>Not a valid longitude coordinate</h2>\n");
	    	exit(1);
		}
	}
	$setlocation = new set_location('latlong', $lat, $long, $tzusername);
	[$geostring, $locstring, $tzurl] = $setlocation->get_locinfo();
	[$lat, $long] = $setlocation->get_latlong();
}

if (!$geostring) {
	$setlocation = new set_location('','','',$tzusername);
	[$geostring, $locstring, $tzurl] = $setlocation->get_locinfo();
	[$lat, $long] = $setlocation->get_latlong();
}	

// Set next/prev navigation URLs for the bottom of the page
$yesterday = date('Y-m-d', strtotime( $usedate . " -1 days"));
$tomorrow = date('Y-m-d', strtotime( $usedate . " +1 days"));

$urlstart = 'https://';
if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
	$urlstart = 'http://';
}
$baseurl =  "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$urlscheme = parse_url($baseurl, PHP_URL_SCHEME);
$urlhost = parse_url($baseurl, PHP_URL_HOST);
$urlpath = parse_url($baseurl, PHP_URL_PATH);
$urlparams = parse_url($baseurl, PHP_URL_QUERY);
$queryParts = explode('&', $urlparams);
$params = array();
foreach ($queryParts as $param) {
    $item = explode('=', $param);
    $params[$item[0]] = $item[1];
}
if (array_key_exists('shabbat', $params)) {
    unset($params['shabbat']);    
}

$urlquery = "";
$params['date'] = $yesterday;
foreach ($params as $key => $value) {
	$urlquery = $urlquery . '&' . $key . '=' . $value;
}
$urlquery = ltrim($urlquery, '&');
$yesterdayurl = $urlstart.$urlhost.$urlpath.'?'.$urlquery;

$urlquery = "";
$params['date'] = $tomorrow;
foreach ($params as $key => $value) {
	$urlquery = $urlquery . '&' . $key . '=' . $value;
}
$urlquery = ltrim($urlquery, '&');
$tomorrowurl = $urlstart.$urlhost.$urlpath.'?'.$urlquery;

//Get initial sunrise/sunset info for regular and shabbat options
if (!$shabbat) {
	//get all regular times
	$zmanday = new set_times($usedate, $lat, $long);
	[$zmansunrise, $zmansunset] = $zmanday->get_suntimes();
} else {
	//(ELSE) IF SHABBAT IS DESIRED
	if (date('l', strtotime($usedate)) == 'Friday') { 
		$friday = date('Y-m-d', strtotime($usedate));
		//else set it for the upcoming Friday
	} else { 
		$notfriday = new DateTime($usedate);
		$friday = $notfriday->modify('next friday');
		$friday = $friday->format('Y-m-d');
	}

	// get sunrise and sunset for each day	
	$fritimes = new set_times($friday, $lat, $long);
	[$frisunrise, $frisunset] = $fritimes->get_suntimes();

 	$saturday= date('Y-m-d', strtotime( $friday . " +1 days"));
 	$sattimes = new set_times($saturday, $lat, $long);
	[$satsunrise, $satsunset] = $sattimes->get_suntimes();

 	$sunday = date('Y-m-d', strtotime( $saturday . " +1 days"));
 	$suntimes = new set_times($sunday, $lat, $long);
	[$sunsunrise, $sunsunset] = $suntimes->get_suntimes();

	$nextfriday = date('Y-m-d', strtotime( $friday . " +7 days"));
	$nfritimes = new set_times($nextfriday, $lat, $long);
	[$nfrisunrise, $nfrisunset] = $nfritimes->get_suntimes();

	$nextsaturday = date('Y-m-d', strtotime( $saturday . " +7 days"));
	$nsattimes = new set_times($nextsaturday, $lat, $long);
	[$nsatsunrise, $nsatsunset] = $nsattimes->get_suntimes();
 	
 	$nextthursday = date('Y-m-d', strtotime( $sunday . " +4 days"));	
 	$nthutimes = new set_times($nextthursday, $lat, $long);
	[$nthusunrise, $nthusunset] = $nthutimes->get_suntimes();

	// Get general Shabbat-specific times that don't need their own function
	// Saturday Mincha = Shkia-40 minutes 
	$satmincha = date('g:i a', strtotime( $satsunset . " -40 minutes"));
	// Saturday Arvit = Shkia+50 minutes
	$satarvit = date('g:i a', strtotime( $satsunset . " +50 minutes"));
	// Late Motzi Shabbat Shkia+72 
	$latemotzei = date('g:i a', strtotime( $satsunset . " +72 minutes"));
	$sundaymincha = date('g:ia', strtotime( $sunsunset . " -17 minutes"));
	$thursdaymincha = date('g:ia', strtotime( $nthusunset . " -17 minutes"));

	//get shabbat, rosh chodesh, and molad info
	$zmanurl = "https://www.hebcal.com/hebcal?v=1&cfg=json&maj=on&min=on&nx=on&mf=on&ss=on&s=on&c=on&b=18&m=0&i=off&leyning=off$geostring&start=$friday&end=$nextsaturday";
	$shabbatinfo = new set_shabbatinfo($zmanurl, $friday, $saturday, $nextsaturday);
	[$hebrewparashat, $englishparashat, $molad, $chodeshtext] = $shabbatinfo->get_shabbatinfo();

	//get early/late calculations
	if (get_isearly($frisunset)) { 
		$candles = date('g:i a', strtotime( $frisunset . " -18 minutes"));
		$candletext = date('m/d', strtotime($friday)) . " Candle Lighting:". get_plag($frisunrise, $frisunset) . " / $candles";
		$frimincha = date('g:i a', strtotime( get_plag($frisunrise, $frisunset) . " -20 minutes"));
		$friminchatext = $frimincha;
		$frishir = date('g:i a', strtotime( get_plag($frisunrise, $frisunset)  . " -35 minutes"));
		$kabshab = "Following Mincha";
	} else {
		$candles = date('g:i a', strtotime( $frisunset . " -18 minutes"));
		$candletext = date('m/d', strtotime($friday)) . " Candle Lighting: $candles";
		$frimincha = date('g:i a', strtotime( $frisunset . " -20 minutes"));
		$friminchakorb = date('g:i a', strtotime( $frisunset . " -23 minutes"));
		$friminchaashrei = date('g:i a', strtotime( $frisunset . " -18 minutes"));
		$friminchatext = "<br>&nbsp&nbsp&nbspKorbanot: " . $friminchakorb . "<br>&nbsp&nbsp&nbspAshrei: " . $friminchaashrei;		
		$kabshab = date('g:i a', strtotime( $frimincha . " +20 minutes"));
	} # end if isearly
} #end if is_shabbat

// DISPLAY CODE STARTS HERE
// debug output block
if ($debug == 1) {
	if ($shabbat == 1) {
		echo "DEBUG INFO<br>\n";
		echo "Server time: " . date('d-m-Y h:i:s A') . "<br>\n";
		echo "Early Shabbat status: " . get_isearly($frisunrise) . "<br>\n";
		echo "tzurl $tzurl<br>\n";
		echo "Zmanurl is " . $zmanurl . "<br>\n";
		echo "Location $locstring<br>\n";
		echo "Friday: $friday<br>\n";
		echo "Next friday  $nextfriday<br>\n";
		echo "Hebyear is ". $hebyear . "<br/>\n";
		echo "Friday alot (netz-(shkia-netz)/10)" . get_alot($frisunrise, $frisunset) . "<br>\n";
		echo "Friday netz $frisunrise<br>\n";
		#" . get_($sunrise, $sunset) . "
		echo "Friday mincha (summer: zman. Winter: shkia-20)" . $frimincha . "<br>\n";
		echo "Friday mincha gedola (6.5 sha'a after alot)" . get_minchaged($frisunrise, $frisunset) . "<br>\n";
		echo "Friday mincha ketana (9.5 sha'a after alot)" . get_minchakat($frisunrise, $frisunset) . "<br>\n";
		echo "Friday plag (mincha ketana + (tzet-mincha ketana / 2) )" . get_plag($frisunrise, $frisunset) . "<br>\n";
		echo "Friday Kabbalat Shabbat (summer: fixed time winter: mincha+20) $kabshab<br>\n";
		echo "Friday shkia $frisunset<br>\n";
		echo "Friday tzet (shkia+45)" . get_tzeit($frisunrise, $frisunset) . "<br>\n";
		echo "Sha'a " . number_format((float)get_shaa($frisunrise, $frisunset)/60, 2, '.', '') ." minutes<br>\n";
		echo "Saturday $saturday<br>\n";
		echo "Saturday Alot (netz-(shkia-netz)/10) is" . get_alot($satsunrise, $satsunset) . "<br>\n";
		echo "Saturday netz $satsunrise<br>\n";
		echo "Saturday Shema (3 sha'a after alot)" . get_shema($satsunrise, $satsunset) . "<br>\n";
		echo "Saturday Mincha gedola (6.5 sha'a after alot)" . get_minchaged($satsunrise, $satsunset) . "<br>\n";
		echo "Saturday Mincha ketatna (9.5 sha'a after alot)" . get_minchakat($satsunrise, $satsunset) . "<br>\n";
		echo "Saturday plag (mincha ketana + (tzet-mincha ketana / 2) )" . get_plag($satsunrise, $satsunset) . "<br>\n";
		echo "Saturday shkia $satsunset<br>\n";
		echo "Saturday tzet (shkia+45)" . get_tzeit($satsunrise, $satsunset) . "<br>\n";
		echo "Saturday Sha'a " . number_format((float)get_shaa($satsunrise, $satsunset)/60, 2, '.', '') ." minutes<br>\n";
		echo "Molad: $molad<br>\n";
		echo "Chodesh text: $chodeshtext<br>\n";
		echo "Yesterday URL: $yesterdayurl<br>\n";
		echo "Tomorrow URL: $tomorrowurl<br>\n";
		echo "END DEBUG INFO<br><br>\n\n";

	} else {
		//debug output for regular day
		echo "DEBUG INFO<br>\n";
		echo "Server time " . date('d-m-Y h:i:s A') . "<br>\n";
		echo "tzurl $tzurl<br>\n";
		echo "Location $locstring<br>\n";
		echo "Date $usedate<br>\n";
		echo "Misheyakir (66 min before Netz)" . get_misheyakir($zmansunrise, $zmansunset) . " <br>\n";
		echo "Alot (netz-(shkia-netz)/10) " . get_alot($zmansunrise, $zmansunset) . "<br>\n";
		echo "Sunrise/Netz $zmansunrise<br>\n";
		echo "Sof Zman Kria Shema" . get_shema($zmansunrise, $zmansunset) . "<br>\n";
		echo "Mincha Gedola (6.5 sha'a after alot)" . get_minchaged($zmansunrise, $zmansunset) . "<br>\n";
		echo "Mincha Ketana (9.5 sha'a after alot)" . get_minchakat($zmansunrise, $zmansunset) . "<br>\n";
		echo "Plag Hamincha (mincha ketana + (tzet-mincha ketana / 2) )" . get_plag($zmansunrise, $zmansunset) . "<br>\n";
		echo "Sunset/Shkia $zmansunset<br>\n";
		echo "Tzeit (shkia+45)" . get_tzeit($zmansunrise, $zmansunset) . "<br>\n";
		echo "Sha'a " . number_format((float)get_shaa($zmansunrise, $zmansunset)/60, 2, '.', '') . " minutes<br>\n";

		echo "Yesterday URL: $yesterdayurl<br>\n";
		echo "Tomorrow URL: $tomorrowurl<br>\n";
		echo "END DEBUG INFO<br><br>\n\n";
	}
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sephardic Congregation of Cleveland Zmanim</title>
    <style>
		table {
  			table-layout: fixed;
  			width: 7.5in;
  			border-collapse: collapse;
  		}
  		th, td {
  			border: 1px solid black;
  			padding: 0.5rem;
  			text-align: left;
  			vertical-align: top;
  		}
</style>
</head>

<body>
<img src="header.png" width="774">
<table border=1>
	<tr>
		<td style="width:1.25in;">
			<P><strong>Rav<br>Rabbi Tzvi Maimon</strong><br><br>
			Officers are listed <A HREF="https://www.bkcscle.com/officers/">on our website:</A></P>
		</td>
<?php if($shabbat == 1) : ?>
		<td style="width: 4in">
			<center><h3><?php echo "$hebrewparashat - $englishparashat"; ?></h3>
			<P><?php echo "$candletext"; ?></P></center>
			<h3>Erev Shabbat</h3>
			<P><?php if($isearly == 1) {echo "Shir haShirim: $frishir<br>";}?>
			<?php echo "Mincha: $friminchatext"; ?><br>
			<?php echo "Kabbalat Shabbat: $kabshab"; ?></P>
			<h3>Shabbat Day</h3>
			<P><?php echo "Shacharit:<br>&nbsp&nbsp&nbspKorbanot: 8:15am<br>&nbsp&nbsp&nbspHodu: 8:30am"; ?><br> 
			<?php echo "Mincha: $satmincha"; ?><br> 
			<?php echo "Arvit: $satarvit"; ?></P>
			<h3>Weekly Tefillot</h3>
			<P>Sunday Shacharit:
			<br>&nbsp&nbsp&nbspKorbanot: 7:30am
			<br>&nbsp&nbsp&nbspHodu: 7:45am
			<br>Mon-Fri Shacharit
			<br>&nbsp&nbsp&nbspKorbanot: 6:35am
			<br>&nbsp&nbsp&nbspHodu: 6:45am
			<br><?php echo "Sun-Thu Mincha: $sundaymincha - $thursdaymincha"; ?></P>
		</td>
		<td style="width: 2.25in">
			<small><h3>Zmanim</h3>
			<br>
			<h3>Friday</h3>
			<P><?php echo "Plag haMincha: " . get_plag($frisunrise, $frisunset); ?><br>
			<?php echo "Shkia: $frisunset"; ?><br>
			<?php echo "Repeat Kria Shema: " . get_tzeit($frisunrise, $frisunset); ?></P>
			<br>
			<h3>Saturday</h3>
			<P><?php echo "Kriat Shema: " . get_shema($satsunrise, $satsunset); ?><br>
			<?php echo "Shkia: $satsunset"; ?><br>
			<?php echo "Shabbat ends: " . get_tzeit($satsunrise, $satsunset) . "/" . $latemotzei; ?></P>

			<?php if ($mevarchim == 1) : ?>
				<h3>Molad</h3>
				<P><?php echo "$molad";?></P>
				<h3>Rosh Chodesh</h3>
				<P><?php echo "$chodeshtext";?></P>
			<?php endif; ?>
			</small>
		</td>
<?php else : ?>
		<td style="width: 4in">
			<h3><?php echo "Zmanim for Date: $usedate"; ?></h3><br>
			<P><?php echo "Location: $locstring"; ?><br>
			<?php echo "Misheyakir (tallit/tefillin): " . get_misheyakir($zmansunrise, $zmansunset); ?><br>
			<?php echo "Alot haShachar: ". get_alot($zmansunrise, $zmansunset); ?><br>
			<?php echo "Sunrise / Netz: $zmansunrise"; ?><br>
			<?php echo "Sof zman kria shema: ". get_shema($zmansunrise, $zmansunset); ?><br>
			<?php echo "Mincha Gedola: ". get_minchaged($zmansunrise, $zmansunset); ?><br>
			<?php echo "Mincha Ketana: ". get_minchakat($zmansunrise, $zmansunset); ?><br>
			<?php echo "Plag haMincha: ". get_plag($zmansunrise, $zmansunset); ?><br>
			<?php echo "Sunset / shkia: $zmansunset"; ?><br>
			<?php echo "Tzet: " . get_tzeit($zmansunrise, $zmansunset); ?><br>
			<?php echo "Sha'a: ". number_format((float)get_shaa($zmansunrise, $zmansunset)/60, 2, '.', '') . " minutes"; ?></P>
		</td><td style="width: 2.25in"></td>
<?php endif; ?>
	</tr>
</table>
<P><A HREF="<?php echo $yesterdayurl; ?>">Prev day</A>....<A HREF="<?php echo $tomorrowurl; ?>">Next day</A></P>
<P>For information on how to use this webpage, click <a href="usage.html">here</a></P>
<P>NOTE: Times are calculated automatically based on the location informatin provided. Because zip codes can cover a large area; and because of variations in things like the source of sunrise/sunset, height of elevation, rounding seconds to minutes, etc. times may be off by as much as 2 minutes. Please plan accordingly.</P>
</body>
</html>
