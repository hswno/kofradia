<?php

/**
 * Kriminalitet-systemet
 */
class kriminalitet
{
	/** Energi ved kriminalitet */
	const ENERGY_KRIM = 100;
	
	/**
	 * Aktiv spiller
	 * @var player
	 */
	public $up;
	
	public $wait;
	public $last;
	public $options;
	
	protected $prob_k_min;
	protected $prob_k_max;
	protected $prob_ks_max;
	
	protected $prob_min = 0.1; // fra 10 % sannsynlighet (kan gå lavere med lav energi)
	protected $prob_max = 0.95; // til 95 % sannsynlighet
	
	/**
	 * Construct
	 * @param player $up
	 */
	public function __construct(player $up)
	{
		$this->up = $up;
	}
	
	/**
	 * Finn informasjon om forrige kriminalitet og ventetid
	 */
	public function get_last_info()
	{
		// finn ut når vi sist utførte kriminalitet og ventetiden
		$this->last = false;
		$this->wait = false;
		$result = ess::$b->db->query("
			SELECT k.id, k.wait_time, k.name, s.last, k.b_id, ks_strength
			FROM kriminalitet_status AS s, kriminalitet AS k
			WHERE s.krimid = k.id AND s.ks_up_id = ".login::$user->player->id."
			ORDER BY s.last DESC
			LIMIT 1");
		
		if (mysql_num_rows($result) != 0)
		{
			$this->last = mysql_fetch_assoc($result);
		
			// er det noe ventetid?
			$now = time();
			$end = $this->last['last'] + $this->last['wait_time'];
			if ($end > $now)
			{
				$this->wait = $end - $now;
				if ($this->wait > $this->last['wait_time']) $this->wait = false;
			}
		}
	}
	
	/**
	 * Hent alternativene vi har
	 */
	public function options_load()
	{
		// allerede lastet inn?
		if ($this->options !== null) return;
		
		// hent probs
		$this->get_prob_stats();
		
		// hent kriminalitet i denne bydelen
		$this->options = array();
		$result = ess::$b->db->query("
			SELECT k.id, k.wait_time, k.name, k.points, k.img, k.max_strength, k.cash_min, k.cash_max, IFNULL(s.count,0) AS count, IFNULL(s.success,0) AS success, k_strength, ks_strength
			FROM kriminalitet AS k LEFT JOIN kriminalitet_status AS s ON k.id = s.krimid AND s.ks_up_id = {$this->up->id}
			WHERE k.b_id = {$this->up->data['up_b_id']}
			ORDER BY ks_strength DESC, k.points DESC");
		
		$probs = array();
		$options = array();
		while ($row = mysql_fetch_assoc($result))
		{
			// sett opp korrekt sannsynlighet
			$row['prob'] = $this->calc_prob($row);
			$probs[] = $row['prob'];
			
			
			$options[] = $row;
		}
		
		// sorter
		array_multisort($probs, SORT_DESC, SORT_NUMERIC, $options);
		
		// lagre
		foreach ($options as $row)
		{
			$this->options[$row['id']] = $row;
		}
	}
	
	/**
	 * Kalkuler sannsynlighet for alternativ
	 */
	protected function calc_prob($row)
	{
		// egen sannsynlighet
		$prob = $this->prob_ks_max == 0
			? 0
			: $row['ks_strength'] / $this->prob_ks_max;
		
		// juster for denne krimen i forhold til de andre
		// den mest populære krimen settes ned 20 %
		$d = $this->prob_k_max == $this->prob_k_min ? 1 : (($row['k_strength'] - $this->prob_k_min) / ($this->prob_k_max - $this->prob_k_min));
		$d *= 0.2; // settes ned maksimalt 20 %
		$prob -= $prob * $d;
		
		// juster mellom min og maks
		$prob = ($prob * ($this->prob_max - $this->prob_min)) + $this->prob_min;
		
		// juster for energi (ikke bruk energi over 100 %)
		$prob *= min(1, $this->up->get_energy_percent() / 100);
		
		return $prob;
	}
	
	/**
	 * Utfør kriminalitet
	 */
	public function utfor($id)
	{
		$this->options_load();
		if (!isset($this->options[$id]))
		{
			throw new HSException("Fant ikke ønsket alternativ.");
		}
		
		$krim = $this->options[$id];
		
		$ret = array(
			"success" => false
		);
		
		// treffer vi med sannsynligheten?
		if (rand(0, 100) <= $krim['prob']*100)
		{
			// vellykket
			$ret['success'] = true;
			
			// gi penger til spilleren
			$cash = rand($krim['cash_min'], $krim['cash_max']);
			ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + $cash WHERE up_id = {$this->up->id}");
			$ret['cash'] = $cash;
			
			// trigger
			$this->up->update_money($cash, true, false);
			
			// gi rank til spilleren
			$ret['rank_change'] = $this->up->increase_rank($krim['points']);
			$ret['rank'] = $krim['points'];
		}
		
		// wanted nivå
		$ret['wanted_change'] = $this->up->fengsel_rank($krim['points'], $ret['success']);
		
		// oppdater kriminalitet-status
		$s = $ret['success'] ? 2 : 1;
		ess::$b->db->query("
			INSERT INTO kriminalitet_status
			SET krimid = $id, ks_up_id = {$this->up->id}, count = 1, success = ".($ret['success'] ? 1 : 0).", last = ".time().", ks_strength = ks_strength + $s
			ON DUPLICATE KEY
			UPDATE count = count + 1, success = success + VALUES(success), last = VALUES(last), ks_strength = VALUES(ks_strength)");
		
		// oppdater kriminalitet
		ess::$b->db->query("
			UPDATE kriminalitet
			SET k_strength = k_strength + $s
			WHERE id = $id");
		
		// trigger
		$this->up->trigger("kriminalitet",
			array(
				"option" => $krim,
				"success" => $ret['success']));
		
		// sett ned energien til spilleren
		$this->up->energy_use(self::ENERGY_KRIM);
		
		return $ret;
	}
	
	/**
	 * Hent ut en tilfeldig melding for en kriminalitet
	 */
	public function get_random_message($id, $success, $cash = null, $points = null)
	{
		$id = (int) $id;
		$rank = $success ? game::format_rank($points, $this->up->rank) : null;
		
		// hent en tilfeldig melding
		$result = ess::$b->db->query("
			SELECT text
			FROM kriminalitet_text
			WHERE krimid = $id AND outcome = ".($success ? 1 : 2)."
			ORDER BY RAND()
			LIMIT 1");
		
		// har melding?
		$row = mysql_fetch_assoc($result);
		if ($row)
		{
			if ($success)
			{
				return str_replace(
					array("%cash", "%points", "%rank"),
					array(game::format_cash($cash), $points, $rank),
					$row['text']);
			}
			
			return $row['text'];
		}
		
		// fant ingen melding
		else
		{
			if ($success)
			{
				return "Vellykket! Du skaffet deg $points poeng og ".game::format_cash($cash).".";
			}
			
			return "Mislykket! Du klarte det ikke...";
		}
	}
	
	/**
	 * Hent sannsynligheter
	 */
	protected function get_prob_stats()
	{
		// hent minimal og maksimal strength for krims
		$result = ess::$b->db->query("
			SELECT MIN(k_strength) min_k_strength, MAX(k_strength) max_k_strength
			FROM kriminalitet
				JOIN bydeler ON kriminalitet.b_id = bydeler.id AND bydeler.active != 0");
		$this->prob_k_min = mysql_result($result, 0, 0);
		$this->prob_k_max = max(1, mysql_result($result, 0, 1));
		
		// hent stats for å sammenlikne med andre spillere
		$result = ess::$b->db->query("
			SELECT MAX(ks_strength)
			FROM kriminalitet_status");
		$this->prob_ks_max = mysql_result($result, 0);
	}
}