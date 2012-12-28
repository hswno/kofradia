<?php

// vis en liste over de siste registrerte brukerene

require "config.php";
global $_base;
$_base->page->theme_file = "doc";

// antall brukere
$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 20);
$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players");
$pagei->set_total(mysql_result($result, 0));
$pagei->calc();

$expire = time() - 604800; // 1 uke
$result = $_base->db->query("
	SELECT
		up.up_id, up.up_name, up.up_access_level, up.up_hits, up.up_last_online, up.up_created_time,
		up.u_id, up.u_email, up.u_created_ip, up.u_online_ip,
		COUNT(ref.up_id) AS ip_count
	FROM 
		(SELECT users_players.*, u_id, u_email, u_created_ip, u_online_ip FROM users_players JOIN users ON u_id = up_u_id ORDER BY up_created_time DESC LIMIT $pagei->start, $pagei->per_page) AS up
		LEFT JOIN (SELECT users.*, users_players.* FROM users_players JOIN users ON up_u_id = u_id) AS ref ON ref.u_created_ip = up.u_created_ip AND ref.u_id != up.u_id AND ref.up_access_level != 0 AND ref.up_last_online > $expire
	GROUP BY up.up_id
	ORDER BY up.up_created_time DESC");

echo '
<h1>Siste registrerte spillere</h1>
<table class="table tablemf">
	<thead>
		<tr>
			<th>ID</th>
			<th>Spiller</th>
			<th>E-post</th>
			<th>Reg IP<br />(for brukeren)</th>
			<th>Siste IP<br />(for brukeren)</th>
			<th>Hits</th>
			<th>Registrert</th>
			<th>Sist pålogget</th>
		</tr>
	</thead>
	<tbody>';

$color = true;
while ($row = mysql_fetch_assoc($result))
{
	echo '
		<tr'.($color = !$color ? ' class="color"' : '').'>
			<td>'.$row['up_id'].'</td>
			<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
			<td>'.htmlspecialchars($row['u_email']).' (#'.$row['u_id'].')</td>
			<td><a href="finn?ip='.urlencode($row['u_created_ip']).'">'.htmlspecialchars($row['u_created_ip']).'</a></td>
			<td>'.($row['ip_count'] > 0 ? '<b style="color: #FF0000">'.game::format_number($row['ip_count']).'</b> - ' : '').'<a href="finn?ip='.urlencode($row['u_online_ip']).'">'.htmlspecialchars($row['u_online_ip']).'</a></td>
			<td class="r">'.game::format_number($row['up_hits']).'</td>
			<td>'.$_base->date->get($row['up_created_time'])->format().'</td>
			<td>'.$_base->date->get($row['up_last_online'])->format().'</td>
		</tr>';
}

echo '
		<tr>
			<th colspan="8" class="c">'.$pagei->pagenumbers().'</th>
		</tr>
	</tbody>
</table>';

$_base->page->load();