<?php

/**
 * Scheduler
 * Legger ut kulene til salgs
 */
class bullets_scheduler
{
	/** Hvor mange ganger skal kuler legges ut for salg i løpet av en dag */
	const GROUPS = 40;
	
	/** Antall kuler produsert avhengig av antall aktive 48 timer tidligere */
	const PRODUCE_ACTIVE = 1.5;
	
	/** Minimum antall kuler som blir produsert */
	const PRODUCE_MIN = 40;
	
	protected $time_start;
	protected $time_end;
	protected $ff_list;
	protected $count_total;
	
	public function __construct()
	{
		// fjern alle kulene som ikke er kjøpt allerede
		ess::$b->db->query("TRUNCATE bullets");
		
		// sett opp tidsperiode
		$this->get_time_period();
		
		// sett opp firmaene hvor kulene skal bli fordelt mellom
		$this->get_ff_list();
		
		// har vi ingen firmaer?
		if (count($this->ff_list) == 0)
		{
			putlog("CREWCHAN", "KULEPLANLEGGING: Det er ingen firmaer som kan selge kuler.");
			return;
		}
		
		// finn ut antall kuler vi skal fordele til hvert firma
		$this->get_count();
		
		// logg antall kuler
		putlog("CREWCHAN", "KULEPLANLEGGING: {$this->count_total} kuler planlagt for salg.");
		
		// legg til kulene i databasen
		$this->add();
	}
	
	/**
	 * Sett opp tidsperiode
	 */
	protected function get_time_period()
	{
		$now = ess::$b->date->get();
		
		// sett opp tidsperiode kulene skal selges
		$date_start = clone $now;
		if ($now->format("H") >= 20) $date_start->modify("+1 day");
		$date_start->setTime(20, 0, 0);
		
		$this->time_start = $date_start->format("U");
		$this->time_end = $this->time_start + 6600; // 1 time 50 min
	}
	
	/**
	 * Hent inn firmaene hvor kulene skal bli fordelt mellom
	 */
	protected function get_ff_list()
	{
		$this->ff_list = array();
		$result = ess::$b->db->query("
			SELECT ff_id
			FROM ff
			WHERE ff_type = 5");
		while ($row = mysql_fetch_assoc($result))
		{
			$row['ant'] = 0;
			$row['grupper'] = 0;
			$this->ff_list[] = $row;
		}
	}
	
	/**
	 * Antall kuler som skal fordeles, fordel også antall omganger kulene skal gis ut i
	 */
	protected function get_count()
	{
		// hvor mange aktive de siste 48 timene?
		$result = ess::$b->db->query("SELECT COUNT(*) FROM users_players WHERE up_last_online > ".(time()-86400*2)." AND up_access_level != 0");
		$this->count_total = round(max(self::PRODUCE_MIN, mysql_result($result, 0) * self::PRODUCE_ACTIVE));
		
		// fordel antallet på firmaene
		$ff_count = count($this->ff_list);
		$each = floor($this->count_total / $ff_count);
		$each_groups = floor(self::GROUPS / $ff_count);
		$rest = $this->count_total % $ff_count;
		$rest_groups = self::GROUPS % $ff_count;
		
		foreach ($this->ff_list as &$row)
		{
			$row['ant'] = $each;
			$row['grupper'] = $each_groups;
		}
		unset($row);
		
		// fordel resten tilfeldig
		if ($rest > 0)
		{
			$extra_rand = (array) array_rand($this->ff_list, $rest);
			foreach ($extra_rand as $k)
			{
				$this->ff_list[$k]['ant']++;
			}
		}
		
		if ($rest_groups > 0)
		{
			$extra_rand = (array) array_rand($this->ff_list, $rest_groups);
			foreach ($extra_rand as $k)
			{
				$this->ff_list[$k]['grupper']++;
			}
		}
	}
	
	/**
	 * Legg til kulene i databasen
	 */
	protected function add()
	{
		$v = array();
		
		foreach ($this->ff_list as $f)
		{
			// sett opp antall som skal fordeles i hver gruppe
			$groups = array();
			$each = floor($f['ant'] / $f['grupper']);
			$rest = $f['ant'] % $f['grupper'];
			
			for ($i = 0; $i < $f['grupper']; $i++)
			{
				$groups[] = $each;
			}
			
			if ($rest > 0)
			{
				$extra_rand = (array) array_rand($groups, $rest);
				foreach ($extra_rand as $k)
				{
					$groups[$k]++;
				}
			}
			
			// sett opp data for databasen
			foreach ($groups as $ant)
			{
				// velg tilfeldig tidspunkt
				$time = rand($this->time_start, $this->time_end);
				
				// sett opp for databasen
				for ($i = 0; $i < $ant; $i++)
					$v[] = "({$f['ff_id']}, $time)";
			}
		}
		
		// legg til i databasen
		if (count($v) > 0)
		{
			ess::$b->db->query("INSERT INTO bullets (bullet_ff_id, bullet_time) VALUES ".implode(",", $v));
		}
	}
}