<?php namespace Kofradia\Controller\Templating;

class Helpers extends \Kofradia\Controller {
	public function action_profilerinfo()
	{
		$profiler = \Kofradia\DB::getProfiler();

		return \Kofradia\View::forgeTwig('templates/helpers/profiler', array(
			"script_time"   => round(microtime(true)-SCRIPT_START-$profiler->time, 4),
			"database_time" => round($profiler->time, 4),
			"num_queries"   => $profiler->num));
	}

	/**
	 * Hent beste ranker siste 24 timer
	 */
	public function action_bestranker()
	{
		$stats = new \Kofradia\Users\Stats();
		$players = $stats->getBestRankers();

		if (count($players) == 0)
		{
			return \Kofradia\View::forgeTwig('users/login/helpers/best_ranker', array(
				"player" => null
			));
		}

		$player = reset($players);

		return \Kofradia\View::forgeTwig('users/login/helpers/best_ranker', array(
			"player"       => $player,
			"img"          => \player::get_profile_image_static($player['up_profile_image_url']),
			"rank"         => new \Kofradia\Game\Player\Rank($player['up_points'], $player['upr_rank_pos'], $player['up_access_level']),
			"profile_link" => \game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level'])
		));
	}

	/**
	 * Hent boks med forumsvar
	 */
	public function action_forum_box()
	{
		$new = \Kofradia\Forum\Helpers::getForumNew();
		foreach ($new as &$row)
		{
			$row['url'] = '/forum/topic?id='.$row['topic_id'].($row['reply'] ? '&replyid='.$row['reply_id'] : '');
		}

		return \Kofradia\View::forgeTwig('users/login/helpers/forumbox', array(
			"new" => $new
		));
	}

	/**
	 * Hent boks med livefeed
	 */
	public function action_livefeed()
	{
		$lf = \livefeed::get_latest(3);

		return \Kofradia\View::forgeTwig('users/login/helpers/livefeed', array(
			"data" => $lf
		));
	}
}