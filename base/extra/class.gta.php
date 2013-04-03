<?php

/**
 * GTA-systemet
 */
class gta
{
	/** Energi ved biltyveri */
	const ENERGY_BILTYVERI = 100;
	
	/** Rank man får av biltyveri */
	const RANK_BILTYVERI = 12;
	
	/**
	 * Aktiv spiller
	 * @var player
	 */
	public $up;
	
	/**
	 * Construct
	 * @param player $up
	 */
	public function __construct(player $up)
	{
		$this->up = $up;
	}
	
	/**
	 * Kalkuler ventetid
	 */
	public function calc_wait()
	{
		if (access::has("mod")) return array(0, 0);
		
		// når utførte vi sist biltyveri?
		$result = ess::$b->db->query("SELECT MAX(time_last) FROM gta_options_status WHERE gos_up_id = ".$this->up->id);
		$last = mysql_result($result, 0);
		
		// ventetid
		$delay = game::$settings['delay_biltyveri']['value'];
		$wait = 0;
		if ($last)
		{
			$wait = $last + $delay - time();
			if ($wait > $delay) $wait = 0;
		}
		
		return array($wait, $last);
	}
	
	/** Kontroller rank */
	public function check_rank()
	{
		// har vi høy nok rank til å utføre biltyveri?
		$result = ess::$b->db->query("SELECT MIN(min_rank) FROM gta");
		$min_rank = mysql_result($result, 0);
		
		return $this->up->rank['number'] >= $min_rank;
	}
	
	/** Alternativene vi har */
	public $options;
	
	/** Hent alternativene i bydelen vi befinner oss */
	public function load_options($force = false)
	{
		// har allerede hentet inn alternativene?
		if (!$force && $this->options) return;
		
		// hent alternativene i denne bydelen
		$result = ess::$b->db->query("
			SELECT a.id, a.b_id, a.name, a.max_pos_change, a.max_neg_change, a.max_percent, s.id AS status_id, GREATEST(a.min_percent, IFNULL(s.percent, 0)) AS percent, s.count, s.success
			FROM gta_options AS a
				LEFT JOIN gta_options_status AS s ON a.id = s.optionid AND gos_up_id = ".$this->up->id."
			WHERE a.b_id = ".$this->up->data['up_b_id']."
			ORDER BY s.percent DESC, a.name");
		
		$this->options = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$this->options[$row['id']] = $row;
		}
	}
	
	/**
	 * Utfør biltyveri
	 */
	public function biltyveri_utfor($option_id)
	{
		if (!isset($this->options[$option_id]))
		{
			throw new HSException("Ugyldig alternativ.");
		}
		
		$option = $this->options[$option_id];
		
		// litt sannsynlighetsberegning
		$rand = rand(0, 1000);
		
		// test sannsynlighet
		$ret = array(
			"success" => $rand <= $option['percent'] * 10
		);
		
		// traff vi på sannsynligheten?
		if ($ret['success'])
		{
			// hent alle bilene vi kan skaffe
			$result = ess::$b->db->query("SELECT id, probability FROM gta WHERE min_rank <= ".$this->up->rank['number']);
			$biler = array();
			$prob_total = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				$prob_total += $row['probability'];
				$biler[$row['id']] = $row['probability'];
			}
			
			// finn en bil
			$rand = rand(1, $prob_total);
			$prob = 1;
			$bil = false;
			
			foreach ($biler as $id => $p)
			{
				$prob = $prob + $p;
				
				if ($rand < $prob)
				{
					// fant enhet
					$bil = $id;
					break;
				}
			}
			
			// hent bilen
			$result = ess::$b->db->query("SELECT id, brand, model, min_rank, img_mini, probability FROM gta WHERE id = $bil");
			
			// finn en tilfeldig bil bassert på sannsynligheten for bilen
			$bil = mysql_fetch_assoc($result);
			
			// beregn skade ut i fra sannsynligheten vi hadde
			$damage_min = 99 - floor($option['percent']) * (rand(1, 15) / 10);
			if ($damage_min < 0) $damage_min = 0;
			
			$damage_max = 99 - floor($option['percent']) * (rand(1, 10) / 10);
			if ($damage_max > 99) $damage_max = 99;
			
			if ($damage_min > $damage_max) $damage = $damage_min;
			$damage = rand($damage_min, $damage_max);
			
			// gi rankpoeng
			$ret['rank_change'] = $this->up->increase_rank(self::RANK_BILTYVERI);
			
			// gi bil
			ess::$b->db->query("INSERT INTO users_gta SET ug_up_id = ".$this->up->id.", gtaid = {$bil['id']}, time = ".time().", b_id_org = ".$this->up->data['up_b_id'].", b_id = ".$this->up->data['up_b_id'].", damage = $damage");
			$ret['gta'] = $bil;
			
			// finn en tekst?
			$result = ess::$b->db->query("SELECT got_text FROM gta_options_text WHERE got_go_id = {$option['id']} ORDER BY RAND() LIMIT 1");
			if ($row = mysql_fetch_assoc($result))
			{
				$replace_from = array("%bil%", "%skade%");
				$replace_to = array("{$bil['brand']} {$bil['model']}", $damage);
				
				$ret['message'] = game::bb_to_html(str_replace($replace_from, $replace_to, $row['got_text']));
			}
			else
			{
				$ret['message'] = "Du skaffet deg en <b>{$bil['brand']} {$bil['model']}</b> med <b>$damage %</b> skade!";
			}
		}
		
		// wanted nivå
		$ret['wanted_change'] = $this->up->fengsel_rank(self::RANK_BILTYVERI, $ret['success']);
		
		// oppdater prosenter osv
		$increase = rand(0, $option['max_pos_change'] * 10) / 10;
		
		// for høy prosent?
		$percent = $option['percent'];
		if ($percent + $increase > $option['max_percent'])
		{
			$percent -= rand(0, $option['max_neg_change'] * 10) / 10;
		}
		else
		{
			$percent += $increase;
		}
		
		// ordne status
		if ($option['status_id'])
		{
			ess::$b->db->query("UPDATE gta_options_status SET percent = $percent, time_last = ".time().", count = count + 1".($ret['success'] ? ', success = success + 1' : '')." WHERE id = {$option['status_id']}");
		}
		
		else
		{
			ess::$b->db->query("INSERT INTO gta_options_status SET optionid = {$option['id']}, gos_up_id = ".$this->up->id.", percent = $percent, time_last = ".time().", count = 1".($ret['success'] ? ', success = 1' : ''));
		}
		
		// energi
		$this->up->energy_use(self::ENERGY_BILTYVERI);
		
		// trigger
		$this->up->trigger("biltyveri", array(
				"success" => $ret['success'],
				"option" => $option,
				"prob" => $option['percent'],
				"gta" => $ret['success'] ? $bil : null,
				"damage" => $ret['success'] ? $damage : null));
		
		return $ret;
	}
	
	/**
	 * Hent informasjon om biler og garasjer i de forskjellige bydelene
	 */
	public function get_bydeler_info()
	{
		$bydeler = array();
		foreach (game::$bydeler as $bydel)
		{
			$bydeler[$bydel['id']] = array(
				"cars" => 0,
				"b_id" => $bydel['id'],
				"b_active" => $bydel['active'],
				"ff_id" => null,
				"ff_name" => null,
				"garage" => null,
				"garage_max_cars" => null,
				"garage_free" => 0,
				"garage_next_rent" => null
			);
		}
		
		// antall biler vi har i de ulike bydelene (garasjene)
		$result = ess::$b->db->query("
			SELECT b_id, COUNT(id) AS ant
			FROM users_gta
			WHERE ug_up_id = ".$this->up->id."
			GROUP BY b_id");
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($bydeler[$row['b_id']])) continue;
			$bydeler[$row['b_id']]['cars'] = $row['ant'];
		}
		
		// informasjon om garasjene vi har
		$result = ess::$b->db->query("
			SELECT ugg_b_id, ugg_places, ugg_time_next_rent, ff_id, ff_name
			FROM users_garage
				LEFT JOIN ff ON ff_id = ugg_ff_id
			WHERE ugg_up_id = ".$this->up->id);
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($bydeler[$row['ugg_b_id']])) continue;
			$bydeler[$row['ugg_b_id']]['garage'] = true;
			$bydeler[$row['ugg_b_id']]['ff_id'] = $row['ff_id'];
			$bydeler[$row['ugg_b_id']]['ff_name'] = $row['ff_name'];
			$bydeler[$row['ugg_b_id']]['garage_max_cars'] = $row['ugg_places'];
			$bydeler[$row['ugg_b_id']]['garage_free'] = max(0, $row['ugg_places'] - $bydeler[$row['ugg_b_id']]['cars']);
			$bydeler[$row['ugg_b_id']]['garage_next_rent'] = $row['ugg_time_next_rent'];
		}
		
		return $bydeler;
	}
	
	/**
	 * Hent oversikt over garasjefirmaer
	 */
	public function get_ff()
	{
		$ff = array();
		
		// hent firmaer som leier ut garasjer
		$crew = access::has("mod") ? "" : " AND ff_is_crew = 0";
		$result = ess::$b->db->query("
			SELECT ff_id, ff_name, ff_params
			FROM ff
			WHERE ff_type = ".ff::TYPE_GARASJE." AND ff_inactive = 0$crew");
		
		while ($row = mysql_fetch_assoc($result))
		{
			$params = new params($row['ff_params']);
			unset($row['ff_params']);
			
			$row['price'] = $params->get("garasje_price", ff::GTA_GARAGE_PRICE_DEFAULT);
			$ff[$row['ff_id']] = $row;
		}
		
		return $ff;
	}
	
	/**
	 * Finn begrensning for antall plasser man kan ha i en garasje
	 */
	public function get_places_limit()
	{
		return 2 + ($this->up->rank['number']-1) * 2;
	}
	
	/**
	 * Sjekk om vi kan utføre betaling nå
	 */
	public static function can_pay($ugg_time_next)
	{
		// må være under 3 dager til neste betaling
		$expire = time() + 86400 * 3;
		
		return $expire > $ugg_time_next;
	}
	
	/**
	 * Juster ned sannsynligheten for å utføre biltyveri for spillere
	 */
	public static function biltyveri_prob_decrease()
	{
		// setter ned sannsynligheten med 1 % av nåværende prosentverdi
		ess::$b->db->query("
			UPDATE gta_options_status gos
				JOIN users_players ON up_id = gos.gos_up_id AND up_access_level != 0
				JOIN gta_options go ON go.id = gos.optionid AND go.min_percent < gos.percent
			SET
				gos.percent = GREATEST(go.min_percent, gos.percent * 0.99)");
	}
}