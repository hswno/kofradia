<?php namespace Kofradia\Forum;

class Helpers {
	/**
	 * Hent nyeste tråder og svar i forumet
	 */
	public static function getForumNew($limit = null)
	{
		$limit = (int) ($limit ?: 5);
		
		// hent forumdata
		$topics = \Kofradia\DB::get()->query("
			SELECT ft_id, ft_title, ft_time, ft_up_id, ft_fse_id, fse_name
			FROM forum_topics
				LEFT JOIN forum_sections ON ft_fse_id = fse_id
			WHERE fse_id IN (1,2,3) AND ft_deleted = 0
			ORDER BY ft_time DESC
			LIMIT $limit");
		$replies = \Kofradia\DB::get()->query("
			SELECT fr_id, fr_ft_id, fr_time, fr_up_id, ft_title, fse_name
			FROM forum_replies
				LEFT JOIN forum_topics ON fr_ft_id = ft_id AND ft_deleted = 0
				LEFT JOIN forum_sections ON ft_fse_id = fse_id
			WHERE fse_id IN (1,2,3) AND fr_deleted = 0
			ORDER BY fr_time DESC
			LIMIT $limit");
		
		$data = array();
		$times = array();
		while ($row = $topics->fetch())
		{
			$data[] = array(
				'topic_id' => $row['ft_id'],
				'time' => $row['ft_time'],
				'user' => $row['ft_up_id'],
				'title' => $row['ft_title'],
				'section' => $row['fse_name'],
				'reply' => false
			);
			$times[] = $row['ft_time'];
		}
		while ($row = $replies->fetch())
		{
			$data[] = array(
				'topic_id' => $row['fr_ft_id'],
				'reply_id' => $row['fr_id'],
				'time' => $row['fr_time'],
				'user' => $row['fr_up_id'],
				'title' => $row['ft_title'],
				'section' => $row['fse_name'],
				'reply' => true
			);
			$times[] = $row['fr_time'];
		}
		
		// sorter data
		array_multisort($times, SORT_DESC, SORT_NUMERIC, $data);
		
		return array_slice($data, 0, $limit);
	}
}