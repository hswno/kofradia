<?php

require "graphs_base.php";
ajax::require_user();

// annen bruker
$u_id = login::$user->id;
$up_name = login::$user->player->data['up_name'];
if (isset($_GET['up_id']) && access::has("mod"))
{
	$up_id = (int) getval("up_id");
	$result = \Kofradia\DB::get()->query("SELECT up_u_id, up_id, up_name FROM users_players WHERE up_id = $up_id");
	if ($result->rowCount() == 0) ajax::text("ERROR:UP-404", ajax::TYPE_404);
	
	$row = $result->fetch();
	$u_id = $row['up_u_id'];
	$up_name = $row['up_name'];
}

// sett opp tidspunkt
$date = ess::$b->date->get();
$time_end = $date->format("U");
$day_end = $date->format("Y-m-d");
$date->modify("-30 days");
$date->setTime(0, 0, 0);
$time_start = $date->format("U");

$stats = array();
while (true)
{
	$day = $date->format("Y-m-d");
	$stats[$day] = 0;
	$date->modify("+1 day");
	if ($day == $day_end) break;
}

// hent statistikk
$result = \Kofradia\DB::get()->query("
	SELECT DATE(FROM_UNIXTIME(poker_time_start)) AS day, SUM(CONVERT(poker_prize - poker_cash, SIGNED) * IF((poker_winner = 1 AND poker_starter_up_id = up_id) OR (poker_winner = 2 AND poker_challenger_up_id = up_id), 1, -1)) sum_result
	FROM poker, users_players
	WHERE poker_time_start >= $time_start AND poker_time_start <= $time_end AND up_u_id = $u_id AND (up_id = poker_starter_up_id OR up_id = poker_challenger_up_id) AND poker_state = 4
	GROUP BY DATE(FROM_UNIXTIME(poker_time_start))");
while ($row = $result->fetch())
{
	$stats[$row['day']] = (float) $row['sum_result'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Pokerstatistikk for $up_name siste 30 dager"));

$bar = new OFC_Charts_Area();
$bar->text("Resultat av poker");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# kr");
$bar->values(array_values($stats));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(2)->rotate(330)->labels(array_keys($stats));
$ofc->axis_y()->set_numbers(min(0, min($stats)), max($stats));

$ofc->dark_colors();
echo $ofc;