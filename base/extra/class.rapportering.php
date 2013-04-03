<?php

/**
 * Rapportering av diverse ting
 */
class rapportering
{
	/** PM id */
	const TYPE_PM = 1;
	
	/** Forumtråd id */
	const TYPE_FORUM_TOPIC = 2;
	
	/** Forumsvar id */
	const TYPE_FORUM_REPLY = 3;
	
	/** Signatur id */
	const TYPE_SIGNATURE = 4;
	
	/** Profil id */
	const TYPE_PROFILE = 5;
	
	public static $types = array(
		1 => "Privat melding",
		2 => "Forumtråd",
		3 => "Forumsvar",
		4 => "Signatur",
		5 => "Profil"
	);
	
	/**
	 * Rapportere en privat melding
	 * @param int $im_id
	 * @param string $message
	 * @return int r_id || bool false if not found || array dupe
	 */
	public static function report_pm($im_id, $message)
	{
		// hent brukerid til meldingen
		$im_id = (int) $im_id;
		$result = ess::$b->db->query("SELECT im_up_id FROM inbox_messages WHERE im_id = $im_id");
		
		// fant ikke meldingen?
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		$up_id = mysql_result($result, 0);
		
		// allerede rapportert?
		if ($dupe = self::check($up_id, self::TYPE_PM, $im_id, login::$user->player->id))
		{
			return $dupe;
		}
		
		return self::add($up_id, self::TYPE_PM, $im_id, $message);
	}
	
	/**
	 * Rapportere en forumtråd
	 * @param int $ft_id
	 * @param string $message
	 * @return int r_id || bool false if not found || string deleted if deleted || array dupe
	 */
	public static function report_forum_topic($ft_id, $message)
	{
		// hent brukerid til personen som opprettet forumtråden
		$ft_id = (int) $ft_id;
		$result = ess::$b->db->query("SELECT ft_up_id, ft_deleted FROM forum_topics WHERE ft_id = $ft_id");
		
		// fant ikke tråden?
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		
		// slettet?
		if (mysql_result($result, 0, 1) != 0)
		{
			// slettede tråder skal ikke kunne rapporteres
			return "deleted";
		}
		$up_id = mysql_result($result, 0);
		
		// allerede rapportert?
		if ($dupe = self::check($up_id, self::TYPE_FORUM_TOPIC, $ft_id))
		{
			return $dupe;
		}
		
		return self::add($up_id, self::TYPE_FORUM_TOPIC, $ft_id, $message);
	}
	
	/**
	 * Rapportere et forumsvar
	 * @param int $fr_id
	 * @param string $message
	 * @return int r_id || bool false if not found || string deleted if deleted || string topic_deleted if topic is deleted || array dupe
	 */
	public static function report_forum_reply($fr_id, $message)
	{
		// hent brukerid til personen som opprettet forumtråden
		$fr_id = (int) $fr_id;
		$result = ess::$b->db->query("SELECT fr_up_id, fr_deleted, fr_ft_id FROM forum_replies WHERE fr_id = $fr_id");
		
		// fant ikke svaret?
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		
		// slettet?
		if (mysql_result($result, 0, 1) != 0)
		{
			// slettede svar skal ikke kunne rapporteres
			return "deleted";
		}
		
		// sjekk om tråden er slettet
		$result2 = ess::$b->db->query("SELECT ft_deleted FROM forum_topics WHERE ft_id = ".mysql_result($result, 0, 2));
		if (mysql_num_rows($result2) == 0)
		{
			return false;
		}
		if (mysql_result($result2, 0) != 0)
		{
			return "topic_deleted";
		}
		$up_id = mysql_result($result, 0);
		
		// allerede rapportert?
		if ($dupe = self::check($up_id, self::TYPE_FORUM_REPLY, $fr_id))
		{
			return $dupe;
		}
		
		return self::add($up_id, self::TYPE_FORUM_REPLY, $fr_id, $message);
	}
	
	/**
	 * Rapportere en signatur
	 * @param int $up_id
	 * @param string $message
	 * @return int r_id
	 */
	public static function report_signature($up_id, $message)
	{
		// kontroller at spilleren finnes
		$up_id = intval($up_id);
		$result = ess::$b->db->query("SELECT up_id FROM users_players WHERE up_id = $up_id");
		
		// fant ikke spilleren?
		if (mysql_num_rows($result) == 0) return "player_not_found";
		
		// allerede rapportert?
		if ($dupe = self::check($up_id, self::TYPE_SIGNATURE, 0, login::$user->player->id))
		{
			return $dupe;
		}
		
		return self::add($up_id, self::TYPE_SIGNATURE, 0, $message);
	}
	
	/**
	 * Rapportere en profil
	 * @param int $up_id
	 * @param string $message
	 * @return int r_id
	 */
	public static function report_profile($up_id, $message)
	{
		// kontroller at spilleren finnes
		$up_id = intval($up_id);
		$result = ess::$b->db->query("SELECT up_id FROM users_players WHERE up_id = $up_id");
		
		// fant ikke spilleren?
		if (mysql_num_rows($result) == 0) return "player_not_found";
		
		// allerede rapportert?
		if ($dupe = self::check($up_id, self::TYPE_PROFILE, 0, login::$user->player->id))
		{
			return $dupe;
		}
		
		return self::add($up_id, self::TYPE_PROFILE, 0, $message);
	}
	
	/**
	 * Internt: Sjekke om en liknende rapportering allerede er sendt inn
	 */
	private static function check($up_id, $type, $type_id, $source_up_id = NULL)
	{
		$up_id = (int)$up_id;
		$type = (int)$type;
		$type_id = (int)$type_id;
		
		// sjekke hvem som rapporterte?
		$more = '';
		if ($source_up_id)
		{
			$source_up_id = (int)$source_up_id;
			$more .= ' AND r_source_up_id = '.$source_up_id;
		}
		
		// finnes det en slik rad?
		$result = ess::$b->db->query("SELECT r_source_up_id, r_time FROM rapportering WHERE r_type = $type AND r_type_id = $type_id AND r_up_id = $up_id AND r_state < 2$more LIMIT 1");
		
		$row = mysql_fetch_assoc($result);
		if ($row) array_unshift($row, "dupe");
		
		return $row;
	}
	
	/**
	 * Internt: Legg til en rapportering
	 * @param int $up_id
	 * @param int $type
	 * @param int $type_id
	 * @param string $message
	 * @return int r_id
	 */
	private static function add($up_id, $type, $type_id, $message)
	{
		global $__server;
		
		// sørg for at brukeren er logget inn
		if (!login::$logged_in)
		{
			throw new HSException("Brukeren er ikke logget inn.");
		}
		
		// samle sammen data
		$source_up_id = intval(login::$user->player->id);
		$up_id = intval($up_id);
		$type = intval($type);
		$type_id = intval($type_id);
		$message = ess::$b->db->quote($message);
		
		// legg til
		ess::$b->db->query("INSERT INTO rapportering SET r_source_up_id = $source_up_id, r_up_id = $up_id, r_type = $type, r_type_id = $type_id, r_time = ".time().", r_note = $message");
		$id = ess::$b->db->insert_id();
		
		// melding på IRC
		putlog("CREWCHAN", "%bNY RAPPORTERING:%b {$__server['path']}/crew/rapportering");
		
		// øk rapporteringstelleren
		tasks::increment("rapporteringer");
		
		// returner iden
		return $id;
	}
	
	public static $data_prerequisite = array(
		"pm" => array(),
		"fr" => array(),
		"up_id" => array()
	);
	
	/**
	 * Hent data for å generere lenker
	 * @param array $rows data fra databasen
	 */
	public static function generate_prerequisite($rows)
	{
		$pm = array();
		$fr = array();
		$up_id = array();
		
		foreach ($rows as $row)
		{
			switch ($row['r_type'])
			{
				case rapportering::TYPE_PM:
					$pm[] = $row['r_type_id'];
				break;
				
				case rapportering::TYPE_FORUM_REPLY:
					$fr[] = $row['r_type_id'];
				break;
				
				case rapportering::TYPE_PROFILE:
				case rapportering::TYPE_SIGNATURE:
					$up_id[] = $row['r_up_id'];
				break;
			}
		}
		
		// hent data
		if (count($pm) > 0)
		{
			$result = ess::$b->db->query("SELECT im_id, im_it_id FROM inbox_messages WHERE im_id IN (".implode(",", $pm).")");
			while ($row = mysql_fetch_assoc($result))
			{
				self::$data_prerequisite['pm'][$row['im_id']] = $row['im_it_id'];
			}
		}
		if (count($fr) > 0)
		{
			$result = ess::$b->db->query("SELECT fr_id, fr_ft_id FROM forum_replies WHERE fr_id IN (".implode(",", $fr).")");
			while ($row = mysql_fetch_assoc($result))
			{
				self::$data_prerequisite['fr'][$row['fr_id']] = $row['fr_ft_id'];
			}
		}
		if (count($up_id) > 0)
		{
			$up_id = array_unique($up_id);
			$result = ess::$b->db->query("SELECT up_id, up_name FROM users_players WHERE up_id IN (".implode(",", $up_id).")");
			while ($row = mysql_fetch_assoc($result))
			{
				self::$data_prerequisite['up_id'][$row['up_id']] = $row['up_name'];
			}
		}
	}
	
	/**
	 * Generer lenke til det som er rapportert
	 * Husk at generate_prerequisite må være kalt på forhånd
	 */
	public static function generate_link($row)
	{
		global $__server;
		switch ($row['r_type'])
		{
			case rapportering::TYPE_PM:
				$it_id = isset(self::$data_prerequisite['pm'][$row['r_type_id']]) ? self::$data_prerequisite['pm'][$row['r_type_id']] : 0;
				return $__server['relative_path'].'/innboks_les?id='.$it_id.'&amp;goto='.$row['r_type_id'];
			
			case rapportering::TYPE_FORUM_TOPIC:
				return $__server['relative_path'].'/forum/topic?id='.$row['r_type_id'];
			
			case rapportering::TYPE_FORUM_REPLY:
				$id = isset(self::$data_prerequisite['fr'][$row['r_type_id']]) ? self::$data_prerequisite['fr'][$row['r_type_id']] : 0;
				return $__server['relative_path'].'/forum/topic?id='.$id.'&amp;replyid='.$row['r_type_id'];
			
			case rapportering::TYPE_PROFILE:
				return $__server['relative_path'].'/p/'.rawurlencode(self::$data_prerequisite['up_id'][$row['r_up_id']])."/".$row['r_up_id'];
			
			case rapportering::TYPE_SIGNATURE:
				return $__server['relative_path'].'/p/'.rawurlencode(self::$data_prerequisite['up_id'][$row['r_up_id']]).'/'.$row['r_up_id'].'?signature';
		}
		
		return false;
	}
}