<?php

class kf_menu
{
	public static $page_id = array();
	public static $data = array();
	
	public static function page_id($page_id)
	{
		self::$page_id[$page_id] = true;
	}
	public static function has_page_id($page_id)
	{
		return isset(self::$page_id[$page_id]);
	}
	
	public static function build_menu()
	{
		$ret = '';
		$lock = defined("LOCK") && LOCK;
		
		$bydeler = '
				<li class="bydeler_alt"><a href="'.ess::$s['relative_path'].'/bydeler#?b" class="menu-icon menu-bydel">Bydeler<span class="icon"></span></a></li>
				<li class="bydeler_filter"><a href="'.ess::$s['relative_path'].'/bydeler" class="menu-icon menu-ff" id="f_">Broderskap og firma<span class="icon"></span></a>';
		if (isset(self::$data['bydeler_menu']))
		{
			$bydeler .= '
				<ul>
					<li><a href="#" id="f_familie" class="bydeler_vis_familie">Broderskap</a></li>
					<li>
						<a href="#" id="f_firma" class="bydeler_vis_firma">Firmaer</a>
						<ul>
							<li><a href="#" id="f_avisfirma" class="bydeler_vis_avisfirma">Avisfirmaer</a></li>
							<li><a href="#" id="f_bankfirma" class="bydeler_vis_bankfirma">Bankfirmaer</a></li>
							<li><a href="#" id="f_bomberomfirma" class="bydeler_vis_bomberomfirma">Bomberom</a></li>
							<li><a href="#" id="f_garasjeutleiefirma" class="bydeler_vis_garasjeutleiefirma">Utleiefirma</a></li>
							<li><a href="#" id="f_sykehusfirma" class="bydeler_vis_sykehusfirma">Sykehus</a></li>
							<li><a href="#" id="f_vapbesfirma" class="bydeler_vis_vapbesfirma" title="Våpen, kuler og beskyttelse">Våpen/besk.</a></li>
						</ul>
					</li>
				</ul>';
		}
		$bydeler .= '</li>';
		
		$min_side = '
				<li><a href="'.ess::$s['relative_path'].'/min_side" class="menu-icon menu-minside">Min side<span class="icon"></span></a></li>';
		
		if (!$lock && login::$logged_in)
		{
			$poker_active = cache::fetch("poker_active", 0);
			$auksjoner_active = game::auksjoner_active_count();
			$fengsel_count = game::fengsel_count();
			
			$ret .= '
			<ul>
				<li><a href="'.ess::$s['relative_path'].'/kriminalitet" class="menu-icon menu-krim">Kriminalitet<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/utpressing" class="menu-icon menu-utpr">Utpressing<span class="icon"></span></a>'.(isset(self::$data['utpressing']) ? '
					<ul>
						<li><a href="'.ess::$s['relative_path'].'/utpressing?log">Siste utpressinger</a></li>
					</ul>' : '').'</li>
				<li><a href="'.ess::$s['relative_path'].'/gta" class="menu-icon menu-bilt">Biltyveri<span class="icon"></span></a>'.(defined("SHOW_GTA_MENU") ? '
					<ul>
						<li><a href="'.ess::$s['relative_path'].'/gta/garasje">Garasje</a></li>
						<li><a href="'.ess::$s['relative_path'].'/gta/stats">Statistikk</a></li>
					</ul>' : '').'</li>
				<li><a href="'.ess::$s['relative_path'].'/oppdrag" class="menu-icon menu-oppd">Oppdrag<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/lotto" class="menu-icon menu-lotto">Lotto<span class="icon"></span></a>'.(isset(self::$data['lotto']) ? '
					<ul>
						<li><a href="'.ess::$s['relative_path'].'/lotto_trekninger">Trekninger</a></li>
					</ul>' : '').'</li>
				<li><a href="'.ess::$s['relative_path'].'/fengsel" class="menu-icon menu-fengsel">Fengsel <span class="ny2" id="fengsel_count">'.($fengsel_count > 0 ? $fengsel_count : '').'</span><span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/angrip" class="menu-icon menu-angr">Angrip spiller<span class="icon"></span></a></li>
			</ul>
			<ul>
				<li><a href="'.ess::$s['relative_path'].'/banken" class="menu-icon menu-banken">Banken<span class="icon"></span></a></li>'.$min_side.'
				<li><a href="'.ess::$s['relative_path'].'/poker" class="menu-icon menu-poker">Poker <span class="ny2" id="poker_active">'.($poker_active > 0 ? $poker_active : '').'</span><span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/auksjoner" class="menu-icon menu-auks">Auksjoner <span class="ny2" id="auksjoner_active">'.($auksjoner_active > 0 ? $auksjoner_active : '').'</span><span class="icon"></span></a></li>'.$bydeler.'
				<li><a href="'.ess::$s['relative_path'].'/min_side?a=achievements" class="menu-icon menu-achievements">Prestasjoner<span class="icon"></span></a>'.(self::has_page_id("achievements") || self::has_page_id("hall_of_fame") ? '
					<ul>
						<li><a href="'.ess::$s['relative_path'].'/hall_of_fame" class="menu-icon menu-achievements">Hall of Fame</a></li>
					</ul>' : '').'</li>
				<li><a href="'.ess::$s['relative_path'].'/etterlyst" class="menu-icon menu-etterl">Etterlyst<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/drap" class="menu-icon menu-drap">Drapliste<span class="icon"></span></a></li>
			</ul>';
		}
		
		elseif (login::$logged_in)
		{
			$ret .= '
			<ul>'.$min_side.$bydeler.'
			</ul>';
		}
		
		else
		{
			$ret .= '
			<ul>
				<li><a href="'.ess::$s['relative_path'].'/?orign='.urlencode($_SERVER['REQUEST_URI']).'" class="menu-icon menu-logginn">Logg inn<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/registrer" class="menu-icon menu-register">Registrer deg<span class="icon"></span></a></li>
			</ul>
			<ul>'.$bydeler.'
				<li><a href="'.ess::$s['relative_path'].'/hall_of_fame" class="menu-icon menu-achievements">Hall of Fame<span class="icon"></span></a></li>
			</ul>';
		}
		
		$ret .= '
			<ul>';
		
		if (!$lock) $ret .= self::get_custom_forums();
		$ret .= '
				<li><a href="'.ess::$s['relative_path'].'/forum/forum?id=1" class="menu-icon menu-forum">Game forum<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/forum/forum?id=2" class="menu-icon menu-forum">Off-topic forum<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/forum/forum?id=3" class="menu-icon menu-forum">Salg/søknad forum<span class="icon"></span></a></li>
			</ul>';
		
		$ret .= '
			<ul>
				<li><a href="'.ess::$s['relative_path'].'/node" class="menu-icon menu-help"><b>Hjelp</b> / Support<span class="icon"></span></a></li>'.(!$lock ? '
				<li><a href="'.ess::$s['relative_path'].'/soknader" class="menu-icon menu-sokn">Søknader<span class="icon"></span></a></li>'.(!isset(self::$data['is_avstemning']) || !self::$data['is_avstemning'] ? '
				<li><a href="'.ess::$s['relative_path'].'/polls" class="menu-icon menu-avst">Avstemninger<span class="icon"></span></a></li>' : '').'
				<li><a href="'.ess::$s['relative_path'].'/ranklist" class="menu-icon menu-rankl">Ranklist<span class="icon"></span></a></li>
				<li><a href="'.ess::$s['relative_path'].'/online_list" class="menu-icon menu-online">Spillere pålogget<span class="icon"></span></a></li>' : '').'
				<li><a href="'.ess::$s['relative_path'].'/crewet" class="menu-icon menu-crew">Crewet<span class="icon"></span></a></li>'.(!$lock ? '
				<li><a href="'.ess::$s['relative_path'].'/statistikk" class="menu-icon menu-stats">Statistikk<span class="icon"></span></a></li>' : '').'
				<li><a href="'.ess::$s['relative_path'].'/donasjon" class="menu-icon menu-donate">Donasjoner<span class="icon"></span></a></li>
			</ul>';
		
		if (!MAIN_SERVER)
		{
			$ret .= '
			<ul>
				<li><a href="&rpath;/dev/" class="menu-icon menu-devt">Utviklerverktøy<span class="icon"></span></a></li>
			</ul>';
		}
		
		return $ret;
	}
	
	/**
	 * Hent HTML for egne forum
	 */
	protected static function get_custom_forums()
	{
		if (!login::$logged_in) return '';
		
		// sett opp egendefinerte forum
		$user_forums = '';
		$forums = login::$user->params->get("forums");
		if (!empty($forums))
		{
			$info = new container($forums);
			foreach ($info->items as $row)
			{
				switch ($row[0])
				{
					case "f":
						$user_forums .= '
						<li><a href="'.ess::$s['relative_path'].'/forum/forum?f='.$row[1].'" class="menu-icon menu-forum">'.htmlspecialchars($row[2]).'<span class="icon"></span></a></li>';
					break;
					
					case "fa":
						$user_forums .= '
						<li><a href="'.ess::$s['relative_path'].'/forum/forum?fa='.$row[1].'" class="menu-icon menu-forum">'.htmlspecialchars($row[2]).'<span class="icon"></span></a></li>';
					break;
					
					case "ff":
						$new = isset($row[4]) ? (int) $row[4] : 0;
						$user_forums .= '
						<li><a href="'.ess::$s['relative_path'].'/forum/forum?id='.$row[3].'" class="menu-icon menu-forum">'.htmlspecialchars($row[2]).($new > 0 ? ' <span class="ny">'.$new.'</span>' : '').'<span class="icon"></span></a></li>';
					break;
				}
			}
		}
		
		return $user_forums;
	}
}

	
