<?php

require "graphs_base.php";
ajax::require_user();
global $_base, $_lang;

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

// annen m�ned?
$date = $_base->date->get();
if (isset($_GET['date']))
{
	$d = check_date($_GET['date'], "%y4");
	if (!$d) die("Invalid date.");
	
	$date->setDate($d[1], 1, 1);
}

// finn tidspunkter
$date->setDate($date->format("Y"), 1, 1);
$date->setTime(0, 0, 0);
$time_from = $date->format("U");
$date->modify("+1 year -1 sec");
$time_to = $date->format("U");

// sett opp timestatistikk
$stats1 = array();
$stats2 = array();
$x = array();
for ($i = 1; $i <= 12; $i++)
{
	$stats1[$i] = 0;
	$stats2[$i] = 0;
	$x[] = $_lang['months'][$i];
}

// hent statistikk
$result = $_base->db->query("SELECT MONTH(FROM_UNIXTIME(ft_time)) AS month, COUNT(ft_id) num FROM forum_topics JOIN users_players ON ft_up_id = up_id WHERE up_u_id = $u_id AND ft_time >= $time_from AND ft_time <= $time_to GROUP BY MONTH(FROM_UNIXTIME(ft_time))");
while ($row = mysql_fetch_assoc($result))
{
	$stats1[$row['month']] = (int) $row['num'];
}
$result = $_base->db->query("SELECT MONTH(FROM_UNIXTIME(fr_time)) AS month, COUNT(fr_id) num FROM forum_replies JOIN users_players ON fr_up_id = up_id WHERE up_u_id = $u_id AND fr_time >= $time_from AND fr_time <= $time_to GROUP BY MONTH(FROM_UNIXTIME(fr_time))");
while ($row = mysql_fetch_assoc($result))
{
	$stats2[$row['month']] = (int) $row['num'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Aktivitet i forumet for $up_name - ".$date->format("Y")));

$bar = new OFC_Charts_Area();
$bar->text("Antall forumsvar");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# svar");
$bar->values(array_values($stats2));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Area();
$bar->text("Antall forumtr�der");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# tr�der");
$bar->values(array_values($stats1));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(1)->rotate(330)->labels($x);
$ofc->axis_y()->set_numbers(0, max(max($stats1), max($stats2)));

$ofc->dark_colors();
echo $ofc;