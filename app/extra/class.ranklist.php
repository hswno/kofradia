<?php

class ranklist
{
	/**
	 * Flush rank lista
	 */
	public static function flush()
	{
		// slett gamle lista
		\Kofradia\DB::get()->exec("TRUNCATE users_players_rank");
		
		// overfør spillerdata
		\Kofradia\DB::get()->exec("
			INSERT IGNORE INTO users_players_rank (upr_up_id, upr_up_access_level, upr_up_points)
			SELECT up_id, up_access_level, up_points
			FROM users_players");
		
		// oppdater lista med korrekte plasseringer
		self::update();
	}
	
	/**
	 * Oppdater ranklista
	 */
	public static function update()
	{
		\Kofradia\DB::get()->exec("SET @num = 1, @rank = 0, @p := NULL, @nc := NULL");
		\Kofradia\DB::get()->exec("
			UPDATE users_players_rank m, (
				SELECT
					upr_up_id,
					@nc := upr_up_access_level >= ".ess::$g['access_noplay']." OR upr_up_access_level = 0,
					@rank := IF(@rank = 0 OR @p > upr_up_points, @num, @rank) AS new_rank_pos,
					@num := IF(@nc, @num, @num + 1),
					@p := IF(@nc, @p, upr_up_points)
				FROM users_players_rank
				ORDER BY upr_up_points DESC
			) r
			SET m.upr_rank_pos = r.new_rank_pos
			WHERE m.upr_up_id = r.upr_up_id");
	}
}