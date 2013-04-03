<?php

// innstillinger
// for Kofradia

error_reporting(E_ALL);

// sett konstanter
define("LOGIN_TYPE_TIMEOUT", 0);
define("LOGIN_TYPE_BROWSER", 1);
define("LOGIN_TYPE_ALWAYS", 2);
define("LOGIN_ERROR_USER_OR_PASS", 0);
define("LOGIN_ERROR_ACTIVATE", 1);
define("LOGIN_ERROR_DEAD", 2);

// tillate spillere med samme spillernavn? (midlertidig konstant)
if (!defined("ALLOW_SAME_PLAYERNAME")) define("ALLOW_SAME_PLAYERNAME", false);

// drapsfunksjon
define("DISABLE_ANGREP", time() < 1276542000);
define("DISABLE_BUY_VAP", time() < 1276542000);
define("DISABLE_BUY_PROT", time() < 1276196400);


// spillinnstillinger
global $_game;
$_game = array(
	"ranks_access_levels" => array(
		13 => 'Utvikler',
		5 => 'Moderator',
		7 => 'Administrator',
		8 => 'Administrator',
		10 => 'Systembruker'
	),
	"rank_death" => "Cadaveri Eccelenti",
	
	// tilgangene - hvilken tilgang representerer hvilket nummer?
	"access" => array(
		"deactivated" => array(0),
		"none" => array(1),
		"crewet" => array(-100,-4,3,4,5,6,7,8,10,12,13),
		"ressurs" => array(-4),
		"forum_mod" => array(4,5,6,7,8,10,12,13),
		"forum_mod_nostat" => array(6,5,6,7,8,10,13),
		"mod" => array(5,7,8,10,13),
		"admin" => array(7,10,8),
		"sadmin" => array(8,10),
		"auto" => array(10),
		"block_pm" => array(-15,8),
		"ressurs_nostat" => array(12),
		"developer" => array(13),
		"nostat" => array(14) // hvis denne fjernes må det endres i /ajax/get_player_info.php
	),
	
	"access_colors" => array(
		"deactivated" => "c_deactivated",
		"forum_mod" => "c_forum_mod",
		"forum_mod_nostat" => "c_forum_mod",
		"mod" => "c_mod",
		"admin" => "c_admin",
		"sadmin" => "c_admin",
		"auto" => "c_system",
		"ressurs" => "c_idemyldrer",
		"ressurs_nostat" => "c_idemyldrer",
		"developer" => "c_idemyldrer"
	),
	
	"access_names" => array(
		"deactivated" => "Deaktivert",
		"none" => "Vanlig bruker",
		"crewet" => "Crewet",
		"forum_mod" => "Forummoderator",
		"forum_mod_nostat" => "Forummoderator",
		"mod" => "Moderator",
		"admin" => "Administrator",
		"auto" => "Systembruker",
		"sadmin" => "Administrator",
		"ressurs" => "Ressurs",
		"ressurs_nostat" => "Ressurs",
		"developer" => "Utvikler"
	),
	"access_formats" => array(
		"auto" => "<b>%user</b>"
	),
	
	"access_noplay" => 5,
	
	// innstillinger for gta
	"gta" => array(
		"points" => 12,
		"hurtigselg_faktor" => 0.3
	),
	
	"cash" => array(
		"Uteligger" => 0,
		"Løpegutt" => 1000,
		"Raner" => 10000,
		"Svart arbeider" => 100000,
		"Langer" => 500000,
		"Millionær" => 1000000,
		"Mangemillionær" => 10000000,
		"Farlig millionær" => 50000000,
		"Beryktet millionær" => 100000000,
		"Milliardær" => 1000000000,
		"Mangemilliardær" => 10000000000
	),
	
	// pengeranker
	"cash_ranks" => array(
		"Izugarri aberatsa", // 1. plass
		"Oso aberatsa",      // 2-5. plass
		"Aberatsa"          // 6-15. plass
	)
);


global $_lang;
$_lang = array(
	"seconds" => array(
		"full" => array("sekund", "sekunder"),
		"partial" => array("sek", "sek"),
		"short" => array("s", "s")
	),
	"minutes" => array(
		"full" => array("minutt", "minutter"),
		"partial" => array("min", "min"),
		"short" => array("m", "m")
	),
	"hours" => array(
		"full" => array("time", "timer"),
		"partial" => array("time", "timer"),
		"short" => array("t", "t")
	),
	"days" => array(
		"full" => array("dag", "dager"),
		"partial" => array("dag", "dager"),
		"short" => array("d", "d")
	),
	"weeks" => array(
		"full" => array("uke", "uker"),
		"partial" => array("uke", "uker"),
		"short" => array("u", "u")
	),
	"weekdays" => array(
		"søndag",
		"mandag",
		"tirsdag",
		"onsdag",
		"torsdag",
		"fredag",
		"lørdag"
	),
	"months" => array(
		1 => "januar",
		"februar",
		"mars",
		"april",
		"mai",
		"juni",
		"juli",
		"august",
		"september",
		"oktober",
		"november",
		"desember"
	)
);


// smileys
global $_smileys;
$_smileys = array(
	":biggrin:" => STATIC_LINK."/smileys/biggrin.gif",
	":badgrin:" => STATIC_LINK."/smileys/badgrin.gif",
	":D" => STATIC_LINK."/smileys/biggrin.gif",
	":S" => STATIC_LINK."/smileys/confused.gif",
	":cool:" => STATIC_LINK."/smileys/cool.gif",
	":'(" => STATIC_LINK."/smileys/cry.gif",
	"8)" => STATIC_LINK."/smileys/despair.gif",
	":evil:" => STATIC_LINK."/smileys/evil.gif",
	":!:" => STATIC_LINK."/smileys/exclamation.gif",
	":light:" => STATIC_LINK."/smileys/light.gif",
	":?:" => STATIC_LINK."/smileys/question.gif",
	":P" => STATIC_LINK."/smileys/razz.gif",
	":(" => STATIC_LINK."/smileys/sad.gif",
	":O" => STATIC_LINK."/smileys/shocked.gif",
	":)" => STATIC_LINK."/smileys/smile.gif",
	":|" => STATIC_LINK."/smileys/strict.gif",
	";)" => STATIC_LINK."/smileys/wink.gif",
	"xD" => STATIC_LINK."/smileys/xd.gif",
	":oO:" => STATIC_LINK."/smileys/oo.gif"
);

global $__page;
$__page = array(
	"title" => "Kofradia.no",
	"title_direction" => "left",
	"title_split" => " &raquo; ",
	"keywords_default" => array("kofradia", "broderskap", "mafia spill", "streetzmafia", "drammen", "spill", "henrik steen"),
	"description_default" => "Kofradia er et tekstbasert nettspill hvor du konkurrerer om å være blant de beste og i det beste broderskapet. Du kan involvere deg i broderskap, firmaer, angripe og forsøke å eliminere andre spillere og forsøke å erobre den mektigste posisjonen i spillet. Velkommen til Kofradia!",
	"theme" => "sm"
);

// tidssone
global $__server;
date_default_timezone_set($__server['timezone']);