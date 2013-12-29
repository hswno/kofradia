<?php

/*
 * Dette scriptet henter topp 10 spillere på ranklista
 * mellom 21:00 dagen før og 21:00 i dag og gir bonus
 * til spillerene
 */

$d = ess::$b->date->get();
$d->modify("-1 day");
$d->setTime(21, 0, 0);
$date_from = $d->format("U");

$d->modify("+1 day");
$date_to = $d->format("U");

// tabell over hvor mange prosent bonus man får
$tabell = array(
	1 => .3,
	.27,
	.24,
	.21,
	.18,
	.15,
	.12,
	.09,
	.06,
	.03
);

// hent statistikk
$result = \Kofradia\DB::get()->query("
	SELECT uhi_up_id, SUM(uhi_points) sum_uhi_points
	FROM users_hits
		JOIN users_players ON up_id = uhi_up_id AND up_access_level < ".ess::$g['access_noplay']." AND up_access_level != 0
	WHERE uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to
	GROUP BY uhi_up_id
	HAVING sum_uhi_points > 0
	ORDER BY sum_uhi_points DESC
	LIMIT 10");

$total_bonus = 0;
$i = 1;
while ($row = $result->fetch())
{
	$up = player::get($row['uhi_up_id']);
	if ($up)
	{
		// beregn bonus
		$bonus = round($row['sum_uhi_points'] * $tabell[$i]);
		
		// legg til hendelse
		$up->add_log("rank_bonus", $i.":".$tabell[$i], $bonus);
		
		// gi rankpoengene
		$up->increase_rank($bonus, false);
		
		// logg
		putlog("LOG", "RANKBONUS: {$up->data['up_name']} fikk $i. plass for 24 timer ranking (fikk $bonus rankpoeng, opptjent {$row['sum_uhi_points']} poeng siste 24 timer)");
		
		$i++;
	}
}