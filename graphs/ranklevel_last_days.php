<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

// annen bruker
$u_id = login::$user->id;
$up_name = login::$user->player->data['up_name'];
if (isset($_GET['up_id']) && access::has("crewet"))
{
	$up_id = (int) getval("up_id");
	$result = $_base->db->query("SELECT up_u_id, up_id, up_name FROM users_players WHERE up_id = $up_id");
	if (mysql_num_rows($result) == 0) ajax::text("ERROR:UP-404", ajax::TYPE_404);
	
	$u_id = mysql_result($result, 0, "up_u_id");
	$up_name = mysql_result($result, 0, "up_name");
}

// for 28 eller 10 dager?
$days = isset($_GET['long']) ? 30 : 10;

// hent statistikk for brukeren de siste 10 dagene som er ranket
$result = ess::$b->db->query("SELECT DATE(FROM_UNIXTIME(uhi_secs_hour)) day, SUM(uhi_points) sum_points FROM users_hits JOIN users_players ON uhi_up_id = up_id AND up_u_id = $u_id GROUP BY DATE(FROM_UNIXTIME(uhi_secs_hour)) ORDER BY day DESC LIMIT $days");
$stats = array();
$stats_avg = array();
$q = array();
while ($row = mysql_fetch_assoc($result))
{
	$stats[$row['day']] = $row['sum_points'];
	$stats_avg[$row['day']] = $row['sum_points'];
	$q[] = "SELECT '{$row['day']}' day, AVG(uhi_points) avg_points FROM (SELECT uhi_points FROM users_hits WHERE DATE(FROM_UNIXTIME(uhi_secs_hour)) = '{$row['day']}' AND uhi_points > 0 ORDER BY uhi_points DESC LIMIT $days) ref";
}

// beregn gjennomsnittet de siste dagene
if (count($q) > 0)
{
	$result = ess::$b->db->query(implode(" UNION ALL ", $q));
	while ($row = mysql_fetch_assoc($result))
	{
		$stats_avg[$row['day']] = (int) $row['avg_points'];
	}
}

else
{
	$today = ess::$b->date->get()->format("Y-m-d");
	$stats[$today] = 0;
	$stats_avg[$today] = 0;
}

// regn om til prosent
foreach ($stats as $day => &$value)
{
	if ($stats_avg[$day] > 0)
		$value = round($value / $stats_avg[$day] * 100, 1);
	else $value = (int) $value; 
}

$stats = array_reverse($stats);

$ofc = new OFC();
$ofc->title(new OFC_Title("Rankaktivitet"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

$bar = new OFC_Charts_Area();
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# %");
#$bar->text("Ranking ift. 5 beste rankere");
$bar->values(array_values($stats));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->labels(array_keys($stats))->steps(0);
$ofc->axis_y()->set_numbers(min(floor(min($stats)), 0), max(100, ceil(max($stats))));

$ofc->dark_colors();
$ofc->bg_colour("#282828");
$ofc->dump();