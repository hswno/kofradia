<?php

class page_bydeler extends pages_player
{
	/**
	 * Energi for å reise med taxi
	 */
	const TAXI_ENERGY = 1000;
	
	const TAXI_PRICE_KM = 20000;
	const TAXI_POINTS_KM = 5; // multiplisert med ranknummer
	
	const GTA_ENERGY_LOW = 100; // energi avhengig av skade på bil
	const GTA_ENERGY_HIGH = 3000;
	const GTA_PRICE_KM = 2000;
	
	const GTA_POINTS_LOW = 20;
	const GTA_POINTS_HIGH = 50;
	const GTA_POINTS_FACT_LOW = 3;
	const GTA_POINTS_FACT_HIGH = 1;
	
	const GTA_DAMAGE_FACT_LOW = 2;
	const GTA_DAMAGE_FACT_HIGH = 10;
	
	/**
	 * Beregn prisfaktor for GTA for verdi
	 * @param int $points antall poeng bilen gir når den stjeles
	 */
	protected static function get_gta_factor_points($points)
	{
		return max(0.5, self::GTA_POINTS_FACT_LOW + (self::GTA_POINTS_FACT_HIGH - self::GTA_POINTS_FACT_LOW) * (($points - self::GTA_POINTS_LOW) / (self::GTA_POINTS_HIGH - self::GTA_POINTS_LOW)));
	}
	
	/**
	 * Beregn prisfaktor for GTA for skade
	 * @param int $damage skade på bilen (0-99)
	 */
	protected static function get_gta_factor_damage($damage)
	{
		return self::GTA_DAMAGE_FACT_LOW + ($damage / 100 * (self::GTA_DAMAGE_FACT_HIGH - self::GTA_DAMAGE_FACT_LOW));
	}
	
	/**
	 * Beregn energi for GTA
	 * @param int $damage skade på bilen (0-99)
	 */
	protected static function get_gta_energy($damage)
	{
		return round(self::GTA_ENERGY_LOW + ($damage / 100 * (self::GTA_ENERGY_HIGH - self::GTA_ENERGY_LOW)));
	}
	
	/**
	 * Finn koordinater på kartet utifra ressurs
	 */
	public static function koordinat_ressurs($x, $y)
	{
		// TODO: flytt ressursene slik at denne funksjonen blir unødvendig
		$res = array(
			round($x / 3 * 1.589) - 40,
			round($y / 3 * 1.589) - 27
		);
		
		return array(
			$res[0] / 720 * 100,
			$res[1] / 665 * 100
		);
	}
	
	public $bydeler = array(
		// liksom-bydel for de FF uten tilknytning bydel
		0 => array(
			"id" => 0,
			"active" => false,
			"ff" => array()
		)
	);
	
	/** FF-konkurranser */
	public $fff = array();
	
	/** Invitasjoner til FF */
	public $ff_invites = array();
	
	public function __construct(player $up = null)
	{
		parent::__construct($up);
		ess::$b->page->add_title("Bydeler");
		
		// hent ut de aktive bydelene
		foreach (game::$bydeler as $row)
		{
			#if ($row['active'] == 0) continue;
			
			$row['ff'] = array();
			$row['num_players'] = 0;
			$row['sum_money'] = 0;
			
			$row['bydel_x'] = $row['bydel_x'] / 646 * 100;
			$row['bydel_y'] = $row['bydel_y'] / 596 * 100;
			
			$this->bydeler[$row['id']] = $row;
		}
		
		if ($this->up) $this->get_gta();
		
		// reise?
		if ($this->up && isset($_POST['reise']))
		{
			$this->reise();
		}
		
		$this->get_stats();
		$this->get_ff();
		
		$this->show_full_page();
	}
	
	/**
	 * Allerede i bydelen
	 */
	protected function reise_error_in($bydel)
	{
		ess::$b->page->add_message("Du befinner deg allerede i ".htmlspecialchars($bydel['name']).".");
		redirect::handle();
	}
	
	/**
	 * Ikke nok penger til å reise
	 */
	protected function reise_error_cash($bydel)
	{
		ess::$b->page->add_message("Du har ikke nok penger til å reise til ".htmlspecialchars($bydel['name']).".", "error");
		redirect::handle();
	}
	
	/**
	 * Reise til en annen bydel
	 */
	protected function reise()
	{
		redirect::store("bydeler#b");
		
		$this->up->fengsel_require_no();
		$this->up->bomberom_require_no();
		
		// finn bydelen
		$bydel = false;
		foreach ($this->bydeler as $row)
		{
			if ($row['id'] == 0 || $row['active'] == 0) continue;
			if ($row['name'] == $_POST['reise'])
			{
				$bydel = $row;
				break;
			}
		}
		if (!$bydel)
		{
			ess::$b->page->add_message("Fant ikke bydelen.", "error");
			redirect::handle();
		}
		
		// allerede i bydelen?
		if ($bydel['id'] == $this->up->data['up_b_id'])
		{
			$this->reise_error_in($bydel);
		}
		
		// teleportere?
		if (isset($_POST['teleporter']) && access::is_nostat())
		{
			// teleporter
			ess::$b->db->query("UPDATE users_players SET up_b_id = {$bydel['id']}, up_b_time = ".time()." WHERE up_id = ".$this->up->id." AND up_access_level != 0 AND up_b_id != {$bydel['id']}");
			if (ess::$b->db->affected_rows() == 0) $this->reise_error_in($bydel);
			
			ess::$b->page->add_message('Du teleporterte til <b>'.htmlspecialchars($bydel['name']).'</b>.');
			redirect::handle();
		}
		
		// med bil?
		if (isset($_POST['gta']))
		{
			if ($this->gta_count == 0)
			{
				ess::$b->page->add_message("Du har ingen biler i bydelen du oppholder deg i.", "error");
				redirect::handle();
			}
			
			if (!$this->gta_garage[$bydel['id']]['garage'])
			{
				ess::$b->page->add_message('Du har ingen garasje på <b>'.htmlspecialchars($bydel['name']).'</b> og kan ikke reise dit med bil.', "error");
				redirect::handle();
			}
			
			if ($this->gta_garage[$bydel['id']]['garage_free'] == 0)
			{
				ess::$b->page->add_message('Det er ingen ledige plasser i garasjen på <b>'.htmlspecialchars($bydel['name']).'</b>.', "error");
				redirect::handle();
			}
			
			// regn ut avstand (km)
			$distance = self::calc_travel_distance($this->up->bydel, $bydel);
			
			// har vi valgt en bil?
			if (isset($_POST['sel']))
			{
				if (!isset($_POST['bil']))
				{
					ess::$b->page->add_message("Du må velge en bil du ønsker å reise med.", "error");
				}
				
				else
				{
					$this->reise_gta_check($bydel, $distance);
				}
					
				// TODO
			}
			
			// vis skjema for å velge en bil å reise med
			ess::$b->page->add_title($bydel['name'], "Reis med bil");
			
			// hent bilene i garasjen
			$pagei = new pagei(pagei::ACTIVE_POST, "side", pagei::PER_PAGE, 10);
			$result = $pagei->query("
				SELECT s.id, s.time, g.brand, g.model, g.img_mini, g.value, s.damage, g.points
				FROM users_gta AS s
					JOIN gta AS g ON s.gtaid = g.id
				WHERE ug_up_id = {$this->up->id} AND s.b_id = {$this->up->data['up_b_id']}
				ORDER BY g.points*(100-s.damage) DESC");
			
			echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Reis med bil til '.htmlspecialchars($bydel['name']).'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="bydeler#b">Tilbake</a></p>
		<form action="bydeler#b" method="post">
			<input type="hidden" name="reise" value="'.htmlspecialchars($bydel['name']).'" />
			<input type="hidden" name="gta" />
			<table class="table center">
				<thead>
					<tr>
						<th colspan="2">Merke/Modell</th>
						<th>Dato anskaffet</th>
						<th>Skade</th>
						<th>Energi</th>
						<th>Utgifter</th>
					</tr>
				</thead>
				<tbody>';
				
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				$price = $distance * self::GTA_PRICE_KM * self::get_gta_factor_points($row['points']) * self::get_gta_factor_damage($row['damage']);
				$energy = self::get_gta_energy($row['damage']);
				
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" id="bil_'.$row['id'].'" name="bil" value="'.$row['id'].'"'.(postval("bil") == $row['id'] ? ' checked="checked"' : '').' />'.(empty($row['img_mini']) ? '&nbsp;' : '<img src="'.$row['img_mini'].'" alt="Bilde" />').'</td>
						<td>'.htmlspecialchars($row['brand']).'<br /><b>'.htmlspecialchars($row['model']).'</b></td>
						<td>'.ess::$b->date->get($row['time'])->format().'</td>
						<td align="right">'.$row['damage'].' %</td>
						<td align="right">'.game::format_num($energy / $this->up->data['up_energy_max'] * 100, 1).' %</td>
						<td align="right">'.game::format_cash($price).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>'.($pagei->pages > 1 ? '
			<p class="c">'.$pagei->pagenumbers("input").'</p>' : '').'
			<p class="c">'.show_sbutton("Reis til ".htmlspecialchars($bydel['name']), 'name="sel"').'</p>
		</form>
	</div>
</div>';
			
			ess::$b->page->load();
		}
		
		// ta taxi?
		if (isset($_POST['taxi']))
		{
			// har vi ikke nok energi?
			if (!$this->up->energy_check(self::TAXI_ENERGY*1.3)) // pluss på 30 % så man ikke kan ende opp på 0 % energi
			{
				ess::$b->page->add_message("Du har ikke nok energi for å reise med taxi.", "error");
				redirect::handle();
			}
			
			// regn ut avstand (km)
			$distance = self::calc_travel_distance($this->up->bydel, $bydel);
			
			// regn ut pris og rankpoeng
			$price = round($distance * self::TAXI_PRICE_KM);
			$points = round($distance * self::TAXI_POINTS_KM * $this->up->rank['number']);
			
			// har ikke nok rank?
			if ($this->up->data['up_points'] < $points * 2) // må ha dobbelte
			{
				ess::$b->page->add_message("Du har ikke nok rank til å reise til ".htmlspecialchars($bydel['name']).".", "error");
				redirect::handle();
			}
			
			// forsøk å reis
			ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $price, up_b_id = {$bydel['id']}, up_b_time = ".time()." WHERE up_id = ".$this->up->id." AND up_cash >= $price AND up_b_id != {$bydel['id']}");
			
			// feilet?
			if (ess::$b->db->affected_rows() == 0)
			{
				// allerede i bydelen?
				$result = ess::$b->db->query("SELECT up_b_id FROM users_players WHERE up_id = ".$this->up->id);
				if (mysql_result($result, 0, 0) == $bydel['id'])
				{
					$this->reise_error_in($bydel);
				}
				
				// hadde ikke råd
				$this->reise_error_cash($bydel);
			}
			
			// energi
			$this->up->energy_use(self::TAXI_ENERGY);
			
			// rank
			$this->up->increase_rank(-$points);
			
			// vellykket
			ess::$b->page->add_message("Du tok taxi til <b>".htmlspecialchars($bydel['name'])."</b>. Det kostet deg <b>".game::format_cash($price)."</b> og ".game::format_number($points)." poeng.");
			redirect::handle();
		}
		
		ess::$b->page->add_message("Ukjent reisemetode.", "error");
		redirect::handle();
	}
	
	protected function reise_gta_check($bydel, $distance)
	{
		$ug_id = (int) $_POST['bil'];
		
		// hent bilen
		$result = ess::$b->db->query("
			SELECT s.id, g.brand, g.model, g.value, s.damage, g.points
			FROM users_gta s
				JOIN gta g ON s.gtaid = g.id
			WHERE ug_up_id = {$this->up->id} AND s.b_id = {$this->up->data['up_b_id']} AND s.id = $ug_id");
		
		$bil = mysql_fetch_assoc($result);
		
		if (!$bil)
		{
			ess::$b->page->add_message("Fant ikke bilen du valgte. Prøv på nytt.", "error");
			return;
		}
		
		$price = round($distance * self::GTA_PRICE_KM * self::get_gta_factor_points($bil['points']) * self::get_gta_factor_damage($bil['damage']));
		$energy = self::get_gta_energy($bil['damage']);
		
		// har vi ikke nok energi?
		if (!$this->up->energy_check($energy*1.3)) // pluss på 30 % så man ikke kan ende opp på 0 % energi
		{
			ess::$b->page->add_message("Du har ikke nok energi for å reise med denne bilen.", "error");
			return;
		}
		
		// forsøk å reis
		ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $price, up_b_id = {$bydel['id']}, up_b_time = ".time()." WHERE up_id = ".$this->up->id." AND up_cash >= $price AND up_b_id != {$bydel['id']}");
		
		// feilet?
		if (ess::$b->db->affected_rows() == 0)
		{
			// anta dårlig råd
			$this->reise_error_cash($bydel);
			return;
		}
		
		// flytt bilen
		ess::$b->db->query("UPDATE users_gta SET time_last_move = ".time().", b_id = {$bydel['id']} WHERE id = {$bil['id']}");
		
		// energi
		$this->up->energy_use($energy);
		
		// vellykket
		ess::$b->page->add_message("Du benyttet din <b>{$bil['brand']} {$bil['model']}</b> med <b>{$bil['damage']} %</b> skade og reiste til <b>".htmlspecialchars($bydel['name'])."</b>. Det kostet deg <b>".game::format_cash($price)."</b>.");
		redirect::handle();
	}
	
	/**
	 * Hent statistikk for hver bydel
	 */
	public function get_stats()
	{
		global $_game;
		
		$expire = time() - 604800; // tell kun spillere som har vært pålogget siste uken
		$result = ess::$b->db->query("SELECT up_b_id, COUNT(up_id) AS ant, SUM(up_cash) AS money FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']} AND up_last_online > $expire GROUP BY up_b_id");
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($this->bydeler[$row['up_b_id']])) continue;
			
			$this->bydeler[$row['up_b_id']]['num_players'] = $row['ant'];
			$this->bydeler[$row['up_b_id']]['sum_money'] = $row['money'];
		}
	}
	
	/**
	 * Hent FF
	 */
	public function get_ff()
	{
		// hent alle eiere
		$result = ess::$b->db->query("
			SELECT ffm_up_id, ffm_ff_id
			FROM ff_members
			WHERE ffm_priority = 1 AND ffm_status = 1");
		$eiere = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$eiere[$row['ffm_ff_id']][] = $row['ffm_up_id'];
		}
		
		// hent FF
		$result = ess::$b->db->query("
			SELECT ff_id, ff_type, ff_name, ff_br_id, ff_logo_path, br_b_id, br_pos_x, br_pos_y
			FROM ff LEFT JOIN bydeler_resources ON ff_br_id = br_id
			WHERE ff_inactive = 0");
		while ($row = mysql_fetch_assoc($result))
		{
			$b_id = $row['ff_br_id'] ? $row['br_b_id'] : 0;
			if ($b_id == 0) $this->bydeler[0]['active'] = true;
			
			if (!isset($this->bydeler[$b_id]))
			{
				throw new HSException("Ugyldig bydel for ff {$row['ff_id']}.");
			}
			
			$k = self::koordinat_ressurs($row['br_pos_x'], $row['br_pos_y']);
			
			$row['br_pos_x'] = $k[0];
			$row['br_pos_y'] = $k[1];
			
			$row['eier'] = isset($eiere[$row['ff_id']]) ? $eiere[$row['ff_id']] : array();
			
			$this->bydeler[$b_id]['ff'][$row['ff_id']] = $row;
		}
		
		// hent mine invitasjoner
		$this->ff_invites = array();
		if ($this->up)
		{
			$result = ess::$b->db->query("
				SELECT ff_id, ff_type, ff_name, ffm_date_join, ffm_priority
				FROM ff_members JOIN ff ON ff_id = ffm_ff_id
				WHERE ffm_up_id = ".$this->up->id." AND ffm_status = 0 AND ff_inactive = 0
				ORDER BY ffm_date_join DESC");
			while ($row = mysql_fetch_assoc($result))
			{
				$row['eier'] = isset($eiere[$row['ff_id']]) ? $eiere[$row['ff_id']] : array();
				$this->ff_invites[] = $row;
			}
		}
		
		// hent konkurranser som er aktive
		$time = time();
		$this->fff = array();
		$result = ess::$b->db->query("
			SELECT fff_id, fff_time_start, fff_time_expire, COUNT(ff_id) AS ff_count, COUNT(IF(ff_inactive = 0, 1, NULL)) AS ff_count_active
			FROM ff_free
				LEFT JOIN ff ON ff_fff_id = fff_id
			WHERE $time >= fff_time_start AND fff_active = 1
			GROUP BY fff_id
			ORDER BY fff_time_expire");
		while ($row = mysql_fetch_assoc($result))
		{
			$this->fff[] = $row;
		}
	}
	
	/** Antall biler i bydelen vi er i */
	protected $gta_count;
	
	/** Garasjeoversikt for andre bydeler */
	protected $gta_garage;
	
	/**
	 * Hent inn GTA info
	 */
	protected function get_gta()
	{
		// hent garasjer
		$gta = new gta($this->up);
		$this->gta_garage = $gta->get_bydeler_info();
		
		// sett opp antall biler i nåværende bydel
		if (!isset($this->gta_garage[$this->up->data['up_b_id']]))
		{
			$this->gta_count = 0;
			return;
		}
		
		$this->gta_count = $this->gta_garage[$this->up->data['up_b_id']]['cars'];
	}
	
	/**
	 * Kalkuler avstand
	 */
	protected static function calc_travel_distance($from, $to)
	{
		return game::coord_distance($from['longitude'], $from['latitude'], $to['longitude'], $to['latitude']);
	}
	
	/**
	 * Vis side med kart
	 */
	public function show_full_page()
	{
		global $__server;
		
		// hent familierangering
		$ff_list = ff::get_fam_points_rank();
		
		// deaktiver høyre side
		//define("DISABLE_RIGHT_COL", true);
		
		ess::$b->page->add_css('
#default_main { overflow: visible }');
		
		ess::$b->page->add_js_domready('
	sm_scripts.load_hm();
	window.HM.addEvent("f-changed", function(data) {
		//$$(".bydeler_filter a").removeClass("active");
		$$(".bydeler_ressurs").setStyle("display", "none");
		$$(".bydeler_ressurs_"+data).setStyle("display", "block");
		//$("f_"+data).addClass("active");
	});
	window.HM.addEvent("f-removed", function() {
		//$$(".bydeler_filter a").removeClass("active");
		//$("f_").addClass("active");
		$$(".bydeler_ressurs").setStyle("display", "block");
	});
	window.HM.addEvent("b-added", function() {
		//$$(".bydeler_alt a").removeClass("active");
		//$("v_b").addClass("active");
		$$(".bydeler_br").setStyle("display", "none");
		$$(".bydeler_steder").setStyle("display", "block");
	});
	window.HM.addEvent("b-removed", function() {
		//$$(".bydeler_alt a").removeClass("active");
		//$("v_").addClass("active");
		$$(".bydeler_br").setStyle("display", "block");
		$$(".bydeler_steder").setStyle("display", "none");
	});
	
	$$(".bydeler_steder").setStyle("display", "none");
	$$(".bydeler_alt a").addEvent("click", function(e)
	{
		window.HM.remove("f");
		window.HM.set("b", "");
		e.stop();
	});
	
	$$(".bydeler_filter a").addEvent("click", function(e)
	{
		window.HM.remove("b");
		if (this.get("id") == "f_") window.HM.remove("f");
		else window.HM.set("f", this.get("id").substring(2));
		e.stop();
	});
	
	window.HM.recheck();
');
		
		// sett opp alle FF og sorter dem i y-retning
		$data = array();
		$pos_x = array();
		$pos_y = array();
		foreach ($this->bydeler as $id => $bydel)
		{
			if ($id == 0) continue;
			
			foreach ($bydel['ff'] as $row)
			{
				$pos_x[] = $row['br_pos_x'];
				$pos_y[] = $row['br_pos_y'];
				
				$type = ff::$types[$row['ff_type']];
				
				// familie
				if ($row['ff_type'] == 1)
				{
					$eier = count($row['eier']) == 0 ? 'Ingen leder av broderskapet' : 'Styres av '.self::list_players($row['eier']);
					$class = "bydeler_ressurs_familie";
					
					// antall poeng
					if (isset($ff_list[$row['ff_id']]) && $ff_list[$row['ff_id']]->data['ff_is_crew'] == 0) $eier .= '<br />'.game::format_num($ff_list[$row['ff_id']]->data['ff_points_sum']).' poeng';
				}
				
				// firma
				else
				{
					if ($type['type'] == "bomberom")
					{
						$eier = count($row['eier']) == 0 ? 'Ingen styrer bomberommet' : 'Styres av '.self::list_players($row['eier']);
					} else {
						$eier = count($row['eier']) == 0 ? 'Ingen eier av firmaet' : 'Eies av '.self::list_players($row['eier']);
					}
					
					$class = "bydeler_ressurs_firma bydeler_ressurs_{$type['type']}firma";
				}
				
				$data[] = '
		<a href="'.$__server['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" class="bydeler_ressurs '.$class.'" style="left: '.$row['br_pos_x'].'%; top: '.$row['br_pos_y'].'%">
			<img class="bydeler_ressurs_t" src="'.htmlspecialchars($type['bydeler_graphic']).'" alt="'.htmlspecialchars($type['bydeler_alt_pre']).htmlspecialchars($row['ff_name']).'" />
			<span class="bydeler_ressurs_tekst">
				'.htmlspecialchars($row['ff_name']).'<span class="bydeler_owner"><br />
				'.$eier.'</span>
			</span>
			<img class="bydeler_ressurs_graphic" src="'.htmlspecialchars(ff::get_logo_path_static($row['ff_id'], $row['ff_logo_path'])).'" alt="" />
		</a>';
			}
		}
		array_multisort($pos_y, $pos_x, $data);
		
		$bydeler_0 = $this->bydeler[0];
		unset($this->bydeler[0]);
		
		// sorter bydelene i y-retning
		$bydeler_x = array();
		$bydeler_y = array();
		foreach ($this->bydeler as $id => $bydel)
		{
			$bydeler_x[] = $bydel['bydel_x'];
			$bydeler_y[] = $bydel['bydel_y'];
		}
		array_multisort($bydeler_x, $bydeler_y, $this->bydeler);
		
		// invitasjoner til FF
		if (count($this->ff_invites) > 0)
		{
			echo '
<div class="bg1_c small">
	<h1 class="bg1">Invitasjoner<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">';
			
			foreach ($this->ff_invites as $row)
			{
				$type = ff::$types[$row['ff_type']];
				echo '
		<p>Du er invitert til '.$type['refobj'].' <a href="'.$__server['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a> som '.$type['priority'][$row['ffm_priority']].' ('.ess::$b->date->get($row['ffm_date_join'])->format(date::FORMAT_NOTIME).') - <a href="'.$__server['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">Godta/avslå</a></p>';
			}
			
			echo '
	</div>
</div>';
		}
		
		if (count($this->fff) > 0)
		{
			echo '
<div class="bg1_c medium bydeler_br bydeler_ressurs bydeler_ressurs_familie">
	<h1 class="bg1">Konkurranse om å danne broderskap<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<table class="table center tablem">
			<thead>
				<tr>
					<th>Avsluttes</th>
					<th>Gjenstår</th>
					<th>Antall broderskap</th>
					<th>Gjenstående broderskap</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody class="r">';
			
			$i = 0;
			$free = 0;
			foreach ($this->fff as $row)
			{
				if ($row['ff_count'] < ff::MAX_FFF_FF_COUNT) $free += ff::MAX_FFF_FF_COUNT-$row['ff_count'];
				
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.ess::$b->date->get($row['fff_time_expire'])->format(date::FORMAT_SEC).'</td>
					<td>'.game::timespan(max(time(), $row['fff_time_expire']), game::TIME_ABS).'</td>
					<td>'.$row['ff_count'].'</td>
					<td>'.$row['ff_count_active'].'</td>
					<td><a href="'.$__server['relative_path'].'/ff/?fff_id='.$row['fff_id'].'">Vis &raquo;</a></td>
				</tr>';
			}
			
			$create_link = login::$logged_in
				? ($this->up->rank['number'] < ff::$types[1]['priority_rank'][1]
					? ' - Du har ikke høy nok rank til å opprette et broderskap'
					: ' - Du har høy nok rank - <a href="'.$__server['relative_path'].'/ff/?create">Opprett broderskap &raquo;</a>')
				: '';
			
			echo '
			</tbody>
		</table>'.($free > 0 ? '
		<p class="c" style="margin-top: 0">Det er '.$free.' '.fword("ledig konkurranseplass", "ledige konkurranseplasser", $free).$create_link.'</p>' : '
		<p class="c" style="margin-top: 0">Ingen ledige konkurranseplasser.</p>').'
	</div>
</div>';
		}
		
		// topp rangerte familier
		if (count($ff_list) > 0)
		{
			echo '
<div class="bg1_c xxsmall bydeler_br bydeler_ressurs bydeler_ressurs_familie">
	<h1 class="bg1">Topp rangerte broderskap<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<dl class="dd_right">';
			
			$i = 0;
			foreach ($ff_list as $ff)
			{
				$title = "For rank til medlemmer: ".$ff->data['ff_points_up']." - For firma til medlemmer: ".$ff->data['ff_points_ff']." - For drap: ".$ff->data['ff_points_kill'];
				
				echo '
			<dt><a href="'.ess::$s['rpath'].'/ff/?ff_id='.$ff->id.'">'.htmlspecialchars($ff->data['ff_name']).'</a></dt>
			<dd title="'.$title.'">'.game::format_num($ff->data['ff_points_sum']).' poeng</dd>';
				
				// vis kun 3 beste familiene
				if (++$i == 3) break;
			}
			
			echo '
		</dl>
		<p class="c"><a href="'.ess::$s['rpath'].'/node/19">Poenginformasjon</a></p>
	</div>
</div>';
		}
		
		kf_menu::$data['bydeler_menu'] = true;
		
		echo '
<h1 class="bydeler">Bydeler</h1>
<div class="bydeler">
	<div class="bydeler_kart bydeler_br">
		<img src="'.STATIC_LINK.'/themes/kofradia/drammen_stor.gif" class="bydeler_bg" />
		'.implode('', $data).'
	</div>';
		
		// har vi noen FF som ikke er plassert?
		if ($bydeler_0['active'])
		{
			echo '
	<div class="bydeler_uplassert bydeler_br">';
			
			foreach ($bydeler_0['ff'] as $row)
			{
				$type = ff::$types[$row['ff_type']];
				
				// familie
				if ($row['ff_type'] == 1)
				{
					$eier = count($row['eier']) == 0 ? 'Ingen leder av broderskapet' : 'Styres av '.self::list_players($row['eier']);
					$class = "bydeler_ressurs_familie";
				}
				
				// firma
				else
				{
					if ($type['type'] == "bomberom")
					{
						$eier = count($row['eier']) == 0 ? 'Ingen styrer bomberommet' : 'Styres av '.self::list_players($row['eier']);
					} else {
						$eier = count($row['eier']) == 0 ? 'Ingen eier av firmaet' : 'Eies av '.self::list_players($row['eier']);
					}
					
					$class = "bydeler_ressurs_firma bydeler_ressurs_{$type['type']}firma";
				}
				
				echo '
		<div class="bydeler_uplassert_boks">
			<a href="'.$__server['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" class="bydeler_ressurs '.$class.'">
				<img class="bydeler_ressurs_graphic" src="'.htmlspecialchars(ff::get_logo_path_static($row['ff_id'], $row['ff_logo_path'])).'" alt="" />
				<span class="bydeler_ressurs_tekst">
					'.htmlspecialchars($row['ff_name']).'<span class="bydeler_owner"><br />
					'.$eier.'</span>
				</span>
				<img class="bydeler_ressurs_t" src="'.htmlspecialchars($type['bydeler_graphic']).'" alt="'.htmlspecialchars($type['bydeler_alt_pre']).htmlspecialchars($row['ff_name']).'" />
			</a>
		</div>';
			}
			
			echo '
	</div>';
		}
		
		echo '
	<div class="bydeler_kart bydeler_steder">
		<img src="'.STATIC_LINK.'/themes/kofradia/drammen_stor.gif" class="bydeler_bg" />';
		
		foreach ($this->bydeler as $bydel)
		{
			if ($bydel['active'] == 0) continue;
			
			if ($this->up)
			{
				$distance = self::calc_travel_distance($this->up->bydel, $bydel);
				
				$taxi_price = round($distance * self::TAXI_PRICE_KM);
				$taxi_points = round($distance * self::TAXI_POINTS_KM * $this->up->rank['number']);
			}
			
			echo '
		<div class="map_unit'.($this->up && $this->up->bydel['id'] == $bydel['id'] ? ' map_active' : '').'" style="left: '.$bydel['bydel_x'].'%; top: '.$bydel['bydel_y'].'%" id="map_link_'.$bydel['id'].'">
			<div class="map_title">
				<p class="map_link"><b><b><b>'.htmlspecialchars($bydel['name']).'</b></b></b></p>
				<div class="bydeler_sted">
					<div class="bydeler_sted_info">
						<dl class="dd_right">
							<dt>Spillere</dt>
							<dd>'.game::format_number($bydel['num_players']).'</dd>
							<dt>Penger i omløp</dt>
							<dd>'.game::format_cash($bydel['sum_money']).'</dd>
						</dl>';
			
			if (!$this->up) {} // ignorer anonyme brukere
			elseif ($this->up->bydel['id'] == $bydel['id'])
			{
				echo '
						<p>Du befinner deg i denne bydelen.</p>';
			}
			elseif ($this->up->fengsel_check())
			{
				echo '
						<p>Du er i fengsel og kan ikke reise.</p>';
			}
			elseif ($this->up->bomberom_check())
			{
				echo '
						<p>Du er i bomberom og kan ikke reise.</p>';
			}
			else
			{
				echo '
						<div class="bydeler_reise c">
							<form action="bydeler" method="post">
								<input type="hidden" name="reise" value="'.htmlspecialchars($bydel['name']).'" />';
				
				// taxi
				if (!$this->up->energy_check(self::TAXI_ENERGY*1.3))
				{
					echo '
								<p>Du har ikke nok energi til å ta taxi hit.</p>';
				}
				elseif ($this->up->data['up_points'] < $taxi_points * 2) // må ha dobbelte
				{
					echo '
								<p>Du har ikke høy nok rank til å ta taxi hit.</p>';
				}
				else
				{
					echo '
								<p>'.show_sbutton("Ta taxi (".game::format_cash($taxi_price).", ".game::format_number(round($taxi_points))." poeng)", 'name="taxi"').'</p>';
				}
				
				// gta
				if ($this->gta_count == 0)
				{
					echo '
								<p>Du har ingen biler i bydelen du oppholder deg i for å reise med.</p>';
				}
				elseif (!$this->gta_garage[$bydel['id']]['garage'])
				{
					echo '
								<p>Det er ingen garasje i denne bydelen.</p>';
				}
				elseif ($this->gta_garage[$bydel['id']]['garage_free'] == 0)
				{
					echo '
								<p>Det er ingen ledige plasser i garasjen i denne bydelen.</p>';
				}
				else
				{
					echo '
								<p>'.show_sbutton("Kjør egen bil", 'name="gta"').'</p>';
				}
				
				// teleportere
				if (access::is_nostat())
				{
					echo '
								<p>'.show_sbutton("Teleporter hit (nostat)", 'name="teleporter"').'</p>';
				}
				
				echo '
							</form>
						</div>';
			}
			
			echo '
					</div>
				</div>
			</div>
		</div>';
		}
		
		echo '
	</div>';
		
		echo '
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Liste opp spillere
	 */
	protected function list_players($list)
	{
		$list = array_map(function($up_id)
		{
			return '<user id="'.$up_id.'" nolink />';
		}, $list);
		
		$last = array_pop($list);
		if (count($list) > 0) return implode(",<br /> ", $list).'<br /> og '.$last;
		
		return $last;
	}
}
