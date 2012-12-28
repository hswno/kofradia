<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

global $__server, $_base, $class_browser, $_game;

require "include_top.php";
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
		<a href="https://kofradia.no/crewstuff/trac/" target="_blank">Trac</a><br />
		<a href="'.$__server['relative_path'].'/crew/htpass">HT-pass</a>
	</p>';
			
			if (access::has("crewet")) echo '
	<p>
		<a href="'.$__server['relative_path'].'/forum/forum?id=5">Crewforum</a> (<a href="'.$__server['relative_path'].'/forum/forum?id=6">arkiv</a>)<br />
		<a href="'.$__server['relative_path'].'/forum/forum?id=7">Idémyldringsforum</a><br />
		<a href="'.$__server['relative_path'].'/crew/trac_rss">Trac hendelser</a>
	</p>';
			
			// support meldinger
			$ant_support = game::$settings['support_ubesvart']['value'];
			if ($ant_support > 0 && access::has("forum_mod"))
			{
				echo '
	<div class="link_box"><a href="'.$__server['relative_path'].'/support/panel">Det er <b>'.$ant_support.'</b> ubesvart'.($ant_support == 1 ? '' : 'e').' supportmelding'.($ant_support == 1 ? '' : 'er').'!</a></div>';
			}
			
			
			// antall ubesvarte henvendelser
			if (access::has("mod"))
			{
				// hent antall nye henvendelser
				$result = $_base->db->query("SELECT COUNT(h_id) FROM henvendelser WHERE h_status = 0");
				$ant = mysql_result($result, 0);
				
				if ($ant > 0)
				{
					echo '
	<div class="link_box"><a href="'.$__server['relative_path'].'/henvendelser?a">Det er <b>'.$ant.'</b> nye henvendelser som er ubesvart.</a></div>';
				}
			}
			
			// nye hendelser i Trac?
			global $_trac_rss;
			@include_once ROOT."/base/data/trac_rss.php";
			if (isset($_trac_rss))
			{
				// har ikke brukeren vært innom status siden enda?
				$last = login::$user->params->get("trac_last_changeset");
				if (!$last)
				{
					echo '
	<div class="link_box"><a href="'.$__server['relative_path'].'/crew/trac_rss?show=changeset">Du vil nå motta nye hendelser om <b>endringer i Subversion</b> fra Trac her. Trykk her for å se de siste hendelser.</a></div>';
				}
				
				// nye hendelser?
				elseif ($last < $_trac_rss['last_changeset'])
				{
					// finn ut antall nye hendelser
					$new = 0;
					foreach ($_trac_rss['data_changeset'] as $item)
					{
						if ($item['time'] <= $last) break;
						$new++;
					}
					
					echo '
	<div class="link_box"><a href="'.$__server['relative_path'].'/crew/trac_rss?show=changeset">Det er <b>'.$new.'</b> usett'.($new == 1 ? '' : 'e').' hendelse'.($new == 1 ? '' : 'r').' i Trac (endringer i <b>Git</b>).</a></div>';
				}
				
				// har ikke brukeren vært innom status siden enda?
				$last = login::$user->params->get("trac_last_other");
				if (!$last)
				{
					echo '
	<div class="link_box"><a href="'.$__server['relative_path'].'/crew/trac_rss?show=other">Du vil nå motta nye hendelser om <b>endringer i wikien og tickets</b> fra Trac her. Trykk her for å se siste hendelser.</a></div>';
				}
				
				// nye hendelser?
				elseif ($last < $_trac_rss['last_other'])
				{
					// finn ut antall nye hendelser
					$new = 0;
					foreach ($_trac_rss['data_other'] as $item)
					{
						if ($item['time'] <= $last) break;
						$new++;
					}
					
					echo '
	<div class="link_box"><a href="'.$__server['relative_path'].'/crew/trac_rss?show=other">Det er <b>'.$new.'</b> usett'.($new == 1 ? '' : 'e').' hendelse'.($new == 1 ? '' : 'r').' i Trac.</a></div>';
				}
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

echo '
<div id="doc_content">'.$_base->page->content.'
</div>
<div id="doc_footer">
	<p id="doc_footer_left">Script: '.round(microtime(true)-SCRIPT_START-$_base->db->time, 4).' sek<span id="js_time"></span><br />Database: '.round($_base->db->time, 4).' sek ('.$_base->db->queries.' spørring'.($_base->db->queries == 1 ? '' : 'er').')</p>
	<p><a href="http://hsw.no"><span>Henrik Steen Webutvikling</span></a></p>
</div>
<!--

'.$_base->date->get()->format("r").'

Kofradia 2010
Beskyttet av åndsverkloven
Utviklet og scriptet av Henrik Steen [henrist.net]
Script tid: '.round(microtime(true)-SCRIPT_START-$_base->db->time, 4).' sek
Database tid: '.round($_base->db->time, 4).' sek - '.$_base->db->queries.' database spørring'.($_base->db->queries == 1 ? '' : 'er').'

-->'.$_base->page->body_end.'
</body>
</html>';