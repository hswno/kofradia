<?php namespace Kofradia;

class DB {
	/**
	 * The active database object
	 *
	 * @var \Kofradia\DB\PDO
	 */
	protected static $pdo;

	/**
	 * Get the active database object
	 * Create it if needed
	 *
	 * @return \Kofradia\DB
	 */
	public static function get()
	{
		if (!isset(static::$pdo))
		{
			$obj = "\\Kofradia\\DB\\PDO";
			static::$pdo = new $obj(sprintf("mysql:host=%s;dbname=%s;charset=utf8", DBHOST, DBNAME), DBUSER, DBPASS);
			static::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			static::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		}

		return static::$pdo;
	}

	/**
	 * Get profiler
	 *
	 * @return \Kofradia\PDO\Profiler
	 */
	public static function getProfiler()
	{
		return static::get()->profiler;
	}

	/**
	 * Check if PDO is in use
	 *
	 * @return bool
	 */
	public static function isActive()
	{
		return !is_null(static::$pdo);
	}


	/**
	 * Quote text
	 *
	 * @param string
	 * @return string
	 */
	public static function quote($data)
	{
		if (empty($data))
		{
			return 'NULL';
		}
		
		return static::get()->quote($data);
	}

	/**
	 * Quote text not accepting NULL
	 *
	 * @param string
	 * @return string
	 */
	public static function quoteNoNull($data)
	{
		return static::get()->quote($data);
	}
}