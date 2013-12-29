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

// finn første oppføring i statistikk
/*
// sett opp timestatistikk
$days = $date->format("t");
$month = $date->format(date::FORMAT_MONTH);
$stats = array();
$stats_redir = array();
$x = array();
for ($i = 1; $i <= $days; $i++)
{
	$stats[$i] = 0;
	$stats_redir[$i] = 0;
	$x[] = "$i. ".$month;
}*/

$stats = array();
$stats_redir = array();
for ($i = 1; $i <= 7; $i++)
{
	$stats[$i] = 0;
	$stats_redir[$i] = 0;
}

// hent dagstatistikk
$result = \Kofradia\DB::get()->query("SELECT WEEKDAY(FROM_UNIXTIME(uhi_secs_hour)) AS date, SUM(uhi_hits) sum_hits, SUM(uhi_hits_redirect) sum_hits_redirect FROM users_hits, users_players WHERE up_u_id = $u_id AND up_id = uhi_up_id GROUP BY WEEKDAY(FROM_UNIXTIME(uhi_secs_hour)) ORDER BY date");
while ($row = $result->fetch())
{
	$stats[$row['date']+1] = (int) $row['sum_hits'];
	$stats_redir[$row['date']+1] = (int) $row['sum_hits_redirect'];
}

$x = array();
global $_lang;
foreach ($stats as $date => $dummy)
{
	if ($date == 7) $date = 0;
	$x[] = $_lang['weekdays'][$date];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Sidevisninger for $up_name"));

$bar = new OFC_Charts_Bar();
$bar->text("Antall visninger");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# visninger");
$bar->values(array_values($stats));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Bar();
$bar->text("Antall videresendinger");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# videresendinger");
$bar->values(array_values($stats_redir));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(ceil(count($x)/20))->rotate(330)->labels($x);
$ofc->axis_y()->set_numbers(0, max(max($stats), max($stats_redir)));

$ofc->dark_colors();
echo $ofc;