<?php namespace Kofradia\GitHub;

class Hendelser {
	/**
	 * Check if user is retrieving updates
	 *
	 * @return boolean
	 */
	public function userHasActivated(\user $user)
	{
		return !is_null($user->params->get("github_count_code")) || !is_null($user->params->get("github_count_other"));
	}

	/**
	 * Set user to be up-to-date with changes
	 */
	public function setUserUpdated(\user $user)
	{
		$user->params->update("github_count_code", $this->getSetting("code"));
		$user->params->update("github_count_other", $this->getSetting("other"), true);
	}

	/**
	 * How many unseen events for code?
	 */
	public function getUserCodeBehind(\user $user)
	{
		return $this->getSetting("code") - $user->params->get("github_count_code", 0);
	}

	/**
	 * How many unseen events for other?
	 */
	public function getUserOtherBehind(\user $user)
	{
		return $this->getSetting("other") - $user->params->get("github_count_other", 0);
	}

	/**
	 * Update settings so the value increases by one for this type
	 *
	 * @param string  Name of type (code|other)
	 */
	private function incSetting($name)
	{
		$name = \ess::$b->db->quote('github_count_'.$name);

		\ess::$b->db->query("
			INSERT INTO settings SET name = $name, value = 1
			ON DUPLICATE KEY UPDATE value = value + 1");

		\Kofradia\Settings::reload();
	}

	/**
	 * Get the current number for last change
	 *
	 * @return int
	 */
	private function getSetting($name)
	{
		return (int) \Kofradia\Settings::get('github_count_'.$name, 0);
	}

	/**
	 * Mark a change in code type
	 */
	public function incCodeChange()
	{
		$this->incSetting('code');
	}

	/**
	 * Mark a change in other types
	 */
	public function incOtherChange($time)
	{
		$this->incSetting('other');
	}
}