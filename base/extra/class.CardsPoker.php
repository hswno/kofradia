<?php

/**
 * Pokerfunksjoner
 */
class CardsPoker extends Cards
{
	// div konstanter for kombinasjoner
	const HIGHCARD = 0;
	const ONE_PAIR = 1;
	const TWO_PAIRS = 2;
	const THREE_EQ = 3;
	const STRAIGHT = 4;
	const FLUSH = 5;
	const HOUSE = 6;
	const FOUR_EQ = 7;
	const STRAIGHT_FLUSH = 8;
	const ROYALE_STRAIGHT_FLUSH = 9;
	
	/**
	 * Construct
	 * @param array kortID $cards (1-52)
	 */
	public function __construct($cards = false)
	{
		if ($cards) $this->add_cards($cards);
	}
	
	/**
	 * Sammenlikne to resultater og finne ut hvem som vant
	 *
	 * @param array $solve1
	 * @param array $solve2
	 * @return array(winner, isHighcard)
	 */
	public static function compare($solve1, $solve2)
	{
		// ulik kombinasjon
		if ($solve1[0] != $solve2[0])
		{
			return array($solve1[0] > $solve2[0] ? 1 : 2, false);
		}
		
		// highcard
		foreach ($solve1[3] as $key => $value)
		{
			$val1 = $solve1[1][$key]->num;
			$val2 = $solve2[1][$key]->num;
			if ($val1 == $val2) continue;
			if ($val1 > $val2)
			{
				return array(1, !isset($solve1[2][$value]));
			}
			return array(2, !isset($solve2[2][$solve2[3][$key]]));
		}
		
		// uavgjort
		return array(0, false);
	}
	
	/**
	 * Spill automatisk - bytter ut kortene som ikke er en del av kombinasjonen
	 */
	public function play()
	{
		$solve = $this->solve();
		
		$renew = array(0,1,2,3,4);
		foreach (array_keys($solve[2]) as $key)
		{
			unset($renew[$key]);
		}
		
		if (count($renew) > 0)
		{
			$this->new_cards($renew);
		}
	}
	
	/**
	 * Finne beste kombinasjon
	 *
	 * @return array(TYPE, HIGHCARDS, TYPE_CARDS)
	 */
	public function solve()
	{
		// sjekk at vi har 5 kort
		if (count($this->active) != 5)
		{
			throw new HSException("Trenger 5 kort for å beregne resultat.");
		}
		
		// sett opp kortnummerene og kortgruppene
		$cards_num = array();
		$cards_group = array();
		$cards_order = array(0,1,2,3,4);
		foreach ($this->active as $card)
		{
			$cards_num[] = $card->num;
			$cards_group[] = $card->group['id'];
		}
		
		// sorter kortnummerene (så det høyeste kortet blir sist) og kortgruppene
		array_multisort($cards_num, SORT_ASC, $cards_order);
		sort($cards_group);
		
		$solve = $this->solve_internal($cards_num, $cards_group);
		// $solve: array(TYPE, HIGHCARDS, TYPE_CARDS)
		
		// sett HIGHCARDS og TYPE_CARDS til riktig id
		foreach ($solve[1] as $key => $card)
		{
			$solve[1][$key] = $this->active[$cards_order[$card]];
			$solve[3][$key] = $cards_order[$card];
		}
		
		$new = array();
		foreach ($solve[2] as $key => $card)
		{
			$new[$cards_order[$card]] = $this->active[$cards_order[$card]];
		}
		$solve[2] = $new;
		
		return $solve;
	}
	
	/**
	 * Finne beste kombinasjonen (internal)
	 */
	private function solve_internal($cards_num, $cards_group)
	{
		// finn ut om vi har flush og/eller straight (for å slippe å sjekke dette ved hver anledning etterpå)
		$flush = $cards_group[0] == $cards_group[4]; // alle kortgruppene er de samme
		$straight = $cards_num[0] == $cards_num[1]-1 && $cards_num[0] == $cards_num[2]-2 && $cards_num[0] == $cards_num[3]-3 && ($cards_num[0] == $cards_num[4]-4 || ($cards_num[0] == 1 && $cards_num[4] == 13)); // 5 kort etter hverandre i antal øyne
		
		// fikk vi: royal straight flush?
		if ($flush && $straight && $cards_num[0] == 9) // 10 er lavest og ess er høyest
		{
			return array(self::ROYALE_STRAIGHT_FLUSH, array(), array(0,1,2,3,4));
		}
		
		// fikk vi: straight flush?
		if ($flush && $straight)
		{
			// striaght med A som 1?
			if ($cards_num[0] == 1 && $cards_num[4] == 13) $high = 3;
			else $high = 4;
			
			return array(self::STRAIGHT_FLUSH, array($high), array(0,1,2,3,4));
		}
		
		// fikk vi: fire like?
		if ($cards_num[1] == $cards_num[3] && ($cards_num[1] == $cards_num[0] || $cards_num[1] == $cards_num[4]))
		{
			if ($cards_num[0] == $cards_num[1])
			{
				return array(self::FOUR_EQ, array(1,4), array(0,1,2,3));
			}
			return array(self::FOUR_EQ, array(1,0), array(1,2,3,4));
		}
		
		// fikk vi: hus? (kombinasjon: 3-2 eller 2-3)
		if ($cards_num[0] == $cards_num[1] && $cards_num[3] == $cards_num[4] && ($cards_num[0] == $cards_num[2] || $cards_num[4] == $cards_num[2]))
		{
			if ($cards_num[0] == $cards_num[2])
			{
				return array(self::HOUSE, array(0,4), array(0,1,2,3,4));
			}
			return array(self::HOUSE, array(4,0), array(0,1,2,3,4));
		}
		
		// fikk vi: flush?
		if ($flush)
		{
			return array(self::FLUSH, array(4,3,2,1,0), array(0,1,2,3,4));
		}
		
		// fikk vi: straight?
		if ($straight)
		{
			// straight med A som 1?
			if ($cards_num[0] == 1 && $cards_num[4] == 13)
			{
				return array(self::STRAIGHT, array(3), array(0,1,2,3,4));
			}
			return array(self::STRAIGHT, array(4), array(0,1,2,3,4));
		}
		
		// fikk vi: tre like?
		if ($cards_num[0] == $cards_num[2])
		{
			return array(self::THREE_EQ, array(2,4,3), array(0,1,2));
		}
		if ($cards_num[1] == $cards_num[3])
		{
			return array(self::THREE_EQ, array(2,4,0), array(1,2,3));
		}
		if ($cards_num[2] == $cards_num[4])
		{
			return array(self::THREE_EQ, array(2,1,0), array(2,3,4));
		}
		
		// fikk vi: to par?
		if (($cards_num[0] == $cards_num[1] && ($cards_num[2] == $cards_num[3] || $cards_num[3] == $cards_num[4])) || ($cards_num[1] == $cards_num[2] && $cards_num[3] == $cards_num[4]))
		{
			if ($cards_num[0] == $cards_num[1])
			{
				if ($cards_num[2] == $cards_num[3])
				{
					return array(self::TWO_PAIRS, array(2,0,4), array(0,1,2,3));
				}
				return array(self::TWO_PAIRS, array(3,0,2), array(0,1,3,4));
			}
			return array(self::TWO_PAIRS, array(3,1,0), array(1,2,3,4));
		}
		
		// fikk vi: ett par?
		if ($cards_num[0] == $cards_num[1])
		{
			return array(self::ONE_PAIR, array(0,4,3,2), array(0,1));
		}
		elseif ($cards_num[1] == $cards_num[2])
		{
			return array(self::ONE_PAIR, array(1,4,3,0), array(1,2));
		}
		elseif ($cards_num[2] == $cards_num[3])
		{
			return array(self::ONE_PAIR, array(2,4,1,0), array(2,3));
		}
		elseif ($cards_num[3] == $cards_num[4])
		{
			return array(self::ONE_PAIR, array(3,2,1,0), array(3,4));
		}
		
		// fikk ingen ting! highcard
		return array(self::HIGHCARD, array(4,3,2,1,0), array(4));
	}
	
	/**
	 * Sett opp tekst for kombinasjonen man fikk
	 *
	 * @param int $type
	 * @param array Card $highcards
	 */
	public function solve_text($type, $highcards = array())
	{
		if (is_array($type))
		{
			$highcards = $type[1];
			$type = $type[0];
		}
		switch ($type)
		{
			// highcard
			case self::HIGHCARD:
				return 'Høyeste kort: <b>'.ucfirst($highcards[0]->sign()).'</b>';
			break;
			
			// ett par
			case self::ONE_PAIR:
				return 'Par i <b>'.$highcards[0]->sign(2).'</b>';
			break;
			
			// to par
			case self::TWO_PAIRS:
				return 'Dobbelt par i <b>'.$highcards[0]->sign(2).'</b> og <b>'.$highcards[1]->sign(2).'</b>';
			break;
			
			// tre like
			case self::THREE_EQ:
				return 'Tre like <b>'.$highcards[0]->sign(3).'</b>';
			break;
			
			// straight
			case self::STRAIGHT:
				return 'Straight med <b>'.$highcards[0]->sign().'</b> øverst';
			break;
			
			// flush
			case self::FLUSH:
				return 'Flush med <b>'.$highcards[0]->sign().'</b> øverst';
			break;
			
			// hus
			case self::HOUSE:
				return 'Hus med tre <b>'.$highcards[0]->sign(3).'</b> og to <b>'.$highcards[1]->sign(2).'</b>';
			break;
			
			// fire like
			case self::FOUR_EQ:
				return 'Fire like <b>'.$highcards[0]->sign(4).'</b>';
			break;
			
			// straight flush
			case self::STRAIGHT_FLUSH:
				return 'Straight flush med <b>'.$highcards[0]->sign().'</b> øverst';
			break;
			
			// royal straight flush
			case self::ROYALE_STRAIGHT_FLUSH:
				return 'Royal straight flush';
			break;
		}
		
		return "Ukjent $type";
	}
}