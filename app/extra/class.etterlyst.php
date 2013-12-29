<?php

class etterlyst
{
	/** Hvor lang tid det må gå før en oppføring kan kjøpes ut */
	const FREEZE_TIME = 604800; // 1 uke
	
	/** Hvor lang tid man må vente mellom hver nye hitlist man oppretter */
	const WAIT_TIME = 3600;
	
	/** Minste beløpet man kan sette en dusør på */
	const MIN_AMOUNT_SET = 500000;
	
	/** Minste beløpet man kan kjøpe ut en dusør for */
	const MIN_AMOUNT_BUYOUT = 100000;
	
	/** Minste prosentsats man kan kjøpe ut en dusør for (hvis større enn MIN_AMOUNT_BUYOUT) */
	const MIN_AMOUNT_BUYOUT_RATIO = 0.1; // 10 %
	
	/**
	 * Finn tidspunkt for når en oppføring har gått ut på tid og kan kjøpes ut
	 */
	public static function get_freeze_expire()
	{
		return time() - self::FREEZE_TIME;
	}
	
	/**
	 * Fjern en dusør hvis den eksisterer (ved drap)
	 * @param player $up spilleren det gjelder
	 * @param player $killer spilleren som forårsaket at spilleren ble drept/døde av skadene sine
	 * @param bool $instant døde spilleren momentant?
	 */
	public static function player_dies(player $up, player $killer = NULL, $instant = NULL)
	{
		// hent og fjern mulig dusør
		$hitlist = \Kofradia\DB::get()->query("SELECT hl_id, hl_by_up_id, hl_time, hl_amount_valid FROM hitlist WHERE hl_up_id = $up->id");
		if ($hitlist->rowCount() == 0) return array("hitlist" => 0);
		
		// fjern alle oppføringene
		\Kofradia\DB::get()->exec("DELETE FROM hitlist WHERE hl_up_id = $up->id");
		
		// deaktiverte seg?
		if (!$killer)
		{
			$list = array();
			
			// sett opp oversikt gruppert over hvem som satt hitlistene
			while ($row = $hitlist->fetch())
			{
				if (!isset($list[$row['hl_by_up_id']])) $list[$row['hl_by_up_id']] = 0;
				$list[$row['hl_by_up_id']] += $row['hl_amount_valid'];
			}
			
			// pengene skal gis tilbake til de spillerene som satte dusøren, dersom de fremdeles er i live
			foreach ($list as $up_id => $sum)
			{
				// gi pengene tilbake hvis spilleren fremdeles er i live
				$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_bank = up_bank + $sum WHERE up_id = $up_id AND up_access_level != 0");
				
				// fikk penger?
				if ($a > 0)
				{
					// hendelse om at pengene er returnert
					player::add_log_static("etterlyst_deactivate", $up->id, $sum, $up_id);
				}
			}
			
			return array(
				"hitlist" => 0
			);
		}
		
		// ble drept og spilleren som drepte er i live
		elseif ($killer->active)
		{
			// beregn total verdi
			$sum = 0;
			$oldest_time = null;
			while ($row = $hitlist->fetch())
			{
				$sum += $row['hl_amount_valid'];
				if ($oldest_time == null || $row['hl_time'] < $oldest_time) $oldest_time = $row['hl_time'];
			}
			
			// gi pengene
			$f = $instant ? 'up_cash' : 'up_bank';
			\Kofradia\DB::get()->exec("UPDATE users_players SET $f = $f + $sum WHERE up_id = $killer->id");
			
			$killer->data[$f] = bcadd($killer->data[$f], $sum);
			
			// hendelse om at man mottok penger fra hitlist for drapet
			// gamelog syntax: offer_up_id:bool(instant?)
			$killer->add_log("etterlyst_receive", $up->id.":".($instant ? '1' : '0'), $sum);
			
			return array(
				"hitlist" => $sum,
				"hitlist_oldest_time" => $oldest_time
			);
		}
	}
	
	/**
	 * Senk deler av en dusør (ved skadet angrep)
	 * @param player $up spilleren det gjelder
	 * @param player $attacker spilleren som angrep
	 * @param float $health_f for hvor mye helse spilleren mistet (i forhold til maksverdien til spilleren)
	 */
	public static function player_hurt(player $up, player $attacker, $health_f)
	{
		\Kofradia\DB::get()->beginTransaction();
		
		// hent informasjon om spilleren
		$result = \Kofradia\DB::get()->query("
			SELECT SUM(hl_amount_valid) AS sum_hl_amount_valid
			FROM (
				SELECT hl_amount_valid
				FROM hitlist
				WHERE hl_up_id = $up->id
				FOR UPDATE
			) ref");
		
		$hl = $result->fetch();
		if (!$hl || $hl['sum_hl_amount_valid'] <= 0)
		{
			\Kofradia\DB::get()->commit();
			return 0;
		}
		$sum = $hl['sum_hl_amount_valid'];
		
		// hvor mye vi får
		$amount = bcmul($sum, $health_f);
		if ($amount <= 0)
		{
			\Kofradia\DB::get()->commit();
			return 0;
		}
		
		// trekk pengene fra hitlist
		\Kofradia\DB::get()->exec("SET @t := $amount");
		\Kofradia\DB::get()->exec("
			UPDATE hitlist h, (
				SELECT
					hl_id,
					GREATEST(0, LEAST(@t, hl_amount_valid)) AS to_remove,
					@t := GREATEST(0, @t - hl_amount_valid)
				FROM hitlist
				WHERE hl_up_id = $up->id AND @t > 0
				ORDER BY hl_time
			) r
			SET h.hl_amount_valid = h.hl_amount_valid - to_remove
			WHERE h.hl_id = r.hl_id");
		\Kofradia\DB::get()->exec("DELETE FROM hitlist WHERE hl_amount_valid = 0");
		
		// gi pengene til spilleren
		\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + $amount WHERE up_id = $attacker->id");
		$attacker->data['up_cash'] = bcadd($attacker->data['up_cash'], $amount);
		
		// hendelse om at man mottok penger fra hitlist for angrepet
		$attacker->add_log("etterlyst_receive", $up->id.":1:1", $amount);
		
		\Kofradia\DB::get()->commit();
		return $amount;
	}
}
