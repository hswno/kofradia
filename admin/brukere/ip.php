<?php

require "../../base.php";
global $_game, $_base, $_lang;

$_base->page->add_title("IP-adresser med flere brukere");
$_base->page->theme_file = "doc";
$_base->page->add_css('td.ip_spacer { background-color: transparent; font-size: 2px; overflow: hidden; height: 2px }');

$options = array(
	300,
	600,
	1800,
	3600,
	21600,
	86400,
	172800,
	604800,
	1209600,
	2419200
);
$time = isset($_GET['filter']) && $_GET['filter'] > 0 ? intval($_GET['filter']) : 172800;

echo '
<h1>IP</h1>
<p>IP-adresser med flere enn 1 bruker som har vært logget inn i løpet av siste '.game::timespan($time, game::TIME_FULL).'</p>
<form action="" method="get">
	<p>
		Filter:
		<select name="filter" onchange="this.form.submit()">';

foreach ($options as $option)
{
	echo '
			<option value="'.$option.'"'.($option == $time ? ' selected="selected"' : '').'>'.game::timespan($option, game::TIME_NOBOLD | game::TIME_FULL).'</option>';
}

echo '
		</select>
	</p>
</form>';

// hent brukerene
$result = $_base->db->query("SELECT up_id, up_name, up_access_level, u_online_ip, up_created_time, u_email, up_last_online, up_hits, up_points, u_birth, up_cash+up_bank AS money, ref.ip_count FROM users_players JOIN users ON up_u_id = u_id, (SELECT u_online_ip AS ref_u_online_ip, COUNT(u_online_ip) AS ip_count FROM users JOIN users_players ON up_u_id = u_id WHERE up_access_level != 0 AND up_last_online > ".(time()-$time)." GROUP BY u_online_ip HAVING COUNT(u_online_ip) > 1) AS ref WHERE u_online_ip = ref_u_online_ip AND up_access_level != 0 AND up_last_online > ".(time()-$time)." ORDER BY ip_count DESC, u_online_ip, up_name");

// sett opp listen
$list = array();
while ($row = mysql_fetch_assoc($result))
{
	$list[$row['u_online_ip']][] = $row;
}

// ingen ip-er?
if (count($list) == 0)
{
	echo '
<p class="info_box">Ingen IP-adresser med flere enn 1 bruker ble funnet for denne perioden.</p>';
}

else
{
	// vis ip-ene
	echo '
<table class="table nowrap" style="font-size: 11px">
	<thead>
		<tr>
			<th>IP-adresse</th>
			<th>Brukernavn</th>
			<th>E-post</th>
			<th>Registert</th>
			<th colspan="2">Sist pålogget</th>
			<th>Hits</th>
			<th>Rankpoeng</th>
			<th>Penger</th>
			<th>Fødselsdato</th>
		</tr>
	</thead>
	<tbody>';
	
	$i = 0;
	foreach ($list as $row)
	{
		// spacer
		echo '
		<tr>
			<td colspan="12" class="ip_spacer">&nbsp;</td>
		</tr>';
		
		$count = count($row);
		
		echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td rowspan="'.$count.'" valign="top"><a href="finn?ip='.urlencode($row[0]['u_online_ip']).'">'.htmlspecialchars($row[0]['u_online_ip']).'</a><br />('.$count.' '.fword("bruker", "brukere", $count).')</td>';
		
		$e = 0;
		foreach ($row as $player)
		{
			// ny rad?
			if (++$e > 1)
			{
				echo '
		</tr>
		<tr'.($i % 2 == 0 ? ' class="color"' : '').'>';
			}
			
			$birth = explode("-", $player['u_birth']);
			
			echo '
			<td>'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</td>
			<td><a href="finn?email='.urlencode($player['u_email']).'">'.htmlspecialchars($player['u_email']).'</a></td>
			<td>'.$_base->date->get($player['up_created_time'])->format(date::FORMAT_SEC).'</td>
			<td>'.$_base->date->get($player['up_last_online'])->format(date::FORMAT_SEC).'</td>
			<td class="r">'.game::timespan($player['up_last_online'], game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD).'</td>
			<td class="r">'.game::format_number($player['up_hits']).'</td>
			<td class="r">'.game::format_number($player['up_points']).'</td>
			<td class="r">'.game::format_cash($player['money']).'</td>
			<td class="r">'.(empty($player['u_birth']) || $player['u_birth'] == "0000-00-00" ? 'Ikke registrert' : intval($birth[2]).". ".$_lang['months'][intval($birth[1])]." ".$birth[0]).'</td>';
		}
		
		echo '
		</tr>';
	}
	
	echo '
	</tbody>
</table>';
}

$_base->page->load();