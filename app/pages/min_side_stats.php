<?php

class page_min_side_stats
{
	public static function main()
	{
		echo '
<p class="minside_toplinks sublinks">
	'.page_min_side::link('Siste periode', "").'
	'.page_min_side::link('Visninger', "act").'
	'.page_min_side::link('Forum', "forum").'
	'.page_min_side::link('Ranking', "rank").'
	'.page_min_side::link('Diverse', "div").'
</p>
<div id="page_user_info">'.(page_min_side::$active_own ? '' : '
	<h1>'.htmlspecialchars(page_min_side::$active_user->data['u_email']) . ' (#'.page_min_side::$active_user->id.')<br />' . page_min_side::$active_player->profile_link() . ' (#'.page_min_side::$active_player->id.')</h1>');
		
		// div stats
		if (page_min_side::$subpage == "")
			self::page_default();
		
		// aktivitet
		elseif (page_min_side::$subpage == "act")
			self::page_act();
		
		// forum
		elseif (page_min_side::$subpage == "forum")
			self::page_forum();
		
		// rank
		elseif (page_min_side::$subpage == "rank")
			self::page_rank();
		
		// diverse
		elseif (page_min_side::$subpage == "div")
			self::page_diverse();
		
		else
			redirect::handle(page_min_side::addr(""));
		
		echo '
</div>';
	}
	
	/**
	 * Div statistikk
	 */
	protected static function page_default()
	{
		OFC::embed("stats_day_last", "graphs/user_hits_day_last?up_id=".page_min_side::$active_player->id, "100%", 250);
		OFC::embed("stats_month_last", "graphs/user_hits_month_last?up_id=".page_min_side::$active_player->id, "100%", 250);
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1">Aktivitet siste 24 timer<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p><span id="stats_day_last"></span></p>
		</div>
		
		<h1 class="bg1">Aktivitet siste periode<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p><span id="stats_month_last"></span></p>
		</div>
	</div>';
	}
	
	/**
	 * Aktivitet
	 */
	protected static function page_act()
	{
		// hvilken måned skal vi vise for?
		$now = ess::$b->date->get();
		$date_month = array($now->format("Y"), $now->format("n"));
		$params = array("up_id=".page_min_side::$active_player->id);
		$params['date'] = 'date='.$now->format("Ym");
		if (isset($_GET['dato_m']))
		{
			$date = $_GET['dato_m'];
			$matches = false;
			if (preg_match("/^(20[0-2]\\d)-(0[1-9]|1[0-2])$/Du", $date, $matches))
			{
				$d = ess::$b->date->get();
				$d->setDate($matches[1], (int)$matches[2], 1);
				if ($d->format("U") <= time())
				{
					$date_month = array($matches[1], intval($matches[2]));
					$params['date'] = "date={$matches[1]}{$matches[2]}";
				}
			}
		}
		
		$month_prev = ess::$b->date->get();
		$month_prev->setTime(0, 0, 0);
		$month_prev->setDate($date_month[0], $date_month[1], 0);
		$month_prev->modify("-1 month");
		$month_next = clone $month_prev;
		$month_next->modify("+2 months");
		
		OFC::embed("stats_monthly", "graphs/user_hits_month?".implode("&", $params), "100%", 350);
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1" id="dato_m">Månedstatistikk<span class="left2"></span><span class="right2"></span></h1>
		<p class="h_left"><a href="'.htmlspecialchars(game::address("min_side", $_GET, array("dato_m"), array("dato_m" => $month_prev->format("Y-m")))).'#dato_m" id="minside_stats_month_prev">Forrige måned</a></p>
		<p class="h_right"><a href="'.htmlspecialchars(game::address("min_side", $_GET, array("dato_m"), array("dato_m" => $month_next->format("Y-m")))).'#dato_m" id="minside_stats_month_next">Neste måned</a></p>
		<div class="bg1">
			<p><span id="stats_monthly"></span></p>
		</div>
	</div>';
		
		// hvilken dag skal vi vise for?
		$date_day = array($now->format("Y"), $now->format("n"), $now->format("j"));
		$params = array("up_id=".page_min_side::$active_player->id);
		$params['date'] = 'date='.$now->format("Ymd");
		if (isset($_GET['dato_d']))
		{
			$date = $_GET['dato_d'];
			if (preg_match("/^(20[0-2]\\d)-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/Du", $date, $matches))
			{
				$d = ess::$b->date->get();
				$d->setDate($matches[1], (int)$matches[2], (int)$matches[3]);
				if ($d->format("U") <= time())
				{
					$date_day = array($matches[1], intval($matches[2]), intval($matches[3]));
					$params['date'] = "date={$matches[1]}{$matches[2]}{$matches[3]}";
				}
			}
		}
		
		$date_prev = ess::$b->date->get();
		$date_prev->setTime(0, 0, 0);
		$date_prev->setDate($date_day[0], $date_day[1], $date_day[2]);
		$date_prev->modify("-1 day");
		$date_next = clone $date_prev;
		$date_next->modify("+2 days");
		
		OFC::embed("stats_daily", "graphs/user_hits_day?".implode("&", $params), "100%", 350);
		OFC::embed("stats_avg", "graphs/user_hits_avg?up_id=".page_min_side::$active_player->id, "100%", 350);
		OFC::embed("stats_all", "graphs/user_hits_all?up_id=".page_min_side::$active_player->id, "100%", 350);
		OFC::embed("stats_all_weekday", "graphs/user_hits_all_weekday?up_id=".page_min_side::$active_player->id, "100%", 350);
	
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1" id="dato_d">Daglig statistikk<span class="left2"></span><span class="right2"></span></h1>
		<p class="h_left"><a href="'.htmlspecialchars(game::address("min_side", $_GET, array("dato_d"), array("dato_d" => $date_prev->format("Y-m-d")))).'#dato_d" id="minside_stats_day_prev">Forrige dag</a></p>
		<p class="h_right"><a href="'.htmlspecialchars(game::address("min_side", $_GET, array("dato_d"), array("dato_d" => $date_next->format("Y-m-d")))).'#dato_d" id="minside_stats_day_next">Neste dag</a></p>
		<div class="bg1">
			<p><span id="stats_daily"></span></p>
		</div>
	</div>
	<div class="bg1_c">
		<h1 class="bg1">Døgnrytme<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p><span id="stats_avg"></span></p>
		</div>
	</div>
	<div class="bg1_c">
		<h1 class="bg1">Statistikk siden registrering<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p><span id="stats_all"></span></p>
		</div>
	</div>
	<div class="bg1_c">
		<h1 class="bg1">Statistikk siden registrering (fordelt på ukedager)<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p><span id="stats_all_weekday"></span></p>
		</div>
	</div>';
		
		ess::$b->page->add_js_domready('
	var up_id = '.page_min_side::$active_player->id.';
	var stats_month = ['.$date_month[0].', '.$date_month[1].'];
	var stats_day = new Date('.$date_day[0].', '.($date_day[1]-1).', '.$date_day[2].');
	var str_stats_month, str_stats_day;
	function reloadvars(skip_save)
	{
		str_stats_month = stats_month[0] + str_pad(stats_month[1]);
		str_stats_day = stats_day.getFullYear() + str_pad(stats_day.getMonth() + 1) + str_pad(stats_day.getDate());
		if (!skip_save) document.location.hash = "m=" + str_stats_month + ",d=" + str_stats_day;
	}
	function month_reload(s){reloadvars(s);$("stats_monthly").reload("graphs/user_hits_month?up_id=" + up_id + "&date=" + str_stats_month);}
	function day_reload(s){reloadvars(s);$("stats_daily").reload("graphs/user_hits_day?up_id=" + up_id + "&date=" + str_stats_day);}
	$("minside_stats_month_prev").addEvent("click", function(e)
	{
		if (stats_month[1] == 1) { stats_month[0]--; stats_month[1] = 12; }
		else stats_month[1]--;
		month_reload();
		e.stop();
	});
	$("minside_stats_month_next").addEvent("click", function(e)
	{
		if (stats_month[1] == 12) { stats_month[0]++; stats_month[1] = 1; }
		else stats_month[1]++;
		month_reload();
		e.stop();
	});
	$("minside_stats_day_prev").addEvent("click", function(e)
	{
		stats_day.setDate(stats_day.getDate() - 1);
		day_reload();
		e.stop();
	});
	$("minside_stats_day_next").addEvent("click", function(e)
	{
		stats_day.setDate(stats_day.getDate() + 1);
		day_reload();
		e.stop();
	});
	
	// test for spesifisert dato
	if (document.location.hash.length > 1)
	{
		setTimeout(function()
		{
			document.location.hash.substring(1).split(",").each(function(val)
			{
				d = val.split("=");
				if (d[0] == "m" && d[1]) {
					stats_month[0] = d[1].substring(0, 4);
					stats_month[1] = d[1].substring(4, 6);
					month_reload(true);
				}
				if (d[0] == "d" && d[1]) {
					stats_day = new Date(d[1].substring(0, 4), d[1].substring(4, 6) - 1, d[1].substring(6, 8));
					day_reload(true);
				}
			});
		}, 750);
	}');
	}
	
	/**
	 * Forum
	 */
	protected static function page_forum()
	{
		// måned
		$now = ess::$b->date->get();
		$date_month = array($now->format("Y"), $now->format("n"));
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1">Din aktivitet i forumet (dager)<span class="left2"></span><span class="right2"></span></h1>
		<div id="minside_stats_month_prev"></div>
		<div id="minside_stats_month_next"></div>
		<div class="bg1">
			<p><span id="stats_monthly"></span></p>
		</div>
	</div>
	<div class="bg1_c">
		<h1 class="bg1">Din aktivitet i forumet (måneder)<span class="left2"></span><span class="right2"></span></h1>
		<div id="minside_stats_year_prev"></div>
		<div id="minside_stats_year_next"></div>
		<div class="bg1">
			<p><span id="stats_yearly"></span></p>
		</div>
	</div>';
		
		OFC::embed("stats_monthly", "graphs/user_forum_monthly?up_id=".page_min_side::$active_player->id."&date=".$now->format("Ym"), "100%", 300);
		OFC::embed("stats_yearly", "graphs/user_forum_yearly?up_id=".page_min_side::$active_player->id."&date=".$now->format("Y"), "100%", 300);
		
		ess::$b->page->add_js_domready('
	var up_id = '.page_min_side::$active_player->id.';
	var stats_month = ['.$date_month[0].', '.$date_month[1].'];
	var stats_year = '.$date_month[0].';
	var str_stats_month;
	function reloadvars(skip_save)
	{
		str_stats_month = stats_month[0] + str_pad(stats_month[1]);
		if (!skip_save) document.location.hash = "m=" + str_stats_month + ",y=" + stats_year;
	}
	function month_reload(s){reloadvars(s);$("stats_monthly").reload("graphs/user_forum_monthly?up_id=" + up_id + "&date=" + str_stats_month);}
	function year_reload(s){reloadvars(s);$("stats_yearly").reload("graphs/user_forum_yearly?up_id=" + up_id + "&date=" + stats_year);}
	new Element("p", {"class": "h_left fakelink", "text": "Forrige måned"}).addEvent("click", function(e)
	{
		if (stats_month[1] == 1) { stats_month[0]--; stats_month[1] = 12; }
		else stats_month[1]--;
		month_reload();
		e.stop();
	}).inject($("minside_stats_month_prev"));
	new Element("p", {"class": "h_right fakelink", "text": "Neste måned"}).addEvent("click", function(e)
	{
		if (stats_month[1] == 12) { stats_month[0]++; stats_month[1] = 1; }
		else stats_month[1]++;
		month_reload();
		e.stop();
	}).inject($("minside_stats_month_next"));
	new Element("p", {"class": "h_left fakelink", "text": "Forrige år"}).addEvent("click", function(e)
	{
		stats_year--;
		year_reload();
		e.stop();
	}).inject($("minside_stats_year_prev"));
	new Element("p", {"class": "h_right fakelink", "text": "Neste år"}).addEvent("click", function(e)
	{
		stats_year++;
		year_reload();
		e.stop();
	}).inject($("minside_stats_year_next"));
	
	// test for spesifisert dato
	if (document.location.hash.length > 1)
	{
		setTimeout(function()
		{
			document.location.hash.substring(1).split(",").each(function(val)
			{
				d = val.split("=");
				if (d[0] == "m" && d[1]) {
					stats_month[0] = d[1].substring(0, 4);
					stats_month[1] = d[1].substring(4, 6);
					month_reload(true);
				}
				if (d[0] == "y") {
					stats_year = parseInt(d[1]);
					year_reload(true);
				}
			});
		}, 1000);
	}');
	}
	
	/**
	 * Ranking
	 */
	protected static function page_rank()
	{
		self::page_rank_pos();
		self::page_rank_points();
		self::page_rank_points_rel();
	}
	
	/**
	 * Rankplassering siden kl 21
	 */
	protected static function page_rank_pos()
	{
		// finn vår plassering for ranking siden forrige rankperiode startet
		$d = ess::$b->date->get();
		if ($d->format("H") < 21) $d->modify("-1 day");
		$d->setTime(21, 0, 0);
		$date_from = $d->format("U");
		
		$d->modify("+1 day");
		$date_to = $d->format("U");
		
		// hent statistikk
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, sum_uhi_points, up_points, up_last_online, up_profile_image_url, upr_rank_pos
			FROM
				(
					SELECT uhi_up_id, SUM(uhi_points) sum_uhi_points
					FROM users_hits
						JOIN users_players ON up_id = uhi_up_id AND (up_access_level != 0 OR up_deactivated_time < $date_to) AND up_access_level < ".ess::$g['access_noplay']."
					WHERE uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to
					GROUP BY uhi_up_id
					HAVING sum_uhi_points > 0
					ORDER BY sum_uhi_points DESC
					LIMIT 5
				) ref,
				users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE uhi_up_id = up_id");
		
		$players = array();
		$up_list = array();
		$in_list = false;
		$pos = null;
		while ($row = $result->fetch())
		{
			$players[] = $row;
			$up_list[] = $row['up_id'];
			if ($row['up_id'] == page_min_side::$active_player->id) $in_list = true;
		}
		
		// er vi ikke i lista?
		if (!$in_list)
		{
			// hvor mange poeng har vi fått?
			$result = \Kofradia\DB::get()->query("
				SELECT SUM(uhi_points) sum_uhi_points
				FROM users_hits
				WHERE uhi_up_id = ".page_min_side::$active_player->id." AND uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to");
			$points = $result->fetchColumn(0);
			
			if ($points > 0)
			{
				// finn plasseringen vår
				$result = \Kofradia\DB::get()->query("
					SELECT COUNT(uhi_up_id) FROM (
						SELECT uhi_up_id, SUM(uhi_points) sum_uhi_points
						FROM users_hits
							JOIN users_players ON up_id = uhi_up_id AND (up_access_level != 0 OR up_deactivated_time < $date_to) AND up_access_level < ".ess::$g['access_noplay']."
						WHERE uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to
						GROUP BY uhi_up_id
						HAVING sum_uhi_points > $points
					) ref");
				
				$pos = $result->fetchColumn(0)+1;
			}
		}
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1">Rankplassering siste periode<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Statistikk gjelder fra kl 21.</p>';
		
		if (count($players) == 0)
		{
			echo '
			<p>Ingen har ranket i denne perioden.</p>';
		}
		else
		{
			echo '
			<ol>';
			
			foreach ($players as $row)
			{
				$me = $row['up_id'] == page_min_side::$active_player->id ? " (meg)" : "";
				
				echo '
				<li>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).$me.'</li>';
			}
			
			echo '
			</ol>';
			
			if (!$in_list && !$pos)
			{
				echo '
			<p>Du har ikke ranket i perioden og har ingen plassering.</p>';
			}
			elseif ($pos)
			{
				echo '
			<p>Din plassering: '.game::format_num($pos).'</p>';
			}
		}
		
		echo '
		</div>
	</div>';
	}
	
	/**
	 * Rankpoeng siste perioden
	 */
	protected static function page_rank_points()
	{
		// hvilken måned skal vi vise for?
		$now = ess::$b->date->get();
		$date_month = array($now->format("Y"), $now->format("n"));
		$params = array("up_id=".page_min_side::$active_player->id);
		$params['date'] = 'date='.$now->format("Ym");
		if (isset($_GET['dato_p']))
		{
			$date = $_GET['dato_p'];
			$matches = false;
			if (preg_match("/^(20[0-2]\\d)-(0[1-9]|1[0-2])$/Du", $date, $matches))
			{
				$d = ess::$b->date->get();
				$d->setDate($matches[1], (int)$matches[2], 1);
				if ($d->format("U") <= time())
				{
					$date_month = array($matches[1], intval($matches[2]));
					$params['date'] = "date={$matches[1]}{$matches[2]}";
				}
			}
		}
		
		$month_prev = ess::$b->date->get();
		$month_prev->setTime(0, 0, 0);
		$month_prev->setDate($date_month[0], $date_month[1], 0);
		$month_prev->modify("-1 month");
		$month_next = clone $month_prev;
		$month_next->modify("+2 months");
		
		OFC::embed("stats_monthly", "graphs/user_points_month?".implode("&", $params), "100%", 350);
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1" id="dato_p">Månedstatistikk<span class="left2"></span><span class="right2"></span></h1>
		<p class="h_left"><a href="'.htmlspecialchars(game::address("min_side", $_GET, array("dato_p"), array("dato_p" => $month_prev->format("Y-m")))).'#dato_p" id="minside_stats_month_prev">Forrige måned</a></p>
		<p class="h_right"><a href="'.htmlspecialchars(game::address("min_side", $_GET, array("dato_p"), array("dato_p" => $month_next->format("Y-m")))).'#dato_p" id="minside_stats_month_next">Neste måned</a></p>
		<div class="bg1">
			<p><span id="stats_monthly"></span></p>
		</div>
	</div>';
		
		ess::$b->page->add_js_domready('
	var up_id = '.page_min_side::$active_player->id.';
	var stats_month = ['.$date_month[0].', '.$date_month[1].'];
	var str_stats_month;
	function reloadvars(skip_save)
	{
		str_stats_month = stats_month[0] + str_pad(stats_month[1]);
		if (!skip_save) document.location.hash = "m=" + str_stats_month;
	}
	function month_reload(s){reloadvars(s);$("stats_monthly").reload("graphs/user_points_month?up_id=" + up_id + "&date=" + str_stats_month);}
	$("minside_stats_month_prev").addEvent("click", function(e)
	{
		if (stats_month[1] == 1) { stats_month[0]--; stats_month[1] = 12; }
		else stats_month[1]--;
		month_reload();
		e.stop();
	});
	$("minside_stats_month_next").addEvent("click", function(e)
	{
		if (stats_month[1] == 12) { stats_month[0]++; stats_month[1] = 1; }
		else stats_month[1]++;
		month_reload();
		e.stop();
	});
	
	// test for spesifisert dato
	if (document.location.hash.length > 1)
	{
		setTimeout(function()
		{
			document.location.hash.substring(1).split(",").each(function(val)
			{
				d = val.split("=");
				if (d[0] == "m" && d[1]) {
					stats_month[0] = d[1].substring(0, 4);
					stats_month[1] = d[1].substring(4, 6);
					month_reload(true);
				}
			});
		}, 750);
	}');
	}
	
	/**
	 * Rankpoeng relativt til andre spillere siste perioden
	 */
	protected static function page_rank_points_rel()
	{
		OFC::embed("ranklevel_last_days", "graphs/ranklevel_last_days?up_id=".page_min_side::$active_player->id."&long", "100%", 350);
		
		echo '
			<div class="bg1_c">
				<h1 class="bg1">Ditt ranknivå siste fire uker<span class="left2"></span><span class="right2"></span></h1>
				<div class="bg1">
					<p>Denne grafen sammenlikner deg med de 10 beste rankerne de aktuelle dagene.</p>
					<p><span id="ranklevel_last_days"></span></p>
				</div>
			</div>';
	}
	
	
	/**
	 * Diverse
	 */
	protected static function page_diverse()
	{
		// hent totalt resultat i poker for siste 30 dager
		$date = ess::$b->date->get();
		$date->modify("-30 days");
		$date->setTime(0, 0, 0);
		$result = \Kofradia\DB::get()->query("
			SELECT SUM(CONVERT(poker_prize - poker_cash, SIGNED) * IF((poker_winner = 1 AND poker_starter_up_id = up_id) OR (poker_winner = 2 AND poker_challenger_up_id = up_id), 1, -1)) sum_result
			FROM poker, users_players
			WHERE poker_time_start >= ".$date->format("U")." AND up_u_id = ".page_min_side::$active_user->id." AND (up_id = poker_starter_up_id OR up_id = poker_challenger_up_id) AND poker_state = 4");
		$poker_result = $result->fetchColumn(0);
		
		OFC::embed("stats_poker", "graphs/poker?up_id=".page_min_side::$active_player->id, "100%", 250);
		OFC::embed("stats_poker_num", "graphs/pokernum?up_id=".page_min_side::$active_player->id, "100%", 250);
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1">Pokerbevegelse siste 30 dager<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p class="c">Totalt resultat siste 30 dager: '.game::format_cash($poker_result).'</p>
			<p><span id="stats_poker"></span></p>
			<p><span id="stats_poker_num"></span></p>
		</div>
	</div>';
	}
}
