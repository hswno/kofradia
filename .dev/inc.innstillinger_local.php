<?php

// se inc.innstillinger_pre.php for forklaringer

$local_settings_version = 1.5;

define("DEBUGGING", true);
define("MAIN_SERVER", false);
define("TEST_SERVER", false);
define("STATIC_LINK", "https://kofradia.no/static");

global $__server;
$__server = array(
  "absolute_path" => "http".HTTPS."://".$_SERVER['HTTP_HOST'],
  "relative_path" => "",
  "session_prefix" => "sm_",
  "cookie_prefix" => "sm_",
  "cookie_path" => "/",
  "cookie_domain" => "",
  "https_support" => false,
  "http_path" => "http://".$_SERVER['HTTP_HOST'],
  "https_path" => false,
  "timezone" => "Europe/Oslo"
);

$__server['path'] = $__server['absolute_path'].$__server['relative_path'];

define("LIB_HTTP", "https://kofradia.no/lib");
define("IMGS_HTTP", "https://kofradia.no/imgs");

define("ANTIBOT_FOLDER", PATH_PUBLIC . "/imgs/antibot");

define("GAMELOG_DIR", PATH_DATA . "/gamelogs");

define("PROFILE_IMAGES_HTTP", $__server['path'] . "/imgs/profilbilder");
define("PROFILE_IMAGES_FOLDER", PATH_PUBLIC . "/imgs/profilbilder");
define("PROFILE_IMAGES_DEFAULT", "https://kofradia.no/static/other/profilbilde_default.png");

define("BYDELER_MAP_FOLDER", PATH_PUBLIC . "/imgs/bydeler");

define("CREWFILES_DATA_FOLDER", "/project/app/data/crewfiles");

define("CACHE_FILES_DIR", "/tmp");
define("CACHE_FILES_PREFIX", "smcache_");

define("DBHOST", "mysql");
define("DBUSER", "root");
define("DBPASS", "kofradiapass");
define("DBNAME", "kofradia");
define("DBARCHIVE_DIR", PATH_DATA . "/dbarchive");

$set = array();
$set["system_user_id"] = 16;
$set["kofradia_debug"] = TRUE;
$set["facebook_app_id"] = null;
$set["facebook_app_secret"] = null;
