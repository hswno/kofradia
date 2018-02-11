<?php


// hvilken versjon dette dokumentet er
// endre denne kun på forespørsel
// brukes til å hindre siden i å kjøre dersom nye innstillinger legges til
// slik at de blir lagt til her før siden blir mulig å bruke igjen
// (først etter at nye innstillinger lagt til, skal versjonen settes til det som samsvarer med de nye innstillingene)
$local_settings_version = 1.5;



// linjene som er kommentert med # er eksempler på andre oppsett



define("DEBUGGING", true);

// hovedserveren?
// settes kun til true på sm serveren
// dette gjør at den utelukker enkelte statistikk spesifikt for serveren, aktiverer teststatus av funksjoner osv.
define("MAIN_SERVER", false);

// testversjon på hovedserveren?
// kun avjørende hvis MAIN_SERVER er true
// deaktiverer opplasting av bilder på testserveren, benytter egen test-cache versjon og litt annet
define("TEST_SERVER", false);

// HTTP adresse til static filer
define("STATIC_LINK", "https://kofradia.no/static");
#define("STATIC_LINK", "/static");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


global $__server;
$__server = array(
	"absolute_path" => "http".HTTPS."://".$_SERVER['HTTP_HOST'],
	"relative_path" => "", // hvis siden ligger i noen undermapper, f.eks. /sm
	"session_prefix" => "sm_",
	"cookie_prefix" => "sm_",
	"cookie_path" => "/",
	"cookie_domain" => "", // eks: ".kofradia.no"
	"https_support" => false, // har vi støtte for SSL (https)?
	"http_path" => "http://".$_SERVER['HTTP_HOST'], // full HTTP adresse, for videresending fra HTTPS
	"https_path" => false, // full HTTPS adresse, false hvis ikke støtte for HTTPS, eks: "https://www.kofradia.no"
	"timezone" => "Europe/Oslo"
);
$__server['path'] = $__server['absolute_path'].$__server['relative_path'];




// mappestruktur
// merk at adresse på windows må ha to \.

// HTTP-adresse til lib-mappen (hvor f.eks. MooTools plasseres)
define("LIB_HTTP", "https://kofradia.no/lib");

// HTTP adresse til hvor bildemappen er plassert
define("IMGS_HTTP", $__server['path'] . "/imgs");

// plassering til anti-bot bildene
define("ANTIBOT_FOLDER", PATH_PUBLIC . "/imgs/antibot");

// adresse til mappen hvor alle logger lagres
define("GAMELOG_DIR", dirname(__FILE__) . "/gamelogs");
#define("GAMELOG_DIR", "C:\\Users\\henrik\\Gamelogs");

// knyttet opp mot profilbilder
define("PROFILE_IMAGES_HTTP", IMGS_HTTP . "/profilbilder"); // HTTP-adressen hvor bildene finnes
define("PROFILE_IMAGES_FOLDER", PATH_PUBLIC . "/imgs/profilbilder"); // mappe hvor bildene skal lagres på disk
#define("PROFILE_IMAGES_FOLDER", "c:\\users\\henrik\\web\\static");
define("PROFILE_IMAGES_DEFAULT", "https://kofradia.no/static/other/profilbilde_default.png"); // standard profilbilde

// knyttet opp mot bydeler, kartfiler
define("BYDELER_MAP_FOLDER", PATH_PUBLIC . "/imgs/bydeler"); // adresse til hvor bydelskartene vil bli generert, må være mulig å nå med IMGS_HTTP/bydeler.

// data for crewfiles
define("CREWFILES_DATA_FOLDER", "/home/kofradia/www/kofradia.no/crewfiles/data");
#define("CREWFILES_DATA_FOLDER", "c:\\users\\henrik\\web\\crewstuff\\f\\data");

// mappe hvor vi skal cache for fil-cache (om ikke APC er til stede)
define("CACHE_FILES_DIR", "/tmp");
define("CACHE_FILES_PREFIX", "smcache_");



// databaseinnstillinger
define("DBHOST", "db");
#define("DBHOST", ":/var/lib/mysql/mysql.sock"); // linux

// brukernavn til MySQL
define("DBUSER", "kofradia_www");

// passord til MySQL
define("DBPASS", "passord");

// MySQL-databasenavn som inneholder dataen
define("DBNAME", "kofradia_data");

// mappe hvor arkiv av databaser skal eksporteres
define("DBARCHIVE_DIR", "/home/smafia/dbarchive");


$set = array();

// bruker-ID til SYSTEM-brukeren
$set["system_user_id"] = 16;

// Debug modus - aktiverer enkelte funksjoner for å forenkle debugging/testing
$set["kofradia_debug"] = TRUE;

// facebook app-id og secret
$set["facebook_app_id"] = null;
$set["facebook_app_secret"] = null;

// kommenter eller fjern neste linje ETTER at innstillingene ovenfor er korrigert
// die("Innstillingene må redigeres før serveren kan benyttes. Se app/inc.innstillinger_local.php.");
