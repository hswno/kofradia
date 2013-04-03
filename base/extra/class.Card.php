<?php

/**
 * Kortfunksjoner
 */
class Card
{
	/**
	 * Navnene for kortnummeret
	 * @var array
	 */
	public static $signs = array(
		"plain" => array(1 => 2, 3, 4, 5, 6, 7, 8, 9, 10, "J", "Q", "K", "A"),
		"one" => array(1 => "2-er", "3-er", "4-er", "5-er", "6-er", "7-er", "8-er", "9-er", "10-er", "knekt", "dame", "konge", "ess"),
		"multiple" => array(1 => "2-ere", "3-ere", "4-ere", "5-ere", "6-ere", "7-ere", "8-ere", "9-ere", "10-ere", "knekt", "damer", "konger", "ess")
	);
	
	/**
	 * Navnene for kortgruppene
	 * @var array
	 */
	public static $groups = array(
		"name" => array(1 => "klover", "spar", "ruter", "hjerter"),
		"title" => array(1 => "kløver", "spar", "ruter", "hjerter")
	);
	
	/** KortID (1-52) */
	public $id = false;
	
	/** Kortgruppe (1-4) */
	public $group = false;
	
	/** Kortnummer (1-13) */
	public $num = false;
	
	/**
	 * Opprett kort
	 * @param int KortID (1-52)
	 */
	public function __construct($id)
	{
		$id = (int) $id;
		if ($id < 1 || $id > 52)
		{
			throw new HSException("KortID må være mellom 1 og 52.");
		}
		
		$this->id = $id;
		$group = ceil($id/13);
		$this->group = array("id" => $group, "name" => self::$groups['name'][$group], "title" => self::$groups['title'][$group]); 
		$this->num = $id - ($this->group['id']-1)*13;
	}
	
	/**
	 * Finn navn for kortnummeret
	 * @param int $count
	 * @return string
	 */
	public function sign($count = 1)
	{
		if ($count == 1) return self::$signs['one'][$this->num];
		if ($count === false) return self::$signs['plain'][$this->num];
		return self::$signs['multiple'][$this->num];
	}
}