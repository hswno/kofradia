<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

// ikke logget inn? hent guest theme fil i stedet
if (!login::$logged_in)
{
	ess::$b->page->theme_file = "guest";
	ess::$b->page->load();
}

require "helpers.php";

class theme_sm_default
{
	protected static $locked = false;
	
	protected static $num_pm;
	protected static $num_log;
	
	#protected static $content_right;
	protected static $date_now;
	
	protected static $class_crew;
	protected static $class_browser;
	
	/**
	 * Behandle template
	 */
	public static function main()
	{
		self::$date_now = ess::$b->date->get();
		self::check_lock();
		
		global $class_browser;
		require "include_top.php";
		
		self::$class_browser = $class_browser;
		
		self::load_vars();
		self::load_polls();
		self::load_donations();
		
		// crew (evt. utvidede tilganger)?
		self::$class_crew = isset(login::$extended_access) ? ' is_crew' : '';
		
		self::generate_page();
	}
	
	protected static function generate_page()
	{
		/*<div class="default_hidden" id="content"><a href="#top">Til toppen av siden</a></div>
				<div class="default_hidden"><a href="#content">Til toppen av innholdet</a></div>*/

		$extra_classes = '';

		// vise juleheader?
		$d = ess::$b->date->get();
		if ($d->format("m") == 12) $extra_classes .= ' juleheader';
		
		echo '<!DOCTYPE html>
<html lang="no">
<head>
<title>'.ess::$b->page->generate_title().'</title>'.ess::$b->page->generate_head().'</head>
<body class="'.self::$class_browser.self::$class_crew.(self::$locked ? ' is_lock' : '').$extra_classes.'" id="default_th">'.ess::$b->page->body_start.'
	<!--<div class="default_hidden print" id="top">
		<h1><a href="'.ess::$s['absolute_path'].'">kofradia.no</a></h1>
		<p class="default_hidden"><a href="#content">Til innholdet</a></p>
	</div>-->
	<div id="default_header_wrap">
		<div id="default_header_img"></div>
		<div id="default_header">';
		
		$boxes = theme_helper::get_extended_access_boxes();
		if ($boxes)
		{
			echo '
			<div id="cboxes"'.(!access::has("mod") ? ' class="nocus"' : '').'>';
			
			foreach ($boxes as $box)
			{
				echo '
				<p class="box"><a href="'.$box[0].'">'.$box[1].'</a></p>';
			}
			
			echo '
			</div>';
		}
		
		echo self::get_extended_access_login();
		echo self::get_extended_access_links();
		echo self::get_extended_access_search();
		
		echo '
			
			<p id="toplink"><a href="'.ess::$s['path'].'/" title="Gå til forsiden"></a></p>
			<p id="fb_link"><a href="http://www.facebook.com/kofradia" target="_blank"><span>Du finner oss på Facebook</span></a></p>';
			//<p id="donate_link"><a href="'.ess::$s['relative_path'].'/donasjon" title="Doner &raquo;"><span>Donér &raquo;</span></a></p>';
		
		echo '
			<ul id="default_topmenu">
				<li><a href="'.ess::$s['relative_path'].'/loggut?sid='.login::$info['ses_id'].'" onclick="return confirm(\'Er du sikker på at du vil logge ut?\n\nTips! Trykk Esc-knappen tre ganger for å logge ut uten å måtte trykke på denne knappen!\')"><b>Logg ut</b></a></li>
				<li><a href="'.ess::$s['relative_path'].'/innboks">Meldinger</a></li>';
		
		if (!self::$locked)
		{
			echo '
				<li><a href="'.ess::$s['relative_path'].'/kontakter">Kontakter</a></li>
				<li><a href="'.ess::$s['relative_path'].'/finn_spiller">Finn spiller</a></li>';
		}
		
		echo '
				<li><a href="'.ess::$s['relative_path'].'/min_side?u&amp;a=set">Innstillinger</a></li>';
		
		if (!self::$locked)
		{
			echo '
				<li><a href="'.ess::$s['relative_path'].'/forum/topic?id=94159">Discord</a></li>';
		}
		
		echo '
			</ul>
			<div id="status_info"></div>
			<p id="default_profilbilde">
				<span id="default_profilbilde_wrap">
					<a href="'.ess::$s['relative_path'].'/min_side?'.(!login::$user->player->active ? "up_id=".login::$user->player->id.'&amp;' : '').'a=profil&amp;b=profilbilde" class="profile_image_edit"><img src="/static/icon/image.png" /> endre</a>
					<a href="'.ess::$s['relative_path'].'/p/'.login::$user->player->data['up_name'].'"><img src="'.htmlspecialchars(login::$user->player->get_profile_image()).'" alt="Ditt profilbilde" class="profile_image" /></a>
				</span>
			</p>
			<p id="default_playername">'.game::profile_link().'</p>'.self::get_oppdrag_status().'
		</div>
		<div id="default_header_subline">
			<p id="server_klokka"><span>'.self::$date_now->format(date::FORMAT_WEEKDAY).' '.self::$date_now->format(date::FORMAT_NOTIME).' - '.self::$date_now->format("H:i:s").'</span></p>
			<div id="pm_new">'.(self::$num_pm > 0 ? '<p class="notification_box"><a href="'.ess::$s['relative_path'].'/innboks"><b>'.self::$num_pm.' '.fword("ny</b> melding", "nye</b> meldinger", self::$num_pm).'</a></p>' : '').'</div>
			<div id="log_new">'.(self::$num_log > 0 ? '<p class="notification_box"><a href="'.ess::$s['relative_path'].'/min_side?log"><b>'.self::$num_log.' '.fword("ny</b> hendelse", "nye</b> hendelser", self::$num_log).'</a></p>' : '').'</div>';
		
		if (login::$user->data['u_log_crew_new'] > 0 && isset(login::$extended_access))
		{
			echo '
			<p class="notification_box"><a href="'.ess::$s['relative_path'].'/min_side?u&a=crewlog"><b>'.login::$user->data['u_log_crew_new'].' '.fword("ny</b> crew-hendelse", "nye</b> crew-hendelser", login::$user->data['u_log_crew_new']).'</a></p>';
		}
		
		if (!self::$locked)
		{
			echo '
			<div id="def_ui2">
				<p>'.login::$user->player->getRank()->getName().'</p>
				<p><span class="farge">Sted: </span> <span id="status_bydel">'.game::$bydeler[login::$user->player->data['up_b_id']]['name'].'</span></p>
				<p><span class="farge">Har ute</span> <span id="status_cash">'.game::format_cash(login::$user->player->data['up_cash']).'</span></p>
				<p><span class="farge">Plassering: </span> nr. <span id="status_rankpos">'.login::$user->player->data['upr_rank_pos'].'</span></p>
			</div>';
		}
		
		echo '
		</div>
	</div>
	<div id="default_left">';
		
		if (!login::$user->player->active)
		{
			echo '
		<div id="default_info_dead">
			<a href="'.ess::$s['relative_path'].'/lock?f=player">'.(login::$user->player->data['up_deactivated_dead'] == 2 ? '
				<span>Din spiller blødde ihjel pga. lite energi.</span>' : (login::$user->player->data['up_deactivated_dead'] ? '
				<span>Din spiller har blitt drept.</span>' : '
				<span>Din spiller er deaktivert.</span>')).'
				<span class="link">Ny spiller &raquo;</span>
			</a>
		</div>';
		}
		
		echo '
		<nav>'.kf_menu::build_menu().'
		</nav>
	</div>';
		
		if (defined("DISABLE_RIGHT_COL"))
		{
			$content_right = '';
		}
		else
		{
			$content_right = ess::$b->page->generate_content_right(); # self::$locked ..?
		}
		
		echo '
	<div id="default_main">';
		
		if ($content_right)
		{
			echo '
		<div id="default_right">'.$content_right.'</div>';
		}
		
		echo '
		<div id="default_content_wrap"'.(!$content_right ? ' class="noright"' : '').'>'.self::get_status_bars().'
			<section id="default_content">'.ess::$b->page->content.'</section>
		</div>
	</div>
	<div id="default_bottom_1">
		<p><a href="/">Kofradia</a> &copy; - Beskyttet av <a href="http://www.lovdata.no/all/nl-19610512-002.html" target="_blank">åndsverkloven</a> - Utviklet av <a href="http://www.henrist.net/" target="_blank">Henrik Steen</a></p>
		<p><a href="'.ess::$s['relative_path'].'/betingelser">Betingelser for bruk</a> - <a href="'.ess::$s['relative_path'].'/credits">Takk til</a></p>
	</div>
	<div id="default_bottom_2">';

		$profiler = \Kofradia\DB::getProfiler();
		echo '
		<p>Script: '.round(microtime(true)-SCRIPT_START-$profiler->time, 4).' sek - Database: '.round($profiler->time, 4).' sek ('.$profiler->num.' spørring'.($profiler->num == 1 ? '' : 'er').')<span id="js_time"></span></p>';
		
		$revision = self::get_revision_info();
		if ($revision) {
			echo '
		<p>Versjon <a href="https://github.com/hswno/kofradia/commit/'.$revision['commit'].'" title="'.htmlspecialchars($revision['message']).'">'.mb_substr($revision['commit'], 0, 8).'</a> oppdatert '.ess::$b->date->get($revision['date'])->format().'. <a href="&rpath;/github">Logg</a></p>';
		} else {
			echo '
		<p>Versjonsinformasjon er utilgjengelig.</p>';
		}
		
		echo '
		<p>Tid og dato ved visning: <b>'.self::$date_now->format(date::FORMAT_SEC).'</b>.</p>
	</div>'.ess::$b->page->body_end;
		
		// debug time
		/*$time = SCRIPT_START;
		ess::$b->dt("end");
		$dt = 'start';
		foreach (ess::$b->time_debug as $row)
		{
			$dt .= ' -> '.round(($row[1]-$time)*1000, 2).' -> '.$row[0];
			$time = $row[1];
		}*/
		/*if (MAIN_SERVER)
		{
			$text = ess::$b->date->get()->format("Y-m-d\tH:i:s\t")."{$_SERVER['REMOTE_ADDR']}\t{$_SERVER['REQUEST_METHOD']}\t{$_SERVER['REQUEST_URI']}\t".login::$user->player->id."\t".login::$user->player->data['up_name']."\t".round(microtime(true)-SCRIPT_START-ess::$b->db->time, 4)."\t".round(ess::$b->db->time, 4)."\t".ess::$b->db->queries."\t".str_replace(" -> ", "\t", $dt)."\n"; 
			@file_put_contents("/home/smafia/debugtime.log", $text, FILE_APPEND);
		}*/
		/*echo '
	<!-- '.$dt.' -->*/
		echo '
</body>
</html>';
	}
	
	/**
	 * Hent avstemningene
	 */
	protected static function get_polls()
	{
		// hent avstemningene
		$polls = cache::fetch("polls_list");
		
		// må opprette cache?
		if ($polls === false)
		{
			$polls = array();
			$time = time();
			
			// hent aktive avstemninger
			$result = \Kofradia\DB::get()->query("
				SELECT p_id, p_title, p_text, p_ft_id, p_params, p_time_end
				FROM polls
				WHERE p_active != 0 AND p_time_start <= $time AND (p_time_end = 0 OR p_time_end > $time)
				ORDER BY p_time_start DESC");
			
			// ingen avstemninger?
			if ($result->rowCount() == 0)
			{
				cache::store("polls_list", $polls, self::get_poll_cachetime());
				cache::delete("polls_options_list");
				return false;
			}
			
			while ($row = $result->fetch())
			{
				// sjekk forum id
				if (!$row['p_ft_id'])
				{
					$params = new params($row['p_params']);
					
					// har vi forum mal og nødvendig info?
					$text = $params->get("forum_text");
					$up_id = $params->get("forum_up_id");
					if ($text && $up_id && $params->get("forum_active"))
					{
						$title = "Avstemning: ".$row['p_title'];
						
						// forsøk å lag emnet først
						\Kofradia\DB::get()->beginTransaction();
						$update = \Kofradia\DB::get()->query("SELECT p_ft_id FROM polls WHERE p_id = {$row['p_id']} AND p_ft_id IS NOT NULL FOR UPDATE");
						
						// fremdeles ingen emner opprettet
						if (!$update->rowCount())
						{
							// opprett
							\Kofradia\DB::get()->exec("INSERT INTO forum_topics SET ft_type = 1, ft_title = ".\Kofradia\DB::quote($title).", ft_time = ".time().", ft_up_id = ".intval($up_id).", ft_text = ".\Kofradia\DB::quote($text).", ft_fse_id = 1, ft_locked = 0");
							
							$id = \Kofradia\DB::get()->lastInsertId();
							$row['p_ft_id'] = $id;
							
							// oppdater avstemningen
							\Kofradia\DB::get()->exec("UPDATE polls SET p_ft_id = $id WHERE p_id = {$row['p_id']}");
							
							// melding på IRC
							putlog("INFO", "%bFORUM EMNE%b: $title (%b".ess::$s['path']."/forum/topic?id=$id%b)\r\n");
						}
						
						else
						{
							$row['p_ft_id'] = $update->fetchColumn(0);
						}
						
						\Kofradia\DB::get()->commit();
					}
				}
				
				$polls[$row['p_id']] = $row;
			}
			
			// fjern alternativene
			cache::delete("polls_options_list");
			
			// lagre avstemningene
			cache::store("polls_list", $polls, self::get_poll_cachetime());
		}
		
		// hent alternativer
		$polls_options = cache::fetch("polls_options_list");
		
		// må opprette cache for alternativene?
		if (!$polls_options)
		{
			$polls_options = array();
			
			// har vi noen avstemninger å hente for?
			if (count($polls) > 0)
			{
				// hent alternativene
				$result = \Kofradia\DB::get()->query("
					SELECT po_id, po_p_id, po_text, po_votes
					FROM polls_options
					WHERE po_p_id IN (".implode(",", array_keys($polls)).")");
				while ($row = $result->fetch())
				{
					$polls_options[$row['po_p_id']]['options'][$row['po_id']] = $row;
					
					if (!isset($polls_options[$row['po_p_id']]['votes'])) $polls_options[$row['po_p_id']]['votes'] = 0;
					$polls_options[$row['po_p_id']]['votes'] += $row['po_votes'];
					
					if (!isset($polls_options[$row['po_p_id']]['votes_max']) || $row['po_votes'] > $polls_options[$row['po_p_id']]['votes_max']) $polls_options[$row['po_p_id']]['votes_max'] = $row['po_votes'];
				}
			}
			
			// lagre cache
			cache::store("polls_options_list", $polls_options);
		}
		
		// finn ut vårt valg
		$votes = array();
		
		// har vi noen avstemninger å hente for?
		if (count($polls) > 0)
		{
			$result = \Kofradia\DB::get()->query("
				SELECT pv_p_id, pv_po_id, pv_time
				FROM polls_votes
				WHERE pv_up_id = ".login::$user->player->id." AND pv_p_id IN (".implode(",", array_keys($polls)).")");
			while ($row = $result->fetch())
			{
				$votes[$row['pv_p_id']] = $row;
			}
		}
		
		return array(
			"polls" => $polls,
			"options" => $polls_options,
			"votes" => $votes
		);
	}
	
	/**
	 * Finn tidspunkt for når polls skal endres neste gang
	 */
	protected static function get_poll_cachetime()
	{
		static $default_time = 3600; // 1 time standard cache tid
		
		$time = time();
		$result = \Kofradia\DB::get()->query("
			SELECT MIN(t) FROM (
				SELECT MIN(p_time_end) t FROM polls WHERE p_active != 0 AND p_time_end > $time
				UNION ALL
				SELECT MIN(p_time_start) FROM polls WHERE p_active != 0 AND p_time_start > $time
			) ref");
		
		$n = $result->fetchColumn(0);
		
		// har ikke noe tidspunkt eller tid er over cache tid?
		if (!$n || $time+$default_time <= $n)
			return $default_time;
		
		// beregn hvor lang tid den skal stå
		return $n - $time;
	}
	
	protected static function check_lock()
	{
		if (defined("LOCK") && LOCK)
		{
			self::$locked = true;
		}
		
		elseif (!login::$user->player->active) $locked = true;
	}
	
	protected static function load_vars()
	{
		self::$num_pm = login::$user->data['u_inbox_new'];
		self::$num_log = login::$user->player->data['up_log_new'] + login::$user->player->data['up_log_ff_new'];
		
	}
	
	protected static function load_polls()
	{
		if (self::$locked) return;
		
		$polls = self::get_polls();
		if (!$polls || !$polls['polls']) return;
		
		// innhold på høyre siden
		$content = '';
		kf_menu::$data['is_avstemning'] = true;
		
		$i = 0;
		foreach ($polls['polls'] as $p_id => $poll)
		{
			$options = isset($polls['options'][$p_id]) ? $polls['options'][$p_id] : array();
			$vote = isset($polls['votes'][$p_id]) ? $polls['votes'][$p_id] : false;
			$voted = (bool)$vote;
			
			$content .= '
<div class="default_right_box r4">
	<!--<h1>Avstemninger</h1>-->';
			
			$content .= (!$voted ? '
	<form action="'.ess::$s['relative_path'].'/polls/vote" method="post">' : '').'
	<h1><b>'.htmlspecialchars($poll['p_title']).'</b></h1>';
			
			if (($bb = game::format_data($poll['p_text'])) != "")
			{
				$content .= '
	<div class="p">'.$bb.'</div>';
			}
			
			// link til forum emne?
			if ($poll['p_ft_id'])
			{
				$content .= '
	<p><a href="'.ess::$s['relative_path'].'/forum/topic?id='.$poll['p_ft_id'].'">Diskuter avstemning</a></p>';
			}
			
			$content .= '
	<div class="poll_options">';
			
			// alternativene
			foreach ($options['options'] as $option)
			{
				if ($voted)
				{
					$p = round($option['po_votes'] / $options['votes'] * 100, 1);
					$p_w = round($option['po_votes'] / $options['votes_max'] * 100, 1);
					$is = $option['po_id'] == $vote['pv_po_id'];
					
					// resultatet
					$content .= '
		<div class="poll_option'.($is ? ' voted' : '').'">
			<div class="p">'.game::format_data($option['po_text']).'</div>
			<div class="poll_option_bar_wrap">
				<div class="poll_option_bar" style="width: '.round($p_w).'%"><p>'.$p.' %'.($is ? ' (valgt)' : '').'</p></div>
			</div>
		</div>';
				}
				
				else
				{
					$content .= '
		<div class="p"><input type="radio" name="poll['.$poll['p_id'].']" value="'.$option['po_id'].'" id="poll_'.$poll['p_id'].'_'.$option['po_id'].'" /><label for="poll_'.$poll['p_id'].'_'.$option['po_id'].'"> '.game::format_data($option['po_text']).'</label></div>';
				}
			}
			
			$content .= (!$voted ? '
		<p class="c">'.show_sbutton("Avgi stemme", 'name="vote"').'</p>' : '').'
	</div>'.(!$voted ? '
	</form>' : '').($i == 0 ? '
	<p><a href="'.ess::$s['relative_path'].'/polls">Tidligere avstemninger &raquo;</a></p>' : '').'
</div>';
			
			$i++;
		}
		
		ess::$b->page->add_content_right($content, 1);
	}
	
	protected static function load_donations()
	{
		// hent siste donasjoner
		$donations = cache::fetch("donation_list");
		if (!$donations)
		{
			// lagre til cache for 10 minutter
			$result = \Kofradia\DB::get()->query("SELECT d_up_id, d_amount, d_time FROM donations ORDER BY d_time DESC LIMIT 6");
			$donations = array();
			while ($row = $result->fetch())
			{
				$donations[] = $row;
			}
			cache::store("donation_list", $donations, 600);
		}
		
		if (count($donations) == 0) return;
		
		$content = '
<div class="default_right_box r4">
	<h1>Siste donasjoner</h1>
	<dl class="dd_right" style="font-size: 10px">';
		
		foreach ($donations as $row)
		{
			$user = $row['d_up_id'] ? '<user id="'.$row['d_up_id'].'" />' : 'Anonym';
			$date = ess::$b->date->get($row['d_time']);
			
			$content .= '
			<dt>'.$user.'</dt>
			<dd>'.$date->format("j. ").$date->format(date::FORMAT_MONTH).'</dd>';
		}
		
		$content .= '
	</dl>
	<p><a href="'.ess::$s['relative_path'].'/donasjon">Bidra til utviklingen av spillet &raquo;</a></p>
</div>';
		
		ess::$b->page->add_content_right($content, 1);
	}
	
	protected static function get_extended_access_links()
	{
		if (!isset(login::$extended_access)) return;
		if (!login::extended_access_is_authed()) return;
		
		// sjekk for endringer i forumet
		$fc = array();
		for ($i = 5; $i <= 7; $i++)
		{
			$t = isset(game::$settings["forum_{$i}_last_change"]) ? game::$settings["forum_{$i}_last_change"]['value'] : false;
			$l = login::$user->params->get("forum_{$i}_last_view");
			$fc[$i] = $t && $t > $l;
		}
		
		// sjekk for endringer i wikien
		$wiki_link = '<a href="https://kofradia.no/crewstuff/wiki/">wiki</a>';
		if (isset(game::$settings['wiki_last_changed']))
		{
			if (game::$settings['wiki_last_changed']['value'] != login::$user->params->get("wiki_last_changed"))
			{
				$wiki_link = '<a href="'.ess::$s['relative_path'].'/crew/wikichanges" class="crew_updates">wiki</a>';
			}
		}
		
		$data = '
			<ul id="clinks">
				<li><a href="'.ess::$s['relative_path'].'/crew/">Crew</a> - <a href="'.ess::$s['relative_path'].'/crew/crewlogg">logg</a> - '.$wiki_link.'</li>
				<li><a href="'.ess::$s['relative_path'].'/crewstuff/f/" target="_blank">Filer</a> - <a href="https://github.com/hswno/kofradia/pulse" target="_blank">GitHub</a> - <a href="https://kofradia.no/crewstuff/" target="_blank">Stuff</a></li>';
		
		if (access::has("crewet")) $data .= '
				<li><a href="'.ess::$s['relative_path'].'/forum/forum?id=5"'.($fc[5] ? ' class="crew_updates"' : '').'>Crewforum</a> - <a href="'.ess::$s['relative_path'].'/forum/forum?id=6"'.($fc[6] ? ' class="crew_updates"' : '').'>arkiv</a></li>';

        if (access::has("seniormod")) $data .= '
                <li><a href="'.ess::$s['relative_path'].'/forum/forum?id=7"'.($fc[7] ? ' class="crew_updates"' : '').'>Idémyldringsforum</a></li>
                <li><a href="'.ess::$s['relative_path'].'/forum/forum?id=4">Evalueringsforum</a></li>';

		if (access::has("crewet")) $data .= '
				<li><a href="'.ess::$s['relative_path'].'/crew/rapportering">Rapportering</a></li>
				<li><a href="'.ess::$s['relative_path'].'/support/?a=panel&amp;kategori=oppsummering">Support-panel</a></li>';

		
		if (access::has("mod")) $data .= '
				<li><a href="'.ess::$s['relative_path'].'/henvendelser?a">Henvendelser</a></li>';
		
		if (access::has("mod")) $data .= '
				<li><a href="'.ess::$s['relative_path'].'/admin/">Administrasjon</a></li>';
		
		$data .= '
				<li><a href="'.ess::$s['relative_path'].'/extended_access?logout&amp;orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg ut</a></li>
			</ul>';
		
		return $data;
	}
	
	protected static function get_extended_access_login()
	{
		if (!isset(login::$extended_access)) return;
		if (login::extended_access_is_authed()) return;
		
		$data = '
			<div id="clogin">
				<h1 class="bg1">Crew</h1>
				<div class="bg1">';
		
		// har ikke passord?
		if (!isset(login::$extended_access['passkey']))
		{
			$data .= '
					<p>Du har ikke opprettet et crewpassord.</p>
					<p><a href="'.ess::$s['relative_path'].'/extended_access?create">Opprett passord &raquo;</a></p>';
		}
		
		else
		{
			// logg inn skjema
			$data .= '
					<form action="'.ess::$s['relative_path'].'/extended_access?login&amp;orign='.urlencode($_SERVER['REQUEST_URI']).'" method="post" autocomplete="off">
						<dl class="dd_right">
							<dt>Pass</dt>
							<dd><input type="password" name="password" class="styled w100" /></dd>
						</dl>
						<p class="r">
							<span style="float: left"><a href="'.ess::$s['relative_path'].'/extended_access?forgot">Glemt passord &raquo;</a></span>
							<a href="'.ess::$s['relative_path'].'/extended_access">Info &raquo;</a>
						</p>
					</form>';
		}
		
		$data .= '
				</div>
			</div>';
		
		return $data;
	}
	
	protected static function get_extended_access_search()
	{
		if (!access::has("mod")) return;
		
		return '
			<div id="cusearch">
				<h2 class="bg1">Finn spiller<span class="left2"></span><span class="right2"></span></h2>
				<div class="bg1">
					<form action="'.ess::$s['relative_path'].'/admin/brukere/finn" method="get" target="_blank">
						<dl class="dd_right dl_1x">
							<dt>Bruker-ID</dt>
							<dd><input type="text" name="u_id" class="styled w40" /></dd>
							<dt>Spiller-ID</dt>
							<dd><input type="text" name="up_id" class="styled w40" /></dd>
							<dt>Spillernavn</dt>
							<dd><input type="text" name="name" class="styled w80" /></dd>
							<dt>IP</dt>
							<dd><input type="text" name="ip" class="styled w80" /></dd>
							<dt>E-post</dt>
							<dd><input type="text" name="email" class="styled w80" /></dd>
						</dl>
						<p class="c">'.show_sbutton("Finn spiller").'</p>
					</form>
				</div>
			</div>';
	}
	
	
	protected static function get_revision_info()
	{
		$branch = "";

		// hent informasjon fra Git
		$data = @file_get_contents(PATH_ROOT."/.git/HEAD");
		if (!$data) return null;

		if (mb_substr($data, 0, 3) == "ref") {
			$ref = trim(mb_substr($data, 5));
			$commit = @file_get_contents(PATH_ROOT."/.git/$ref");
			$branch = basename($ref);
		} else {
			$commit = trim($data);
			$branch = $commit;
		}

		if (!$commit) return null;

		// hent tidspunkt og melding
		$last_rev = cache::fetch("gitlog_last_rev");
		$last_info = cache::fetch("gitlog_last_info");
		if (!$last_rev || $last_rev != $commit) {
			$res = shell_exec("git log -1 --format=\"%ct %s\"");
			$last_info = sscanf($res, "%d %[^$]s");
			cache::store("gitlog_last_info", $last_info);
		}

		$r = array(
			"branch" => $branch,
			"commit" => $commit,
			"date" => $last_info[0],
			"message" => $last_info[1]
		);
		
		return $r;
	}
	
	protected static function get_blog_links()
	{
		$blog = isset(game::$settings['wordpress_data']) ? unserialize(game::$settings['wordpress_data']['value']) : false;
		if (!$blog) return;
		
		$show = 3; // antall innlegg som skal vises
		$expire = time() - 604800; // hvor gamle innlegg som skal vises (alltid 1 innlegg vises)
		
		$list = array();
		$i = 0;
		while ($row = current($blog))
		{
			if ($i++ == $show || ($row['time'] < $expire && $i > 1)) break;
			$list[] = '<a href="'.htmlspecialchars($row['link']).'">'.$row['title'].'</a> ('.ess::$b->date->get($row['time'])->format("d.m.y").')';
			next($blog);
		}
		
		return $list;
	}
	
	protected static function get_status_bars()
	{
		if (self::$locked) return;
		$bars = array();
		
		// helse
		$health = login::$user->player->get_health_percent();
		$bars[] = array(
			'Helse',
			($health == 100 ? '100' : game::format_num($health, 2)).' %',
			ess::$s['relative_path'].'/min_side',
			$health < 20 ? 'levelcrit' : ($health < 50 ? 'levelwarn' : ''),
			'upst_health',
			$health
		);
		
		// energi
		$energy = login::$user->player->get_energy_percent();
		$bars[] = array(
			'Energi',
			($energy == 100 ? '100' : game::format_num($energy, 2)).' %',
			ess::$s['relative_path'].'/min_side',
			$energy < 20 ? 'levelcrit' : ($energy < 50 ? 'levelwarn' : ''),
			'upst_energy',
			$energy
		);
		
		// beskyttelse
		$protection = login::$user->player->get_protection_percent();
		$bars[] = array(
			'Beskyttelse',
			($protection === false ? 'Ingen' : ($protection == 100 ? '100' : game::format_num($protection, 2)) . ' %'),
			ess::$s['relative_path'].'/min_side',
			$protection !== false ? ($protection < 20 ? 'levelcrit' : ($protection < 50 ? 'levelwarn' : '')) : '',
			'upst_protection',
			$protection
		);
		
		if (!login::$user->params->get("hide_progressbar_left"))
		{
			$rank_prosent = login::$user->player->rank['need_points'] == 0 ? login::$user->player->data['up_points'] / login::$user->player->rank['points'] * 100 : (login::$user->player->data['up_points']-login::$user->player->rank['points']) / login::$user->player->rank['need_points'] * 100;
			$wl = login::$user->player->data['up_wanted_level']/10;
			
			// rank
			$bars[] = array(
				'Poeng',
				game::format_num(login::$user->player->data['up_points']),
				ess::$s['relative_path'].'/min_side',
				'',
				'upst_rank',
				$rank_prosent
			);
			
			// wanted nivå
			$bars[] = array(
				'Wanted nivå',
				($wl == 0 ? '0' : game::format_num($wl, 1)).' %',
				ess::$s['relative_path'].'/min_side',
				$wl > 80 ? 'levelwarn' : '',
				'upst_wanted',
				$wl,
				'Wanted nivå'
			);
		}
		
		$data = '
			<div id="default_status">';
		
		$c = count($bars);
		$max = 100 - $c * 2 + 2;
		$width = floor($max / $c);
		$width_extra = $max - $width * $c;
		$i = 0;
		foreach ($bars as $bar)
		{
			/*
			 * 0 => tekst
			 * 1 => verdi
			 * 2 => link
			 * 3 => class
			 * 4 => id
			 * 5 => prosent 0-100
			 * 6 => title-attr
			 */
			
			// legge til ekstra bredde hvis det totalt sett ikke blir 100 %
			$w = $width;
			if ($width_extra-- > 0) $w++;
			
			$data .= '
				<a href="'.$bar[2].'" class="def_up_st'.($bar[3] ? ' '.$bar[3] : '').(++$i == $c ? ' last' : '').'" id="'.$bar[4].'" style="width: '.$w.'%">
					<span class="def_upst_t">'.$bar[0].'</span>
					<span class="def_upst_v">'.$bar[1].'</span>
					<span class="def_upst_pw r4">
						<span class="def_upst_p r4" style="width: '.min(100, round($bar[5])).'%"></span>
					</span>
				</a>';
		}
		
		return $data . '
			</div>';
		
		/*
			
				<a href="'.ess::$s['relative_path'].'/min_side" class="def_up_st progressbar'.($health < 20 ? ' levelcrit' : ($health < 50 ? ' levelwarn' : '')).'" id="upst_health">
					<span class="progress" style="width: '.min(100, round($health)).'%"><span>Helse: '.($health == 100 ? '100' : game::format_num($health, 2)).' %</span></span>
				</a>
				<a href="'.ess::$s['relative_path'].'/min_side" class="def_up_st progressbar def_up_st2'.($energy < 20 ? ' levelcrit' : ($energy < 50 ? ' levelwarn' : '')).'" id="upst_energy">
					<span class="progress" style="width: '.min(100, round($energy)).'%"><span>Energi: '.($energy == 100 ? '100' : game::format_num($energy, 2)).' %</span></span>
				</a>
				<a href="'.ess::$s['relative_path'].'/min_side" class="def_up_st progressbar def_up_st3'.($protection !== false ? ($protection < 20 ? ' levelcrit' : ($protection < 50 ? ' levelwarn' : '')) : '').'" id="upst_protection">
					<span class="progress" style="width: '.min(100, round($protection)).'%"><span>Beskyttelse: '.($protection === false ? 'Ingen' : ($protection == 100 ? '100' : game::format_num($protection, 2)) . " %").'</span></span>
				</a>';
			
			if (!login::$user->params->get("hide_progressbar_left"))
			{
				$wl = login::$user->player->data['up_wanted_level']/10;
				
				// rankinfo
				$rank_prosent = login::$user->player->rank['need_points'] == 0 ? login::$user->player->data['up_points'] / login::$user->player->rank['points'] * 100 : (login::$user->player->data['up_points']-login::$user->player->rank['points']) / login::$user->player->rank['need_points'] * 100;
				
				echo '
				<a href="'.ess::$s['relative_path'].'/min_side" class="def_up_st progressbar def_up_st4" id="upst_rank">
					<span class="progress" style="width: '.min(100, round($rank_prosent)).'%">
						<span>Poeng: '.game::format_num(login::$user->player->data['up_points']).'</span>
					</span>
				</a>
				<a href="'.ess::$s['relative_path'].'/min_side" class="def_up_st progressbar def_up_st5'.($wl > 80 ? ' levelwarn' : '').'" id="upst_wanted">
					<span class="progress" style="width: '.round(min(100, $wl)).'%">
						<span title="Wanted nivå">Wanted: '.($wl == 0 ? '0' : game::format_num($wl, 1)).' %</span>
					</span>
				</a>';
			}
		}*/
	}
	
	protected static function get_oppdrag_status()
	{
		// ikke på et aktivt oppdrag?
		if (!login::$user->player->oppdrag->active) return '';
		
		$ret = '
			<section id="default_oppdrag">
				<h1>Oppdrag: '.login::$user->player->oppdrag->active['o_title'].'</h1>'.login::$user->player->oppdrag->status(login::$user->player->oppdrag->active['o_id'], true).'
				<p id="default_oppdrag_link"><a href="&rpath;/oppdrag"></a></p>
			</section>';
		
		return $ret;
	}
}

theme_sm_default::main();
