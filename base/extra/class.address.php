<?php

/**
 * For behandling av adresser (scope resolution)
 */
class address
{
	const PATH = 2;
	const ADD = 3;
	const ADDS = 4;
	const EVICT = 5;
	const NOHTML = 6;
	const SUFFIX = 7;
	const PAGEI = 8;
	
	/** HTML eller ikke */
	private static $html = true;
	
	/** Suffix */
	private static $suffix = NULL;
	
	/** Adressen */
	private static $path = "";
	
	/** Elementene */
	private static $elements = array();
	
	/** Elementene (hvor verdien ikke skal encodes) */
	private static $elements_special = array();
	
	/**
	 * Lag adresse
	 */
	public function make()
	{
		// reset
		self::$html = true;
		self::$suffix = NULL;
		self::$path = NULL;
		self::$elements = array();
		self::$elements_special = array();
		$pagei = false;
		
		// sjekk parameterene
		for ($i = 0; $i < func_num_args(); $i++)
		{
			$arg = func_get_arg($i);
			
			if (!is_int($arg))
			{
				// pagei objektet
				if (is_object($arg) && get_class($arg) == "pagei")
				{
					$pagei = $arg;
					continue;
				}
				
				// hurtigversjon for å legge til enheter
				if (is_array($arg))
				{
					self::add_elms($arg);
					continue;
				}
				
				// hurtigversjon for å ikke bruke html
				if ($arg === false)
				{
					self::$html = false;
					continue;
				}
				
				// hurtigversjon for å legge til suffix
				if (is_string($arg))
				{
					self::$suffix = $arg;
					continue;  
				}
			}
			
			switch ($arg)
			{
				case self::PATH:
					$v = func_get_arg(++$i);
					self::set_path($v);
				break;
				
				case self::ADD:
				case self::ADDS:
					$v = func_get_arg(++$i);
					if (is_array($v))
					{
						self::add_elms($v, $arg == self::ADD);
					}
					else
					{
						$v2 = func_get_arg(++$i);
						self::add($v, $v2, $arg == self::ADD);
					}
				break;
				
				case self::EVICT:
					$v = func_get_arg(++$i);
					self::evict($v);
				break;
				
				case self::NOHTML:
					self::$html = false;
				break;
				
				case self::SUFFIX:
					self::$suffix = func_get_arg(++$i);
				break;
				
				case self::PAGEI:
					$pagei = &func_get_arg(++$i);
				break;
			}
		}
		
		// ingen path?
		if (self::$path === NULL) self::set_path($_SERVER['REQUEST_URI']);
		
		// sidetall?
		if ($pagei && $pagei->get_name)
		{
			self::$html = false;
			self::evict($pagei->get_name);
			$addr = self::build();
			
			self::add($pagei->get_name, "<page>", false);
			$addrx = self::build();
			
			return $pagei->pagenumbers($addr, $addrx);
		}
		
		return self::build();
	}
	
	/**
	 * Sett adresse
	 * 
	 * @param string path to file $path
	 */
	private function set_path($path)
	{
		if (($pos = strpos($path, "?")) !== false)
		{
			$path = substr($path, 0, $pos);
		}
		
		self::$path = $path;
	}
	
	/**
	 * Legg til elementer
	 * 
	 * @param array elements $elms
	 */
	private function add_elms($elms, $encode_value = true)
	{
		if (!is_array($elms)) throw new HSException("\$elms i not an array");
		
		foreach ($elms as $key => $value)
		{
			self::add($key, $value, $encode_value);
		}
	}
	
	/**
	 * Legg til et element
	 * 
	 * @param string name $key
	 * @param mixed $value
	 * @param boolean $encode_value
	 */
	private function add($key, $value, $encode_value = true)
	{
		self::verify($value);
		
		if ($encode_value)
		{
			self::$elements[$key] = $value;
		}
		else
		{
			self::$elements_special[$key] = $value;
		}
	}
	
	/**
	 * Fjern et mulig element
	 * 
	 * @param string name $key OR array names $key
	 */
	private function evict($key)
	{
		if (is_array($key))
		{
			array_map(array("self", "evict"), $key);
			return;
		}
		
		if (isset(self::$elements[$key]))
		{
			unset(self::$elements[$key]);
		}
	}
	
	/**
	 * Kontroller elementene
	 * 
	 * @param mixed $value
	 */
	private function verify($value)
	{
		if (is_null($value) || is_bool($value) || is_scalar($value))
		{
			return;
		}
		
		if (is_array($value))
		{
			array_map(array("self", "verify"), $value);
			return;
		}
		
		throw new HSException("Ugyldig type ".gettype($value).". Kan ikke legge til som adresse parameter.");
	}
	
	/**
	 * Bygg adressen
	 * 
	 * @param bool $html
	 * @param string $suffix
	 */
	private function build()
	{
		// sett opp alle element par
		$pairs = self::build_pairs(self::$elements);
		
		// noen som ikke skal encodes?
		if (count(self::$elements_special) > 0)
		{
			$pairs = array_merge($pairs, self::build_pairs(self::$elements_special, false));
		}
		
		$uri = self::$path;
		if (count($pairs) > 0)
		{
			$uri .= "?" . implode(self::$html ? "&amp;" : "&", $pairs);
		}
		
		// anker?
		if (self::$suffix !== NULL)
		{
			$uri .= self::$suffix; 
		}
		
		return $uri;
	}
	
	/**
	 * Bygg nøkkel/verdi par for elementene
	 * 
	 * @param array elements
	 */
	private function build_pairs($elms, $encode_value = true)
	{
		$ret = array();
		
		foreach ($elms as $key => $value)
		{
			if (is_null($value) || $value === "")
			{
				$ret[] = urlencode($key);
			}
			
			elseif (is_array($value))
			{
				$ret = array_merge($ret, self::build_pairs($value, $encode_value));
			}
			
			else
			{
				$ret[] = urlencode($key)."=".($encode_value ? urlencode($value) : $value);
			}
		}
		
		return $ret;
	}
}