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

	public function __construct()
	{
		$this->debug = isset($_COOKIE['show_queries_info']);
		if (isset(\ess::$b->profiler))
		{
			\ess::$b->profiler->addDBProfiler($this);
		}
	}

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
				"statement"                 => $statement,
				"backtrace"                 => $this->getBacktrace()
			);
		}
	}

	/**
	 * Generate string for backtrace in debugging
	 *
	 * @return string
	 */
	protected function getBacktrace()
	{
		$backtrace = debug_backtrace(0);
		array_shift($backtrace);
		array_shift($backtrace);

		$ret = array();
		foreach ($backtrace as $row)
		{
			$msg = '';
			if (isset($row['class']))
			{
				$msg .= sprintf("%s%s%s",
					$row['class'],
					$row['type'],
					$row['function']);
			}
			else
			{
				$msg .= $row['function'];
			}

			$msg = str_pad($msg, 30, " ");

			if (isset($row['file']))
			{
				$msg .= sprintf(" (%s:%s)", $row['file'], $row['line']);
			}

			$ret[] = $msg;
		}

		return $ret;
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