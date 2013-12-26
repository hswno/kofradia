<?php

require "../../base.php";
error_reporting(E_ALL ^ E_NOTICE);
global $_base;

$_base->page->add_title("Brukere pålogget per dag");

$sort_dir = $_GET['sort_dir'] == "asc" ? "ASC" : "DESC";
$sort = $_GET['sort'] == "users" ? "users" : ($_GET['sort'] == "hits" ? "hits" : "date");

$result = $_base->db->query("
	SELECT
		DATE(FROM_UNIXTIME(uhi_secs_hour)) AS date,
		UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(uhi_secs_hour))) AS timestamp,
		COUNT(DISTINCT up_u_id) AS users,
		SUM(uhi_hits) AS hits
	FROM users_hits, users_players
	WHERE uhi_up_id = up_id
	GROUP BY DATE(FROM_UNIXTIME(uhi_secs_hour))
	ORDER BY $sort $sort_dir");

echo '
<h1>Antall brukere pålogget for hver dag</h1>
<p align="center">
	<a href="./">&laquo; Tilbake</a>
</p>
<form action="'.PHP_SELF.'" method="GET">
	<p align="center">
		<select name="sort">
			<option value="date"'.($sort == "date" ? ' selected="selected"' : '').'>Dato</option>
			<option value="users"'.($sort == "users" ? ' selected="selected"' : '').'>Brukere</option>
			<option value="hits"'.($sort == "hits" ? ' selected="selected"' : '').'>Hits</option>
		</select>
		<select name="sort_dir">
			<option value="desc"'.($sort_dir == "DESC" ? ' selected="selected"' : '').'>DESC: Størst/nyeste øverst</option>
			<option value="asc"'.($sort_dir == "ASC" ? ' selected="selected"' : '').'>ASC: Minst/eldste nederst</option>
		</select>
		<input type="submit" value="VIS" />
	</p>
</form>
<table class="table center tablemb">
	<thead>
		<tr>
			<th>&nbsp;</th>
			<th>Når</th>
			<th>Dato</th>
			<th>Antall brukere</th>
			<th>Antall hits</th>
		</tr>
	</thead>
	<tbody>';

$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	$time = floor((time()-$row['timestamp'])/86400);
	echo '
		<tr>
			<td align="right">#'.$i.'</td>
			<td align="right">'.($time == 1 ? '<b>I går</b>' : ($time > 0 ? '<b>'.$time.'</b> dager' : '<b>I dag</b>')).'</td>
			<td>'.$row['date'].'</td>
			<td align="right">'.game::format_number($row['users']).'</td>
			<td align="right">'.game::format_number($row['hits']).'</td>
		</tr>';
}

echo '
	</tbody>
</table>';

$_base->page->load();