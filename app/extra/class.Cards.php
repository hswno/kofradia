<?php

/**
 * Kortstokkfunksjoner
 */
class Cards
{
	/**
	 * Kortstokken
	 * @var array
	 */
	public $cards = array(1 => 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52);
	
	/**
	 * Aktuell kortstokk
	 * @param array $active
	 */
	public $active = array();
	
	/**
	 * Legg til kortID (1-52)
	 * @param array $cards
	 * @param boolean $replace
	 */
	public function add_cards($cards, $replace = false)
	{
		foreach ($cards as $key => $card)
		{
			// fjern
			unset($this->cards[$card]);
			
			// legg til
			if ($replace)
			{
				$this->active[$key] = new Card($card);
			}
			else
			{
				$this->active[] = new Card($card);
			}
		}
	}
	
	/**
	 * Hent ut kortID
	 * @return array
	 */
	public function get_cards()
	{
		$cards = array();
		foreach ($this->active as $card)
		{
			$cards[] = $card->id;
		}
		return $cards;
	}
	
	/**
	 * Sett opp tilfeldige kort
	 * @param optional int or array $items - antall kort/array: bytt ut med
	 */
	public function new_cards($items = 1)
	{
		if (is_array($items))
		{
			foreach ($items as $item)
			{
				$this->add_cards(array($item => array_rand($this->cards)), true);
			}
			return;
		}
		
		for ($i = 0; $i < $items; $i++)
		{
			$this->add_cards(array(array_rand($this->cards)));
		}
	}
	
	/**
	 * Fjern kort fra kortstokken
	 * @param array kortID $cards
	 */
	public function remove_cards($cards)
	{
		foreach ($cards as $card)
		{
			if (isset($this->cards[$card]))
			{
				unset($this->cards[$card]);
			}
		}
	}
}