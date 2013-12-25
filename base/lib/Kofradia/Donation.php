<?php namespace Kofradia;

class Donation {
	/**
	 * Get donations
	 * Sorted by newest first
	 *
	 * @param \pagei $pagei
	 * @return array(\Kofradia\Donasjon, ..)
	 */
	public static function getDonations(\pagei $pagei = null)
	{
		$q = "
			SELECT d_up_id, d_time, d_amount
			FROM donations
			ORDER BY d_time DESC";
		$result = $pagei ? $pagei->query($q) : \ess::$b->db->query($q);

		$list = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$list[] = static::load($row);
		}

		return $list;
	}

	/**
	 * Data about the donation
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Create new object from data
	 *
	 * @param array $data
	 * @return \Kofradia\Donasjon
	 */
	protected static function load(array $data)
	{
		$d = new static();
		$d->data = $data;
		return $d;
	}

	/**
	 * Get time
	 *
	 * @return int
	 */
	public function getTime()
	{
		return $this->data['d_time'];
	}

	/**
	 * Get player ID
	 *
	 * @return int
	 */
	public function getPlayerID()
	{
		return $this->data['d_up_id'];
	}
}