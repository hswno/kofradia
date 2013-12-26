<?php

class auksjon
{
	const MAKS_BUD = 99999999999999;
	const MAX_TIME_REMOVE = 600; // hvor lang tid det kan gå etter budet før det blir endelig
	
	/** ID */
	public $id;
	
	/** Data */
	public $data;
	
	/** Status */
	public $status;
	
	/**
	 * Params
	 * @var params
	 */
	public $params;
	
	/** Venter på å bli åpnet */
	const STATUS_WAIT = 0;
	
	/** Avsluttet */
	const STATUS_FINISHED = 1;
	
	/** Aktiv */
	const STATUS_ACTIVE = 2;
	
	/** Minste antall kuler som kan selges på en auksjon på en gang */
	const BULLETS_MIN = 3;
	
	/** Høyeste antall kuler som kan selges på en auksjon på en gang */
	const BULLETS_MAX = 10;
	
	/** Minste varighet for kuleauksjon i minutter */
	const BULLETS_TIME_MIN = 5;
	
	/** Lengste varighet for kuleauksjon i minutter */
	const BULLETS_TIME_MAX = 30;
	
	/** Type: Firma */
	const TYPE_FIRMA = 1;
	
	/** Type: Kuler */
	const TYPE_KULER = 2;
	
	/**
	 * Hent auksjon
	 * @return auksjon
	 */
	public static function get($a_id)
	{
		$auksjon = new self($a_id);
		if (!$auksjon->data) return null;
		return $auksjon;
	}
	
	/**
	 * Construct
	 */
	protected function __construct($a_id)
	{
		$this->id = (int) $a_id;
		
		// hent informasjon
		$result = ess::$b->db->query("
			SELECT a_id, a_type, a_up_id, a_title, a_start, a_end, a_bid_start, a_bid_jump, a_num_bids, a_info, a_params, a_completed
			FROM auksjoner
			WHERE a_id = $this->id AND a_active != 0");
		
		$this->data = mysql_fetch_assoc($result);
		if (!$this->data) return;
		
		if ($this->data['a_bid_jump'] < 1) $this->data['a_bid_jump'] = 1;
		$this->params = new params($this->data['a_params']);
		
		// status
		$this->status = $this->data['a_start'] > time() && $this->data['a_completed'] == 0
			? self::STATUS_WAIT
			: ($this->data['a_end'] <= time() || $this->data['a_completed'] != 0
				? self::STATUS_FINISHED
				: self::STATUS_ACTIVE);
		
		// skal den behandles?
		if ($this->status == self::STATUS_FINISHED && $this->data['a_completed'] == 0)
		{
			$this->handle_complete();
		}
		
		// skal vi sette gamle bud som inaktive?
		if ($this->status == self::STATUS_ACTIVE)
		{
			// hent det budet som har stått lenger enn tiden som man kan trekke seg og som er aktivt
			$expire = time() - self::MAX_TIME_REMOVE;
			$result = ess::$b->db->query("
				SELECT ab_id, ab_bid
				FROM auksjoner_bud
				WHERE ab_a_id = $this->id AND ab_active != 0 AND ab_time < $expire
				ORDER BY ab_time DESC
				LIMIT 1");
			$bud = mysql_fetch_assoc($result);
			
			// har vi dette budet?
			if ($bud)
			{
				// hent alle budene som skal settes inaktive
				$result = ess::$b->db->query("
					SELECT ab_id, ab_bid, ab_up_id
					FROM auksjoner_bud
					WHERE ab_a_id = $this->id AND ab_active != 0 AND ab_bid < {$bud['ab_bid']}");
				while ($row = mysql_fetch_assoc($result))
				{
					self::set_bud_inactive($row, $this);
				}
			}
		}
	}
	
	/**
	 * Behandle auksjonen når den er ferdig
	 */
	protected function handle_complete()
	{
		// forsøk å sett som behandlet
		$this->data['a_completed'] = 1;
		ess::$b->db->query("UPDATE auksjoner SET a_completed = 1 WHERE a_id = {$this->id} AND a_completed = 0");
		
		// allerede behandlet?
		if (ess::$b->db->affected_rows() == 0)
		{
			return;
		}
		
		// hent vinnerbudet
		$result = ess::$b->db->query("
			SELECT ab_id, ab_bid, ab_up_id
			FROM auksjoner_bud
			WHERE ab_a_id = $this->id AND ab_active != 0
			ORDER BY ab_time DESC LIMIT 1");
		$bud = mysql_fetch_assoc($result);
		
		// har ikke noe bud?
		if (!$bud)
		{
			// behandle hver type auksjon forskjellig
			switch ($this->data['a_type'])
			{
				// firma
				case self::TYPE_FIRMA:
					// forleng auksjonen med 3 timer fra nå
					$this->data['a_end'] = time() + 10800;
					$this->data['a_completed'] = 0;
					$this->status = self::STATUS_ACTIVE;
					ess::$b->db->query("UPDATE auksjoner SET a_end = {$this->data['a_end']}, a_completed = 0 WHERE a_id = $this->id");
				break;
				
				// kuler
				case self::TYPE_KULER:
					// gi kulene tilbake til personen som startet auksjonen
					$kuler = (int) $this->params->get("bullets");
					if ($kuler && $this->data['a_up_id'])
					{
						player::add_log_static("auksjon_kuler_no_bid", $this->id, $kuler, $this->data['a_up_id']);
						ess::$b->db->query("UPDATE users_players SET up_weapon_bullets = up_weapon_bullets + $kuler, up_weapon_bullets_auksjon = GREATEST(0, up_weapon_bullets_auksjon - $kuler) WHERE up_id = {$this->data['a_up_id']}");
					}
				break;
			}
		}
		
		else
		{
			$up = player::get($bud['ab_up_id']);
			
			// behandle hver type auksjon forskjellig
			switch ($this->data['a_type'])
			{
				// firma
				case self::TYPE_FIRMA:
					// inviter spilleren til firmaet
					$ff_id = $this->params->get("ff_id");
					if ($ff_id)
					{
						$ff = ff::get_ff($ff_id, ff::LOAD_SCRIPT);
						$ff->player_set_priority($bud['ab_up_id'], 1);
						$ff->reset_date_reg(true);
						
						// første firma av denne typen i spillet?
						hall_of_fame::trigger("ff_owner", $ff);
					}
				break;
				
				// kuler
				case self::TYPE_KULER:
					// gi kulene til vinneren av auksjonen
					$kuler = (int) $this->params->get("bullets");
					if ($kuler)
					{
						// oppdater antall auksjonskuler for spilleren som holdt auksjonen
						if ($this->data['a_up_id'])
						{
							ess::$b->db->query("UPDATE users_players SET up_weapon_bullets_auksjon = GREATEST(0, up_weapon_bullets_auksjon - $kuler) WHERE up_id = {$this->data['a_up_id']}");
						}
						
						// gi kulene til vinneren
						$up->add_log("auksjon_kuler_won", $this->id.":".$bud['ab_bid'], $kuler);
						$up->data['up_weapon_bullets'] += $kuler;
						$up->data['up_weapon_bullets'] = max(0, $up->data['up_weapon_bullets'] - $kuler);
						ess::$b->db->query("UPDATE users_players SET up_weapon_bullets = up_weapon_bullets + $kuler, up_weapon_bullets_auksjon = GREATEST(0, up_weapon_bullets_auksjon - $kuler) WHERE up_id = {$bud['ab_up_id']}");
					}
				break;
			}
			
			// live-feed
			livefeed::add_row('<user id="'.$bud['ab_up_id'].'" /> vant auksjonen <a href="'.ess::$s['relative_path'].'/auksjoner?a_id='.$this->id.'">'.htmlspecialchars($this->data['a_title']).'</a>.');
			
			// gi penger til den som la ut auksjonen
			if ($this->data['a_up_id'])
			{
				ess::$b->db->query("
					UPDATE users_players
					SET up_cash = up_cash + {$bud['ab_bid']}, up_auksjoner_total_in = up_auksjoner_total_in = {$bud['ab_bid']}
					WHERE up_id = {$this->data['a_up_id']}");
			}
			
			// oppdater statistikk til spilleren som vant budet
			ess::$b->db->query("
				UPDATE users_players
				SET up_auksjoner_total_out = up_auksjoner_total_out = {$bud['ab_bid']}
				WHERE up_id = {$bud['ab_up_id']}");
			
			// hent alle budene som skal settes inaktive
			$result = ess::$b->db->query("
				SELECT ab_id, ab_bid, ab_up_id
				FROM auksjoner_bud
				WHERE ab_a_id = $this->id AND ab_active != 0 AND ab_id != {$bud['ab_id']}");
			while ($row = mysql_fetch_assoc($result))
			{
				self::set_bud_inactive($row, $this);
			}
			
			// behandle trigger
			$up->trigger("auksjon_won", array(
				"auksjon" => $this,
				"bud" => $bud));
		}
		
		// behandle trigger
		if ($this->data['a_up_id'])
		{
			$up = player::get($this->data['a_up_id']);
			if ($up) $up->trigger("auksjon_complete", array(
					"auksjon" => $this,
					"winner_bud" => $bud));
		}
		
		// oppdater cache
		self::update_cache();
	}
	
	/**
	 * Slett auksjonen (sett som inaktiv og returner bud)
	 */
	public function handle_delete()
	{
		// forsøk å sett som behandlet
		$this->data['a_completed'] = 1;
		ess::$b->db->query("UPDATE auksjoner SET a_completed = 1 WHERE a_id = $this->id AND a_completed = 0");
		
		// allerede behandlet?
		if (ess::$b->db->affected_rows() == 0)
		{
			return;
		}
		
		// behandle ulike auksjonstyper
		switch ($this->data['a_type'])
		{
			case self::TYPE_KULER:
				// gi kulene tilbake til personen som startet auksjonen
				$kuler = (int) $this->params->get("bullets");
				if ($kuler && $this->data['a_up_id'])
				{
					ess::$b->db->query("UPDATE users_players SET up_weapon_bullets = up_weapon_bullets + $kuler, up_weapon_bullets_auksjon = GREATEST(0, up_weapon_bullets_auksjon - $kuler) WHERE up_id = {$this->data['a_up_id']}");
				}
			break;
		}
		
		// hent alle budene som skal settes inaktive
		$result = ess::$b->db->query("
			SELECT ab_id, ab_bid, ab_up_id
			FROM auksjoner_bud
			WHERE ab_a_id = $this->id AND ab_active != 0");
		while ($row = mysql_fetch_assoc($result))
		{
			self::set_bud_inactive($row, $this);
		}
		
		// behandle trigger
		if ($this->data['a_up_id'])
		{
			$up = player::get($this->data['a_up_id']);
			if ($up) $up->trigger("auksjon_delete", array(
					"auksjon" => $this));
		}
		
		// oppdater cache
		self::update_cache();
	}
	
	/**
	 * Sett et bud som inaktivt
	 */
	public static function set_bud_inactive($row, auksjon $auksjon = null, player $up = null, $compare_price = null)
	{
		$up_id = $up ? $up->id : $row['ab_up_id'];
		$price = $compare_price ? " AND ab_bid = {$row['ab_bid']}" : "";
		
		// sett som inaktivt og gi tilbake pengene
		ess::$b->db->query("
			UPDATE auksjoner_bud, users_players
			SET ab_active = 0, up_cash = up_cash + ab_bid
			WHERE ab_id = {$row['ab_id']} AND ab_active != 0$price AND ab_up_id = up_id");
		if (ess::$b->db->affected_rows() == 0) return false;;
		
		// bestemt spiller?
		if ($up)
		{
			// bregn ny pengeverdi
			$up->data['up_cash'] = bcadd($up->data['up_cash'], $row['ab_bid']);
		}
		
		// aktiv spiller?
		elseif (login::$logged_in && login::$user->player->id == $up_id)
		{
			// beregn ny pengeverdi
			$up = login::$user->player;
			$up->data['up_cash'] = bcadd($up->data['up_cash'], $row['ab_bid']);
		}
		
		// behandle ulike auksjonstyper
		switch ($auksjon ? $auksjon->data['a_type'] : $row['a_type'])
		{
			// kuler
			case auksjon::TYPE_KULER:
				// "gi tilbake" kulene som er reservert
				$params = $auksjon ? $auksjon->params : new params($row['a_params']);
				$kuler = (int) $params->get("bullets");
				if ($kuler)
				{
					ess::$b->db->query("UPDATE users_players SET up_weapon_bullets_auksjon = GREATEST(0, up_weapon_bullets_auksjon - $kuler) WHERE up_id = $up_id");
				}
			break;
		}
		
		return true;
	}
	
	/**
	 * Frigjør en spiller fra aktive auksjoner
	 * @param player $up
	 * @param int $up_id
	 * @param int $type skal dette kun gjelde en bestemt type auksjon?
	 */
	public static function player_release(player $up = null, $up_id = null, $type = null)
	{
		if ($up) $up_id = $up->id;
		$type = $type ? " AND a_type = ".((int) $type) : "";
		
		// hent auksjonene spilleren selv holder
		$result = ess::$b->db->query("
			SELECT a_id
			FROM auksjoner
			WHERE a_completed = 0 AND a_up_id = $up_id$type");
		while ($row = mysql_fetch_assoc($result))
		{
			$a = auksjon::get($row['a_id']);
			if ($a) $a->handle_delete();
		}
		
		// hent budene vi har og sett de som inaktive
		$result = ess::$b->db->query("
			SELECT ab_id, ab_up_id, ab_bid, a_id, a_type, a_params
			FROM auksjoner_bud JOIN auksjoner ON a_id = ab_a_id
			WHERE a_completed = 0 AND ab_up_id = $up_id AND ab_active != 0$type");
		while ($row = mysql_fetch_assoc($result))
		{
			self::set_bud_inactive($row, null, $up);
		}
	}
	
	/**
	 * Bygg opp cache over auksjoner for menyen
	 */
	public static function update_cache()
	{
		// hent alle aktive og kommende auksjoner
		$result = ess::$b->db->query("SELECT a_start, a_end FROM auksjoner WHERE a_end >= ".time()." AND a_active != 0 AND a_completed = 0");
		$data = array();
		while ($row = mysql_fetch_row($result))
		{
			$data[] = $row;
		}
		
		cache::store("auksjoner_active", $data);
	}
	
	/**
	 * Opprett auksjon for FF
	 */
	public static function create_auksjon_ff(ff $ff, $start = null, $end = null)
	{
		$time = time();
		if (!$start) $start = $time;
		else $start = (int) $start;
		
		if (!$end)
		{
			// varighet er til 21:00 med minimum 12 timer
			$date = ess::$b->date->get();
			$date->setTime(21, 0, 0);
		
			$min_time = 3600 * 12;
		
			if ($date->format("U") < $time + $min_time) $date->modify("+1 day");
			$expire = $date->format("U");
		}
		else $expire = (int) $end;
		
		// sett opp params for ff_id
		$params = new params();
		$params->update("ff_id", $ff->id);
		
		// opprett auksjonen
		ess::$b->db->query("INSERT INTO auksjoner SET a_type = ".self::TYPE_FIRMA.", a_title = ".ess::$b->db->quote($ff->data['ff_name']).", a_start = $start, a_end = $expire, a_bid_start = 1000000, a_bid_jump = 500000, a_active = 1, a_params = ".ess::$b->db->quote($params->build()));
		$a_id = ess::$b->db->insert_id();
		
		// logg
		putlog("INFO", "%bAUKSJON:%b Auksjon for %u".$ff->data['ff_name']."%u ble opprettet ".ess::$s['spath']."/auksjoner?a_id=$a_id");
		
		// live-feed
		livefeed::add_row('<a href="'.ess::$s['rpath'].'/auksjoner?a_id='.$a_id.'">Auksjon</a> for <a href="'.ess::$s['rpath'].'/ff/?ff_id='.$ff->id.'">'.htmlspecialchars($ff->data['ff_name']).'</a> ble opprettet.');
		
		self::update_cache();
	}
}

class auksjon_type
{
	/**
	 * Ulike kategorier
	 */
	public static $types = array(
		1 => array(
			"title" => "Firmaer",
			"img" => "/static/firma/bank.png",
			"have_up" => false
		),
		2 => array(
			"title" => "Kuler",
			"img" => "/static/firma/avis.png",
			"have_up" => true
		)
	);
	
	/** ID */
	public $id;
	
	/** Tittel */
	public $title;
	
	/** Bilde */
	public $img;
	
	/** Er det spillere som eier disse auksjonene? */
	public $have_up;
	
	/** Construct */
	protected function __construct($type)
	{
		$this->id = (int) $type;
		$type = &self::$types[$type];
		$this->title = $type['title'];
		$this->img = $type['img'];
		$this->have_up = $type['have_up'];
	}
	
	/**
	 * @return auksjon_type
	 */
	public static function get($type)
	{
		if (!isset(self::$types[$type])) return null;
		return new self($type);
	}
	
	public function format_title($row)
	{
		return htmlspecialchars($row['a_title']);
	}
}
