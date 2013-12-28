<?php namespace Kofradia\GitHub\Event;

use \Kofradia\GitHub\Event;

class PushEvent extends Event {
	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event\PushEvent
	 */
	public static function process($data)
	{
		$event = parent::process($data);
		$event->ref = $data['ref'];
		$event->url_compare = $data['compare'];
		foreach ($data['commits'] as $commit)
		{
			$event->processCommit($commit);
		}

		return $event;
	}

	/**
	 * Name for this type of event
	 *
	 * @var string
	 */
	public $event_name = "push";
	
	/**
	 * Branch name
	 *
	 * @var string
	 */
	public $ref;

	/**
	 * The actual commits
	 *
	 * @var array(Commit, ..)
	 */
	public $commits;

	/**
	 * Compare URL
	 *
	 * @var string
	 */
	public $url_compare;

	/**
	 * Process commit-data from GitHub API
	 *
	 * @param array The data from the API
	 */
	public function processCommit($commit)
	{
		$this->commits[] = Commit::process($commit, $this);
	}

	/**
	 * Return message(s) describing event in HTML-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionHTML()
	{
		$text = array();
		foreach ($this->commits as $commit)
		{
			$text[] = $commit->getDescriptionHTML();
		}
		
		return $text;
	}

	/**
	 * Return message(s) describing event in IRC-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionIRC()
	{
		$text = array();
		foreach ($this->commits as $commit)
		{
			$text[] = $commit->getDescriptionIRC();
		}
		
		return $text;
	}

	/**
	 * Return the number of log entries
	 *
	 * @return int
	 */
	public function getLogCount()
	{
		return count($this->commits);
	}
}