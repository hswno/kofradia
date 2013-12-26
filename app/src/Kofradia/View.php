<?php namespace Kofradia;

class View {
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