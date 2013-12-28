<?php namespace Kofradia\GitHub\Event;

use \Kofradia\GitHub\Event;

class WikiEvent extends Event {
	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event\WikiEvent
	 */
	public static function process($data)
	{
		$event = parent::process($data);
		
		foreach ($data['pages'] as $page)
		{
			$event->processPage($page);
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
	 * Name of sender
	 *
	 * @var string
	 */
	public $sender_name;

	/**
	 * The actual pages
	 *
	 * @var array(Wiki, ..)
	 */
	public $pages;

	/**
	 * Process page-data from GitHub API
	 *
	 * @param array The data from the API
	 */
	public function processPage($page)
	{
		$this->pages[] = Wiki::process($page, $this);
	}

	/**
	 * Return message(s) describing event in HTML-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionHTML()
	{
		$text = array();
		foreach ($this->pages as $page)
		{
			$text[] = $page->getDescriptionHTML();
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
		foreach ($this->pages as $page)
		{
			$text[] = $page->getDescriptionIRC();
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
		return count($this->pages);
	}
}