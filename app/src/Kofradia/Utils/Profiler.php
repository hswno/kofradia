<?php namespace Kofradia\Utils;

/**
 * Some very simple profiling of the code
 */
class Profiler {
	/**
	 * Tags
	 */
	protected $tags;

	/**
	 * Time of start
	 */
	protected $startTime;

	/**
	 * Database profiler
	 *
	 * @var array(\Kofradia\DB\Profiler, ..)
	 */
	protected $dbProfilers = array();

	/**
	 * Constructor
	 *
	 * @param float Time the script started
	 */
	public function __construct($start_time = null)
	{
		$this->startTime = $start_time ? $start_time : microtime(true);
	}

	/**
	 * Add DB profiler
	 *
	 * @param \Kofradia\DB\Profiler
	 */
	public function addDBProfiler(\Kofradia\DB\Profiler $profiler)
	{
		$this->dbProfilers[] = $profiler;
	}

	/**
	 * Get DB-times
	 *
	 * Loops over all DB-profilers and sum the time
	 *
	 * @return int
	 */
	public function getDBTime()
	{
		$t = 0;
		foreach ($this->dbProfilers as $profiler)
		{
			$t += $profiler->time;
		}
		return $t;
	}

	/**
	 * Get DB query count
	 *
	 * Loops over all DB-profilers and sum the count
	 *
	 * @return int
	 */
	public function getDBQueryCount()
	{
		$c = 0;
		foreach ($this->dbProfilers as $profiler)
		{
			$c += $profiler->num;
		}
		return $c;
	}

	/**
	 * Get total time
	 *
	 * @return float Time elapsed since profiler started
	 */
	public function getTime()
	{
		return microtime(true) - $this->startTime;
	}

	/**
	 * Add tag
	 *
	 * @param string Description to mark the tag
	 */
	public function tag($description)
	{
		$this->tags[] = array(
			"name"   => $description,
			"time"   => microtime(true),
			"dbtime" => $this->getDBTime()
		);
	}

	/**
	 * Format a pretty table
	 *
	 * @return string
	 */
	public function getPrettyTable()
	{
		$ret = 'profiler start';
		$this->tag("profiler end");

		$timeLast = $this->startTime;
		$timeLastDB = 0;
		foreach ($this->tags as $tag)
		{
			$lapsedLastAll = $tag['time'] - $timeLast;
			$lapsedLastDB  = $tag['dbtime'] - $timeLastDB;
			$lapsedTotal = round(($tag['time'] - $this->startTime), 4);

			$ret .= sprintf("\n                     script: %3.4f  db: %3.4f  sum: %3.4f     accum: %3.4f\n%s",
				$lapsedLastAll-$lapsedLastDB, $lapsedLastDB, $lapsedLastAll, $lapsedTotal, $tag['name']);
			$timeLast = $tag['time'];
			$timeLastDB = $tag['dbtime'];
		}

		return $ret;
	}
}
