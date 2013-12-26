<?php

/*
 * Setter ned våpentreningen
 * Kjøres 1 gang per time
 * 
 * Hvis en spiller har under 25 % våpentrening mister spilleren våpenet (om det er bedre våpen enn glock)
 */

// sett ned våpentreningen
$expire = time() - 172800; // kun for de som har vært aktive siste 48 timer
ess::$b->db->query("
	UPDATE users_players
	SET up_weapon_training = GREATEST(0.1, up_weapon_training * 0.988)
	WHERE up_weapon_training > 0.1
		AND up_last_online > $expire");

// hent de spillerene som skal nedgradere eller miste våpenet sitt
$result = ess::$b->db->query("
	SELECT up_id, up_name, up_weapon_id, up_weapon_bullets
	FROM users_players
	WHERE up_weapon_training < 0.25 AND up_weapon_id > 1 AND up_access_level != 0");

while ($row = mysql_fetch_assoc($result))
{
	if (!isset(weapon::$weapons[$row['up_weapon_id']])) continue;
	$w = &weapon::$weapons[$row['up_weapon_id']];
	
	// fjern fra evt. auksjoner
	auksjon::player_release(null, $row['up_id'], auksjon::TYPE_KULER);
	
	// skal vi nedgradere våpenet?
	// man vil aldri miste våpen, det blir alltid nedgradert til dårligste våpen
	// beholder resten av koden i tilfelle vi ønsker å gjøre forandringer igjen
	if ($row['up_weapon_id'] > 1)
	{
		$new_id = $row['up_weapon_id'] - 1;
		$new_w = &weapon::$weapons[$new_id];
		$training = weapon::DOWNGRADE_TRAINING;
		
		// sett til 50 % på forrige våpen
		ess::$b->db->query("
			UPDATE users_players
			SET up_weapon_id = $new_id, up_weapon_bullets = 0, up_weapon_training = $training
			WHERE up_id = {$row['up_id']} AND up_weapon_id = {$row['up_weapon_id']}");
		
		if (ess::$b->db->affected_rows() > 0)
		{
			// gi hendelse
			player::add_log_static("weapon_lost", $row['up_weapon_id'].":".urlencode($w['name']).":".urlencode($row['up_weapon_bullets']).":".urlencode($new_w['name']).":".$training, 1, $row['up_id']);
			
			// logg
			putlog("LOG", "NEDGRADERT VÅPEN: {$row['up_name']} mistet våpenet {$w['name']} med {$row['up_weapon_bullets']} kuler grunnet lav våpentrening. Fikk i stedet våpenet {$new_w['name']}.");
		}
	}
	
	else
	{
		// gi hendelse
		player::add_log_static("weapon_lost", $row['up_weapon_id'].":".urlencode($w['name']).":".urlencode($row['up_weapon_bullets']), 0, $row['up_id']);
		
		// logg
		putlog("LOG", "MISTET VÅPEN: {$row['up_name']} mistet våpenet {$w['name']} med {$row['up_weapon_bullets']} kuler grunnet lav våpentrening.");
	}
}

unset($w);

if (mysql_num_rows($result) > 0)
{
	// fjern våpnene fra de som skal miste det
	ess::$b->db->query("
		UPDATE users_players
		SET up_weapon_id = NULL, up_weapon_bullets = 0
		WHERE up_weapon_training < 0.25 AND up_weapon_id > 1 AND up_access_level != 0");
}