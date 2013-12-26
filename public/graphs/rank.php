<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

// TODO: range

// hent statistikk
$time = $_base->date->get();
$time->modify("-50 days");
$time->setTime(0, 0, 0);
$expire = $time->format("U");

$stats = array();
$today = $_base->date->get()->format("Y-m-d");
while (true)
{
	$day = $time->format("Y-m-d");
	$stats[$day] = 0;
	$time->modify("+1 day");
	if ($day == $today) break;
}

$result = $_base->db->query("SELECT DATE(FROM_UNIXTIME(uhi_secs_hour)) day, SUM(uhi_points) sum_points FROM users_hits WHERE uhi_secs_hour >= $expire GROUP BY DATE(FROM_UNIXTIME(uhi_secs_hour))");
while ($row = mysql_fetch_assoc($result))
{
	$stats[$row['day']] = (int) $row['sum_points'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Rankaktivitet"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

$bar = new OFC_Charts_Area();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# rankpoeng");
$bar->text("Antall rankpoeng");
$bar->values(array_values($stats));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->rotate(330)->labels(array_keys($stats))->steps(3);
$ofc->axis_y()->set_numbers(min(0, min($stats)), max($stats));

$ofc->dark_colors();
$ofc->dump();