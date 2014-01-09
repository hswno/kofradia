<?php namespace Kofradia;

class View {
	/**
	 * Twig-object
	 *
	 * @var Twig_Environment
	 */
	protected static $twig;

	/**
	 * Create a view and get its content
	 *
	 * @param string  Name of view
	 * @param array   Data to pass to the view
	 * @return \Kofradia\Viev
	 */
	public static function forge($name, $data = array())
	{
		$path = PATH_APP."/views/$name.php";
		$view = new static($path);
		$view->setData($data);

		return $view->render();
	}

	/**
	 * Create a view and get its content (by Twig)
	 *
	 * @param string  Name of view
	 * @param array   Data to pass to the view
	 * @return \Kofradia\Viev
	 */
	public static function forgeTwig($name, $data = array())
	{
		\ess::$b->dt(sprintf('forgeTwig(%s)', $name));
		return static::getTwig()->render($name.".html.twig", $data);
	} 

	/**
	 * Get Twig-instance
	 *
	 * @return Twig_Environment
	 */
	protected static function getTwig()
	{
		if (!static::$twig)
		{
			$loader = new \Twig_Loader_Filesystem(PATH_APP.'/views');
			static::$twig = new \Twig_Environment($loader, array(
				'cache' => MAIN_SERVER ? PATH_DATA.'/twig-cache' : null,
				'strict_variables' => true,
				'autoescape' => false,
			));
			static::$twig->addExtension(new \Kofradia\Twig\Date());
			static::$twig->addExtension(new \Kofradia\Twig\Counter());
		}

		return static::$twig;
	}

	/**
	 * Path to file
	 */
	protected $path;

	/**
	 * Data to pass to the view
	 */
	protected $data;

	/**
	 * Create a view
	 *
	 * @param string Path to file
	 */
	public function __construct($path)
	{
		$this->path = $path;

		if (!is_readable($this->path))
		{
			throw new \HSException("Could not find viewfile");
		}
	}

	/**
	 * Set data for the view
	 *
	 * @param array
	 */
	public function setData(array $data)
	{
		$this->data = $data;
	}

	/**
	 * Render the view
	 */
	public function render()
	{
		// export data to global
		extract($this->data, EXTR_REFS);

		$pre = ob_get_contents();
		ob_clean();
		
		require $this->path;
		$data = ob_get_contents();
		ob_clean();

		echo $pre;
		return $data;
	}
}