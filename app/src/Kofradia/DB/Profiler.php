<?php namespace Kofradia\DB;

class Profiler {
	/**
	 * Debug mode?
	 *
	 * @var bool
	 */
	public $debug = false;

	/**
	 * Time last query started
	 *
	 * @var float
	 */
	protected $lastStart;

	/**
	 * Time last query ended
	 *
	 * @var float
	 */
	protected $lastEnd;

	/**
	 * Number of statements
	 *
	 * @var int
	 */
	public $num = 0;

	/**
	 * List of statements, filled if startDebug is called
	 *
	 * @var array
	 */
	public $statements = array();

	/**
	 * Total time used on statements
	 */
	public $time;

	/**
	 * End profiler
	 *
	 * @param string The statement that was executed
	 */
	public function end($statement)
	{
		$timeSinceLast = ($this->lastEnd ? $this->lastStart - $this->lastEnd : 0);
		$this->lastEnd = microtime(true);
		$duration      = $this->lastEnd - $this->lastStart;
		$this->time   += $duration;

		// debug?
		if ($this->debug)
		{
			$this->statements[] = array(
				"time_since_script_start"   => round($this->lastEnd - SCRIPT_START, 6) * 1000,
				"time_since_last_statement" => round($timeSinceLast, 6) * 1000,
				"statement_time"            => round($duration, 6) * 100,
				"statement_info"            => "TODO",
				"statement"                 => $statement
			);
		}
	}

	/**
	 * Get statistics
	 *
	 * @return array
	 */
	public function getStats()
	{
		return array(
			"lastStart"  => $this->lastStart,
			"lastEnd"    => $this->lastEnd,
			"num"        => $this->num,
			"statements" => $this->statements,
			"time"       => $this->time
		);
	}

	/**
	 * Start profiler
	 */
	public function start()
	{
		$this->lastStart = microtime(true);
		$this->num++;
	}
}