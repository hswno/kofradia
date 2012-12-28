<?php

/**
 * Funksjoner for behandling av bomberom
 */
class bomberom
{
	/**
	 * Antall plasser det blir gjort ledig i bomberom avhengig av antall pålogget siste 48 timer
	 */
	const CAPACITY_FACTOR = 0.35;
	
	/**
	 * Ventetid før vi kan sette et nytt broderskapmedlem i bomberom
	 */
	const FAMILIY_MEMBERS_WAIT = 43200; // 12 timer
	
	/**
	 * Maksimalt antall timer man kan plassere seg i bomberom
	 */
	const MAX_HOURS = 48;
	
	/**
	 * Pris per time å sitte i bomberommet
	 */
	const PRICE_HOUR = 2000;
	
	/**
	 * Hvor mye prisen øker per time multiplisert med antall i bomberommet 
	 */
	const PRICE_EACH_PLAYER = 2000;
	
	/**
	 * Hvor mye prisen øker (faktor) når man plasserer en annen spiller i bomberom
	 */
	const PRICE_FACTOR_OTHER = 3;
	
	/**
	 * Prisfaktor når man setter seg selv i eget bomberom-firma
	 */
	const PRICE_FACTOR_OWN = 0.25;
	
	/**
	 * Juster kapasiteten i bomberommene
	 */
	public static function adjust_capacity()
	{
		// finn antall pålogget siste 48 timer
		$expire = time() - 86400 * 2;
		$result = ess::$b->db->query("SELECT COUNT(*) FROM users_players WHERE up_access_level != 0 AND up_last_online > $expire");
		$ant_online = mysql_result($result, 0);
		
		// for julaften og nyttår
		$d = array("12-24", "12-30", "12-31");
		$f = 1;
		if (in_array(ess::$b->date->get()->format("m-d"), $d))
		{
			$f = 3; // 3 ganger så mange plasser
		}
		
		// antall som skal fordeles (minimum 5 stk)
		$ant_fordeles = max(5, ceil($ant_online * self::CAPACITY_FACTOR * $f));
		
		ess::$b->db->begin();
		
		// hent ut alle bomberommene
		$result = ess::$b->db->query("SELECT ff_id, ff_params FROM ff WHERE ff_type = 4 AND ff_inactive = 0 FOR UPDATE");
		$bomberom = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['rest'] = 0; // antall ekstra plasser det skal settes av (de som blir fordelt tilfeldig)
			$bomberom[] = $row;
		}
		
		// ingen bomberom?
		$ant_bomberom = count($bomberom);
		if ($ant_bomberom == 0)
		{
			putlog("LOG", "BOMBEROM KAPASITET: Ingen bomberom eksisterer.");
			ess::$b->db->commit();
			return;
		}
		
		// fordel plasser på bomberommene
		$per_bomberom = floor($ant_fordeles / $ant_bomberom);
		$rest = $ant_fordeles % $ant_bomberom;
		
		// eksta å fordele tilfeldig?
		if ($rest > 0)
		{
			// plukk ut tilfeldige bomberom
			$tilfeldige = (array) array_rand($bomberom, $rest);
			foreach ($tilfeldige as $key)
			{
				$bomberom[$key]['rest']++;
			}
		}
		
		// oppdater bomberommene
		foreach ($bomberom as $row)
		{
			// antall bomberommet skal ha plass til
			$ant = $row['rest'] + $per_bomberom;
			
			// frihavnen får dobbelt så mange plasser
			if ($row['ff_id'] == 44) $ant += $per_bomberom;
			
			// oppdater
			$params = new params($row['ff_params']);
			$params->update("bomberom_kapasitet", $ant);
			
			// lagre
			ess::$b->db->query("UPDATE ff SET ff_params = ".ess::$b->db->quote($params->build())." WHERE ff_id = {$row['ff_id']}");
		}
		
		// lagre
		ess::$b->db->commit();
	}
}