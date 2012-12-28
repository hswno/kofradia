<?php

require "base.php";
global $_base;

// vis en spesiell bruker?
$user = login::$user;
$show_deleted = false;

// bruker ID
if (isset($_GET['u_id']) && access::has("admin"))
{
	// hent info
	$u_id = (int) getval("u_id");
	$user = user::get($u_id);
	
	if (!$user)
	{
		echo '
<h1>Meldinger</h1>
<p>Fant ingen bruker med ID <b>'.$u_id.'</b>.</p>';
		$_base->page->load();
	}
	
	$show_deleted = true;
	echo '
<h1 class="scroll_here">Meldinger Admin</h1>
<p>Du viser meldingene som tilhører '.game::profile_link($user->player->data['up_id'], $user->player->data['up_name'], $user->player->data['up_access_level']).'</p>';
	
	redirect::store("innboks_sok?u_id=$u_id");
}

// logg visning av innboks
putlog("PROFILVIS", "%c5%bVIS-MELDINGER-SOK:%b%c %u".login::$user->player->data['up_name']."%u ({$_SERVER['REQUEST_URI']})");


// hvem som skal ha kunnet skrevet det man søker på
$search_from = array(
	1 => array("Alle", ""),
	array("Meg selv", " AND im_up_id = up_ref.up_id"),
	array("Andre", " AND im_up_id != up_ref.up_id"),
	array('Spesifiser', NULL, array())
);
$id = requestval("f");
$search_from_id = isset($search_from[$id]) ? $id : 1;

// fant ikke brukeren?
if ($search_from_id == 4)
{
	$name = trim(postval("u"));
	
	// sett opp brukernavnene
	$names = explode(",", $name);
	foreach ($names as $name)
	{
		$name = trim($name);
		if (empty($name)) continue;
		if (preg_match('/^[0-9a-zA-Z\-_ ]+$/D', $name))
		{
			$search_from[4][2][] = $name;
		}
		else
		{
			$_base->page->add_message('Ugyldig spillernavn: <b>'.htmlspecialchars($name).'</b>.', "error");
		}
	}
	
	if (count($search_from[4][2]) == 0)
	{
		$search_from_id = 1;
	}
	elseif (count($search_from[4][2]) == 1)
	{
		$search_from[4][1] = " AND up.up_name = ".$_base->db->quote($search_from[4][2][0]);
	}
	else
	{
		$search_from[4][1] = " AND up.up_name IN (".implode(",", array_map(array($_base->db, "quote"), $search_from[4][2])).")";
	}
}


//$title = ucwords($search_where[$search_where_id][0]);
$_base->page->add_title("Meldinger", "Søk");



// sortering
$sort = new sorts("sort");
$sort->append("asc", "Avsender", "up_name, im_time DESC");
$sort->append("desc", "Avsender", "up_name DESC, im_time DESC");
$sort->append("asc", "Emne", "it_title, im_time DESC");
$sort->append("desc", "Emne", "it_title DESC, im_time DESC");
$sort->append("asc", "Innhold", "id_text");
$sort->append("desc", "Innhold", "id_text DESC");
$sort->append("asc", "Dato", "im_time");
$sort->append("desc", "Dato", "im_time DESC");
$sort->set_active(postval("sort"), 7);

// søkeform
echo '
<h1>Søk - Meldinger</h1>
<p class="h_right"><a href="'.htmlspecialchars(game::address("innboks", $_GET)).'">Tilbake</a></p>
<form action="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array("alle", "innboks", "utboks", "side"))).'" method="post">
	<div class="section" style="width: 410px; margin-left: auto; margin-right: auto">
		<h2>Søk</h2>
		<dl class="dl_20 dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title")).'" class="styled w150" /></dd>
			
			<dt>Innhold</dt>
			<dd><input type="text" name="text" value="'.htmlspecialchars(postval("text")).'" class="styled w300" /></dd>
			
			<dt>Av?</dt>
			<dd>';

$i = 0;
foreach ($search_from as $key => $item)
{
	if (++$i == count($search_from)) break;
	echo '
				<input type="radio" id="f_'.$key.'" name="f" value="'.$key.'"'.($search_from_id == $key ? ' checked="checked"' : '').' /><label for="f_'.$key.'"> '.htmlspecialchars($item[0]).'</label>';
}

echo '
			</dd>
			
			<dt>&nbsp;</dt>
			<dd><input type="radio" id="f_'.$key.'" name="f" value="'.$key.'"'.($search_from_id == $key ? ' checked="checked"' : '').' onclick="$(\'u_name\').focus()" /><label for="f_'.$key.'"> Spesifiser: </label><input type="text" name="u" value="'.htmlspecialchars(postval("u")).'" class="styled w100" id="u_name" onfocus="$(\'f_'.$key.'\').checked=true" /></dd>
		</dl>
		<h3 class="c">
			'.show_sbutton("Utfør søk", 'name="search"').'
		</h3>
	</div>
</form>';




// søke?
if (isset($_POST['search']))
{
	$title_search = postval("title");
	$text_search = postval("text");
	
	// finn ut delene av spørringen
	$title_parts = search_query($title_search);
	$text_parts = search_query($text_search);
	
	if (count($title_parts[0]) == 0 && count($text_parts[0]) == 0 && $search_from_id != 4)
	{
		echo '
<h2>
	Søkeresultater
</h2>
<p>
	Skal du ikke søke etter noe?!
</p>';
	}
	
	else
	{
		// sett opp søkespørringen
		$search = "";
		if (count($title_parts[0]) > 0)
		{
			$search .= " AND it_title".implode(" AND it_title", $title_parts[0]);
		}
		if (count($text_parts[0]) > 0)
		{
			$search .= " AND id_text".implode(" AND id_text", $text_parts[0]);
		}
		
		// søke i slettede meldinger?
		$deleted = $show_deleted ? " AND im_deleted = 0" : " AND ir_deleted = 0";
		
		// sortering
		$sort_info = $sort->active();
		
		$per_page = login::data_get("innboks_per_side", 15);
		$pagei = new pagei(pagei::ACTIVE_POST, "side", pagei::PER_PAGE, $per_page);
		//  AND it_id = im_it_id{$search_where[$search_where_id][1]}
		$result = $pagei->query("
			SELECT it_id, it_title, ir_up_id, ir_unread, ir_unread, ir_deleted, ir_marked, im_id, im_up_id, im_time, id_text, up.up_name, up.up_access_level
			FROM inbox_threads, inbox_rel, users_players up_ref, inbox_data, inbox_messages LEFT JOIN users_players up ON up.up_id = im_up_id
			WHERE
				up_ref.up_u_id = $user->id
				AND ir_up_id = up_ref.up_id$deleted
				AND it_id = ir_it_id
				AND im_it_id= it_id{$search_from[$search_from_id][1]}
				AND im_time <= ir_restrict_im_time
				AND im_id = id_im_id$search
			ORDER BY {$sort_info['params']}");
		
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
			if (count($search_from[4][2]) == 1)
			{
				$info[] = '<b>Avsender:</b> <user="'.htmlspecialchars($search_from[4][2][0]).'" />';
			}
			else
			{
				$u = array();
				foreach ($search_from[4][2] as $name)
				{
					$u[] = '<user="'.htmlspecialchars($name).'" />';
				}
				$info[] = '<b>Avsender:</b> '.implode(" eller ", $u);
			}
		}
		$info = implode(" ", $info);
		
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
<p>
	Antall treff: <b>'.$pagei->total.'</b>
</p>
<form action="" method="post">';
			
			foreach ($_POST as $key => $value)
			{
				if ($key == "side" || $key == "sort") continue;
				echo '
	<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />';
			}
			
			echo '
	<input type="hidden" name="sort" id="sort_sort" value="'.$sort->active.'" />
	<table class="table" width="100%" id="meldinger">
		<thead>
			<tr>
				<th><span class="tools_r">'.$sort->show_button(0, 1).'</span> Avsender</th>
				<th><span class="tools_r">'.$sort->show_button(2, 3).'</span> Emne</th>
				<th><span class="tools_r">'.$sort->show_button(4, 5).'</span>Innhold</th>
				<th><span class="tools_r">'.$sort->show_button(6, 7).'</span> Dato</th>
			</tr>
		</thead>
		<tbody>';
			
			$_base->page->add_css('
.ny { color: #FF0000; font-weight: bold }
.it_u { white-space: nowrap; width: 100px }
.it_dato { text-align: center; white-space: nowrap; color: #888888; width: 100px }
#meldinger a { text-decoration: none }
.utgaaende { color: #888888 }'.($show_deleted ? '
.slettet { color: #BBBB99 }' : '
.ir_marked { color: #BBBB99; font-weight: bold }'));
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				$content = trim(strip_tags(game::bb_to_html($row['id_text'])));
				$length = strlen($content);
				
				$max = 60;
				if (strlen($content) > $max)
				{
					$content = substr($content, 0, $max - 4) . " ...";
				}
				
				echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td class="it_u">'.($row['im_up_id'] == $user->player->id ? '<span class="utgaaende">Utgående</span>' : game::profile_link($row['im_up_id'], $row['up_name'], $row['up_access_level'])).'</td>
				<td><a href="innboks_les?id='.$row['it_id'].'&amp;goto='.$row['im_id'].'">'.htmlspecialchars($row['it_title']).'</a>'.($row['ir_unread'] == 1
					? ' <span class="ny">(Ny!)</span>' : ($row['ir_unread'] > 1
					? ' <span class="ny">('.$row['ir_unread'].' nye!)</span>' : '')).($row['ir_deleted'] != 0
					? ' <span class="slettet">(Slettet)</span>' : '').($row['ir_up_id'] != $user->player->id || !$user->player->active
					? ' <span class="it_locked">(Låst)</span>' : '').($row['ir_marked'] != 0
					? ' <span class="ir_marked">(Til oppfølging)</span>' : '').'</td>
				<td class="dark">'.htmlspecialchars($content).' ('.$length.' tegn)</td>
				<td class="it_dato">'.$_base->date->get($row['im_time'])->format().'</td>
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
		'.$pagei->pagenumbers("input").'
	</p>';
			}
			
			echo '
</form>';
		}
	}
}

$_base->page->load();