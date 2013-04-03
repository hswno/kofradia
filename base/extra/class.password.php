<?php

class password
{
	const LEVEL_STRONG = 1; // min 8, non-cap, cap, numbers
	const LEVEL_LONG = 2; // min 8
	const LEVEL_WEAK = 3; // min 3
	const LEVEL_LOGIN = 4; // minst 6 tegn
	const ERROR_SHORT = 1; // kort passord
	const ERROR_NONCAP = 2; // mangler små bokstaver
	const ERROR_CAP = 4; // mangler store bokstaver
	const ERROR_NUM = 8; // mangler nummer
	const ERROR_EASY = 16; // for lett passord
	
	/**
	 * @var PasswordHash
	 */
	private static $ph = null;
	
	/**
	 * Kontroller passord sikkerhet
	 *
	 * @param string passodet $password
	 * @param int nivået $level
	 * @return errors
	 */
	public static function validate($password, $level = self::LEVEL_STRONG)
	{
		$error = 0;
		$password = trim($password);
		
		switch ($level)
		{
			case self::LEVEL_STRONG:
				if (!preg_match("/[a-zæøå]/u", $password))
				{
					$error |= self::ERROR_NONCAP;
				}
				
				if (!preg_match("/[A-ZÆØÅ]/u", $password))
				{
					$error |= self::ERROR_CAP;
				}
				
				if (!preg_match("/\\d/u", $password))
				{
					$error |= self::ERROR_NUM;
				}
				
			case self::LEVEL_LONG:
				if (strlen($password) < 8)
				{
					$error |= self::ERROR_SHORT;
				}
			case self::LEVEL_LOGIN:
				if (strlen($password) < 6) $error |= self::ERROR_SHORT;
				if (strpos($password, "12345") !== false) $error |= self::ERROR_EASY;
			break;
				
			case self::LEVEL_WEAK:
				if (strlen($password) < 3) $error |= self::ERROR_SHORT;
			break;
			
			case self::LEVEL_LOGIN:
				if (strlen($password) < 3) $error |= self::ERROR_SHORT;
		}
		
		return $error;
	}
	
	/**
	 * Formatter tekst for bestemt passordfeil
	 * 
	 * @param int passordfeil $error
	 * @return string
	 */
	public static function format_errors($error)
	{
		$errors = array();
		
		if ($error & self::ERROR_SHORT) $errors[] = 'lengde';
		if ($error & self::ERROR_NONCAP) $errors[] = 'små bokstaver';
		if ($error & self::ERROR_CAP) $errors[] = 'store bokstaver';
		if ($error & self::ERROR_NUM) $errors[] = 'tall';
		
		return $errors;
	}
	
	/**
	 * Krypter (generer) passord hash/verdi
	 *
	 * @param string passordet $password
	 * @param string salt $salt
	 * @param string type $type
	 * @return unknown
	 */
	// krypter/generer passord verdi
	public static function hash($password, $salt = '', $type = null)
	{
		switch ($type)
		{
			case "combine":
				return md5(sha1($password . $salt) . $salt);
				
			case "user":
			case "bank_auth":
				// legg til md5-lag rundt for kompatibilitet
				$password = md5($password);
				
			default:
				// generere salt?
				if (!$salt)
				{
					$salt = self::generate_blowfish_salt();
				}
				
				return crypt($password, $salt);
		}
		
		throw new HSException("Unknown password hash type.");
	}
	
	/**
	 * Verifiser passord
	 */
	public static function verify_hash($password, $stored_hash, $type = null)
	{
		return self::hash($password, $stored_hash, $type) == $stored_hash;
	}
	
	/**
	 * Get PasswordHash-object
	 * @return PasswordHash
	 */
	private static function get_ph_object()
	{
		if (!self::$ph)
			self::$ph = new PasswordHash(8, false);
		
		return self::$ph;
	}
	
	/**
	 * Generer Blowfish-salt
	 */
	public static function generate_blowfish_salt()
	{
		// generere ved hjelp av PhPass-biblioteket
		$ph = self::get_ph_object();
		$salt = $ph->gensalt_blowfish($ph->get_random_bytes(16));
		
		return $salt;
	}
}