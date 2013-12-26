<?php namespace Kofradia;

class Route {
	/**
	 * The URI currently requested
	 *
	 * @var string
	 */
	protected $uri;

	/**
	 * The active controller
	 *
	 * @var string|closure
	 */
	protected $controller;

	/**
	 * Arguments to the controller
	 *
	 * @var array
	 */
	protected $controller_args = array();

	/**
	 * Create object by current request
	 *
	 * @return \Kofradia\Route
	 */
	public static function getRoute()
	{
		$uri = ltrim($_SERVER['REQUEST_URI'], "/");
		if (($pos = mb_strpos($uri, "?")) !== false) $uri = mb_substr($uri, 0, $pos);

		return new static($uri);
	}

	public function __construct($uri)
	{
		$this->uri = $uri;
	}

	/**
	 * Process the current request
	 */
	public function process()
	{
		$this->loadController();
		if ($this->controller)
		{
			$this->processController();
		}
		else
		{
			page_not_found();
			die;
		}
	}

	/**
	 * Execute the active controller
	 */
	public function processController()
	{
		// closure
		if (is_callable($this->controller))
		{
			$func = new \ReflectionFunction($this->controller);
			echo $func->invokeArgs($this->controller_args);
		}

		else
		{
			$pos = strpos($this->controller, "@");
			$classname = "\\Kofradia\\Controller\\".substr($this->controller, 0, $pos);
			$method = "action_".substr($this->controller, $pos+1);

			$class = new \ReflectionClass($classname);

			$instance = $class->newInstance();
			$class->getMethod("before")->invoke($instance);

			echo $class->getMethod($method)->invokeArgs($instance, $this->controller_args);
		}

		\ess::$b->page->load();
	}

	/**
	 * Load controller for current requested
	 */
	public function loadController()
	{
		// controller syntax: controllername@methodname
		// controllername can be subnamespaced (e.g. My\Thing => \Kofradia\Controller\My\Thing)
		// can also be a closure

		$this->controller = null;
		$this->controller_args = array();

		$routes = require PATH_APP . "/routes.php";
		$controller = null;
		$arguments = array();
		if (!isset($routes[$this->uri]))
		{
			// try some regex
			$match = false;
			foreach ($routes as $check => $controller)
			{
				$check = sprintf('~^%s$~i', $check);
				if ($match = preg_match($check, $this->uri, $matches))
				{
					$arguments = $matches;
					array_shift($arguments);
					break;
				}
			}

			if ($match)
			{
				$this->controller = $controller;
				$this->controller_args = $arguments;
			}
		}
		else
		{
			$this->controller = $routes[$this->uri];
			$this->controller_args = array();
		}
	}
}