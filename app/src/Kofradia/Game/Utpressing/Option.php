<?php namespace Kofradia\Game\Utpressing;

use Kofradia\Game\Utpressing;

class Option {
	/**
	 * How much the attacker keeps from what the victim looses
	 *
	 * @var float
	 */
	const CASH_KEEP_FACTOR = 0.8;

	/**
	 * The option text
	 *
	 * @var string
	 */
	public $text;

	/**
	 * The probability for this option
	 *
	 * @var float
	 */
	public $prob;

	/**
	 * The number of points we get
	 *
	 * @var int
	 */
	public $points;

	/**
	 * Cash range from (factor)
	 *
	 * @var float
	 */
	public $cash_min;

	/**
	 * Cash range to (factor)
	 *
	 * @var float
	 */
	public $cash_max;

	/**
	 * The max amount of cash we can get
	 *
	 * @var int
	 */
	public $cash_max_real;

	/**
	 * The result set of the option after executing
	 *
	 * @var \Kofradia\Game\Utpressing\Result
	 */
	public $result;

	/**
	 * Constructor
	 *
	 * @param \Kofradia\Game\Utpressing
	 * @param string Option text
	 * @param float Probability
	 * @param int Points we get on success
	 * @param float Cash from factor
	 * @param float Cash to factor
	 * @param int Max amount of cash
	 */
	public function __construct(Utpressing $ut, $text, $prob, $points, $cash_min, $cash_max, $cash_max_real)
	{
		$this->ut = $ut;
		$this->text = $text;
		$this->prob = $prob;
		$this->points = $points;
		$this->cash_min = $cash_min;
		$this->cash_max = $cash_max;
		$this->cash_max_real = $cash_max_real;
	}

	/**
	 * Attempt the action
	 *
	 * @return \Kofradia\Game\Utpressing\Result
	 */
	public function attempt()
	{
		$this->result = new Result($this);
		if ($this->result->success = $this->testProb())
		{
			$this->handleSuccess();
		}
		else
		{
			$this->handleFailure();
		}

		return $this->result;
	}

	/**
	 * Get max cash amount we can get
	 *
	 * @return int
	 */
	protected function getCashMax()
	{
		return $this->ut->up->rank['number'] / count(\game::$ranks['items']) * $this->cash_max_real / 2 + $this->cash_max_real / 2;
	}

	/**
	 * Get amount of cash for this option
	 *
	 * @return int
	 */
	protected function getCash()
	{
		$cash_max = $this->getCashMax();
		return round(rand($cash_max * $this->cash_min, $cash_max * $this->cash_max));
	}

	/**
	 * Get amount of points for this option
	 *
	 * @return int
	 */
	protected function getPoints()
	{
		return rand($this->points - 1, $this->points + 1);
	}

	/**
	 * Handle success
	 */
	protected function handleSuccess()
	{
		// how much cash to get?
		$cash = $this->getCash();

		// find players we can try
		$players = $this->getPlayers($cash);

		while (count($players) > 0)
		{
			// pick one random player
			$key = array_rand($players);
			$row = $players[$key];
			unset($players[$key]);

			// try this player
			if ($this->handleSuccessPlayer($row, $cash))
			{
				break;
			}
		}

		$points = $this->getPoints();

		$this->result->points = isset($this->result->attack) && $this->result->attack['drept']
			? $result->attack['rankpoeng']
			: $points;

		// don't give rank points if victim is killed
		// it will be handled by other functions
		if (!isset($this->result->attack) || !$this->result->attack['drept'])
		{
			$this->ut->up->increase_rank($points);
		}
		
		// if we have no player (none found matching criterias)
		// then we only get half the money
		$this->result->cash = round($cash * static::CASH_KEEP_FACTOR);
		if (!$this->result->up)
		{
			$this->result->cash = round($this->result->cash / 2);
		}

		// hand over the money
		\Kofradia\DB::get()->prepare("
			UPDATE users_players
			SET up_utpressing_last = ?, up_cash = up_cash + ?
			WHERE up_id = ?")
			->execute(array(
				time(),
				$this->result->cash,
				$this->ut->up->id));
		$this->ut->up->data['up_utpressing_last'] = time();
		$this->ut->up->update_money($this->result->cash, true, false);
		
		// increase wanted level
		$this->result->wanted = $this->ut->up->fengsel_rank($this->result->points, true);
		
		// triggers
		$this->ut->up->trigger("utpressing", array("option" => $this));
		if ($this->result->up && $this->result->up->active)
		{
			$this->result->up->trigger("utpresset", array("option" => $this));
		}
	}

	/**
	 * Try to attack a specific player
	 *
	 * @param array Data about the player
	 * @param int Cash to get
	 * @return bool|null True on success
	 */
	protected function handleSuccessPlayer($player, $cash)
	{
		$rank = \Kofradia\Game\Rank\Points::getRank($player['up_points']);
		$affect = $this->ut->getAffectedTable($rank);

		// can not have too little energy
		if ($player['up_energy'] < $affect['energy']*2)
		{
			return;
		}

		// now take money
		$a = \Kofradia\DB::get()->prepare("
			UPDATE users_players
			SET up_bank = IF(up_cash < ?, up_bank - ?, up_bank),
				up_cash = IF(up_cash >= ?, up_cash - ?, up_cash)
			WHERE up_id = ? AND (up_cash >= ? OR up_bank >= ?)");
		if (!$a->execute(array($cash, $cash, $cash, $cash, $player['up_id'], $cash, $cash)))
		{
			// did not succeed
			return;
		}

		$this->result->up = \player::get($player['up_id']);
		$this->result->cashLost = $cash;
		$this->result->fromBank = $this->result->up->data['up_cash'] < $cash; // TODO: this cannot be checked this way?

		// notify victim
		$this->result->up->add_log("utpressing", $this->ut->up->id, $cash);

		// log
		putlog("SPAMLOG", "%c11%bUTPRESSING:%b%c %u{$this->ut->up->data['up_name']}%u presset %u{$this->result->up->data['up_name']}%u for %u".\game::format_cash($cash)."%u".($this->result->fromBank ? ' (fra bankkonto)' : ''));
		\Kofradia\DB::get()->prepare("
			INSERT INTO utpressinger
			SET
				ut_action_up_id = ?,
				ut_affected_up_id = ?,
				ut_b_id = ?,
				ut_time = ?")
			->execute(array(
				$this->ut->up->id,
				$this->result->up->id,
				$this->ut->up->data['up_b_id'],
				time()));

		// the victim always looses energy
		// but only health if the money comes from the hand
		// (don't really know why we made it this way)
		$this->result->up->energy_use($affect['energy']);
		if (!$this->result->fromBank)
		{
			$this->result->attack = $this->result->up->health_decrease($affect['health'], $this->ut->up, \player::ATTACK_TYPE_UTPRESSING);
		}

		return true;
	}

	/**
	 * Handle failure
	 */
	protected function handleFailure()
	{
		// wanted level
		$this->result->wanted = $this->ut->up->fengsel_rank($this->getPoints());
		
		\Kofradia\DB::get()->prepare("
			UPDATE users_players
			SET up_utpressing_last = ?
			WHERE up_id = ?")
			->execute(array(
				time(),
				$this->ut->up->id));
		$this->ut->up->data['up_utpressing_last'] = time();
		
		// we can fail totally, which basicly mean
		// we don't find a player at all
		// or we can try find a player and pretend we
		// didn't manage to take the money
		if (rand(1, 100) <= 30)
		{
			return;
		}
		
		$cash = $this->getCash();
		$w = $this->getPlayersCriterias($cash);

		// find a matching player
		$result = \Kofradia\DB::get()->prepare("
			SELECT up_id
			FROM (
				SELECT up_id
				FROM users_players
				WHERE {$w[0]}
				ORDER BY up_last_online DESC
				LIMIT 100
			) ref
			ORDER BY RAND()
			LIMIT 1");
		$result->execute($w[1]);
		
		// no players?
		$row = $result->fetch();
		if (!$row)
		{
			return;
		}
		
		$this->result->up = \player::get($row['up_id']);
		$this->result->cashLost = $cash;
	}

	/**
	 * Get players we can attack
	 *
	 * @param int Cash we are looking for
	 * @return array
	 */
	protected function getPlayers($cash)
	{
		$w = $this->getPlayersCriterias($cash);

		// retrieve players
		$result = \Kofradia\DB::get()->prepare("
			SELECT up_id, up_cash, up_energy, up_points
			FROM users_players
			WHERE {$w[0]}
			ORDER BY up_last_online DESC
			LIMIT 100");
		$result->execute($w[1]);
		$players = array();
		while ($row = $result->fetch())
		{
			$players[] = $row;
		}

		return $players;
	}

	/**
	 * Get criterias for player match
	 *
	 * @param int Cash to retrieve
	 * @return array(string statement, array prepared_data)
	 */
	protected function getPlayersCriterias($cash)
	{
		$p = array();

		// players to ignore
		$up_ignore = $this->getFFPlayers();
		$up_ignore[] = $this->ut->up->id;

		// limit by time online
		$time_limit = time() - 604800 * 2;

		// ranklimits
		$ranks = $this->getRankLimits();
		
		// criterias for finding player
		$where = "up_access_level != 0";
		$where .= " AND up_last_online >= ?";  $p[] = $time_limit;
		$where .= " AND up_b_id = ?";          $p[] = $this->ut->up->data['up_b_id'];
		$where .= " AND up_fengsel_time < ?";  $p[] = time();
		$where .= " AND up_brom_expire < ?";   $p[] = time();
		$where .= " AND up_points >= ?";       $p[] = $ranks[0]->points;
		
		// upper rank limit
		if ($ranks[1])
		{
			$where .= " AND up_points < ?";
			$p[] = $ranks[1]->points;
		}
		
		if ($this->result->success)
		{
			$where .= " AND (up_cash >= ? OR up_bank >= ?)";
			$p[] = $cash;
			$p[] = $cash;
		}
		else
		{
			$where .= " AND up_cash < $cash";
		}
		
		$where .= " AND up_id NOT IN (".implode(",", $up_ignore).")";
		
		if (MAIN_SERVER)
		{
			$where .= access::is_nostat()
				? " AND up_access_level >= ".ess::$g['access_noplay']
				: " AND up_access_level < ".ess::$g['access_noplay'];
		}
		
		return array($where, $p);
	}

	/**
	 * Get the players that are in a same FF as us
	 *
	 * We don't want to attack a allied
	 *
	 * TODO: Move this to player-class for reuse
	 *
	 * @return array(int up_id, ..)
	 */
	protected function getFFPlayers()
	{
		$result = \Kofradia\DB::get()->prepare("
			SELECT DISTINCT f2.ffm_up_id
			FROM ff_members f1
				JOIN ff ON ff_id = f1.ffm_ff_id AND ff_is_crew = 0
				JOIN ff_members f2 ON f1.ffm_ff_id = f2.ffm_ff_id AND f2.ffm_status = 1 AND f2.ffm_up_id != f1.ffm_up_id
			WHERE f1.ffm_up_id = ? AND f1.ffm_status = 1");
		$result->execute(array($this->ut->up->id));
		$up_ids = array();
		while ($row = $result->fetch())
		{
			$up_ids[] = $row['ffm_up_id'];
		}
		
		return $up_ids;
	}

	/**
	 * Get rank limits
	 *
	 * We can only find players 2 ranks below and 2 ranks above ourself
	 *
	 * If we are first rank, or highest rank, it will adjust so it will be
	 * a total of 5 possible ranks
	 *
	 * @return array(TODO)
	 */
	protected function getRankLimits()
	{
		$num_min = max(1, $this->ut->up->rank['number'] - 2);
		$num_max = $num_min + 4;
		
		$ranks = \Kofradia\Game\Rank\Points::getRanks();
		$c = count($ranks);
		if ($num_max > $c)
		{
			$num_min -= $num_max - $c;
			$num_max = $c;
		}
		
		return array(
			$ranks[$num_min],
			$num_max == $c ? null : $ranks[$num_max+1]
		);
	}

	/**
	 * Test probability
	 *
	 * @return bool
	 */
	protected function testProb()
	{
		return rand(0, 100) <= $this->prob;
	}

}