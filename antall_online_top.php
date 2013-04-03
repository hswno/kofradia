<?php

require "base.php";
global $_base;

$_base->page->add_title("Antall pålogget rekorder");

// hent stats
$result = $_base->db->query("SELECT name, extra, value, time FROM sitestats");
$sitestats = array();
$sitestats_max = array();

while ($row = mysql_fetch_assoc($result))
{
	$sitestats[$row['name']][$row['extra']] = $row;
	$sitestats[$row['name']][$row['extra']] = $row;
	
	if (!array_key_exists($row['name'], $sitestats_max))
	{
		$sitestats_max[$row['name']] = $row;
	}
	else
	{
		if ($row['value'] > $sitestats_max[$row['name']]['value'])
		{
			$sitestats_max[$row['name']] = $row;
		}
	}
}

$_base->page->add_css('
.stats_rekord tbody th, .stats_rekord tbody td {
	text-align: right;
}');

echo '
<h1>Antall pålogget rekorder</h1>

<table class="table stats_rekord center tablemb">
	<thead>
		<tr>
			<th>Time</th>
			<th>30 sekunder</th>
			<th>1 minutt</th>
			<th>5 minutter</th>
			<th>15 minutter</th>
		</tr>
	</thead>
	<tbody>';

$color = false;
foreach ($sitestats['max_online_30'] as $hour => $info1)
{
	echo '
		<tr'.($color = !$color ? ' class="color"' : '').'>
			<th>'.$hour.'</th>
			<td>'.game::format_number($info1['value']).'</td>
			<td>'.game::format_number($sitestats['max_online_60'][$hour]['value']).'</td>
			<td>'.game::format_number($sitestats['max_online_300'][$hour]['value']).'</td>
			<td>'.game::format_number($sitestats['max_online_900'][$hour]['value']).'</td>
		</tr>';
}

echo '
		<tr'.($color = !$color ? ' class="color"' : '').' style="font-weight: bold">
			<th>Rekord</th>
			<td>'.game::format_number($sitestats_max['max_online_30']['value']).'</td>
			<td>'.game::format_number($sitestats_max['max_online_60']['value']).'</td>
			<td>'.game::format_number($sitestats_max['max_online_300']['value']).'</td>
			<td>'.game::format_number($sitestats_max['max_online_900']['value']).'</td>
		</tr>
	</tbody>
</table>';

$_base->page->load();