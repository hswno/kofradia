<?php

/**
 * Beskyttelse
 */
class protection
{
	/**
	 * Maksimal beskyttelse (som blir brukt til å regne ut beskyttelsen)
	 */
	const MAX_PROTECTION = 50;
	
	/**
	 * Beskyttelse man alltid har
	 * Kan ikke helt sammenliknes med de ulike beskyttelsene, da denne ikke settes i perspektiv med beskyttelseskapasiteten
	 */
	const PROTECTION_ALL = 5;
	
	/**
	 * De ulike beskyttelsene
	 */
	public static $protections = array(
		1 => array(
			"name" => "Hettegenser m/stålplate",
			"price" => 400000,
			"strength" => 15,
			"rank" => 0
		),
		2 => array(
			"name" => "Skuddsikker vest",
			"price" => 1100000,
			"strength" => 35,
			"rank" => 6
		),
		3 => array(
			"name" => "Skuddsikker heldress",
			"price" => 2600000,
			"strength" => 50,
			"rank" => 8
		)
	);
	
	public static $vekt_familie = array(
		1 => 0.25, // boss
		2 => 0.2, // underboss
		3 => 0.1, // capo
		4 => 0.05 // soldier
	);
	
	public static $vekt_rankdiff = array(
		1 => 0.03,
		2 => 0.06,
		3 => 0.1,
		4 => 0.15,
		5 => 0.2
	);
	
	public static $vekt_specrankdiff = array(
		1 => 0.2, // f.eks. Don/Leiemorder angriper Lucky Luciano, Lucky Luciano angriper Legend
		2 => 0.25, // f.eks. Don/Leiemorder angriper Legend, Lucky Luciano angriper Capo di tutti capi
		3 => 0.3 // f.eks. Don/Leiemorder angriper Capi di tutti capi
	);
	
	/**
	 * Hent beskyttelsesobjekt
	 */
	public static function get($id, $state, player $up)
	{
		if (!isset(self::$protections[$id]))
		{
			return new self(null, null, $up);
		}
		
		return new self($id, $state, $up);
	}
	
	public $id;
	public $data;
	public $state;
	
	/**
	 * @var player
	 */
	public $up;
	
	/**
	 * Opprett objekt
	 * @param int $id
	 * @param int $state skade
	 * @param player $up
	 */
	public function __construct($id, $state, player $up)
	{
		// har kjøpt beskyttelse?
		if (isset(self::$protections[$id]))
		{
			$this->id = $id;
			$this->data = &self::$protections[$id];
			$this->state = $state; // 0-1
		}
		
		$this->up = $up;
	}
	
	/**
	 * Kalkuler beskyttelse
	 * @param player $up spiller som angriper (beskyttelsen er avhengig av den som angriper)
	 */
	public function calc_protection(player $up = NULL)
	{
		$styrke = 0;
		
		// beregn vektverdi
		$weight = $this->calc_weight($up);
		
		// har vi kjøpt beskyttelse?
		if ($this->data)
		{
			// beregn utgangspunkt for styrkeverdi
			$styrke += $this->data['strength'] / (self::MAX_PROTECTION * 2);
			
			// legg til vektverdi
			$styrke += $weight;
			
			// sett i perspektiv med beskyttelseskapasiteten
			$styrke *= $this->up->data['up_protection_state'];
		}
		
		// legg til minimumbeskyttelse
		$styrke += $this->calc_protection_min($weight);
		
		// sett i perspektiv med energiprosenten
		$styrke *= 1 - (1 - $this->up->get_energy_percent()/100) / 4;
		
		return $styrke;
	}
	
	protected function calc_protection_min($weight)
	{
		$prot = self::PROTECTION_ALL / (self::MAX_PROTECTION * 2);
		return $prot + $prot * $weight;
	}
	
	/**
	 * Kalkuler beskyttelse man får med vektpoengene
	 * @param player $up spiller som angriper
	 */
	public function calc_weight(player $up)
	{
		$vekt = 0;
		
		// sjekk for broderskapmedlemskap
		$result = ess::$b->db->query("SELECT MIN(ffm_priority) FROM ff_members WHERE ffm_up_id = {$this->up->id} AND ffm_status = 1");
		$pos = mysql_result($result, 0);
		if ($pos)
		{
			if (isset(self::$vekt_familie[$pos]))
			{
				$vekt += self::$vekt_familie[$pos];
			}
			else
			{
				// finn nærmeste verdi
				if ($pos < 1)
				{
					$vekt += reset(self::$vekt_familie);
				}
				else
				{
					$vekt += end(self::$vekt_familie);
				}
			}
		}
		
		// sjekk for rankforskjeller
		if ($up)
		{
			// ren rankforskjell
			$diff = abs(game::calc_rank_diff($up, $this->up));
			
			// har vi den bestemt?
			if (isset(self::$vekt_rankdiff[$diff]))
			{
				$vekt += self::$vekt_rankdiff[$diff];
			}
			
			elseif ($diff != 0)
			{
				// hent maksimalt (må være over 0, som tilsier at vi kan velge siste alternativ)
				$vekt += end(self::$vekt_rankdiff);
			}
			
			// spesialrank
			$diff = game::calc_specrank_diff($up, $this->up);
			if ($diff > 0)
			{
				if (isset(self::$vekt_specrankdiff[$diff]))
				{
					$vekt += self::$vekt_specrankdiff[$diff];
				}
				else
				{
					// bruk siste mulighet
					$vekt += end(self::$vekt_specrankdiff);
				}
			}
		}
		
		// maksimalt kan den være 0,5
		return min($vekt, 0.5);
	}
	
	/**
	 * Sørg for korrekt svekkelse av beskyttelse og evt. utbytting
	 * @return boolean false hvis selve beskyttelsen ble byttet, evt.
	 * @return float verdien beskyttelsen sank
	 */
	public function weakened($skadeprosent)
	{
		// har ingen beskyttelse?
		if (!$this->data) return 0;
		
		$r = rand(-200,200)/1000;
		$v = $this->up->data['up_protection_state'] * (1 - ($skadeprosent + $skadeprosent * $r) / 2);
		$v = round($v, 5);
		
		// under 20 %?
		if ($v < 0.2)
		{
			// har vi noe beskyttelse å bytte til?
			if ($this->id > 1)
			{
				// bytt beskyttelse
				ess::$b->db->query("UPDATE users_players SET up_protection_id = ".(--$this->id).", up_protection_state = 0.75 WHERE up_id = {$this->up->id}");
				$this->up->data['up_protection_state'] = 0.75;
				unset($this->data);
				$this->data = &self::$protections[$this->id];
				$this->state = 0.75;
				
				// gi hendelse
				$this->up->add_log("beskyttelse_lost", urlencode(self::$protections[$this->id+1]['name']).":".urlencode($this->data['name']).":".$this->state, 0);
				
				return false;
			}
		}
		
		// endring i beskyttelse
		$endring = $this->up->data['up_protection_state'] - $v;
		
		// sett ny beskyttelsesvedi
		$this->up->data['up_protection_state'] = $v;
		$this->state = $v;
		ess::$b->db->query("UPDATE users_players SET up_protection_state = $v WHERE up_id = {$this->up->id}");
		
		return $endring;
	}
}

/**
 * Våpen
 */
class weapon
{
	/**
	 * Utgangspunkt for maksimal helse man kan skade en spiller
	 */
	const MAX_ATTACK_HEALTH = 10000;
	
	/**
	 * Hvor mye våpentrening man får når man får nedgradert våpen
	 */
	const DOWNGRADE_TRAINING = 0.5;
	
	/**
	 * De ulike våpnene
	 */
	public static $weapons = array(
		1 => array(
			"name" => "Glock",
			"price" => 500000,
			"rank" => 8,
			"bullets" => 9,
			"bullet_strength" => 3,
			"bullet_price" => 200000
		),
		2 => array(
			"name" => "MP5",
			"price" => 1000000,
			"rank" => 9,
			"bullets" => 15,
			"bullet_strength" => 5,
			"bullet_price" => 300000
		),
		3 => array(
			"name" => "HK 416",
			"price" => 5000000,
			"rank" => 10,
			"bullets" => 20,
			"bullet_strength" => 7,
			"bullet_price" => 500000
		),
		4 => array(
			"name" => "AK 47",
			"price" => 20000000,
			"rank" => 11,
			"bullets" => 20,
			"bullet_strength" => 10,
			"bullet_price" => 800000
		),
		5 => array(
			"name" => "AG3",
			"price" => 50000000,
			"rank" => 12,
			"bullets" => 20,
			"bullet_strength" => 12,
			"bullet_price" => 1200000
		)
	);
	
	/**
	 * Rankpoeng ved vellykket drap
	 */
	public static $rankpoeng_success = array(
		1 => 0,
		234,
		334,
		500,
		1000,
		1667,
		2667,
		4000,
		5334,
		6667,
		8000,
		10000,
		12000
	);
	
	/**
	 * Rankpoeng ved vellykket drap for spesialranker
	 */
	public static $rankpoeng_success_special = array(
		1 => 22500,
		26000,
		30000
	);
	
	/**
	 * Rankpoeng ved mislykket drapsforsøk (blir overført fra offer til angriper)
	 */
	public static $rankpoeng_try = array(
		1 => 0,
		0,
		150,
		220,
		250,
		300,
		383,
		573,
		800,
		2000,
		4000,
		6000,
		9000
	);
	
	/**
	 * Rankpoeng ved mislykket drapsforsøk for spesialrank
	 */
	public static $rankpoeng_try_special = array(
		1 => 3500,
		5500,
		7000,
	);
	
	/**
	 * Faktor i forhold til rankpoeng ved drapsforsøk
	 */
	public static $rankpoeng_ratio = array(
		-5 => 0.5,
		-4 => 0.6,
		-3 => 0.7,
		-2 => 0.8,
		-1 => 0.9,
		0 => 1,
		1.25,
		1.5,
		2,
		2.5,
		3
	);
	
	const RANKPOENG_RATIO_LOW = -5;
	const RANKPOENG_RATIO_HIGH = 5;
	
	/**
	 * Hent våpenobjekt
	 */
	public static function get($id, player $up)
	{
		if (!isset(self::$weapons[$id])) return false;
		return new self($id, $up);
	}
	
	public $id;
	public $data;
	
	/**
	 * @var player
	 */
	public $up;
	
	/**
	 * Opprett objekt
	 * @param int $id
	 * @param player $up
	 */
	public function __construct($id, player $up)
	{
		if (!isset(self::$weapons[$id])) return false;
		
		$this->id = $id;
		$this->data = &self::$weapons[$id];
		$this->up = $up;
	}
	
	/**
	 * Kalkuler angrepstyrke
	 */
	public function get_strength($bullets)
	{
		$f = 0.9;
		$s = ($this->data['bullet_strength'] * (pow($f, $bullets) - 1) / ($f - 1)) / 100; // sum av geometrisk rekke
		return $s * $this->up->data['up_weapon_training'];
	}
	
	/**
	 * Kalkuler skade
	 * @param player $up spiller man angriper
	 * @param int $bullets
	 */
	public function calc_damage(player $up, $bullets)
	{
		// beregn beskyttelsestyrke
		$p = $up->protection->calc_protection($this->up);
		
		// beregn angrepstyrke
		$s = $this->get_strength($bullets);
		
		// beregn skadeprosent
		return array(
			$s,
			$p,
			$s / ($s + $p)
		);
	}
	
	/**
	 * Angrep en annen spiller
	 * @param player $up spilleren man angriper
	 * @param int $bullets antall kuler man bruker
	 */
	public function attack(player $up, $bullets)
	{
		$transaction_before = ess::$b->db->transaction;
		ess::$b->db->begin();
		
		// beregn skadeprosent
		$skade = $this->calc_damage($up, $bullets);
		$skadeprosent = $skade[2];
		
		// tilfeldig skadeprosent
		$skadeprosent *= rand(7500,10000)/10000;
		
		// kalkuler hvor mye helse spilleren skal miste
		$miste_helse = $skadeprosent * self::MAX_ATTACK_HEALTH;
		
		// sett ned helsen til spilleren og behandle spilleren
		$ret = $up->health_decrease($miste_helse, $this->up, player::ATTACK_TYPE_KILL, $skadeprosent, array(
			"bullets" => $bullets,
			"attack_skade" => $skade,
			"skadeprosent" => $skadeprosent));
		if (!$ret)
		{
			if (!$transaction_before) ess::$b->db->commit();
			return false;
		}
		
		// ble drept?
		if ($ret['drept'])
		{
			// øk wanted nivået
			$ret['fengsel'] = $this->up->fengsel_rank($ret['rankpoeng'], true);
		}
		
		// eller skadet?
		else
		{
			// sett angriper i fengsel
			$tid = rand(300, 600);
			$ret['fengsel'] = $this->up->fengsel_rank($ret['rankpoeng'], false, true, $tid);
			
			putlog("DF", " - Fengsel i ".game::timespan($tid, game::TIME_NOBOLD | game::TIME_ALL | game::TIME_FULL));
		}
		
		// sett ny energi for angriper
		$m = $ret['drept'] ? 0.6 : 0.8; // faktoren med hvor mye energi man mister
		$m *= $skadeprosent;
		$m = 1 - $m;
		ess::$b->db->query("UPDATE users_players SET up_energy = GREATEST(0, up_energy * $m) WHERE up_id = {$this->up->id}");
		$this->up->data['up_energy'] = max(0, $this->up->data['up_energy'] * $m);
		
		// gjennomfør transaksjon
		if (!$transaction_before) ess::$b->db->commit();
		
		return $ret;
	}
}