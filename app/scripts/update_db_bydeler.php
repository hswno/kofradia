<?php

// denne filen henter bydelene og lagrer til cache

$result = \Kofradia\DB::get()->query("SELECT * FROM bydeler ORDER BY name");
game::$bydeler = array();
while ($row = $result->fetch())
{
	game::$bydeler[$row['id']] = $row;
}

// lagre til cache
cache::store("bydeler", game::$bydeler, 0);