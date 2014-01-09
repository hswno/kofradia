<?php namespace Kofradia\Users;

class Stats {
	/**
	 * Hent beste ranker siste 24 timer
	 */
	public static function getBestRankers($limit = null)
	{
		$limit = (int) ($limit ?: 1);
		
		// tidsperiode
		$d = \ess::$b->date->get();
		$a = $d->format("H") < 21 ? 2 : 1;
		$d->modify("-$a day");
		$d->setTime(21, 0, 0);
		$date_from = $d->format("U");
		
		$d->modify("+1 day");
		$date_to = $d->format("U");
		
		// hent spiller
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, sum_uhi_points, up_points, up_last_online, up_profile_image_url, upr_rank_pos
			FROM
				(
					SELECT uhi_up_id, SUM(uhi_points) sum_uhi_points
					FROM users_hits
					WHERE uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to
					GROUP BY uhi_up_id
					HAVING sum_uhi_points > 0
					ORDER BY sum_uhi_points DESC
					LIMIT $limit
				) ref,
				users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE uhi_up_id = up_id");
		
		if ($result->rowCount() == 0) return array();
		
		$players = array();
		$up_id = array();
		while ($row = $result->fetch())
		{
			$row['ff_links'] = array();
			$players[$row['up_id']] = $row;
			$up_id[] = $row['up_id'];
		}
		
		// hent familier hvor spilleren er medlem
		$ff = \ff::get_ff_list($up_id, \ff::TYPE_FAMILIE);
		foreach ($ff as $row)
		{
			$players[$row['ffm_up_id']]['ff'][] = $row;
			$players[$row['ffm_up_id']]['ff_links'][] = $row['link'];
		}
		
		return $players;
	}
}