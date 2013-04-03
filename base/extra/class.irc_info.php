<?php

abstract class irc_info
{
	/** Parametere for kommandoen */
	protected $cmd_x;
	
	/** Send melding til endepunktet */
	abstract public function send_output($text);
	
	/** Gå gjennom tekst som skal behandles (kommandoen) */
	public function parse_request($text)
	{
		// script for info til IRC (mIRC kaller denne filen)
		$this->cmd_x = explode(" ", $text, 2);
		$cmd = preg_replace("/[^a-zA-Z0-9\\_]/u", "", $this->cmd_x[0]);
		
		// finnes denne funksjonen?
		if (method_exists($this, "c_" . $cmd))
		{
			call_user_func(array($this, "c_" . $cmd));
		}
		
		// liste opp alt?
		elseif ($cmd == "help")
		{
			$list = array();
			foreach (get_class_methods($this) as $row)
			{
				if (mb_substr($row, 0, 2) == "c_") $list[] = mb_substr($row, 2);
			}
			sort($list);
			
			$this->send_output("Tilgjengelige kommandoer:");
			foreach ($list as $row)
			{
				$this->send_output(" - " . $row);
			}
		}
		
		else
		{
			$this->send_output("Ukjent kommando.");
		}
	}
	
	/** Hent info for en spiller */
	protected function hent_bruker_info($navn, $finn)
	{
		if (mb_substr($navn, 0, 1) == "#" || mb_substr($navn, 0, 1) == "%")
		{
			$where = "up_id = ".intval(mb_substr($navn, 1));
		}
		else
		{
			$where = "up_name LIKE '".str_replace(array("%", "*"), array("\\%", "%"), addslashes($navn))."'";
		}
		$result = ess::$b->db->query("
			SELECT $finn
			FROM users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE $where ORDER BY up_last_online DESC LIMIT 1");
		return mysql_fetch_assoc($result);
	}
	
	/** Regstats */
	public function c_regstats()
	{
		$date = $this->cmd_x[1];
		if (empty($date)) $date = ess::$b->date->get(time())->format("Y-m-d");
		if (!preg_match("/^200[6-9](-[0-9]{2}){2}$/Du", $date))
		{
			$this->send_output("Ugyldig inntasting. Syntax: yyyy-mm-dd");
		}
		else
		{
			$result = ess::$b->db->query("SELECT COUNT(up_id), DATE(FROM_UNIXTIME(up_created_time)) AS day FROM users_players WHERE DATE(FROM_UNIXTIME(up_created_time)) = '$date' GROUP BY day");
			$ant = game::format_number(intval(mysql_result($result, 0)));
			$this->send_output("Antall registrerte $date: $ant");
		}
	}
	
	/** Klokka */
	public function c_time()
	{
		$this->send_output("Server tid: ".ess::$b->date->get(time())->format(date::FORMAT_SEC));
	}
	
	/** Antall pålogget */
	public function c_online()
	{
		$time = isset($this->cmd_x[1]) ? intval($this->cmd_x[1]) : 0;
		if ($time == 0 || $time < 0)
		{
			$time = 300;
		}
		
		$last = time()-$time;
		$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online >= $last");
		$ant = game::format_number(mysql_result($result, 0));
		
		$time = game::timespan($time, game::TIME_NOBOLD);
		$this->send_output("%bAntall pålogget siste $time%b: %u$ant%u");
	}
	
	/** Antall pålogget (kort tid) */
	public function stats_short()
	{
		$result = ess::$b->db->query("SELECT COUNT(IF(up_last_online > ".(time()-60).", 1, NULL)), COUNT(IF(up_last_online > ".(time()-10).", 1, NULL)) FROM users_players");
		$this->send_output("Siste 60 sekundene: ".mysql_result($result, 0, 0));
		$this->send_output("Siste 10 sekundene: ".mysql_result($result, 0, 1));
		#$this->send_output("stats_short er ikke lengre tilgjengelig!");
		/*
		$time_10 = time()-10;
		$time_60 = time()-60;
		$time_300 = time()-300;
		$time_600 = time()-600;
		$time_1800 = time()-1800;
		$result = ess::$b->db->query("SELECT COUNT(IF(time > $time_10, id, NULL)), COUNT(IF(time > $time_60, id, NULL)), COUNT(IF(time > $time_300, id, NULL)), COUNT(IF(time > $time_600, id, NULL)), COUNT(IF(time > $time_1800, id, NULL)) FROM requests");
		
		$hits_10 = game::format_number(mysql_result($result, 0, 0));
		$hits_60 = game::format_number(mysql_result($result, 0, 1));
		$hits_300 = game::format_number(mysql_result($result, 0, 2));
		$hits_600 = game::format_number(mysql_result($result, 0, 3));
		$hits_1800 = game::format_number(mysql_result($result, 0, 4));
		
		$hits_10_m = game::format_number(mysql_result($result, 0, 0)/10);
		$hits_60_m = game::format_number(mysql_result($result, 0, 1)/60);
		$hits_300_m = game::format_number(mysql_result($result, 0, 2)/300);
		$hits_600_m = game::format_number(mysql_result($result, 0, 3)/600);
		$hits_1800_m = game::format_number(mysql_result($result, 0, 4)/1800);
		
		$this->send_output("%bHits siste 10 sec:%b $hits_10 ($hits_10_m/sec)");
		$this->send_output("%bHits siste minuttet:%b $hits_60 ($hits_60_m/sec)");
		$this->send_output("%bHits siste 5 min:%b $hits_300 ($hits_300_m/sec)");
		$this->send_output("%bHits siste 10 min:%b $hits_600 ($hits_600_m/sec)");
		$this->send_output("%bHits siste 30 min:%b $hits_1800 ($hits_1800_m/sec)");*/
	}
	
	/** Antall hits */
	public function c_hits()
	{
		// antall visninger og slikt
		$date = ess::$b->date->get(time());
		$date->setTime(0, 0, 0);
		$idag = $date->format("U");
		$date->modify("-1 day");
		$igaar = $date->format("U");
		$date->modify("-1 day");
		$igaar2 = $date->format("U");
		$result = ess::$b->db->query("
			SELECT SUM(up_hits) FROM users_players
			UNION ALL
			SELECT SUM(uhi_hits) FROM users_hits WHERE uhi_secs_hour >= $idag
			UNION ALL
			SELECT SUM(uhi_hits) FROM users_hits WHERE uhi_secs_hour < $idag AND uhi_secs_hour >= $igaar
			UNION ALL
			SELECT SUM(uhi_hits) FROM users_hits WHERE uhi_secs_hour < $igaar AND uhi_secs_hour >= $igaar2");
		$total = game::format_number(mysql_result($result, 0));
		$today = game::format_number(mysql_result($result, 1));
		$day_1 = game::format_number(mysql_result($result, 2));
		$day_2 = game::format_number(mysql_result($result, 3));
		
		$this->send_output("%bHits statistikk:%b");
		$this->send_output("%bTotalt:%b %c5%u$total%u");
		$this->send_output("%bI dag:%b %c7%u$today%u");
		$this->send_output("I går: $day_1");
		$this->send_output("I forigårs: $day_2");
	}
	
	/** Belastning på serveren */
	public function c_load()
	{
		$oppetid = shell_exec('uptime');
		$oppetid = str_replace("\n", " - ", $oppetid);
		$this->send_output("%bSERVER LOAD:%b $oppetid");
	}
	
	/** Antall uleste meldinger for en spiller */
	public function c_unread()
	{
		$player = $this->cmd_x[1];
		
		// skjekk om spiller mangler -- send hjelp
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info unread <spiller>");
		}
		
		// fortsett
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_id, up_name, up_u_id"))
			{
				// hent antall uleste og antall totalt
				$result = ess::$b->db->query("
					SELECT u_inbox_new, SUM(im_id)
					FROM users
						LEFT JOIN inbox_rel ON ir_up_id = {$row['up_id']}
						LEFT JOIN inbox_messages ON im_it_id = ir_it_id AND im_time <= ir_restrict_im_time
					WHERE u_id = {$row['up_u_id']}
					GROUP BY u_id");
				$ant = mysql_result($result, 0);
				
				// hent antall uleste meldinger
				$this->send_output("%b{$row['up_name']}%b har %b".game::format_number($ant)."%b ".($ant == 1 ? 'ulest melding' : 'uleste meldinger')." av totalt %b".game::format_number($ant)."%b ".($ant == 1 ? 'melding' : 'meldinger')." i sin innboks!");
			}
			
			// fant ikke
			else
			{	
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Når en spiller sist var pålogget */
	public function c_last_online()
	{
		$player = $this->cmd_x[1];
		
		// skjekk om spiller mangler -- send hjelp
		if (empty($player))
		{	
			$this->send_output("%bSyntax:%b !info online <spiller>");
		}
		
		// fortsett
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_id, up_name, up_last_online"))
			{
				$this->send_output("%b{$row['up_name']}%b var sist aktiv %u".game::timespan($row['up_last_online'], game::TIME_ABS | game::TIME_NOBOLD)."%u siden");
			}
			
			// fant ikke
			else
			{	
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hvem som har flest uleste meldinger */
	public function c_maxunread()
	{
		global $__server;
		$skip = intval($this->cmd_x[1]);
		
		$result = ess::$b->db->query("SELECT up_id, up_name, u_inbox_new FROM users JOIN users_players ON u_id = up_u_id ORDER BY u_inbox_new DESC, up_last_online DESC LIMIT 1");
		$player = mysql_fetch_assoc($result);
		
		$this->send_output("%b{$player['up_name']}%b har flest uleste meldinger. %b{$player['u_inbox_new']}%b ".($player['u_inbox_new'] == 1 ? 'ulest melding' : 'uleste meldinger')."! ({$__server['absolute_path']}{$__server['relative_path']}/innboks?user=".urlencode($player['up_name']).")");
	}
	
	/** Tidspunkt (formattert) */
	public function c_timestamp()
	{
		$this->send_output(ess::$b->date->get(intval($this->cmd_x[1]))->format(date::FORMAT_SEC));
	}
	
	/** Antall registrerte spillere på en dag */
	public function c_regstats_last()
	{
		$date = ess::$b->date->get(time());
		$date_max = $date->format("Y-m-d");
		
		$dager = 5;
		$date->modify("-".($dager-1)." days");
		$date = $date->format("Y-m-d");
		$result = ess::$b->db->query("SELECT COUNT(up_id) AS ant, DATE(FROM_UNIXTIME(up_created_time)) AS day FROM users_players WHERE DATE(FROM_UNIXTIME(up_created_time)) >= '$date' AND DATE(FROM_UNIXTIME(up_created_time)) <= '$date_max' GROUP BY day ORDER BY day DESC");
		if (mysql_num_rows($result) == 0)
		{
			$this->send_output("Ingen registrerte de siste $dager dagene..");
		}
		else
		{
			$this->send_output("Antall registrasjoner siste $dager dagene:");
			while ($row = mysql_fetch_assoc($result))
			{
				$ant = game::format_number($row['ant']);
				$this->send_output("%b{$row['day']}%b: {$ant}");
			}
		}
	}
	
	/** Finn ut plasseringen til en spiller */
	public function c_bydel()
	{
		global $_game;
		$finn = $this->cmd_x[1];
		
		// skjekk om spiller mangler -- send hjelp
		if (empty($finn))
		{	
			$this->send_output("%bSyntax:%b !info bydel <bydelnavn>");
		}
		
		// fortsett
		else
		{
			// finn bydelen
			$bydel = false;
			foreach (game::$bydeler as $row)
			{
				if ($row['name'] == $finn)
				{
					$bydel = $row;
					break;
				}
			}
			
			if (!$bydel)
			{
				$this->send_output("%bBydel%: Bydelen %u$finn%u finnes ikke!");
				return;
			}
			
			$this->send_output("%bBydel:%b Informasjon om %u{$bydel['name']}%u:");
			
			// finn ut hvor mange som bor i denne bydelen
			$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']} AND up_b_id = {$bydel['id']}");
			$this->send_output("%bBosettere:%b %u".game::format_number(mysql_result($result, 0))."%u spillere");
			
			// antall biler
			$result = ess::$b->db->query("SELECT COUNT(users_gta.id) FROM users_gta LEFT JOIN users_players ON up_access_level != 0 AND up_access_level < {$_game['access_noplay']} AND up_id = ug_up_id WHERE users_gta.b_id = {$bydel['id']} GROUP BY users_gta.b_id");
			$this->send_output("%bBiler:%b %u".game::format_number(mysql_result($result, 0))."%u biler befinner seg i denne bydelen");
		}
	}
	
	/** Hent rankinformasjon for en spiller */
	public function c_rank()
	{
		global $_game;
		$player = $this->cmd_x[1];
		
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info rank <spiller>");
		}
		
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_name, up_points, up_created_time, upr_rank_pos, up_access_level"))
			{
				$points_max = end(game::$ranks['items']);
				$points_max = $points_max['points'];
				
				// antall prosent -- hele spillet
				$percent = round($row['up_points'] / $points_max * 100, 3);
				
				// hvilken rank -- nåværende
				$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
				
				// hvilken rank -- den neste
				if ($rank_neste = game::next_rank($row['up_points']))
				{
					// antall prosent -- neste rank
					$points_needed = $rank_neste['points'] - $rank['points'];
					$percent_next = round(100 - round(($row['up_points'] - $rank['points']) / $points_needed * 100, 3), 3);
					
					$this->send_output("%b{$row['up_name']}%b har {$row['up_points']} rankpoeng, er %b{$rank['name']}%b og mangler %b$percent_next%b % fra å bli {$rank_neste['name']}, og har fullført spillet med %b$percent %%b!");
					
					
					$tid_start = $row['up_created_time'];
					$tid_idag = time();
					$prosent = $percent;
					
					$tid = $tid_idag - $tid_start;
					$tid *= 100/$prosent;
					
					$tid_pre = round($tid_idag - $tid);
					$tid_post = round($tid_idag + $tid);
					
					$this->send_output("Det vil ta %b{$row['up_name']}%b %u".game::timespan($tid_pre, game::TIME_ABS | game::TIME_NOBOLD | game::TIME_FULL)."%u å nå den høyeste ranken! (%u".ess::$b->date->get($tid_post)->format()."%u)");
				}
				
				else
				{
					$this->send_output("%b{$row['up_name']}%b har {$row['up_points']} rankpoeng og er %b{$rank['name']}%b, som er den høyeste ranken! I forhold til hele spillet har han fullført det %b$percent %%b!");
				}
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent rankplasseringen til en spiller */
	public function c_rankplassering()
	{
		global $_game;
		$player = $this->cmd_x[1];
		
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info rankplassering <spiller>");
		}
		
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_name, up_points, up_access_level, upr_rank_pos"))
			{
				// hvilken rank -- nåværende
				$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
				$rank['name'] = strip_tags($rank['name']);
				
				$this->send_output("%b{$row['up_name']}%b er %b{$rank['name']}%b og ligger på %b{$row['upr_rank_pos']}%b. plass!".($row['up_access_level'] >= $_game['access_noplay'] ? ' (NoStatUser)' : ''));
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent informasjon om en spiller */
	public function c_spiller()
	{
		global $_game;
		
		$player = $this->cmd_x[1];
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info spiller <spiller>");
		}
		
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_id, up_name, up_points, up_last_online, up_hits, up_cash, up_b_id, up_bank, upr_rank_pos, up_access_level"))
			{
				$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
				$real = $rank['orig'] ? '%b ('.$rank['orig'].')' : '';
				$rank = strip_tags($rank['name']) . $real;
				$last_online_delay = game::timespan($row['up_last_online'], game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD);
				$cash = game::format_cash($row['up_cash']);
				$bydel = game::$bydeler[$row['up_b_id']]['name'];
				$bank = game::format_cash($row['up_bank']);
				$hits = game::format_number($row['up_hits']);
				
				$this->send_output("Info for %b{$row['up_name']}%b (#{$row['up_id']}):");
				$this->send_output("Rank: %b$rank");
				$this->send_output("Bydel: %b$bydel");
				$this->send_output("Penger: %b$cash%b - I banken: %b$bank");
				$this->send_output("Sist pålogget: %b$last_online_delay");
				$this->send_output("Antall sidevisninger: %b$hits");
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Sjekk etter en spiller og gi lenke til profil */
	public function c_profile()
	{
		global $__server;
		
		$finn = $this->cmd_x[1];
		if (!($info = $this->hent_bruker_info($finn, "up_name")))
		{
			$this->send_output("Fant ikke brukeren.");
		}
		else
		{
			$this->send_output($__server['absolute_path'].$__server['relative_path']."/p/".rawurlencode($info['up_name'])."/".$info['up_id']);
		}
	}
	
	/** Søk etter en spiller */
	public function c_finnspiller()
	{
		global $__server;
		
		$find = $this->cmd_x[1];
		if (empty($find))
		{
			$this->send_output("%bSyntax:%b !info finnspiller <søketter>");
			$this->send_output("Bruk * dersom du ikke vet hva som er mellom..");
		}
		
		else
		{
			if (mb_substr($find, 0, 1) == "!" || mb_substr($find, 0, 1) == "%")
			{
				$find = 'up_id = '.intval(mb_substr($find, 1));
			}
			else
			{
				$find = "up_name LIKE '".str_replace(array("%", "*"), array("\\%", "%"), addslashes($find))."%'";
			}
			
			// hvor mange?
			$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE $find");
			$ant = mysql_result($result, 0, 0);
			
			if ($ant == 0)
			{
				$this->send_output("Fant ingen spillere med navn lik %b{$this->cmd_x[1]}%b..");
			}
			
			else
			{
				if ($ant <= 5)
				{
					$this->send_output("Fant %b$ant%b spiller".($ant == 1 ? '' : 'e').":");
					$orderby = "up_name";
				}
				else
				{
					$this->send_output("Fant %$ant% spillere.. Viser 5 tilfeldige:");
					$orderby = "RAND()";
				}
				
				$result = ess::$b->db->query("SELECT up_id, up_name FROM users_players WHERE $find ORDER BY $orderby LIMIT 5");
				$i = 0;
				while ($row = mysql_fetch_assoc($result))
				{
					$i++;
					$this->send_output("%b$i:%b (#{$row['up_id']}) {$row['up_name']} - {$__server['absolute_path']}{$__server['relative_path']}/p/".rawurlencode($row['up_name'])."/".$row['up_id']);
				}
			}
		}
	}
	
	/** Hent statistikk for en spiller */
	public function c_spillerstats()
	{
		$player = $this->cmd_x[1];
		$toptoday = false;
		if (mb_substr($player, 0, 10) == "!toptoday ")
		{
			$toptoday = true;
			$player = mb_substr($player, 10);
		}
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info spillerstats [!toptoday ]<spiller>");
		}
		
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_id, up_name, up_hits"))
			{
				$date = ess::$b->date->get(time());
				$date->setTime(0, 0, 0);
				$time_today = $date->format("U");
				
				$w = date("w", $time_today);
				if ($w == 0) $w = 7;
				$w--;
				
				$date->modify("-$w days");
				$time_week_start = $date->format("U");
				
				// hent stats
				$result = ess::$b->db->query("SELECT SUM(IF(uhi_secs_hour >= $time_today, uhi_hits, 0)) AS hits_today, SUM(IF(uhi_secs_hour >= $time_week_start, uhi_hits, 0)) AS hits_week FROM users_hits WHERE uhi_up_id = {$row['up_id']}");
				
				$stats = mysql_fetch_assoc($result);
				
				// vis stats
				$this->send_output("Statistikk for %b{$row['up_name']}%b:");
				$this->send_output("Totalt antall visninger: %b".game::format_number($row['up_hits']));
				$this->send_output("Antall visninger denne uken: %b".game::format_number($stats['hits_week']));
				$this->send_output("Antall visninger siden midnatt: %b".game::format_number($stats['hits_today']));
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent liste over rankene */
	public function c_ranks()
	{
		$this->send_output("%bListe over rankene:");
		$ranks = game::$ranks['items_number'];
		krsort($ranks);
		foreach ($ranks as $number => $rank)
		{
			$this->send_output("%b$number%b: ".$rank['name']);
		}
	}
	
	/** Hent statistikk */
	public function c_stats()
	{
		global $_game;
		$online_min = 15;
		
		// hent nøkkeltall
		$result = ess::$b->db->query("
		SELECT COUNT(up_id) FROM users_players WHERE up_access_level < {$_game['access_noplay']}
		UNION ALL
		SELECT SUM(up_cash) FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']}
		UNION ALL
		SELECT SUM(up_bank) FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']}
		UNION ALL
		SELECT COUNT(up_id) FROM users_players WHERE up_access_level = 0
		UNION ALL
		SELECT COUNT(up_id) FROM users_players WHERE up_last_online >= ".(time()-$online_min*60));
		
		$players = game::format_number(mysql_result($result, 0));
		$cash = game::format_cash(mysql_result($result, 1));
		$bank = game::format_cash(mysql_result($result, 2));
		$deaths = game::format_number(mysql_result($result, 3));
		$living = game::format_number(mysql_result($result, 0)-mysql_result($result, 3));
		$online = game::format_number(mysql_result($result, 4));
		$this->send_output("%bStatistikk:%b");
		$this->send_output("Antall spillere: %u$players%u (%u$living%u lever)");
		$this->send_output("Totalt penger på hånda: %u$cash%u");
		$this->send_output("Totalt penger i bankene: %u$bank%u");
		$this->send_output("Antall pålogget siste 15 minuttene: %u$online%u");
	}
	
	/** Hent antall spillere */
	public function c_users()
	{
		global $_game;
		
		$result = ess::$b->db->query("SELECT COUNT(up_id) AS tot, COUNT(IF(up_access_level != 0, 1, NULL)) AS living FROM users_players WHERE up_access_level < {$_game['access_noplay']}");
		$row = mysql_fetch_assoc($result);
		
		$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players");
		$ant = mysql_result($result, 0);
		
		$this->send_output("%bAntall spillere:%b %u".game::format_number($row['living'])."%u av ".game::format_number($row['tot'])." lever (altså %u".game::format_number($row['tot']-$row['living'])."%u døde) (totalt %u".game::format_number($ant)."%u med nostats)");
	}
	
	/** Hent pengestatistikk */
	public function c_penger()
	{
		global $_game;
		$cash = array();
		
		$result = ess::$b->db->query("SELECT SUM(up_cash) + SUM(up_bank) FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']}");
		$current = mysql_result($result, 0);
		$cash[] = $current;
		$this->send_output("%bPenger i spillet%b: " . game::format_cash($current));
		
		$result = ess::$b->db->query("SELECT SUM(poker_cash * IF(poker_challenger_up_id=0,1,2)) FROM poker WHERE poker_state <= 3");
		$current = mysql_result($result, 0);
		$cash[] = $current;
		$this->send_output("%bPenger i pokeren%b: " . game::format_cash($current));
		
		$result = ess::$b->db->query("SELECT SUM(ff_bank) FROM ff");
		$current = mysql_result($result, 0);
		$cash[] = $current;
		$this->send_output("%bPenger i FF%b: " . game::format_cash($current));
		
		// totalt
		$result = ess::$b->db->query("SELECT ".implode(" + ", $cash));
		$this->send_output("%bTotalt%b: " . game::format_cash(mysql_result($result, 0)));
	}
	
	/** Hent antall brukere med nummer registrert */
	public function c_tlf_reg()
	{
		$result = ess::$b->db->query("SELECT COUNT(*) FROM users WHERE u_phone IS NOT NULL");
		$this->send_output("Antall brukere med nummmer registrert: %u".intval(mysql_result($result, 0))."%u");
	}
	
	/** Tell antall rader i en tabell */
	public function c_count_rows()
	{
		$find = $this->cmd_x[1];
		
		$result = ess::$b->db->query("SHOW TABLES");
		$tables = array();
		while ($row = mysql_fetch_row($result)) $tables[] = $row[0];
		
		#$this->send_output("Totalt er det %u".count($tables)."%u tabeller i MySQL databasen..");
		
		if (in_array($find, $tables))
		{
			$table = array_search($find, $tables);
			$table = $tables[$table];
			$result = ess::$b->db->query("SELECT COUNT(*) FROM `".mysql_real_escape_string($table)."`");
			
			$num = mysql_result($result, 0);
			
			$this->send_output("Tabellen %u$table%u inneholder %u".game::format_number($num)."%u rader..");
		}
		else
		{
			$this->send_output("Tabellen %u$find%u finnes ikke!");
		}
	}
	
	/** Tell antall tabeller i databasen */
	public function c_count_tables()
	{
		$result = ess::$b->db->query("SHOW TABLES");
		$this->send_output("Totalt finnes det %u".mysql_num_rows($result)."%u tabeller i databasen.");
	}
	
	/** Tell antall rader i databasen */
	public function c_count_rows_all()
	{
		$find = $this->cmd_x[1];
		
		$result = ess::$b->db->query("SHOW TABLES");
		$rows = 0;
		$i = 0;
		while ($row = mysql_fetch_row($result))
		{
			$sresult = ess::$b->db->query("SELECT COUNT(*) FROM `".mysql_real_escape_string($row[0])."`");
			$rows += mysql_result($sresult, 0);
			$i++;
		}
		
		$this->send_output("Databasen ".DBNAME." inneholder $i tabeller og %u".game::format_number($rows)."%u rader..");
	}
	
	/** Hent nummer registrert på en spiller */
	public function c_phone()
	{
		$player = $this->cmd_x[1];
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info phone <spiller>");
		}
		
		else
		{
			$result = ess::$b->db->query("SELECT up_id, u_phone, up_name FROM users, users_players WHERE up_name = ".ess::$b->db->quote($player)." AND up_u_id = u_id LIMIT 1");
			if ($row = mysql_fetch_assoc($result))
			{
				if (empty($row['u_phone']))
				{
					$this->send_output("Det er ikke noe nummer registrert for %u{$row['up_name']}%u (#{$row['up_id']})");
				}
				
				else
				{
					$this->send_output("Nummer registrert for %u{$row['up_name']}%u (#{$row['up_id']}): %u{$row['u_phone']}%u");
				}
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent antall hits i dag for en spiller */
	public function c_hits_today()
	{
		$player = $this->cmd_x[1];
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info hits_today <spiller>");
		}
		
		else
		{
			if ($row = $this->hent_bruker_info($player, "up_id, up_name, up_hits"))
			{
				$date = ess::$b->date->get(time());
				$date->setTime(0, 0, 0);
				$time_today = $date->format("U");
				
				// hent stats
				$result = ess::$b->db->query("SELECT SUM(IF(uhi_secs_hour >= $time_today, uhi_hits, 0)) AS hits_today FROM users_hits WHERE uhi_up_id = {$row['up_id']}");
				
				$this->send_output("Visninger: ".game::format_number(mysql_result($result, 0)));
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent lockdown status */
	public function c_lockdown()
	{
		$fh = @fopen("/home/smafia/sm_base/lockdown.sm", "r");
		if (!$fh)
		{
			$this->send_output("Kunne ikke åpne lockdown fil. Antar at siden ikke er låst.");
		}
		
		else
		{
			$contents = "";
			while (!feof($fh))
			{
				$contents .= fread($fh, 8192);
			}
			
			if (preg_match("/^false/u", $contents) || $contents == "")
			{
				$this->send_output("SMafia.no er åpent.");
			}
			else
			{
				$this->send_output("SMafia.no er stengt, begrunnelse: $contents");
			}
		}
	}
	
	/** Hent liste over bydeler */
	public function c_bydeler()
	{
		// vis alle bydelene
		$bydeler = array();
		foreach (game::$bydeler as $bydel)
		{
			$bydeler[] = $bydel['name'];
		}
		
		$this->send_output("%bBydeler%b: ".implode(", ", $bydeler));
	}
	
	/** Hent antall biler det finnes totalt */
	public function c_biler()
	{
		global $_game;
		
		// antall biler totalt
		$result = ess::$b->db->query("SELECT COUNT(users_gta.id) FROM users_gta, users_players WHERE up_id = ug_up_id AND up_access_level != 0 AND up_access_level < {$_game['access_noplay']}");
		$this->send_output("%bBiler totalt%b: %u".game::format_number(mysql_result($result, 0))."%u");
	}
	
	/** Oppdater innstillinger som er cachet på nytt fra databasen */
	public function c_reload()
	{
		$this->send_output("%bReload fra databasen%b");
		
		$file = $this->cmd_x[1];
		if ($file == "ranks")
		{
			$this->send_output("Henter %urankene%u fra databasen");
			require ROOT."/base/scripts/update_db_ranks.php";
		}
		elseif ($file == "ranks_pos")
		{
			$this->send_output("Henter %urankene (plasseringene)%u fra databasen");
			require ROOT."/base/scripts/update_db_ranks_pos.php";
		}
		elseif ($file == "bydeler")
		{
			$this->send_output("Henter %ubydelene%u fra databasen");
			require ROOT."/base/scripts/update_db_bydeler.php";
		}
		elseif ($file == "settings")
		{
			$this->send_output("Henter %uinnstillinger%u fra databasen");
			require ROOT."/base/scripts/update_db_settings.php";
		}
		
		
		else
		{
			$this->send_output("Ukjent parameter!");
			return;
		}
		
		$this->send_output("%bLokal fil ble oppdatert!%b Lagret til $file");
	}
	
	/** Hent alder for en spiller */
	public function c_alder()
	{
		global $_lang;
		$player = $this->cmd_x[1];
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info alder <spiller>");
		}
		
		else
		{
			$result = ess::$b->db->query("SELECT up_id, u_birth, up_name FROM users, users_players WHERE up_name = ".ess::$b->db->quote($player)." AND up_u_id = u_id LIMIT 1");
			if ($row = mysql_fetch_assoc($result))
			{
				if (empty($row['u_birth']))
				{
					$this->send_output("Det er ikke noen fødselsdato for %u{$row['up_name']}%u (#{$row['up_id']})");
				}
				
				else
				{
					$date = ess::$b->date->get(time());
					$n_day = $date->format("j");
					$n_month = $date->format("n");
					$n_year = $date->format("Y");
					$time = $date->format("U");
					
					$result = preg_match("/^(.*)-(.*)-(.*)$/Du", $row['u_birth'], $info);
					$age = $n_year - $info[1] - (($n_month < $info[2] || ($info[2] == $n_month && $n_day < $info[3])) ? 1 : 0);
					$left = "";
					if ($age < 13)
					{
						$date->setTime(0, 0, 0);
						$date->setDate($info[1]+13, $info[2], $info[3]);
						$then = $date->format("U");
						
						$left = " Det gjenstår %u".game::timespan($then - $time, game::TIME_FULL | game::TIME_NOBOLD)."%u før %u{$row['up_name']}%u fyller 13 år!";
					}
					$extra = $age > 20 ? " og begynner å komme langt opp i åra" : "";
					$this->send_output("%u{$row['up_name']}%u ble født %u".intval($info[3]).". ".$_lang['months'][intval($info[2])]." {$info[1]}%u og er nå %u$age%u år$extra!$left");
				}
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent hvor lenge siden en spiller ble født */
	public function c_alder_eksistert()
	{
		$player = $this->cmd_x[1];
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info alder_eksistert <spiller>");
		}
		
		else
		{
			$result = ess::$b->db->query("SELECT up_id, u_birth, up_name FROM users, users_players WHERE up_name = ".ess::$b->db->quote($player)." AND up_u_id = u_id LIMIT 1");
			if ($row = mysql_fetch_assoc($result))
			{
				if (empty($row['birth']))
				{
					$this->send_output("Det er ikke noen fødselsdato for %u{$row['up_name']}%u (#{$row['up_id']})");
				}
				
				else
				{
					$date = ess::$b->date->get(time());
					$time = $date->format("U");
					
					$result = preg_match("/^(.*)-(.*)-(.*)$/Du", $row['u_birth'], $info);
					$date->setTime(0, 0, 0);
					$date->setDate($info[1], $info[2], $info[3]);
					$birth = $date->format("U");
					
					$this->send_output("%u{$row['up_name']}%u har vært ute i verdenen i %u".game::timespan($time - $birth, game::TIME_FULL | game::TIME_NOBOLD)."%u!");
				}
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Sjekk om en bestemt IP tilhører en spiller */
	public function c_verify()
	{
		$info = explode(",", $this->cmd_x[1], 3);
		if ($info[2] == "host")
		{
			$info[1] = gethostbyname($info[1]);
		}
		
		$player = $info[0];
		if (empty($player))
		{
			$this->send_output("%bSyntax:%b !info verify <spiller> <ip>");
		}
		
		else
		{
			$result = ess::$b->db->query("SELECT up_id, u_online_ip, up_name FROM users, users_players WHERE up_name = ".ess::$b->db->quote($player)." AND up_u_id = u_id LIMIT 1");
			if ($row = mysql_fetch_assoc($result))
			{
				if ($row['up_online_ip'] == $info[1])
				{
					$this->send_output("%c3%u{$info[1]}%u tilhører %u{$row['up_name']}%u!");
				}
				else
				{
					$this->send_output("%c4%u{$info[1]}%u tilhører IKKE %u{$row['up_name']}%u!");
				}
			}
			
			else
			{
				$this->send_output("%b$player%b finnes ikke!");
			}
		}
	}
	
	/** Hent informasjon om antall spørringer per tidsenhet */
	public function c_qps()
	{
		// finn antall spørringer
		$result = ess::$b->db->query("SHOW GLOBAL STATUS");
		$vars = array();
		while ($row = mysql_fetch_row($result))
		{
			$vars[$row[0]] = $row[1];
		}
		$q = intval($vars['Questions']);
		
		if (!isset($this->cmd_x[1]) || $this->cmd_x[1] != "reset")
		{
			if (!isset(game::$settings['qps_int']) || !isset(game::$settings['qps_time']))
			{
				$this->send_output("Trenger init, kjør med reset param.");
				return;
			}
			
			$time = round(microtime(true) - game::$settings['qps_time']['value'], 1);
			$count = $q - game::$settings['qps_int']['value'];
			
			$this->send_output("Periode: %u".game::timespan($time, game::TIME_FULL | game::TIME_NOBOLD)."%u -- Antall spørringer: %u".game::format_number($count)."%u -- %u".game::format_number($count/$time, 1)."%u spørringer i sekundet");
		}
		
		if (isset($this->cmd_x[1]) && ($this->cmd_x[1] == "reset" || $this->cmd_x[1] == "resetd"))
		{
			ess::$b->db->query("REPLACE INTO settings SET name = 'qps_int', value = '$q'");
			ess::$b->db->query("REPLACE INTO settings SET name = 'qps_time', value = '".microtime(true)."'");
			require ROOT . "/base/scripts/update_db_settings.php";
			if ($this->cmd_x[1] != "resetd") $this->send_output("QPS er nå nullstilt");
		}
	}
	
	/** Finn ut antall spilleren som har blitt deaktivert i et bestemt tidsrom */
	public function c_deactivated_after()
	{
		$result = ess::$b->db->query("SELECT COUNT(up_id), FROM_UNIXTIME(MIN(up_deactivated_time)), FROM_UNIXTIME(MAX(up_deactivated_time)) FROM users_players WHERE up_deactivated_time >= UNIX_TIMESTAMP(".ess::$b->db->quote($this->cmd_x[1]).")");
		$ant = mysql_result($result, 0);
		if ($ant == 0) $this->send_output("Ingen ble funnet!");
		else $this->send_output("Antall deaktivert i tidsrommet: ".mysql_result($result, 0)." (første: ".mysql_result($result, 0, 1)."; siste: ".mysql_result($result, 0, 2).")");
	}
	
	/** Hent status */
	public function c_status()
	{
		// hent server info
		$request = new httpreq();
		$request->actualhost = "kofradia.no";
		$data = $request->get("/httpd-status-server", array());
		$c = $data['content'];
		
		// finn antall requests pr sekund
		$p1 = mb_strpos($c, "CPU Usage");
		if ($p1 !== false)
		{
			$p2 = mb_strpos($c, "\n", $p1)+1;
			$p3 = mb_strpos($c, "\n", $p2)-4;
			
			// finn ut cpu load
			$p4 = mb_strpos($c, "-", $p1)+2;
			$p5 = mb_strpos($c, "%", $p4)+1;
			
			// finn antall requests i dette øyeblikket
			$req = explode(" ", mb_substr($c, $p3+6, 10));
			$req = $req[0];
			
			$load = str_replace(array(".", "%"), array(",", " %"), mb_substr($c, $p4, $p5-$p4));
			
			$status = explode(" - ", mb_substr($c, $p2, $p3-$p2));
			$status[0] = explode(" ", $status[0]);
			$status[0] = str_replace(".", ",", $status[0][0]);
			$status[1] = explode(" ", $status[1]);
			$status[1] = str_replace(".", ",", $status[1][0]);
			$status[2] = explode(" ", $status[2]);
			$status[2] = str_replace(".", ",", $status[2][0]);
		}
		else
		{
			$load = "ERROR";
			$req = "ERROR";
			$status = array("ERROR", "ERROR", "ERROR");
		}
		
		
		// minne informasjon
		$mem = shell_exec("free -t");
		$matches = false;
		if (preg_match("/Mem:\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)/u", $mem, $matches))
		{
			$mem_percent = number_format($matches[2]/$matches[1]*100, 1, ",", " ");
		}
		else
		{
			$mem_percent = "ERROR";
		}
		
		
		// hent antall brukere pålogget
		$result = ess::$b->db->query("SELECT COUNT(IF(up_last_online > ".(time()-1800).", 1, NULL)), COUNT(IF(up_last_online > ".(time()-900).", 1, NULL)), COUNT(IF(up_last_online > ".(time()-600).", 1, NULL)), COUNT(IF(up_last_online > ".(time()-300).", 1, NULL)), COUNT(IF(up_last_online > ".(time()-60).", 1, NULL)), COUNT(IF(up_last_online > ".(time()-30).", 1, NULL)) FROM users_players");
		
		$row = mysql_fetch_row($result);
		
		$ret = "Status: Antall pålogget siste sekunder: 1800={$row[0]}, 900={$row[1]}, 600={$row[2]}, 300={$row[3]}, 60={$row[4]}, 30={$row[5]}. CPU: $load. Visninger/sekund: {$status[0]}. Nå: {$req}. Minnebruk: {$mem_percent}";
		
		$this->send_output($ret);
	}
	
	/** Finn den siste vervede spilleren */
	public function c_siste_vervet()
	{
		$ant = 5;
		if (isset($this->cmd_x[1]))
		{
			$ant = intval($this->cmd_x[1]);
			if ($ant < 1 || $ant > 20) $ant = 5;
		}
		
		$result = ess::$b->db->query("SELECT u1.up_id, u1.up_name, u1.up_created_time, u2.up_id AS r_id, u2.up_name AS r_up_name FROM users_players AS u1, users_players AS u2 WHERE u1.up_recruiter_up_id = u2.up_id ORDER BY u1.up_created_time DESC LIMIT $ant");
		
		$this->send_output(mysql_num_rows($result)." siste vervede spillere:");
		
		while ($row = mysql_fetch_assoc($result))
		{
			$this->send_output(ess::$b->date->get($row['up_created_time'])->format(date::FORMAT_SEC)." - %u{$row['up_name']}%u (av %u{$row['r_up_name']}%u) - http://smafia.no/p|".urlencode($row['up_name']));
		}
	}
	
	/** Hent når crewmedlemmene sist var logget inn */
	public function c_crewstatus()
	{
		global $_game;
		
		$result = ess::$b->db->query("SELECT up_id, up_name, up_access_level, up_last_online FROM users_players WHERE up_access_level != 0 AND up_access_level != 1 ORDER BY up_last_online DESC");
		
		$this->send_output("Sist pålogget for Crewet:");
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['up_name'] == "SYSTEM" || $row['up_name'] == "beta") continue;
			
			$type = access::type($row['up_access_level']);
			$name = isset($_game['access_names'][$type]) ? '%c14 ('.$_game['access_names'][$type].')' : '';
			$this->send_output("%c7%b%b{$row['up_name']}:%c6 ".game::timespan($row['up_last_online'], game::TIME_NOBOLD | game::TIME_ABS).$name);
		}
	}
}