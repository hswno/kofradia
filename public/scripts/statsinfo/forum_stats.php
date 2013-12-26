<?php

require "../../base.php";
global $_base;

// hent alle kategoriene og sett opp mal
$result = $_base->db->query("SELECT fse_id, fse_name FROM forum_sections ORDER BY fse_name");
$sections = array();
$stats_mal = array();
while ($row = mysql_fetch_assoc($result))
{
	$sections[$row['fse_id']] = $row['fse_name'];
	$stats_mal[$row['fse_id']]['topics'] = 0;
	$stats_mal[$row['fse_id']]['replies'] = 0;
}


// hent statistikken for antall emner (fordelt dagvis og kategorisk)
$result = $_base->db->query("SELECT DATE(FROM_UNIXTIME(ft_time)) AS date, ft_fse_id, COUNT(ft_id) AS count FROM forum_topics GROUP BY ft_fse_id, DATE(FROM_UNIXTIME(ft_time)) ORDER BY date DESC");

// del opp i dager og bruk malen
$stats = array();
while ($row = mysql_fetch_assoc($result))
{
	if (!isset($stats[$row['date']]))
	{
		$stats[$row['date']] = $stats_mal; 
	}
	
	// hoppe over (ugyldig/slettet kategori)
	if (!isset($stats[$row['date']][$row['ft_fse_id']])) continue;
	
	$stats[$row['date']][$row['ft_fse_id']]['topics'] = $row['count'];
}


// hent statistikken for antall svar (fordelt dagvis og kategorisk)
$result = $_base->db->query("SELECT DATE(FROM_UNIXTIME(fr_time)) AS date, ft_fse_id, COUNT(fr_id) AS count FROM forum_replies, forum_topics WHERE fr_ft_id = ft_id GROUP BY ft_fse_id, DATE(FROM_UNIXTIME(fr_time)) ORDER BY date DESC");

// del opp i dager og bruk malen
while ($row = mysql_fetch_assoc($result))
{
	if (!isset($stats[$row['date']]))
	{
		$stats[$row['date']] = $stats_mal; 
	}
	
	// hoppe over (ugyldig/slettet kategori)
	if (!isset($stats[$row['date']][$row['ft_fse_id']])) continue;
	
	$stats[$row['date']][$row['ft_fse_id']]['replies'] = $row['count'];
}

echo '
<h1>Antall emner i forumet</h1>';

// for totaloversikt
$total = array();

foreach ($stats as $day => $rows)
{
	echo '
<table class="table" width="300">
	<thead>
		<tr>
			<th>'.$day.'</th>
			<th>Emner</th>
			<th>Svar</th>
		</tr>
	</thead>
	<tbody>';
	
	$total_t = 0; $total_r = 0;
	foreach ($rows as $id => $type)
	{
		$total_t += $type['topics']; $total_r += $type['replies'];
		echo '
		<tr>
			<td>'.htmlspecialchars($sections[$id]).'</td>
			<td class="r">'.$type['topics'].'</td>
			<td class="r">'.$type['replies'].'</td>
		</tr>';
	}
	
	echo '
		<tr>
			<td>Totalt</td>
			<td class="r">'.$total_t.'</td>
			<td class="r">'.$total_r.'</td>
		</tr>
	</tbody>
</table>';
	
	$total[$day] = array($total_t, $total_r);
}

echo '
<h1>Totaloversikt</h1>
<table class="table">
	<thead>
		<tr>
			<th>Dato</th>
			<th>Emner</th>
			<th>Svar</th>
		</tr>
	</thead>
	<tbody>';

$total_t = 0; $total_r = 0;
foreach ($total as $day => $type)
{
	$total_t += $type[0]; $total_r += $type[1];
	echo '
		<tr>
			<td>'.htmlspecialchars($day).'</td>
			<td class="r">'.$type[0].'</td>
			<td class="r">'.$type[1].'</td>
		</tr>';
}

echo '
		<tr>
			<td>Totalt</td>
			<td class="r">'.$total_t.'</td>
			<td class="r">'.$total_r.'</td>
		</tr>
	</tbody>
</table>';

$_base->page->load();