<?php

class blokkeringer
{
	/** Type: Forum */
	const TYPE_FORUM = 1;
	
	/** Type: Meldinger */
	const TYPE_MELDINGER = 2;
	
	/** Type: Profiltekst og signatur */
	const TYPE_PROFIL = 3;
	
	/** Type: Rapporteringer */
	const TYPE_RAPPORTERINGER = 4;
	
	/** Type: Support */
	const TYPE_SUPPORT = 5;
	
	/** Type: Deaktivere */
	const TYPE_DEAKTIVER = 6;
	
	/** Type: E-post */
	const TYPE_EPOST = 7;
	
	/** Type: Signatur */
	const TYPE_SIGNATUR = 8;
	
	/** Type: Profilbilde */
	const TYPE_PROFILE_IMAGE = 9;
	
	/** Oversikt over de forskjellige type blokkeringer */
	public static $types = array(
		1 => array(
			"title" => "Forum",
			"description" => "Hindre en bruker i å opprette nye tråder og svar, samt redigere egne tråder og svar.",
			"access" => "forum_mod",
			"userlog" => "utføre handlinger i forumet"
		),
		2 => array(
			"title" => "Meldinger",
			"description" => "Hindre en bruker i å sende ut meldinger til andre spillere enn Crewet.",
			"access" => "mod",
			"userlog" => "sende meldinger til andre spillere enn Crewet"
		),
		3 => array(
			"title" => "Profiltekst",
			"description" => "Hindre en bruker i å redigere profilteksten sin.",
			"access" => "forum_mod",
			"userlog" => "redigere profilteksten din"
		),
		4 => array(
			"title" => "Rapporteringer",
			"description" => "Hindre en bruker i å sende inn rapporteringer.",
			"access" => "crewet",
			"userlog" => "sende inn rapporteringer"
		),
		5 => array(
			"title" => "Support",
			"description" => "Hindre en bruker i å sende inn henvendelser til support.",
			"access" => "forum_mod",
			"userlog" => "sende inn henvendelser til support"
		),
		6 => array(
			"title" => "Deaktivere",
			"description" => "Hindre en bruker i å deaktivere sin egen spiller/bruker.",
			"access" => "mod",
			"userlog" => "deaktivere din egen spiller/bruker"
		),
		7 => array(
			"title" => "E-postadresse",
			"description" => "Hindre en bruker i å bytte e-postadresse.",
			"access" => "mod",
			"userlog" => "bytte e-postadresse"
		),
		8 => array(
			"title" => "Signatur",
			"description" => "Hindre en bruker i å redigere signaturen sin.",
			"access" => "forum_mod",
			"userlog" => "redigere signaturen din"
		),
		9 => array(
			"title" => "Profilbilde",
			"description" => "Hindre en bruker i å fjerne, legge til, eller endre profilbildet sitt.",
			"access" => "forum_mod",
			"userlog" => "fjerne, legge til eller endre profilbildet ditt"
		)
	);
	
	/**
	 * Hente brukerID
	 */
	private static function u_id()
	{
		if (!login::$logged_in)
		{
			throw new HSException("Mangler brukerinformasjon.");
		}
		
		return login::$user->id;
	}
	
	/**
	 * Hent en bestemt type
	 * @param integer $type
	 */
	public static function get_type($type)
	{
		$type = (int) $type;
		if (!isset(self::$types[$type]))
		{
			return array(
				"title" => "Ukjent type ($type)",
				"description" => "Ukjent blokkeringstype.",
				"access" => "crewet"
			);
		}
		
		return self::$types[$type];
	}
	
	/**
	 * Sjekk om brukeren er blokkert
	 * @param integer $type TYPE_ konstant
	 * @return false | array(ub_id, ub_time_expire, ub_reason)
	 */
	public static function check($type, $u_id = NULL)
	{
		// hvilken bruker det skal gjelde
		$u_id = is_int($u_id) ? $u_id : self::u_id();
		
		// hent blokkeringen hvis den finnes
		$type = intval($type);
		$result = ess::$b->db->query("SELECT ub_id, ub_time_expire, ub_reason FROM users_ban WHERE ub_u_id = $u_id AND ub_type = $type AND ub_time_expire > ".time());
		
		// fant ingen rader?
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		
		// returner første raden
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Hent informasjon om en blokkering
	 */
	public static function get_info($ub_id)
	{
		$ub_id = (int) $ub_id;
		
		// hent informasjon
		$result = ess::$b->db->query("SELECT ub_id, ub_u_id, ub_type, ub_time_added, ub_time_expire, ub_reason, ub_note FROM users_ban WHERE ub_id = $ub_id");
		
		// send tilbake resultatet
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Legg til blokkering
	 * @param integer $u_id brukerID
	 * @param integer $type
	 * @param integer $expire unix timestamp
	 * @param string $log begrunnelse
	 * @param string $note intern informasjon
	 */
	public static function add($u_id, $type, $expire, $log, $note)
	{
		$u_id = (int) $u_id;
		$type = (int) $type;
		$expire = (int) $expire;
		
		// kontroller at typen finnes
		if (!isset(self::$types[$type]))
		{
			throw new HSException("Fant ikke blokkeringstypen.");
		}
		
		// kontroller at tidspunktet er fremover i tid
		if ($expire <= time())
		{
			throw new HSException("Sluttidspunktet for blokkeringen må være fremover i tid.");
		}
		
		// kontroller at det ikke finnes noen blokkering
		if ($exists = self::check($type, $u_id))
		{
			return $exists;
		}
		
		// legg til blokkeringen
		ess::$b->db->query("INSERT INTO users_ban SET ub_u_id = $u_id, ub_type = $type, ub_time_added = ".time().", ub_time_expire = $expire, ub_reason = ".ess::$b->db->quote($log).", ub_note = ".ess::$b->db->quote($note));
		
		global $_game;
		
		// finn korrekt spiller
		$result = ess::$b->db->query("SELECT up_id FROM users JOIN users_players ON u_active_up_id = up_id WHERE u_id = $u_id");
		$up_id = mysql_result($result, 0);
		
		// legg til logg hos spilleren
		player::add_log_static("blokkering", "1:$expire:".urlencode($log), $type, $up_id);
		
		return true;
	}
	
	/**
	 * Rediger en blokkering
	 * @param integer $ub_id
	 * @param integer $expire unix timestamp
	 * @param string $log
	 * @param string $note intern informasjon
	 */
	public static function edit($ub_id, $expire, $log, $note)
	{
		$ub_id = (int) $ub_id;
		$expire = (int) $expire;
		
		// kontroller at tidspunktet er fremover i tid
		if ($expire <= time())
		{
			throw new HSException("Sluttidspunktet for blokkeringen må være fremover i tid.");
		}
		
		// hent nåværende informasjon
		$res = self::get_info($ub_id);
		
		// forsøk å oppdater blokkeringen
		ess::$b->db->query("UPDATE users_ban SET ub_time_expire = $expire, ub_reason = ".ess::$b->db->quote($log).", ub_note = ".ess::$b->db->quote($note)." WHERE ub_id = $ub_id AND ub_time_expire > ".time());
		$aff = ess::$b->db->affected_rows();
		
		// legg til logg hos spilleren
		if ($res && $aff > 0)
		{
			$expire = $expire == $res['ub_time_expire'] ? '' : $expire;
			$log = $log == $res['ub_reason'] ? '' : $log;
			if ($expire !== "" || $log !== "")
			{
				global $_game;
				
				// finn korrekt spiller
				$result = ess::$b->db->query("SELECT up_id FROM users JOIN users_players ON u_active_up_id = up_id WHERE u_id = {$res['ub_u_id']}");
				$up_id = mysql_result($result, 0);
				
				player::add_log_static("blokkering", "2:$expire:".urlencode($log), $res['ub_type'], $up_id);
			}
		}
		
		return $aff;
	}
	
	/**
	 * Fjern en blokkering
	 */
	public static function delete($ub_id)
	{
		$ub_id = (int) $ub_id;
		
		// hent nåværende informasjon
		$res = self::get_info($ub_id);
		
		// forsøk å sett tidspunktet til nå
		ess::$b->db->query("UPDATE users_ban SET ub_time_expire = ".time()." WHERE ub_id = $ub_id AND ub_time_expire > ".time());
		$aff = ess::$b->db->affected_rows();
		
		if ($res && $aff > 0)
		{
			global $_game;
			
			// finn korrekt spiller
			$result = ess::$b->db->query("SELECT up_id FROM users JOIN users_players ON u_active_up_id = up_id WHERE u_id = {$res['ub_u_id']}");
			$up_id = mysql_result($result, 0);
			
			player::add_log_static("blokkering", "3", $res['ub_type'], $up_id);
		}
		
		return $aff;
	}
}