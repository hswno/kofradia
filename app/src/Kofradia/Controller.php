<?php namespace Kofradia;

use \Kofradia\Response;

abstract class Controller {
	public $createResponseObject = true;

	/**
	 * Execute a specific controller by parsing the route-string
	 *
	 * @param string Route string
	 * @param array  Controller arguments
	 * @return mixed The return from the controller
	 */
	public static function execute($route, $args = array())
	{
		$pos = strpos($route, "@");
		$classname = "\\Kofradia\\Controller\\".substr($route, 0, $pos);
		$method = "action_".substr($route, $pos+1);

		$class = new \ReflectionClass($classname);

		$instance = $class->newInstance();
		$class->getMethod("before")->invoke($instance);

		$data = $class->getMethod($method)->invokeArgs($instance, $args);
		if (is_string($data) && $instance->createResponseObject)
		{
			$r = new Response();
			$r->setContents($data);
			return $r;
		}

		return $data;
	}

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