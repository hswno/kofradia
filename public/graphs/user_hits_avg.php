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

// annen måned?
$date = $_base->date->get();
if (isset($_GET['date']))
{
	$d = check_date($_GET['date'], "%y4%m");
	if (!$d) die("Invalid date.");
	
	$date->setDate($d[1], $d[2], 1);
}

$stats = array();
$stats_redir = array();
$x_label = array();
for ($i = 0; $i <= 23; $i++)
{
	$stats[$i] = 0;
	$stats_redir[$i] = 0;
	$x_label[] = "$i:00 - ".($i+1).":00";
}

// hent timestatistikk
$result = \Kofradia\DB::get()->query("SELECT HOUR(FROM_UNIXTIME(uhi_secs_hour)) AS hour, SUM(uhi_hits) AS sum_hits, SUM(uhi_hits_redirect) AS sum_hits_redirect FROM users_hits, users_players WHERE up_u_id = $u_id AND uhi_up_id = up_id GROUP BY HOUR(FROM_UNIXTIME(uhi_secs_hour))");
while ($row = $result->fetch())
{
	$stats[$row['hour']] = (int) $row['sum_hits'];
	$stats_redir[$row['hour']] = (int) $row['sum_hits_redirect'];
}

$ofc = new OFC();
$c = new OFC_Colours();
$ofc->title(new OFC_Title("Gjennomsnittlig antall visninger for $up_name"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

$bar = new OFC_Charts_Area();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# visninger");
$bar->text("Antall visninger");
$bar->values(array_values($stats));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Area();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# videresendinger");
$bar->text("Antall videresendinger");
$bar->values(array_values($stats_redir));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->rotate(330)->labels($x_label)->steps(2);
$ofc->axis_y()->set_numbers(0, max(max($stats), max($stats_redir)));

$ofc->dark_colors();
echo $ofc;