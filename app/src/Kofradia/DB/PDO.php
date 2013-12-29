<?php namespace Kofradia\DB;

class PDO extends \PDO {
	/**
	 * Profiler
	 *
	 * @var \Kofradia\DB\Profiler
	 */
	public $profiler;

	/**
	 * The transaction depth
	 * This is incremented on every beginTransaction, and decremented on commits
	 *
	 * @var int
	 */
	protected $transaction_depth = 0;

	/**
	 * Constructor
	 *
	 * @param string $dns
	 * @param string $username
	 * @param string $password
	 * @param array $driver_options
	 */
	public function __construct($dsn, $username = null, $password = null, $driver_options = null)
	{
		parent::__construct($dsn, $username, $password, $driver_options);
		$this->setAttribute(static::ATTR_STATEMENT_CLASS, array("\\Kofradia\\DB\\PDOStatement", array($this)));
		$this->profiler = new Profiler();
	}

	/**
	 * Begin transaction
	 *
	 * @return int The level/depth we are in (multiple calls increases the level). Returns 1 on first call.
	 */
	public function beginTransaction()
	{
		if ($this->transaction_depth++ == 0 || !$this->inTransaction())
		{
			$this->profiler->start();
			parent::beginTransaction();
			$this->profiler->end("beginTransaction");
		}

		return $this->transaction_depth;
	}

	/**
	 * Commit transaction
	 *
	 * @param bool Override transaction depth and commit anyways
	 * @return bool False if not at root depth. PDO's result elsewise
	 */
	public function commit($override = false)
	{
		if (--$this->transaction_depth <= 0 || $override)
		{
			$this->profiler->start();

			$this->transaction_depth = 0;
			$res = parent::commit();

			$this->profiler->end("commit");
			return $res;
		}

		return false;
	}

	/**
	 * Execute query
	 */
	public function exec($statement)
	{
		$this->profiler->start();
		$res = parent::exec($statement);
		$this->profiler->end($statement);
		return $res;
	}

	/**
	 * Get the transaction depth
	 *
	 * @return int
	 */
	public function getTransactionDepth()
	{
		return $this->transaction_depth;
	}

	/**
	 * Roll back transaction
	 *
	 * @param bool Override transaction depth and rollback anyways
	 * @return bool False if not at root depth. PDO's result elsewise
	 */
	public function rollBack($override = false)
	{
		// deny rollback of nested transactions
		// this is because all the other code is not prepared for it...
		if ($this->transaction_depth > 1)
		{
			throw new \HSException("Support for nested rollback unavailable.");
		}

		if (--$this->transaction_depth <= 0 || $override)
		{
			$this->profiler->start();

			$this->transaction_depth = 0;
			$res = parent::rollBack();

			$this->profiler->end("rollback");
			return $res;
		}

		return false;
	}

	/**
	 * Start debugging
	 */
	public function startDebug()
	{
		$this->profiler->debug = true;
	}
}