<?php

require "graphs_base.php";
ajax::require_user();

// annen bruker
$u_id = login::$user->id;
$up_name = login::$user->player->data['up_name'];
if (isset($_GET['up_id']) && access::has("mod"))
{
	$up_id = (int) getval("up_id");
	$result = ess::$b->db->query("SELECT up_u_id, up_id, up_name FROM users_players WHERE up_id = $up_id");
	if (mysql_num_rows($result) == 0) ajax::text("ERROR:UP-404", ajax::TYPE_404);
	
	$u_id = mysql_result($result, 0, "up_u_id");
	$up_name = mysql_result($result, 0, "up_name");
}

// sett opp tidspunkt
$date = ess::$b->date->get();
$time_end = $date->format("U");
$day_end = $date->format("Y-m-d");
$date->modify("-30 days");
$date->setTime(0, 0, 0);
$time_start = $date->format("U");

$stats_wins = array();
$stats_loss = array();
$stats_u = array();
while (true)
{
	$day = $date->format("Y-m-d");
	$stats_wins[$day] = 0;
	$stats_loss[$day] = 0;
	$stats_u[$day] = 0;
	$date->modify("+1 day");
	if ($day == $day_end) break;
}

// hent statistikk
$result = $_base->db->query("
	SELECT DATE(FROM_UNIXTIME(poker_time_start)) AS day, COUNT(IF(poker_winner = 0, 1, NULL)) num_uavgjort,
		COUNT(IF((poker_winner = 1 AND poker_starter_up_id = up_id) OR (poker_winner = 2 AND poker_challenger_up_id = up_id), 1, NULL)) num_wins,
		COUNT(IF((poker_winner = 1 AND poker_starter_up_id = up_id) OR (poker_winner = 2 AND poker_challenger_up_id = up_id), NULL, 1)) num_loss
	FROM poker, users_players
	WHERE poker_time_start >= $time_start AND poker_time_start <= $time_end AND up_u_id = $u_id AND (up_id = poker_starter_up_id OR up_id = poker_challenger_up_id) AND poker_state = 4
	GROUP BY DATE(FROM_UNIXTIME(poker_time_start))");
$max = 0;
while ($row = mysql_fetch_assoc($result))
{
	$stats_wins[$row['day']] = (int) $row['num_wins'];
	$stats_loss[$row['day']] = (int) $row['num_loss'];
	$stats_u[$row['day']] = (int) $row['num_uavgjort'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Pokerstatistikk for $up_name siste 30 dager"));

$bar = new OFC_Charts_Line();
$bar->text("Antall vunnet");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>Vunnet #val# runder");
$bar->values(array_values($stats_wins));
$bar->colour(OFC_Colours::$colours[2]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Line();
$bar->text("Antall tapt");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>Tapt #val# runder");
$bar->values(array_values($stats_loss));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Line();
$bar->text("Antall uavgjort");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# runder uavgjort");
$bar->values(array_values($stats_u));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(2)->rotate(330)->labels(array_keys($stats_wins));
$ofc->axis_y()->set_numbers(0, max(max($stats_wins), max($stats_loss), max($stats_u)));
$ofc->dark_colors();
echo $ofc;