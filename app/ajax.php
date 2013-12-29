<?php

/**
 * Diverse funksjoner for å behandle AJAX kall
 * @static
 */
class ajax
{
	/** Hent essentials */
	public static function essentials()
	{
		if (defined("SCRIPT_START")) return;
		define("SCRIPT_AJAX", true);
		require "essentials.php";
	}
	
	/** Forespørsel er ugyldig */
	const TYPE_INVALID = 1;
	
	/** Forespørselen er OK */
	const TYPE_OK = 2;
	
	/** Forespørselen finnes ikke */
	const TYPE_404 = 3;
	
	/** Standard retur */
	public static $type = self::TYPE_OK;
	
	/** Sett type header */
	public static function type_header($type)
	{
		switch ($type)
		{
			case self::TYPE_INVALID:
				header("HTTP/1.1 406 Not Acceptable");
			break;
			
			case self::TYPE_404:
				header("HTTP/1.1 404 Not Found");
			break;
			
			default:
				header("HTTP/1.1 200 OK");
		}
	}
	
	/** Sett headers */
	public static function set_headers()
	{
		// kan ikke sende headers hvis headers allerede er sendt
		if (headers_sent()) return;
		
		// sett riktig retur
		self::type_header(self::$type);
		
		// sett script tid
		if (defined("SCRIPT_START"))
		{
			header("X-HSW-Time: ".round(microtime(true)-SCRIPT_START, 4));
		}
		
		// sett database info
		if ($profiler = \Kofradia\DB::getProfiler())
		{
			header("X-HSW-Queries: ".$profiler->num);
			header("X-HSW-Queries-Time: ".round($profiler->time, 4));
		}
	}
	
	/** Print ren tekst */
	public static function text($data, $type = NULL)
	{
		header("Content-Type: text/plain; charset=utf-8");
		if ($type) self::$type = $type;
		self::set_headers();
		
		echo $data;
		die;
	}
	
	/** Print HTML */
	public static function html($data, $type = NULL)
	{
		header("Content-Type: text/html; charset=utf-8");
		if ($type) self::$type = $type;
		self::set_headers();
		
		echo $data;
		die;
	}
	
	/** Print XML */
	public static function xml($data, $type = NULL)
	{
		header("Content-Type: text/xml; charset=utf-8");
		if ($type) self::$type = $type;
		self::set_headers();
		
		echo '<?xml version="1.0" encoding="utf-8"?>'.$data;
		die;
	}
	
	/**
	 * Krev at brukerdata finnes og blir lastet inn
	 */
	public static function require_user()
	{
		// har vi lasta inn essentials?
		if (!defined("SCRIPT_START")) self::essentials();
		
		if (!login::$logged_in)
		{
			self::$type = self::TYPE_INVALID;
			self::text("ERROR:SESSION-EXPIRE", self::TYPE_INVALID);
		}
	}
	
	/**
	 * Valider session ID
	 * Må gis ved $_POST['sid']
	 */
	public static function validate_sid()
	{
		// har vi lasta inn essentials?
		if (!defined("SCRIPT_START")) self::essentials();
		
		// krev at vi har lastet inn brukerinfo
		if (!login::$logged_in)
		{
			self::text("ERROR:SESSION-EXPIRE", self::TYPE_INVALID);
		}
		
		// mangler SID?
		if (!isset($_POST['sid']))
		{
			self::text("ERROR:MISSING", ajax::TYPE_INVALID);
		}
		
		// ikke riktig SID?
		if ($_POST['sid'] != login::$info['ses_id'])
		{
			ajax::text("ERROR:WRONG-SESSION-ID", ajax::TYPE_INVALID);
		}
	}
	
	/**
	 * Krev at brukeren ikke har noen aktiv lås
	 * @param boolean $allow_crew tillate crew å vise siden?
	 */
	public static function validate_lock($allow_crew = false)
	{
		// har vi lås?
		if (login::check_lock())
		{
			// crew?
			if ($allow_crew && access::has("crewet")) return;
			
			// har vi ingen spiller?
			if (count(login::$user->lock) == 1 && in_array("player", login::$user->lock))
				ajax::text("ERROR:NO-PLAYER", ajax::TYPE_INVALID);
			
			// ikke tillatt
			ajax::text("ERROR:USER-RESTRICTED", ajax::TYPE_INVALID);
		}
	}
}