<?php

require "../../base.php";
global $_base;

access::need("mod");
$_base->page->theme_file = "doc";

if (!isset($_GET['ip']))
{
	redirect::handle("finn");
}

$ip = $_GET['ip'];

echo '
<h1>Økter på IP</h1>
<p class="h_right"><a href="finn?ip='.urlencode($ip).'">Tilbake</a></p>
<p><span class="dark">IP-adresse:</span> '.htmlspecialchars($ip).'</p>
<p>IP-adressen kan inneholde ? og * for å søke etter ukjente siffer.</p>';

// sortering
$sort = new sorts("sort");
$sort->append("asc", "Økt ID", "ses_id");				$sort->append("desc", "Økt ID", "ses_sid DESC");
$sort->append("asc", "Bruker ID", "ses_u_id");			$sort->append("desc", "Bruker ID", "ses_u_id DESC");
$sort->append("asc", "IP-adresse", "ses_last_ip");		$sort->append("desc", "IP-adresse", "ses_last_ip DESC");
#$sort->append("asc", "Brukernavn", "user");	$sort->append("desc", "Brukernavn", "user DESC");
$sort->append("asc", "Opprettet", "ses_created_time");	$sort->append("desc", "Opprettet", "ses_created_time DESC");
$sort->append("asc", "Sist aktiv", "ses_last_time");	$sort->append("desc", "Sist aktiv", "ses_last_time DESC");
$sort->append("asc", "Hits", "ses_hits");				$sort->append("desc", "Hits", "ses_hits DESC");
$sort->append("asc", "Rank", "ses_points");				$sort->append("desc", "Rank", "ses_points DESC");
$sort->set_active(getval("sort"), 7);

function like_search($value)
{
	return strtr($value, array('_' => '\\_', '%' => '\\%', '*' => '%', '?' => '_'));
}

$wc = strpos($ip, "*") !== false || strpos($ip, "?") !== false;

// hent data
$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 200);
$sort_info = $sort->active();
$result = $pagei->query("SELECT ses_id, ses_u_id, ses_active, ses_created_time, ses_last_time, ses_logout_time, ses_hits, ses_points, ses_last_ip, u_email, u_access_level, ses_browsers, up_name, up_id, up_access_level FROM sessions LEFT JOIN users ON u_id = ses_u_id LEFT JOIN users_players ON up_id = u_active_up_id WHERE ses_last_ip LIKE ".like_search($_base->db->quote($ip))." ORDER BY {$sort_info['params']}");

// ingen treff?
if (mysql_num_rows($result) == 0)
{
	echo '
<p>Ingen treff.</p>';
}

else
{
	echo '
<p>Antall treff: '.$pagei->total.'</p>
<table class="table nowrap" width="100%" style="font-size: 11px">
	<thead>
		<tr>
			<th>Økt ID '.$sort->show_link(0, 1).'</th>
			<th>Bruker ID '.$sort->show_link(2, 3).'</th>'.($wc ? '
			<th>IP-adresse '.$sort->show_link(4, 5).'</th>' : '').'
			<th>Brukerens aktive spiller</th>
			<th>Aktiv</th>
			<th>Opprettet '.$sort->show_link(6, 7).'</th>
			<th>Sist aktiv '.$sort->show_link(8, 9).'</th>
			<th>Logget ut</th>
			<th>Hits '.$sort->show_link(10, 11).'</th>
			<th>Rank '.$sort->show_link(12, 13).'</th>
			<th>Nettlesere</th>
		</tr>
	</thead>
	<tbody>';
			
	$i = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td class="r">'.$row['ses_id'].'</td>
			<td class="r">'.$row['ses_u_id'].'</td>'.($wc ? '
			<td>'.$row['ses_last_ip'].'</td>' : '').'
			<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
			<td>'.($row['ses_active'] == 0 ? 'Nei' : '<b>Ja</b>').'</td>
			<td>'.$_base->date->get($row['ses_created_time'])->format(date::FORMAT_SEC).'</td>
			<td>'.$_base->date->get($row['ses_last_time'])->format(date::FORMAT_SEC).'</td>
			<td>'.($row['ses_logout_time'] == 0 ? '<b>Nei</b>' : $_base->date->get($row['ses_logout_time'])->format(date::FORMAT_SEC)).'</td>
			<td class="r">'.game::format_number($row['ses_hits']).'</td>
			<td class="r">'.game::format_number($row['ses_points']).'</td>
			<td>'.(empty($row['ses_browsers']) ? '<i>Mangler</i>' : strtr(htmlspecialchars($row['ses_browsers']), "\n", "<br />")).'</td>
		</tr>';
	}
	
	echo '
	</tbody>
</table>';
	
	// flere sider?
	if ($pagei->pages > 1)
	{
		echo '
<p>Navigasjon: '.$pagei->pagenumbers().'</p>';
	}
}

$_base->page->load();