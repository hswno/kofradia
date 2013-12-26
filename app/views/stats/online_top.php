<?php

// data:
// $sitestats
// $sitestats_max

\ess::$b->page->add_css('
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