<?php

/*
 * Dette scriptet setter ned wanted nivået til en spiller
 */

$i = 0;
while (true)
{
	// for mange feil?
	if (++$i > 2)
	{
		sysreport::log("For mange feilforsøk. Kunne ikke fullføre schedule fengsel.", "Scheduler: Fengsel feilet");
		break;
	}
	
	try
	{
		// sett ned wanted level med 15 %
		ess::$b->db->query("UPDATE users_players SET up_wanted_level = IF(up_wanted_level <= 3, 0, LEAST(1000, up_wanted_level * 0.85)) WHERE up_wanted_level != 0");
		break;
	}
	catch (SQLQueryException $e)
	{
		sysreport::exception_caught($e, "Scheduler: Fengsel");
	}
}