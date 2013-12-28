<?php namespace Kofradia\GitHub\Event;

use \Kofradia\GitHub\Event;

class CommitCommentEvent extends Event {
	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event\CommitComment
	 */
	public static function process($data)
	{
		$event = parent::process($data);
		$event->sender_name = $data['sender']['login'];
		$event->url = $data['comment']['html_url'];
		$event->commit_id = $data['comment']['commit_id'];

		return $event;
	}

	/**
	 * Name for this type of event
	 *
	 * @var string
	 */
	public $event_name = "commit_comment";

	/**
	 * Name of sender
	 *
	 * @var string
	 */
	public $sender_name;

	/**
	 * Issue URL
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Id for the commit
	 *
	 * @var string
	 */
	public $commit_id;

	/**
	 * Return message(s) describing event in HTML-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionHTML()
	{
		$text = sprintf('<u>%s</u> la til <a href="%s">kommentar på commit %s</a>',
			htmlspecialchars($this->sender_name),
			htmlspecialchars($this->url),
			htmlspecialchars(substr($this->commit_id, 10)));

		return array($text);
	}

	/**
	 * Return message(s) describing event in IRC-format
	 *
	 * @return array(string, ..)  Array of event descriptions
	 */
	public function getDescriptionIRC()
	{
		$text = sprintf("%%u%s%%u la til kommentar på commit %s %s",
			$this->sender_name,
			substr($this->commit_id, 10),
			$this->url);

		return array($text);
	}
}