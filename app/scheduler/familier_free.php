<?php

global $_base, $__server;

/**
 * Dette scriptet avslutter familiekonkurranser og setter de familiene
 * som ikke oppnådde mistekravet ellers om tapte konkurransen som døde.
 */

// hent konkurranser som skal avsluttes
$result_faf = \Kofradia\DB::get()->query("SELECT fff_id, fff_time_expire, fff_required_points FROM ff_free WHERE fff_time_expire <= ".time()." AND fff_active = 1");

while ($faf = $result_faf->fetch())
{
	// rankoversikt for familiene
	$ff = array();
	$ff_rank = array();
	
	// hent familiene som har konkurrert i denne konkurransen
	$result = \Kofradia\DB::get()->query("SELECT ff_id FROM ff WHERE ff_fff_id = {$faf['fff_id']} AND ff_inactive = 0");
	while ($row = $result->fetch())
	{
		$familie = ff::get_ff($row['ff_id'], ff::LOAD_SCRIPT);
		if (!$familie) continue;
		
		// har ikke oppnådd minstekravet?
		$rank_points = $familie->competition_rank_points();
		if ($rank_points < $familie->data['fff_required_points'])
		{
			$familie->dies();
		}
		
		else
		{
			$ff[$familie->id] = $familie;
			if (!isset($ff_rank[0]) || $ff_rank[0] < $rank_points)
			{
				$ff_rank = array($rank_points, $familie->id);
			}
		}
	}
	
	// ingen ff har oppnådd minstekravet?
	if (count($ff) == 0)
	{
		putlog("INFO", "Ingen broderskap overlevde eller klarte minstekravet i broderskapkonkurransen. {$__server['path']}/ff/?fff_id={$faf['fff_id']}");
		
		// kjør ny konkurranse
		ff::create_competition();
		
		// sett konkurransen som avsluttet
		\Kofradia\DB::get()->exec("UPDATE ff_free SET fff_active = 0 WHERE fff_id = {$faf['fff_id']}");
	}
	
	else
	{
		// legg ned familiene som ikke vant
		foreach ($ff as $familie)
		{
			if ($familie->id == $ff_rank[1]) continue;
			$familie->dies();
		}
		
		// utrop vinneren
		$familie = $ff[$ff_rank[1]];
		$familie->competition_won();
		
		// sett status for konkurransen at bygning skal velges
		\Kofradia\DB::get()->exec("UPDATE ff_free SET fff_time_expire_br = ".(time()+86400).", fff_active = 2 WHERE fff_id = {$faf['fff_id']}");
		
		// sett opp scheduler for bygning
		\Kofradia\DB::get()->exec("
			UPDATE scheduler, (
				SELECT MIN(fff_time_expire_br) fff_time, COUNT(fff_id) fff_count FROM ff_free WHERE fff_active = 2
			) ref
			SET s_next = IF(fff_count > 0, fff_time, s_next), s_active = IF(fff_count > 0, 1, 0)
			WHERE s_name = 'familier_br'");
	}
}


// sett scheduler til neste konkurrase
$scheduler_skip_next = true;
\Kofradia\DB::get()->exec("
	UPDATE scheduler, (
		SELECT MIN(fff_time_expire) fff_time, COUNT(fff_id) fff_count FROM ff_free WHERE fff_active = 1
	) ref
	SET s_next = IFNULL(fff_time, s_next), s_active = IF(fff_count > 0, 1, 0)
	WHERE s_name = 'familier_free'");