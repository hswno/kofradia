<?php

require "../../app/ajax.php";
ajax::require_user();

// kontroller lås
ajax::validate_lock();

// hent alle utfordringer
$result = \Kofradia\DB::get()->query("SELECT poker_id, poker_starter_up_id, poker_time_start, poker_starter_cards, poker_cash FROM poker WHERE poker_state = 2 ORDER BY poker_cash");

$i = 0;
$data = array();
$html_to_parse = array();
while ($row = $result->fetch())
{
	$d = array();
	$d['self'] = $row['poker_starter_up_id'] == login::$user->player->id;
	$html_to_parse[$i] = (!$d['self'] ? '<input type="radio" name="id" value="'.$row['poker_id'].'" />' : '') . '<user id="'.$row['poker_starter_up_id'].'" />';
	$d['cash'] = game::format_cash($row['poker_cash']);
	$d['reltime'] = poker_round::get_time_text($row['poker_time_start']);
	
	if (access::has("admin"))
	{
		$cards = new CardsPoker(explode(",", $row['poker_starter_cards']));
		$d['cards'] = $cards->solve_text($cards->solve());
	}
	
	$data[$i++] = $d;
}

// parse html
if (count($html_to_parse) > 0)
{
	$html_to_parse = parse_html_array($html_to_parse);
	foreach ($html_to_parse as $i => $value)
	{
		$data[$i]['player'] = $value;
	}
}

ajax::text(js_encode($data), ajax::TYPE_OK);