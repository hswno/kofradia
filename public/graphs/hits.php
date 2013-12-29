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

$stats_normal = array();
$stats_redirect = array();
$today = $_base->date->get()->format("Y-m-d");
while (true)
{
	$day = $time->format("Y-m-d");
	$stats_normal[$day] = 0;
	$stats_redirect[$day] = 0;
	$time->modify("+1 day");
	if ($day == $today) break;
}

$result = \Kofradia\DB::get()->query("SELECT DATE(FROM_UNIXTIME(uhi_secs_hour)) day, SUM(uhi_hits) sum_hits, SUM(uhi_hits_redirect) sum_hits_redirect FROM users_hits WHERE uhi_secs_hour >= $expire GROUP BY DATE(FROM_UNIXTIME(uhi_secs_hour))");
while ($row = $result->fetch())
{
	$stats_normal[$row['day']] = (int) $row['sum_hits'];
	$stats_redirect[$row['day']] = (int) $row['sum_hits_redirect'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Antall sidevisninger"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

$bar = new OFC_Charts_Area();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# visninger");
$bar->text("Antall visninger");
$bar->values(array_values($stats_normal));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Area();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# videresendinger");
$bar->text("Antall videresendinger");
$bar->values(array_values($stats_redirect));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->rotate(330)->labels(array_keys($stats_normal))->steps(3);
$ofc->axis_y()->set_numbers(0, max(max($stats_normal), max($stats_redirect)));

$ofc->dark_colors();
$ofc->dump();