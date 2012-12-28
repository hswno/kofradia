<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

// hent statistikk
$time = $_base->date->get();
$time->modify("-50 days");
$time->setTime(0, 0, 0);
$expire = $time->format("U");

$stats_new = array();
$stats_die = array();
$today = $_base->date->get()->format("Y-m-d");
while (true)
{
	$day = $time->format("Y-m-d");
	$stats_new[$day] = 0;
	$stats_die[$day] = 0;
	$time->modify("+1 day");
	if ($day == $today) break;
}

$result = $_base->db->query("SELECT DATE(FROM_UNIXTIME(up_created_time)) day, COUNT(up_id) count FROM users_players WHERE up_created_time >= $expire GROUP BY DATE(FROM_UNIXTIME(up_created_time))");
while ($row = mysql_fetch_assoc($result))
{
	$stats_new[$row['day']] = (int) $row['count'];
}

$result = $_base->db->query("SELECT DATE(FROM_UNIXTIME(up_deactivated_time)) day, COUNT(up_id) count FROM users_players WHERE up_deactivated_time >= $expire GROUP BY DATE(FROM_UNIXTIME(up_deactivated_time))");
while ($row = mysql_fetch_assoc($result))
{
	$stats_die[$row['day']] = (int) $row['count'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Antall spillere"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

$bar = new OFC_Charts_Line();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# nye brukere");
$bar->text("Antall nye spillere");
$bar->values(array_values($stats_new));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Line();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# døde brukere");
$bar->text("Antall døde spillere");
$bar->values(array_values($stats_die));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(2)->rotate(330)->labels(array_keys($stats_new));
$ofc->axis_y()->set_numbers(0, max(max($stats_new), max($stats_die)));

$ofc->dark_colors();
$ofc->dump();