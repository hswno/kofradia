<?php

class hall_of_fame
{
	protected static $data;
	
	/**
	 * Hent, evt. generer, cache
	 */
	public static function cache_load($reload = false)
	{
		if (!$reload)
		{
			$data = cache::fetch("hall_of_fame");
			if ($data)
			{
				self::$data = $data;
				return;
			}
		}
		
		$data = self::get_data_structure();
		
		// hent fra databasen
		$result = ess::$b->db->query("
			SELECT hof_id, hof_name, hof_sub, hof_time, hof_data
			FROM hall_of_fame");
		while ($row = mysql_fetch_assoc($result))
		{
			$data[$row['hof_name']][$row['hof_sub'] ?: 0] = array($row['hof_time'], unserialize($row['hof_data']));
		}
		
		cache::store("hall_of_fame", $data, 900);
		self::$data = $data;
	}
	
	/**
	 * Hent oversikt over ulike Hall of Fames
	 */
	protected static function get_data_structure()
	{
		$data = array();
		
		// ranker
		foreach (array_reverse(game::$ranks['items_number']) as $row)
		{
			// fra Pusher og opp
			if ($row['number'] < 5) continue;
			
			$data['rank'][$row['number']]      = false;
			$data['rank_kill'][$row['number']] = false;
		}
		
		// første familie
		$data['familie'][] = false;
		
		// topp rangert familie
		$data['familie_rank'][] = false;
		
		// første eier av ulike FF
		foreach (array_keys(ff::$types) as $id)
		{
			$data['ff_owner'][$id] = false;
		}
		
		// første spiller i ulike pengeranker
		$i = 0;
		foreach (array_reverse(ess::$g['cash']) as $name => $val)
		{
			// hopp over første 6 pengerankene
			if (++$i < 7) continue;
			
			$data['cash_num'][$i] = false;
		}
		
		return $data;
	}
	
	/**
	 * Oppdater en Hall of Fame (oppnådd)
	 */
	protected static function set_data($name, $sub, $data, $extra = null)
	{
		ess::$b->db->query("
			INSERT IGNORE INTO hall_of_fame
			SET
				hof_name = ".ess::$b->db->quote($name).",
				hof_sub = ".ess::$b->db->quote($sub).",
				hof_time = ".time().",
				hof_data = ".ess::$b->db->quote(serialize($data)));
		$affected = ess::$b->db->affected_rows() > 0;
		
		// logg
		list($subject, $url) = self::get_subject($name, $extra);
		$text = self::get_text($name, $sub, $data);
		putlog("INFO", "%bHALL OF FAME:%b %u".$subject."%u ble ".$text." $url");
		
		// spillerlogg
		$up = null;
		if ($name != "familie" && $name != "familie_rank") $up = $extra;
		if ($name == "ff_owner") $up = $extra[1];
		if ($up) $up->add_log("hall_of_fame", $text);
		
		// ff-logg
		if ($name == "familie") $extra->add_log("info", 'Broderskapet ble det første broderskap i spillet og havnet på <a href="&rpath;/hall_of_fame">Hall of Fame</a>!');
		elseif ($name == "familie_rank") $extra->add_log("info", 'Broderskapet har for øyeblikket flest poeng av alle broderskap på spillet i historien og havnet på <a href="&rpath;/hall_of_fame">Hall of Fame</a>!');
		
		self::cache_load(true);
		return $affected;
	}
	
	/**
	 * Hent referanse til subjekt
	 */
	protected static function get_subject($name, $data = null)
	{
		switch ($name)
		{
			case "rank":
			case "rank_kill":
			case "cash_num":
				return array($data->data['up_name'], $data->generate_profile_url());
			
			case "familie":
			case "familie_rank":
				return array($data->data['ff_name'], ess::$s['path']."/ff/?ff_id=".$data->id);
			
			case "ff_owner":
				// $data[0] = ff, $data[1] = player
				return array($data[1]->data['up_name'], $data[1]->generate_profile_url());
		}
		
		throw new HSException("Ukjent type.");
	}
	
	/**
	 * Hent tekst for en Hall of Fame
	 */
	public static function get_text($name, $sub, $data)
	{
		switch ($name)
		{
			case "rank":
				return 'første spilleren til å oppnå ranken '.game::$ranks['items_number'][$sub]['name'];
			
			case "rank_kill":
				return 'første spilleren til å drepe en '.game::$ranks['items_number'][$sub]['name'];
			
			case "familie":
				return 'første familien i spillet';
			
			case "familie_rank":
				return 'topp rangert broderskap i spillet med '.game::format_num($data['ff_points_sum']).' poeng';
			
			case "ff_owner":
				return 'første spilleren til å eie '.($sub == 1 ? 'en' : 'et').' '.ff::$types[$sub]['typename'];
			
			case "cash_num":
				return 'første spilleren til å oppnå pengeplasseringen &laquo;'.self::get_cash_pos($sub).'&raquo;';
		}
		
		throw new HSException("Ukjent type.");
	}
	
	/**
	 * Hent navn for pengerank
	 */
	public static function get_cash_pos($pos)
	{
		static $cache = null;
		
		if (!$cache)
		{
			// lag cache
			$cache = array();
			$i = 0;
			foreach (array_keys(ess::$g['cash']) as $name)
			{
				$cache[++$i] = $name;
			}
		}
		
		return $cache[$pos];
	}
	
	/**
	 * Sjekk om vi har utført en Hall of Fame
	 */
	public static function check_new($name, $sub)
	{
		return isset(self::$data[$name][$sub ?: 0]) && self::$data[$name][$sub ?: 0] === false;
	}
	
	/**
	 * Trigger for å sjekke om vi har utført en Hall of Fame
	 */
	public static function trigger($name, $data, player $up = null)
	{
		// ignorer crew
		if (isset($up) && $up->data['up_access_level'] >= ess::$g['access_noplay']) return;
		
		switch ($name)
		{
			// rankplassering
			case "rank":
				// utfør kun på positive forandringer
				if ($data['points_rel'] < 0) return;
				
				// har ikke ranken forandret seg?
				if ($data['rank'] <= 0) return;
				
				// allerede oppnådd eller ukjent?
				$num = $up->rank['number'];
				if (!self::check_new("rank", $num)) return;
				
				self::set_data("rank", $num, $up->id, $up);
			break;
			
			// drepe en spiller i en rank
			case "rank_kill":
				// bare skadet angrep?
				if (isset($data['attack']) && !$data['attack']['drept']) return;
				
				// allerede oppnådd eller ukjent?
				$rank = $data['up']->rank['number'];
				if (!self::check_new("rank_kill", $rank)) return;
				
				self::set_data("rank_kill", $rank, array("up_attacker" => $up->id, "up_died" => $data['up']->id), $up);
			break;
			
			// første familie
			case "familie":
				// allerede oppnådd eller ukjent?
				if (!self::check_new("familie", null)) return;
				
				self::set_data("familie", null, array("ff_id" => $data->id, "ff_name" => $data->data['ff_name']), $data);
			break;
			
			// eier av FF
			case "ff_owner":
				// allerede oppnådd eller ukjent?
				if (!self::check_new("ff_owner", $data->data['ff_type'])) return;
				
				// hent eier
				if (!$up)
				{
					// skulle det ved en feil være flere eiere velger vi uansett bare den første
					if (!isset($data->members['members_priority'][1])) return;
					$up = reset($data->members['members_priority'][1]);
					$up = $up->up;
				}
				
				self::set_data("ff_owner", $data->data['ff_type'], array("up_id" => $up->id, "ff_id" => $data->id, "ff_name" => $data->data['ff_name']), array($data, $up));
			break;
			
			// pengerank
			case "cash_num":
				// finn totalt beløp
				$sum = bcadd($up->data['up_cash'], $up->data['up_bank']);
				
				// finn pengeranknummer
				$num = game::cash_name_number($sum);
				
				// allerede oppnådd eller ukjent?
				if (!self::check_new("cash_num", $num)) return;
				
				self::set_data("cash_num", $num, $up->id, $up);
			break;
			
			// topprangert familie
			case "familie_rank":
				// har vi en rangert familie?
				if (!self::check_new("familie_rank", null))
				{
					// er vi ikke rangert over den forrige?
					if ($data->data['ff_points_sum'] <= self::$data['familie_rank'][0][1]['ff_points_sum'])
					{
						return;
					}
					
					// slett forrige oppføring
					ess::$b->db->query("DELETE FROM hall_of_fame WHERE hof_name = 'familie_rank'");
				}
				
				// legg til
				self::set_data("familie_rank", null, array("ff_id" => $data->id, "ff_name" => $data->data['ff_name'], "ff_points_sum" => $data->data['ff_points_sum']), $data);
			break;
		}
	}
	
	/**
	 * Hent liste over alle Hall of Fame for visning
	 */
	public static function get_all_status()
	{
		return self::$data;
	}
	
	/**
	 * Generer html for subjekt
	 */
	public static function get_subject_html($name, $data)
	{
		switch ($name)
		{
			case "rank":
			case "cash_num":
				return '<user id="'.$data.'" />';
			
			case "rank_kill":
				return '<user id="'.$data['up_attacker'].'" />';
			
			case "familie":
			case "familie_rank":
				return '<a href="'.ess::$s['rpath'].'/ff/?ff_id='.$data['ff_id'].'">'.htmlspecialchars($data['ff_name']).'</a>';
				
			case "ff_owner":
				return '<user id="'.$data['up_id'].'" />';
		}
		
		throw new HSException("Ukjent type.");
	}
}

hall_of_fame::cache_load();
