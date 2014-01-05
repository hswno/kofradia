<?php namespace Kofradia\Game\Player;

/**
 * Rank-object for the player
 *
 * Each player will have this object, which itself will have a
 * reference to the points-rank, position-rank etc
 */
class Rank {
	/**
	 * Rank name for dead players
	 */
	const DEAD_NAME = 'Cadaveri Eccelenti';

	/**
	 * The points-rank
	 *
	 * @var \Kofradia\Game\Rank\Points
	 */
	public $pointsRank;

	/**
	 * The position-rank
	 *
	 * @var \Kofradia\Game\Rank\Pos
	 */
	public $posRank;

	/**
	 * Special rank if dead
	 *
	 * @var bool True if dead
	 */
	public $isDead = false;

	/**
	 * Special rank for crew access
	 *
	 * @var string|null
	 */
	public $accessTitle;

	/**
	 * Generate object
	 *
	 * @param int points
	 * @param int pos
	 * @param int access level (0 = dead)
	 */
	public function __construct($points, $pos = 0, $access_level = 0)
	{
		// set correct points-rank
		$this->pointsRank = \Kofradia\Game\Rank\Points::getRank($points);

		// set correct pos-rank
		$this->posRank = \Kofradia\Game\Rank\Pos::getRank($pos);

		// dead?
		if ($access_level == 0)
		{
			$this->isDead = true;
		}

		// access?
		if (isset(\ess::$g['ranks_access_levels'][$access_level]))
		{
			$this->accessTitle = \ess::$g['ranks_access_levels'][$access_level];
		}
	}

	/**
	 * Get name of current rank
	 *
	 * @return string
	 */
	public function getName()
	{
		$name = $this->pointsRank->name;
		$name_extra = null;

		if ($this->isDead)
		{
			$name_extra = static::DEAD_NAME;
		}

		elseif ($this->accessTitle)
		{
			$name_extra = $this->accessTitle;
		}

		elseif ($this->posRank)
		{
			$name_extra = $this->posRank->name;
		}

		else
		{
			return $name;
		}

		return sprintf("%s (%s)", $name_extra, $name);
	}
}