<?php

require "../base.php";
global $_base;

access::no_guest();

// firma/familie?
if (isset($_GET['f']) || isset($_GET['fa']))
{
	#require "f/sok";
	die;
}

// hent forumene og sett opp forumene vi har tilgang til
$sections = \Kofradia\Forum\Category::get_forum_list();

$_base->page->add_title("Forum", "Søk");

$forum_mod = access::has("forum_mod");
$allow_deleted = $forum_mod;


// hvor man skal kunne søke
$search_where = array(
	1 => array("Alle innlegg", true, true),
	array("Kun hovedinnlegg", true, false),
	array("Kun svar", false, true)
);
$id = requestval("w");
$search_where_id = isset($search_where[$id]) ? $id : 1;


// hvilke forum søker vi i?
$search_forums = $sections;
$search_forums_active = array();

foreach ($_REQUEST as $key => $dummy)
{
	$match = false;
	if (preg_match("/^s(\\d+)$/Du", $key, $match))
	{
		if (isset($search_forums[$match[1]]))
		{
			$search_forums_active[] = $match[1];
		}
	}
}

if (count($search_forums_active) == 0)
{
	// søk i alle forumene (vi må ha et forum å søke i!)
	$search_forums_active = array_keys($search_forums);
}

$search_forums_query = " AND ft_fse_id IN (".implode(",", $search_forums_active).")";


// hvem som skal ha kunnet skrevet det man søker på
$search_from = array(
	1 => array("Alle", "", ""),
	array("Meg selv", " AND ft_up_id = ".login::$user->player->id, " AND fr.fr_up_id = ".login::$user->player->id),
	array("Andre", " AND ft_up_id != ".login::$user->player->id, " AND fr.fr_up_id != ".login::$user->player->id),
	array('Spesifiser', NULL, NULL, array())
);
$id = requestval("fu");
$search_from_id = isset($search_from[$id]) ? $id : 1;
// fant ikke brukeren?
if ($search_from_id == 4)
{
	$name = trim(requestval("u"));
	
	// sett opp brukernavnene
	$names = array_unique(array_map("trim", explode(",", $name)));
	foreach ($names as $key => $name) { if ($name == "") unset($names[$key]); }
	$names_lc = array_flip(array_map("strtolower", $names));
	
	if (count($names) == 0)
	{
		$search_from_id = 1;
		//$_base->page->add_message("Ingen brukere ble funnet.. Søker blant alle.", "error");
	}
	
	else
	{
		// hent brukerene
		$result = \Kofradia\DB::get()->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE up_name IN (".implode(",", array_map(array($_base->db, "quote"), $names)).")");
		
		$players = array();
		while ($row = $result->fetch())
		{
			$players[$row['up_id']] = $row;
			unset($names_lc[mb_strtolower($row['up_name'])]);
		}
		
		// noen vi ikke fant?
		if (count($names_lc) > 0)
		{
			$missing = array();
			foreach ($names_lc as $id)
			{
				$missing[] = $names[$id];
			}
			
			$_base->page->add_message("Følgende spillere finnes ikke: ".implode(", ", array_map("htmlspecialchars", $missing)), "error");
		}
		
		if (count($players) == 0)
		{
			$search_from_id = 1;
		}
		else
		{
			$where = count($players) == 1 ? '= '.current(array_keys($players)) : 'IN ('.implode(",", array_keys($players)).')';
			$search_from[4][1] = " AND ft_up_id $where";
			$search_from[4][2] = " AND fr.fr_up_id $where";
		}
	}
}
$search_from_topic_query = $search_from[$search_from_id][1];
$search_from_reply_query = $search_from[$search_from_id][2];

// slettet
$search_deleted = array(
	1 => array("<b>Skjul alle</b> slettede forumtråder og forumsvar", " AND ft_deleted = 0", " AND ft_deleted = 0 AND fr.fr_deleted = 0"),
	array("<b>Skjul</b> slettede <b>forumsvar</b>, <b>vis</b> slettede <b>forumtråder</b>", "", " AND fr.fr_deleted = 0"),
	array("<b>Vis slettede</b> forumtråder og forumsvar", "", "")
);
$id = requestval("d");
$search_deleted_id = isset($search_deleted[$id]) && $allow_deleted ? $id : 1;



// sortering
$sort = new sorts("sort");
$sort->append("asc", "Tittel", "ft_title, ft_time DESC");
$sort->append("desc", "Tittel", "ft_title DESC, ft_time DESC");
$sort->append("asc", "Dato", "ft_time");
$sort->append("desc", "Dato", "ft_time DESC");
$sort->append("asc", "Antall svar", "ft_replies, ft_time DESC");
$sort->append("desc", "Antall svar", "ft_replies DESC, ft_time DESC");
$sort->append("asc", "Antall visninger", "ft_views");
$sort->append("desc", "Antall visninger", "ft_views DESC");
$sort->append("asc", "Siste innlegg", "IFNULL(ft_last_reply, ft_time)");
$sort->append("desc", "Siste innlegg", "IFNULL(ft_last_reply, ft_time) DESC");
$sort->set_active(requestval("sort"), 3);

// søkeform
echo '
<div class="bg1_c small">
	<h1 class="bg1">Søk i forum<span class="left"></span><span class="right"></span></h1>
	<form action="" method="get">
		<div class="bg1">
			<dl class="dl_20 dl_2x">
				<dt>Tittel</dt>
				<dd><input type="text" name="qs" value="'.htmlspecialchars(requestval("qs")).'" class="styled w150" /></dd>
				
				<dt>Innhold</dt>
				<dd><input type="text" name="qt" value="'.htmlspecialchars(requestval("qt")).'" class="styled w300" /></dd>
				
				<dt>Søk i forum</dt>
				<dd>
					<div style="float: left">';

foreach ($search_forums as $row)
{
	echo '
				<input type="checkbox" id="section_'.$row['fse_id'].'" name="s'.$row['fse_id'].'" value="1"'.(in_array($row['fse_id'], $search_forums_active) ? ' checked="checked"' : '').' /><label for="section_'.$row['fse_id'].'"> '.htmlspecialchars($row['name']).'</label><br />';
}

echo '
				</div><div class="clear"></div></dd>
				
				<dt>Type innlegg</dt>
				<dd>
					<div style="float: left">';

foreach ($search_where as $key => $item)
{
	echo '
					<input type="radio" id="w_'.$key.'" name="w" value="'.$key.'"'.($search_where_id == $key ? ' checked="checked"' : '').' /><label for="w_'.$key.'"> '.htmlspecialchars($item[0]).'</label><br />';
}

echo '
				</div><div class="clear"></div></dd>
				
				<dt>Av?</dt>
				<dd>
					<div style="float: left">';

$i = 0;
foreach ($search_from as $key => $item)
{
	if (++$i == count($search_from)) break;
	echo '
					<input type="radio" id="f_'.$key.'" name="fu" value="'.$key.'"'.($search_from_id == $key ? ' checked="checked"' : '').' /><label for="f_'.$key.'"> '.htmlspecialchars($item[0]).'</label><br />';
}

echo '
				</div><div class="clear"></div></dd>
				
				<dt>&nbsp;</dt>
				<dd><input type="radio" id="f_'.$key.'" name="fu" value="'.$key.'"'.($search_from_id == $key ? ' checked="checked"' : '').' onclick="$(\'u_name\').focus()" /><label for="f_'.$key.'"> Spesifiser: </label><input type="text" name="u" value="'.htmlspecialchars(requestval("u")).'" class="styled w100" id="u_name" onfocus="$(\'f_'.$key.'\').checked=true" /></dd>';

// vis slettede?
if ($allow_deleted)
{
	echo '
			
				<dt>Slettet</dt>
				<dd>
					<div style="float: left">';
	
	foreach ($search_deleted as $key => $item)
	{
		echo '
					<input type="radio" id="d_'.$key.'" name="d" value="'.$key.'"'.($search_deleted_id == $key ? ' checked="checked"' : '').' /><label for="d_'.$key.'"> '.$item[0].'</label><br />';
	}
	
	echo '
				</div><div class="clear"></div></dd>';
}

echo '
			</dl>
			<p class="c">'.show_sbutton("Utfør søk").'</p>
		</div>
	</form>
</div>';


// søke?
if (isset($_GET['qs']))
{
	$title_search = requestval("qs");
	$text_search = requestval("qt");
	
	// finn ut delene av spørringen
	$title_parts = search_query($title_search);
	$text_parts = search_query($text_search);
	
	/*if (count($title_parts[0]) == 0 && count($text_parts[0]) == 0 && $search_from_id != 4)
	{
		echo '
<h2>Søkeresultater</h2>
<p>Skal du ikke søke etter noe?!</p>';
	}
	
	else*/
	{
		// sett opp søkespørringen
		$search_title = false;
		$search_text_topic = false;
		$search_text_reply = false;
		if (count($title_parts[0]) > 0)
		{
			$search_title = " AND ft_title".implode(" AND ft_title", $title_parts[0]);
		}
		if (count($text_parts[0]) > 0)
		{
			$search_text_topic = " AND ft_text".implode(" AND ft_text", $text_parts[0]);
			$search_text_reply = " AND fr.fr_text".implode(" AND fr.fr_text", $text_parts[0]);
		}
		
		if ($search_title === false && $search_text_topic === false && $search_text_reply === false)
		{
			$search_text_topic = "";
			$search_text_reply = "";
		}
		
		// opprett temporary tabell
		\Kofradia\DB::get()->exec("
			CREATE TEMPORARY TABLE temp_results (
				tr_ft_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
				tr_fr_id INT(11) UNSIGNED NULL DEFAULT NULL,
				tr_match_type ENUM('subject','topic','reply') NOT NULL DEFAULT 'subject',
				PRIMARY KEY (tr_ft_id, tr_fr_id)
			) ENGINE = MEMORY");
		
		$deleted = $search_deleted[$search_deleted_id][1];
		$where = $search_where[$search_where_id];
		
		// hente tittel?
		if ($search_title !== false)
		{
			\Kofradia\DB::get()->exec("
				INSERT INTO temp_results (tr_ft_id, tr_match_type)
				SELECT ft_id, 'subject'
				FROM forum_topics
				WHERE 1$deleted$search_forums_query$search_from_topic_query$search_title");
		}
		
		// hente hovedinnleggene?
		if ($where[1] && $search_text_topic !== false)
		{
			\Kofradia\DB::get()->exec("
				INSERT IGNORE INTO temp_results (tr_ft_id, tr_match_type)
				SELECT ft_id, 'topic'
				FROM forum_topics
				WHERE 1$deleted$search_forums_query$search_from_topic_query$search_text_topic");
		}
		
		// hente svarene?
		if ($where[2] && $search_text_reply !== false)
		{
			$deleted = $search_deleted[$search_deleted_id][2];
			\Kofradia\DB::get()->exec("
				CREATE TEMPORARY TABLE temp_results2 (
					tr_ft_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
					tr_fr_id INT(11) UNSIGNED NOT NULL DEFAULT 0
				) ENGINE = MEMORY");
			
			\Kofradia\DB::get()->exec("
				INSERT INTO temp_results2
				SELECT ft_id, fr_id
				FROM
					forum_topics,
					forum_replies fr
				WHERE ft_id = fr.fr_ft_id$deleted$search_forums_query$search_from_reply_query$search_text_reply");
			
			// legg til i "funnene"
			\Kofradia\DB::get()->exec("
				INSERT IGNORE INTO temp_results
				SELECT DISTINCT tr_ft_id, tr_fr_id, 'reply'
				FROM temp_results2");
		}
		
		// sortering
		$sort_info = $sort->active();
		$query = "
			SELECT
				tr_match_type, tr_fr_id,
				ft_deleted, ft_id, ft_type, ft_fse_id, ft_title, ft_time, ft_up_id, ft_locked, ft_replies, ft_views, ft_last_reply,
				up.up_name, up.up_access_level,
				rup.up_name r_up_name, rup.up_access_level r_up_access_level,
				fr_time, fr_up_id
			FROM
				temp_results tr,
				forum_topics t
				LEFT JOIN users_players up ON ft_up_id = up.up_id
				LEFT JOIN forum_replies r ON ft_last_reply = fr_id
				LEFT JOIN users_players rup ON fr_up_id = rup.up_id
			WHERE tr_ft_id = ft_id
			ORDER BY {$sort_info['params']}";
		
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 30);
		$result = $pagei->query($query);
		
		$info = array();
		foreach ($title_parts[1] as $part)
		{
			$info[] = '<b>Tittel:</b> '.htmlspecialchars($part);
		}
		foreach ($text_parts[1] as $part)
		{
			$info[] = '<b>Innhold:</b> '.htmlspecialchars($part);
		}
		if ($search_from_id == 4)
		{
			$u = array();
			foreach ($players as $player)
			{
				$u[] = game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']);
			}
			$info[] = '<b>Bruker:</b> '.implode(" eller ", $u);
		}
		
		if (count($info) == 0)
		{
			$info = "Fritt søk";
		}
		else
		{
			$info = implode(" ", $info);
		}
		
		echo '
<div class="bg1_c large scroll_here">
	<h1 class="bg1">Søkeresultater<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Søk: '.$info.'</p>';
		
		// fant vi noe?
		if ($pagei->total == 0)
		{
			echo '
		<p>Fant ingen treff.</p>';
		}
		
		else
		{
			echo '
		<p>Antall treff: <b>'.$pagei->total.'</b></p>
		<table class="table'.($pagei->pages == 1 ? ' tablemb' : '').'" width="100%">
			<thead>
				<tr>
					<th>Forum</th>
					<th>Tittel<br /><nobr>'.$sort->show_link(0, 1).'</nobr></th>
					<th>Trådstarter<br /><nobr>'.$sort->show_link(2, 3).'</nobr></th>
					<th>Svar<br /><nobr>'.$sort->show_link(4, 5).'</nobr></th>
					<th><abbr title="Visninger">Vis</abbr><br /><nobr>'.$sort->show_link(6, 7).'</nobr></th>
					<th>Siste innlegg<br />'.$sort->show_link(8, 9).'</th>
					<th>Type<br />treff</th>
				</tr>
			</thead>
			<tbody class="c">';
			
			// legg til nødvendig css
			$_base->page->add_css('
.f_viktig a { font-weight: bold; color: #FFFFFF }
.f_viktig .info { color: #CCFF00; font-weight: bold }
.f_sticky a { color: #FFFFFF }
.f_sticky .info { color: #CCFF00 }
.f_lock { color: #FFFFFF }
.f_u a span { color: #FFFFFF; text-decoration: none }
.f_u a:hover span { text-decoration: underline }
.f_time { color: #AAAAAA }
.f_deld { color: #FFF; font-size: 11px }');
			
			// vis hver topic
			$i = 0;
			while ($row = $result->fetch())
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="forum?id='.$row['ft_fse_id'].'">'.htmlspecialchars($sections[$row['ft_fse_id']]['name']).'</a></td>
					<td class="l'.($row['ft_type'] == 3 ? ' f_viktig' : ($row['ft_type'] == 2 ? ' f_sticky' : '')).'"><a href="topic?id='.$row['ft_id'].'">'.htmlspecialchars($row['ft_title']).'</a>'.($row['ft_type'] == 3 ? ' <span class="info">(Viktig)</span>' : ($row['ft_type'] == 2 ? ' <span class="info">(Sticky)</span>' : '')).($row['ft_locked'] == 1 ? ' <span class="f_lock">(låst)</span>' : '').($row['ft_deleted'] != 0 ? ' <span class="f_deld">(Slettet)</span>' : '').'</td>
					<td class="f_u">'.game::profile_link($row['ft_up_id'], $row['up_name'], $row['up_access_level']).'<br /><span class="f_time">'.$_base->date->get($row['ft_time'])->format().'</span></td>
					<td>'.game::format_number($row['ft_replies']).'</td>
					<td>'.game::format_number($row['ft_views']).'</td>
					<td class="f_u">'.($row['fr_time'] ? game::profile_link($row['fr_up_id'], $row['r_up_name'], $row['r_up_access_level']).'<br /><span class="f_time">'.game::timespan($row['fr_time'], game::TIME_ABS).'</span>' : '<span style="color: #AAA">Ingen</span>').'</td>
					<td>'.($row['tr_match_type'] == 'subject' ? 'Tittel' : ($row['tr_match_type'] == 'topic' ? 'Hoved' : '<a href="topic?id='.$row['ft_id'].'&amp;replyid='.$row['tr_fr_id'].'">Svar &raquo;</a>')).'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		echo '
	</div>
</div>';
	}
}

$_base->page->load();