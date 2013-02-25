<?php

/**
 * Support
 */
class support
{
	/** Kategoriene */
	public static $kategorier = array(
		1 => array(
			"id" => 1,
			"name" => "Generelt",
			"total" => 0,
			"new" => 0
		),
		4 => array(
			"id" => 4,
			"name" => "Forslag",
			"total" => 0,
			"new" => 0
		),
		2 => array(
			"id" => 2,
			"name" => "Feil i spillet/bugs",
			"total" => 0,
			"new" => 0
		),
		3 => array(
			"id" => 3,
			"name" => "Annet",
			"total" => 0,
			"new" => 0
		)
	);
	
	/** Ventetid mellom hver nye henvendelse */
	public static $ventetid_new = 600;
	
	/** Ventetid mellom hver melding */
	public static $ventetid_reply = 10;
	
	/** Initialisering */
	public static function init()
	{
		access::no_guest();
		
		// oppdatere status?
		if (isset($_POST['load_status']))
		{
			self::action_status();
			die;
		}
		
		ess::$b->page->add_title("Support");
		ess::$b->page->add_css('
td.support_important {
	background-color: #FF0000;
	color: #FFFFFF;
	font-weight: bold;
}
');
		
		// hva skal vises?
		switch (getval("a"))
		{
			// vis en henvendelse
			case "show":
				self::action_show();
			break;
			
			// panelet
			case "panel":
				self::action_panel();
			break;
			
			// søk
			case "search":
				self::action_search();
			break;
			
			// forsiden
			case "":
				self::action_main();
			break;
			
			// ukjent
			default:
				redirect::handle("");
		}
		
		ess::$b->page->load();
	}
	
	/** Hent status for en henvendelse (ajax) */
	public static function action_status()
	{
		// mangler ID?
		if (!isset($_POST['su_id'])) redirect::handle("");
		
		// finner vi den?
		$su = support_henvendelse::get($_POST['su_id']);
		if (!$su || !$su->has_access() || $su->own)
		{
			ajax::text("ERROR:404-SUPPORT", ajax::TYPE_404);
		}
		
		// vis status
		$su->status_ajax();
	}
	
	/** Vis forsiden */
	public static function action_main()
	{
		// skal vi sende inn en henvendelse?
		if (isset($_POST['kategori']))
		{
			self::handle_new();
		}
		
		echo '
<h1>Support</h1>';
		
		// vis skjema for ny henvendelse
		self::show_new_form();
		
		// vis oversikt over egne henvendelser
		self::show_own();
		
		// vis oversikt over panel
		self::show_main_panel_info();
		
		// vis statistikk
		self::show_stats();
	}
	
	/** Vis en henvendelse */
	public static function action_show()
	{
		// mangler ID?
		if (!isset($_GET['su_id'])) redirect::handle("");
		
		// finner vi den?
		$su = support_henvendelse::get($_GET['su_id']);
		if (!$su)
		{
			ess::$b->page->add_message("Fant ikke henvendelsen.");
			redirect::handle("");
		}
		
		// sjekk for tilgang
		if (!$su->has_access())
		{
			ess::$b->page->add_message("Fant ikke henvendelsen.");
			redirect::handle("");
		}
		
		// behandle visning av siden
		$su->init();
	}
	
	/** Søkefunksjon */
	public static function action_search()
	{
		access::need("crewet");
		ess::$b->page->add_title("Søk");
		
		// hvem som skal ha kunnet skrevet det man søker på
		$search_from = array(
			1 => array("Alle", ""),
			array("Meg selv", " AND sum_up_id = up_ref.up_id"),
			array("Andre", " AND sum_up_id != up_ref.up_id"),
			array('Spesifiser', NULL, array())
		);
		$id = requestval("f");
		$search_from_id = isset($search_from[$id]) ? $id : 1;
		
		// sjekk etter spillere?
		if ($search_from_id == 4)
		{
			$name = trim(postval("up"));
			
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
					ess::$b->page->add_message('Ugyldig spillernavn: <b>'.htmlspecialchars($name).'</b>.', "error");
				}
			}
			
			if (count($search_from[4][2]) == 0)
			{
				$search_from_id = 1;
			}
			else
			{
				$search_from[4][1] = " AND up_sum.up_name IN (".implode(",", array_map(array(ess::$b->db, "quote"), $search_from[4][2])).")";
			}
		}
		
		// søke kun blant den som sendte inn henvendelsen eller de som svarte?
		// MERK: tar ikke høyde for at brukere skifter spiller
		$search_by = array(
			1 => array("Alle", ""),
			array("Innsender", " AND sum_up_id = su_up_id"),
			array("Crewet", " AND sum_up_id != su_up_id")
		);
		$id = requestval("fb");
		$search_by_id = isset($search_by[$id]) ? $id : 1;
			
		// kategorier
		$search_kat = array();
		foreach (self::$kategorier as $info)
		{
			$search_kat[$info['id']] = array(
				$info['name'], "su_category = {$info['id']}"
			);
		}
		$search_kat_active = array();
		$search_kat_query = array();
		
		foreach ($_REQUEST as $key => $dummy)
		{
			$match = false;
			if (preg_match("/^k(\\d+)$/D", $key, $match))
			{
				if (isset($search_kat[$match[1]]))
				{
					$search_kat_active[] = $match[1];
					$search_kat_query[] = $search_kat[$match[1]][1];
				}
			}
		}
		
		if (count($search_kat_query) > 0 && count($search_kat_query) != count($search_kat))
		{
			$search_kat_query = " AND (" . implode(" OR ", $search_kat_query) . ")";
			$more = true;
		}
		else
		{
			if (count($search_kat_active) == 0)
			{
				$search_kat_active = array_keys($search_kat);
			}
			$search_kat_query = "";
		}
		
		// sortering
		$sort = new sorts("sort");
		$sort->append("asc", "Avsender", "up_name, sum_time DESC");
		$sort->append("desc", "Avsender", "up_name DESC, sum_time DESC");
		$sort->append("asc", "Tittel", "su_title, sum_time DESC");
		$sort->append("desc", "Tittel", "su_title DESC, sum_time DESC");
		#$sort->append("asc", "Innhold", "id_text");
		#$sort->append("desc", "Innhold", "id_text DESC");
		$sort->append("asc", "Tid", "sum_time");
		$sort->append("desc", "Tid", "sum_time DESC");
		$sort->set_active(postval("sort"), 5);
		
		// søkeskjema
		echo '
<h1>Søk i support</h1>
<p class="h_right"><a href="./">Tilbake</a></p>
<form action="" method="post">
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
			if (++$i == count($search_from))
				break;
			echo '
				<input type="radio" id="f_'.$key.'" name="f" value="'.$key.'"'.($search_from_id == $key ? ' checked="checked"' : '').' /><label for="f_'.$key.'"> '.htmlspecialchars($item[0]).'</label>';
		}
		
		echo '
			</dd>
			
			<dt>&nbsp;</dt>
			<dd><input type="radio" id="f_'.$key.'" name="f" value="'.$key.'"'.($search_from_id == $key ? ' checked="checked"' : '').' onclick="$(\'u_name\').focus()" /><label for="f_'.$key.'"> Spesifiser: </label><input type="text" name="up" value="'.htmlspecialchars(postval("up")).'" class="styled w100" id="u_name" onfocus="$(\'f_'.$key.'\').checked=true" /></dd>
			
			<dt>Hvilke meldinger?</dt>
			<dd>';
		
		foreach ($search_by as $key => $item)
		{
			echo '
				<input type="radio" id="fb_'.$key.'" name="fb" value="'.$key.'"'.($search_by_id == $key ? ' checked="checked"' : '').' /><label for="fb_'.$key.'"> '.htmlspecialchars($item[0]).'</label>';
		}
		
		echo '
			</dd>
			
			<dt>Kategorier</dt>
			<dd>';
		
		$i = 0;
		foreach ($search_kat as $key => $item)
		{
			if ($i++ > 0) echo '<br />';
			echo '
					<input type="checkbox" id="kat?' . $key . '" name="k' . $key . '" value="1"' . (in_array($key, $search_kat_active) ? ' checked="checked"' : '') . ' /><label for="kat_' . $key . '"> ' . htmlspecialchars($item[0]) . '</label>';
		}
		
		echo '
			</dd>
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
			
			if (count($title_parts[0]) == 0 && count($text_parts[0]) == 0 && $search_from_id != 4 && $search_kat_query == "")
			{
				echo '
<h2>Søkeresultater</h2>
<p>Ingen søkekriterier.</p>';
			} 
			
			else
			{
				// sett opp søkespørringen
				$search = "";
				if (count($title_parts[0]) > 0)
				{
					$search .= " AND su_title".implode(" AND su_title", $title_parts[0]);
				}
				if (count($text_parts[0]) > 0)
				{
					$search .= " AND sum_text".implode(" AND sum_text", $text_parts[0]);
				}
				
				// sortering
				$sort_info = $sort->active();
				
				// sidetall - hent henvendelsene på denne siden
				$pagei = new pagei(pagei::ACTIVE_POST, "side", pagei::PER_PAGE, 50);
				$result = $pagei->query("
					SELECT su_id, su_up_id, su_category, su_title, su_time, su_solved, sum_id, sum_up_id, sum_time, sum_text, up_sum.up_name, up_sum.up_access_level
					FROM support
						JOIN support_messages ON sum_su_id = su_id
						JOIN users_players up_sum ON up_sum.up_id = sum_up_id,
						users_players up_ref
					WHERE up_ref.up_u_id = ".login::$user->id."{$search_from[$search_from_id][1]}{$search_by[$search_by_id][1]}$search_kat_query$search
					GROUP BY sum_id".(count($text_parts[0]) == 0 && $search_from_id == 1 ? ", su_id" : "")."
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
						$info[] = '<b>Spiller:</b> <user="'.htmlspecialchars($search_from[4][2][0]).'" />';
					} else
					{
						$u = array();
						foreach ($search_from[4][2] as $name)
						{
							$u[] = '<user="'.htmlspecialchars($name).'" />';
						}
						$info[] = '<b>Spiller:</b> '.implode(" eller ", $u);
					}
				}
				$info = implode(" ", $info);
				
				echo '
<h2>Søkeresultater</h2>
<p>Søkekriterier: '.$info.'</p>';
				
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
<form action="" method="post">';
					
					foreach ($_POST as $key => $value)
					{
						if ($key == "side" || $key == "sort")
							continue;
						echo '
	<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />';
					}
					
					echo '
	<input type="hidden" name="sort" id="sort_sort" value="'.$sort->active.'" />
	<table class="table'.($pagei->pages == 1 ? ' tablemb' : '').'" width="100%">
		<thead>
			<tr>
				<th><span class="tools_r">'.$sort->show_button(0, 1).'</span> Spiller</th>
				<th><span class="tools_r">'.$sort->show_button(2, 3).'</span> Henvendelse</th>
				<th><span class="tools_r">Tekst</th>
				<th><span class="tools_r">'.$sort->show_button(4, 5).'</span> Tid</th>
			</tr>
		</thead>
		<tbody>';
					
					ess::$b->page->add_css('
.su_not_solved { color: #FF0000; font-weight: bold }
.sum_up { white-space: nowrap; width: 100px }
.su_time { text-align: center; white-space: nowrap; color: #888888; width: 100px }');
					
					$i = 0;
					while ($row = mysql_fetch_assoc($result))
					{
						$content = trim(strip_tags(game::bb_to_html($row['sum_text'])));
						$length = strlen($content);
						
						$max = 60;
						if (strlen($content) > $max)
						{
							$content = substr($content, 0, $max - 4)." ...";
						}
						
						echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td class="sum_up">'.game::profile_link($row['sum_up_id'], $row['up_name'], $row['up_access_level']).'</td>
				<td><a href="./?a=show&amp;su_id='.$row['su_id'].'">'.htmlspecialchars($row['su_title']).'</a>'.($row['su_solved'] == 0 ? ' <span class="su_not_solved">(Uløst)</span>' : '').'</td>
				<td class="dark">'.htmlspecialchars($content).' ('.$length.' tegn)</td>
				<td class="su_time">'.ess::$b->date->get($row['sum_time'])->format().'</td>
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
	}
	
	/** Vis panelet */
	public static function action_panel()
	{
		access::need("crewet");
		ess::$b->page->add_title("Panelet");
		
		echo '
<h1>Support panelet</h1>
<p class="h_right"><a href="./">Tilbake</a></p>';
		
		// vis kategori
		self::show_panel_category();
		
		// vis oversikt over kategoriene
		self::show_main_panel_info();
	}
	
	/** Vis en kategori */
	protected static function show_panel_category()
	{
		// har ikke kategori?
		if (!isset($_GET['kategori'])) return;
		
		$oppsummering = false;
		if ($_GET['kategori'] == "oppsummering")
		{
			$oppsummering = true;
			$kategori = array(
				"id" => "oppsummering", "name" => "Oppsummering"
			);
		}
		elseif (!isset(self::$kategorier[$_GET['kategori']]))
		{
			ess::$b->page->add_message("Fant ikke kategorien med ID <b>".htmlspecialchars($_GET['kategori'])."</b>!", "error");
			ess::$b->page->load();
		}
		else
		{
			$kategori = self::$kategorier[$_GET['kategori']];
		}
		
		// hent kategori info
		$where = $oppsummering ? '' : " WHERE su_category = {$kategori['id']}";
		$result = ess::$b->db->query("SELECT COUNT(su_id), COUNT(IF(su_solved=0,1,NULL)) FROM support$where");
		$kategori['total'] = mysql_result($result, 0, 0);
		$kategori['new'] = mysql_result($result, 0, 1);
		
		// vis kategori informasjon
		echo '
<table class="table center tablem">
	<thead>
		<tr>
			<th colspan="2">'.htmlspecialchars($kategori['name']).'</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th>Antall henvendelser</th>
			<td class="r">'.($kategori['total'] == 0 ? '<span style="color: #AAA">Ingen</span>' : game::format_number($kategori['total']).' stk').'</td>
		</tr>
		<tr class="color">
			<th>Nye</th>
			<td class="r">'.($kategori['new'] == 0 ? '<span style="color: #AAA">Ingen</span>' : '<b>'.game::format_number($kategori['new']).'</b> stk').'</td>
		</tr>
		<tr>
			<th>Besvarte</th>
			<td class="r">'.($kategori['total'] - $kategori['new'] == 0 ? '<span style="color: #AAA">Ingen</span>' : game::format_number($kategori['total'] - $kategori['new']).' stk').'</td>
		</tr>
	</tbody>
</table>';
		
		self::show_panel_category_items($kategori);
	}
	
	/** List opp ting i en kategori */
	protected static function show_panel_category_items($kategori)
	{
		$oppsummering = $kategori['id'] === "oppsummering";
		
		echo '
<table class="table center tablem">
	<tbody>
		<tr>
			<th>Tittel</th>
			<th>Innsender</th>
			<th>Ant</th>
			<th class="r">Dato</th>
			<th>Siste melding</th>
		</tr>';
		
		// sidetall - hent henvendelsene på denne siden
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
		$result = $pagei->query("
			SELECT su_id, su_up_id, su_category, su_title, su_time, su_solved, COUNT(sum1.sum_id) AS num_sum, MAX(sum1.sum_id) AS max_sum_id, sum2.sum_up_id, sum2.sum_time, sum2.sum_id
			FROM support
				JOIN support_messages sum1 ON sum1.sum_su_id = su_id
				JOIN support_messages sum2 ON sum2.sum_su_id = su_id
			WHERE 1".(!$oppsummering ? " AND su_category = ".ess::$b->db->quote($kategori['id']) : "")."
			GROUP BY su_id, sum_id
			HAVING sum2.sum_id = max_sum_id
			ORDER BY sum_time DESC");
		
		$i = 0;
		ess::$b->page->add_css('
.support_not_solved { color: #FF0000; font-weight: bold }
.support_sum_r_time, .support_category { font-size: 11px; color: #888 }');
		while ($row = mysql_fetch_assoc($result))
		{
			echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td><a href="./?a=show&amp;su_id='.$row['su_id'].'">'.htmlspecialchars($row['su_title']).'</a>'.($row['su_solved'] == 0 ? ' <span class="support_not_solved">(uløst)</span>' : '').($oppsummering ? '<br />
				<span class="support_category">'.htmlspecialchars(self::$kategorier[$row['su_category']]['name']) : '').'</td>
			<td><user id="'.$row['su_up_id'].'" /></td>
			<td class="r">'.$row['num_sum'].'</td>
			<td class="r">'.ess::$b->date->get($row['su_time'])->format().'<br />'.game::timespan($row['su_time'], game::TIME_ABS | game::TIME_SHORT).'</td>'.($row['sum_time'] == $row['su_time'] ? '
			<td>--</td>' : '
			<td class="r">
				<user id="'.$row['sum_up_id'].'" /><br />
				<span class="support_sum_r_time">'.ess::$b->date->get($row['sum_time'])->format().'<br />
				'.game::timespan($row['sum_time'], game::TIME_ABS | game::TIME_SHORT).'</span>
			</td>').'
		</tr>';
		}
		
		echo '
	</tbody>
</table>';
		
		if ($pagei->pages > 1)
		{
			echo '
<p class="c">'.$pagei->pagenumbers().'</p>';
		}
	}
	
	/** Opprett ny henvendelse */
	protected static function handle_new()
	{
		global $__server;
		
		// sjekk for blokkering
		$blokkering = blokkeringer::check(blokkeringer::TYPE_SUPPORT);
		if ($blokkering)
		{
			ess::$b->page->add_message("Du er blokkert fra å sende inn henvendelser til support. Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
			return;
		}
		
		// sjekk for ventetid
		if (!access::has("crewet"))
		{
			// hvor lenge er det siden forrige henvendelse ble opprettet?
			$result = ess::$b->db->query("
				SELECT su_time FROM support, users_players
				WHERE up_u_id = ".login::$user->id." AND su_up_id = up_id
				ORDER BY su_id DESC LIMIT 1");
			
			if (mysql_num_rows($result) > 0)
			{
				$last = mysql_result($result, 0);
				$wait = max(0, $last + self::$ventetid_new - time());
				
				if ($wait > 0)
				{
					ess::$b->page->add_message('Du må vente '.game::counter($wait).' før du kan sende inn en ny henvendelse.', "error");
					return;
				}
			}
		}
		
		$kategori = intval(postval("kategori"));
		$tittel = trim(postval("tittel"));
		$innhold = trim(postval("innhold"));
		
		// gyldig kategori?
		if (!isset(self::$kategorier[$kategori]))
		{
			ess::$b->page->add_message("Ugyldig kategori.", "error");
			return;
		}
		
		// for kort tittel?
		if (strlen($tittel) < 1)
		{
			ess::$b->page->add_message("Du må fylle ut en tittel.", "error");
			return;
		}
		
		// for lang tittel?
		if (strlen($tittel) > 80)
		{
			ess::$b->page->add_message("Tittelen kan maksimalt inneholde 80 tegn.", "error");
			return;
		}
		
		// mangler innhold?
		if (empty($innhold))
		{
			ess::$b->page->add_message("Du må fylle inn en tekst for din henvendelse.", "error");
			return;
		}
		
		$time = time();
		ess::$b->db->begin();
		
		// legg til henvendelsen
		ess::$b->db->query("INSERT INTO support SET su_up_id = ".login::$user->player->id.", su_category = $kategori, su_title = ".ess::$b->db->quote($tittel).", su_time = ".$time);
		$su_id = ess::$b->db->insert_id();
		
		// legg til innholdet av henvendelsen
		ess::$b->db->query("INSERT INTO support_messages SET sum_su_id = $su_id, sum_up_id = ".login::$user->player->id.", sum_time = $time, sum_text = ".ess::$b->db->quote($innhold));
		ess::$b->db->commit();
		
		// sett cache
		self::update_tasks();
		
		putlog("CREWCHAN", "%c11%bSUPPORT HENVENDELSE%b%c: %u".login::$user->player->data['up_name']."%u leverte en henvendelse til support - %u{$tittel}%u i kategorien %u".self::$kategorier[$kategori]['name']."%u - ".ess::$s['spath']."/support/?a=show&su_id=$su_id");
		
		ess::$b->page->add_message("Din henvendelse er nå levert og vil bli besvart når vi får mulighet til å behandle den.");
		redirect::handle("?a=show&su_id=$su_id");
	}
	
	/** Vis skjema for å sende inn ny henvendelse */
	protected static function show_new_form()
	{
		ess::$b->page->add_js_domready('
	$("previewButton").addEvent("click", function()
	{
		$("previewContainer").set("html", "<p>Laster inn forhåndsvisning..</p>");
		$("previewOuter").setStyle("display", "block");
		if ($("previewOuter").getPosition().y > window.getScroll().y + window.getSize().y)
		{
			$("previewOuter").goto(-15);
		}
		preview($("textContent").get("value"), $("previewContainer"));
	});');
		
		echo '
<div class="bg1_c small">
	<h2 class="bg1">Ny henvendelse<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<boxes />
		<p>Husk og les gjennom <a href="'.ess::$s['relative_path'].'/node">hjelpesidene</a> før du sender inn din henvendelse. Finner du ikke det du ser etter kan du sende inn en henvendelse her som alle i Crewet får mulighet til å svare på.</p>
		<form action="./" method="post">
			<dl class="dl_20">
				<dt>Kategori</dt>
				<dd>
					<select name="kategori">'.(!isset(self::$kategorier[requestval("kategori")]) ? '
						<option>Velg kategori..</option>' : '');
		
		// hent alle kategoriene
		foreach (self::$kategorier as $id => $row)
		{
			echo '
						<option value="'.intval($id).'"'.(requestval('kategori') == $id ? ' selected="selected"' : '').'>'.htmlspecialchars($row['name']).'</option>';
		}
		
		echo '
					</select>
				</dd>
				<dt>Tittel</dt>
				<dd><input type="text" name="tittel" value="'.htmlspecialchars(requestval('tittel')).'" class="styled w200" maxlength="80" /></dd>
				<dt>Beskrivelse</dt>
				<dd><textarea name="innhold" id="textContent" style="width: 90%" rows="10">'.htmlspecialchars(requestval('innhold')).'</textarea></dd>
			</dl>
			<p class="c">'.show_sbutton("Send inn spørsmål").' '.show_button("Forhåndsvis", 'accesskey="p" id="previewButton"').'</p>
			<div style="display: none" id="previewOuter">
				<p>Forhåndsvisning:</p>
				<div class="p" id="previewContainer"></div>
			</div>
		</form>
	</div>
</div>';
	}
	
	/** Vis informasjon for panel på forsiden */
	protected static function show_main_panel_info()
	{
		if (!access::has("crewet")) return;
		
		// finn ut hvor mange ubehandlede det er
		$result = ess::$b->db->query("
			SELECT su_category, COUNT(su_id) AS total, COUNT(IF(su_solved=0, 1, NULL)) AS new
			FROM support GROUP BY su_category");
		
		$kategorier = self::$kategorier;
		while ($row = mysql_fetch_assoc($result))
		{
			$kategorier[$row['su_category']]['total'] = $row['total'];
			$kategorier[$row['su_category']]['new'] = $row['new'];
		}
		
		echo '
<div class="bg1_c small">
	<h2 class="bg1">Oppsummering av henvendeser<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<table class="table center tablemt">
			<thead>
				<tr>
					<th>Kategori</th>
					<th>Antall spørsmål</th>
					<th>Nye spørsmål</th>
				</tr>
			</thead>
			<tbody>';
		
		$total = 0;
		$new = 0;
		$i = 0;
		foreach ($kategorier as $id => $row)
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="./?a=panel&amp;kategori='.$id.'">'.htmlspecialchars($row['name']).'</a></td>
					<td class="r">'.game::format_number($row['total']).'</td>
					<td class="r">'.($row['new'] > 0 ? '<b>'.game::format_number($row['new']).'</b> ny'.($row['new'] == 1 ? '' : 'e') : '<span style="color: #AAA">Ingen</span>').'</td>
				</tr>';
			$total += $row['total'];
			$new += $row['new'];
		}
		
		echo '
				<tr class="highlight">
					<td><a href="./?a=panel&amp;kategori=oppsummering">Oppsummering</a></td>
					<td class="r">'.game::format_number($total).'</td>
					<td class="r">'.($new > 0 ? '<b>'.game::format_number($new).'</b> ny'.($new == 1 ? '' : 'e') : '<span style="color: #AAA">Ingen</span>').'</td>
				</tr>
			</tbody>
		</table>
		<p class="c"><a href="./?a=search">Søk i support &raquo;</a></p>
	</div>
</div>';
		
		// avvik fra boksen?
		if ($new != tasks::get("support"))
		{
			// sett cache
			self::update_tasks();
		}
	}
	
	/** Vis oversikt over egne henvendelser */
	protected static function show_own()
	{
		// hent besvarelser
		$pagei = new pagei(pagei::PER_PAGE, 10, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT su_id, su_category, su_title, su_time, su_solved
			FROM support, users_players
			WHERE up_id = su_up_id AND up_u_id = ".login::$user->id."
			ORDER BY su_solved, su_time DESC");
		
		if ($pagei->total > 0)
		{
			echo '
<div class="bg1_c small">
	<h2 class="bg1">Mine henvendelser<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<table class="table center tablem" width="100%">
			<thead>
				<tr>
					<th>Tittel</th>
					<th>Dato</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="./?a=show&amp;su_id='.$row['su_id'].'">'.(empty($row['su_title']) ? '<i>Mangler tittel</i>' : htmlspecialchars($row['su_title'])).'</a> ['.htmlspecialchars(self::$kategorier[$row['su_category']]['name']).']</td>
					<td class="c">'.ess::$b->date->get($row['su_time'])->format().'</td>
					<td class="c">'.($row['su_solved'] == 0 ? '<span style="color: #CCFF00">Under behandling</span>' : 'Avsluttet').'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
			
			echo '
	</div>
</div>';
		}
	}
	
	/** Vis oversikt over statistikk */
	protected static function show_stats()
	{
		// hent litt statistikk
		$result = ess::$b->db->query("SELECT COUNT(su_id) FROM support");
		$totalt = mysql_result($result, 0);
		
		// hent spillere med status..
		$result = ess::$b->db->query("SELECT up_id, up_name, up_access_level, up_last_online FROM users_players WHERE up_access_level > 1 ORDER BY up_name");
		
		$players = array();
		$last_online = array();
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['up_access_level'] == 4)
				$level = 3;
			elseif ($row['up_access_level'] == 6)
				$level = 3;
			elseif ($row['up_access_level'] == 8)
				$level = 7;
			else
				$level = $row['up_access_level'];
			$players[$level][] = $row;
			$last_online[$row['up_id']] = $row['up_last_online'];
		}
		
		// hent antall besvarelser per bruker
		$result = ess::$b->db->query("
			SELECT sum_up_id, COUNT(sum_id) num_sum, MAX(sum_time) max_sum_time, m.up_last_online, m.up_access_level
			FROM support
				JOIN support_messages ON sum_su_id = su_id
				JOIN users_players s ON s.up_id = su_up_id
				JOIN users_players m ON m.up_id = sum_up_id AND m.up_u_id != s.up_u_id
			GROUP BY sum_up_id");
		$reply_users = array();
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['max_sum_time'] < time() - 2592000 && ($row['up_access_level'] == 0 || $row['up_access_level'] == 1))
				continue;
			$reply_users[$row['sum_up_id']] = $row['num_sum'];
			$last_online[$row['sum_up_id']] = $row['up_last_online'];
		}
		
		// vis oversikt
		echo '
<table class="table center" style="width: 400px">
	<thead>
		<tr>
			<th colspan="3">Besvarte henvendelser</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td colspan="2">Antall henvendelser totalt</td>
			<td>'.game::format_number($totalt).' stk</td>
		</tr>
		<tr>
			<th colspan="3">Administratorer</th>
		</tr>'.self::show_stats_users($players, 7, $reply_users, $last_online).'
		<tr>
			<th colspan="3">Moderatorer</th>
		</tr>'.self::show_stats_users($players, 5, $reply_users, $last_online).'
		<tr>
			<th colspan="3">Forummoderatorer</th>
		</tr>'.self::show_stats_users($players, 3, $reply_users, $last_online);
		
		// noen andre folk?
		if (count($reply_users) > 0)
		{
			echo '
		<tr>
			<th colspan="3">Andre brukere</th>
		</tr>';
			
			$i = 0;
			foreach ($reply_users as $player => $ant)
			{
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td><user id="'.$player.'" /></td>
			<td class="r">'.game::format_number($ant).'</td>
			<td class="r">'.game::timespan($last_online[$player], game::TIME_ABS).'</td>
		</tr>';
			}
		}
		
		echo '
	</tbody>
</table>';
		
		// statistikk for de siste 30 ukene
		$uker = 30;
		$limit = 7 * $uker;
		$result = ess::$b->db->query("
			SELECT num_su, num_sum, date_sum
			FROM
				(SELECT COUNT(sum_id) num_sum, DATE(FROM_UNIXTIME(sum_time)) date_sum
					FROM support_messages
					GROUP BY DATE(FROM_UNIXTIME(sum_time))
					ORDER BY date_sum DESC
					LIMIT $limit) ref_sum
				LEFT JOIN
				(SELECT COUNT(su_id) num_su, DATE(FROM_UNIXTIME(su_time)) date_su
					FROM support
					GROUP BY DATE(FROM_UNIXTIME(su_time))
					ORDER BY date_su DESC
					LIMIT $limit) ref_su ON date_sum = date_su
			ORDER BY date_sum DESC
			LIMIT $limit");
		
		$data = array();
		$d = ess::$b->date->get();
		for ($i = 0; $i < $uker; $i++)
		{
			$w = $d->format("o-W");
			$data['labels'][$w] = $w;
			$data['sum'][$w] = 0;
			$data['su'][$w] = 0;
			$d->modify("-1 week");
		}
		
		while ($row = mysql_fetch_assoc($result))
		{
			$w = ess::$b->date->parse($row['date_sum'])->format("o-W");
			if (!isset($data['labels'][$w])) continue;
			
			$data['sum'][$w] += (int) $row['num_sum'];
			$data['su'][$w] += (int) $row['num_su'];
		}
		
		// reverser så nyeste kommer sist
		$data['labels'] = array_reverse($data['labels']);
		$data['sum'] = array_reverse($data['sum']);
		$data['su'] = array_reverse($data['su']);
		
		$ofc = new OFC();
		
		$bar = new OFC_Charts_Area();
		$bar->text("Antall meldinger");
		$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# meldinger");
		$bar->values(array_values($data['sum']));
		$bar->colour(OFC_Colours::$colours[1]);
		$ofc->add_element($bar);
		
		$bar = new OFC_Charts_Area();
		$bar->text("Antall henvendelser");
		$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# henvendelser");
		$bar->values(array_values($data['su']));
		$bar->colour(OFC_Colours::$colours[0]);
		$ofc->add_element($bar);
		
		$ofc->axis_x()->label()->steps(2)->rotate(330)->labels(array_values($data['labels']));
		$ofc->axis_y()->set_numbers(0, max(max($data['sum']), max($data['su'])));
		
		$ofc->dark_colors();
		
		ess::$b->page->add_js('
function open_flash_chart_data()
{
	return '.js_encode((string) $ofc).';
}');
		
		ess::$b->page->add_js_file(LIB_HTTP.'/swfobject/swfobject.js');
		ess::$b->page->add_js_domready('swfobject.embedSWF("'.LIB_HTTP.'/ofc/open-flash-chart.swf", "stats_support", "100%", 250, "9.0.0");');
		
		echo '
<div class="bg1_c small">
	<h2 class="bg1">Statistikk over henvendelser til support<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1" style="padding: 10px">
		<div id="stats_support"></div>
	</div>
</div>';
	}
	
	protected static function show_stats_users($players, $id, &$reply_users, $last_online)
	{
		if (!isset($players[$id]))
		{
			return '
						<tr>
							<td colspan="3">Ingen brukere.</td>
						</tr>';
		}
		
		$i = 0;
		$ret = '';
		foreach ($players[$id] as $player)
		{
			$ant = array_key_exists($player['up_id'], $reply_users) ? $reply_users[$player['up_id']] : 0;
			unset($reply_users[$player['up_id']]);
			
			$ret .= '
						<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
							<td>'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</td>
							<td class="r">'.game::format_number($ant).'</td>
							<td class="r">'.game::timespan($last_online[$player['up_id']], game::TIME_ABS).'</td>
						</tr>';
		}
		
		return $ret;
	}
	
	/** Oppdater cache */
	public static function update_tasks()
	{
		tasks::set("support", mysql_result(ess::$b->db->query("SELECT COUNT(su_id) FROM support WHERE su_solved = 0"), 0));
	}
}

/** En bestemt henvendelse */
class support_henvendelse
{
	/** Data */
	public $data;
	
	/** Egen henvendelse? */
	public $own;
	
	/** Løst? */
	public $solved;
	
	/**
	 * Params
	 * @var params_update
	 */
	public $params;
	
	/** Opprette */
	protected function __construct($su_id)
	{
		$su_id = (int) $su_id;
		
		// hent informasjon
		$result = ess::$b->db->query("
			SELECT
				su_id, su_up_id, org.up_u_id, su_category, su_title, su_time, su_solved, su_views_solved, su_views_nosolved, su_views_crew_solved, su_views_crew_nosolved, su_params,
				new.up_id AS new_up_id
			FROM support
				JOIN users_players org ON su_up_id = org.up_id
				JOIN users ON u_id = org.up_u_id
				JOIN users_players new ON new.up_id = u_active_up_id
			WHERE su_id = $su_id");
		
		$this->data = mysql_fetch_assoc($result);
		if (!$this->data) return;
		
		$this->own = login::$logged_in && $this->data['up_u_id'] == login::$user->id;
		$this->solved = $this->data['su_solved'] != 0;
		
		$this->params = new params_update($this->data['su_params'], "support", "su_params", "su_id = {$this->data['su_id']}");
	}
	
	/**
	 * Test for oppretting
	 * @param int $su_id
	 * @return support_henvendelse
	 */
	public static function get($su_id)
	{
		$su = new support_henvendelse($su_id);
		if (!$su->data) return NULL;
		return $su;
	}
	
	/**
	 * Har vi tilgang til denne henvendelsen?
	 */
	public function has_access()
	{
		$ea = defined("SCRIPT_AJAX") ? NULL : "login";
		if (!$this->own && !access::has("crewet", NULL, NULL, $ea)) return false;
		return true;
	}
	
	/** Behandle visning */
	public function init()
	{
		ess::$b->page->add_title("Henvendelse", $this->data['su_title']);
		
		// avslutte henvendelsen uten å svare?
		if (isset($_POST['close']))
		{
			$this->close();
		}
		
		// legge til ny melding?
		if (isset($_POST['text']))
		{
			$this->handle_reply();
		}
		
		// vis henvendelsen
		$this->show();
	}
	
	/** Lukke henvendelsen uten å svare */
	protected function close()
	{
		if (!$this->solved)
		{
			// oppdater status
			ess::$b->db->query("UPDATE support SET su_solved = 1 WHERE su_id = {$this->data['su_id']}");
			
			// send varsel
			if (!$this->own)
			{
				global $_game;
				player::add_log_static("support", "c:".login::$user->player->id.":".$this->data['su_title'], $this->data['su_id'], $this->data['new_up_id']);
			}
			
			// fiks antall nye henvendelser
			support::update_tasks();
			
			// gi beskjed at henvendelsen har blitt lukket
			ess::$b->page->add_message("Henvendelsen har blitt avsluttet.");
		}
		
		redirect::handle("?a=show&su_id={$this->data['su_id']}");
	}
	
	/** Forsøk å legg til ny melding i henvendelsen */
	protected function handle_reply()
	{
		// sjekk for blokkering
		$blokkering = blokkeringer::check(blokkeringer::TYPE_SUPPORT);
		if ($blokkering)
		{
			ess::$b->page->add_message("Du er blokkert fra å sende inn henvendelser til support. Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
			return;
		}
		
		$text = trim(postval("text"));
		
		// tom tekst?
		if (empty($text))
		{
			ess::$b->page->add_message("Du må fylle inn en melding.", "error");
			return;
		}
		
		// sjekk for ventetid
		if (!access::has("crewet"))
		{
			// hvor lenge er det siden forrige melding ble lagt til?
			$result = ess::$b->db->query("
				SELECT sum_time FROM support_messages, users_players
				WHERE up_u_id = ".login::$user->id." AND sum_up_id = up_id
				ORDER BY sum_id DESC LIMIT 1");
			
			if (mysql_num_rows($result) > 0)
			{
				$last = mysql_result($result, 0);
				$wait = max(0, $last + support::$ventetid_reply - time());
				
				if ($wait > 0)
				{
					ess::$b->page->add_message('Du må vente '.game::counter($wait).' før du kan legge til nytt svar.', "error");
					return;
				}
			}
		}
		
		// sjekk om det har blitt lagt til noen nye meldinger siden vi viste siden
		$result = ess::$b->db->query("SELECT sum_id FROM support_messages WHERE sum_su_id = {$this->data['su_id']} ORDER BY sum_time DESC LIMIT 1");
		$last_sum = mysql_num_rows($result) > 0 ? mysql_result($result, 0) : 0;
		if (!isset($_POST['last_sum']) || $_POST['last_sum'] != $last_sum)
		{
			ess::$b->page->add_message("Nytt svar har blitt lagt til siden du viste siden sist. Trykk legg til melding på nytt for å fortsette.", "error");
			return;
		}
		
		// legg til meldingen
		ess::$b->db->query("INSERT INTO support_messages SET sum_su_id = {$this->data['su_id']}, sum_up_id = ".login::$user->player->id.", sum_time = ".time().", sum_text = ".ess::$b->db->quote($text));
		
		// endre status?
		if ($this->own) $su_solved = 0;
		elseif (isset($_POST['solve'])) $su_solved = 1;
		else $su_solved = 0;
		
		ess::$b->db->query("UPDATE support SET su_solved = $su_solved WHERE su_id = {$this->data['su_id']}");
		
		// sende logg til spilleren som henvendelsen tilhører?
		if (!$this->own)
		{
			global $_game;
			player::add_log_static("support", login::$user->player->id.":".$this->data['su_title'], $this->data['su_id'], $this->data['new_up_id']);
		}
		
		// fiks antall nye henvendelser
		support::update_tasks();
		
		// fjern fra status
		if (!$this->own) $this->status_remove();
		
		if ($this->own) putlog("CREWCHAN", "%c11%bSUPPORT HENVENDELSE OPPDATERT%b%c: %u".login::$user->player->data['up_name']."%u la til nytt svar i %u{$this->data['su_title']}%u ".ess::$s['spath']."/support/?a=show&su_id={$this->data['su_id']}");
		
		ess::$b->page->add_message("Meldingen ble lagt til.");
		redirect::handle("?a=show&su_id={$this->data['su_id']}");
	}
	
	/** Vis henvendelsen */
	protected function show()
	{
		// øk visningstelleren
		$this->increase_view_counter();
		
		echo '
<h1>Henvendelse</h1>
<p class="c"><a href="./">Tilbake</a></p>
<table class="table center tablemb">
	<tbody>
		<tr>
			<th>Tittel</th>
			<td>'.htmlspecialchars($this->data['su_title']).'</td>
		</tr>
		<tr>
			<th>Kategori</th>
			<td>'.htmlspecialchars(support::$kategorier[$this->data['su_category']]['name']).'</td>
		</tr>'.(!$this->own ? '
		<tr>
			<th>Innsender</th>
			<td><user id="'.$this->data['su_up_id'].'" />'.($this->data['new_up_id'] != $this->data['su_up_id'] ? '<br />
				(Ny: <user id="'.$this->data['new_up_id'].'" />)' : '').'</td>
		</tr>' : '').'
		<tr>
			<th>Innsendt</th>
			<td>'.ess::$b->date->get($this->data['su_time'])->format(date::FORMAT_SEC).'<br />'.game::timespan($this->data['su_time'], game::TIME_ABS | game::TIME_PAST).'</td>
		</tr>
		<tr>
			<th>Status</th>';
		
		if ($this->solved)
		{
			echo '
			<td>Avsluttet</td>';
		}
		else
		{
			// knapp for å avslutte henvendelsen
			echo '
			<td>
				Åpen / under behandling<br />
				<form action="" method="post">'.show_sbutton("Avslutt henvendelsen", 'name="close" style="margin-top: 3px"').'</form>
			</td>';
		}
		
		echo '
		</tr>
	</tbody>
</table>';
		
		// vis skjema for å svare på henvendelsen
		$this->show_reply_form();
		
		// hent meldingene
		$pagei = new pagei(pagei::PER_PAGE, 30, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT sum_up_id, sum_time, sum_text
			FROM support_messages
			WHERE sum_su_id = {$this->data['su_id']}
			ORDER BY sum_time DESC");
		
		if ($pagei->pages > 1)
		{
			echo '
<p class="c">'.$pagei->pagenumbers().'</p>';
		}
		
		ess::$b->page->add_css('.profile_link_imgfix img { vertical-align: top; margin-top: -2px }');
		while ($row = mysql_fetch_assoc($result))
		{
			echo '
<div class="bg1_c" style="width: 500px">
	<h2 class="bg1">
		<span style="float: left" class="profile_link_imgfix"><user id="'.$row['sum_up_id'].'" /></span>
		<span style="float: right; font-size: 10px">'.ess::$b->date->get($row['sum_time'])->format(date::FORMAT_SEC).'</span>
		<span class="left2"></span><span class="right2"></span>
	</h2>
	<div class="bg1">
		<div class="p">'.game::format_data($row['sum_text']).'</div>
	</div>
</div>';
		}
		
		if ($pagei->pages > 1)
		{
			echo '
<p class="c">'.$pagei->pagenumbers().'</p>';
		}
		
		echo '
<div style="margin-bottom: 50px"></div>';
	}
	
	/** Øke visningstelleren */
	protected function increase_view_counter()
	{
		// hvilket felt skal oppdateres?
		$f = $this->own
			? ($this->solved
				? 'su_views_solved'
				: 'su_views_nosolved')
			: ($this->solved
				? 'su_views_crew_solved'
				: 'su_views_crew_nosolved');
		
		// oppdater telleren
		ess::$b->db->query("UPDATE support SET $f = $f + 1 WHERE su_id = {$this->data['su_id']}");
	}
	
	/** Vise skjema for å legge til ny melding */
	protected function show_reply_form()
	{
		global $__server;
		
		if ($this->own && $this->solved)
		{
			echo '
<p class="center" style="width: 300px">Du kan legge til ny melding i denne henvendelsen hvis det er mer knyttet til henvendelsen du ønsker svar på.</p>';
		}
		
		// finn ID til siste melding
		$result = ess::$b->db->query("SELECT sum_id FROM support_messages WHERE sum_su_id = {$this->data['su_id']} ORDER BY sum_time DESC LIMIT 1");
		$last_sum = mysql_num_rows($result) > 0 ? mysql_result($result, 0) : 0;
		
		// vis skjema for å legge til ny melding
		ess::$b->page->add_css('
#support_reply_header { cursor: pointer }
#support_reply_preview h2 { cursor: pointer; color: #CCFF00 }
#support_reply_status {
	width: 500px;
	margin: 18px auto;
}');
		
		ess::$b->page->add_js_file($__server['relative_path'].'/js/support.js');
		ess::$b->page->add_js_domready('
	new SupportHenvendelse('.$this->data['su_id'].', '.js_encode($this->own).');');
		
		echo '
<div class="bg1_c" style="width: 500px">
	<h2 class="bg1" id="support_reply_header">Opprett ny melding i henvendelsen<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1'.($_SERVER['REQUEST_METHOD'] != "POST" ? ' hide' : '').'" id="support_reply_container">
		<form action="" method="post">
			<input type="hidden" name="last_sum" value="'.$last_sum.'" />
			<p>Tekst:</p>
			<p><textarea name="text" rows="10" cols="30" id="support_reply_text" style="width: 97%">'.htmlspecialchars(postval("text")).'</textarea></p>'.(!$this->own ? '
			<p><input type="checkbox" name="solve"'.($_SERVER['REQUEST_METHOD'] != "POST" || isset($_POST['solve']) ? ' checked="checked"' : '').' id="support_solve" /><label for="support_solve"> Marker henvendelsen som avsluttet (spørsmål besvart/problem løst)</label></p>' : '').'
			<p class="c">'.show_sbutton("Legg til melding").' <a class="button" id="support_reply_preview_button">Forhåndsvis</a></p>
		</form>
	</div>
</div>'.($this->own ? '' : '
<div id="support_reply_status">'.$this->status().'</div>').'
<div class="bg1_c hide" id="support_reply_preview" style="width: 500px">
	<h2 class="bg1">Forhåndsvisning<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<div class="p" id="support_reply_preview_view">Forhåndsvisning..</div>
	</div>
</div>';
	}
	
	/** Vis status for henvendelsen (ajax) */
	public function status_ajax()
	{
		$this->params->lock();
		$status = unserialize($this->params->get("repliers"));
		
		// oppdater oppføringen for denne spilleren
		$expire = time() - 300;
		if (isset($status[login::$user->player->id]) && $status[login::$user->player->id]['last'] >= $expire)
		{
			$status[login::$user->player->id]['last'] = time();
		}
		else
		{
			$status[login::$user->player->id] = array(
				"first" => time(),
				"last" => time()
			);
		}
		
		// lagre liste
		$this->params->update("repliers", serialize($status), true);
		
		// sett opp liste over spillere som har begynt å svare
		$list = array();
		$expire = time() - 180; // vis de som har blitt oppdatert innen 3 min
		foreach ($status as $up_id => $data)
		{
			if ($data['last'] < $expire) continue;
			$list[$up_id] = $data['last'];
		}
		arsort($list);
		
		// vis liste over spillere
		$ul = array();
		foreach ($list as $up_id => $last)
		{
			$ul[] = '<li><user id="'.$up_id.'" /> åpnet svarskjemaet '.ess::$b->date->get($status[$up_id]['first'])->format(date::FORMAT_SEC).' ('.game::timespan($status[$up_id]['first'], game::TIME_ABS | game::TIME_PAST | game::TIME_FULL).') -- oppdatert '.game::timespan($last, game::TIME_ABS | game::TIME_PAST | game::TIME_FULL).'</p>';
		}
		
		ajax::html(parse_html('
<ul>
	'.implode('
	', $ul).'
</ul>'));
	}
	
	/** Vis status for henvendelsen */
	public function status()
	{
		$status = unserialize($this->params->get("repliers"));
		if (!is_array($status)) $status = array();
		
		// sett opp liste over spillere som har begynt å svare
		$list = array();
		$expire = time() - 180; // vis de som har blitt oppdatert innen 3 min
		foreach ($status as $up_id => $data)
		{
			if ($data['last'] < $expire) continue;
			$list[$up_id] = $data['last'];
		}
		arsort($list);
		
		// vis liste over spillere
		$ul = array();
		foreach ($list as $up_id => $last)
		{
			$ul[] = '<li><user id="'.$up_id.'" /> åpnet svarskjemaet '.ess::$b->date->get($status[$up_id]['first'])->format(date::FORMAT_SEC).' ('.game::timespan($status[$up_id]['first'], game::TIME_ABS | game::TIME_PAST | game::TIME_FULL).') -- oppdatert '.game::timespan($last, game::TIME_ABS | game::TIME_PAST | game::TIME_FULL).'</p>';
		}
		
		if (count($ul) == 0) return '';
		
		return '
<ul>
	'.implode('
	', $ul).'
</ul>';
	}
	
	/** Fjern fra status */
	public function status_remove()
	{
		$this->params->lock();
		$status = unserialize($this->params->get("repliers"));
		
		// oppdater oppføringen for denne spilleren
		if (isset($status[login::$user->player->id]))
		{
			unset($status[login::$user->player->id]);
		}
		
		// lagre
		if (is_array($status) && count($status) == 0)
		{
			$this->params->remove("repliers", true);
		}
		else
		{
			$this->params->update("repliers", serialize($status), true);
		}
	}
}