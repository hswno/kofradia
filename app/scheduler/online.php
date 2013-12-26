<?php

# Scheduler - Online
# Kofradia
# A: Henrik Steen

global $_base;

$hour = $_base->date->get()->format("G");
$now = time();

// hent stats
$result = $_base->db->query("SELECT name, extra, value, time FROM sitestats");
$sitestats = array();
$sitestats_max = array();

while ($row = mysql_fetch_assoc($result))
{
	$sitestats[$row['name']][$row['extra']] = $row;
	$sitestats[$row['name']][$row['extra']] = $row;
	
	if (!array_key_exists($row['name'], $sitestats_max))
	{
		$sitestats_max[$row['name']] = $row;
	}
	else
	{
		if ($row['value'] > $sitestats_max[$row['name']]['value'])
		{
			$sitestats_max[$row['name']] = $row;
		}
	}
}


// antall pålogget siste 30 sekunder
$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online > ".($now-30));
$ant = mysql_result($result, 0);

// ny all-time rekord?
if ($ant > $sitestats_max['max_online_30']['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_30' AND extra = $hour");
	putlog("INFO", "Ny all-time rekord for antall pålogget i løpet av %u30%u sekunder! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats_max['max_online_30']['time'])->format()."%u og lød på %u".game::format_number($sitestats_max['max_online_30']['value'])."%u spillere!");
}

// høyere enn rekorden for denne timen?
elseif ($ant > $sitestats['max_online_30'][$hour]['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_30' AND extra = $hour");
	putlog("INFO", "Ny rekord for antall pålogget i løpet av %u30%u sekunder for denne timen! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats['max_online_30'][$hour]['time'])->format()."%u og lød på %u".game::format_number($sitestats['max_online_30'][$hour]['value'])."%u spillere!");
}


// antall pålogget siste 60 sekunder
$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online > ".($now-60));
$ant = mysql_result($result, 0);

// ny all-time rekord?
if ($ant > $sitestats_max['max_online_60']['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_60' AND extra = $hour");
	putlog("INFO", "Ny all-time rekord for antall pålogget i løpet av %u1%u minutt! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats_max['max_online_60']['time'])->format()."%u og lød på %u".game::format_number($sitestats_max['max_online_60']['value'])."%u spillere!");
}

// høyere enn rekorden for denne timen?
elseif ($ant > $sitestats['max_online_60'][$hour]['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_60' AND extra = $hour");
	putlog("INFO", "Ny rekord for antall pålogget i løpet av %u1%u minutt for denne timen! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats['max_online_60'][$hour]['time'])->format()."%u og lød på %u".game::format_number($sitestats['max_online_60'][$hour]['value'])."%u spillere!");
}


// antall pålogget siste 300 sekunder
$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online > ".($now-300));
$ant = mysql_result($result, 0);

// ny all-time rekord?
if ($ant > $sitestats_max['max_online_300']['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_300' AND extra = $hour");
	putlog("INFO", "Ny all-time rekord for antall pålogget i løpet av %u5%u minutter! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats_max['max_online_300']['time'])->format()."%u og lød på %u".game::format_number($sitestats_max['max_online_300']['value'])."%u spillere!");
}

// høyere enn rekorden for denne timen?
elseif ($ant > $sitestats['max_online_300'][$hour]['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_300' AND extra = $hour");
	putlog("INFO", "Ny rekord for antall pålogget i løpet av %u5%u minutter for denne timen! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats['max_online_300'][$hour]['time'])->format()."%u og lød på %u".game::format_number($sitestats['max_online_300'][$hour]['value'])."%u spillere!");
}


// antall pålogget siste 900 sekunder
$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online > ".($now-900));
$ant = mysql_result($result, 0);

// ny all-time rekord?
if ($ant > $sitestats_max['max_online_900']['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_900' AND extra = $hour");
	putlog("INFO", "Ny all-time rekord for antall pålogget i løpet av %u15%u minutter! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats_max['max_online_900']['time'])->format()."%u og lød på %u".game::format_number($sitestats_max['max_online_900']['value'])."%u spillere!");
}

// høyere enn rekorden for denne timen?
elseif ($ant > $sitestats['max_online_900'][$hour]['value'])
{
	$result = $_base->db->query("UPDATE sitestats SET value = $ant, time = $now WHERE name = 'max_online_900' AND extra = $hour");
	putlog("INFO", "Ny rekord for antall pålogget i løpet av %u15%u minutter for denne timen! Rekorden lyder på %u".game::format_number($ant)."%u spillere! Forrige rekord ble satt %u".$_base->date->get($sitestats['max_online_900'][$hour]['time'])->format()."%u og lød på %u".game::format_number($sitestats['max_online_900'][$hour]['value'])."%u spillere!");
}