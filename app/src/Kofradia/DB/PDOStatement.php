<?php namespace Kofradia\DB;

class PDOStatement extends \PDOStatement {
	/**
	 * The PDO-object
	 *
	 * @var \Kofradia\DB\PDO
	 */
	public $pdo;

	/**
	 * Constructor
	 */
	protected function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	/**
	 * Debug this statement (show the results to the user)
	 */
	public function debug()
	{
		Debugger::debugResult($this);
	}

	/**
	 * Execute statement
	 *
	 * @param array $input_parameters
	 * @return bool
	 */
	public function execute($input_parameters = array())
	{
		$this->pdo->profiler->start();
		$res = parent::execute($input_parameters);
		$this->pdo->profiler->end($this->queryString);
		return $res;
	}
}