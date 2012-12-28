<?php

/**
 * Klasse for å modifisere/generere htdigest filer/passord
 */
class htdigest
{
	/**
	 * Generer digest
	 * @param string brukernavn $username
	 * @param string realm $realm
	 * @param string passord $password
	 * @return string md5 digest
	 */
	public static function generate_digest($username, $realm, $password)
	{
		// digest: md5(username:realm:pass)
		// username:realm:digest
		return md5("$username:$realm:$password");
	}
	
	/**
	 * Realms med data
	 * array("realms" => array("user" => pass, ..))
	 */
	protected $realms = array();
	
	/**
	 * Opprett digest objektet
	 * @param string data $data
	 */
	public function __construct($data)
	{
		$lines = explode("\n", $data);
		foreach ($lines as $line)
		{
			$line = trim($line);
			if (empty($line)) continue;
			
			$info = @explode(":", $line, 3);
			if (!isset($info[2])) continue;
			
			$this->realms[$info[1]][$info[0]] = $info[2];
		}
	}
	
	/**
	 * Hent oversikt over realms
	 * @return array realms
	 */
	public function get_realms()
	{
		$realms = array_keys($this->realms);
		sort($realms);
		return $realms;
	}
	
	/**
	 * Hent oversikt over brukere i en realm
	 * @param string realm $realm
	 * @return array brukernavn
	 */
	public function get_users($realm)
	{
		if (!isset($this->realms[$realm])) return array();
		$users = array_keys($this->realms[$realm]);
		sort($users);
		return $users;
	}
	
	/**
	 * Se om en bruker finnes
	 * @param string brukernavn $username
	 * @param string realm $realm
	 * @return boolean
	 */
	public function is_user($username, $realm)
	{
		return isset($this->realms[$realm][$username]);
	}
	
	/**
	 * Legg til bruker/endre passord
	 * @param string brukernavn $username
	 * @param string realm $realm
	 * @param string passord $password
	 */
	public function set_password($username, $realm, $password)
	{
		$digest = self::generate_digest($username, $realm, $password);
		$this->realms[$realm][$username] = $digest;
	}
	
	/**
	 * Fjern bruker
	 * @param string brukernavn $username
	 * @param string realm $realm
	 * @return boolean success
	 */
	public function remove_user($username, $realm)
	{
		if ($this->is_user($username, $realm))
		{
			unset($this->realms[$realm][$username]);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Generer data
	 * @return string data
	 */
	public function generate_data()
	{
		$data = array();
		foreach ($this->realms as $realm => $users)
		{
			foreach ($users as $user => $digest)
			{
				$data[] = "$user:$realm:$digest";
			}
		}
		
		return implode("\n", $data);
	}
}