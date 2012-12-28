<?php

/**
 * Lagre parametere med navn og verdi som array i tekst (praktisk i database felt f.eks.)
 */
class params
{
	/**
	 * Enhetene
	 * @var array $params
	 */
	public $params = array();
	
	/**
	 * Construct
	 * @param text $text
	 */
	public function __construct($text = '')
	{
		$this->add_text($text);
	}
	
	/**
	 * Finn ut om en enhet finnes
	 * 
	 * @param string $nae
	 * @return boolean
	 */
	public function exists($name)
	{
		return isset($this->params[$name]);
	}
	
	/**
	 * Fjern en enhet
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function remove($name)
	{
		if (isset($this->params[$name]))
		{
			unset($this->params[$name]);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Legg til eller endre en enhet
	 *
	 * @param string $name
	 * @param string $value
	 */
	public function update($name, $value)
	{
		$this->params[$name] = $value;
	}
	
	/**
	 * Legg til flere enheter (som tekst)
	 *
	 * @param string $text
	 * @return int count
	 */
	public function add_text($text)
	{
		if (empty($text)) return 0;
		
		// forenklet?
		if (substr($text, 0, 1) == "*")
		{
			$items = explode(";", substr($text, 1));
			foreach ($items as $info)
			{
				$x = explode("=", $info, 2);
				$this->params[$x[0]] = isset($x[1]) ? $x[1] : '';
			}
			return count($items);
		}
		
		$items = $this->decode($text, "=");
		foreach ($items as $info)
		{
			// hent ut data og legg til
			list($name, $value) = $this->decode($info);
			$this->params[$name] = $value;
		}
		
		return count($items);
	}
	
	/**
	 * Hent enhet
	 *
	 * @param string $name
	 * @param string $default_value
	 * @return string
	 */
	public function get($name, $default_value = NULL)
	{
		if (!isset($this->params[$name]))
		{
			return $default_value;
		}
		
		return $this->params[$name];
	}
	
	/**
	 * Bygg opp hele teksten
	 *
	 * @return string
	 */
	public function build()
	{
		$items = "";
		
		foreach ($this->params as $name => $value)
		{
			$items .= $this->encode($this->encode($name).$this->encode($value), "=");
		}
		
		return $items;
	}
	
	/**
	 * Sett sammen et ledd ("krypter")
	 *
	 * @param string $string
	 * @param string $delimiter
	 * @return string
	 */
	private function encode($string, $delimiter = ":")
	{
		return strlen($string) . $delimiter . $string;
	}
	
	/**
	 * Ta fra hverandre alle leddene ("dekrypter")
	 * 
	 * @param string $string
	 * @param string $delimiter
	 * @return string
	 */
	private function decode($string, $delimiter = ":")
	{
		$data = array();
		
		// finn delimiter
		while (($pos = strpos($string, $delimiter)) !== false)
		{
			$len = intval(substr($string, 0, $pos));
			$data[] = substr($string, $pos+1, $len);
			$string = substr($string, $pos+$len+1);
		}
		
		return $data;
	}
}