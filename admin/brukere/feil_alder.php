<?php

require "../../base.php";
global $_game, $_base, $_lang;

$_base->page->add_title("Brukere med feil fødselsdato");
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



// hent fødselsdato for når man er 13 år
$find_date = strtotime("-13 year", time());
$legal_year = date('Y-m-d', $find_date);

// hent brukere som er over 25 år
$find_old_date = strtotime("-12 year", $find_date);
$older_year = date('Y-m-d', $find_old_date);

echo '<h1>Feil alder</h1>
<p>Brukere med mulig feil alder.</p>
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
// hent brukerne
$result = $_base->db->query("
	SELECT 
		up_id, up_name, up_access_level, u_online_ip, up_created_time, u_email, up_last_online, up_hits, up_points, u_birth, up_cash+up_bank AS money
	FROM users_players 
		JOIN users ON up_u_id = u_id 
	WHERE up_access_level != 0 AND up_last_online > ".(time()-$time)." AND STR_TO_DATE(".$legal_year.", '%Y-%m-%d') > u_birth
	ORDER BY u_birth DESC, u_online_ip, up_name LIMIT 5");

//u_birth < STR_TO_DATE(".$legal_year.", '%Y-%m-%d') OR u_birth >= STR_TO_DATE(".$older_year.", '%Y-%m-%d')

// sett opp listen
$list = array();
while ($row = mysql_fetch_assoc($result))
{
	$list[$row['u_birth']][] = $row;
}

// ingen "feile" fødselsdatoer?
if (count($list) == 0)
{
	echo '
<p class="info_box">Ingen brukere med feil fødselsdato.</p>';
}

else
{
	// vis listen med feil fødselsdato
	echo '
<table class="table nowrap" style="font-size: 11px">
	<thead>
		<tr>
			
			<th>Fødselsdato</th>
			<th>Brukernavn</th>
			<th>E-post</th>
			<th>Registert</th>
			<th colspan="2">Sist pålogget</th>
			<th>Hits</th>
			<th>Rankpoeng</th>
			<th>Penger</th>
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

		$birth_array = explode("-", $player['u_birth']);
		$birth = (empty($player['u_birth']) || $player['u_birth'] == "0000-00-00" ? 'Ikke registrert' : intval($birth_array[2]).". ".$_lang['months'][intval($birth_array[1])]." ".$birth_array[0]);
		
		
		$date = $_base->date->get();
		$n_day = $date->format("j");
		$n_month = $date->format("n");
		$n_year = $date->format("Y");

		$age = ($birth == 'Ikke registrert' ? 0 : $n_year - $birth_array[0] - (($n_month < $birth_array[1] || ($birth_array[1] == $n_month && $n_day < $birth_array[2])) ? 1 : 0));

		$birthdate = $birth + ($age == 0 ? '' : '(' .$age. ' år)' );

		echo '
		<tr'.((++$i % 2 == 0) ? ' class="color"' : '').'>
			<td rowspan="'.$count.'" valign="top">'.$birthdate.'<br />('.$count.' '.fword("bruker", "brukere", $count).')</td>';

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
			
			echo '
			<td>'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</td>
			<td><a href="finn?email='.urlencode($player['u_email']).'">'.htmlspecialchars($player['u_email']).'</a></td>
			<td>'.$_base->date->get($player['up_created_time'])->format(date::FORMAT_SEC).'</td>
			<td>'.$_base->date->get($player['up_last_online'])->format(date::FORMAT_SEC).'</td>
			<td class="r">'.game::timespan($player['up_last_online'], game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD).'</td>
			<td class="r">'.game::format_number($player['up_hits']).'</td>
			<td class="r">'.game::format_number($player['up_points']).'</td>
			<td class="r">'.game::format_cash($player['money']).'</td>';
			
		}
		
		echo '
		</tr>';
	}
	
	echo '
	</tbody>
</table>';
}

$_base->page->load();