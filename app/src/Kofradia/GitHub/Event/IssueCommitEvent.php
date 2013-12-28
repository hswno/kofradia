<?php namespace Kofradia\GitHub\Event;

class IssueCommitEvent extends Issue {
	/**
	 * Create object from data by GitHub API
	 *
	 * @param array Data from API
	 * @return \Kofradia\GitHub\Event\IssueEvent
	 */
	public static function process($data)
	{
		return parent::process($data);
	}

	/**
	 * Description of actions
	 *
	 * @var array(string action => string text, ..)
	 */
	public static $action_text = array(
		"created" => "kommenterte på");

	/**
	 * Name for this type of event
	 *
	 * @var string
	 */
	public $event_name = "issue_comment";
}