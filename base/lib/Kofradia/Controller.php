<?php namespace Kofradia;

abstract class Controller {
	/**
	 * Set SSL?
	 * @var null|boolean null to ignore, true to force ssl, false to allow not using ssl
	 */
	protected $ssl = false;

	/**
	 * Current user
	 *
	 * @var \user
	 */
	protected $user;

	/**
	 * Function to be called before calling controller
	 */
	public function before()
	{
		// check for SSL
		if (!is_null($this->ssl))
		{
			force_https($this->ssl);
		}

		// se the active user
		$this->user = \login::$logged_in ? \login::$user : null;
	}

	/**
	 * Go to login if no user
	 */
	public function needUser()
	{
		if (!$this->user)
		{
			// are we really logged in?
			if (\login::$logged_in)
			{
				throw new \HSException("Cannot send to login, a user is already logged in.");
			}

			\access::no_guest();
		}
	}
}