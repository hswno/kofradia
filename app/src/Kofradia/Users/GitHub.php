<?php namespace Kofradia\Users;

use \Kofradia\GitHub\Hendelser;

class GitHub
{
	/**
	 * Get object by user
	 *
	 * @param \user
	 * @return \Kofradia\GitHub\User
	 */
	public static function get(\user $user)
	{
		$u = new static();
		$u->user = $user;
		return $u;
	}

	/**
	 * The user to operate on
	 *
	 * @param \user
	 */
	public $user;

	/**
	 * Check if user is retrieving updates
	 *
	 * @return boolean
	 */
	public function hasActivated()
	{
		return !is_null($this->user->params->get("github_last_seen_id")) && Hendelser::getSetting("count_events") > 0;
	}

	/**
	 * Set user to be up-to-date with changes
	 *
	 * @return int
	 */
	public function setUpdated()
	{
		$this->user->params->update("github_last_seen_id", Hendelser::getLastId());
		$this->user->params->update("github_count_code", Hendelser::getSetting("count_code"));
		$this->user->params->update("github_count_other", Hendelser::getSetting("count_other"), true);
	}

	/**
	 * How many unseen events for code?
	 *
	 * @return int
	 */
	public function getCodeBehindCount()
	{
		return Hendelser::getSetting("count_code") - $this->user->params->get("github_count_code", 0);
	}

	/**
	 * How many unseen events for other?
	 *
	 * @return int
	 */
	public function getOtherBehindCount()
	{
		return Hendelser::getSetting("count_other") - $this->user->params->get("github_count_other", 0);
	}

	/**
	 * Get unseen events
	 *
	 * @return array(\Kofradia\GitHub\Event, ..)
	 */
	public function getUnseenEvents()
	{
		if (!$this->hasActivated() || ($this->getCodeBehindCount() == 0 && $this->getOtherBehindCount() == 0))
		{
			return array();
		}

		return Hendelser::getEventsSinceId($this->user->params->get("github_last_seen_id", 0));
	}
}