<?php

require "config.php";
global $_base, $__server;

$_base->page->add_title("Aktive blokkeringer");

// hent liste
$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
$result = $pagei->query("SELECT u_id, up_id, up_name, up_access_level, ub_type, ub_time_added, ub_time_expire, ub_reason, ub_note FROM users, users_players, users_ban WHERE u_id = ub_u_id AND u_active_up_id = up_id AND ub_time_expire > ".time()." ORDER BY ub_time_expire");

echo '
<h1>Aktive blokkeringer</h1>
<p>Denne oversikten viser alle blokkeringer som er satt.</p>';

if (mysql_num_rows($result) == 0)
{
	echo '
<p>Ingen blokkeringer for øyeblikket satt.</p>';
}

else
{
	echo '
<table class="table">
	<thead>
		<tr>
			<th>Bruker/spiller</th>
			<th>Type blokkering</th>
			<th>Ble utestengt</th>
			<th>Utestengt til</th>
			<th>Begrunnelse</th>
			<th>Intern info</th>
		</tr>
	</thead>
	<tbody>';
	
	$i = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		$access = access::has(blokkeringer::$types[$row['ub_type']]['access']);
		
		echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
			<td>'.($access ? '<a href="'.$__server['relative_path'].'/min_side?u_id='.$row['u_id'].'&amp;b=blokk&amp;t='.$row['ub_type'].'">' : '').htmlspecialchars(blokkeringer::$types[$row['ub_type']]['title']).($access ? '</a>' : '').'</td>
			<td>'.$_base->date->get($row['ub_time_added'])->format(date::FORMAT_SEC).'</td>
			<td>'.$_base->date->get($row['ub_time_expire'])->format(date::FORMAT_SEC).'<br />
			'.game::timespan($row['ub_time_expire'], game::TIME_ABS).'</td>
			<td>'.game::format_data($row['ub_reason'], "bb-opt", "Ingen begrunnelse oppgitt.").'</td>
			<td>'.game::format_data($row['ub_note'], "bb-opt", "Ingen begrunnelse oppgitt.").'</td>
		</tr>';
	}
	
	echo '
	</tbody>
</table>
<p>'.$pagei->pagenumbers().'</p>';
}

$_base->page->load();