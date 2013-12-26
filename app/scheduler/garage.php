<?php

/*
 * Scriptet fjerner garasjer og biler hos de som ikke har betalt leie innen fristen
 */

// hent garasjene som skal fjernes
$result = ess::$b->db->query("
	SELECT ugg_up_id, ugg_b_id, COUNT(id) count_ug, up_access_level
	FROM users_garage
		LEFT JOIN users_gta ON ug_up_id = ugg_up_id AND b_id = ugg_b_id
		JOIN users_players ON ugg_up_id = up_id
	WHERE ugg_time_next_rent <= ".time()."
	GROUP BY ugg_id");

while ($row = mysql_fetch_assoc($result))
{
	$bydel = game::$bydeler[$row['ugg_b_id']]['name'];
	
	// lagre logg hvis spilleren fremdeles er i live
	if ($row['up_access_level'] != 0)
	{
		player::add_log_static("garage_lost", urlencode($bydel), $row['count_ug'], $row['ugg_up_id']);
	}
	
	// slett garasjen
	ess::$b->db->query("DELETE FROM users_garage WHERE ugg_up_id = {$row['ugg_up_id']} AND ugg_b_id = {$row['ugg_b_id']}");
	
	// slett evt. biler
	ess::$b->db->query("DELETE FROM users_gta WHERE ug_up_id = {$row['ugg_up_id']} AND b_id = {$row['ugg_b_id']}");
}