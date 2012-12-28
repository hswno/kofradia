<?php

class trac
{
	/** Adressen til RSS for changesets */
	private static $url_changeset = 'https://kofradia.no/crewstuff/trac/timeline?changeset=on&max=50&daysback=90&format=rss';
	
	/** Adressen til RSS for andre endringer */
	private static $url_other = 'https://kofradia.no/crewstuff/trac/timeline?ticket=on&ticket_details=on&milestone=on&wiki=on&max=50&daysback=90&format=rss';
	
	/**
	 * Hent ut data fra Trac Timeline RSS (henter 50 siste)
	 * @param Changesets eller Tickets/wiki $changeset
	 */
	public static function get_changes($changeset = false)
	{
		global $_base;
		
		// hente subversion endringer
		if ($changeset)
		{
			$url = self::$url_changeset;
		}
		else
		{
			// hent annet
			$url = self::$url_other;
		}
		
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
			$author = isset($item->author) ? utf8_decode((string)$item->author) : '';
			$result[] = array(
				"title" => utf8_decode((string)$item->title),
				"author" => $author,
				"time" => $time,
				"link" => utf8_decode((string)$item->link),
				"description" => utf8_decode((string)$item->description),
				"category" => utf8_decode((string)$item->category)
			);
		}
		
		return $result;
	}
	
	/**
	 * Sjekk etter nye ting
	 */
	public static function check_new($data, $changeset)
	{
		global $_base, $_game;
		
		$name = $changeset ? "changeset" : "other";
		$last_prev = isset(game::$settings['trac_last_'.$name]) ? intval(game::$settings['trac_last_'.$name]['value']) : false;
		$last = 0;
		
		// reverser data så nyeste kommer til slutt
		$data = array_reverse($data);
		
		// gå gjennom og se om noe er nyere
		foreach ($data as $row)
		{
			$last = max($row['time'], $last);
			
			// ny?
			if ($last_prev !== false && $row['time'] > $last_prev)
			{
				// loggmelding
				putlog("CREWCHAN", '%bNy hendelse i Trac'.($changeset ? ' (Git)' : '').':%b %u'.$row['title'].'%u'.($row['author'] ? ' ('.$row['author'].')' : '').' '.$row['link']);
			}
		}
		
		// lagre hvis det var noe nytt
		if ($last > 0 && ($last_prev === false || $last_prev < $last))
		{
			$_base->db->query("REPLACE INTO settings SET name = 'trac_last_$name', value = $last");
			require ROOT."/base/scripts/update_db_settings.php";
		}
	}
	
	/**
	 * Oppdater data
	 */
	public static function update_data()
	{
		// hent data
		$data_changeset = self::get_changes(true);
		$data_other = self::get_changes();
		
		if (!$data_changeset || !$data_other) return false;
		
		// sjekk for ny data
		self::check_new($data_changeset, true);
		self::check_new($data_other, false);
		
		// sett opp data
		$data_changeset = self::format($data_changeset);
		$data_other = self::format($data_other);
		
		// lagre data
		$result = '<?php

// Trac RSS
// Generated '.date("r").'

global $_trac_rss;
$_trac_rss = array(
	"updated" => '.time().',
	"last_changeset" => '.$data_changeset[0].',
	"data_changeset" => '.$data_changeset[1].',
	"last_other" => '.$data_other[0].',
	"data_other" => '.$data_other[1].'
);';
		
		// lagre data
		return @file_put_contents(ROOT."/base/data/trac_rss.php", $result);
	}
	
	/**
	 * Formatter php data
	 * @return array(int last, string data)
	 */
	public static function format($data)
	{
		$result = array();
		$last = 0;
		
		foreach ($data as $item)
		{
			$arr = array();
			$arr[] = '"title" => "'.safe_val($item["title"]).'"';
			$arr[] = '"author" => "'.safe_val($item["author"]).'"';
			$arr[] = "'time' => ".intval($item["time"]);
			$arr[] = '"link" => "'.safe_val($item["link"]).'"';
			$arr[] = '"description" => "'.safe_val($item["description"]).'"';
			$arr[] = '"category" => "'.safe_val($item["category"]).'"';
			$result[] = "array(".implode(', ', $arr).")";
			$last = max($item['time'], $last);
		}
		
		$result = "array(\n\t\t".implode(",\n\t\t", $result)."\n\t)";
		
		return array($last, $result);
	}
}
