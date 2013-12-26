<?php

// denne filen henter bydelene og lagrer til cache

$result = ess::$b->db->query("SELECT * FROM bydeler ORDER BY name");
game::$bydeler = array();
while ($row = mysql_fetch_assoc($result))
{
	game::$bydeler[$row['id']] = $row;
}

// lagre til cache
cache::store("bydeler", game::$bydeler, 0);