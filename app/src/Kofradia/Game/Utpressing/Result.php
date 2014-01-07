<?php namespace Kofradia\Game\Utpressing;

class Result {
	/**
	 * Utpressing-object
	 *
	 * @var \Kofradia\Game\Utpressing\Option
	 */
	public $option;

	/**
	 * Did it succeed?
	 *
	 * @var bool
	 */
	public $success = false;

	/**
	 * The change in wanted level. False if prison
	 *
	 * @var int|bool
	 */
	public $wanted = 0;

	/**
	 * The player attacked
	 *
	 * @var \player
	 */
	public $up;

	/**
	 * Money from bank?
	 *
	 * @var bool
	 */
	public $fromBank = false;

	/**
	 * Attack data
	 *
	 * @var array|null Null if no attack
	 */
	public $attack;

	/**
	 * The amount of cash the attacked gets
	 *
	 * This it not the same as the victim looses
	 * (some is lost to the game)
	 *
	 * @var int
	 */
	public $cash;

	/**
	 * The amount of cash the victim lost
	 *
	 * @var int
	 */
	public $cashLost;

	/**
	 * Constructor
	 *
	 * @param \Kofradia\Game\Utpressing
	 */
	public function __construct(\Kofradia\Game\Utpressing\Option $option)
	{
		$this->option = $option;
	}

	/**
	 * Get message explaining result
	 *
	 * @return string
	 */
	public function getMessage()
	{
		$msg = array();
		if ($this->success)
		{
			// didn't find player?
			if (!$this->up)
			{
				$msg[] = "Du fant ".\game::format_cash($this->cash)." liggende på gata.";
			}

			else
			{
				if ($this->fromBank)
				{
					$msg[] = sprintf("Du fant %s. Spilleren hadde ingen kontanter på seg, men du tok bankkortet til spilleren og fikk ut %s fra kontoen.",
						$this->up->profile_link(),
						\game::format_cash($this->cash));
				}
				else
				{
					$msg[] = sprintf("Du fant %s og prettet spilleren for %s.",
						$this->up->profile_link(),
						\game::format_cash($this->cash));
				}

				// player died?
				if ($this->attack && $this->attack['drept'])
				{
					$msg[] = 'Spilleren hadde så lite helse at spilleren døde av utpressingen din.';
					
					// witnesses
					if (count($this->attack['vitner']) == 0)
					{
						$msg[] = ' Ingen spillere vitnet drapet.';
					}
					
					else
					{
						// make list of witnesses
						$list = array();
						$count_other = 0;
						foreach ($this->attack['vitner'] as $vitne)
						{
							if ($vitne['visible']) $list[] = $vitne['up']->profile_link();
							else $count_other++;
						}
						if ($count_other > 0) $list[] = fwords("%d ukjent spiller", "%d ukjente spillere", $count_other);
						
						$msg[] = sentences_list($list).' vitnet drapet.';
					}
				}
			}

			$msg[] = 'Du mottok '.\game::format_num($this->points).' poeng.';
		}
		
		else
		{
			// found a player?
			if ($this->up)
			{
				// do the player have money in the bank?
				if ($this->up->data['up_bank'] > 10000)
				{
					$msg[] = sprintf("Du fant %s, men spillerne hadde verken kontanter eller bankkort på seg.",
						$this->up->profile_link());
				}

				else
				{
					$msg[] = sprintf("Du fant %s, men spilleren hadde ingen kontanter på seg. Du fikk tak i bankkortet til spilleren men det var ingen penger å hente der.",
						$this->up->profile_link());
				}
			}
			
			else
			{
				$msg[] = "Du mislykket utpressingsforsøket.";
			}
		}

		if ($this->wanted > 0)
		{
			$msg[] = sprintf("Wanted nivået økte med %s %%.",
				\game::format_number($this->wanted/10, 1));
		}

		return implode(" ", $msg);
	}
}
