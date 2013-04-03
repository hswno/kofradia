<?php

class tasks
{
	/** Lokal cache */
	private static $cache = null;
	
	/** Hent oppgaver */
	private static function load($skip_cache = false)
	{
		// forsøk å hent fra cache
		if (!$skip_cache && !self::$cache)
		{
			self::$cache = cache::fetch("tasks");
		}
		
		// hent fra databasen
		if ($skip_cache || !self::$cache)
		{
			global $_base;
			$result = $_base->db->query("SELECT t_name, t_ant, t_last FROM tasks");
			
			// les data
			self::$cache = array();
			while ($row = mysql_fetch_assoc($result))
			{
				self::$cache[$row['t_name']] = $row;
			}
			
			// lagre til cache
			cache::store("tasks", self::$cache);
		}
	}
	
	/** Hent ut info om en oppgave */
	public static function get($name)
	{
		// sørg for data
		if (!self::$cache) self::load();
		
		// fant ikke
		if (!isset(self::$cache[$name]))
		{
			sysreport::log("Fant ikke oppgaven $name", "tasks::get()");
			return false;
		}
		
		return self::$cache[$name];
	}
	
	/** Øk telleren for en oppgave */
	public static function increment($name)
	{
		global $_base;
		
		// forsøk å øk telleren
		$_base->db->query("UPDATE tasks SET t_ant = t_ant + 1 WHERE t_name = ".$_base->db->quote($name));
		
		if ($_base->db->affected_rows() == 0)
		{
			sysreport::log("Fant ikke oppgaven $name", "tasks::increment()");
			return false;
		}
		
		// oppdater cache
		self::load(true);
		
		return true;
	}
	
	/**
	 * Senk telleren for en oppgave
	 * @param string $name
	 * @param integer $count
	 */
	public static function mark($name, $count = 1)
	{
		global $_base;
		$count = max(1, (int) $count);
		
		// forsøk å senke telleren
		$_base->db->query("UPDATE tasks SET t_ant = t_ant - 1 WHERE t_name = ".$_base->db->quote($name));
		
		if ($_base->db->affected_rows() == 0)
		{
			sysreport::log("Fant ikke oppgaven $name", "tasks::mark()");
			return false;
		}
		
		// oppdater cache
		self::load(true);
		
		return true;
	}
	
	/**
	 * Sett telleren til bestemt verdi
	 * @param string $name
	 * @param integer $count
	 */
	public static function set($name, $value)
	{
		global $_base;
		$value = (int) $value;
		
		// forsøk å sett telleren til bestemt verdi
		$_base->db->query("UPDATE tasks SET t_ant = $value WHERE t_name = ".$_base->db->quote($name));
		$affected = $_base->db->affected_rows();
		
		// oppdater cache
		self::load(true);
		
		return $affected;
	}
}