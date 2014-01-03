<?php

class page_min_side_player
{
	public static function main()
	{
		$nye_hendelser = page_min_side::$active_player->data['up_log_ff_new'] + page_min_side::$active_player->data['up_log_new'];
		if (page_min_side::$subpage == "log" && page_min_side::$active_user->id == login::$user->id) $nye_hendelser = 0;
		
		echo '
<p class="minside_toplinks sublinks">
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/eye.png" alt="" />Status', "").'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/information.png" alt="" />Info', "info").(page_min_side::$pstats ? '
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/ruby.png" alt="" />Prestasjoner', "achievements").'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/newspaper.png" alt="" />Hendelser'.($nye_hendelser > 0 ? ' ('.$nye_hendelser.' '.fword("ny", "nye", $nye_hendelser).')' : ''), "log") : '').'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/note_edit.png" alt="" />Forum', "forum").'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/page_edit.png" alt="" />Profil', "profil");
		
		if (page_min_side::$active_player->active && page_min_side::$pstats)
		{
			echo '
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/delete.png" alt="" />Deaktiver', "deact");
		}
		
		if (access::has("crewet")) echo '
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/key.png" alt="" />Crew', "crew");
		
		echo '
</p>
<div id="page_user_info" class="player">'.(page_min_side::$active_own && page_min_side::$active_player->id == page_min_side::$active_user->data['u_active_up_id'] && page_min_side::$active_player->active ? '' : '
	<h1>'.page_min_side::$active_player->profile_link().' (#'.page_min_side::$active_player->id.')'.(page_min_side::$active_player->active ? '' : '<br />('.(page_min_side::$active_player->data['up_deactivated_dead'] == 0 ? 'deaktivert' : 'drept').' '.ess::$b->date->get(page_min_side::$active_player->data['up_deactivated_time'])->format(date::FORMAT_NOTIME).')').'</h1>');
		
		// status
		if (page_min_side::$subpage == "")
			self::page_default();
		
		// info
		elseif (page_min_side::$subpage == "info")
			self::page_info();
		
		// prestasjoner
		elseif (page_min_side::$subpage == "achievements")
			self::page_achievements();
		
		// hendelser / logg
		elseif (page_min_side::$subpage == "log" && page_min_side::$pstats)
			self::page_log();
		
		// forum
		elseif (page_min_side::$subpage == "forum")
			self::page_forum();
		
		// profil
		elseif (page_min_side::$subpage == "profil")
			self::page_profil();
		
		// deaktivere spilleren som moderator?
		elseif (page_min_side::$subpage == "deact" && access::has("mod"))
			self::page_deact_mod();
		
		// endre informasjon om deaktivering?
		elseif (page_min_side::$subpage == "cdeact" && access::has("mod"))
			self::page_cdeact();
		
		// deaktivere spilleren
		elseif (page_min_side::$subpage == "deact" && page_min_side::$active_own)
			self::page_deact();
		
		// aktiver spiller
		elseif (page_min_side::$subpage == "activate" && access::has("mod"))
			self::page_activate();
		
		// crew
		elseif (page_min_side::$subpage == "crew" && access::has("crewet", NULL, NULL, true))
			self::page_crew();
		
		else
			redirect::handle(page_min_side::addr(""));
		
		echo '
</div>';
	}
	
	/**
	 * Standard side - viser status for spilleren
	 */
	protected static function page_default()
	{
		// kan ikke se?
		if (!page_min_side::$pstats)
		{
			echo '
	<p class="c">Du har ikke tilgang til å se denne siden.</p>';
			return;
		}
		
		global $_game;
		
		// rank
		$rank_need = 0;
		if (page_min_side::$active_player->rank['need_points'] == 0)
		{
			$rank_prosent = 100;
		}
		else
		{
			$rank_prosent = (page_min_side::$active_player->data['up_points'] - page_min_side::$active_player->rank['points']) / page_min_side::$active_player->rank['need_points'] * 100;
			$rank_need = page_min_side::$active_player->rank['need_points'] - page_min_side::$active_player->data['up_points'] + page_min_side::$active_player->rank['points'];
		}
		$rank_prosent_top = page_min_side::$active_player->data['up_points'] / game::$ranks['items_number'][count(game::$ranks['items_number'])]['points'] * 100;
		
		
		// hvor mange rankprosent må vi til for å ta igjen neste person?
		$result = \Kofradia\DB::get()->query("SELECT up_points FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']} AND up_points > ".page_min_side::$active_player->data['up_points']." ORDER BY up_points LIMIT 1");
		$rank_user_next = false;
		$rank_user_prevnext = false;
		if ($next = $result->fetch())
		{
			$points = $next['up_points'];
			$to = game::rank_info($points);
			if ($to['need_points'] == 0)
			{
				// totalt for spillet
				$percent = game::format_rank($points, "all");
				$rank_user_next = '<p>Du må oppnå '.game::format_num($points).' poeng ('.$percent.' rank for spillet totalt) for å ta igjen neste rangert spiller.</p>';
				
				// antall prosent vi trenger
				$rank_user_next .= '<p>Du trenger '.game::format_num($points-page_min_side::$active_player->data['up_points']).' poeng ('.game::format_rank($points-page_min_side::$active_player->data['up_points'], $to).' rank).</p>';
			}
			
			else
			{
				$percent = game::format_number(($points-$to['points']) / $to['need_points'] * 100, 3);
				
				// samme rank?
				$same = page_min_side::$active_player->rank['id'] == $to['id'];
				
				$rank_user_next = '<p>Du må oppnå '.game::format_num($points).' poeng ('.$percent.' % '.($same ? 'på nåværende rank' : 'på ranken '.$to['name']).') for å nå neste rangert spiller.</p>';
				
				if ($same)
				{
					$rank_user_next .= '<p>Du trenger '.game::format_num($points-page_min_side::$active_player->data['up_points']).' poeng ('.game::format_rank($points-page_min_side::$active_player->data['up_points'], $to).' rank).</p>';
				}
			}
			
			// finn ut hvor langt det er til forrige rankerte spiller
			$result = \Kofradia\DB::get()->query("SELECT up_points FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']} AND up_id != ".page_min_side::$active_player->id." AND up_points <= ".page_min_side::$active_player->data['up_points']." ORDER BY up_points DESC LIMIT 1");
			if ($row = $result->fetch())
			{
				$rank_user_prevnext = round((page_min_side::$active_player->data['up_points']-$row['up_points']) / ($next['up_points']-$row['up_points']) * 100, 4);
			}
		}
		
		// finn ut tidspunkt for de forskjellige funksonene
		$status = array(
			"krim" => page_min_side::$active_player->status_kriminalitet(),
			"utpressing" => page_min_side::$active_player->status_utpressing(),
			"gta" => page_min_side::$active_player->status_gta(),
			"lotto" => page_min_side::$active_player->status_lotto()
		);
		
		// finn ut når vi kan gjøre forskjellige ting
		$wait = array(
			"kriminalitet" => $status['krim']['wait_time'],
			"utpressing" => $status['utpressing']['wait_time'],
			"biltyveri" => $status['gta']['wait_time'],
			"lotto" => $status['lotto']['wait_time'],
			"forum_topic" => 0,
			"forum_reply" => 0,
			"fengsel" => 0
		);
		
		// forumene
		$wait['forum_reply'] = max(0, page_min_side::$active_user->data['u_forum_reply_time'] + game::$settings['delay_forum_reply']['value'] - time());
		$wait['forum_topic'] = max(0, page_min_side::$active_user->data['u_forum_topic_time'] + game::$settings['delay_forum_new']['value'] - time());
		if (page_min_side::$active_player->data['up_weapon_id']) $wait['training'] = max(0, page_min_side::$active_player->data['up_weapon_training_next'] - time());
		
		// for lav rank til å opprette forumtråder?
		if (page_min_side::$active_player->rank['number'] < 4) $wait['forum_topic'] = -1;
		
		// fengsel og bomberom
		$wait['fengsel'] = max(0, page_min_side::$active_player->data['up_fengsel_time'] - time());
		$wait['bomberom'] = max(0, page_min_side::$active_player->data['up_brom_expire'] - time());
		
		$wait['lock'] = max($wait['fengsel'], $wait['bomberom']);
		
		// sammendrag
		$status = array(
			array("Kriminalitet", max($wait['kriminalitet'], $wait['lock']), "kriminalitet"),
			array("Utpressing", max($wait['utpressing'], $wait['lock']), "utpressing"),
			array("Biltyveri", max($wait['biltyveri'], $wait['lock']), "gta/biltyveri"),
			array("Lotto", max($wait['lotto'], $wait['lock']), "lotto"),
			array("Forumemne", $wait['forum_topic'], "forum/"),
			array("Forumsvar", $wait['forum_reply'], "forum/"),
			array("Fengsel", $wait['lock'], "fengsel")
		);
		
		if (page_min_side::$active_player->data['up_weapon_id']) $status[] = array("Våpentrening", max($wait['training'], $wait['lock']), "angrip");
		
		// javascript funksjoner for status
		ess::$b->page->add_js_domready('
	var min_side_data = '.js_encode($status).';
	var elm, c;
	for (var i = 0; i < min_side_data.length; i++)
	{
		elm = $("min_side_"+i);
		if (!elm) continue;
		
		c = new Countdown($("min_side_"+i));
		c.timesize = "partial",
		c.complete = function()
		{
			this.element.set("text", "Klar!");
			this.element.removeClass("status_venter").addClass("status_ny");
		};
	}');
		
		// css
		ess::$b->page->add_css('
.ms_space { margin-top: 2px }
.ms_space_bt { margin-bottom: 1em }
.status dd { text-align: right }
.status dd a { text-decoration: none; color: #CCFF00 }
a.status_ny { font-weight: bold }
a.status_klar:hover, a.status_ny:hover { color: #AAFF00; text-decoration: underline }
.status_venter { color: #888888 !important }
a.status_venter:hover { }
.split { height: 1px; background-color: #222222; overflow: hidden }');
		
		$health = page_min_side::$active_player->data['up_health'] / page_min_side::$active_player->data['up_health_max'] * 100;
		$energy = page_min_side::$active_player->data['up_energy'] / page_min_side::$active_player->data['up_energy_max'] * 100;
		
		$health = page_min_side::$active_player->get_health_percent();
		$energy = page_min_side::$active_player->get_energy_percent();
		$protection = page_min_side::$active_player->get_protection_percent();
		$training = page_min_side::$active_player->weapon ? page_min_side::$active_player->data['up_weapon_training'] * 100 : false;
		
		echo '
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">'.(page_min_side::$active_player->active ? '
				<div class="bg1_c">
					<h1 class="bg1">Status<span class="left2"></span><span class="right2"></span></h1>
					<div class="ms_space ms_up_st progressbar'.($health < 20 ? ' levelcrit' : ($health < 50 ? ' levelwarn' : '')).'">
						<div class="progress" style="width: '.round(min(100, $health)).'%">
							<p>Helse: '.($health == 100 ? '100' : game::format_num($health, 2)).' %</p>
						</div>
					</div>
					<div class="ms_space ms_up_st progressbar'.($energy < 20 ? ' levelcrit' : ($energy < 50 ? ' levelwarn' : '')).'">
						<div class="progress" style="width: '.round(min(100, $energy)).'%">
							<p>Energi: '.($energy == 100 ? '100' : game::format_num($energy, 2)).' %</p>
						</div>
					</div>
					<div class="ms_space ms_up_st progressbar'.($protection !== false ? ($protection < 20 ? ' levelcrit' : ($protection < 50 ? ' levelwarn' : '')) : '').'">
						<div class="progress" style="width: '.round(min(100, $protection)).'%">
							<p>Beskyttelse: '.($protection === false ? 'Ingen' : ($protection == 100 ? '100' : game::format_num($protection, 2)).' %').'</p>
						</div>
					</div>
					<div class="ms_space ms_up_st progressbar'.($training !== false ? ($training < 28 ? ' levelcrit' : ($training < 35 ? ' levelwarn' : '')) : '').'">
						<div class="progress" style="width: '.round(min(100, $training)).'%">
							<p><a href="'.ess::$s['relative_path'].'/angrip">Våpentrening</a>: '.($training === false ? 'Ingen våpen' : ($training == 100 ? '100' : game::format_num($training, 2)).' %').'</p>
						</div>
					</div>
					<div class="progressbar ms_space">
						<div class="progress" style="width: '.round(min(100, page_min_side::$active_player->data['up_wanted_level']/10)).'%">
							<p>Wanted nivå: '.game::format_number(page_min_side::$active_player->data['up_wanted_level']/10, 1).' %</p>
						</div>
					</div>
				</div>' : '').($rank_prosent < 100 ? '
				<div class="bg1_c">
					<h1 class="bg1">Prosent til neste rank<span class="left2"></span><span class="right2"></span></h1>
					<div class="progressbar">
						<div class="progress" style="width: '.round($rank_prosent).'%">
							<p>'.game::format_number($rank_prosent, 3).' %'.($rank_need > 0 ? ' (trenger '.game::format_num($rank_need).' poeng)' : '').'</p>
						</div>
					</div>
				</div>' : '').'
				<div class="bg1_c">
					<h1 class="bg1">Prosent til høyeste rank<span class="left2"></span><span class="right2"></span></h1>
					<div class="progressbar">'.(page_min_side::$active_player->rank['number'] < 5 && !access::has("mod") ? '
						<p>Krever ranken <b>'.game::$ranks['items_number'][5]['name'].'</b> eller høyere!</p>' : '
						<div class="progress" style="width: '.min(round($rank_prosent_top), 100).'%">
							<p>'.game::format_number($rank_prosent_top, 4).' % ('.game::format_num(page_min_side::$active_player->data['up_points']).' poeng)</p>
						</div>').'
					</div>
				</div>
				<div class="bg1_c">
					<h1 class="bg1">Rangstige<span class="left2"></span><span class="right2"></span></h1>';
		
		$ranks = array_reverse(game::$ranks['items_number']);
		$active = page_min_side::$active_player->rank['number'];
		
		$split = false;
		foreach ($ranks as $rank)
		{
			if ($split)
			{
				echo '
						<div class="split"></div>';
			}
			
			if ($rank['number'] <= $active)
			{
				echo '
						<div class="progressbar_v"'.($rank['number'] == $active ? ' style="font-weight: bold"' : '').'>
							<p>'.$rank['number'].' - '.$rank['name'].'</p>
						</div>';
			}
		
			elseif ($rank['number'] == $active+1)
			{
				echo '
						<div class="progressbar_v">
							<div class="progress" style="height: '.round(100-$rank_prosent).'%">
								<p>'.$rank['number'].' - '.$rank['name'].'</p>
							</div>
						</div>';
			}
		
			else
			{
				echo '
						<div class="progressbar_v">
							<div class="progress">
								<p>'.$rank['number'].' - '.$rank['name'].'</p>
							</div>
						</div>';
			}
		
			$split = true;
		}
		
		echo '
				</div>
			</div>
		</div>
		<div class="col_w right">
			<div class="col">';
		
		if (page_min_side::$active_player->active)
		{
			echo '
				<div class="bg1_c">
					<h1 class="bg1">Status<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						<dl class="dl_40 status">';
			
			foreach ($status as $key => $row)
			{
				echo '
							<dt>'.htmlspecialchars($row[0]).'</dt>
							<dd>'.($row[1] == -1
				? '<span class="status_venter">For lav rank</span>'
				: ($row[1] == 0
					? '<a href="'.htmlspecialchars($row[2]).'" class="status_klar">Klar!</a>'
					: '<a href="'.htmlspecialchars($row[2]).'" rel="'.$row[1].'" id="min_side_'.$key.'" class="status_venter">'.game::timespan($row[1], 0, 5).'</a>'
				)).'
							</dd>';
			}
			
			echo '
					</div>
				</div>';
		}
		
		// drept?
		elseif (page_min_side::$active_player->data['up_deactivated_dead'] != 0)
		{
			$instant = page_min_side::$active_player->data['up_deactivated_dead'] == 1;
			echo '
				<div class="bg1_c">
					<h1 class="bg1">Drept<span class="left2"></span><span class="right2"></span></h1>'.(access::has("mod") ? '
					<p class="h_right"><a href="'.htmlspecialchars(page_min_side::addr("activate")).'">aktiver</a></p>' : '').'
					<div class="bg1">
						<p>Denne spilleren '.($instant ? 'ble drept' : 'døde av skader').(access::has("mod") ? ($instant ? ' av <user id="'.page_min_side::$active_player->data['up_deactivated_up_id'].'" />' : ' påført av <user id="'.page_min_side::$active_player->data['up_deactivated_up_id'].'" />') : '').' '.ess::$b->date->get(page_min_side::$active_player->data['up_deactivated_time'])->format(date::FORMAT_SEC).'.</p>
					</div>
				</div>';
		}
		
		// deaktivert?
		else
		{
			// deaktivert av seg selv?
			$deact_self = false;
			if (!empty(page_min_side::$active_player->data['up_deactivated_up_id']))
			{
				$deact_self = page_min_side::$active_player->data['up_deactivated_up_id'] == page_min_side::$active_player->id;
				if (!$deact_self)
				{
					$result = \Kofradia\DB::get()->query("SELECT u_id FROM users JOIN users_players ON u_id = up_u_id WHERE up_id = ".page_min_side::$active_player->data['up_deactivated_up_id']);
					$row = $result->fetch();
					unset($result);
					if ($row && $row['u_id'] == page_min_side::$active_user->id) $deact_self = true;
				}
			}
			
			echo '
				<div class="bg1_c">
					<h1 class="bg1">Deaktivert<span class="left2"></span><span class="right2"></span></h1>'.(access::has("mod") ? '
					<p class="h_right"><a href="'.htmlspecialchars(page_min_side::addr("cdeact")).'">rediger</a> <a href="'.htmlspecialchars(page_min_side::addr("activate")).'">aktiver</a></p>' : '').'
					<div class="bg1">'.($deact_self ? '
						<p>Denne spilleren deaktiverte seg selv '.ess::$b->date->get(page_min_side::$active_player->data['up_deactivated_time'])->format(date::FORMAT_SEC).'.</p>' : '
						<p>Denne spilleren ble deaktivert '.ess::$b->date->get(page_min_side::$active_player->data['up_deactivated_time'])->format(date::FORMAT_SEC).(!page_min_side::$active_own ? ' av '.(empty(page_min_side::$active_player->data['up_deactivated_up_id']) ? 'en ukjent bruker' : '<user id="'.page_min_side::$active_player->data['up_deactivated_up_id'].'" />') : '').'.</p>').'
						<div class="p"><b>Begrunnelse:</b> '.(empty(page_min_side::$active_player->data['up_deactivated_reason']) ? 'Ingen begrunnelse oppgitt.' : game::bb_to_html(page_min_side::$active_player->data['up_deactivated_reason'])).'</div>'.(!page_min_side::$active_own && !$deact_self ? '
						<div class="p"><b>Intern informasjon:</b> '.(access::has("mod") ? (empty(page_min_side::$active_player->data['up_deactivated_note']) ? 'Ingen intern informasjon oppgitt.' : game::bb_to_html(page_min_side::$active_player->data['up_deactivated_note'])) : 'Du har ikke tilgang til å se intern informasjon.').'</div>' : '').'
					</div>
				</div>';
		}
		
		echo '
				<div class="bg1_c">
					<h1 class="bg1">Avstand til neste rangert spiller<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						'.($rank_user_next ? $rank_user_next : '<p>Du er høyest rangert!</p>').($rank_user_prevnext ? '
						<div class="progressbar ms_space_bt">
							<div class="progress" style="width: '.round($rank_user_prevnext).'%">
								<p>Avstand forrige/neste spiller: '.game::format_number($rank_user_prevnext, 4).' %</p>
							</div>
						</div>' : '').'
					</div>
				</div>';
		
		if (page_min_side::$active_player->active)
		{
			OFC::embed("ranklevel_last_days", "graphs/ranklevel_last_days?up_id=".page_min_side::$active_player->id, "100%", 150);
			echo '
				<div class="bg1_c">
					<h1 class="bg1">Ditt ranknivå siste dagene<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1" style="padding: 0 0 5px; background-color: #1A1A1A">
						<p style="font-size: 10px; margin: 5px">Denne grafen sammenlikner deg med de 10 beste rankerne de siste dagene.</p>
						<span id="ranklevel_last_days"></span>
					</div>
				</div>';
		}
		
		echo '
			</div>
		</div>
	</div>';
	}
	
	/**
	 * Informasjon om spilleren
	 */
	protected static function page_info()
	{
		// kan ikke se?
		if (!page_min_side::$pstats)
		{
			echo '
	<p class="c">Du har ikke tilgang til å se denne siden.</p>';
			return;
		}
		
		global $_game;
		
		// antall ganger vi har vunnet i lotto
		$result = \Kofradia\DB::get()->query("SELECT COUNT(id), SUM(won) FROM lotto_vinnere WHERE lv_up_id = ".page_min_side::$active_player->id);
		$row = $result->fetch(\PDO::FETCH_NUM);
		$lotto_vinn = $row[0];
		$lotto_vinn_sum = $row[1];
		
		ess::$b->page->add_css('
.minside_stats_h { margin-bottom: 0; text-decoration: underline }
.minside_stats_d { margin-top: 0 }');
		
		// pengeplassering
		if (access::has("mod"))
		{
			$result = \Kofradia\DB::get()->query("SELECT COUNT(up_id)+1 FROM users_players WHERE up_cash+up_bank > ".page_min_side::$active_player->data['up_cash']."+".page_min_side::$active_player->data['up_bank']." AND up_access_level < {$_game['access_noplay']} AND up_access_level != 0");
			$pengeplassering = $result->fetchColumn(0);
		}
		
		echo '
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Basisinformasjon<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						<dl class="dd_right">
							<dt>Spillerens ID</dt>
							<dd>#'.page_min_side::$active_player->id.'</dd>
							<dt>Opprettet</dt>
							<dd>'.ess::$b->date->get(page_min_side::$active_player->data['up_created_time'])->format().'<br />
								'.game::timespan(page_min_side::$active_player->data['up_created_time'], game::TIME_ABS).'</dd>
							<dt>Spillernavn</dt>
							<dd>'.page_min_side::$active_player->profile_link().'</dd>
							<dt>Plassering</dt>
							<dd><a href="bydeler?bydel='.page_min_side::$active_player->bydel['name'].'">'.page_min_side::$active_player->bydel['name'].'</a></dd>
						</dl>
					</div>
				</div>';
		
		echo '
				<div class="bg1_c">
					<h1 class="bg1">Spilleressurser<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						<dl class="dd_right">
							<dt>Penger ute</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_cash']).'</dd>
							<dt>Penger i bank</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_bank']).'</dd>
							<dt>Rankplassering</dt>
							<dd>#'.page_min_side::$active_player->data['upr_rank_pos'].'</dd>'.(access::has("mod") ? '
							<dt>Poeng</dt>
							<dd>'.(access::has("admin") ? '<a href="'.htmlspecialchars(page_min_side::addr("crew", "b=rank")).'">' : '').game::format_number(page_min_side::$active_player->data['up_points']).(access::has("admin") ? '</a>' : '').'</dd>
							<dt>Pengeplassering</dt>
							<dd>'.game::format_number($pengeplassering).'. plass</dd>' : '').'
						</dl>
					</div>
				</div>
				<div class="bg1_c">
					<h1 class="bg1">Våpen<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">';
		
		// TODO: lenker til å kjøpe våpen og beskyttelse (hvis man har høy nok rank) etc
		if (!page_min_side::$active_player->weapon)
		{
			
			echo '
						<p>Du har ikke noe våpen. Du kjøper våpen hos et våpen og beskyttelse-firma via bydeler.</p>';
		}
		else
		{
			echo '
						<dl class="dd_right">
							<dt>Våpen</dt>
							<dd>'.htmlspecialchars(page_min_side::$active_player->weapon->data['name']).'</dd>
							<dt>Kulekapasitet</dt>
							<dd>'.page_min_side::$active_player->weapon->data['bullets'].'</dd>
							<dt>Antall kuler i våpenet</dt>
							<dd>'.page_min_side::$active_player->data['up_weapon_bullets'].'</dd>
							<dt>Våpentrening</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_weapon_training']*100, 2).' %</dd>
						</dl>';
		}
		
		echo '
					</div>
				</div>
				<div class="bg1_c">
					<h1 class="bg1">Beskyttelse<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">';
		
		if (!page_min_side::$active_player->protection->data)
		{
			echo '
						<p>Du har ingen beskyttelse. Du kjøper beskyttelse hos et våpen og beskyttelse-firma via bydeler.</p>';
		}
		else
		{
			echo '
						<dl class="dd_right">
							<dt>Beskyttelse</dt>
							<dd>'.htmlspecialchars(page_min_side::$active_player->protection->data['name']).'</dd>
							<dt>Status</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_protection_state']*100, 2).' %</dd>
						</dl>';
			}
		
		// i bomberom?
		$bomberom_wait = page_min_side::$active_player->bomberom_wait();
		if ($bomberom_wait > 0)
		{
			echo '
						<p>Befinner seg i <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.page_min_side::$active_player->data['up_brom_ff_id'].'">bomberom</a> til '.ess::$b->date->get(page_min_side::$active_player->data['up_brom_expire'])->format().' ('.game::counter($bomberom_wait).' gjenstår).</p>';
		}
		
		echo '
					</div>
				</div>';
		
		echo '
			</div>
		</div>
		<div class="col_w right">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Statistikk<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						<p class="minside_stats_h">Generelt</p>
						<dl class="dd_right minside_stats_d">
							<dt>Sidevisninger</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_hits']).'</dd>
							<dt>Videresendinger</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_hits_redirect']).'</dd>
						</dl>
						<p class="minside_stats_h">Profil</p>
						<dl class="dd_right minside_stats_d">
							<dt>Visninger i profilen</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_profile_hits']).'</dd>
						</dl>
						<p class="minside_stats_h">Spiller</p>
						<dl class="dd_right minside_stats_d">
							<dt>Ant. ganger i fengsel</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_fengsel_num']).'</dd>
							<dt>Antall utbrytninger</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_fengsel_num_out_success']).' / '.game::format_number(page_min_side::$active_player->data['up_fengsel_num_out_tries']).'</dd>
							<dt>Antall ganger vunnet i lotto</dt>
							<dd><a href="lotto_vinn'.(page_min_side::$active_user->id != login::$user->id ? '?up_id='.page_min_side::$active_player->id : '').'">'.game::format_number($lotto_vinn).'</a></dd>
							<dt>Totalt gitt fra seg i fengseldusører</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_fengsel_dusor_total_out']).'</dd>
							<dt>Totalt skaffet av fengseldusører</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_fengsel_dusor_total_in']).'</dd>
							<dt>Totalt vunnet i lotto</dt>
							<dd>'.game::format_cash($lotto_vinn_sum).'</dd>
							<dt>Siste rentebeløp</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_interest_last']).'</dd>
							<dt>Totalt brukt på auksjoner</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_auksjoner_total_out']).'</dd>
							<dt>Totalt tjent på auksjoner</dt>
							<dd>'.game::format_cash(page_min_side::$active_player->data['up_auksjoner_total_in']).'</dd>
						</dl>
						<p class="minside_stats_h">Meldinger og forum</p>
						<dl class="dd_right minside_stats_d">
							<dt>Nye meldinger</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_inbox_num_threads']).'</dd>
							<dt>Svar på meldinger</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_inbox_num_messages']).'</dd>
							<dt>Forumtråder</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_forum_num_topics']).'</dd>
							<dt>Forumsvar</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_forum_num_replies']).'</dd>
							<dt>Forumtråder i firma/broderskap</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_forum_ff_num_topics']).'</dd>
							<dt>Forumsvar i firma/broderskap</dt>
							<dd>'.game::format_number(page_min_side::$active_player->data['up_forum_ff_num_replies']).'</dd>
						</dl>
						<p class="minside_stats_h">Angrep</p>
						<dl class="dd_right minside_stats_d">
							<dt>Angrep hvor spilleren ikke ble funnet</dt>
							<dd>'.game::format_num(page_min_side::$active_player->data['up_attack_failed_num']).'</dd>
							<dt>Angrep hvor spilleren ble skadet</dt>
							<dd>'.game::format_num(page_min_side::$active_player->data['up_attack_damaged_num']).'</dd>
							<dt>Angrep hvor spilleren ble drept</dt>
							<dd>'.game::format_num(page_min_side::$active_player->data['up_attack_killed_num']).'</dd>
							<dt>Angrep hvor spilleren døde av skadene påført</dt>
							<dd>'.game::format_num(page_min_side::$active_player->data['up_attack_bleed_num']).'</dd>'.(page_min_side::$active_player->data['up_df_time'] ? '
							<dt>Siste angrep ble utført</dt>
							<dd>'.ess::$b->date->get(page_min_side::$active_player->data['up_df_time'])->format().'</dd>' : '').'
						</dl>
					</div>
				</div>
			</div>
		</div>
	</div>';
	}
	
	/**
	 * Prestasjoner
	 */
	protected static function page_achievements()
	{
		ess::$b->page->add_title("Prestasjoner");
		kf_menu::page_id("achievements");
		
		// kan ikke se?
		if (!page_min_side::$pstats)
		{
			echo '
	<p class="c">Du har ikke tilgang til å se denne siden.</p>';
			return;
		}
		
		echo '
	<p class="c">Du har oppnådd totalt '.game::format_num(page_min_side::$active_player->data['up_achievements_points']).' prestasjonspoeng.</p>
	
	<div class="achievements">';
		
		// hent repetisjoner
		$rep_all = page_min_side::$active_player->achievements->get_rep_count();
		
		// grupper etter gjentakelsemulighet
		$list = array(
			"norep" => array(),
			"rep" => array()
		);
		foreach (achievements::$achievements as $a)
		{
			$list[$a->data['ac_recurring'] ? 'rep' : 'norep'][] = $a;
		}
		
		foreach ($list as $type => $all)
		{
			echo '
		<div class="achievements_group">
			<p class="ac_group">'.($type == "rep" ? 'Repeterende prestasjoner:' : 'Enkeltoppnående prestasjoner:').'</p>';
			
			foreach ($all as $a)
			{
				// hent premie
				$prize = sentences_list($a->get_prizes()/*, "<br />", "<br />"*/);
				if (empty($prize)) $prize = '&nbsp;';
				
				// sjekk om utført
				if (isset($rep_all[$a->id]))
				{
					if ($a->data['ac_recurring'])
					{
						$done = 'Oppnådd '.fwords("%d gang", "%d ganger", $rep_all[$a->id]['count_upa_id']);
						$done .= '<br />Sist '.ess::$b->date->get($rep_all[$a->id]['max_upa_time'])->format();
					}
					else
					{
						$done = 'Oppnådd '.ess::$b->date->get($rep_all[$a->id]['max_upa_time'])->format();
					}
				}
				else
				{
					$done = "Du har ikke oppnådd denne prestasjonen";
				}
				
				$img = isset($rep_all[$a->id]) && !$a->data['ac_recurring'] ? '<img src="'.STATIC_LINK.'/icon/ruby.png" alt="" title="Oppnådd" /> ' : '';
				
				// fremdrift
				$progress = '';
				$item = new achievement_player_item(page_min_side::$active_player, $a);
				$item->load_active();
				if ($p = $item->get_progress())
				{
					$w = round($p['current'] / $p['target'] * 100, 1);
					$progress = '
			<div class="ac_progress" title="Fremdrift: '.$p['current'].' / '.$p['target'].'" style="width: '.$w.'%"></div>';
				}
				
				echo '
		<div class="ac_row">'.$progress.'
			<div class="ac_data'.($progress ? ' ac_data_progress' : '').'">
				<h2>'.htmlspecialchars($a->data['ac_name']).'</h2>'.($a->data['ac_text'] ? '
				<p class="ac_text">'.$a->data['ac_text'].'</p>' : '').'
				<p class="ac_prize">Premie: '.$prize.'</p>
				<p class="ac_apoints" title="Prestasjonspoeng">'.$img.$a->data['ac_apoints'].'</p>
				<p class="ac_status">'.$done.'</p>
			</div>
		</div>';
			}
			
			echo '
		</div>';
		}
		
		echo '
	</div>';
	}
	
	/**
	 * Hendelsene til spilleren
	 */
	protected static function page_log()
	{
		// kan ikke se?
		if (!page_min_side::$pstats)
		{
			echo '
	<p class="c">Du har ikke tilgang til å se denne siden.</p>';
			return;
		}
		
		global $_game;
		
		ess::$b->page->add_title("Hendelser");
		ess::$b->page->add_css('
.gamelog { width: 80%; margin: 0 auto }
.gamelog .time { color: #888888; padding-right: 2px }
.ffl_time {
	color: #AAA;
}
.log_section {
	background-color: #1C1C1C;
	padding: 15px 15px 5px;
	margin: 30px 0;
	border: 10px solid #111111;
}');
		echo '
	<div class="gamelog">';
		
		$gamelog = new gamelog();
		
		// finn ut hva som er tilgjengelig
		$result = \Kofradia\DB::get()->query("SELECT type, COUNT(id) AS count FROM users_log WHERE ul_up_id IN (0, ".page_min_side::$active_player->id.") GROUP BY type");
		$in_use = array();
		$count = array();
		$total = 0;
		while ($row = $result->fetch())
		{
			$in_use[] = $row['type'];
			$count[$row['type']] = $row['count'];
		}
		
		$tilgjengelig = array();
		foreach (gamelog::$items_id as $id => $name)
		{
			if (in_array($id, $in_use)) $tilgjengelig[$id] = $id;
		}
		
		// fjern ting vi ikke har tilgang til
		unset($tilgjengelig[gamelog::$items['crewforum_emne']], $count[gamelog::$items['crewforum_emne']]);
		unset($tilgjengelig[gamelog::$items['crewforum_svar']], $count[gamelog::$items['crewforum_svar']]);
		unset($tilgjengelig[gamelog::$items['crewforuma_emne']], $count[gamelog::$items['crewforuma_emne']]);
		unset($tilgjengelig[gamelog::$items['crewforuma_svar']], $count[gamelog::$items['crewforuma_svar']]);
		unset($tilgjengelig[gamelog::$items['crewforumi_emne']], $count[gamelog::$items['crewforumi_emne']]);
		unset($tilgjengelig[gamelog::$items['crewforumi_svar']], $count[gamelog::$items['crewforumi_svar']]);
		
		$i_bruk = $tilgjengelig;
		$total = array_sum($count);
		
		// nye hendelser (viser også nye hendelser i firma/familie)?
		if ((page_min_side::$active_player->data['up_log_ff_new'] > 0 || page_min_side::$active_player->data['up_log_new'] > 0) && page_min_side::$active_own)
		{
			echo '
		<h1 class="c">Nye hendelser</h1>';
			
			// nye hendelser i ff?
			if (page_min_side::$active_player->data['up_log_ff_new'] > 0)
			{
				// totalt antall logg hendelser som blir vist
				$counter_total = 0;
				
				// hent FF vi skal hente logg for
				$ffm_result = \Kofradia\DB::get()->query("SELECT ffm_ff_id, ffm_log_new FROM ff_members WHERE ffm_up_id = ".page_min_side::$active_player->id." AND ffm_status = 1 AND ffm_log_new > 0");
				
				while ($ffm = $ffm_result->fetch())
				{
					$ff = ff::get_ff($ffm['ffm_ff_id'], ff::LOAD_SILENT);
					if (!$ff) continue;
					
					// hent hendelsene
					$result = \Kofradia\DB::get()->query("SELECT ffl_id, ffl_time, ffl_type, ffl_data, ffl_extra FROM ff_log WHERE ffl_ff_id = {$ff->id} ORDER BY ffl_time DESC LIMIT {$ffm['ffm_log_new']}");
					
					if ($result->rowCount() > 0)
					{
						$logs = array();
						while ($row = $result->fetch())
						{
							$counter_total++;
							
							$day = ess::$b->date->get($row['ffl_time'])->format(date::FORMAT_NOTIME);
							$data = $ff->format_log($row['ffl_id'], $row['ffl_time'], $row['ffl_type'], $row['ffl_data'], $row['ffl_extra']);
							
							$logs[$day][] = '<span class="ffl_time">'.ess::$b->date->get($row['ffl_time'])->format("H:i").':</span> '.$data;
						}
						
						echo '
		<div class="log_section">';
						
						$ff->load_header();
						
						foreach ($logs as $day => $items)
						{
							echo '
			<div class="section">
				<h2>'.$day.'</h2>';
							
							foreach ($items as $item)
							{
								echo '
				<p>'.$item.'</p>';
							}
							
							echo '
			</div>';
						}
						
						echo '
			<p class="c"><a href="ff/logg?ff_id='.$ff->id.'">Vis alle hendelsene for '.$ff->type['refobj'].' &raquo;</a></p>';
						
						$ff->load_footer();
						
						echo '
		</div>';
					}
				}
				
				// ble det ikke vist noen hendelser?
				if ($counter_total == 0)
				{
					echo '
		<div class="bg1_c xsmall">
			<h1 class="bg1">Logg for firma og broderskap<span class="left"></span><span class="right"></span></h1>
			<div class="bg1">
				<p>Ingen nye hendelser tilknyttet firma eller broderskap.</p>
			</div>
		</div>';
				}
				
				// nullstill telleren
				\Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_log_new = 0 WHERE ffm_up_id = ".page_min_side::$active_player->id);
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_log_ff_new = 0 WHERE up_id = ".page_min_side::$active_player->id);
				page_min_side::$active_player->data['up_log_ff_new'] = 0;
			}
			
			// nye normale hendelser
			if (page_min_side::$active_player->data['up_log_new'] > 0)
			{
				ess::$b->page->add_css('.ny { color: #FF0000 }');
				
				$i_bruk[] = 'NULL';
				$where = ' AND type IN ('.implode(",", $i_bruk).')';
				$result = \Kofradia\DB::get()->query("SELECT time, type, note, num FROM users_log WHERE ul_up_id IN (0, ".page_min_side::$active_player->id.")$where ORDER BY time DESC, id DESC LIMIT ".page_min_side::$active_player->data['up_log_new']);
				
				if ($result->rowCount() == 0)
				{
					echo '
		<p class="c">Ingen hendelser ble funnet.</p>';
				}
				
				else
				{
					// vis hendelsene
					$logs = array();
					while ($row = $result->fetch())
					{
						$day = ess::$b->date->get($row['time'])->format(date::FORMAT_NOTIME);
						$data = $gamelog->format_log($row['type'], $row['note'], $row['num']);
						
						$logs[$day][] = '
				<p><span class="time"><span class="ny">Ny!</span> - '.ess::$b->date->get($row['time'])->format("H:i").':</span> '.$data.'</p>';
					}
					
					foreach ($logs as $day => $items)
					{
						echo '
		<div class="bg1_c">
			<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">';
						
						foreach ($items as $item)
						{
							echo $item;
						}
						
						echo '
			</div>
		</div>';
					}
					
					echo '
				<p class="c">Viser '.page_min_side::$active_player->data['up_log_new'].' <b>ny'.(page_min_side::$active_player->data['up_log_new'] == 1 ? '' : 'e').'</b> hendelse'.(page_min_side::$active_player->data['up_log_new'] == 1 ? '' : 'r').'<br /><a href="'.htmlspecialchars(page_min_side::addr()).'">Se full oversikt</a></p>';
				}
				
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_log_new = 0 WHERE up_id = ".page_min_side::$active_player->id);
				page_min_side::$active_player->data['up_log_new'] = 0;
			}
			
			else
			{
				echo '
		<div class="bg1_c small bg1_padding">
			<h1 class="bg1">Normale hendelser<span class="left"></span><span class="right"></span></h1>
			<div class="bg1" id="logg">
				<p class="c"><a href="'.htmlspecialchars(page_min_side::addr()).'">Vis oversikt over alle normale hendelser &raquo;</a></p>
			</div>
		</div>';
			}
		}
		
		
		// vis vanlig visning
		else
		{
			// filter
			$filter = array();
			foreach ($_GET as $name => $val)
			{
				$matches = NULL;
				if (preg_match("/^f([0-9]+)$/Du", $name, $matches) && in_array($matches[1], $tilgjengelig))
				{
					$filter[] = $matches[1];
				}
			}
			if (count($filter) == 0) $filter = false;
			else
			{
				$i_bruk = $filter;
				$filter = true;
				
				ess::$b->page->add_message("Du har aktivert et filter og viser kun bestemte enheter.");
			}
			
			// hva skal vi vise?
			if (!$filter)
			{
				echo '
		<p class="c filterbox"><a href="#" onclick="toggle_display(\'.filterbox\', event)">Vis filteralternativer</a></p>';
			}
			
			echo '
		<div'.(!$filter ? ' style="display: none"' : '').' class="filterbox bg1_c">
			<h1 class="bg1">Filter<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">
				<p class="c">Velg filter (<a href="#" class="box_handle_toggle" rel="f[]">Merk alle</a>)</p>
				<form action="" method="get">'.(!page_min_side::$active_own || !page_min_side::$active_player->active ? '
					<input type="hidden" name="up_id" value="'.page_min_side::$active_player->id.'" />' : '').'
					<input type="hidden" name="a" value="log" />
					<table class="table center" width="100%">
						<tbody>';
			
			$tbody = new tbody(3); // 3 kolonner
			foreach ($tilgjengelig as $id)
			{
				$title = gamelog::$items_name[$id];
				$aktivt = in_array($id, $i_bruk) && $filter;
				$ant = $count[$id];
				$tbody->append('<input type="checkbox" name="f'.$id.'" rel="f[]" value=""'.($aktivt ? ' checked="checked"' : '').' />'.htmlspecialchars($title).' <span class="dark">('.$ant.' stk)</span>', 'class="box_handle"');
			}
			$tbody->clean();
			
			echo '
						</tbody>
					</table>
					<p class="c">'.show_sbutton("Oppdater").'</p>
				</form>
			</div>
		</div>';
			
			$i_bruk[] = "NULL";
			$where = ' AND type IN ('.implode(",", $i_bruk).')';
			
			// sideinformasjon - hent loggene på denne siden
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, max(50, page_min_side::$active_player->data['up_log_new']));
			$result = $pagei->query("SELECT time, type, note, num FROM users_log WHERE ul_up_id IN (0, ".page_min_side::$active_player->id.")$where ORDER BY time DESC, id DESC");
			
			if ($result->rowCount() == 0)
			{
				echo '
		<p class="c">Ingen hendelser ble funnet.</p>';
			}
			
			else
			{
				echo '
		<p class="c">Totalt har du <b>'.game::format_number($total).'</b> hendelse'.($total == 1 ? '' : 'r').'.</p>';
				
				if ($pagei->pages > 1)
				{
					echo '
		<p class="c">'.address::make($_GET, "", $pagei).'</p>';
				}
				
				// hendelsene
				$logs = array();
				$i = 0;
				$e = $pagei->start;
				while ($row = $result->fetch())
				{
					$day = ess::$b->date->get($row['time'])->format(date::FORMAT_NOTIME);
					$data = $gamelog->format_log($row['type'], $row['note'], $row['num']);
					
					$ny = $e < page_min_side::$active_player->data['up_log_new'];
					
					$logs[$day][] = '
				<p><span class="time">'.($ny ? '<span class="ny">Ny!</span> - ' : '').''.ess::$b->date->get($row['time'])->format("H:i").':</span> '.$data.'</p>';
					$e++;
				}
				
				foreach ($logs as $day => $items)
				{
					echo '
		<div class="bg1_c">
			<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">';
					
					foreach ($items as $item)
					{
						echo $item;
					}
					
					echo '
			</div>
		</div>';
				}
				
				echo '
		<p class="c">Viser '.$pagei->count_page.' av '.$pagei->total.' hendelse'.($pagei->total == 1 ? '' : 'r').'</p>';
				
				if ($pagei->pages > 1)
				{
					echo '
		<p class="c">'.address::make($_GET, "", $pagei).'</p>';
				}
			}
		}
		
		echo '
	</div>';
	}
	
	/**
	 * Forumalternativer for spilleren
	 */
	protected static function page_forum()
	{
		$subpage2 = getval("b");
		redirect::store(page_min_side::addr(NULL, ($subpage2 != "" ? "b=" . $subpage2 : '')));
		ess::$b->page->add_title("Forum");
		ess::$b->page->add_css('
.minside_set_links .active { color: #CCFF00 }');
		
		echo '
	<p class="c minside_set_links">
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "")).'"'.($subpage2 == "" ? ' class="active"' : '').'>Signatur</a> |
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=ft")).'"'.($subpage2 == "ft" ? ' class="active"' : '').'>Mine forumtråder</a> |
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=fr")).'"'.($subpage2 == "fr" ? ' class="active"' : '').'>Mine forumsvar</a>
	</p>';
		
		if ($subpage2 == "")
		{
			// vise signaturen?
			if (!page_min_side::$active_own && !access::has("forum_mod"))
			{
				ess::$b->page->add_title("Signatur");
				
				echo '
	<div class="bg1_c">
		<h1 class="bg1">Signatur<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du har kun rettigheter til å se innholdet av signaturen til denne spilleren.</p>'.(page_min_side::$active_player->data['up_forum_signature'] == "" ? '
			<p>Det er ingen signatur knyttet opp til denne spilleren.</p>' : '
			<p><textarea style="width: 98%" rows="4">'.htmlspecialchars(page_min_side::$active_player->data['up_forum_signature']).'</textarea></p>').'
		</div>
	</div>';
			}
			
			// har vi kun rettigheter til å fjerne signaturen?
			elseif (!page_min_side::$active_player->active && !access::has("forum_mod"))
			{
				// fjerne signaturen?
				if (isset($_POST['remove']))
				{
					if (page_min_side::$active_player->data['up_forum_signature'] == "")
					{
						ess::$b->page->add_message("Det var ingen signatur å fjerne.");
					}
					else
					{
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_forum_signature = NULL WHERE up_id = ".page_min_side::$active_player->id);
						ess::$b->page->add_message("Signaturen ble fjernet.");
					}
					if (page_min_side::$active_player->active || access::has("mod")) redirect::handle(page_min_side::addr());
					else redirect::handle(page_min_side::addr(""));
				}
				
				ess::$b->page->add_title("Fjerne signatur");
				
				echo '
	<div class="bg1_c">
		<h1 class="bg1">Fjern signatur for forumet<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Siden denne spilleren er deaktivert kan du ikke redigere signaturen. Du kan derimot fjerne signaturen om det er ønskelig.</p>'.(page_min_side::$active_player->data['up_forum_signature'] == "" ? '
			<p>Det er ingen signatur knyttet opp til denne spilleren.</p>' : '
			<p>Den nåværende signaturen vises i tilfelle det er ønskelig å hente ut informasjon fra den:</p>
			<p><textarea style="width: 98%" rows="4">'.htmlspecialchars(page_min_side::$active_player->data['up_forum_signature']).'</textarea></p>
			<form action="" method="post">
				<p class="c"><span class="red">'.show_sbutton("Fjern signaturen", 'name="remove" onclick="return confirm(\'Er du sikker på at du ønsker å fjerne signaturen fra denne spilleren? Denne handlingen kan ikke angres.\')"').'</span></p>
			</form>').'
		</div>
	</div>';
			}
			
			else
			{
				// blokkert fra å endre signaturen?
				$blokkering = blokkeringer::check(blokkeringer::TYPE_SIGNATUR);
				
				// lagre endringer?
				if (isset($_POST['save_forum']) && (!$blokkering || access::has("forum_mod")))
				{
					$signature = trim(postval("signature"));
					if ($signature == page_min_side::$active_player->data['up_forum_signature'])
					{
						ess::$b->page->add_message("Ingen endringer ble utført.", "error");
					}
					
					else
					{
						// legge til som mod?
						$ok = true;
						if (page_min_side::$active_user->id != login::$user->id)
						{
							// mangler logg?
							$log = trim(postval("log"));
							if ($log == "")
							{
								ess::$b->page->add_message("Mangler begrunnelse.", "error");
								$ok = false;
							}
							
							elseif (!self::advarsel_handle("signature", $log))
							{
								$ok = false;
							}
							
							else
							{
								// legg til crewlogg
								crewlog::log("player_signature", page_min_side::$active_player->id, $log, array(
									"signature_old" => page_min_side::$active_player->data['up_forum_signature'],
									"signature_diff" => diff::make(page_min_side::$active_player->data['up_forum_signature'], $signature))
								);
							}
						}
						
						if ($ok)
						{
							\Kofradia\DB::get()->exec("UPDATE users_players SET up_forum_signature = ".\Kofradia\DB::quote($signature)." WHERE up_id = ".page_min_side::$active_player->id);
							ess::$b->page->add_message("Signaturen ble endret.");
							redirect::handle(page_min_side::addr());
						}
					}
				}
				
				ess::$b->page->add_title("Rediger signatur");
				ess::$b->page->add_css('
.minside_preview {
	border: 1px dotted #525252;
	padding: 5px;
}
.minside_preview .p {
	margin: 5px 0;
}');
				ess::$b->page->add_js('
function minside_preview_forum()
{
	var data = $("minside_signatur").value;
	var preview_box = $("preview_forum").empty();
	var p = new Element("div").set("class", "p").set("html", "Henter forhåndsvisning...").inject(preview_box.set("class", "minside_preview"));
	
	// xhr objekt
	var xhr = new Request({
		"url": relative_path + "/ajax/bb",
		"autoCancel": true
	});
	xhr.addEvents({
		"success": function(data, xml)
		{
			var text = ajax.parse_data(xmlGetValue(xml, "content"));
			
			if (text == "") p.set("html", "Mangler innhold.");
			else p.set("html", text);
			
			ajax.refresh();
		},
		"failure": function(xhr)
		{
			p.set("html", "Feil:<br />"+xhr.responseText);
		}
	});
	xhr.options.data = { "text": data };
	xhr.send();
}');
				echo '
	<div class="bg1_c">
		<h1 class="bg1">Rediger signatur for forumet<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">'.($blokkering && !access::has("mod") ? '
			<p class="error_box">Du er blokkert fra å redigere signaturen din. Blokkeringen varer til '.ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).'.<br /><b>Begrunnelse:</b> '.game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>' : '').'
			<boxes />
			<p><b>Begrensninger:</b> Maks 4-5 linjer med tekst. Dersom du har et eller flere bilder i signaturen er maks høyde totalt 100 pixels og total størrelse 50 KB. Ved bilde kan man kun ha én linje tekst over eller under bildet. Hvis bildet ditt er for stort, kan du prøve å lagre det som PNG og bruke <a href="https://tinypng.com/">https://tinypng.com/</a> for å komprimere det.</p>
			<p>Vanlige <a href="'.ess::$s['relative_path'].'/node/11" target="_blank">BB-koder</a> og <a href="'.ess::$s['relative_path'].'/node/15" target="_blank">uttryks-ikon</a> kan benyttes.</p>
			<form action="" method="post">
				<p><textarea name="signature" id="minside_signatur" style="width: 98%" rows="4">'.htmlspecialchars(postval("signature", page_min_side::$active_player->data['up_forum_signature'])).'</textarea></p>
				<div id="preview_forum"></div>'.(!page_min_side::$active_own ? '
				<dl class="dd_right">
					<dt>Begrunnelse for endring (crewlogg)</dt>
					<dd><textarea name="log" rows="3" cols="40">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>' : '').'
				<p class="c">'.show_sbutton("Lagre endringer", 'name="save_forum"').' '.show_button("Forhåndsvis", 'onclick="minside_preview_forum()"').'</p>'.(!page_min_side::$active_own ? '
				'.self::advarsel_input("signature") : '').'
			</form>
		</div>
	</div>';
			}
		}
		
		elseif ($subpage2 == "ft")
		{
			ess::$b->page->add_title("Forumtråder");
			
			echo '
	<div class="bg1_c">
		<h1 class="bg1">Mine forumtråder i forumet<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">';
			
			// vise slettede?
			$deleted = isset($_GET['sd']);
			$deleted_sql = $deleted ? '' : ' AND ft_deleted = 0';
			
			// hent trådene
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
			$result = $pagei->query("
				SELECT IF(fse_ff_id IS NULL, fse_name, ff_name) fse_name, ft_id, ft_type, ft_title, ft_fse_id, ft_time, ft_views, ft_up_id, ft_locked, ft_replies, ft_last_reply, r.fr_time AS r_time, fs_time, COUNT(rs.fr_id) AS fs_new, ft_deleted
				FROM forum_topics
					LEFT JOIN forum_replies AS r ON ft_last_reply = r.fr_id
					LEFT JOIN forum_sections ON ft_fse_id = fse_id
					LEFT JOIN forum_seen ON fs_ft_id = ft_id AND fs_u_id = ".page_min_side::$active_user->id."
					LEFT JOIN forum_replies AS rs ON rs.fr_ft_id = ft_id AND rs.fr_time > fs_time AND rs.fr_deleted = 0
					LEFT JOIN ff ON ff_id = fse_ff_id
				WHERE ft_up_id = ".page_min_side::$active_player->id."$deleted_sql
				GROUP BY ft_id ORDER BY ft_time DESC");
			
			echo '
			<p>Oversikt over tråder som er opprettet'.($deleted ? ' (viser også <span style="color: #FF0000">slettede</span> tråder)' : '').':</p>';
			
			// ingen tråder
			if ($result->rowCount() == 0)
			{
				echo '
			<p>Fant ingen forumtråder.</p>';
			}
			
			else
			{
				echo '
			<p>Totalt <b>'.$pagei->total.'</b> tråd'.($pagei->total == 1 ? '' : 'er').':</p>
			<table class="table forum" style="width: 100%">
				<thead>
					<tr>
						<th>Tittel</th>
						<th>Svar</th>
						<th>Visninger</th>
						<th>Dato</th>
					</tr>
				</thead>
				<tbody>';
				
				// vis trådene
				$i = 0;
				while ($row = $result->fetch())
				{
					// sjekke status?
					$fs_info = '';
					$fs_link_suffix = '';
					if (empty($row['fs_time']))
					{
						$fs_info = ' <span class="fs_ft_new">NY!</span>';
					}
					elseif ($row['fs_time'] < $row['r_time'])
					{
						$fs_info = ' <span class="fs_fr_new">'.$row['fs_new'].' <span class="fs_fr_newi">NY'.($row['fs_new'] == 1 ? '' : 'E').'</span></span>';
						$fs_link_suffix = '&amp;fs';
					}
					
					$date = ess::$b->date->get($row['ft_time']);
					echo '
				<tr class="'.(++$i % 2 == 0 ? 'color' : '').'">
					<td class="f">'.(access::has("forum_mod") || $row['ft_deleted'] == 0 ? '<a href="forum/topic?id='.$row['ft_id'].$fs_link_suffix.'">'.htmlspecialchars($row['ft_title']).'</a>' : htmlspecialchars($row['ft_title'])).($row['ft_type'] > 1 ? ($row['ft_type'] == 3 ? ' <span style="color: #CCFF00; font-weight: bold">(Viktig)</span>' : ' <span style="color: #CCFF00">(Sticky)</span>') : '').($row['ft_locked'] == 1 ? ' <span class="forum_lock">(låst)</span>' : '').($row['ft_deleted'] != 0 ? ' <span style="color: #FF0000">(Slettet)</span>' : '').$fs_info.'<br /><a href="forum/forum?id='.$row['ft_fse_id'].'" style="color: #555">'.htmlspecialchars($row['fse_name']).'</a></td>
					<td>'.game::format_number($row['ft_replies']).'</td>
					<td>'.game::format_number($row['ft_views']).'</td>
					<td class="f_time nowrap">'.$date->format(date::FORMAT_NOTIME).'<br />'.$date->format("H:i:s").'</td>
				</tr>';
				}
				
				echo '
			</tbody>
		</table>';
				
				// sidetall
				if ($pagei->pages > 0)
				{
					echo '
			<p class="c">'.$pagei->pagenumbers().'</p>';
				}
			}
			
			// slettede lenke
			if ($deleted)
			{
				echo '
			<p><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=ft")).'">Skjul slettede</a></p>';
			}
			else
			{
				echo '
			<p><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=ft&sd")).'">Vis slettede</a></p>';
			}
			
			echo '
		</div>
	</div>';
		}
		
		// forumsvar
		elseif ($subpage2 == "fr")
		{
			ess::$b->page->add_title("Forumsvar");
			
			echo '
	<div class="bg1_c">
		<h1 class="bg1">Mine forumsvar i forumet<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">';
			
			// vise slettede?
			$deleted = isset($_GET['sd']);
			$deleted_sql = $deleted ? '' : ' AND r.fr_deleted = 0';
			
			// hent svar
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
			$result = $pagei->query("
				SELECT IF(fse_ff_id IS NULL, fse_name, ff_name) fse_name, r.fr_id, ft_id, ft_type, ft_title, ft_fse_id, ft_time, ft_views, ft_up_id, ft_locked, ft_replies, r.fr_time, fs_time, COUNT(rs.fr_id) AS fs_new, r.fr_deleted, ft_deleted
				FROM forum_replies r
					LEFT JOIN forum_topics t ON r.fr_ft_id = ft_id
					LEFT JOIN forum_sections ON ft_fse_id = fse_id
					LEFT JOIN forum_seen ON fs_ft_id = ft_id AND fs_u_id = ".page_min_side::$active_user->id."
					LEFT JOIN forum_replies AS rs ON rs.fr_ft_id = ft_id AND rs.fr_time > fs_time AND rs.fr_deleted = 0
					LEFT JOIN ff ON ff_id = fse_ff_id
				WHERE r.fr_up_id = ".page_min_side::$active_player->id."$deleted_sql
				GROUP BY r.fr_id ORDER BY r.fr_time DESC");
			
			echo '
			<p>Oversikt over svar som er opprettet i de ulike trådene'.($deleted ? ' (viser også <span style="color: #FF0000">slettede</span> svar)' : '').':</p>';
			
			// ingen svar
			if ($result->rowCount() == 0)
			{
				echo '
			<p>Fant ingen forumsvar.</p>';
			}
			
			else
			{
				echo '
			<p>Totalt <b>'.$pagei->total.'</b> forumsvar:</p>
			<table class="table forum" style="width: 100%">
				<thead>
					<tr>
						<th>Trådtittel</th>
						<th>Trådskaper</th>
						<th>Svar</th>
						<th>Visninger</th>
						<th>Dato</th>
					</tr>
				</thead>
				<tbody>';
				
				// vis svarene
				$i = 0;
				while ($row = $result->fetch())
				{
					// sjekke status?
					$fs_info = '';
					if (empty($row['fs_time']))
					{
						$fs_info = ' <span class="fs_ft_new">NY!</span>';
					}
					elseif ($row['fs_time'] < $row['fr_time'])
					{
						$fs_info = ' <span class="fs_fr_new">'.$row['fs_new'].' <span class="fs_fr_newi">NY'.($row['fs_new'] == 1 ? '' : 'E').'</span></span>';
					}
					
					$date = ess::$b->date->get($row['fr_time']);
					echo '
					<tr class="'.(++$i % 2 == 0 ? 'color' : '').'">
						<td class="f">'.
							(($row['ft_deleted'] == 0 && $row['fr_deleted'] == 0) || access::has("forum_mod") ? '<a href="forum/topic?id='.$row['ft_id'].'&amp;replyid='.$row['fr_id'].'">'.htmlspecialchars($row['ft_title']).'</a>' : htmlspecialchars($row['ft_title'])).
							($row['ft_type'] > 1 ? ($row['ft_type'] == 3 ? ' <span style="color: #CCFF00; font-weight: bold">(Viktig)</span>' : ' <span style="color: #CCFF00">(Sticky)</span>') : '').
							($row['ft_locked'] == 1 ? ' <span class="forum_lock">(låst)</span>' : '').
							($row['fr_deleted'] != 0 ? ' <span style="color: #FF0000">(Slettet)</span>' : '').
							($row['ft_deleted'] != 0 ? ' <span style="color: #555">(Tråd slettet)</span>' : '').
							$fs_info.'<br /><a href="forum/forum?id='.$row['ft_fse_id'].'" style="color: #555">'.htmlspecialchars($row['fse_name']).'</a></td>
						<td><user id="'.$row['ft_up_id'].'" /></td>
						<td>'.game::format_number($row['ft_replies']).'</td>
						<td>'.game::format_number($row['ft_views']).'</td>
						<td class="f_time nowrap">'.$date->format(date::FORMAT_NOTIME).'<br />'.$date->format("H:i:s").'</td>
					</tr>';
				}
				
				echo '
				</tbody>
			</table>';
				
				// sidetall
				if ($pagei->pages > 0)
				{
					echo '
			<p class="c">'.$pagei->pagenumbers().'</p>';
				}
			}
			
			// slettede lenke
			if ($deleted)
			{
				echo '
			<p><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=fr")).'">Skjul slettede</a></p>';
			}
			else
			{
				echo '
			<p><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=fr&sd")).'">Vis slettede</a></p>';
			}
			
			echo '
		</div>
	</div>';
		}
	}
	
	/**
	 * Profilinnstillinger for spilleren
	 */
	protected static function page_profil()
	{
		global $__server;
		
		$subpage2 = getval("b");
		redirect::store(page_min_side::addr(NULL, ($subpage2 != "" ? "b=" . $subpage2 : '')));
		ess::$b->page->add_title("Profil");
		ess::$b->page->add_css('
.minside_links2 .active { color: #CCFF00 }');
		
		echo '
	<p class="c minside_links2">
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "")).'"'.($subpage2 == "" ? ' class="active link-icon link-icon-underline"' : ' class="link-icon link-icon-underline"').'><img src="'.STATIC_LINK.'/icon/page_edit.png" alt="" /><span>Profiltekst</span></a> |
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=profilbilde")).'"'.($subpage2 == "profilbilde" ? ' class="active link-icon link-icon-underline"' : ' class="link-icon link-icon-underline"').'><img src="'.STATIC_LINK.'/icon/image.png" alt="" /><span>Profilbilde</span></a>
	</p>';
		
		// profiltekst
		if ($subpage2 == "")
		{
			// vise signaturen?
			if (!page_min_side::$active_own && !access::has("mod"))
			{
				echo '
	<div class="bg1_c">
		<h1 class="bg1">Profiltekst<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du har kun rettigheter til å se innholdet av profilteksten til denne spilleren.</p>'.(page_min_side::$active_player->data['up_profile_text'] == "" ? '
			<p>Det er ingen profiltekst knyttet opp til denne spilleren.</p>' : '
			<p><textarea style="width: 98%" rows="30">'.htmlspecialchars(page_min_side::$active_player->data['up_profile_text']).'</textarea></p>').'
		</div>
	</div>';
			}
			
			// har vi kun rettigheter til å fjerne profilteksten?
			elseif (!page_min_side::$active_player->active && !access::has("mod"))
			{
				// fjerne profilteksten?
				if (isset($_POST['remove']))
				{
					if (page_min_side::$active_player->data['up_profile_text'] == "")
					{
						ess::$b->page->add_message("Det var ingen tekst å fjerne.");
					}
					else
					{
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_profile_text = NULL WHERE up_id = ".page_min_side::$active_player->id);
						ess::$b->page->add_message("Innholdet i profilen ble fjernet.");
					}
					if (page_min_side::$active_player->active || access::has("mod")) redirect::handle(page_min_side::addr());
					else redirect::handle(page_min_side::addr(""));
				}
				
				ess::$b->page->add_title("Fjern profiltekst");
				
				echo '
	<div class="bg1_c">
		<h1 class="bg1">Fjern teksten i profilen<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Siden denne spilleren er deaktivert kan du ikke redigere profilen. Du kan derimot fjerne teksten i profilen om det er ønskelig.</p>
			<p><a href="p/'.htmlspecialchars(page_min_side::$active_player->data['up_name']).'/'.page_min_side::$active_player->id.'">Gå til profil &raquo;</a></p>'.(page_min_side::$active_player->data['up_profile_text'] == "" ? '
			<p>Profilen inneholder ikke noe tekst.</p>' : '
			<p>Det nåværende innholdet vises i tilfelle det er ønskelig å hente ut informasjon fra det:</p>
			<p><textarea style="width: 98%" rows="30">'.htmlspecialchars(page_min_side::$active_player->data['up_profile_text']).'</textarea></p>
			<form action="" method="post">
				<p class="c"><span class="red">'.show_sbutton("Fjern teksten i profilen", 'name="remove" onclick="return confirm(\'Er du sikker på at du ønsker å fjerne teksten i profilen for denne spilleren? Denne handlingen kan ikke angres.\')"').'</span></p>
			</form>').'
		</div>
	</div>';
			}
			
			else
			{
				// blokkert fra å endre profilteksten?
				$blokkering = blokkeringer::check(blokkeringer::TYPE_PROFIL);
				
				// lagre endringer?
				if (isset($_POST['save_profile_content']) && (!$blokkering || access::has("mod")))
				{
					$text = trim(postval("profile_text"));
					
					// ingen endringer?
					if ($text == page_min_side::$active_player->data['up_profile_text'])
					{
						ess::$b->page->add_message("Ingen endringer ble utført.", "error");
					}
					
					else
					{
						// legge til som mod?
						$ok = true;
						if (page_min_side::$active_user->id != login::$user->id)
						{
							$log = trim(postval("log"));
							
							// mangler logg?
							if ($log == "")
							{
								ess::$b->page->add_message("Mangler begrunnelse.", "error");
								$ok = false;
							}
							
							elseif (!self::advarsel_handle("profiletext", $log))
							{
								$ok = false;
							}
							
							else
							{
								// legg til crewlogg
								crewlog::log("player_profile_text", page_min_side::$active_player->id, $log, array(
									"profile_text_old" => page_min_side::$active_player->data['up_profile_text'],
									"profile_text_diff" => diff::make(page_min_side::$active_player->data['up_profile_text'], $text))
								);
							}
						}
						
						if ($ok)
						{
							\Kofradia\DB::get()->exec("UPDATE users_players SET up_profile_text = ".\Kofradia\DB::quote($text)." WHERE up_id = ".page_min_side::$active_player->id);
							ess::$b->page->add_message("Innholdet i profilen ble lagret.");
							redirect::handle(page_min_side::addr());
						}
					}
				}
				
				ess::$b->page->add_title("Rediger profiltekst");
				ess::$b->page->add_js('
function rp_preview_profile_text()
{
	var form = new Element("form")
		.set("method", "post")
		.set("target", "_blank")
		.set("action", (use_https ? relative_path : http_path) + "/p/'.rawurlencode(page_min_side::$active_player->data['up_name']).'/'.page_min_side::$active_player->id.'")
		.grab(
			new Element("input")
				.set("type", "hidden")
				.set("name", "preview")
				.set("value", $("profile_content").value)
		)
		.inject(document.body).submit();
	form.destroy();
}');
				
				echo '
	<div class="bg1_c">
		<h1 class="bg1">Rediger profiltekst<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">'.($blokkering && !access::has("mod") ? '
			<p class="error_box">Du er blokkert fra å redigere profilteksten din. Blokkeringen varer til '.ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).'.<br /><b>Begrunnelse:</b> '.game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>' : '').'
			<boxes />
			<p>Vanlige <a href="'.ess::$s['relative_path'].'/node/11" target="_blank">BB-koder</a> og <a href="'.ess::$s['relative_path'].'/node/15" target="_blank">uttryks-ikon</a> kan benyttes. I tillegg kan det benyttes egne <a href="'.ess::$s['relative_path'].'/node/12" target="_blank">BB-koder for profil</a>.</p>
			<form action="" method="post">
				<p><textarea name="profile_text" id="profile_content" style="width: 98%" rows="30">'.htmlspecialchars(postval("profile_text", page_min_side::$active_player->data['up_profile_text'])).'</textarea></p>'.(page_min_side::$active_user->id != login::$user->id ? '
				<dl class="dd_right">
					<dt>Begrunnelse for endring (crewlogg)</dt>
					<dd><textarea name="log" class="w300" rows="6" cols="40">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>' : '').'
				<p class="c">'.show_sbutton("Lagre endringer", 'name="save_profile_content" onclick="return confirm(\'Er du sikker på at du vil lagre profilen din?\\n\\nTips: Forhåndsvis profilen din før du gjør endringer for å se om alt blir som det skal.\')"').' '.show_button("Forhåndsvis", 'onclick="rp_preview_profile_text()"').'</p>'.(!page_min_side::$active_own ? '
				'.self::advarsel_input("profiletext") : '').'
			</form>
		</div>
	</div>';
			}
		}
		
		// profilbilde
		elseif ($subpage2 == "profilbilde")
		{
			ess::$b->page->add_title("Profilbilde");
			
			// blokkert fra å endre profilbilder?
			$blokkering = blokkeringer::check(blokkeringer::TYPE_PROFILE_IMAGE);
			
			// har vi valgt et profilbilde?
			if (isset($_POST['image_id']) && ((page_min_side::$active_player->active && page_min_side::$active_own && !$blokkering) || access::has("forum_mod")))
			{
				$id = (int) postval("image_id");
				
				$result = \Kofradia\DB::get()->query("SELECT id, pi_up_id, local, address, time FROM profile_images WHERE id = $id AND pi_up_id = ".page_min_side::$active_player->id);
				$image = $result->fetch();
				if (!$image)
				{
					ess::$b->page->add_message("Fant ikke bildet.", "error");
				}
				
				// slette bildet?
				elseif (isset($_POST['delete']))
				{
					// slett bildet
					\Kofradia\DB::get()->exec("DELETE FROM profile_images WHERE id = $id");
					
					// slette lokalt?
					if ($image['local'])
					{
						$src = PROFILE_IMAGES_FOLDER . "/" . $image['address'];
						$suf = mb_substr($src, mb_strrpos($src, ".")+1);
						if (file_exists($src) && ($suf == "jpg" || $suf == "png"))
						{
							unlink($src);
						}
					}
					
					// fjerne som profilbilde?
					if (page_min_side::$active_player->data['up_profile_image'] == $id)
					{
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_profile_image = NULL, up_profile_image_url = NULL WHERE up_id = ".page_min_side::$active_player->id);
					}
					
					ess::$b->page->add_message("Bildet ble slettet.");
				}
				
				// sette som aktivt bilde
				elseif (isset($_POST['active']) && $id != page_min_side::$active_player->data['up_profile_image'])
				{
					// er brukeren deaktivert?
					if (!page_min_side::$active_player->active && !access::has("mod"))
					{
						ess::$b->page->add_message("Du kan ikke sette nytt profilbilde på en spiller som er død.");
					}
					
					else
					{
						$url = ($image['local'] ? "l:" : "") . $image['address'];
						
						// oppdater profilen
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_profile_image = $id, up_profile_image_url = ".\Kofradia\DB::quote($url)." WHERE up_id = ".page_min_side::$active_player->id);
						ess::$b->page->add_message("Bildet ble satt som aktivt profilbilde.");
					}
				}
				
				redirect::handle();
			}
			
			// fjern aktivt profilbilde
			if (isset($_POST['profile_image_inactive']) && ((page_min_side::$active_own && !$blokkering) || access::has("forum_mod")))
			{
				if (page_min_side::$active_player->data['up_profile_image'])
				{
					\Kofradia\DB::get()->exec("UPDATE users_players SET up_profile_image = NULL, up_profile_image_url = NULL WHERE up_id = ".page_min_side::$active_player->id);
					ess::$b->page->add_message("Du har ikke lengre noe profilbilde.");
				}
				
				redirect::handle();
			}
			
			$profile_images_max = access::has("crewet", NULL, NULL, true) ? 15 : 10;
			
			// laste opp profilbilde
			if (isset($_FILES['profile_image']) && !$blokkering)
			{
				// er brukeren deaktivert?
				if (!page_min_side::$active_player->active && !access::has("mod"))
				{
					ess::$b->page->add_message("Du kan ikke sette nytt profilbilde på en spiller som er død.");
				}
				
				if (TEST_SERVER)
				{
					ess::$b->page->add_message("Du kan ikke laste opp profilbilder på testsiden. Last opp bildet på den vanlige siden.", "error");
					redirect::handle();
				}
				
				// har allerede maks antall bilder?
				$result = \Kofradia\DB::get()->query("SELECT COUNT(*) FROM profile_images WHERE pi_up_id = ".page_min_side::$active_player->id);
				if ($result->fetchColumn(0) >= $profile_images_max)
				{
					ess::$b->page->add_message("Du kan ikke ha flere enn ".$profile_images_max." bilder lastet opp samtidig. Slett et bilde og prøv igjen.", "error");
					redirect::handle();
				}
				
				$src = $_FILES['profile_image']['tmp_name'];
				$org = $_FILES['profile_image']['name'];
				
				// skjekk om det er et gyldig bilde
				if (!is_uploaded_file($src))
				{
					ess::$b->page->add_message("Noe gikk galt. Prøv på nytt.", "error");
					redirect::handle();
				}
				
				// les bildet
				$image = @imagecreatefromstring(@file_get_contents($src));
				if (!$image)
				{
					ess::$b->page->add_message("Kunne ikke lese bildet. Prøv et annet bilde.", "error");
					redirect::handle();
				}
				
				$w = imagesx($image);
				$h = imagesy($image);
				
				// dimensjoner til 120 i bredden og maks 180 i høyden
				$width = 120;
				$max_h = 180;
				$height = floor($width / $w * $h);
				if ($height > $max_h) $height = $max_h;
				elseif ($height < 1) $height = 10;
				
				// opprett nytt bilde
				$new = imagecreatetruecolor($width, $height);
				
				// kopier det andre bildet over hit
				imagecopyresampled($new, $image, 0, 0, 0, 0, $width, $height, $w, $h);
				
				// opprett ny rad i databasen for å finne id
				\Kofradia\DB::get()->exec("INSERT INTO profile_images SET pi_up_id = ".page_min_side::$active_player->id.", local = 1, time = ".time());
				$id = \Kofradia\DB::get()->lastInsertId();
				
				// lagre bildet
				$img_navn = preg_replace("/[^a-zA-Z0-9\\-_\\. ]/u", "", page_min_side::$active_player->data['up_name']);
				if (empty($img_navn)) $img_navn = page_min_side::$active_player->id;
				$img_navn .= ".$id.png";
				
				$filename = PROFILE_IMAGES_FOLDER . "/$img_navn";
				
				imagepng($new, $filename);
				imagedestroy($image);
				imagedestroy($new);
				
				$url = "l:$img_navn";
				
				// oppdater databasen
				\Kofradia\DB::get()->exec("UPDATE profile_images SET address = ".\Kofradia\DB::quote($img_navn)." WHERE id = $id");
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_profile_image = $id, up_profile_image_url = ".\Kofradia\DB::quote($url)." WHERE up_id = ".page_min_side::$active_player->id);
				
				// vis info
				ess::$b->page->add_message("Bildet ble lastet opp og er satt som ditt nye profilbilde.");
				
				// irc announce
				$url = PROFILE_IMAGES_HTTP . "/" . rawurlencode($img_navn);
				putlog("NOTICE", "%bPROFILBILDE:%b ".login::$user->player->data['up_name']." lastet opp $url");
				
				redirect::handle();
			}
			
			// hent profilbildene
			$result = \Kofradia\DB::get()->query("SELECT id, local, address, time FROM profile_images WHERE pi_up_id = ".page_min_side::$active_player->id." ORDER BY time DESC");
			$profile_images = array();
			while ($row = $result->fetch()) $profile_images[] = $row;
			
			// blokkert?
			if ($blokkering && !access::has("mod"))
			{
				echo '
	<p class="error_box">Du er blokkert fra å endre profilbildet ditt. Blokkeringen varer til '.ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).'.<br /><b>Begrunnelse:</b> '.game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>';
			}
			
			echo '
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Laste opp profilbilde<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">';
			
			if (!page_min_side::$active_own && !access::has("mod"))
			{
				echo '
						<p>Du har ikke tilgang til å laste opp bilder for denne spilleren.</p>';
			}
			elseif (!page_min_side::$active_player->active && !access::has("mod"))
			{
				echo '
						<p>Denne spilleren er deaktivert. Du har ikke mulighet til å laste opp nytt bilde.</p>
						<p>Du kan eventuelt prøve og be en <a href="crew">moderator</a> laste opp et bilde for deg.</p>';
			}
			else
			{
				echo '
						<p>Her laster du opp nytt profilbilde.</p>
						<p>Bildet skal helst være 120 piksler bredt og 120 piksler høyt. Bildet blir skalert til 120 piksler i bredden. Maks høyde er 180 piksler.</p>';
				
				if (count($profile_images) > $profile_images_max)
				{
					echo '
						<p>Du kan kun ha '.$profile_images_max.' bilder lastet opp på en gang. Slett et bilde for å kunne laste opp et nytt.</p>';
				}
				
				else
				{
					ess::$b->page->add_js_domready('
	$("upload_file").addEvent("change", function()
	{
		this.form.submit();
		$("upload_path").set("text", this.get("value"));
		$("upload_pre").addClass("hide");
		$("upload_post").removeClass("hide");
	});');
					
					echo '
						<div id="upload_pre">
							<form action="" method="post" enctype="multipart/form-data">
								<p><b>Velg bilde</b></p>
								<p><input type="file" name="profile_image" id="upload_file" /></p>
								<p>'.show_sbutton("Last opp bildet", 'name="upload_btn"').'</p>
							</form>
						</div>
						<div id="upload_post" class="hide">
							<p>Laster opp <span id="upload_path" class="dark"></span>...</p>
						</div>';
				}
			}
			
			echo '
					</div>
				</div>
			</div>
		</div>
		<div class="col_w right">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Mine profilbilder<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">'.(page_min_side::$active_player->data['up_profile_image'] && (page_min_side::$active_own || access::has("forum_mod")) ? '
						<form action="" method="post">
							<p class="c">'.show_sbutton("Sett til standard profilbilde", 'name="profile_image_inactive"').'</p>
						</form>' : '');
			
			if (count($profile_images) == 0)
			{
				echo '
						<p>Du har ikke lastet opp noen bilder enda.</p>';
			}
			
			else
			{
				ess::$b->page->add_css('
.minside_profilbilde {
	text-align: center;
	background-color: #161616;
	padding: 10px 5px;
	line-height: 1.5em;
}
');
				foreach ($profile_images as $row)
				{
					$src = $row['local'] ? PROFILE_IMAGES_HTTP . "/" . $row['address'] : $row['address'];
					echo '
						<form action="" method="post">
							<input type="hidden" name="image_id" value="'.$row['id'].'" />
							<p class="minside_profilbilde">
								<img src="'.htmlspecialchars($src).'" alt="Bilde #'.$row['id'].'" class="profile_image" /><br />
								Lastet opp '.ess::$b->date->get($row['time'])->format().(page_min_side::$active_own || access::has("forum_mod") ? '<br />
								'.(page_min_side::$active_player->data['up_profile_image'] != $row['id'] && (page_min_side::$active_player->active || access::has("forum_mod")) ? show_sbutton("Sett som profilbilde", 'name="active"').' ' : '').show_sbutton("Slett bildet", 'name="delete"') : '').'
							</p>
						</form>';
				}
			}
			
			echo '
					</div>
				</div>
			</div>
		</div>
	</div>';
		}
	}
	
	/**
	 * Deaktivere spilleren som moderator
	 */
	protected static function page_deact_mod()
	{
		ess::$b->page->add_title("Deaktiver spiller");
		
		// er deaktivert?
		if (page_min_side::$active_player->data['up_access_level'] == 0)
		{
			ess::$b->page->add_message("Denne spilleren er allerede deaktivert.");
			redirect::handle(page_min_side::addr(""));
		}
		
		// deaktivere?
		if (isset($_POST['deaktiver']))
		{
			$log = trim(postval("log"));
			$note = trim(postval("note"));
			$send_email = isset($_POST['email']);
			
			// mangler logg?
			if ($log == "")
			{
				ess::$b->page->add_message("Mangler begrunnelse.", "error");
			}
			
			// mangler intern informasjon?
			elseif ($note == "")
			{
				ess::$b->page->add_message("Mangler intern informasjon.", "error");
			}
			
			// ikke normal spiller?
			elseif (page_min_side::$active_player->data['up_access_level'] != 1 && !access::has("sadmin"))
			{
				ess::$b->page->add_message("Crewmedlemmer og spillere med spesielle tilganger kan kun deaktiveres av en Senioradministrator.");
			}
			
			else
			{
				// transaksjon
				\Kofradia\DB::get()->beginTransaction();
				
				// deaktiver spilleren
				if (page_min_side::$active_player->deactivate($log, $note, login::$user->player))
				{
					// legg til crewlogg
					$data = array("note" => $note);
					if ($send_email) $data["email_sent"] = 1;
					crewlog::log("player_deactivate", page_min_side::$active_player->id, $log, $data);
					
					// fullfør transaksjon
					\Kofradia\DB::get()->commit();
					
					// send e-post
					if ($send_email)
					{
						$email = new email();
						$email->text = 'Hei,

Din spiller '.page_min_side::$active_player->data['up_name'].' har blitt deaktivert av Crewet.

Begrunnelse for deaktivering:
'.strip_tags(game::bb_to_html($log)).'

Du kan opprette ny spiller ved å logge inn på din bruker.

--
www.kofradia.no';
						$email->send(page_min_side::$active_user->data['u_email'], "Din spiller ".page_min_side::$active_player->data['up_name']." har blitt deaktivert");
					}
					
					ess::$b->page->add_message("Spilleren ble deaktivert".($send_email ? " og e-post ble sendt til ".page_min_side::$active_user->data['u_email'] : "").".");
				}
				
				else
				{
					// fullfør transaksjon
					\Kofradia\DB::get()->commit();
				}
				
				redirect::handle(page_min_side::addr(""));
			}
		}
		
		echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Deaktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<p>Ved å deaktivere spilleren kan brukeren fremdeles logge inn og opprette en ny spiller på samme bruker. Ønsker du egentlig å <a href="'.htmlspecialchars(page_min_side::addr("deact", "", "user")).'">deaktivere brukeren</a>?</p>
			<p>Hvis e-post blir sendt blir brukeren også informert om muligheten for å logge inn på brukeren sin og opprette ny spiller.</p>
			<form action="" method="post">
				<dl class="dd_right">
					<dt>Begrunnelse for deaktivering<br />(for spiller)</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
					<dt>Intern informasjon (crewlogg)</dt>
					<dd><textarea name="note" id="note" cols="30" rows="5">'.htmlspecialchars(postval("note")).'</textarea></dd>
				</dl>
				<p><input type="checkbox" id="email" name="email"'.($_SERVER['REQUEST_METHOD'] != "POST" || isset($_POST['email']) ? ' checked="checked"' : '').' /><label for="email"> Send e-post med begrunnelse for deaktivering til '.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</label></p>
				<p class="c">'.show_sbutton("Deaktiver spiller", 'name="deaktiver"').'</p>
			</form>
		</div>
	</div>';
	}
	
	/**
	 * Endre informasjon om deaktivering
	 */
	protected static function page_cdeact()
	{
		ess::$b->page->add_title("Endre deaktivering");
		
		// er ikke deaktivert?
		if (page_min_side::$active_player->data['up_access_level'] != 0)
		{
			redirect::handle(page_min_side::addr("deact"));
		}
		
		// ble drept? (ingen begrunnelse eller intern info)
		if (page_min_side::$active_player->data['up_deactivated_dead'] != 0)
		{
			ess::$b->page->add_message("Spilleren ble drept og kan ikke ha begrunnelse for deaktivering tilegnet seg.", "error");
			redirect::handle(page_min_side::addr(""));
		}
		
		// lagre endringer?
		if (isset($_POST['save']))
		{
			$log = trim(postval("log"));
			$note = trim(postval("note"));
			$log_change = $log != page_min_side::$active_player->data['up_deactivated_reason'];
			$note_change = $note != page_min_side::$active_player->data['up_deactivated_note'];
			
			// mangler logg?
			if ($log == "")
			{
				ess::$b->page->add_message("Mangler begrunnelse.", "error");
			}
			
			// mangler intern informasjon?
			elseif ($note == "")
			{
				ess::$b->page->add_message("Mangler intern informasjon.", "error");
			}
			
			// ingen endringer?
			if (!$log_change && !$note_change)
			{
				ess::$b->page->add_message("Du har ikke gjort noen endringer.", "error");
			}
			
			else
			{
				// lagre endringer
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_deactivated_reason = ".\Kofradia\DB::quote($log).", up_deactivated_note = ".\Kofradia\DB::quote($note)." WHERE up_id = ".page_min_side::$active_player->id);
				
				// lagre crewlog
				$data = array("log_old" => page_min_side::$active_player->data['up_deactivated_reason'], "note_old" => page_min_side::$active_player->data['up_deactivated_note']);
				if ($log_change) $data['log_new'] = $log;
				if ($note_change) $data['note_new'] = $note;
				crewlog::log("player_deactivate_change", page_min_side::$active_player->id, NULL, $data);
				
				ess::$b->page->add_message("Endringene ble lagret.");
				redirect::handle(page_min_side::addr(""));
			}
		}
		
		echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Endre informasjon om deaktivering av spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<form action="" method="post">'.(page_min_side::$active_user->active ? '
				<p>Brukeren vil ikke bli informert om disse endringene, annet enn at brukeren får oppgitt den nye begrunnelsen på min side.</p>' : '
				<p>Brukeren er deaktivert, og vil derfor ikke ha mulighet til å motta denne nye begrunnelsen. Ønsker du heller å <a href="'.htmlspecialchars(page_min_side::addr("cdeact", "", "user")).'">endre deaktiveringen til brukeren</a>? Du kan da også velge å oppdatere både deaktiveringen til brukeren og spilleren.</p>').'
				<dl class="dd_right">
					<dt>Begrunnelse for deaktivering<br />(for spiller)</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log", page_min_side::$active_player->data['up_deactivated_reason'])).'</textarea></dd>
					<dt>Intern informasjon (crewlogg)</dt>
					<dd><textarea name="note" id="note" cols="30" rows="5">'.htmlspecialchars(postval("note", page_min_side::$active_player->data['up_deactivated_note'])).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Lagre endringer", 'name="save"').'</p>
			</form>
		</div>
	</div>';
	}
	
	/**
	 * Deaktivere spilleren
	 */
	protected static function page_deact()
	{
		global $__server;
		
		ess::$b->page->add_title("Deaktiver spiller");
		
		// er deaktivert?
		if (page_min_side::$active_player->data['up_access_level'] == 0)
		{
			ess::$b->page->add_message("Denne spilleren er allerede deaktivert.");
			redirect::handle(page_min_side::addr(""));
		}
		
		// blokkert fra å deaktivere spilleren?
		$blokkering = blokkeringer::check(blokkeringer::TYPE_DEAKTIVER);
		if ($blokkering)
		{
			echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du er blokkert fra å deaktivere spilleren din.</p>
			<p>Blokkeringen varer til '.ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).'.</p>
			<p><b>Begrunnelse:</b> '.game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>
		</div>
	</div>';
		}
		
		// spesielle tilganger?
		elseif (page_min_side::$active_player->data['up_access_level'] != 1)
		{
			echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Din spiller har spesielle rettigheter og kan ikke deaktiveres uten videre.</p>
		</div>
	</div>';
		}
		
		else
		{
			// deaktivere seg selv -- status: sjekk om spilleren kan deaktivere seg selv
			$deactivate_expire = page_min_side::$active_player->params->get("deactivate_expire");
			$deactivate_expire_time = 3600;
			
			// må be om e-post?
			if (!$deactivate_expire || $deactivate_expire < time())
			{
				if (isset($_POST['deactivate']))
				{
					// opprett nøkkel
					$key = uniqid();
					$expire = time()+$deactivate_expire_time;
					
					page_min_side::$active_player->params->update("deactivate_expire", $expire);
					page_min_side::$active_player->params->update("deactivate_key", $key);
					page_min_side::$active_player->params->update("deactivate_time", time(), true);
					
					// opprett e-post
					$email = new email();
					$email->text = 'Hei,

Du har bedt om å deaktivere din spiller '.page_min_side::$active_player->data['up_name'].' på Kofradia.
For din egen skyld sender vi deg denne e-posten for å være sikker på at ingen uvedkommende forsøker å deaktivere spilleren din.

Brukerinformasjon:
Bruker ID: '.page_min_side::$active_user->id.'
E-post: '.page_min_side::$active_user->data['u_email'].'
Spiller: '.page_min_side::$active_player->data['up_name'].' (#'.page_min_side::$active_player->id.')

For å godta eller avslå deaktivering:
'.$__server['path'].'/min_side?up_id='.page_min_side::$active_player->id.'&a=deact&key='.urlencode($key).'

--
www.kofradia.no';
					$email->send(page_min_side::$active_user->data['u_email'], "Deaktiver spiller");
					
					putlog("CREWCHAN", "%bDeaktiveringsmulighet%b: ".page_min_side::$active_player->data['up_name']." (".page_min_side::$active_user->data['u_email'].") ba om e-post for å deaktivere spilleren -- {$__server['path']}/min_side?up_id=".page_min_side::$active_player->id);
					ess::$b->page->add_message("E-post med detaljer ble sendt til <b>".htmlspecialchars(page_min_side::$active_user->data['u_email'])."</b>.");
					
					redirect::handle();
				}
				
				if (($deactivate_expire && $deactivate_expire < time()) || isset($_GET['key']))
				{
					if (isset($_GET['key']))
					{
						ess::$b->page->add_message("Du brukte for lang tid fra e-posten ble sendt. Alternativt er du logget inn på feil bruker.", "error");
					}
					else
					{
						ess::$b->page->add_message("Du brukte for lang tid fra e-posten ble sendt om å deaktivere spilleren din. Alternativt er du logget inn på feil bruker.", "error");
					}
					
					if ($deactivate_expire && $deactivate_expire < time())
					{
						// fjern oppføringene
						page_min_side::$active_player->params->remove("deactivate_expire");
						page_min_side::$active_player->params->remove("deactivate_key");
						page_min_side::$active_player->params->remove("deactivate_time", true);
					}
					
					redirect::handle();
				}
				
				$deactivate_expire = false;
			}
			
			else
			{
				// ikke normal spiller
				if (page_min_side::$active_player->data['up_access_level'] != 1 && false)
				{
					// fjern oppføringene
					page_min_side::$active_player->params->remove("deactivate_expire");
					page_min_side::$active_player->params->remove("deactivate_key");
					page_min_side::$active_player->params->remove("deactivate_time", true);
					
					redirect::handle();
				}
				
				// avbryte?
				if (isset($_GET['abort']))
				{
					ess::$b->page->add_message("Du har trukket tilbake ditt ønske om deaktivering.", "error");
					
					// fjern oppføringene
					page_min_side::$active_player->params->remove("deactivate_expire");
					page_min_side::$active_player->params->remove("deactivate_key");
					page_min_side::$active_player->params->remove("deactivate_time", true);
					
					redirect::handle();
				}
				
				// kode fra e-post?
				if (isset($_GET['key']))
				{
					// kontroller kode
					$key = getval("key");
					if ($key != page_min_side::$active_player->params->get("deactivate_key"))
					{
						ess::$b->page->add_message("Lenken er feil. Sørg for at du kopierer hele lenken.", "error");
						redirect::handle();
					}
					
					// bekreftet?
					elseif (isset($_POST['pass']))
					{
						// kontroller note og passord
						$pass = postval("pass");
						$note = trim(postval("note"));
						
						if ($note == "")
						{
							ess::$b->page->add_message("Mangler begrunnelse.", "error");
						}
						
						elseif ($pass == "")
						{
							ess::$b->page->add_message("Du må fylle inn passordet ditt.", "error");
						}
						
						elseif (!password::verify_hash($pass, page_min_side::$active_user->data['u_pass'], 'user'))
						{
							ess::$b->page->add_message("Passordet stemte ikke.", "error");
						}
						
						else
						{
							// deaktiver spiller
							$player_deact = page_min_side::$active_player->active;
							if (page_min_side::$active_player->deactivate($note, NULL, page_min_side::$active_player))
							{
								ess::$b->page->add_message("Spilleren er nå deaktivert.");
								
								// send e-post
								$email = new email();
								$email->text = 'Hei,

Du har deaktivert din spiller '.page_min_side::$active_player->data['up_name'].'.

Din begrunnelse for deaktivering:
'.game::bb_to_html($note).'

Du kan fremdeles logge inn på din bruker og opprette en ny spiller.

--
www.kofradia.no';
								$email->send(page_min_side::$active_user->data['u_email'], "Din spiller ".page_min_side::$active_player->data['up_name']." har blitt deaktivert");
								
								redirect::handle("lock?a=player");
							}
						}
					}
				}
			}
			
			// venter på kode
			if ($deactivate_expire !== false)
			{
				// har kode?
				if (isset($_GET['key']))
				{
					echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<div class="warning">
				<p>Du er i ferd med å deaktivere spilleren din. Når spilleren din blir deaktivert vil du fremdeles være logget inn med din bruker og kan opprette en ny spiller.</p>
				<p>Hvis du ønsker å fjerne din bruker fra spillet må du <a href="'.htmlspecialchars(page_min_side::addr("deact", "", "user")).'">deaktivere brukeren din</a>.</p>
			</div>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Passord</dt>
					<dd><input type="password" name="pass" class="styled w100" /></dd>
					<dt>Begrunnelse</dt>
					<dd><textarea name="note" cols="30" rows="5">'.htmlspecialchars(postval("note")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Deaktiver spiller").'</p>
				<p class="c"><a href="'.htmlspecialchars(page_min_side::addr(NULL, "abort")).'">Avbryt - ønsker ikke å deaktivere spilleren</a></p>
			</form>
		</div>
	</div>';
				}
				
				else
				{
					echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du skal ha mottatt en e-post med link til å deaktivere din spiller.</p>
			<p><a href="'.htmlspecialchars(page_min_side::addr(NULL, "abort")).'">Avbryt - ønsker ikke å deaktivere spilleren</a></p>
		</div>
	</div>';
				}
			}
			
			else
			{
				echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Ved å deaktivere spilleren din vil du fremdeles være logget inn med din bruker og kan opprette en ny spiller.</p>
			<p>Du mister muligheten til å benytte denne spilleren, og vil ikke kunne aktivere den igjen.</p>
			<p>Hvis du ønsker å fjerne din bruker fra spillet må du <a href="'.htmlspecialchars(page_min_side::addr("deact", "", "user")).'">deaktivere brukeren din</a>.</p>
			<p>Av sikkerhetsmessige grunner vil du motta en e-post med nærmere instrukser for å deaktivere spilleren.</p>
			<form action="" method="post">
				<p class="c">'.show_sbutton("Be om e-post", 'name="deactivate"').'</p>
			</form>
		</div>
	</div>';
			}
		}
	}
	
	/**
	 * Aktiver spiller
	 */
	protected static function page_activate()
	{
		global $__server;
		
		ess::$b->page->add_title("Aktiver spiller");
		
		// er ikke deaktivert?
		if (page_min_side::$active_player->data['up_access_level'] != 0)
		{
			ess::$b->page->add_message("Denne spilleren er ikke deaktivert.");
			redirect::handle(page_min_side::addr(""));
		}
		
		// aktivere?
		if (isset($_POST['aktiver']))
		{
			$log = trim(postval("log"));
			$note = trim(postval("note"));
			$send_email = isset($_POST['email']);
			
			// mangler logg?
			if ($log == "")
			{
				ess::$b->page->add_message("Mangler begrunnelse.", "error");
			}
			
			else
			{
				// aktiver spilleren
				if (page_min_side::$active_player->activate())
				{
					// legg til crewlogg
					if ($send_email) $data = array("email_sent" => 1, "email_note" => $note);
					else $data = array();
					crewlog::log("player_activate", page_min_side::$active_player->id, $log, $data);
					
					// aktivere spilleren?
					$user_activate = !page_min_side::$active_user->active;
					if ($user_activate)
					{
						if (page_min_side::$active_user->activate())
						{
							// legg til crewlogg
							if ($send_email) $data = array("email_sent" => 1, "email_note" => $note);
							else $data = array();
							crewlog::log("user_activate", page_min_side::$active_player->id, $log, $data);
						}
					}
					
					// send e-post
					if ($send_email)
					{
						$email = new email();
						$email->text = 'Hei,

Din'.($user_activate ? ' bruker og' : '').' spiller '.page_min_side::$active_player->data['up_name'].' har blitt aktivert igjen av Crewet.

Du får tilgang til din spiller ved å logge inn på Kofradia:
'.$__server['path'].'/'.(!empty($note) ? '

Begrunnelse for aktivering:
'.$note : '').'

--
www.kofradia.no';
						$email->send(page_min_side::$active_user->data['u_email'], "Din".($user_activate ? ' bruker og' : '')." spiller ".page_min_side::$active_player->data['up_name']." har blitt aktivert igjen");
					}
					
					ess::$b->page->add_message(($user_activate ? "Brukeren og spilleren" : "Spilleren")." ble aktivert igjen".($send_email ? " og e-post ble sendt til ".page_min_side::$active_user->data['u_email'].(empty($note) ? " uten begrunnelse" : " med begrunnelse")."." : ". Brukeren har ikke blitt informert om dette."));
				}
				
				redirect::handle(page_min_side::addr(""));
			}
		}
		
		ess::$b->page->add_js_domready('
	$("email").addEvent("change", function()
	{
		$("note").set("disabled", !this.get("checked"));
		if (this.get("checked")) $("email-info").removeClass("email-info-dis");
		else $("email-info").addClass("email-info-dis");
	});');
		
		ess::$b->page->add_css('.email-info-dis { color: #555 }');
		echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Aktiver spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du er i ferd med å aktivere denne spilleren.'.(page_min_side::$active_user->active ? '' : ' Dette vil også aktivere brukeren til spilleren. Du kan også velge å kun <a href="'.htmlspecialchars(page_min_side::addr("activate", "", "user")).'">aktivere brukeren</a> slik at brukeren kan opprette ny spiller på egenhånd.').'</p>
			<form action="" method="post">
				<dl class="dd_right">
					<dt>Begrunnelse for aktivering<br />(internt for crewet)</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p>
					<input type="checkbox" id="email" name="email"'.($_SERVER['REQUEST_METHOD'] != "POST" || isset($_POST['email']) ? ' checked="checked"' : '').' />
					<label for="email"> Send e-post til '.htmlspecialchars(page_min_side::$active_user->data['u_email']).' for å informere om at brukeren er aktivert igjen</label>
				</p>
				<dl class="dd_right">
					<dt id="email-info"'.($_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['email']) ? ' class="email-info-dis"' : '').'>Tilleggsinformasjon til spilleren<br />(ikke BB-kode)<br /><br />(Blir oppgitt som begrunnelse<br />for aktivering hvis fylt ut)</dt>
					<dd><textarea name="note" id="note" cols="30" rows="5"'.($_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['email']) ? ' disabled="disabled"' : '').'>'.htmlspecialchars(postval("note")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Aktiver igjen", 'name="aktiver"').'</p>
			</form>
		</div>
	</div>';
	}
	
	/**
	 * Crewside
	 */
	protected static function page_crew()
	{
		if (!isset(login::$extended_access['authed']))
		{
			echo '
	<p class="c">Du må logge inn for utvidede tilganger.</p>';
		}
		
		else
		{
			$subpage2 = getval("b");
			redirect::store(page_min_side::addr(NULL, ($subpage2 != "" ? "b=" . $subpage2 : '')));
			ess::$b->page->add_title("Crew");
			ess::$b->page->add_css('
.minside_links .active { color: #CCFF00 }');
			
			$links = array();
			$links[] = '<a href="'.htmlspecialchars(page_min_side::addr("crew", "", "user")).'">Min bruker</a>';
			$links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "")).'"'.($subpage2 == "" ? ' class="active"' : '').'>Oversikt</a>';
			if (access::has("seniormod")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=rank")).'"'.($subpage2 == "rank" ? ' class="active"' : '').'>Juster rank</a>';
			if (access::has("mod")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=name")).'"'.($subpage2 == "name" ? ' class="active"' : '').'>Endre spillernavn</a>';
			
			echo '
	<p class="c minside_links">'.implode(" | ", $links).'</p>';
			
			if ($subpage2 == "")
			{
				echo '
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Oversikt<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">'.(access::has("mod") ? '
						<ul>'.(access::has("admin") && KOFRADIA_DEBUG ? '
							<li><a href="innboks?u_id='.page_min_side::$active_user->id.'">Vis innboksen</a></li>
							<li><a href="innboks_sok?u_id='.page_min_side::$active_user->id.'">Søk i innboksen</a></li>' : '').'
							<li><a href="poker?up_id='.page_min_side::$active_player->id.'&amp;stats">Vis pokerhistorien</a></li>
							<li><a href="admin/brukere/bankoverforinger?u1='.page_min_side::$active_player->id.'">Vis bankoverføringer</a></li>
							<li><a href="drap?up_id='.page_min_side::$active_player->id.'">Vis angrep utført av spilleren</a></li>
							<li><a href="drap?offer_up_id='.page_min_side::$active_player->id.'">Vis angrep utført mot spilleren</a></li>
						</ul>' : '').'
						<p>Trykk på <a href="'.htmlspecialchars(page_min_side::addr(NULL, "", "user")).'">min bruker</a> for å vise informasjon om brukeren.</p>
					</div>
				</div>
			</div>
		</div>
		<div class="col_w right">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Crewnotat for brukeren<span class="left2"></span><span class="right2"></span></h1>
					<p class="h_right"><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=enote", "user")).'">rediger</a></p>
					<div class="bg1">
						<p>Her kan hvem som helst i crewet legge til eller endre et notat for denne brukeren for å memorere ting som har med <u>brukeren</u> å gjøre.</p>'.(empty(page_min_side::$active_user->data['u_note_crew']) ? '
						<p>Ingen notat er registrert.</p>' : '
						<div class="p">'.game::bb_to_html(page_min_side::$active_user->data['u_note_crew']).'</div>').'
					</div>
				</div>
				<div class="bg1_c">
					<h1 class="bg1">Crewnotat for spilleren<span class="left2"></span><span class="right2"></span></h1>
					<p class="h_right"><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=enote")).'">rediger</a></p>
					<div class="bg1">
						<p>Her kan hvem som helst i crewet legge til eller endre et notat for denne spilleren for å memorere ting som har med <u>spilleren</u> å gjøre.</p>'.(empty(page_min_side::$active_player->data['up_note_crew']) ? '
						<p>Ingen notat er registrert.</p>' : '
						<div class="p">'.game::bb_to_html(page_min_side::$active_player->data['up_note_crew']).'</div>').'
					</div>
				</div>
			</div>
		</div>
	</div>
	<p class="c">Loggoppføringer for denne spilleren - <a href="'.htmlspecialchars(page_min_side::addr(NULL, "", "user")).'">se komplett logg for brukeren</a></p>';
				
				// hent loggene for denne spilleren
				$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
				$result = $pagei->query("SELECT lc_id, lc_up_id, lc_time, lc_lca_id, lc_a_up_id, lc_log FROM log_crew WHERE lc_a_up_id = ".page_min_side::$active_player->id." ORDER BY lc_time DESC");
				
				// ingen handlinger?
				if ($result->rowCount() == 0)
				{
					echo '
	<p class="c">Ingen oppføringer eksisterer.</p>';
				}
				
				else
				{
					$rows = array();
					while ($row = $result->fetch()) $rows[$row['lc_id']] = $row;
					$data = crewlog::load_summary_data($rows);
					
					$logs = array();
					foreach ($data as $row)
					{
						// hent sammendrag
						$summary = crewlog::make_summary($row, NULL, $row['lc_a_up_id'] != page_min_side::$active_player->id);
						$day = ess::$b->date->get($row['lc_time'])->format(date::FORMAT_NOTIME);
						
						$logs[$day][] = '<p><span class="time">'.ess::$b->date->get($row['lc_time'])->format("H:i").':</span> '.$summary.'</p>';
					}
					
					ess::$b->page->add_css('.crewlog .time { color: #888888; padding-right: 5px }');
					
					foreach ($logs as $day => $items)
					{
						echo '
	<div class="bg1_c">
		<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1 crewlog">
			'.implode('
			', $items).'
		</div>
	</div>';
					}
					
					echo '
	<p class="c">'.$pagei->pagenumbers().'</p>';
				}
			}
			
			elseif ($subpage2 == "enote")
			{
				ess::$b->page->add_title("Endre notat");
				
				// lagre endringer?
				if (isset($_POST['notat']))
				{
					$notat = postval("notat");
					if ($notat == page_min_side::$active_player->data['up_note_crew'])
					{
						ess::$b->page->add_message("Ingen endringer ble utført.", "error");
					}
					
					else
					{
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_note_crew = ".\Kofradia\DB::quote($notat)." WHERE up_id = ".page_min_side::$active_player->id);
						
						// legg til crewlogg
						crewlog::log("player_note_crew", page_min_side::$active_player->id, NULL, array(
							"note_old" => page_min_side::$active_player->data['up_note_crew'],
							"note_diff" => diff::make(page_min_side::$active_player->data['up_note_crew'], $notat))
						);
						
						page_min_side::$active_player->data['up_note_crew'] = $notat;
						
						ess::$b->page->add_message("Notet ble endret.");
						redirect::handle();
					}
				}
				
				echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Endre crewnotat for spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<form action="" method="post">
				<p>Dette endrer notatet som er tilknyttet denne spilleren. Du kan også tilknytte <a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=enote", "user")).'">informasjon til brukeren</a>, eller til en annen av brukerens spillere hvis det heller er ønskelig.</p>
				<p>Notat:</p>
				<p><textarea name="notat" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(page_min_side::$active_player->data['up_note_crew']).'</textarea></p>
				<p class="c">'.show_sbutton("Lagre").'</p>
			</form>
		</div>
	</div>';
			}
			
			// juster ranken til spilleren
			elseif ($subpage2 == "rank" && access::has("seniormod"))
			{
				// endre?
				if (isset($_POST['rel']) || isset($_POST['abs']))
				{
					$log = trim(postval("log"));
					$rel = 0;
					
					// mangler begrunnelse?
					if ($log == "")
					{
						ess::$b->page->add_message("Mangler begrunnelse.", "error");
					}
					
					// bestem rankpoeng
					elseif (isset($_POST['abs']))
					{
						$points = game::intval(postval("points_abs"));
						
						// samme?
						if ($points == page_min_side::$active_player->data['up_points'])
						{
							ess::$b->page->add_message("Ingen endringer ble utført.", "error");
						}
						
						// negativt?
						elseif ($points < 0)
						{
							ess::$b->page->add_message("Kan ikke sette til negativt tall.", "error");
						}
						
						// for høyt?
						elseif ($points > 9999999)
						{
							ess::$b->page->add_message("Kan ikke settes til så høyt tall.", "error");
						}
						
						else
						{
							$rel = $points - page_min_side::$active_player->data['up_points'];
						}
					}
					
					// juster rankpoeng
					elseif (isset($_POST['rel']))
					{
						$points = game::intval(postval("points_rel"));
						
						// ingen endring?
						if ($points == 0)
						{
							ess::$b->page->add_message("Ingen endringer ble utført.", "error");
						}
						
						// resulterer i negativ rank?
						elseif (page_min_side::$active_player->data['up_points'] + $points < 0)
						{
							ess::$b->page->add_message("Kan ikke utføre handlingen. Vil føre til <b>for lav</b> verdi.", "error");
						}
						
						// resulterer i for høy rank?
						elseif (page_min_side::$active_player->data['up_points'] + $points > 9999999)
						{
							ess::$b->page->add_message("Kan ikke utføre handlingen. Vil føre til <b>for høy</b> verdi.", "error");
						}
						
						else
						{
							$rel = $points;
						}
					}
					
					// skal ikke dette annonseres?
					$silent = isset($_POST['silent']);
					
					// øke ranken?
					if ($rel > 0)
					{
						page_min_side::$active_player->increase_rank($rel, false, $silent, 0);
						
						// legg til crewlogg
						crewlog::log("player_rank_inc", page_min_side::$active_player->id, $log, array("points" => $rel));
						
						ess::$b->page->add_message("Endringene ble lagret. Du økte ranken med ".game::format_number($rel)." poeng.".($silent ? ' Informasjonen ble ikke annonsert.' : ''));
						redirect::handle();
					}
					
					// senke ranken?
					elseif ($rel < 0)
					{
						page_min_side::$active_player->increase_rank($rel, false, $silent, 0);
						$rel = abs($rel);
						
						// legg til crewlogg
						crewlog::log("player_rank_dec", page_min_side::$active_player->id, $log, array("points" => $rel));
						
						ess::$b->page->add_message("Endringene ble lagret. Du senket ranken med ".game::format_number($rel)." poeng.".($silent ? ' Informasjonen ble ikke annonsert.' : ''));
						redirect::handle();
					}
				}
				
				ess::$b->page->add_title("Juster rank");
				
				echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Juster rank<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Antall rankpoeng: <b>'.game::format_number(page_min_side::$active_player->data['up_points']).'</b></p>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Bestem rankpoeng (absolutt verdi)</dt>
					<dd><input type="text" name="points_abs" value="'.game::format_number(postval("points_abs", page_min_side::$active_player->data['up_points'])).'" class="styled w60" maxlength="10" /> '.show_sbutton("Lagre", 'name="abs"').'</dd>
					<dt>Juster ranken (relativ verdi)</dt>
					<dd><input type="text" name="points_rel" value="'.game::format_number(postval("points_rel", 0)).'" class="styled w60" maxlength="10" /> '.show_sbutton("Lagre", 'name="rel"').'</dd>
					<dd><input type="checkbox" name="silent"'.(isset($_POST['silent']) ? ' checked="checked"' : '').' id="silent" /><label for="silent"> Ikke annonser denne endringen (f.eks. på IRC)</label></dd>
					<dt>Begrunnelse for endring (crewlogg)</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
			</form>
		</div>
	</div>';
			}
			
			// endre spillernavn
			elseif ($subpage2 == "name" && access::has("mod"))
			{
				// lagre nytt spillernavn?
				if (isset($_POST['name']))
				{
					$name = trim(postval("name"));
					$log = trim(postval("log"));
					
					// ingen endringer utført?
					if (strcmp(page_min_side::$active_player->data['up_name'], $name) === 0)
					{
						ess::$b->page->add_message("Spillernavnet er det samme som før.", "error");
					}
					
					// mangler begrunnelse?
					elseif ($log == "")
					{
						ess::$b->page->add_message("Mangler begrunnelse.", "error");
					}
					
					else
					{
						// kontroller spillernavnet (kun hvis endringer utover små/store bokstaver er gjort)
						$check = strcasecmp(page_min_side::$active_player->data['up_name'], $name) !== 0;
						if ($check) $result = \Kofradia\DB::get()->query("SELECT ".\Kofradia\DB::quoteNoNull($name)." REGEXP regex AS m, error FROM regex_checks WHERE type = 'reg_user_strength' HAVING m = 1");
						if ($check && $result->rowCount() > 0)
						{
							// sett opp feilmeldingene
							$feil = array();
							while ($row = $result->fetch())
							{
								$feil[] = '<li>'.htmlspecialchars($row['error']).'</li>';
							}
							
							// legg til feilmeldingene
							ess::$b->page->add_message("<p>Spillernavnet var ikke gyldig:</p><ul>".implode("", $feil)."</ul>", "error");
						}
						
						else
						{
							// sjekk at spillernavnet ikke finnes fra før
							$result = \Kofradia\DB::get()->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE up_name = ".\Kofradia\DB::quote($name)." AND up_id != ".page_min_side::$active_player->id." AND (up_u_id != ".page_min_side::$active_user->id." OR up_access_level != 0)");
							if ($result->rowCount() > 0)
							{
								$row = $result->fetch();
								ess::$b->page->add_message("Spillernavnet er allerede i bruk: ".game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']), "error");
							}
							
							else
							{
								// utfør endringer - endre spillernavnet
								\Kofradia\DB::get()->exec("UPDATE users_players SET up_name = ".\Kofradia\DB::quote($name)." WHERE up_id = ".page_min_side::$active_player->id);
								
								// legg til crewlogg
								crewlog::log("player_name", page_min_side::$active_player->id, $log, array(
									"user_old" => page_min_side::$active_player->data['up_name'],
									"user_new" => $name));
								
								ess::$b->page->add_message("Spillernavnet ble endret fra ".htmlspecialchars(page_min_side::$active_player->data['up_name'])." til ".game::profile_link(page_min_side::$active_player->id, $name, page_min_side::$active_player->data['up_access_level']).'.');
								redirect::handle();
							}
						}
					}
				}
				
				ess::$b->page->add_title("Endre spillernavn");
				
				echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Endre spillernavn<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Ikke utfør andre endringer enn store/små bokstaver i spillernavnet dersom det ikke er veldig nødvendig. Dette på grunn av BB-koder som [user=..] ikke lenger vil fungere.</p>
			<p>Det er mulig å gi en spiller samme navn som en annen spiller, så lenge spillerene tilhører samme bruker.</p>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Nåværende spillernavn</dt>
					<dd>'.htmlspecialchars(page_min_side::$active_player->data['up_name']).'</dd>
					<dt>Nytt spillernavn</dt>
					<dd><input type="text" value="'.htmlspecialchars(postval("name", page_min_side::$active_player->data['up_name'])).'" name="name" class="styled w120" /></dd>
					<dt>Begrunnelse for endring (crewlogg)</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Endre spillernavnet").'</p>
			</form>
		</div>
	</div>';
			}
		}
	}
	
	/**
	 * Felt for advarsel
	 */
	protected static function advarsel_input($kategori = null)
	{
		$active = !empty($_POST['a_active']);
		$types = crewlog::$user_warning_types;
		$types_name = crewlog::$user_warning_types_name;
		
		ess::$b->page->add_js_domready('
	$("advarsel_inactive").getElement("a").addEvent("click", function(event)
	{
		$("advarsel_inactive").addClass("hide");
		$("advarsel_active").removeClass("hide");
		$("a_active").set("value", "1");
		event.stop();
	});
	$("advarsel_active").getElement("a").addEvent("click", function(event)
	{
		$("advarsel_inactive").removeClass("hide");
		$("advarsel_active").addClass("hide");
		$("a_active").set("value", "0");
		event.stop();
	});');
		
		$html = '
<div id="advarsel_inactive"'.($active ? ' class="hide"' : '').'>
	<p class="c"><a href="#">Legg til advarsel sammen med endringen</a></p>
</div>
<div id="advarsel_active"'.(!$active ? ' class="hide"' : '').'>
	<p class="c"><a href="#">Ikke legg til advarsel</a></p>
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Gi advarsel til brukeren<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<form action="" method="post">
				<input type="hidden" name="a_active" id="a_active" value="'.($active ? 1 : 0).'" />
				<dl class="dd_right">';
		
		if ($kategori)
		{
			if (!isset($types_name[$kategori])) throw new HSException("Ugyldig kategori.");
			
			$html .= '
					<dt>Kategori</dt>
					<dd>'.htmlspecialchars($types[$types_name[$kategori]]).'</dd>';
		}
		
		else
		{
			$html .= '
					<dt>Kategori</dt>
					<dd>
						<select name="a_type">';
			
			$type = isset($_POST['a_type']) && isset($types[$_POST['a_type']]) ? intval($_POST['a_type']) : false;
			if ($type === false) $html .= '
							<option value="">Velg ..</option>';
			
			foreach ($types as $key => $row)
			{
				$html .= '
							<option value="'.$key.'"'.($key === $type ? ' selected="selected"' : '').'>'.htmlspecialchars($row).'</option>';
			}
			
			$html .= '
						</select>
					</dd>';
		}
		
		$html .= '
					<dt>Alvorlighet/prioritet</dt>
					<dd>
						<select name="a_priority">';
		
		$priority = isset($_POST['a_priority']) && is_numeric($_POST['a_priority']) && $_POST['a_priority'] >= 1 && $_POST['a_priority'] <= 3 ? $_POST['a_priority'] : 2;
		$html .= '
							<option value="1"'.($priority == 1 ? ' selected="selected"' : '').'>Lav</option>
							<option value="2"'.($priority == 2 ? ' selected="selected"' : '').'>Moderat</option>
							<option value="3"'.($priority == 3 ? ' selected="selected"' : '').'>Høy</option>
						</select>
					</dd>
				</dl>
				<p>Begrunnelse for advarsel</p>
				<p><textarea name="a_log" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(postval("a_log")).'</textarea></p>
				<p><input type="checkbox" name="a_notify"'.($_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['a_notify']) ? '' : ' checked="checked"').' id="warning_notify" /><label for="warning_notify"> Gi brukeren informasjon om denne advarselen. Kun kategori og begrunnelse vil bli oppgitt til brukeren som en logg i hendelser.</label></p>
			</form>
		</div>
	</div>';
		
		// analyser advarsler
		$lca_id = crewlog::$actions['user_warning'][0];
		
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 15);
		$result = $pagei->query("
			SELECT lc_id, lc_up_id, lc_time, lc_log, lcd_data_int
			FROM log_crew
				JOIN users_players ON lc_a_up_id = up_id AND up_u_id = ".page_min_side::$active_user->id."
				LEFT JOIN log_crew_data ON lcd_lc_id = lc_id AND lcd_lce_id = 5
			WHERE lc_lca_id = $lca_id AND (lcd_data_int IS NULL OR lcd_data_int = 0)
			ORDER BY lc_time DESC");
		
		$data = array();
		while ($row = $result->fetch())
		{
			$data[$row['lc_id']] = $row;
		}
		
		// sett opp data
		$data = crewlog::load_summary_data($data);
		
		$html .= '
	<div class="bg1_c '.(count($data) == 0 ? 'xsmall' : 'xmedium').'">
		<h1 class="bg1">Tidligere advarsler<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">';
		
		if (count($data) == 0)
		{
			$html .= '
			<p>Brukeren har ingen tidligere advarsler.</p>';
		}
		
		else
		{
			ess::$b->page->add_css('
.advarsel { border: 1px solid #292929; margin: 10px 0; padding: 0 10px }');
			
			foreach ($data as $row)
			{
				$priority = $row['data']['priority'] == 1 ? "lav" : ($row['data']['priority'] == 2 ? "moderat" : "høy");
				
				$html .= '
			<div class="advarsel">
				<p><b>'.ess::$b->date->get($row['lc_time'])->format().'</b>: '.$row['data']['type'].' (alvorlighet: <b>'.$priority.'</b>):</p>
				<ul>
					<li>'.game::format_data($row['lc_log']).'</li>
					<li>Internt notat: '.game::format_data($row['data']['note']).'</li>
				</ul>
				<p>'.(empty($row['data']['notified']) ? 'Ble IKKE varslet.' : 'Ble varslet.').' Av <user id="'.$row['lc_up_id'].'" /></p>
			</div>';
			}
			
			// TODO: AJAX på sidevalg
			
			$html .= '
			<p class="c">'.$pagei->pagenumbers().'</p>';
			
			if ($pagei->pages > 1)
			{
				$html .= '
			<p class="c dark">(Sidene åpner i samme vindu, så pass på hvis du har fylt inn feltene ovenfor.)</p>';
			}
		}
		
		$html .= '
		</div>
	</div>
</div>';
		
		return $html;
	}
	
	/**
	 * Sjekk om advarsel er fylt ut
	 */
	protected static function advarsel_handle($kategori = null, $note = null)
	{
		// ingen advarsel
		if (empty($_POST['a_active'])) return true;
		
		$types = crewlog::$user_warning_types;
		$types_name = crewlog::$user_warning_types_name;
		
		if ($kategori)
		{
			if (!isset($types_name[$kategori])) throw new HSException("Ugyldig kategori.");
			$type = $types_name[$kategori];
		}
		else
		{
			$type = postval("a_type");
			if (!isset($types[$type]))
			{
				ess::$b->page->add_message("Ugyldig kategori for advarsel.", "error");
				return false;
			}
		}
		
		$log = trim(postval("a_log"));
		$priority = (int) postval("a_priority");
		$notify = isset($_POST['a_notify']);
		
		if (empty($log))
		{
			ess::$b->page->add_message("Begrunnelse for advarsel må fylles ut.", "error");
			return false;
		}
		
		if ($priority < 1 || $priority > 3)
		{
			ess::$b->page->add_message("Ugylig alvorlighet for advarsel.", "error");
			return false;
		}
		
		$data = array(
			"type" => $types[$type],
			"note" => $note,
			"priority" => $priority
		);
		
		// legge til spillerlogg?
		if ($notify)
		{
			$data['notified'] = 1;
			$data['notified_id'] = player::add_log_static(gamelog::$items['advarsel'], urlencode($types[$type]).':'.urlencode($log), NULL, page_min_side::$active_player->id);
			ess::$b->page->add_message("Advarselen ble lagret. Brukeren ble informert.");
		}
		
		else
		{
			ess::$b->page->add_message("Advarselen ble lagret. Du har ikke informert brukeren om denne advarselen.");
		}
		
		// legg til advarselen
		crewlog::log("user_warning", page_min_side::$active_player->id, $log, $data);
		
		return true;
	}
}