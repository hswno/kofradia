<?php

/**
 * Prestasjoner
 */
class achievements
{
	/**
	 * Oppnåelser
	 */
	public static $achievements;
	
	/**
	 * Oppnåelser indeksert etter code
	 */
	public static $achievements_code;
	
	/**
	 * Sjekk cache
	 */
	public static function load_cache($reload = null)
	{
		if (self::$achievements && !$reload) return;
		
		// har vi i cache?
		if (!$reload && ($data = cache::fetch("achievements_cache")))
		{
			self::$achievements = $data['id'];
			self::$achievements_code = $data['code'];
			return;
		}
		
		// hent frisk data
		$result = \Kofradia\DB::get()->query("
			SELECT ac_id, ac_code, ac_name, ac_text, ac_recurring, ac_apoints, ac_prize, ac_count, ac_params
			FROM achievements
			WHERE ac_active = 1
			ORDER BY ac_name");
		
		$data = array();
		$data_code = array();
		while ($row = $result->fetch())
		{
			$data[$row['ac_id']] = new achievements_item($row);
			$data_code[$row['ac_code']][$row['ac_id']] = $data[$row['ac_id']];
		}
		
		self::$achievements = $data;
		self::$achievements_code = $data_code;
		
		// lagre til cache
		cache::store("achievements_cache", array("id" => $data, "code" => $data_code), 86400);
	}
}

class achievements_item
{
	/**
	 * ID
	 */
	public $id;
	
	/**
	 * Data
	 */
	public $data;
	
	/**
	 * Params
	 * @var params
	 */
	public $params;
	
	/**
	 * Constructor
	 * @param string $data fra databasen
	 */
	public function __construct($data)
	{
		$this->id = $data['ac_id'];
		$this->data = $data;
		$this->params = new params($data['ac_params']);
	}
	
	/**
	 * Hent liste over premier for informasjonsside
	 */
	public function get_prizes()
	{
		// mulige premier:
		// * cash
		// * points
		// * bullets
		// (se også achivement_player_item::prize())
		
		$params = new params($this->data['ac_prize']);
		$text = array();
		
		// cash
		if ($cash = $params->get("cash"))
		{
			$text[] = game::format_cash($cash);
		}
		
		// points
		if ($points = $params->get("points"))
		{
			$text[] = game::format_num($points)." rankpoeng";
		}
		
		// kuler
		if ($bullets = $params->get("bullets"))
		{
			$bullets = (int) $bullets;
			$text[] = fwords("%d kule", "%d kuler", $bullets);
		}
		
		return $text;
	}
}


class achievements_player
{
	/**
	 * Spilleren
	 * @var player
	 */
	protected $up;
	
	/**
	 * Cache
	 */
	public $cache;
	
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		$this->up = $up;
		$this->up->achievements = $this;
		self::load_cache();
	}
	
	/**
	 * Behandle trigger
	 */
	public function handle($trigger, $data)
	{
		switch ($trigger)
		{
			case "kriminalitet":
				if (!$data['success']) return;
				$this->handle_code("krim_rep", $data);
			break;
			
			case "utpressing":
				$this->handle_code("utpress_rep", $data);
			break;
			
			case "biltyveri":
				if (!$data['success']) return;
				$this->handle_code("gta_rep", $data);
			break;
			
			case "rank_points":
				// utfør kun på positive forandringer
				if ($data['points_rel'] < 0) return;
				
				$this->handle_code("rank", $data);
			break;
			
			case "fengsel":
				if (!$data['success']) return;
				$this->handle_code("fengsel_rep", $data);
			break;
			
			case "money_change":
				$this->handle_code("money", $data);
			break;
			
			case "attack":
				if (!$data['attack']['drept']) return;
				$this->handle_code("kill_rep", $data);
				$this->handle_code("kill", $data);
			break;
			
			case "attack_bleed":
				$data = array_merge($data, array("bleed" => true));
				$this->handle_code("kill_rep", $data);
				$this->handle_code("kill", $data);
			break;
			
			case "oppdrag":
				if (!$data['success']) return;
				$this->handle_code("oppdrag_rep", $data);
			break;
			
			case "lotto":
				$this->handle_code("lotto_rep", $data);
			break;
			
			case "ff_won_member":
				$this->handle_code("ff_won_member", $data);
				$this->handle_code("ff_pos", $data);
			break;
			
			case "ff_priority_change":
			case "ff_join":
				$this->handle_code("ff_pos", $data);
			break;
		}
	}
	
	/**
	 * Kall underfunksjon per prestasjon
	 */
	protected function handle_code($code, $data)
	{
		foreach ($this->get_items($code) as $item)
		{
			call_user_func(array($item, "handle_$code"), $data, $item);
		}
	}
	
	/**
	 * Hent cache over ikke-fullførte prestasjoner
	 */
	public function load_cache($reload = null)
	{
		if ($this->cache && !$reload) return;
		$cache_key = "achievements_up_".$this->up->id;
		
		// hent fra cache
		if (!$reload && ($data = cache::fetch($cache_key)))
		{
			$this->achievements = $data;
		}
		
		// hent frisk data
		$result = \Kofradia\DB::get()->query("
			SELECT upa_id, upa_ac_id, upa_time, upa_prize, upa_apoints, upa_params, upa_up_id, upa_complete
			FROM up_achievements
			WHERE upa_up_id = {$this->up->id} AND upa_complete = 0");
		
		$data = array();
		while ($row = $result->fetch())
		{
			$data[$row['upa_ac_id']] = $row;
		}
		
		$this->cache = $data;
		
		// lagre til cache
		cache::store($cache_key, $data, 300);
	}
	
	/**
	 * Hent prestasjoner
	 */
	public function get_items($code)
	{
		if (!isset(achievements::$achievements_code[$code])) return array();
		
		$items = array();
		foreach (achievements::$achievements_code[$code] as $row)
		{
			$items[] = new achievement_player_item($this->up, $row);
		}
		
		return $items;
	}
	
	/**
	 * Hent antall repetisjoner for alle prestasjoner
	 */
	public function get_rep_count()
	{
		$result = \Kofradia\DB::get()->query("
			SELECT upa_ac_id, COUNT(upa_id) count_upa_id, MAX(upa_time) max_upa_time
			FROM up_achievements
			WHERE upa_up_id = {$this->up->id} AND upa_complete != 0
			GROUP BY upa_ac_id");
		
		$data = array();
		while ($row = $result->fetch())
		{
			$data[$row['upa_ac_id']] = $row;
		}
		
		return $data;
	}
}

class achievement_player_item
{
	/**
	 * @var player
	 */
	public $up;
	
	/**
	 * Data
	 */
	public $data;
	
	/**
	 * Prestasjon
	 * @var achievements_item
	 */
	public $a;
	
	/**
	 * Params
	 * @var params_update
	 */
	public $params;
	
	/**
	 * Constructor
	 */
	public function __construct(player $up, achievements_item $a)
	{
		$this->up = $up;
		$this->a = $a;
	}
	
	/**
	 * Hent aktiv oppføring, hvis noen
	 */
	public function load_active($create = null)
	{
		// hent fra cache
		if (!isset($this->up->achievements->cache[$this->a->data['ac_id']]))
		{
			if ($create)
			{
				return $this->create();
			}
			
			return false;
		}
		
		$this->data = $this->up->achievements->cache[$this->a->data['ac_id']];
		$this->init_params();
		return true;
	}
	
	/**
	 * Last inn params
	 */
	protected function init_params()
	{
		$this->params = new params_update($this->data['upa_params'], "up_achievements", "upa_params", "upa_id = {$this->data['upa_id']}");
	}
	
	/**
	 * Opprett hvis ikke finnes
	 */
	protected function create()
	{
		if ($this->load_active()) return false;
		
		\Kofradia\DB::get()->exec("
			INSERT INTO up_achievements
			SET upa_ac_id = {$this->a->id}, upa_time = ".time().", upa_up_id = {$this->up->id}, upa_complete = 0");
		
		$this->up->achievements->load_cache(true);
		return $this->load_active();
	}
	
	/**
	 * Utfør x antall kriminalitet
	 */
	public function handle_krim_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Oppnå rangering
	 */
	public function handle_rank($data)
	{
		/*
		 * params:
		 *   rank
		 *   pos
		 *     min_rank
		 */
		
		// oppnådd rankpoeng?
		if ($rank = $this->a->params->get("rank"))
		{
			// har ikke ranken forandret seg?
			if ($data['rank'] <= 0) return; 
			
			if ($rank < 0) $rank = count(game::$ranks['items']) + $rank + 1;
			$rank = max(1, min(count(game::$ranks['items']), $rank));
			
			// har vi oppnådd denne ranken?
			if ($this->up->rank['number'] >= $rank)
			{
				// allerede utført?
				if ($this->check_complete()) return;
				
				// marker som utført
				$this->load_active(true);
				$this->mark_complete();
			}
		}
		
		// oppnådd plassering?
		elseif ($pos = $this->a->params->get("pos"))
		{
			// har vi ikke oppnådd denne plasseringen?
			if ($pos < $this->up->rank['pos']) return;
			
			// minstekrav?
			if ($min_rank = $this->a->params->get("min_rank"))
			{
				if ($min_rank < 0) $min_rank = count(game::$ranks['items']) + $min_rank + 1;
				$min_rank = max(1, min(count(game::$ranks['items']), $min_rank));
				
				// ikke oppnådd?
				if ($this->up->rank['number'] < $min_rank) return;
			}
			
			// allerede utført?
			if ($this->check_complete()) return;
			
			// marker som utført
			$this->load_active(true);
			$this->mark_complete();
		}
	}
	
	/**
	 * Oppnå pengeplassering
	 */
	public function handle_money($data)
	{
		/*
		 * params:
		 *   money
		 */
		
		// mangler målverdi?
		if (!$this->a->params->get("money")) return;
		
		// har vi oppnådd målet?
		$sum = bcadd($this->up->data['up_cash'], $this->up->data['up_bank']);
		if (bccomp($sum, $this->a->params->get("money")) >= 0)
		{
			// allerede utført?
			if ($this->check_complete()) return;
			
			// marker som utført
			$this->load_active(true);
			$this->mark_complete();
		}
	}
	
	/**
	 * Oppnå rangering i FF (etter konkurranse for broderskap)
	 */
	public function handle_ff_pos($data)
	{
		/*
		 * params:
		 *   pos
		 *   type
		 */
		
		// ignorer crew type
		if ($data['ff']->data['ff_is_crew']) return;
		
		// ignorer konkurransemodus
		if ($data['ff']->competition) return;
		
		// mangler pos?
		$pos = (int) $this->a->params->get("pos");
		if ($pos <= 0) return;
		
		// annen type?
		if ($type = $this->a->params->get("type"))
		{
			if ($type != $data['ff']->data['ff_type']) return;
		}
		
		// oppnådd mål?
		if ($pos >= $data['member']->data['ffm_priority'])
		{
			// allerede utført?
			if ($this->check_complete()) return;
			
			// marker som utført
			$this->load_active(true);
			$this->mark_complete();
		}
	}
	
	/**
	 * Være med i broderskapet når konkurransen blir vunnet
	 */
	public function handle_ff_won_member($data)
	{
		$this->load_active(true);
		$this->mark_complete();
	}
	
	/**
	 * Utpresse spillere
	 */
	public function handle_utpress_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Stjel biler gjennom GTA
	 */
	public function handle_gta_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Drep spillere
	 */
	public function handle_kill_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Bryt ut spillere
	 */
	public function handle_fengsel_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Utfør oppdrag
	 */
	public function handle_oppdrag_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Vinn i Lotto 1000 ganger
	 */
	public function handle_lotto_rep($data)
	{
		$this->check_rep($data);
	}
	
	/**
	 * Drepe en spesiell rank
	 */
	public function handle_kill($data)
	{
		/*
		 * params:
		 *   pos (kan være på formen X-X, hvor X er posisjon)
		 *   attacked_rank (kan være på formen -X for å telle fra øverste rank, og X+ for å telle fra og med X)
		 *     (-X+[X] er også tillatt, -4+3 betyr de tre nest øverste rankene)
		 *   etterlyst
		 */
		
		// sjekk posisjon
		if ($pos = $this->a->params->get("pos"))
		{
			$range = explode("-", $pos);
			if (count($range) > 1)
			{
				sort($range);
				
				// sjekk verdi
				if ($data['up']->rank['pos'] < $range[0] || $data['up']->rank['pos'] > $range[1]) return;
			}
			
			else
			{
				// sjekk verdi
				if ($data['up']->rank['pos'] != $pos) return;
			}
		}
		
		// sjekk rank
		if ($rank = $this->a->params->get("attacked_rank"))
		{
			$max = count(game::$ranks['items']);
			$rank = explode("+", $rank);
			
			if ($rank[0] < 0) $rank[0] = $max + $rank[0] + 1;
			$rank[0] = max(1, min($max, $rank[0]));
			
			// inneholder avgrensning?
			if (isset($rank[1]))
			{
				if (empty($rank[1])) $rank[1] = $max;
				sort($rank);
				
				// sjekk verdi
				if ($data['up']->rank['number'] < $rank[0] || $data['up']->rank['number'] > $rank[1]) return;
			}
			
			else
			{
				// sjekk verdi
				if ($data['up']->rank['number'] != $rank[0]) return;
			}
		}
		
		// etterlyst?
		if ($etterlyst = $this->a->params->get("etterlyst"))
		{
			$ret = isset($data['res']) ? $data['res'] : $data['attack'];
			
			// var ikke etterlyst?
			// man får ikke kreditert prestasjonen hvis en spiller under 40 % som er etterlyst deaktiverer seg
			if (empty($ret['hitlist'])) return;
			
			// etterlyst for under 24 timer siden?
			if ($ret['hitlist_oldest_time'] > time()-86400)
			{
				putlog("DF", $this->up->data['up_name'].' (#'.$this->up->id.') oppnådde ikke etterlyst-prestasjon mot '.$data['up']->data['up_name'].' (#'.$data['up']->id.') grunnet at etterlysningen var for ny.');
				return;
			}
			
			// offer ikke pålogget siste 7 dager?
			if ($data['up']->data['up_last_online'] < time()-86400*7)
			{
				putlog("DF", $this->up->data['up_name'].' (#'.$this->up->id.') oppnådde ikke etterlyst-prestasjon mot '.$data['up']->data['up_name'].' (#'.$data['up']->id.') grunnet at offeret ikke har vært pålogget siste 7 dager.');
				return;
			}
		}
		
		// marker som utført
		$this->load_active(true);
		$this->mark_complete();
	}
	
	/**
	 * Enkel repetisjon
	 */
	protected function check_rep($data)
	{
		$this->load_active(true);
		
		// øk antall
		$this->params->lock();
		$this->params->update("c", $this->params->get("c", 0) + 1);
		
		// oppnådd?
		if ($this->params->get("c") >= $this->a->params->get("count", 100))
		{
			$this->mark_complete();
		}
		
		$this->params->commit(true);
	}
	
	/**
	 * Sjekk om vi har oppnådd denne prestasjonen
	 */
	public function check_complete()
	{
		// TODO: cache?
		$result = \Kofradia\DB::get()->query("
			SELECT upa_id
			FROM up_achievements
			WHERE upa_ac_id = {$this->a->id} AND upa_up_id = {$this->up->id} AND upa_complete != 0");
		
		return $result->rowCount() > 0;
	}
	
	/**
	 * Marker som utført
	 */
	public function mark_complete()
	{
		$this->data['upa_complete'] = 1;
		$this->data['upa_time'] = time();
		$this->data['upa_apoints'] = $this->a->data['ac_apoints'];
		$this->data['upa_prize'] = $this->a->data['ac_prize'];
		
		// marker som utført
		$a = \Kofradia\DB::get()->exec("
			UPDATE up_achievements
			SET upa_complete = 1, upa_time = {$this->data['upa_time']}, upa_apoints = {$this->data['upa_apoints']}, upa_prize = ".\Kofradia\DB::quote($this->a->data['ac_prize'])."
			WHERE upa_id = {$this->data['upa_id']} AND upa_complete = 0");
		if ($a == 0) return false;
		
		// oppdater count i hovedtabellen
		\Kofradia\DB::get()->exec("
			UPDATE achievements
			SET ac_count = ac_count + 1
			WHERE ac_id = {$this->a->id}");
		
		// oppdater spilleren
		$this->up->data['up_achievements_points'] += $this->a->data['ac_apoints'];
		\Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_achievements_points = up_achievements_points + {$this->a->data['ac_apoints']}
			WHERE up_id = {$this->up->id}");
		
		// gi spillerlogg
		$rep = $this->get_rep_count();
		$prize = $this->prize();
		$this->up->add_log("achievement", "$rep:".urlencode($this->a->data['ac_name']).":$prize", $this->a->id);
		
		// gi ut premie
		$this->prize(true);
		
		return true;
	}
	
	/**
	 * Hent antall utførte prestasjoner
	 */
	protected function get_rep_count()
	{
		$result = \Kofradia\DB::get()->query("
			SELECT COUNT(upa_id)
			FROM up_achievements
			WHERE upa_ac_id = {$this->a->id} AND upa_up_id = {$this->up->id}");
		return $result->fetchColumn(0);
	}
	
	/**
	 * Formatter tekst for premie (og gi ut hvis spesifisert)
	 */
	protected function prize($give = false)
	{
		// mulige premier:
		// * cash
		// * points
		// * bullets
		
		$params = new params($this->a->data['ac_prize']);
		$text = array();
		
		// cash
		if ($cash = $params->get("cash"))
		{
			if ($give) $this->up->update_money($cash);
			$text[] = game::format_cash($cash);
		}
		
		// points
		if ($points = $params->get("points"))
		{
			if ($give) $this->up->increase_rank($points);
			$text[] = game::format_num($points)." rankpoeng";
		}
		
		// kuler
		if ($bullets = $params->get("bullets"))
		{
			$bullets = (int) $bullets;
			
			// har vi plass til noen kuler?
			if ($this->up->weapon)
			{
				$kap = $this->up->weapon ? $this->up->weapon->data['bullets'] : 0;
				$free = $kap - $this->up->data['up_weapon_bullets'] - $this->up->data['up_weapon_bullets_auksjon'];
				$bullets = max(0, min($free, $bullets));
				
				if ($bullets > 0)
				{
					if ($give)
					{
						// gi kuler
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_weapon_bullets = up_weapon_bullets + $bullets WHERE up_id = {$this->up->id}");
						$this->up->data['up_weapon_bullets'] += $bullets;
					}
					
					$text[] = fwords("%d kule", "%d kuler", $bullets);
				}
			}
		}
		
		return sentences_list($text);
	}
	
	/**
	 * Regn ut progresjon
	 */
	public function get_progress()
	{
		if (!$this->data) $this->load_active();
		if (!$this->data) return null;
		
		// kan ikke regne ut pregresjon?
		if (!$this->params->get("c") || !$this->a->params->get("count")) return null;
		
		return array(
			"current" => $this->params->get("c"),
			"target" => $this->a->params->get("count")
		);
	}
}

achievements::load_cache();
