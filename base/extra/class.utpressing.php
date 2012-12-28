<?php

/**
 * Utpressingsobjekt
 */
class utpressing extends pages_player
{
	/**
	 * Energi for å utføre utpressing
	 */
	const ENERGY = 100;
	
	/**
	 * Maksimalt pengebeløp man kan utpresse
	 */
	const CASH_MAX = 45000;
	
	const ANTIBOT_SPAN = 10;
	
	/**
	 * Ventetid
	 */
	const DELAY_TIME = 240;
	
	/** Tabell over sannsynligheter og pengeskala */
	public static $tabell = array(
		1 => array(
			"text" => "Mye penger",
			"prob" => 60,
			"points" => 9,
			"cash_min" => 0.6,
			"cash_max" => 0.9
		),
		array(
			"text" => "Mye poeng",
			"prob" => 60,
			"points" => 15,
			"cash_min" => 0.2,
			"cash_max" => 0.5
		)
	);
	
	public static $affect = array(
		-2 => array(
			"health" => 50,
			"energy" => 100
		),
		-1 => array(
			"health" => 100,
			"energy" => 200
		),
		0 => array(
			"health" => 200,
			"energy" => 200
		),
		1 => array(
			"health" => 350,
			"energy" => 350
		),
		2 => array(
			"health" => 500,
			"energy" => 500
		)
	);
	
	public function __construct(player $up)
	{
		parent::__construct($up);
	}
	
	public function get_affected_table($rank_to)
	{
		$rank_from = $this->up->rank;
		
		$offset = 0;
		if ($rank_from['number'] < 3)
			$offet = 3 - $rank_from['number'];
		else
		{
			$max = count(game::$ranks['items']);
			if ($rank_from['number'] > $max - 2)
				$offset = $rank_from['number'] - $max;
		}
		
		$offset += $rank_to['number'] - $rank_from['number'];
		$offset = max(-2, min(2, $offset));
		
		return self::$affect[$offset];
	}
	
	/**
	 * Kalkuler ventetid
	 */
	protected function calc_wait()
	{
		if (access::has("admin")) return 0;
		
		$wait = max(0, $this->up->data['up_utpressing_last'] + self::DELAY_TIME - time());
		
		return $wait;
	}
	
	/**
	 * Utfør utpressing
	 */
	public function utpress($opt_key)
	{
		$ret = array("success" => false, "wanted" => 0);
		
		// valider alternativ
		if (!isset(self::$tabell[$opt_key]))
		{
			throw new HSException("Ugyldig alternativ.");
		}
		
		// hent ut alternativet
		$opt = self::$tabell[$opt_key];
		
		// bruk energi
		$this->up->energy_use(self::ENERGY);
		
		// test for sannsynlighet
		if (rand(0, 100) <= $opt['prob'])
		{
			// vellykket
			$this->utpress_success($opt, $ret);
		}
		
		else
		{
			// mislykket
			$this->utpress_failed($opt, $ret);
		}
		
		return $ret;
	}
	
	/**
	 * Behandle vellykket utpressing
	 */
	private function utpress_success($opt, &$ret)
	{
		// beregn maksimal pengeverdi offeret kan miste
		$cash_max = $this->up->rank['number'] / count(game::$ranks['items']) * self::CASH_MAX / 2 + self::CASH_MAX / 2;
		
		// beregn hvor mye penger vi får
		$cash = round(rand($cash_max * $opt['cash_min'], $cash_max * $opt['cash_max']));
		
		// kriterier for å finne en spiller
		$where = $this->get_criterias($cash, true);
		
		// finn en tilfeldig spiller som passer med kriteriene
		ess::$b->db->begin();
		
		// hent alle spillerene
		$result = ess::$b->db->query("
			SELECT up_id, up_cash, up_energy, up_points
			FROM users_players
			WHERE $where
			ORDER BY up_last_online DESC
			LIMIT 100");
		$players = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$players[] = $row;
		}
		
		while (count($players) > 0)
		{
			// plukk ut en tilfeldig spiller
			$key = array_rand($players);
			$row = $players[$key];
			
			$rank = game::rank_info($row['up_points']);
			$affect = $this->get_affected_table($rank);
			
			// kontroller energi
			if ($row['up_energy'] < $affect['energy']*2)
			{
				// for lite energi, velg en ny spiller
				unset($players[$key]);
				continue;
			}
			
			// trekk fra penger
			ess::$b->db->query("
				UPDATE users_players
				SET up_bank = IF(up_cash < $cash, up_bank - $cash, up_bank),
					up_cash = IF(up_cash >= $cash, up_cash - $cash, up_cash)
				WHERE up_id = {$row['up_id']} AND (up_cash >= $cash OR up_bank >= $cash)");
			
			// ble ikke endret?
			if (ess::$b->db->affected_rows() == 0)
			{
				unset($players[$key]);
				continue;
			}
			
			// vellykket
			$up = player::get($row['up_id']);
			$ret['player'] = $up;
			$ret['player_from_bank'] = $row['up_cash'] < $cash;
			
			// legg til en melding i innboksen til offeret
			$up->add_log("utpressing", $this->up->id, $cash);
			
			// irc logg
			putlog("SPAMLOG", "%c11%bUTPRESSING:%b%c %u{$this->up->data['up_name']}%u presset %u{$up->data['up_name']}%u for %u".game::format_cash($cash)."%u".($ret['player_from_bank'] ? ' (fra bankkonto)' : ''));

			// lagre utpressing
			ess::$b->db->query("
				INSERT INTO utpressinger
				SET
					ut_action_up_id = {$this->up->id},
					ut_affected_up_id = {$up->id},
					ut_b_id = {$this->up->data['up_b_id']},
					ut_time = ".time());
			
			// trekk fra energi og helse fra offeret
			$up->energy_use($affect['energy']);
			if (!$ret['player_from_bank'])
			{
				$ret['attack'] = $up->health_decrease($affect['health'], $this->up, player::ATTACK_TYPE_UTPRESSING);
			}
			
			break;
		}
		
		$opt_points = $this->get_points($opt);
		$points = isset($ret['attack']) && $ret['attack']['drept'] ? $ret['attack']['rankpoeng'] : $opt_points;
		if (!isset($ret['attack']) || !$ret['attack']['drept']) $this->up->increase_rank($opt_points);
		
		$ret['points'] = $points;
		
		$ret['cash'] = round($cash * 0.8);
		if (!isset($ret['player'])) $ret['cash'] = round($ret['cash'] / 2);
		$ret['success'] = true;
		
		// gi penger til brukeren
		ess::$b->db->query("UPDATE users_players SET up_utpressing_last = ".time().", up_cash = up_cash + {$ret['cash']} WHERE up_id = {$this->up->id}");
		$this->up->data['up_utpressing_last'] = time();
		
		// trigger
		$this->up->update_money($ret['cash'], true, false);
		
		// fengsel opplegg
		$ret['wanted'] = $this->up->fengsel_rank($points, true);
		
		// triggere
		$this->up->trigger("utpressing", array(
			"option" => $opt,
			"up" => !empty($ret['player']) ? $ret['player'] : false,
			"points" => $ret['points'],
			"cash" => $ret['cash'],
			"cash_lost" => $cash,
			"from_bank" => !empty($ret['player']) && $ret['player_from_bank'],
			"attack" => !empty($ret['attack']) ? $ret['attack'] : false));
		
		if (!empty($ret['player']) && $ret['player']->active)
		{
			$ret['player']->trigger("utpresset", array(
				"option" => $opt,
				"up" => $this->up,
				"points" => $ret['points'],
				"cash" => $ret['cash'],
				"cash_lost" => $cash,
				"from_bank" => $ret['player_from_bank'],
				"attack" => !empty($ret['attack']) ? $ret['attack'] : false));
		}
		
		ess::$b->db->commit();
	}
	
	/**
	 * Mislykket utpressing
	 */
	private function utpress_failed($opt, &$ret)
	{
		// mislykket
		$ret['wanted'] = $this->up->fengsel_rank($this->get_points($opt));
		
		ess::$b->db->query("UPDATE users_players SET up_utpressing_last = ".time()." WHERE up_id = {$this->up->id}");
		$this->up->data['up_utpressing_last'] = time();
		
		// mislykker helt?
		if (rand(1, 100) <= 30)
		{
			return $ret;
		}
		
		// beregn maksimal pengeverdi offeret ville mistet
		$cash_max = $this->up->rank['number'] / count(game::$ranks['items']) * self::CASH_MAX / 2 + self::CASH_MAX / 2;
		
		// beregn hvor mye penger vi får
		$cash = round(rand($cash_max * $opt['cash_min'], $cash_max * $opt['cash_max']));
		
		// kriterier for å finne en spiller
		$where = $this->get_criterias($cash);
		
		// finn en tilfeldig spiller som passer med kriteriene
		$result = ess::$b->db->query("
			SELECT up_id
			FROM (
				SELECT up_id
				FROM users_players
				WHERE $where
				ORDER BY up_last_online DESC
				LIMIT 100
			) ref
			ORDER BY RAND()
			LIMIT 1");
		
		// fant ingen spillere?
		$row = mysql_fetch_assoc($result);
		if (!$row)
		{
			return $ret;
		}
		
		$ret['player'] = player::get($row['up_id']);
		
		return $ret;
	}
	
	protected function get_ff_up_ids()
	{
		// hent alle medlemmer av familie/firma vi med i
		$result = ess::$b->db->query("
			SELECT DISTINCT f2.ffm_up_id
			FROM ff_members f1
				JOIN ff ON ff_id = f1.ffm_ff_id AND ff_is_crew = 0
				JOIN ff_members f2 ON f1.ffm_ff_id = f2.ffm_ff_id AND f2.ffm_status = 1 AND f2.ffm_up_id != f1.ffm_up_id
			WHERE f1.ffm_up_id = {$this->up->id} AND f1.ffm_status = 1");
		$up_ids = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$up_ids[] = $row['ffm_up_id'];
		}
		
		return $up_ids;
	}
	
	/**
	 * Setter opp kriterier for å finne en spiller
	 * @param int $cash
	 */
	private function get_criterias($cash, $success = null)
	{
		// hent alle medlemmer av familie/firma som ikke skal utpresses
		$up_ids = $this->get_ff_up_ids();
		$up_ids[] = $this->up->id;
		
		// når siste må ha vært pålogget
		$expire = time() - 604800 * ($cash ? 2 : 3);
		
		// rankbegrensning
		$ranks = $this->get_rank_limits();
		
		// sett opp kriterier for å finne spiller
		$where = "up_access_level != 0";
		$where .= " AND up_last_online >= $expire";
		$where .= " AND up_b_id = {$this->up->data['up_b_id']}";
		$where .= " AND up_fengsel_time < ".time();
		$where .= " AND up_brom_expire < ".time();
		$where .= " AND up_points >= ".$ranks[0]['points'];
		if ($ranks[1]) $where .= " AND up_points < {$ranks[1]['points']}";
		
		if ($success)
		{
			$where .= " AND (up_cash >= $cash OR up_bank >= $cash)";
		}
		else
		{
			$where .= " AND up_cash < $cash";
		}
		
		$where .= " AND up_id NOT IN (".implode(",", $up_ids).")";
		
		if (MAIN_SERVER)
		{
			$where .= access::is_nostat()
				? " AND up_access_level >= ".ess::$g['access_noplay']
				: " AND up_access_level < ".ess::$g['access_noplay'];
		}
		
		return $where;
	}
	
	private function get_rank_limits()
	{
		// velger -/+ 2 ranker
		$num_min = max(1, $this->up->rank['number'] - 2);
		$num_max = $num_min + 4;
		
		$c = count(game::$ranks['items']);
		if ($num_max > $c)
		{
			$num_min -= $num_max - $c;
			$num_max = $c;
		}
		
		return array(
			game::$ranks['items_number'][$num_min],
			$num_max == $c ? null : game::$ranks['items_number'][$num_max+1]
		);
	}
	
	protected function get_points($opt)
	{
		return rand($opt['points'] - 1, $opt['points'] + 1);
	}
}
