<?php

// initialize cacher
cache::init();

class cache
{
	/** Prefiks for nøkkelen */
	protected static $prefix = null;
	
	/** For å deaktivere caching */
	protected static $disabled = false;
	
	/**
	 * Cache-motor
	 * @var cache_engine
	 */
	protected static $engine;
	
	/**
	 * Hent data
	 * @param string $key
	 */
	public static function fetch($key)
	{
		if (self::$disabled) return false;
		return self::$engine->fetch(self::$prefix . $key);
	}
	
	/**
	 * Lagre data
	 * @param string $key
	 * @param mixed $data
	 * @param int $ttl seconds
	 */
	public static function store($key, $data, $ttl = 0)
	{
		if (self::$disabled) return false;
		return self::$engine->store(self::$prefix . $key, $data, $ttl);
	}
	
	/**
	 * Slett data
	 * @param string $key
	 */
	public static function delete($key)
	{
		if (self::$disabled) return false;
		return self::$engine->delete(self::$prefix . $key);
	}
	
	/**
	 * Initialize
	 */
	public static function init()
	{
		// apc?
		if (function_exists("apc_fetch"))
		{
			self::$engine = new cache_apc();
		}
		
		else
		{
			self::$engine = new cache_file();
		}
		
		#self::$disabled = true;
		
		// generer key prefiks
		self::$prefix = "smafia_" . (TEST_SERVER ? 'test_' : (!MAIN_SERVER ? 'beta_' : ''));
		self::$prefix .= ess::$s['session_prefix'];
	}
}

interface cache_engine
{
	public function fetch($key);
	public function store($key, $data, $ttl);
	public function delete($key);
}