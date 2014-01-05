<?php namespace Kofradia\Game\Rank;

/**
 * Rank by position
 */
class Pos extends Base {
	use BaseTrait;

	/**
	 * Type of rank (used by cache etc.)
	 */
	protected static $type = 'pos';

	/**
	 * Lowest position a player can be to have one of this ranks
	 *
	 * @var int
	 */
	public static $lowest_pos = 0;

	/**
	 * Fetch ranks from db
	 */
	protected static function fetchRanks()
	{
		$result = \Kofradia\DB::get()->query("
			SELECT pos, name
			FROM ranks_pos
			ORDER BY pos DESC");

		static::fetchRanksProcessResult($result);
	}

	/**
	 * Get rank for specified position
	 *
	 * @param int
	 * @return \Kofradia\Game\Rank\Pos
	 */
	public static function getRank($pos)
	{
		$res = null;
		foreach (static::getRanks() as $rank)
		{
			if ($pos <= $rank->pos)
			{
				$res = $rank;
			}
		}

		return $res;
	}

	/**
	 * Position the player must be to have this rank
	 *
	 * @var int
	 */
	public $pos;

	/**
	 * The name of the rank
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Set data from database
	 *
	 * @param array
	 */
	public function setData(array $data)
	{
		$this->pos  = $data['pos'];
		$this->name = $data['name'];

		if ($this->pos > static::$lowest_pos)
		{
			static::$lowest_pos = $this->pos;
		}
	}
}