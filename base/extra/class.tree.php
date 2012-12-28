<?php

/**
 * Sett opp data som et tre.
 */
class tree
{
	private $hierachy = array();
	public $data = array();
	private $link = NULL;
	private $link_to = NULL;
	private $link_name = NULL;
	private $max_number = false;
	public $prefix = array(
		"normal" => '&#9500;',
		"last" => '&#9492;',
		"jump" => '&#9474;&nbsp;',
		"none" => '&nbsp;&nbsp;'
	);
	
	/**
	 * Constructor
	 *
	 * @param array $hierachy (parent->child id relations)
	 */
	public function __construct($hierachy)
	{
		if (!is_array($hierachy))
		{
			throw new HSException('No array $hierachy found.');
			return;
		}
		
		$this->hierachy = $hierachy;
	}
	
	/**
	 * Generer tree
	 *
	 * @param optional integer $parent_id
	 * @param optional init $data
	 * @param optional array $link_to by reference
	 * @param optional string $link_name
	 * @param optional integer $max_number (max descendents)
	 * @return array generated tree
	 */
	public function generate($parent_id = 0, $data = NULL, &$link_to = NULL, $link_name = "data", $max_number = NULL)
	{
		// nullstill data
		if (is_array($data)) $this->data = $data;
		else $this->data = array();
		
		// linking
		$this->link = is_array($link_to);
		$this->link_to = is_array($link_to) ? $link_to : NULL;
		$this->link_name = $link_name;
		
		if ($max_number) $this->max_number = intval($max_number);
		
		// lag data
		$this->generate_internal($parent_id, "", 1);
		
		return $this->data;
	}
	
	/**
	 * Generer tree (intern funksjon)
	 *
	 * @param integer $parent_id
	 * @param integer $prefix
	 * @param integer $number
	 */
	private function generate_internal($parent_id = 0, $prefix, $number)
	{
		if (isset($this->hierachy[$parent_id]))
		{
			$i = 0;
			$count = count($this->hierachy[$parent_id]);
			
			foreach ($this->hierachy[$parent_id] as $row)
			{
				$i++;
				$prefix_this = ($i < $count ? $this->prefix['normal'] : $this->prefix['last']);
				$prefix_sub = $prefix . ($i < $count ? $this->prefix['jump'] : $this->prefix['none']);
				
				// oppdag løkke
				if (isset($this->data[$row])) throw new HSException("loop on id $row");
				
				$add = array("number" => $number, "prefix" => $prefix, "prefix_node" => $prefix_this);
				if ($this->link) $add[$this->link_name] = $this->link_to[$row];
				
				$this->data[$row] = $add;
				
				// children av denne igjen?
				if (!$this->max_number || $number < $this->max_number)
				{
					$this->generate_internal($row, $prefix_sub, $number+1);
				}
			}
		}
	}
}