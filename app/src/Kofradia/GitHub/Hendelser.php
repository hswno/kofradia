<?php namespace Kofradia\GitHub;

class Hendelser {
	/**
	 * Get events
	 *
	 * @param \pagei
	 * @return array(\Kofradia\GitHub\Event, ..)
	 */
	public static function getEvents(\pagei $pagei)
	{
		$result = $pagei->query("
			SELECT gl_id, gl_time, gl_event_type, gl_contents, gl_log_count
			FROM github_log
			ORDER BY gl_id DESC");
		return static::parseResult($result);
	}

	/**
	 * Get events later than specified id
	 *
	 * @param int The id, not inclusive
	 * @return array(\Kofradia\GitHub\Event, ..)
	 */
	public static function getEventsSinceId($id)
	{
		$id = (int) $id;
		$result = \ess::$b->db->query("
			SELECT gl_id, gl_time, gl_event_type, gl_contents, gl_log_count
			FROM github_log
			WHERE gl_id > $id
			ORDER BY gl_id DESC");
		return static::parseResult($result);
	}

	/**
	 * Parse query result
	 *
	 * @param mysql_result
	 * @return array of events
	 */
	protected static function parseResult($result)
	{
		$events = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$event = unserialize($row['gl_contents']);
			$event->id = $row['gl_id'];
			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Update settings so the value increases by one for this type
	 *
	 * @param string  Name of type (code|other)
	 */
	public static function incSetting($name, $inc = 1)
	{
		$name = \ess::$b->db->quote('github_'.$name);
		$inc = (int) $inc;

		\ess::$b->db->query("
			INSERT INTO settings SET name = $name, value = 1
			ON DUPLICATE KEY UPDATE value = value + $inc");

		\Kofradia\Settings::reload();
	}

	/**
	 * Get the current number for last change
	 *
	 * @return int
	 */
	public static function getSetting($name)
	{
		return (int) \Kofradia\Settings::get('github_'.$name, 0);
	}

	/**
	 * Add event from GitHub API to log
	 *
	 * @param string Name of action (e.g. push)
	 * @param array Data from GitHub-API
	 * @return \Kofradia\GitHub\Event
	 */
	public static function addEvent($action, array $data)
	{
		if ($event = static::processGitHubData($action, $data))
		{
			$event->addLog();
		}

		return $event;
	}

	/**
	 * Process event from GitHub
	 *
	 * @param string Name of action (e.g. push)
	 * @param array Data from GitHub-API
	 * @return \Kofradia\GitHub\Event|null
	 */
	public static function processGitHubData($action, array $data)
	{
		$handlers = array(
			"push"           => "Event\\PushEvent",
			"issues"         => "Event\\IssueEvent",
			"issue_commit"   => "Event\\IssueCommentEvent",
			"gollum"         => "Event\\WikiEvent",
			"commit_comment" => "Event\\CommitCommentEvent",
			//TODO: create
			//TODO: delete
			//TODO: pull_request
			//TODO: pull_request_review_comment
			//TODO: watch
			//TODO: release
			//TODO: fork
			//TODO: member
			//TODO: public
			//TODO: team_add
			//TODO: status
		);

		if (isset($handlers[$action]))
		{
			$handler = $handlers[$action];
			return call_user_func(__NAMESPACE__."\\".$handler."::process", $data);
		}

		putlog("CREWCHAN", "%b%c4ukjent github event:%c%b $action");
	}

	/**
	 * Get last id in database
	 *
	 * @return int
	 */
	public static function getLastId()
	{
		$result = \ess::$b->db->query("SELECT gl_id FROM github_log ORDER BY gl_id DESC LIMIT 1");
		if ($row = mysql_fetch_assoc($result))
		{
			return $row['gl_id'];
		}

		return 0;
	}

	/**
	 * Delete old records
	 */
	public static function deleteOld()
	{
		// delete older than 30 days
		$expire = time() - 86400 * 30;
		\ess::$b->db->query("
			DELETE FROM github_log
			WHERE gl_time < $expire");
	}
}