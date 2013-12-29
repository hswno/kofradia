<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

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

// annen dag?
$date = $_base->date->get();
if (isset($_GET['date']))
{
	$d = check_date($_GET['date'], "%y4%m2%d2");
	if (!$d) die("Invalid date.");
	
	$date->setDate($d[1], $d[2], $d[3]);
}

// finn tidspunkter
$date->setTime(0, 0, 0);
$time_from = $date->format("U");
$date->setTime(23, 59, 59);
$time_to = $date->format("U");

// sett opp timestatistikk
$stats = array();
$stats_redir = array();
$x = array();
for ($i = $time_from; $i <= $time_to; $i += 3600)
{
	$time = $_base->date->get($i);
	$stats[$time->format("H")] = 0;
	$stats_redir[$time->format("H")] = 0;
	
	$h = $time->format("H");
	$x[] = $h.":00 - ".($h+1).":00";
}

// hent timestatistikk
$result = \Kofradia\DB::get()->query("SELECT uhi_secs_hour, uhi_hits, uhi_hits_redirect FROM users_hits, users_players WHERE up_u_id = $u_id AND up_id = uhi_up_id AND uhi_secs_hour >= $time_from AND uhi_secs_hour <= $time_to");
while ($row = $result->fetch())
{
	$time = $_base->date->get($row['uhi_secs_hour']);
	$stats[$time->format("H")] = (int) $row['uhi_hits'];
	$stats_redir[$time->format("H")] = (int) $row['uhi_hits_redirect'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Sidevisninger for $up_name - ".$date->format(date::FORMAT_WEEKDAY)." ".$date->format(date::FORMAT_NOTIME)));

$bar = new OFC_Charts_Area();
$bar->text("Antall visninger");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# visninger");
$bar->values(array_values($stats));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Area();
$bar->text("Antall videresendinger");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# videresendinger");
$bar->values(array_values($stats_redir));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(2)->rotate(330)->labels($x);
$ofc->axis_y()->set_numbers(0, max(max($stats), max($stats_redir)));

$ofc->dark_colors();
echo $ofc;