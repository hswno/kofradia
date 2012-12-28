<?php

define("ALLOW_GUEST", true);

require "base.php";
global $_base, $_game;

$_base->page->add_title("Finn spiller");

$more = false;

// hvor man skal kunne søke
$search_status = array(
	1 => array("Alle", ""),
	array("Levende", " AND up_access_level != 0"),
	array("Død/deaktivert", " AND up_access_level = 0")
);
$id = requestval("status");
$search_status_id = isset($search_status[$id]) ? $id : 1;
if ($search_status_id != 1) $more = true;


// siste pålogget
$search_online_sort = array(
	1 => array("mindre enn", ">"),
	array("mer enn", "<")
);
$id = requestval("online_sort");
$search_online_sort_id = isset($search_online_sort[$id]) ? $id : 1;
$type = $search_online_sort[$search_online_sort_id][1];

$time = time();
$search_online_time = array(
	1 => array("5 minutter", "up_last_online $type ".($time-300)),
	array("10 minutter", "up_last_online $type ".($time-600)),
	array("15 minutter", "up_last_online $type ".($time-900)),
	array("20 minutter", "up_last_online $type ".($time-1200)),
	array("30 minutter", "up_last_online $type ".($time-1800)),
	array("1 time", "up_last_online $type ".($time-3600)),
	array("2 timer", "up_last_online $type ".($time-7200)),
	array("3 timer", "up_last_online $type ".($time-10800)),
	array("6 timer", "up_last_online $type ".($time-21600)),
	array("12 timer", "up_last_online $type ".($time-43200)),
	array("1 dag", "up_last_online $type ".($time-86400)),
	array("2 dager", "up_last_online $type ".($time-172800)),
	array("3 dager", "up_last_online $type ".($time-259200)),
	array("4 dager", "up_last_online $type ".($time-345600)),
	array("5 dager", "up_last_online $type ".($time-432000)),
	array("6 dager", "up_last_online $type ".($time-518400)),
	array("1 uke", "up_last_online $type ".($time-604800)),
	array("2 uker", "up_last_online $type ".($time-1209600)),
	array("3 uker", "up_last_online $type ".($time-1814400)),
	array("4 uker", "up_last_online $type ".($time-2419200)),
	array("8 uker", "up_last_online $type ".($time-4838400))
);
$id = requestval("online_time");
$search_online_time_id = isset($search_online_time[$id]) ? $id : 1;

$search_online = array(
	1 => array("Ja", " AND {$search_online_time[$search_online_time_id][1]}"),
	array("Nei", "")
);
$search_online_id = requestval("online") ? 1 : 2;
if ($search_online_id != 2) $more = true;


// rankene
$search_ranks = array();
foreach (game::$ranks['items'] as $key => $info)
{
	$search_ranks[$key] = array($info['name'], "(up_points >= {$info['points']}".($info['need_points'] > 0 ? " AND up_points < ".($info['points']+$info['need_points']) : '').")");
}
$search_ranks_active = array();
$search_ranks_query = array();

foreach ($_REQUEST as $key => $dummy)
{
	$match = false;
	if (preg_match("/^r(\\d+)$/D", $key, $match))
	{
		if (isset($search_ranks[$match[1]]))
		{
			$search_ranks_active[] = $match[1];
			$search_ranks_query[] = $search_ranks[$match[1]][1];
		}
	}
}

if (count($search_ranks_query) > 0 && count($search_ranks_query) != count($search_ranks))
{
	$search_ranks_query = " AND (".implode(" OR ", $search_ranks_query).")";
	$more = true;
}
else
{
	if (count($search_ranks_active) == 0)
	{
		$search_ranks_active = array_keys($search_ranks);
	}
	$search_ranks_query = "";
}


// sortering
$sort = new sorts("sort");
$sort->append("asc", "Spiller", "up_name, up_last_online DESC");
$sort->append("desc", "Spiller", "up_name DESC, up_last_online");
$sort->append("asc", "Lengde på spiller", "LENGTH(up_name), up_name, up_last_online DESC");
$sort->append("desc", "Lengde på spiller", "LENGTH(up_name) DESC, up_name DESC, up_last_online");
$sort->append("asc", "Sist pålogget", "up_last_online");
$sort->append("desc", "Sist pålogget", "up_last_online DESC");
$sort->append("asc", "Plassering", "up_points DESC");
$sort->append("desc", "Plassering", "up_points");
$sort->set_active(requestval("sort"), 2);

$_base->page->add_js('
function expandForm()
{
	$("more_false").style.display = "none";
	$("more_true1").style.display = "inline";
	$("more_true2").style.display = "block";
	
	return false;
}
function shrinkForm()
{
	$("more_false").style.display = "inline";
	$("more_true1").style.display = "none";
	$("more_true2").style.display = "none";
	
	return false;
}');

// søkeform
echo '
<h1>Finn spiller</h1>
<form action="'.PHP_SELF.'" method="get">
	<div class="section" style="width: 460px; margin-left: auto; margin-right: auto">
		<h2>Søk</h2>
		<p class="h_right">
			<a href="#" id="more_false"'.($more ? ' style="display: none"' : '').' onclick="return expandForm()">Avansert skjema</a>
			<a href="#" id="more_true1"'.($more ? '' : ' style="display: none"').' onclick="return shrinkForm()">Enkelt skjema</a>
		</p>
		<dl class="dl_20 dl_2x">
			<dt>Spiller</dt>
			<dd><input type="text" name="finn" id="finn" value="'.htmlspecialchars(requestval("finn")).'" class="styled w150" /></dd>
			
			<dt>Profiltekst</dt>
			<dd><input type="text" name="profiletext" value="'.htmlspecialchars(requestval("profiletext")).'" class="styled w200" style="width: 340px" /></dd>
		</dl>
		<script type="text/javascript">
		$("finn").focus();
		</script>
		<div id="more_true2"'.($more ? '' : ' style="display: none"').'>
			<dl class="dl_20 dl_2x">
				<dt>Med en av rankene</dt>
				<dd>';

foreach ($search_ranks as $key => $item)
{
	echo '
					<input type="checkbox" id="rank_'.$key.'" name="r'.$key.'" value="1"'.(in_array($key, $search_ranks_active) ? ' checked="checked"' : '').' /><label for="rank_'.$key.'"> '.htmlspecialchars($item[0]).'</label><br />';
}

echo '
				</dd>
				
				<dt>Status</dt>
				<dd>';

foreach ($search_status as $key => $item)
{
	echo '
					<input type="radio" id="status_'.$key.'" name="status" value="'.$key.'"'.($search_status_id == $key ? ' checked="checked"' : '').' /><label for="status_'.$key.'"> '.htmlspecialchars($item[0]).'</label>';
}

echo '
				</dd>
				
				<dt>Sist pålogget</dt>
				<dd>
					<input type="checkbox" name="online" id="online" value="1"'.($search_online_id == 1 ? ' checked="checked"' : '').' />
					<label for="online">For</label>
					<select name="online_sort" onchange="$(\'online\').checked=true">';

foreach ($search_online_sort as $key => $item)
{
	echo '
						<option value="'.$key.'"'.($search_online_sort_id == $key ? ' selected="selected"' : '').'>'.htmlspecialchars($item[0]).'</option>';
}

echo '
					</select>
					<select name="online_time" onchange="$(\'online\').checked=true">';

foreach ($search_online_time as $key => $item)
{
	echo '
						<option value="'.$key.'"'.($search_online_time_id == $key ? ' selected="selected"' : '').'>'.htmlspecialchars($item[0]).'</option>';
}

echo '
					</select>
				</dd>
			</dl>
		</div>
		<h4>'.show_sbutton("Utfør søk").'</h4>
	</div>
</form>';


// søke?
if (isset($_REQUEST['finn']))
{
	$user_search = requestval("finn");
	$text_search = requestval("profiletext");
	
	// finn ut delene av spørringen
	$user_parts = search_query($user_search, false);
	$text_parts = search_query($text_search);
	
	// sett opp søkespørringen
	$search = "";
	if (count($user_parts[0]) > 0)
	{
		$search .= " AND up_name".implode(" AND up_name", $user_parts[0]);
	}
	if (count($text_parts[0]) > 0)
	{
		$search .= " AND up_profile_text".implode(" AND up_profile_text", $text_parts[0]);
	}
	
	// sortering
	$sort_info = $sort->active();
	
	$query = "up_id, up_name, up_last_online, up_access_level, up_points, upr_rank_pos FROM users_players LEFT JOIN users_players_rank ON upr_up_id = up_id WHERE 1{$search_online[$search_online_id][1]}{$search_status[$search_status_id][1]}$search$search_ranks_query ORDER BY {$sort_info['params']}";
	
	$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
	$result = $pagei->query($query);
	
	$info = array();
	foreach ($user_parts[1] as $part)
	{
		$info[] = '<b>Spiller:</b> '.htmlspecialchars($part);
	}
	foreach ($text_parts[1] as $part)
	{
		$info[] = '<b>Profiltekst:</b> '.htmlspecialchars($part);
	}
	if (count($search_ranks_active) > 0 && count($search_ranks_active) != count($search_ranks))
	{
		if (count($search_ranks_active) == 1)
		{
			$info[] = '<b>Rank:</b> '.htmlspecialchars($search_ranks[$search_ranks_active[0]][0]);
		}
		else
		{
			$l = array();
			foreach ($search_ranks_active as $key)
			{
				$l[] = htmlspecialchars($search_ranks[$key][0]);
			}
			$last = array_pop($l);
			$info[] = '<b>Rank:</b> '.implode(", ", $l).' eller '.$last;
		}
	}
	if ($search_status_id != 1)
	{
		$info[] = '<b>Status:</b> '.htmlspecialchars($search_status[$search_status_id][0]);
	}
	if ($search_online_id == 1)
	{
		$info[] = '<b>Pålogget:</b> '.htmlspecialchars($search_online_sort[$search_online_sort_id][0]).' '.htmlspecialchars($search_online_time[$search_online_time_id][0]).' siden';
	}
	
	if (count($info) == 0)
	{
		$info = "<b>Ingenting</b> - viser alle brukere";
	}
	else
	{
		$info = implode(" ", $info);
	}
	
	echo '
<h2>Søkeresultater</h2>
<p class="h_right">Søker etter: '.$info.'</p>';
	
	// fant vi noe?
	if ($pagei->total == 0)
	{
		echo '
<p>
	Fant ingen treff.
</p>';
	}
	
	else
	{
		echo '
<p class="c">
	Antall treff: <b>'.$pagei->total.'</b>
</p>
<table class="table" width="100%" id="finnspiller_r'.($pagei->pages == 1 ? ' tablemb' : '').'">
	<thead>
		<tr>
			<th class="name">Spiller '.$sort->show_link(0, 1).' ('.$sort->show_link(2, 3).')</th>
			<th>Sist pålogget '.$sort->show_link(4, 5).'</th>
			<th>Rank</th>
			<th>Plassering '.$sort->show_link(6, 7).'</th>
		</tr>
	</thead>
	<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
			$rank_name = $rank['name'].($rank['orig'] ? ' ('.$rank['orig'].')' : '');
			
			echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
			<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>
			<td>'.$rank_name.'</td>
			<td class="r">'.game::format_number($row['upr_rank_pos']).'</td>
		</tr>';
		}
		
		echo '
	</tbody>
</table>';
		
		// flere sider?
		if ($pagei->pages > 1)
		{
			echo '
<div class="hr"></div>
<p class="c">
	'.$pagei->pagenumbers().'
</p>';
		}
	}
}

$_base->page->load();