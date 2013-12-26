<?php

/*
 * Dette scriptet forandrer helsen og energien til spilleren
 */

/*

Energien øker hvert minutt med 1 % av nåværende verdi, og med minimum 10 energipoeng.

*/


ess::$b->db->begin();
$time = time();


/*
 * Forandre energi
 */

// sett opp energien
ess::$b->db->query("
	UPDATE users_players
	SET
		up_energy = IF(
			up_energy > up_energy_max,
			GREATEST(up_energy_max, up_energy - GREATEST(up_energy*0.0005, 1)),
			LEAST(up_energy_max, up_energy + GREATEST(up_energy*0.01, 10))
		)
	WHERE
		up_access_level != 0
		AND up_energy != up_energy_max
		AND up_brom_ff_time < $time");



/*
 * Forandre helse
 */

// juster helsen
ess::$b->db->query("
	UPDATE users_players
	SET
		up_health = LEAST(up_health_max, up_health + IF(
			(up_energy / up_energy_max) < 0.33,
			-(0.33 - (up_energy / up_energy_max)) / 0.33 * 200,
			GREATEST(1, 80 * (1 - ABS((up_health / up_health_max) * 2 - 1)) * (up_health / up_health_max) * (up_energy / up_energy_max) * (up_energy / up_energy_max))
		)),
		up_health_ff_time = IF(up_health_ff_time IS NULL, up_health_ff_time, IF(up_health / up_health_max >= ".player::FF_HEALTH_LOW.", IF(up_health_ff_time = 0, $time, up_health_ff_time), 0))
	WHERE
		up_access_level != 0
		AND (up_health != up_health_max OR up_energy != up_energy_max)
		AND up_brom_ff_time < $time");


// finn spillere som ikke har mer helse og som dør
$result = ess::$b->db->query("SELECT up_id, up_attacked_time, up_attacked_up_id, up_attacked_ff_id_list FROM users_players WHERE up_access_level != 0 AND up_health <= 0");
while ($row = mysql_fetch_assoc($result))
{
	$player = player::get($row['up_id']);
	if (!$player) throw new HSException("Mangler spiller med ID {$row['up_id']}");
	
	$by_up = $player->bleed_handle();
	
	// sett spilleren død
	$ret = $player->dies(false, $by_up);
	
	// trigger
	if ($by_up)
	{
		$by_up->trigger("attack_bleed", array(
				"res" => $ret,
				"up" => $player));
	}
}

// finn spillere som skal miste FF
$result = ess::$b->db->query("
	SELECT up_id
	FROM users_players
		JOIN ff_members ON ffm_up_id = up_id AND ffm_status = ".ff_member::STATUS_MEMBER."
		JOIN ff ON ff_id = ffm_ff_id AND ff_inactive = 0 AND ff_is_crew = 0
	WHERE up_access_level != 0 AND up_health/up_health_max < ".player::FF_HEALTH_LOW."
	GROUP BY up_id");
while ($row = mysql_fetch_assoc($result))
{
	$player = player::get($row['up_id']);
	if (!$player) throw new HSException("Mangler spiller med ID {$row['up_id']}");
	
	$player->release_relations_low_health();
}


// finn spillere som skal miste hele tilknytningen til FF
// man mister medlemskapet dersom:
// - over 40 % helse og tid > 12 timer (denne oppgaven tar vi her)
// - over 40 % helse og blir medlem av FF
// - spiller blir aktivert
$expire = $time - 43200; // 12 timer
$result = ess::$b->db->query("
	UPDATE ff_members, users_players
	SET ffm_status = ".ff_member::STATUS_KICKED.", ffm_date_part = $time, up_health_ff_time = NULL
	WHERE up_access_level != 0 AND up_health_ff_time != 0 AND up_health_ff_time < $expire AND ffm_up_id = up_id AND ffm_status = ".ff_member::STATUS_DEACTIVATED);

ess::$b->db->commit();