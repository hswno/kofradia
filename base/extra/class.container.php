<?php

/**
 * Klasse for å lage forskjellige verdier i en tekst (liknende en javascript array)
 */
class container
{
	/**
	 * Enhetene
	 *
	 * @var array $items
	 */
	public $items = array();
	
	/**
	 * Constructor
	 *
	 * @param string $text
	 */
	public function __construct($text = '')
	{
		$this->add_text($text);
	}
	
	/**
	 * For å "kryptere" teksten så den ikke blander seg med noe annet
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
	 * For å dele opp delene/teksten til en array
	 * 
	 * @param string $string
	 * @param string $delimiter
	 * @return array
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
	
	/**
	 * Legg til enheter (som tekst)
	 *
	 * @param string $text
	 * @return int count
	 */
	public function add_text($text)
	{
		if (empty($text)) return 0;
		
		$items = $this->decode($text, "=");
		$this->items = array_merge($this->items, array_map(array($this, "decode"), $items));
		
		return count($items);
	}
	
	/**
	 * Sett sammen alle verdiene/enhetene til en tekst
	 *
	 * @return string
	 */
	function build()
	{
		$items = "";
		
		foreach ($this->items as $row)
		{
			$items .= $this->encode(implode("", array_map(array($this, "encode"), $row)), "=");
		}
		
		return $items;
	}
}