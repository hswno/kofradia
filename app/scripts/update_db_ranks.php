<?php

// denne filen henter informasjon om rankene og lagrer til cache

game::$ranks = array(
	"items" => array(),
	"items_number" => array(),
	"pos" => array(),
	"pos_max" => 0
);

// hent rankene
$result = \Kofradia\DB::get()->query("SELECT id, name, points, rank_max_health, rank_max_energy FROM ranks ORDER BY points");

// sett opp data
$i = 0;
$last_id = 0;

while ($row = $result->fetch())
{
	// oppdater need_points til den forrige raden
	if ($last_id)
	{
		game::$ranks['items'][$last_id]['need_points'] = $row['points'] - game::$ranks['items'][$last_id]['points'];
		game::$ranks['items_number'][$i]['need_points'] = $row['points'] - game::$ranks['items'][$last_id]['points'];
	}
	
	$row['number'] = ++$i;
	game::$ranks['items'][$row['id']] = $row;
	game::$ranks['items_number'][$i] = game::$ranks['items'][$row['id']];
	
	$last_id = $row['id'];
}

// oppdater need_points til den siste raden
if ($last_id)
{
	game::$ranks['items'][$last_id]['need_points'] = 0;
	game::$ranks['items_number'][$i]['need_points'] = 0;
}

// hent rankene for posisjonene
$result = \Kofradia\DB::get()->query("SELECT pos, name FROM ranks_pos ORDER BY pos");

$i = 0;
while ($row = $result->fetch())
{
	$row['number'] = ++$i;
	game::$ranks['pos'][$row['pos']] = $row;
	game::$ranks['pos_max'] = max(game::$ranks['pos_max'], $row['pos']);
}

// lagre til cache
cache::store("ranks", game::$ranks);