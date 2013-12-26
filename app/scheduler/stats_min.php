<?php

global $_base;
$time = time();

// TODO: antall hits

// pålogget siste time
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_3600', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-3600");

// pålogget siste 15 min
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_900', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-900");

// pålogget siste 5 min
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_300', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-300");

// pålogget siste 1 min
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_60', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-60");

// pålogget siste 30 sek
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_30', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-30");