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

// annen måned?
$date = $_base->date->get();
if (isset($_GET['date']))
{
	$d = check_date($_GET['date'], "%y4%m");
	if (!$d) die("Invalid date.");
	
	$date->setDate($d[1], $d[2], 1);
}

// finn tidspunkter
$date->setDate($date->format("Y"), $date->format("n"), 1);
$date->setTime(0, 0, 0);
$time_from = $date->format("U");
$date->modify("+1 month -1 sec");
$time_to = $date->format("U");

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
}

// hent dagstatistikk
$result = $_base->db->query("SELECT DAY(FROM_UNIXTIME(uhi_secs_hour)) AS day, SUM(uhi_hits) sum_hits, SUM(uhi_hits_redirect) sum_hits_redirect FROM users_hits, users_players WHERE up_u_id = $u_id AND up_id = uhi_up_id AND uhi_secs_hour >= $time_from AND uhi_secs_hour <= $time_to GROUP BY DAY(FROM_UNIXTIME(uhi_secs_hour))");
while ($row = mysql_fetch_assoc($result))
{
	$stats[$row['day']] = (int) $row['sum_hits'];
	$stats_redir[$row['day']] = (int) $row['sum_hits_redirect'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Sidevisninger for $up_name - ".$date->format(date::FORMAT_MONTH)." ".$date->format("Y")));

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