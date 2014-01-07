<?php namespace Kofradia\Game;

class Utpressing {
	/**
	 * Energy it costs to perform a utpresing
	 *
	 * @var int
	 */
	const ENERGY = 100;
	
	/**
	 * Wait between each utpressing, in seconds
	 *
	 * @var int
	 */
	const DELAY_TIME = 240;

	/**
	 * Health and energy the target player looses
	 *
	 * @var array
	 */
	public static $affect = array(
		-2 => array(
			"health" => 50,
			"energy" => 100
		),
		-1 => array(
			"health" => 100,
			"energy" => 200
		),
		0 => array(
			"health" => 200,
			"energy" => 200
		),
		1 => array(
			"health" => 350,
			"energy" => 350
		),
		2 => array(
			"health" => 500,
			"energy" => 500
		)
	);

	/**
	 * Player
	 *
	 * @var player
	 */
	public $up;

	/**
	 * Options that can be performed
	 *
	 * @var array(\Kofradia\Game\Utpressing\Option, ..)
	 */
	public $options;

	/**
	 * Constructor
	 */
	public function __construct(\player $up)
	{
		$this->up = $up;
	}

	/**
	 * Get options
	 */
	public function getOptions()
	{
		if (!$this->options)
		{
			$this->options = array();
			$this->options[] = new Utpressing\Option(
				$this,
				"Mye penger", // text
				60, // prob
				9, // points
				0.6, // cash_min
				0.9, // cash_max
				45000 // cash_max_real
			);
			$this->options[] = new Utpressing\Option(
				$this,
				"Mye poeng",
				60,
				15,
				0.2,
				0.5,
				45000
			);
		}

		return $this->options;
	}

	/**
	 * Get specific option
	 *
	 * @param int Option ID
	 * @return \Kofradia\Utpressing\Option|null
	 */
	public function getOption($id)
	{
		$options = $this->getOptions();
		if (isset($options[$id]))
		{
			return $options[$id];
		}
	}

	/**
	 * Get health and energy the victim looses
	 *
	 * This is dependent on the rank difference
	 *
	 * Attacking a player with higher rank means the victim
	 * will loose more health and energy
	 *
	 * @param \Kofradia\Game\Rank\Points Rank of victim
	 * @return array(
	 *   health => int
	 *   energy => int)
	 */
	public function getAffectedTable(\Kofradia\Game\Rank\Points $rank_to)
	{
		$num_from = $this->up->getRank()->getPointsRank()->number;
		$num_to = $rank_to->number;

		$offset = $num_to - $num_from;
		$offset = max(-2, min(2, $offset));

		return self::$affect[$offset];
	}
	
	/**
	 * Calculate waiting time
	 *
	 * @return int Seconds to wait
	 */
	public function getWait()
	{
		if (\access::has("admin")) return 0;
		
		$wait = max(0, $this->up->data['up_utpressing_last'] + self::DELAY_TIME - time());
		
		return $wait;
	}
	
	/**
	 * Utfør utpressing
	 *
	 * @return \Kofradia\Game\Utpressing\Result
	 */
	public function utpress(Utpressing\Option $option)
	{
		// bruk energi
		$this->up->energy_use(self::ENERGY);

		return $option->attempt();
	}

	/**
	 * Get list of last utpressinger
	 *
	 * @param \pagei
	 * @param int Oldest log to show
	 * @return array
	 */
	public function getLast($pagei, $expire)
	{
		$result = $pagei->query("
			SELECT ut_affected_up_id, ut_b_id, ut_time
			FROM utpressinger
			WHERE ut_action_up_id = ? AND ut_time >= ?
			ORDER BY ut_time DESC", array($this->up->id, $expire));

		return $result->fetchAll();
	}
}