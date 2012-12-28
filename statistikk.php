<?php

define("ALLOW_GUEST", true);

require "base.php";
global $_game, $_base;

$now = time();

// antall minutter man skal vise pålogget
$online_min = 15;

$u = login::$logged_in ? "%u".login::$user->player->data['up_name']."%u" : "Anonym";

// vis nostatusers
$show_nsu = isset($_GET['show_nsu']);
if ($show_nsu) $_base->page->add_message("Du viser nå også folk som ikke vanligvis vises på statistikk (NoStatUser)!");

$next = isset($_SESSION[$GLOBALS['__server']['session_prefix'].'statistikk_check']) ? $_SESSION[$GLOBALS['__server']['session_prefix'].'statistikk_check'] : 0;
if (!empty($next) && $next > time() && !access::has("mod"))
{
	$wait = $next-time();
	if ($wait <= 5)
	{
		$_base->page->add_message('Du sjekket statistikk for under 5 sekunder siden og må derfor vente i '.game::counter($wait, true).'!', "error");
		
		putlog("LOG", "%bVIS STATISTIKK%b: $u (%bDelayed%b)");
		$_base->page->load();
	}
}
$_SESSION[$GLOBALS['__server']['session_prefix'].'statistikk_check'] = time()+5;

$_base->page->add_title("Statistikk");
putlog("LOG", "%bVIS STATISTIKK%b: $u".($show_nsu ? ' (Show NoStatUser)' : ''));


// hente server informasjon?
if (MAIN_SERVER)
{
	// minneinformasjon
	$mem = shell_exec("free -bo");
	$matches = false;
	if (preg_match_all("/(Mem|Swap):\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)(\\s+(\\d+)\\s+(\\d+)\\s+(\\d+))?/", $mem, $matches, PREG_SET_ORDER))
	{
		$mem = array(
			"mem" => array(
				"total" => $matches[0][2],
				"used" => $matches[0][3],
				"free" => $matches[0][4],
				"shared" => $matches[0][6],
				"buffers" => $matches[0][7],
				"cached" => $matches[0][8],
				"used_app" => $matches[0][3]-$matches[0][6]-$matches[0][7]-$matches[0][8]
			),
			"swap" => array(
				"total" => $matches[1][2],
				"used" => $matches[1][3],
				"free" => $matches[1][4]
			)
		);
	}
	else
	{
		$mem = false;
	}
	
	// uptime
	$uptime = 0;
	$fh = fopen("/proc/uptime", "r");
	if ($fh)
	{
		$line = fgets($fh);
		$elms = explode(" ", $line);
		$uptime = intval($elms[0]);
	}
}


// hent mysql status
$result = $_base->db->query("SHOW GLOBAL STATUS");
$vars = array();
while ($row = mysql_fetch_row($result))
{
	$vars[$row[0]] = $row[1];
}

echo '
<h1>Statistikk</h1>';


if (MAIN_SERVER && $mem)
{
	function format_size($bytes)
	{
		// GB
		if ($bytes >= 1073741824)
		{
			return game::format_number(round($bytes/1073741824, 3), 3) . " GB";
		}
		
		// MB
		if ($bytes >= 1048576)
		{
			return game::format_number(round($bytes/1048576, 2), 2) . " MB";
		}
		
		// KB
		if ($bytes >= 1024)
		{
			return game::format_number(round($bytes/1024, 2), 2) . " KB";
		}
		
		// bytes
		return $bytes . " bytes";
	}
	
	echo '
<table class="table tablemb" style="float: right; margin: 0 5px 5px 5px">
	<thead>
		<tr>
			<th colspan="2">Minneinformasjon for serveren</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th>I bruk</th>
			<td>'.format_size($mem['mem']['used_app']).' ('.game::format_number($mem['mem']['used_app']/$mem['mem']['total']*100, 1).' %)</td>
		</tr>
		<tr class="color">
			<th>I bruk<br />(med buffers og cache)</th>
			<td>'.format_size($mem['mem']['used']).' ('.game::format_number($mem['mem']['used']/$mem['mem']['total']*100, 1).' %)</td>
		</tr>
		<tr>
			<th>Swap</th>
			<td>'.format_size($mem['swap']['used']).' ('.game::format_number($mem['swap']['used']/$mem['swap']['total']*100, 1).' %)</td>
		</tr>
	</tbody>
</table>';
}

echo '
<p><b>Nøkkeltallene og rankinfo viser kun vanlige spillere. Alle spillere som har høyere status enn moderator er utelukket.</b></p>
<p>Det er nå '.game::timespan(time()-1167675960, game::TIME_FULL).' siden spillet ble åpnet for registrering.</p>';

if (MAIN_SERVER)
{
	echo '
<p>Serveren har vært oppe i '.game::timespan($uptime, game::TIME_FULL).'.</p>';
}

$date = $_base->date->get();
$date->setTime(0, 0, 0);
$idag = $date->format("U");
$date->modify("-1 day");
$igaar = $date->format("U");
$date->modify("-1 day");
$igaar2 = $date->format("U");

// hent nøkkeltall
$result = $_base->db->query("
SELECT 'players',     COUNT(up_id) FROM users_players WHERE up_access_level < {$_game['access_noplay']} AND up_access_level != 0
UNION ALL
SELECT 'cash',        SUM(up_cash) FROM users_players WHERE up_access_level != 0".(!$show_nsu ? " AND up_access_level < {$_game['access_noplay']}" : "")."
UNION ALL
SELECT 'bank',        SUM(up_bank) FROM users_players WHERE up_access_level != 0".(!$show_nsu ? " AND up_access_level < {$_game['access_noplay']}" : "")."
UNION ALL
SELECT 'online',      COUNT(up_id) FROM users_players WHERE up_last_online >= ".(time()-$online_min*60)."
UNION ALL
SELECT 'online_5',    COUNT(up_id) FROM users_players WHERE up_last_online >= ".(time()-300)."
UNION ALL
SELECT 'online_1',    COUNT(up_id) FROM users_players WHERE up_last_online >= ".(time()-60)."
UNION ALL
SELECT 'online_30',   COUNT(up_id) FROM users_players WHERE up_last_online >= ".(time()-1800)."
UNION ALL
SELECT 'ant_mld',     SUM(up_inbox_num_messages) FROM users_players
UNION ALL
SELECT 'ant_forum_replies', SUM(up_forum_num_replies) FROM users_players
UNION ALL
SELECT 'ant_forum_topics', SUM(up_forum_num_topics) FROM users_players
UNION ALL
SELECT 'hits',        SUM(up_hits) FROM users_players
UNION ALL
SELECT 'hits_today',  SUM(uhi_hits) FROM users_hits WHERE uhi_secs_hour >= $idag
UNION ALL
SELECT 'hits_yesterday', SUM(uhi_hits) FROM users_hits WHERE uhi_secs_hour < $idag AND uhi_secs_hour >= $igaar
UNION ALL
SELECT 'hits_yesterday2', SUM(uhi_hits) FROM users_hits WHERE uhi_secs_hour < $igaar AND uhi_secs_hour >= $igaar2
UNION ALL
SELECT 'online_24t',   COUNT(up_id) FROM users_players WHERE up_last_online >= ".(time()-86400)."
UNION ALL
SELECT 'online_today', COUNT(up_id) FROM users_players WHERE up_last_online >= $idag
UNION ALL
SELECT 'reg_today',   COUNT(up_id) FROM users_players WHERE up_created_time >= $idag
UNION ALL
SELECT 'reg_yesterday', COUNT(up_id) FROM users_players WHERE up_created_time >= $igaar AND up_created_time < $idag
UNION ALL
SELECT 'reg_yesterday2', COUNT(up_id) FROM users_players WHERE up_created_time >= $igaar2 AND up_created_time < $igaar
UNION ALL
SELECT 'players_deactivated', COUNT(up_id) FROM users_players WHERE up_access_level = 0 AND up_deactivated_dead = 0
UNION ALL
SELECT 'players_dead', COUNT(up_id) FROM users_players WHERE up_access_level = 0 AND up_deactivated_dead != 0
UNION ALL
SELECT 'players_total', COUNT(up_id) FROM users_players
UNION ALL
SELECT 'spillelogger', MAX(id) FROM users_log
UNION ALL
SELECT 'ff_cash',  SUM(ff_bank) FROM ff WHERE ff_is_crew = 0 AND ff_inactive = 0
UNION ALL
SELECT 'users_count_active', COUNT(u_id) FROM users WHERE u_access_level != 0
UNION ALL
SELECT 'users_count_deactivated', COUNT(u_id) FROM users WHERE u_access_level = 0");

$stats = array();
while ($row = mysql_fetch_row($result))
{
	$stats[$row[0]] = $row[1];
}

$_base->page->add_css("#statistikk { clear: both; margin-top: 1em }");

echo '
<table width="100%" cellpadding="4" cellspacing="2" id="statistikk">';


echo '
	<tr valign="top">
		<td colspan="2">
			<!-- beste rankene siste 5 timene -->
			<table width="100%" class="table game tablemb">
				<thead>
					<tr>
						<th colspan="'.(access::has("mod") ? 7 : 5).'">Beste rankerne de 5 siste timene</th>
					</tr>
					<tr>
						<td>Klokkeslett</td>
						<td>Plass</td>
						<td>Spiller</td>
						<td>Rank</td>
						<td>Sist pålogget</td>'.(access::has("mod") ? '
						<td>Rankpoeng</td>
						<td>Hits</td>' : '').'
					</tr>
				</thead>
				<tbody>';


ess::$b->db->query("
	SET
		@num := 0,
		@hour := '',
		@pos := 0,
		@time = IF((@time := (SELECT DISTINCT uhi_secs_hour FROM users_hits ORDER BY uhi_secs_hour DESC LIMIT 6,1)) IS NULL, 0, @time)");
$result = ess::$b->db->query("
	SELECT count_time, pos, uhi_secs_hour, uhi_points, uhi_hits, uhi_up_id, up_name, up_points AS points_total, up_access_level, up_last_online, upr_rank_pos
	FROM (
			SELECT uhi_secs_hour, uhi_points, uhi_hits, uhi_up_id,
				@pos := IF(@hour = uhi_secs_hour, @pos, @pos + 1) AS count_time,
				@num := IF(@hour = uhi_secs_hour, @num + 1, 1) AS pos,
				@hour := uhi_secs_hour AS dummy
			FROM users_hits
			WHERE uhi_secs_hour > @time AND uhi_points > 0
			ORDER BY uhi_secs_hour DESC, uhi_points DESC
		) ref
		JOIN users_players ON up_id = uhi_up_id
		LEFT JOIN users_players_rank ON upr_up_id = up_id
	WHERE count_time > 1 AND count_time <= 6 AND pos <= 5");

$hours = array();
while ($row = mysql_fetch_assoc($result))
{
	$hours[$row['uhi_secs_hour']][] = $row;
}

$_base->page->add_css("td.stats_5hits { font-weight: bold; color: #AAA }");

$e = 0;
foreach ($hours as $hour => $rows)
{
	echo '
					<tr'.(is_int($e/2) ? ' class="color"' : '').'>
						<td rowspan="'.count($rows).'" class="c stats_5hits">'.$_base->date->get($hour)->format("H:i:s").' - '.$_base->date->get($hour+3600)->format("H:i:s").'</td>';
	
	$new = false;
	$i = 0;
	foreach ($rows as $row)
	{
		$i++;
		$rank = game::rank_info($row['points_total'], $row['upr_rank_pos'], $row['up_access_level']);
		if ($new)
			echo '
					</tr>
					<tr'.(is_int($e/2) ? ' class="color"' : '').'>';
		
		echo '
						<td class="r">#'.$i.'</td>
						<td>'.game::profile_link($row['uhi_up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td>'.$rank['name'].'</td>
						<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>'.(access::has("mod") ? '
						<td class="r">'.game::format_number($row['uhi_points']).'</td>
						<td class="r">'.game::format_number($row['uhi_hits']).'</td>' : '');
		
		$new = true;
	}
	
	echo '
					</tr>';
	$e++;
}

echo '
				</tbody>
			</table>
		</td>
	</tr>
	<tr valign="top">
		<td width="50%">
			
			<!-- nøkkeltall -->
			<table width="100%" class="table tablemb">
				<thead>
					<tr>
						<th colspan="2">Nøkkeltall</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Antall aktiverte brukere</td>
						<td align="right"><b>'.game::format_number($stats['users_count_active']).'</b></td>
					</tr>
					<tr class="color">
						<td>Antall deaktiverte brukere</td>
						<td class="r">'.game::format_number($stats['users_count_deactivated']).'</td>
					</tr>
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr>
						<td>Antall <b>aktiverte</b> spillere</td>
						<td align="right" style="color: #F9E600"><b>'.game::format_number($stats['players']).'</b></td>
					</tr>
					<tr class="color">
						<td>Antall <b>deaktiverte</b> spillere</td>
						<td class="r" style="color: #FF0000">'.game::format_number($stats['players_deactivated']).'</td>
					</tr>
					<tr>
						<td>Antall <b>drepte</b> spillere</td>
						<td class="r" style="color: #FF0000">'.game::format_number($stats['players_dead']).'</td>
					</tr>
					<tr class="color">
						<td>Antall registrerte spillere</td>
						<td class="r" style="color: #AAAAAA">'.game::format_number($stats['players_total']).'</td>
					</tr>
					
					
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr>
						<td><a href="online_list?t=24t">Spillere pålogget siste 24 timene</a></td>
						<td align="right">'.game::format_number($stats['online_24t']).'</td>
					</tr>
					<tr class="color">
						<td><a href="online_list?t=today">Spillere pålogget etter midnatt</a></td>
						<td align="right">'.game::format_number($stats['online_today']).'</td>
					</tr>
					<tr>
						<td><a href="online_list?t=1800">Spillere pålogget siste 30 min</a></td>
						<td align="right">'.game::format_number($stats['online_30']).'</td>
					</tr>
					<tr class="color">
						<td><a href="online_list?t='.($online_min*60).'">Spillere pålogget siste '.$online_min.' min</a></td>
						<td align="right">'.game::format_number($stats['online']).'</td>
					</tr>
					<tr>
						<td><a href="online_list?t=300">Spillere pålogget siste 5 min</a></td>
						<td align="right">'.game::format_number($stats['online_5']).'</td>
					</tr>
					<tr class="color">
						<td><a href="online_list?t=60">Spillere pålogget siste minuttet</a></td>
						<td align="right">'.game::format_number($stats['online_1']).'</td>
					</tr>
					
					
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr>
						<td>Penger i omløp</td>
						<td align="right">'.game::format_cash($stats['cash']).'</td>
					</tr>
					<tr class="color">
						<td>Penger i bankene</td>
						<td align="right">'.game::format_cash($stats['bank']).'</td>
					</tr>
					<tr>
						<td>Penger i firma/broderskap</td>
						<td align="right">'.game::format_cash($stats['ff_cash']).'</td>
					</tr>
					
					
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr class="color">
						<td>Antall registrerte i dag</td>
						<td align="right">'.game::format_number($stats['reg_today']).'</td>
					</tr>
					<tr>
						<td>Antall registrerte i går</td>
						<td align="right">'.game::format_number($stats['reg_yesterday']).'</td>
					</tr>
					<tr class="color">
						<td>Antall registrerte i forigårs</td>
						<td align="right">'.game::format_number($stats['reg_yesterday2']).'</td>
					</tr>
					<tr>
						<td>Antall registrerte gjennomsnitt</td>
						<td align="right">'.game::format_number($stats['players']/((time()-1167675960)/86400)).'</td>
					</tr>
					
					
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr class="color">
						<td>Antall private meldinger sendt</td>
						<td align="right">'.game::format_number($stats['ant_mld']).'</td>
					</tr>
					<tr>
						<td>Antall forumemner</td>
						<td align="right">'.game::format_number($stats['ant_forum_topics']).'</td>
					</tr>
					<tr class="color">
						<td>Antall forumsvar</td>
						<td align="right">'.game::format_number($stats['ant_forum_replies']).'</td>
					</tr>
					<tr>
						<td>Antall spillelogger totalt</td>
						<td align="right">'.game::format_number($stats['spillelogger']).'</td>
					</tr>
					
					
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr class="color">
						<td>Antall sidevisninger</td>
						<td align="right">'.game::format_number($stats['hits']).'</td>
					</tr>
					<tr>
						<td>Antall sidevisninger i forigårs</td>
						<td align="right">'.game::format_number($stats['hits_yesterday2']).'</td>
					</tr>
					<tr class="color">
						<td>Antall sidevisninger i går</td>
						<td align="right">'.game::format_number($stats['hits_yesterday']).'</td>
					</tr>
					<tr>
						<td>Antall sidevisninger i dag</td>
						<td align="right">'.game::format_number($stats['hits_today']).'</td>
					</tr>
					
					
					<tr class="spacer"><td colspan="2">&nbsp;</td></tr>
					<tr class="color">
						<td>Oppetid for databasen</td>
						<td align="right">'.game::timespan($vars['Uptime'], game::TIME_SHORT).'</td>
					</tr>
					<tr>
						<td>Oppetid (etter statusflush)</td>
						<td class="r">'.game::timespan($vars['Uptime_since_flush_status'], game::TIME_SHORT).'</td>
					</tr>
					<tr class="color">
						<td>Databasespørringer per sekund</td>
						<td align="right">'.game::format_number($vars['Questions']/$vars['Uptime_since_flush_status'], 4).'</td>
					</tr>
					<tr>
						<td>Antall databasespørringer</td>
						<td align="right">'.game::format_number($vars['Questions']).'</td>
					</tr>
				</tbody>
			</table>';




// drap og angrepstats

function get_df_stats($col, &$ff_id_list)
{
	$result = ess::$b->db->query("
		SELECT up_id, $col
		FROM users_players
		WHERE up_access_level != 0 AND up_access_level < ".ess::$g['access_noplay']." AND $col > 0
		ORDER BY $col DESC
		LIMIT 5");
	
	$rows = array();
	while ($row = mysql_fetch_assoc($result))
	{
		$rows[] = $row;
		$ff_id_list[] = $row['up_id'];
	}
	
	return $rows;
}

$ff_id_list = array();
$stats_kills = get_df_stats("up_attack_killed_num", $ff_id_list);
$stats_dam = get_df_stats("up_attack_damaged_num", $ff_id_list);

// hent alle FF hvor spilleren var medlem
$up_ff = array();
if (count($ff_id_list) > 0)
{
	essentials::load_module("ff");
	
	$up_list = implode(",", array_unique($ff_id_list));
	$result_ff = ess::$b->db->query("
		SELECT ffm_up_id, ffm_priority, ff_id, IFNULL(ffm_ff_name, ff_name) ffm_ff_name, ff_type
		FROM
			ff_members
			JOIN ff ON ff_id = ffm_ff_id AND ff_inactive = 0
		WHERE ffm_up_id IN ($up_list) AND ffm_status = ".ff_member::STATUS_MEMBER." AND ff_is_crew = 0 AND ff_type = 1
		ORDER BY ffm_ff_name");
	
	while ($row = mysql_fetch_assoc($result_ff))
	{
		$pos = ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']];
		$text = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ffm_ff_name']).'</a>';
		
		$up_ff[$row['ffm_up_id']][] = $text;
	}
}

echo '
			<!-- drap og angrepstats -->
			<table class="table tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="4">Flest drap</th>
					</tr>
					<tr>
						<td>#</td>
						<td>Spiller</td>
						<td>Antall</td>
						<td>Broderskap</td>
					</tr>
				</thead>
				<tbody>';

$i = 0;
foreach ($stats_kills as $row)
{
	$familier = isset($up_ff[$row['up_id']]) ? implode(",<br />", $up_ff[$row['up_id']]) : '&nbsp;';
	
	echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
						<td class="c">'.$i.'.</td>
						<td><user id="'.$row['up_id'].'" /></td>
						<td class="r">'.game::format_num($row['up_attack_killed_num']).'</td>
						<td>'.$familier.'</td>
					</tr>';
}

echo '
				</tbody>
			</table>
			<table class="table tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="4">Flest angrep</th>
					</tr>
					<tr>
						<td>#</td>
						<td>Spiller</td>
						<td>Antall</td>
						<td>Broderskap</td>
					</tr>
				</thead>
				<tbody>';

$i = 0;
foreach ($stats_dam as $row)
{
	$familier = isset($up_ff[$row['up_id']]) ? implode(",<br />", $up_ff[$row['up_id']]) : '&nbsp;';
	
	echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
						<td class="c">'.$i.'.</td>
						<td><user id="'.$row['up_id'].'" /></td>
						<td class="r">'.game::format_num($row['up_attack_damaged_num']).'</td>
						<td>'.$familier.'</td>
					</tr>';
}

echo '
				</tbody>
			</table>';



echo '	
			<!-- pengestatusene -->
			<table class="table game tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="4">Pengestatusene</th>
					</tr>
					<tr>
						<td>Nummer</td>
						<td>Navn (<a href="'.ess::$s['relative_path'].'/node/27" style="font-size: 10px">Se hjelp</a>)</td>
						<td colspan="2">Antall</td>
					</tr>
				</thead>
				<tbody>';

$ranks = $_game['cash'];
$req = array();

while (($cash_min = current($ranks)) !== false)
{
	$name = key($ranks);
	$next = next($ranks);
	$cash_max = $next !== false ? " AND up_cash+up_bank < ".(/*$cash_min + */$next) : "";
	$req[] = "SELECT COUNT(up_id) FROM users_players WHERE up_cash+up_bank >= $cash_min$cash_max AND up_access_level != 0".(!$show_nsu ? " AND up_access_level < {$_game['access_noplay']}" : "");
}

$req = array_reverse($req);
$ranks = array_reverse($ranks);

$req = implode(" UNION ALL ", $req);
$result = $_base->db->query($req);

$i = 0;
$ant = count($ranks);
foreach ($ranks as $name => $cash_min)
{
	$e = $ant - $i;
	$num = mysql_result($result, $i);
	$i++;
	$percent = $num == 0 ? ' colspan="2">' : ' style="color: #888">'.round($num/$stats['players']*100, 1).' %</td>
						<td align="right">';
	
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.$e.'</td>
						<td>'.$name.'</td>
						<td align="right"'.$percent.'<b>'.game::format_number($num).'</b></td>
					</tr>';
}


echo '
				</tbody>
			</table>';



echo '			
			<!-- rankene -->
			<table class="table game tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="4">Rankene</th>
					</tr>
					<tr>
						<td>Nummer</td>
						<td>Navn</td>
						<td colspan="2">Antall</td>
					</tr>
				</thead>
				<tbody>';

// henter rankene i en spørring (union join)
$ranks = array_reverse(game::$ranks['items']);
$req = array();

$i = 0;
foreach ($ranks as $rank)
{
	$points_min = $rank['points'];
	$points_max = $rank['need_points'] > 0 ? " AND up_points < ".($rank['need_points']+$rank['points']) : "";
	
	$req[] = "SELECT COUNT(up_id) FROM users_players WHERE up_points >= $points_min$points_max AND up_access_level != 0 AND up_access_level < {$_game['access_noplay']}";
}

$req = implode(" UNION ALL ", $req);
$result = $_base->db->query($req);

$i = 0;
foreach ($ranks as $rank)
{
	$num = mysql_result($result, $i);
	$i++;
	$percent = $num == 0 ? ' colspan="2">' : ' style="color: #888">'.round($num/$stats['players']*100, 1).' %</td>
						<td align="right">';
	
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.$rank['number'].'</td>
						<td><a href="finn_spiller?r'.$rank['id'].'&amp;finn">'.$rank['name'].'</a></td>
						<td align="right"'.$percent.'<b>'.game::format_number($num).'</b></td>
					</tr>';
}


echo '
				</tbody>
			</table>';







// hent 10 siste registrasjoner
$result = $_base->db->query("SELECT up_id, up_name, up_created_time, u_created_ip, up_access_level FROM users_players JOIN users ON up_u_id = u_id ORDER BY up_created_time DESC LIMIT 10");
echo '
			<!-- 10 siste reg -->
			<table width="100%" class="table tablemb">
				<thead>
					<tr>
						<th colspan="'.(access::has("mod") ? 3 : 2).'">10 siste registrerte spillere</th>
					</tr>
					<tr>
						<td>Spiller</td>'.(access::has("mod") ? '
						<td>Reg IP</td>' : '').'
						<td>Tid siden</td>
					</tr>
				</thead>
				<tbody>';

$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<!--<td align="right">'.$_base->date->get($row['up_created_time'])->format("d.m.Y H:i:s").'</td>-->'.(access::has("mod") ? '
						<td><a href="admin/brukere/finn?ip='.urlencode($row['u_created_ip']).'" target="_blank">'.htmlspecialchars($row['u_created_ip']).'</a></td>' : '').'
						<td align="right">'.game::timespan($row['up_created_time'], game::TIME_ABS).'</td>
					</tr>';
}

echo '
				</tbody>
			</table>
			
			<!-- top 10 foruminnlegg -->
			<table class="table tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="2">Spillere med flest foruminnlegg</th>
					</tr>
					<tr>
						<td>Spiller</td>
						<td>Antall</td>
					</tr>
				</thead>
				<tbody>';

// hent topp 10 i foruminnlegg
$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_forum_num_replies FROM users_players WHERE up_access_level != 0 ORDER BY up_forum_num_replies DESC LIMIT 10");
$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td class="r">'.game::format_number($row['up_forum_num_replies']).'</td>
					</tr>';
}

echo '
				</tbody>
			</table>';

// topp 15 rikeste (for moderatorer)
if (access::has("mod"))
{
	echo '
			<!-- top 10 private meldinger-->
			<table class="table tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="2">Spillere med flest private meldinger (mod)</th>
					</tr>
					<tr>
						<td>Spiller</td>
						<td>Antall</td>
					</tr>
				</thead>
				<tbody>';

// hent topp 10 i private meldinger
$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_inbox_num_messages FROM users_players WHERE up_access_level != 0 ORDER BY up_inbox_num_messages DESC LIMIT 10");
$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td class="r">'.game::format_number($row['up_inbox_num_messages']).'</td>
					</tr>';
}

echo '
				</tbody>
			</table>
			
			<!-- top 15 rikeste -->
			<table class="table tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="3">Top 15 rikeste spillere (mod)</th>
					</tr>
					<tr>
						<td width="30">&nbsp;</td>
						<td>Spiller</td>
						<td>Totalt</td>
					</tr>
				</thead>
				<tbody>';

// hent topp 15 i rikeste spillere
$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_bank+up_cash AS amount FROM users_players WHERE up_access_level != 0".(!$show_nsu ? " AND up_access_level < {$_game['access_noplay']}" : "")." ORDER BY up_bank+up_cash DESC LIMIT 15");
$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td class="r">'.$i.'</td>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td align="right">'.game::format_cash($row['amount']).'</td>
					</tr>';
}

echo '
				</tbody>
			</table>';
}

echo '
			<!-- top 15 rankede -->
			<table class="table tablemb" width="100%">
				<thead>
					<tr>
						<th colspan="'.(access::has("mod") ? '3' : '2').'">Top 15 rankede spillere</th>
					</tr>
					<tr>
						<td width="30">&nbsp;</td>
						<td>Spiller</td>'.(access::has("mod") ? '
						<td>Rankpoeng (mod+)</td>' : '').'
					</tr>
				</thead>
				<tbody>';

// hent topp 15 rankede spillere
$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_points FROM users_players WHERE up_access_level != 0".(!$show_nsu ? " AND up_access_level < {$_game['access_noplay']}" : "")." ORDER BY up_points DESC LIMIT 15");
$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td class="r">'.$i.'</td>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>'.(access::has("mod") ? '
						<td align="right">'.game::format_number($row['up_points']).'</td>' : '').'
					</tr>';
}

echo '
				</tbody>
			</table>
		</td>
		<td>';


// hent de 20 som har ranket mest i løpet av denne timen
$result = $_base->db->query("
	SELECT uhi_up_id, uhi_hits, uhi_points, up_points, up_access_level, upr_rank_pos
	FROM users_hits
		LEFT JOIN users_players ON up_id = uhi_up_id
		LEFT JOIN users_players_rank ON upr_up_id = up_id
	WHERE uhi_secs_hour = ".login::get_secs_hour()." AND uhi_points > 0 AND up_access_level != 0
	ORDER BY uhi_points DESC LIMIT 20");

echo '
			<!-- 20 som har ranket mest denne timen -->
			<table width="100%" class="table game tablemb">
				<thead>
					<tr>
						<th colspan="'.(access::has("mod") ? 5 : 3).'">20 beste rankere denne timen (etter '.$_base->date->get(login::get_secs_hour())->format("H:i").')</th>
					</tr>
					<tr>
						<td width="30">&nbsp;</td>
						<td>Spiller</td>
						<td>Rank</td>'.(access::has("mod") ? '
						<td>Poeng</td>
						<td>Hits</td>' : '').'
					</tr>
				</thead>
				<tbody>';

if (mysql_num_rows($result) == 0)
{
	echo '
					<tr>
						<td colspan="'.(access::has("mod") ? 5 : 3).'">Ingen spillere har ranket noe så langt denne timen.</td>
					</tr>';
}

else
{
	$i = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		$i++;
		$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
		echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>#'.$i.'</td>
						<td><user id="'.$row['uhi_up_id'].'" /></td>
						<td>'.$rank['name'].'</td>'.(access::has("mod") ? '
						<td>'.game::format_number($row['uhi_points']).'</td>
						<td>'.game::format_number($row['uhi_hits']).'</td>' : '').'
					</tr>';
	}
}

echo '
				</tbody>
			</table>';

// hent 10 høyest wanted spillere
$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_fengsel_time, up_wanted_level FROM users_players WHERE up_wanted_level > 0 ORDER BY up_wanted_level DESC LIMIT 10");
echo '
			<!-- mest wanted -->
			<table width="100%" class="table tablemb">
				<thead>
					<tr>
						<th colspan="2">10 mest wanted spillere</th>
					</tr>
					<tr>
						<td>Spiller</td>
						<td>Wanted nivå</td>
					</tr>
				</thead>
				<tbody>';

if (mysql_num_rows($result) == 0)
{
	echo '
					<tr>
						<td colspan="2">Ingen spillere er wanted.</td>
					</tr>';
}

$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).($row['up_fengsel_time'] > time() ? ' (i fengsel)' : '').'</td>
						<td class="r">'.game::format_number($row['up_wanted_level']/10, 1).' %</td>
					</tr>';
}

echo '
				</tbody>
			</table>';


// hent 100 siste aktive spillere
$result = $_base->db->query("
	SELECT up_id, up_name, up_points, up_access_level, up_last_online, upr_rank_pos
	FROM users_players
		LEFT JOIN users_players_rank ON upr_up_id = up_id
	ORDER BY up_last_online DESC
	LIMIT 100");
echo '
			<!-- 100 siste aktive -->
			<table width="100%" class="table s_o tablemb">
				<thead>
					<tr>
						<th colspan="3">100 siste aktive spillere</th>
					</tr>
					<tr>
						<td>Spiller</td>
						<td>Rank</td>
						<td>Aktiv</td>
					</tr>
				</thead>
				<tbody>';

$_base->page->add_css('.s_or { color: #BBBBBB } .s_o tbody td { font-size: 9px }');
$i = 0;
while ($row = mysql_fetch_assoc($result))
{
	$i++;
	$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
	echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td class="s_or">'./*($rank['number'] < 10 ? '&nbsp;' : '').$rank['number'].' - */'<a href="finn_spiller?r'.$rank['id'].'&amp;finn">'.$rank['name'].'</a></td>
						<td align="right">'.game::timespan(min($row['up_last_online'], $now), game::TIME_ABS | game::TIME_SHORT).'</td>
					</tr>';
}

echo '
				</tbody>
			</table>';

if (access::has("crewet"))
{
	// 10 siste eksterne linker
	$result = $_base->db->query("SELECT lr_up_id, lr_referer, lr_time FROM log_referers ORDER BY lr_time DESC LIMIT 10");
	
	echo '
			<!-- 10 siste eksterne linker -->
			<table width="100%" class="table tablemb">
				<thead>
					<tr>
						<th colspan="2">10 siste eksterne linker (for crewet)</th>
					</tr>
					<tr>
						<td>Tid</td>
						<td width="220">Adresse</td>
					</tr>
				</thead>
				<tbody>';
	
	$i = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		$i++;
		$player = $row['lr_up_id'] ? '<br /><user id="'.$row['lr_up_id'].'" />' : '';
		echo '
					<tr'.(is_int($i/2) ? ' class="color"' : '').'>
						<td>'.$_base->date->get($row['lr_time'])->format().$player.'</td>
						<td><a href="'.htmlspecialchars($row['lr_referer']).'" style="word-spacing: -3px">'.nl2br(htmlspecialchars(chunk_split($row['lr_referer'], 1, " "))).'</a></td>
					</tr>';
	}
	
	echo '
				</tbody>
			</table>';
}

echo '
		</td>
	</tr>
</table>';

$_base->page->load();