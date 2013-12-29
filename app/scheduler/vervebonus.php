<?php

/*
 * Dette scriptet gir bonus til spillere som har vervet andre spillere.
 * Oppgaven kjøres kl. 07:00 hver dag.
 * 
 * Selve vervingen er brukerbasert, slik at hvis en vervet spiller dør,
 * vil den nye spilleren telle (men spilleren som vervet vil ikke vite hvem
 * dette er). Hvis den som verver dør/deaktiverer vil den nye spilleren gjelde.
 */

$points_limit = 300000;
$points_bonus_factor = 0.10; // 10 % i bonus

// Øk bonusen til 20% i desember
if (time() > 1354320000 && time() < 1356998399)
{
	$points_bonus_factor = 0.20;
}

// oppdater brukerne med totalt rankepoeng
\Kofradia\DB::get()->exec("
	UPDATE users, (
		SELECT up_u_id, SUM(up_points) sum_up_points
		FROM users_players
		GROUP BY up_u_id) ref
	SET u_recruiter_points_now = GREATEST(0, sum_up_points), u_recruiter_points_last = IF(u_recruiter_points_last IS NULL, u_recruiter_points_now, u_recruiter_points_last)
	WHERE u_id = up_u_id AND u_recruiter_u_id IS NOT NULL");

// hent spillere som skal motta poeng og hvor mye poeng en bruker skal gi bort
$result = \Kofradia\DB::get()->query("
	SELECT
		up_id, up_name, u1.u_id, u1.u_email,
		u1.u_recruiter_points_last, u1.u_recruiter_points_now, u1.u_recruiter_points
	FROM
		users u1
		JOIN users u2 ON u1.u_recruiter_u_id = u2.u_id
		JOIN users_players ON u2.u_active_up_id = up_id
	WHERE
		u1.u_recruiter_points_last != u1.u_recruiter_points_now AND u1.u_recruiter_points < $points_limit AND u1.u_recruiter_points_last < u1.u_recruiter_points_now AND up_access_level != 0");

$players = array();
$players_count = array();

while ($row = $result->fetch())
{
	// beregn bonus
	$diff = $row['u_recruiter_points_now'] - $row['u_recruiter_points_last'];
	if ($row['u_recruiter_points'] + $diff > $points_limit)
	{
		$diff = $points_limit - $row['u_recruiter_points'];
	}
	$bonus = round($diff * $points_bonus_factor);
	if ($bonus <= 0) continue;
	
	putlog("LOG", "VERVEBONUS: {$row['up_name']} skal motta $bonus poeng fra {$row['u_email']} (#{$row['u_id']})");
	
	if (!isset($players[$row['up_id']]))
	{
		$players[$row['up_id']] = 0;
		$players_count[$row['up_id']] = 0;
	}
	
	$players[$row['up_id']] += $bonus;
	$players_count[$row['up_id']]++;
}

// gi rankpoengene
$total_bonus = 0;
$i = 0;
foreach ($players as $up_id => $bonus)
{
	$up = player::get($up_id);
	if ($up)
	{
		// legg til hendelse
		$up->add_log("verve_bonus", $players_count[$up_id], $bonus);
		
		// gi rankpoengene
		$up->increase_rank($bonus, false);
		
		// oppdater antall poeng vi har fått via verving
		\Kofradia\DB::get()->exec("UPDATE users_players SET up_points_recruited = up_points_recruited + $bonus WHERE up_id = $up_id");
	}
}


// oppdater brukerene med totalt rankepoeng for forrige oppdatering
\Kofradia\DB::get()->exec("
	UPDATE users
	SET
		u_recruiter_points_bonus = u_recruiter_points_bonus + ROUND($points_bonus_factor * IF(u_recruiter_points >= $points_limit, 0, LEAST($points_limit, u_recruiter_points + GREATEST(0, CAST(u_recruiter_points_now - u_recruiter_points_last AS SIGNED))) - u_recruiter_points)),
		u_recruiter_points = IF(u_recruiter_points >= $points_limit, u_recruiter_points, LEAST($points_limit, u_recruiter_points + GREATEST(0, CAST(u_recruiter_points_now - u_recruiter_points_last AS SIGNED)))),
		u_recruiter_points_last = u_recruiter_points_now
	WHERE u_recruiter_points_last != u_recruiter_points_now AND u_recruiter_u_id IS NOT NULL");