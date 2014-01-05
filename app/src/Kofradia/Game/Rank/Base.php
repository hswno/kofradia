<?php namespace Kofradia\Game\Rank;

/**
 * Ranks
 *
 * The child-classes must use the BaseTrait-trait
 */
abstract class Base {
	/**
	 * Get list of ranks
	 *
	 * @return array(\Kofradia\Game\Rank\Base, ..)
	 */
	public static function getRanks()
	{
		if (!static::$by_number)
		{
			// check cache first, then get from DB
			if ($cache = \cache::fetch("ranks-".static::$type))
			{
				static::$by_number = $cache;
				return $cache;
			}

			static::fetchRanks();
			\cache::store("ranks-".static::$type, static::$by_number);
		}

		return static::$by_number;
	}

	/**
	 * Fetch ranks from db
	 */
	abstract protected static function fetchRanks();

	/**
	 * Process resultset from database
	 *
	 * @param \PDOStatement
	 */
	public static function fetchRanksProcessResult(\PDOStatement $result)
	{
		$last = null;
		$i = 1;
		while ($row = $result->fetch())
		{
			$rank = new static();
			$rank->setData($row);
			$rank->setNum($i);
			static::$by_number[$i] = $rank;

			if ($last)
			{
				$rank->setPrev($last);
				$last->setNext($rank);
			}

			$last = $rank;
			$i++;
		}
	}

	/**
	 * Previous rank
	 *
	 * @var \Kofradia\Game\Rank\Base
	 */
	public $prev;

	/**
	 * Next rank
	 *
	 * @var \Kofradia\Game\Rank\Base
	 */
	public $next;

	/**
	 * The number this rank is (1 is lowest/first rank)
	 */
	public $number;

	/**
	 * Set data from database
	 *
	 * @param array
	 */
	abstract public function setData(array $data);

	/**
	 * Set rank number
	 *
	 * @param int
	 */
	public function setNum($i)
	{
		$this->number = $i;
	}

	/**
	 * Set previous rank
	 *
	 * @param \Kofradia\Game\Rank
	 */
	public function setPrev(\Kofradia\Game\Rank\Base $rank)
	{
		$this->prev = $rank;
	}

	/**
	 * Set next rank
	 *
	 * @param \Kofradia\Game\Rank
	 */
	public function setNext(\Kofradia\Game\Rank\Base $rank)
	{
		$this->next = $rank;
	}
}