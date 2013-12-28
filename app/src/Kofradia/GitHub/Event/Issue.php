<?php namespace Kofradia\GitHub\Event;

use \Kofradia\GitHub\Event;

abstract class Issue extends Event {
	use TraitActionText;

	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event\IssueEvent
	 */
	public static function process($data)
	{
		$event = parent::process($data);
		$event->action = $data['action'];
		$event->sender_name = $data['sender']['login'];
		$event->number = $data['issue']['number'];
		$event->title = $data['issue']['title'];
		$event->url = $data['isue']['html_url'];

		return $event;
	}

	/**
	 * Name of sender
	 *
	 * @var string
	 */
	public $sender_name;

	/**
	 * Issue number
	 *
	 * @var int
	 */
	public $number;

	/**
	 * Issue title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Issue URL
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Return message(s) describing event in HTML-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionHTML()
	{
		$text = sprintf('<u>%s</u> %s issue <a href="%s">#%d (%s)</a>',
			htmlspecialchars($this->sender_name),
			htmlspecialchars($this->getActionText()),
			htmlspecialchars($this->url),
			htmlspecialchars($this->number),
			htmlspecialchars($this->title));

		return array($text);
	}

	/**
	 * Return message(s) describing event in IRC-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionIRC()
	{
		$text = sprintf("%%u%s%%u %s issue #%d (%s) %s",
			$this->sender_name,
			$this->getActionText(),
			$this->number,
			$this->title,
			$this->url);

		return array($text);
	}
}