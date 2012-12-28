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
	$result = $_base->db->query("SELECT up_u_id, up_id, up_name FROM users_players WHERE up_id = $up_id");
	if (mysql_num_rows($result) == 0) ajax::text("ERROR:UP-404", ajax::TYPE_404);
	
	$u_id = mysql_result($result, 0, "up_u_id");
	$up_name = mysql_result($result, 0, "up_name");
}

// hent statistikk
$time = $_base->date->get();
$today = $time->format("Y-m-d");
$time->modify("-50 days");
$time->setTime(0, 0, 0);
$time_from = $time->format("U");

$stats_normal = array();
$stats_redirect = array();
while (true)
{
	$day = $time->format("Y-m-d");
	$stats[$day] = 0;
	$stats_redir[$day] = 0;
	$time->modify("+1 day");
	if ($day == $today) break;
}
$time->modify("-1 sec");
$time_to = $time->format("U");

// hent dagstatistikk
$result = $_base->db->query("SELECT DATE(FROM_UNIXTIME(uhi_secs_hour)) AS date, SUM(uhi_hits) sum_hits, SUM(uhi_hits_redirect) sum_hits_redirect FROM users_hits, users_players WHERE up_u_id = $u_id AND up_id = uhi_up_id AND uhi_secs_hour >= $time_from AND uhi_secs_hour <= $time_to GROUP BY DATE(FROM_UNIXTIME(uhi_secs_hour))");
while ($row = mysql_fetch_assoc($result))
{
	$stats[$row['date']] = (int) $row['sum_hits'];
	$stats_redir[$row['date']] = (int) $row['sum_hits_redirect'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Sidevisninger for $up_name siste periode"));

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

$ofc->axis_x()->steps(2)->label()->steps(4)->rotate(330)->labels(array_keys($stats));
$ofc->axis_y()->set_numbers(0, max(max($stats), max($stats_redir)));

$ofc->dark_colors();
echo $ofc;