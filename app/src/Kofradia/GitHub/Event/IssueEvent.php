<?php namespace Kofradia\GitHub\Event;

class IssueEvent extends Issue {
	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event\IssueEvent
	 */
	public static function process($data)
	{
		return parent::process();
	}

	/**
	 * Description of actions
	 *
	 * @var array(string action => string text, ..)
	 */
	public static $action_text = array(
		"closed" => "lukket",
		"opened" => "opprettet",
		"reopened" => "gjenåpnet");

	/**
	 * Name for this type of event
	 *
	 * @var string
	 */
	public $event_name = "issues";
}