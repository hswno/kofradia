<?php

/**
 * Attributter til HTML elementer
 */
class attr
{
	public $name = NULL;
	public $items = array();
	public $split = " ";
	public function __construct($name = NULL, $items = NULL, $split = NULL)
	{
		if (!$name) throw new HSException("Mangler navn til attributt.");
		$this->name = $name;
		if ($split) $this->split = $split;
		if ($items) $this->add($items);
		return $this;
	}
	public function add($items)
	{
		$items = is_array($items) ? $items : explode($this->split, $items);
		foreach ($items as $item)
		{
			$item = trim($item);
			if (empty($item)) continue;
			
			$this->items[] = $item;
		}
		return $this;
	}
	public function build()
	{
		if (count($this->items) == 0) return '';
		
		return ' '.$this->name.'="'.implode($this->split, $this->items).'"';
	}
}