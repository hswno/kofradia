<?php

class wordpress_entries
{
	/** Adressen til RSS */
	private static $url = 'http://kofradia.no/blogg/feed/';
	
	/**
	 * Hent ut data fra RSS
	 */
	public static function get_changes()
	{
		global $_base;
		$url = self::$url;
		
		// hent xml
		$data = @file_get_contents($url);
		if (!$data)
		{
			return false;
		}
		
		// les xml
		try {
			$xml = new SimpleXMLElement($data);
		} catch (Exception $e)
		{
			sysreport::exception($e);
			return false;
		}
		
		// hent ut det vi skal ha
		$result = array();
		foreach ($xml->channel->item as $item)
		{
			$time = $_base->date->parse($item->pubDate)->format("U");
			#$author = isset($item->author) ? (string)$item->author : '';
			$result[] = array(
				"title" => (string)$item->title,
				#"author" => $author,
				"time" => $time,
				"link" => (string)$item->link,
				"description" => (string)$item->description,
				"category" => (string)$item->category
			);
		}
		
		return $result;
	}
	
	/**
	 * Sjekk etter nye ting
	 */
	public static function check_new($data)
	{
		global $_base, $_game;
		
		$last_prev = isset(game::$settings['wordpress_last']) ? intval(game::$settings['wordpress_last']['value']) : false;
		$last = 0;
		
		// hent siste data
		$last_data = isset(game::$settings['wordpress_last_data']) ? unserialize(game::$settings['wordpress_last_data']['value']) : NULL;
		
		// reverser data så nyeste kommer til slutt
		$data = array_reverse($data);
		
		// gå gjennom og se om noe er nyere
		$time_old = time()-600;
		foreach ($data as $row)
		{
			$last = max($row['time'], $last);
			
			// ny?
			if ($last_prev !== false && $row['time'] > $last_prev)
			{
				// loggmelding
				$time = $row['time'] < $time_old ? ' ('.$_base->date->get($row['time'])->format().')' : '';
				putlog("INFO", '%bNytt innlegg i bloggen:%b %u'.$row['title'].'%u'.$time.' '.$row['link']);
			}
		}
		
		// lagre hvis det var noe nytt
		if ($last > 0 && ($last_prev === false || $last_prev < $last || (isset($last_data) && ($row['link'] != $last_data['link'] || $row['title'] != $last_data['title']))))
		{
			$data = $_base->db->quote(serialize($row));
			$_base->db->query("REPLACE INTO settings SET name = 'wordpress_last', value = $last");
			$_base->db->query("REPLACE INTO settings SET name = 'wordpress_last_data', value = $data");
		}
	}
	
	/**
	 * Oppdater data
	 */
	public static function update_data()
	{
		global $_base;
		
		// hent data
		$data = self::get_changes();
		if (!$data) return false;
		
		// lagre ny data
		$data_sql = $_base->db->quote(serialize($data));
		$_base->db->query("INSERT INTO settings SET name = 'wordpress_data', value = $data_sql ON DUPLICATE KEY UPDATE value = $data_sql");
		
		// sjekk for ny data
		self::check_new($data);
		
		// last inn settings på nytt
		require PATH_APP."/scripts/update_db_settings.php";
	}
}