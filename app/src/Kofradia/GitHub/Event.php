<?php namespace Kofradia\GitHub;

abstract class Event {
	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event
	 */
	public static function process($data)
	{
		$event = new static();
		$event->repository = $data['repository']['name'];
		$event->event_time = new \DateTime();

		return $event;
	}

	/**
	 * The ID this event have in the table
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Name for this type of event
	 * Should be overloaded by subclass
	 *
	 * @var string
	 */
	public $event_name;

	/**
	 * Event time
	 *
	 * @var \DateTime
	 */
	public $event_time;

	/**
	 * Repository name
	 *
	 * @var string
	 */
	public $repository;

	/**
	 * Return message(s) describing event in HTML-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	abstract public function getDescriptionHTML();

	/**
	 * Return message(s) describing event in IRC-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	abstract public function getDescriptionIRC();

	/**
	 * Get the number of log entries
	 *
	 * @return int
	 */
	public function getLogCount()
	{
		return 1;
	}

	/**
	 * Add to database
	 */
	public function addToDb()
	{
		// already in db?
		if ($this->id) return;

		Hendelser::incSetting("count_events");
		foreach ($this->getDescriptionHTML() as $text)
		{
			if ($this->event_name == "push")
			{
				Hendelser::incSetting("count_code", $this->getLogCount());
			}
			else
			{
				Hendelser::incSetting("count_other", $this->getLogCount());
			}
		}

		\ess::$b->db->query("
			INSERT INTO github_log
			SET gl_time = ".$this->event_time->getTimestamp().", gl_event_type = ".\ess::$b->db->quote($this->event_name).",
			    gl_contents = ".\ess::$b->db->quote(serialize($this)).", gl_log_count = ".$this->getLogCount());
	}

	/**
	 * Add event to log
	 *
	 * @param \Kofradia\GitHub\Event
	 */
	public function addLog()
	{
		foreach ($this->getDescriptionIRC() as $text)
		{
			putlog("CREWCHAN", "%bGitHub - {$this->event_name}%b: $text");
			putlog("INFO", "%bGitHub - {$this->event_name}%b: $text");
		}

		$this->addToDb();
	}
}