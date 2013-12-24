<?php namespace Kofradia;

class Settings {

	/**
	 * Load settings and save to cache
	 */
	public static function reload()
	{
		$result = \ess::$b->db->query("SELECT id, name, value FROM settings");

		\game::$settings = array();
		while ($row = mysql_fetch_assoc($result))
		{
			\game::$settings[$row['name']] = array("id" => $row['id'], "value" => $row['value']);
		}

		// keep for 1 hour
		\cache::store("settings", \game::$settings, 3600);
	}

	/**
	 * Retrieve a setting from cache
	 *
	 * @param string  Name of setting
	 * @param mixed   To be returned if setting don't exist
	 * @return mixed  String if setting found
	 */
	public static function get($name, $alternative = null)
	{
		if (!isset(\game::$settings[$name]))
		{
			return $alternative;
		}

		return \game::$settings[$name]['value'];
	}
}