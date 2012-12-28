<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

// må være medlem av crewet
access::need("crewet");

// hent informasjon om crewloggen
$expire = $_base->date->get();
$expire->setTime(0, 0, 0);
$expire->modify("-59 days"); // vis siste 60 dagene
$expire = $expire->format("U");
$result = $_base->db->query("
	SELECT up_name, COUNT(lc_id) num_actions, DATE(FROM_UNIXTIME(lc_time)) day
	FROM log_crew JOIN users_players ON lc_up_id = up_id AND up_access_level != 0 AND up_access_level != 1 
	WHERE lc_time >= $expire
	GROUP BY lc_up_id, DATE(FROM_UNIXTIME(lc_time))
	ORDER BY day");

// les statistikk
$days = array();
$days_max = 0;
$users = array();
while ($row = mysql_fetch_assoc($result))
{
	$users[$row['up_name']][$row['day']] = (int) $row['num_actions'];
	$days[$row['day']] = (isset($days[$row['day']]) ? (int)$days[$row['day']] : 0) + (int) $row['num_actions'];
	$days_max = max($days_max, $row['num_actions']);
}

// sorter statistikk
$stats = array();
foreach ($days as $day => $total)
{
	foreach ($users as $user => $user_days)
	{
		$stats[$user][$day] = isset($user_days[$day]) ? $user_days[$day] : 0;
	}
}

$ofc = new OFC();
$c = new OFC_Colours();
$ofc->title(new OFC_Title("Aktivitet i crewloggen"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

foreach ($stats as $user => $days)
{
	$bar = new OFC_Charts_Line();
	$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("Antall registreringer #x_label# for $user: #val#");
	$bar->text($user);
	$bar->values(array_values($days));
	$bar->colour($c->pick());
	$ofc->add_element($bar);
}

$ofc->axis_x()->label()->rotate(340)->labels(array_keys($days))->steps(5);
$ofc->axis_y()->set_numbers(0, min(20, $days_max));

$ofc->dark_colors();
$ofc->dump();