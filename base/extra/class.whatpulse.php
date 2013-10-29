<?php

// whatpulse stæsj
global $_whatpulse;
$_whatpulse = array(
	"fields_text" => array(
		"UserID" => "Bruker ID",
		"AccountName" => "Kontonavn",
		"GeneratedTime" => "Sist oppdatert",
		"DateJoined" => "Opprettet",
		"Keys" => "Antall tastetrykk",
		"AvKPS" => "Tastetrykk/sekund",
		"Clicks" => "Antall museklikk",
		"AvCPS" => "Museklikk/sekund",
		"LastPulse" => "Siste pulse",
		"Pulses" => "Antall pulse",
		"Rank" => "Rankering",
		"Team" => "Team Navn"
	),
	
	// 0 => PlainText, 1 => Number, 2 => DateTime, 3 => Date, 4 => Precision, 5 => Miles->KM
	"types" => array(
		"UserID" => 0,
		"AccountName" => 0,
		"GeneratedTime" => 2,
		"DateJoined" => 3,
		"Keys" => 1,
		"AvKPS" => 4,
		"Clicks" => 1,
		"AvCPS" => 4,
		"LastPulse" => 3,
		"Pulses" => 1,
		"Rank" => 1,
		"Team" => 6
	)
);

/**
 * WhatPulse objekt
 */
class whatpulse
{
	/** WhatPulse bruker ID */
	public $user_id = 0;
	
	/** XML objektet */
	public $xml = false;
	
	/** Data for brukeren dette gjelder (fra stats_whatpulse tabellen) (sw_userid, sw_time_update, sw_xml, sw_params) */
	public $data = false;
	
	/**
	 * Params objektet
	 * @var params
	 */
	public $params = false;
	
	/** Constructor */
	public function __construct($user_id = 0)
	{
		$this->user_id = intval($user_id);
	}
	
	/** Hent whatpulse info for en spiller */
	public function load_user($up_id)
	{
		global $_base;
		
		$result = $_base->db->query("SELECT sw_userid, sw_time_update, sw_xml, sw_params FROM stats_whatpulse WHERE sw_up_id = ".intval($up_id));
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		
		$this->set_user_data(mysql_fetch_assoc($result));
		return true;
	}
	
	/** Sett brukerinfo */
	public function set_user_data($data)
	{
		$this->data = $data;
		$this->user_id = $this->data['sw_userid'];
		$this->params = new params($this->data['sw_params']);
	}
	
	/** Hent XML-data fra whatpulse.org */
	public function get_xml()
	{
		return @file_get_contents("http://whatpulse.org/api/user.php?UserID=" . $this->user_id);
	}
	
	/** Les/parse XML-data */
	protected function read_xml($xml_data)
	{
		// har ikke noe data?
		if ($xml_data == "")
		{
			$this->xml = false;
			return false;
		}
		
		// forsøk å les XML data
		try
		{
			return @new SimpleXMLElement($xml_data);
		} catch (Exception $e)
		{
			// feilet
			sysreport::exception_caught($e);
			$this->xml = false;
		}
		
		return false;
	}
	
	/** Hent ut data for brukeren og sørg for at den er oppdatert */
	public function update($data = NULL, $force_update = NULL)
	{
		global $_base;
		
		// hente data lokalt?
		if ($data === NULL && $this->data)
		{
			$data = $this->data['sw_xml'];
		}
		
		// forsøk å lese data
		$xml = $this->read_xml($data);
		$update = $force_update !== false;
		
		// sjekk om data ikke skal oppdateres
		if ($xml && $force_update === NULL)
		{
			$last_real_update = $_base->date->parse($xml->GeneratedTime)->format("U");
			
			// gått mindre enn en time?
			if ($last_real_update > time()-3600)
			{
				// ikke oppdater data
				$update = false;
			}
		}
		
		// oppdatere data?
		if ($update)
		{
			$data = $this->get_xml();
			$xml_update = $this->read_xml($data);
			
			// gyldig?
			if ($xml_update)
			{
				$xml = $xml_update;
				
				// lagre data
				$_base->db->query("UPDATE stats_whatpulse SET sw_time_update = ".time().", sw_xml = ".$_base->db->quote($data)." WHERE sw_userid = $this->user_id");
			}
		}
		
		$this->xml = $xml;
		return (bool) $xml;
	}
	
	/** Hent statistikk info */
	public function stat_info($field)
	{
		// seperator?
		if ($field == "-")
		{
			return array("&nbsp;", "&nbsp;");
		}
		
		// ukjent?
		if (!isset($this->xml->$field))
		{
			return array("ukjent", "ukjent felt: ".htmlspecialchars($field));
		}
		
		global $_base, $_whatpulse;
		
		// har vi tittel?
		if (isset($_whatpulse['fields_text'][$field]))
		{
			$title = htmlspecialchars($_whatpulse['fields_text'][$field]);
		}
		else
		{
			$title = htmlspecialchars($field);
		}
		
		
		// finn verdi
		$value = $this->xml->$field;
		
		$types = $_whatpulse['types'];
		$type = isset($types[$field]) ? $types[$field] : 0;
		
		switch ($type)
		{
			// Number
			case 1: $value = game::format_number($value); break;
			
			// DateTime
			case 2: $value = $_base->date->get(strtotime($value))->format(); break;
			
			// Date
			case 3: $value = $_base->date->get(strtotime($value))->format(date::FORMAT_NOTIME); break;
			
			// Precision (2)
			case 4: $value = game::format_number($value, 2); break;
			
			// Miles
			case 5: $value = game::format_number($value*1.609344, 1) . " km"; break;
			
			// TeamName
			case 6: $value = $_whatpulse['Team']['Name'];
			
			// Tekst
			default: $value = htmlspecialchars($value);
		}
		
		return array($title, $value);
	}
}