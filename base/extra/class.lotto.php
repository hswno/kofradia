<?php

/**
 * Lotto!
 */
class lotto
{
	public static $lodd_maks = 140;
	public static $ventetid = 120;
	public static $lodd_maks_om_gangen = 10;
	public static $premier = array(
		array(600, 0.35),
		array(380, 0.23),
		array(230, 0.12),
		array(160, 0.1),
		array(90, 0.08)
	);
	
	const PRICE = 5000;
	const PRICE_CHANGE = 1277954155;
	const PRICE_CHANGE_OLD = 1000;
	
	/**
	 * Antall spillere hvor grensen mellom nedsettelse av bevinst går
	 */
	const PLAYERS_TOP = 5;
	
	/**
	 * Finn prisen på lotto-lodd
	 */
	public static function get_lodd_price()
	{
		// gammel pris?
		if (time() < self::PRICE_CHANGE) return self::PRICE_CHANGE_OLD;
		
		return self::PRICE;
	}
	
	/**
	 * Kjør konkurranse
	 */
	public static function run_comp()
	{
		$l = new lotto_konk();
		$l->run();
	}
}

class lotto_konk
{
	protected $antall;
	protected $brukere;
	protected $pott;
	protected $vinnere;
	
	/** Oversikt over hva som gis ut av rank og penger */
	protected $premier;
	
	/**
	 * Kjør konkurransen og trekk vinnere
	 */
	public function run()
	{
		// hent informasjon om runden
		$result = ess::$b->db->query("
			SELECT COUNT(id), COUNT(DISTINCT IF(up_id IS NOT NULL AND up_access_level != 0, l_up_id, NULL))
			FROM lotto LEFT JOIN users_players ON l_up_id = up_id");
		$this->antall = mysql_result($result, 0);
		$this->brukere = mysql_result($result, 0, 1);
		
		// ingen deltakere?
		if ($this->antall == 0)
		{
			putlog("INFO", "%bLOTTO%b: Ingen spillere deltok i denne lottorunden!");
			return;
		}
		
		$this->premier = lotto::$premier;
		
		// korriger ranken i forhold til antall spillere som har deltatt
		if ($this->brukere < lotto::PLAYERS_TOP)
		{
			$f = min(1, $this->brukere / lotto::PLAYERS_TOP);
			for ($i = 0; $i < 5; $i++)
			{
				$this->premier[$i][0] = round($this->premier[$i][0] * $f);
			}
		}
		
		// sett opp fordeling av gevinsten
		if ($this->brukere < 5)
		{
			// beregn hvor mye som blir fordelt i utgangspunktet
			$tot = 0;
			for ($i = 0; $i < $this->brukere; $i++)
			{
				$tot += $this->premier[$i][1];
			}
			
			// beregn maks som kan fordeles
			$max = $tot;
			for (; $i < 5; $i++)
			{
				$max += $this->premier[$i][1];
			}
			
			// korriger utgangspunktet
			for ($i = 0; $i < $this->brukere; $i++)
			{
				$this->premier[$i][1] = $this->premier[$i][1] * $max / $tot;
			}
		}
		
		// beregn hvor mye man tjener
		$lodd_pris = lotto::get_lodd_price();
		$result = ess::$b->db->query("
			SELECT
				$this->antall * $lodd_pris,
				$this->antall * $lodd_pris * {$this->premier[0][1]},
				$this->antall * $lodd_pris * {$this->premier[1][1]},
				$this->antall * $lodd_pris * {$this->premier[2][1]},
				$this->antall * $lodd_pris * {$this->premier[3][1]},
				$this->antall * $lodd_pris * {$this->premier[4][1]}");
		
		$this->pott = mysql_result($result, 0, 0);
		
		for ($i = 1; $i <= 5; $i++)
		{
			$this->premier[$i-1][2] = round(mysql_result($result, 0, $i));
		}
		$this->vinnere = array();
		$this->vinnere_text = array();
		
		putlog("INFO", "%bLOTTO%b: ".game::format_number($this->brukere)." spiller".($this->brukere == 1 ? '' : 'e')." deltok i denne lottorunden med totalt ".game::format_number($this->antall)." lodd og en pott på ".game::format_cash($this->pott)."!");
		
		$to = min(5, $this->brukere);
		for ($i = 1; $i <= $to; $i++)
		{
			$this->give_prize($i);
		}
		
		if (count($this->vinnere) == 0)
		{
			putlog("INFO", "%bLOTTO%b: Ingen spillere deltok i denne lottorunden!");
		}
		
		else
		{
			// melding
			$ekstra = "";
			$ekstra_l = "";
			if (count($this->vinnere_text) > 1)
			{
				$e = array();
				$el = array();
				foreach ($this->vinnere_text as $n => $u)
				{
					if ($n == 0) continue;
					$e[] = "%u$u%u";
					$el[] = '<user id="'.$this->vinnere[$n].'" />';
				}
				$ekstra = " 2".(count($this->vinnere_text) > 2 ? '-'.count($this->vinnere_text) : '')." plass ble ".implode(", ", $e);
				$ekstra_l = " 2".(count($this->vinnere) > 2 ? '-'.count($this->vinnere) : '')." plass ble ".sentences_list($el).".";
			}
			putlog("INFO", "%bLOTTO%b: %u{$this->vinnere_text[0]}%u vant runden!$ekstra");
			
			// live-feed
			//livefeed::add_row('<user id="'.$this->vinnere[0].'" /> vant lottorunden.'.$ekstra_l);
		}
		
		// fjern loddene som var kjøpt
		ess::$b->db->query("DELETE FROM lotto");
	}
	
	/**
	 * Behandle vinner
	 */
	protected function give_prize($nummer)
	{
		$id = $nummer - 1;
		
		// finn ut antall lodd vi kan trekke ifra
		$result = ess::$b->db->query("
			SELECT COUNT(id)
			FROM lotto JOIN users_players ON up_id = l_up_id AND up_access_level != 0".(count($this->vinnere) > 0 ? "
			WHERE l_up_id NOT IN (".implode(",", $this->vinnere).")" : ""));
		
		$num = mysql_result($result, 0);
		if ($num == 0) return;
		
		// hent en tilfeldig vinner
		$rand = rand(0, $num - 1);
		$result = ess::$b->db->query("
			SELECT id, l_up_id, time, up_name
			FROM lotto JOIN users_players ON up_id = l_up_id AND up_access_level != 0".(count($this->vinnere) > 0 ? "
			WHERE l_up_id NOT IN (".implode(",", $this->vinnere).")" : "")."
			LIMIT $rand, 1");
		$vinner = mysql_fetch_assoc($result);
		if (!$vinner) return;
		
		$up = player::get($vinner['l_up_id']);
		if (!$up) return;
		
		// antall lodd vi hadde
		$result = ess::$b->db->query("SELECT COUNT(*) FROM lotto WHERE l_up_id = {$up->id}");
		$count = mysql_result($result, 0);
		
		$up->add_log("lotto", $nummer.":".$this->premier[$id][0], $this->premier[$id][2]);
		
		// sett opp som vinner
		ess::$b->db->query("INSERT INTO lotto_vinnere SET l_id = {$vinner['id']}, lv_up_id = {$vinner['l_up_id']}, time = {$vinner['time']}, won = {$this->premier[$id][2]}, total_lodd = $this->antall, total_users = $this->brukere, type = $nummer");
		
		// send brukeren penger
		$up->update_money($this->premier[$id][2], false);
		
		// øk rank
		$up->increase_rank($this->premier[$id][0], false, null, null, "lotto");
		
		// trigger
		$up->trigger("lotto", array(
				"number" => $nummer,
				"cash" => $this->premier[$id][2],
				"points" => $this->premier[$id][0],
				"lodd" => $count,
				"lodd_total" => $this->antall,
				"players" => $this->brukere));
		
		$this->vinnere[] = $vinner['l_up_id'];
		$this->vinnere_text[] = $vinner['up_name'];
	}
}