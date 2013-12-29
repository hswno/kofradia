<?php

if (!function_exists("save_user_backup"))
{
	require PATH_APP . "/extra/func.save_user_backup.php";
}

putlog("INFO", "Renter! :D");

// sett opp renteoversikten
$rentetabell = array(
	array('10000000000', 0.0015),
	array('1000000000', 0.005),
	array('100000000', 0.02),
	array('10000000', 0.04),
	array('1000000', 0.06),
	array('100000', 0.09),
	array('10000', 0.12),
	array('1000', 0.15),
	array('0', 0)
);

// pålogget siste 12 timene
$time = time();
$last_online = $time - 43200;

// lagre backup
save_user_backup();

// lås brukertabellen
\Kofradia\DB::get()->exec("LOCK TABLES users_players WRITE");

// sett last_interest feltet til bankbeløpet så vi kan beregne forskjell
\Kofradia\DB::get()->exec("UPDATE users_players SET up_interest_last = 0 WHERE up_interest_last != 0");

// gå gjennom hvert rentenivå og gi renter
$last0 = 0;
$last1 = 0;
foreach ($rentetabell as $row)
{
	if ($last1 != 0)
	{
		// gi renter for denne prisgruppen
		\Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_interest_last = up_interest_last + LEAST(CAST(up_bank AS SIGNED) - {$row[0]}, $last0 - {$row[0]}) * $last1,
				up_bank = up_bank + LEAST(CAST(up_bank AS SIGNED) - {$row[0]}, $last0 - {$row[0]}) * $last1
			WHERE up_access_level != 0 AND up_bank > {$row[0]} AND up_last_online >= $last_online");
	}
	
	$last0 = $row[0];
	$last1 = $row[1];
}

// lagre backup
save_user_backup();

\Kofradia\DB::get()->exec("
	UPDATE users_players
	SET up_interest_total = up_interest_total + up_interest_last, up_interest_num = up_interest_num + 1, up_log_new = up_log_new + 1
	WHERE up_interest_last > 0");

// lås opp brukertabellen
\Kofradia\DB::get()->exec("UNLOCK TABLES");

// lagre logg melding
\Kofradia\DB::get()->exec("
	INSERT INTO users_log (time, ul_up_id, type, note, num)
	SELECT $time, up_id, ".gamelog::$items['renter'].", NULL, up_interest_last
	FROM users_players
	WHERE up_interest_last > 0");

// finn ut hvor mye renter som ble gitt
$result = \Kofradia\DB::get()->query("
	SELECT COUNT(up_id), SUM(up_interest_last)
	FROM users_players
	WHERE up_interest_last > 0 AND up_access_level < ".ess::$g['access_noplay']);
$row = $result->fetch(\PDO::FETCH_NUM);
$ant = game::format_number($row[0]);
$sum = game::format_cash($row[1]);

putlog("INFO", "%bRENTER:%b %u$ant%u spillere mottok renter! Totalt %u$sum%u ble gitt ut i form av renter!");

// renter gitt til nostat
$result = \Kofradia\DB::get()->query("
	SELECT COUNT(up_id), SUM(up_interest_last)
	FROM users_players
	WHERE up_interest_last > 0 AND up_access_level >= ".ess::$g['access_noplay']);
$row = $result->fetch(\PDO::FETCH_NUM);
$ant = game::format_number($row[0]);
$sum = game::format_cash($row[1]);

putlog("CREWCHAN", "%bRENTER-NOSTAT:%b %u$ant%u spillere mottok renter! Totalt %u$sum%u ble gitt ut i form av renter!");

save_user_backup();