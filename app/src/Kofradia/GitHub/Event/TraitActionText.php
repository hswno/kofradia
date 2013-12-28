<?php namespace Kofradia\GitHub\Event;

trait TraitActionText {
	/**
	 * Description of actions
	 *
	 * @var array(string action => string text, ..)
	 */
	abstract public static $action_text;

	/**
	 * Name of action
	 *
	 * @var string
	 */
	abstract public $action;

	/**
	 * Get description of action
	 *
	 * @return string
	 */
	public getActionText()
	{
		if (isset(static::$action_text[$this->action]))
		{
			return static::$action_text[$this->action];
		}

		return $this->action;
	}
}