<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

global $__server, $_base, $class_browser, $_game;

require "include_top.php";
require "helpers.php";
$_base->page->add_head('<link href="/themes/sm/doc.css?'.@filemtime(dirname(__FILE__)."/doc.css").'" rel="stylesheet" type="text/css" />');

echo '<!DOCTYPE html>
<html lang="no">
<head>
<title>'.$_base->page->generate_title().'</title>'.$_base->page->generate_head().'
</head>
<body class="'.$class_browser.'">'.$_base->page->body_start.'
<h1 id="doc_header"><a href="'.$__server['path'].'/">Kofradia</a></h1>';

// logget inn?
if (login::$logged_in)
{
	echo '
<p id="doc_userinfo">Logget inn som '.game::profile_link().' | <a href="'.$__server['relative_path'].'/loggut?sid='.login::$info['ses_id'].'">Logg ut</a></p>';
	
	// utvidede tilganger?
	if (isset(login::$extended_access))
	{
		if (login::extended_access_is_authed())
		{
			echo '
<p id="doc_crew">Logget inn som '.access::name(access::type(login::$user->player->data['up_access_level'])).' | <a href="'.$__server['relative_path'].'/extended_access?logout&amp;orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg ut</a></p>';
			
			echo '
<div id="doc_crewm">
	<p class="first">
		<a href="'.$__server['relative_path'].'/crew/">Crew</a> (<a href="https://kofradia.no/crewstuff/" target="_blank">Stuff</a>)<br />
		<a href="https://github.com/hswno/kofradia/pulse" target="_blank">GitHub</a><br />
		<a href="'.$__server['relative_path'].'/crew/htpass">HT-pass</a>
	</p>';
			
			if (access::has("crewet")) echo '
	<p>
		<a href="'.$__server['relative_path'].'/forum/forum?id=5">Crewforum</a> (<a href="'.$__server['relative_path'].'/forum/forum?id=6">arkiv</a>)<br />
		<a href="'.$__server['relative_path'].'/forum/forum?id=7">Idémyldringsforum</a><br />
	</p>';
			
			foreach (theme_helper::get_extended_access_boxes() as $box)
			{
				echo '
	<div class="link_box"><a href="'.$box[0].'">'.$box[1].'</a></div>';
			}
			
			echo '
</div>';
		}
		
		// ikke logget inn
		else
		{
			// har ikke passord?
			if (!isset(login::$extended_access['passkey']))
			{
				echo '
<p id="doc_crew"><b>Ikke</b> logget inn som '.access::name(access::type(login::$user->player->data['up_access_level'])).' | <a href="'.$__server['relative_path'].'/extended_access?create&amp;orign='.urlencode($_SERVER['REQUEST_URI']).'">Opprett passord</a></p>';
			}
			
			// logg inn lenke
			else
			{
				echo '
<p id="doc_crew"><b>Ikke</b> logget inn som '.access::name(access::type(login::$user->player->data['up_access_level'])).' | <a href="'.$__server['relative_path'].'/extended_access?orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg inn</a></p>';
			}
		}
	}
}

else
{
	echo '
<p id="doc_userinfo">Du er ikke logget inn | <a href="'.$__server['relative_path'].'/?orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg inn</a> | <a href="'.$__server['relative_path'].'/registrer">Registrer</a></p>';
}

$profiler = \Kofradia\DB::getProfiler();
echo '
<div id="doc_content">'.$_base->page->content.'
</div>
<div id="doc_footer">
	<p id="doc_footer_left">Script: '.round(microtime(true)-SCRIPT_START-$profiler->time, 4).' sek<span id="js_time"></span><br />Database: '.round($profiler->time, 4).' sek ('.$profiler->num.' spørring'.($profiler->num == 1 ? '' : 'er').')</p>
	<p><a href="http://hsw.no"><span>Henrik Steen Webutvikling</span></a></p>
</div>
<!--

'.$_base->date->get()->format("r").'

Kofradia 2010
Beskyttet av åndsverkloven
Utviklet og scriptet av Henrik Steen [henrist.net]
Script tid: '.round(microtime(true)-SCRIPT_START-$profiler->time, 4).' sek
Database tid: '.round($profiler->time, 4).' sek - '.$profiler->num.' database spørring'.($profiler->num == 1 ? '' : 'er').'

-->'.$_base->page->body_end.'
</body>
</html>';