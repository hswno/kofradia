<?php namespace Kofradia\Game\Rank;

/**
 * Rank by points
 */
class Points extends Base {
	use BaseTrait;

	/**
	 * Type of rank (used by cache etc.)
	 */
	protected static $type = 'points';

	/**
	 * Fetch ranks from db
	 */
	protected static function fetchRanks()
	{
		$result = \Kofradia\DB::get()->query("
			SELECT id, name, points, rank_max_health, rank_max_energy
			FROM ranks
			ORDER BY points");

		static::fetchRanksProcessResult($result);
	}

	/**
	 * Get rank for specified points
	 *
	 * @param int
	 * @return \Kofradia\Game\Rank\Points
	 */
	public static function getRank($points)
	{
		$res = null;
		foreach (static::getRanks() as $rank)
		{
			// first rank is always assigned,
			// regardless of the points requirement
			if (!$res)
			{
				$res = $rank;
			}

			else if ($rank->points <= $points)
			{
				$res = $rank;
			}
		}

		return $res;
	}

	/**
	 * The ID for the rank in the database
	 *
	 * @var int
	 */
	public $id;

	/**
	 * The name of the rank
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Points needed to have this rank
	 *
	 * @var int
	 */
	public $points;

	/**
	 * Maximum health a player can have with this rank
	 *
	 * @var int
	 */
	public $rank_max_health;

	/**
	 * Maximum energy a player can have with this rank
	 *
	 * @var int
	 */
	public $rank_max_energy;

	/**
	 * Required points to next rank
	 *
	 * @var int
	 */
	public $points_to_next;

	/**
	 * Set data from database
	 *
	 * @param array
	 */
	public function setData(array $data)
	{
		$this->id              = $data['id'];
		$this->name            = $data['name'];
		$this->points          = $data['points'];
		$this->rank_max_health = $data['rank_max_health'];
		$this->rank_max_energy = $data['rank_max_energy'];
	}

	/**
	 * Set previous rank (extends)
	 *
	 * @param \Kofradia\Game\Rank
	 */
	public function setPrev(\Kofradia\Game\Rank\Base $rank)
	{
		parent::setPrev($rank);
		$this->points_to_next = $this->points - $this->prev->points;
	}
}