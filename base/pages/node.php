<?php

class page_node
{
	public static function main()
	{
		// sett opp path enheter
		$path = isset($_SERVER['REDIR_URL']) ? $_SERVER['REDIR_URL'] : "";
		if (mb_substr($path, 0, 1) == "/") $path = mb_substr($path, 1);
		$path = explode("/", $path);
		
		$node_id = null;
		if (!isset($path[1]))
		{
			// hovedsiden
			if (nodes::$default_node)
			{
				$node_id = nodes::$default_node;
			}
		}
		
		else
		{
			// sjekk node og om den er gyldig
			array_shift($path);
			$node_id = $path[0];
			if (preg_match("/(^0|[^0-9])/u", $node_id))
			{
				// admin?
				if ($node_id == "a")
				{
					page_node_admin::main();
					return;
				}
				
				// sidekart?
				elseif ($node_id == "sitemap")
				{
					self::sitemap();
					return;
				}
				
				// vise alle nodene?
				elseif ($node_id == "all")
				{
					self::all_nodes();
					return;
				}
				
				// søke?
				elseif ($node_id == "search")
				{
					self::search();
					return;
				}
				
				page_not_found();
			}
			
			// hoved noden?
			if ($node_id == nodes::$default_node)
			{
				redirect::handle("node", redirect::REDIRECT_ROOT);
			}
		}
		
		// har vi ikke node?
		if (!$node_id)
		{
			page_node::load_page();
			#page_not_found();
		}
		
		// hent info
		nodes::load_node($node_id);
			
		// vis node
		nodes::parse_node();
		
		page_node::load_page();
	}
	
	/**
	 * Sidekart
	 */
	protected static function sitemap()
	{
		ess::$b->page->add_title("Sidekart");
		nodes::add_node(0, "Sidekart", ess::$s['relative_path']."/node/sitemap");
		
		echo '
<h1>Sidekart</h1>
<p>På denne siden finner du en overskt over alle sidene du finner ved å bla deg rundt i menyen på disse sidene.</p>';
		
		// sett opp riktige referanser og lag tree
		$sub = array();
		foreach (nodes::$nodes as $row)
		{
			if ($row['node_enabled'] != 0)
			{
				$sub[$row['node_parent_node_id']][] = $row['node_id'];
			}
		}
		$tree = new tree($sub);
		$data = $tree->generate(0, NULL, nodes::$nodes);
		
		if (count($data) == 0)
		{
			echo '
<p>Ingen sider er opprettet.</p>';
		}
		
		else
		{
			echo '
<ul>
	<li>';
			
			$number = 1;
			$first = true;
			foreach ($data as $row)
			{
				if ($row['number'] > $number)
				{
					echo '
	<ul>
	<li>';
					
					$first = true;
				}
				elseif ($row['number'] < $number)
				{
					for (; $row['number'] < $number; $number--)
					{
						echo '
	</li>
	</ul>';
					}
					
					echo '
	</li>
	<li>';
					
					$first = false;
				}
				elseif (!$first)
				{
					echo '
	</li>
	<li>';
				}
				
				// innholdet
				switch ($row['data']['node_type'])
				{
					case "url_absolute":
						$params = new params($row['data']['node_params']);
						$row_prefix = '<a href="'.htmlspecialchars($params->get("url")).'"'.($params->get("new_window") ? ' target="_blank"' : '').'>';
						$row_suffix = '</a>';
					break;
		
					case "url_relative":
						$params = new params($row['data']['node_params']);
						$row_prefix = '<a href="'.ess::$s['relative_path'].htmlspecialchars($params->get("url")).'"'.($params->get("new_window") ? ' target="_blank"' : '').'>';
						$row_suffix = '</a>';
					break;
		
					default: // container
						if ($row['data']['node_id'] == nodes::$default_node)
						{
							$url = ess::$s['relative_path'].'/node';
						}
						else
						{
							$url = ess::$s['relative_path'].'/node/'.$row['data']['node_id'];
						}
						$row_prefix = '<a href="'.$url.'">';
						$row_suffix = '</a>';
				}
				
				$content = $row_prefix . htmlspecialchars($row['data']['node_title']) . $row_suffix;
				
				echo $content;
				
				$first = false;
				$number = $row['number'];
			}
			
			for (; $number >= 1; $number--)
			{
				echo '
	</li>
	</ul>';
			}
		}
		
		self::load_page();
	}
	
	/**
	 * Vis alle nodene samtidig
	 */
	protected static function all_nodes()
	{
		ess::$b->page->add_title("Innhold fra alle sidene");
		nodes::add_node(0, "Alt innhold", ess::$s['relative_path']."/node/all");
		
		// hindre søkemotorer i å indeksere denne siden
		ess::$b->page->add_head('<meta name="robots" content="noindex" />');
		
		echo '
<h1>Innhold fra alle sidene</h1>
<p>Denne siden viser innholdet til alt som er synlig på siden.</p>';
		
		ess::$b->page->add_css('
.nodes_all_node {
	margin: 20px 0;
	padding: 20px 0;
	border-top: 2px solid #666666;
}');
		
		// hent all informasjon
		$result = ess::$b->db->query("SELECT node_id, node_parent_node_id, node_title, node_type, node_params, node_show_menu, node_expand_menu, node_enabled, node_priority, node_change FROM nodes");
		$nodes = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['params'] = new params($row['node_params']);
			$row['enheter'] = array();
			$nodes[$row['node_id']] = $row;
		}
		
		// hent alle enhetene
		$result = ess::$b->db->query("SELECT ni_id, ni_node_id, ni_type, nir_content, nir_params, nir_time FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_enabled != 0 AND ni_deleted = 0 ORDER BY ni_priority");
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($nodes[$row['ni_node_id']])) continue;
			$nodes[$row['ni_node_id']]['enheter'][] = nodes::content_build($row);
		}
		
		// sett opp riktige referanser og lag tree
		$sub = array();
		foreach (nodes::$nodes as $row)
		{
			if ($row['node_enabled'] != 0)
			{
				$sub[$row['node_parent_node_id']][] = $row['node_id'];
			}
		}
		$tree = new tree($sub);
		$data = $tree->generate(0, NULL, $nodes);
		
		if (count($data) == 0)
		{
			echo '
<p>Ingen sider er opprettet.</p>';
		}
		
		else
		{
			$path = array();
			
			$number = 1;
			foreach ($data as $row)
			{
				for (; $row['number'] <= $number; $number--)
				{
					// fjern fra path
					array_pop($path);
				}
				
				if ($row['number'] >= $number)
				{
					// legg til i path
					switch ($row['data']['node_type'])
					{
						case "url_absolute":
							$path[] = '<a href="'.htmlspecialchars($row['data']['params']->get("url")).'"'.($row['data']['params']->get("new_window") ? ' target="_blank"' : '').'>'.htmlspecialchars($row['data']['node_title']).'</a>';
						break;
						
						case "url_relative":
							$path[] = '<a href="'.ess::$s['relative_path'].htmlspecialchars($row['data']['params']->get("url")).'"'.($row['data']['params']->get("new_window") ? ' target="_blank"' : '').'>'.htmlspecialchars($row['data']['node_title']).'</a>';
						break;
						
						default:
							if ($row['data']['node_id'] == nodes::$default_node)
							{
								$url = ess::$s['relative_path'].'/node';
							}
							else
							{
								$url = ess::$s['relative_path'].'/node/'.$row['data']['node_id'];
							}
							
							$path[] = '<a href="'.htmlspecialchars($url).'">'.htmlspecialchars($row['data']['node_title']).'</a>';
					}
				}
				
				echo '
<div class="nodes_all_node">
	<h1>'.htmlspecialchars($row['data']['node_title']).'</h1>
	<p class="nodes_all_path">'.implode(" &raquo; ", $path).'</p>';
				
				// innholdet
				switch ($row['data']['node_type'])
				{
					case "url_absolute":
						echo '
	<p>Lenke: <a href="'.htmlspecialchars($row['data']['params']->get("url")).'"'.($row['data']['params']->get("new_window") ? ' target="_blank"' : '').'>'.htmlspecialchars($row['data']['node_title']).'</a></p>';
					break;
					
					case "url_relative":
						echo '
	<p>Lenke: <a href="'.ess::$s['relative_path'].htmlspecialchars($row['data']['params']->get("url")).'"'.($row['data']['params']->get("new_window") ? ' target="_blank"' : '').'>'.htmlspecialchars($row['data']['node_title']).'</a></p>';
					break;
					
					default: // container
						echo implode('
', $row['data']['enheter']);
						
						if (!$row['data']['params']->get("hide_time_change"))
						{
							echo '
<p align="right" style="color:#AAAAAA;font-size:10px">
	Sist endret '.ess::$b->date->get($row['data']['node_change'])->format().'
</p>';
						}
				}
				
				// linker
				if (access::has("crewet"))
				{
					echo '
<p style="color:#AAA;text-align:right;font-size:10px">[<a href="'.ess::$s['relative_path'].'/node/a?node_id='.$row['data']['node_id'].'">rediger side</a>]</p>';
				}
				
				echo '
</div>';
				
				$number = $row['number'];
			}
		}
		
		self::load_page();
	}
	
	/**
	 * Søke i alle nodene
	 */
	protected static function search()
	{
		ess::$b->page->add_title("Søk");
		nodes::add_node(0, "Søk", ess::$s['relative_path']."/node/search");
		
		// hindre søkemotorer i å indeksere denne siden om man har søkt etter noe
		if (isset($_GET['q'])) ess::$b->page->add_head('<meta name="robots" content="noindex" />');
		
		echo '
<h1>Søk</h1>
<p>Denne siden lar deg søke gjennom alt innholdet som er synlig i hjelpesidene.</p>';
		
		// hent all informasjon
		$result = ess::$b->db->query("SELECT node_id, node_parent_node_id, node_title, node_type, node_params, node_show_menu, node_expand_menu, node_enabled, node_priority, node_change FROM nodes");
		$nodes = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['params'] = new params($row['node_params']);
			$row['enheter'] = array();
			$row['plain'] = "";
			$nodes[$row['node_id']] = $row;
		}
		
		if (count($nodes) == 0)
		{
			echo '
<p>Ingen sider er opprettet som det er mulig å søke gjennom.</p>';
		}
		
		else
		{
			// skal vi søke?
			$search = null;
			if (isset($_GET['q']))
			{
				$search = trim($_GET['q']);
				if ($search == "")
				{
					$search = false;
				}
			}
			
			// vise søkeboks
			ess::$b->page->add_js_domready('$("searchq").focus();');
			echo '
<div style="background-color: #222; padding: 1px 10px; margin: 1em 0">
	<form action="" method="get">
		<p class="c">
			<strong>Søkestreng</strong>:
			<input type="text" class="styled w200" name="q" id="searchq" value="'.htmlspecialchars(getval("q")).'" />
			'.show_sbutton("Utfør søk").'
			<a href="&rpath;/node/61">Hjelp</a>
		</p>'.($search === false ? '
		<p class="c">Du må fylle ut et søkekriterie.</p>' : '').'
	</form>
</div>';
			
			// søke?
			if (is_string($search))
			{
				// sett opp søkekriteriene
				$search_list = search_query($search);
				$search_list = $search_list[1];
				$search_list2 = $search_list; // for delvise treff
				
				foreach ($search_list as &$q)
				{
					$q = '/(\\P{L}|^)'.preg_replace(array('/([\\/\\\\\\[\\]()$.+?|{}])/u', '/\\*\\*+/u', '/\\*/u'), array('\\\\$1', '*', '\\S*'), $q).'(\\P{L}|$)/i';
				}
				
				// sett opp søkeliste hvor vi søker med * på slutten av ordene
				foreach ($search_list2 as &$q)
				{
					$q = '/'.preg_replace(array('/([\\/\\\\\\[\\]()$.+?|{}])/u', '/\\*\\*+/u', '/\\*/u'), array('\\\\$1', '*', '\\S*'), $q).'/i';
				}
				
				// gå over alle sidene og finn treff
				self::search_handle($search_list, $search_list2, $nodes);
			}
		}
		
		self::load_page();
	}
	
	/**
	 * Utfør et søk
	 */
	protected static function search_handle($search_list, $search_list2, $nodes)
	{
		// hent alle enhetene
		$result = ess::$b->db->query("SELECT ni_id, ni_node_id, ni_type, nir_content, nir_params, nir_time FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_enabled != 0 AND ni_deleted = 0 ORDER BY ni_priority");
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($nodes[$row['ni_node_id']])) continue;
			
			$data = nodes::content_build($row);
			$nodes[$row['ni_node_id']]['enheter'][] = $data;
			
			// bygg opp plain tekst
			$plain = preg_replace("/<br[^\\/>]*\\/?>/u", "\n", $data);
			$plain = preg_replace("/(<\\/?(h[1-6]|p)[^>]*>)/u", "\n\\1", $plain);
			$plain = html_entity_decode(strip_tags($plain));
			$plain = preg_replace("/(^ +| +$|\\r)/mu", "", $plain);
			#$plain = preg_replace("/(?<![!,.\\n ])\\n/u", " ", $plain);
			$plain = preg_replace("/\\n/u", " ", $plain);
			$plain = preg_replace("/  +/u", " ", $plain);
			$plain = trim($plain);
			$nodes[$row['ni_node_id']]['plain'] .= $plain . " ";
		}
		
		// sett opp riktige referanser og lag tree
		$sub = array();
		foreach (nodes::$nodes as $row)
		{
			if ($row['node_enabled'] != 0)
			{
				$sub[$row['node_parent_node_id']][] = $row['node_id'];
			}
		}
		$tree = new tree($sub);
		$data = $tree->generate(0, NULL, $nodes);
		
		// sett opp paths
		$paths = array();
		$path = array();
		$number = 1;
		foreach ($data as $row)
		{
			for (; $row['number'] <= $number; $number--)
			{
				// fjern fra path
				array_pop($path);
			}
			
			if ($row['number'] >= $number)
			{
				// legg til i path
				switch ($row['data']['node_type'])
				{
					case "url_absolute":
						$path[] = '<a href="'.htmlspecialchars($row['data']['params']->get("url")).'"'.($row['data']['params']->get("new_window") ? ' target="_blank"' : '').'>'.htmlspecialchars($row['data']['node_title']).'</a>';
					break;
					
					case "url_relative":
						$path[] = '<a href="'.ess::$s['relative_path'].htmlspecialchars($row['data']['params']->get("url")).'"'.($row['data']['params']->get("new_window") ? ' target="_blank"' : '').'>'.htmlspecialchars($row['data']['node_title']).'</a>';
					break;
					
					default:
						if ($row['data']['node_id'] == nodes::$default_node)
						{
							$url = ess::$s['relative_path'].'/node';
						}
						else
						{
							$url = ess::$s['relative_path'].'/node/'.$row['data']['node_id'];
						}
						
						$path[] = '<a href="'.htmlspecialchars($url).'">'.htmlspecialchars($row['data']['node_title']).'</a>';
				}
			}
			
			$paths[$row['data']['node_id']] = $path;
			$number = $row['number'];
		}
		
		// sett opp søkeresultater
		$result = array();
		$points = array();
		$points2 = array();
		
		foreach ($data as $row)
		{
			if ($row['data']['node_type'] != "container") continue;
			
			// utfør søk
			$found = true;
			$p = 0;
			$p2 = 0;
			foreach ($search_list as $key => $regex)
			{
				$ok = false;
				$matches = null;
				
				// søk i teksten
				if (preg_match_all($regex, $row['data']['plain'], $matches))
				{
					$ok = true;
					$p += count($matches[0]);
				}
				
				if (preg_match_all($search_list2[$key], $row['data']['plain'], $matches))
				{
					$ok = true;
					$p2 += count($matches[0]);
				}
				
				// søk i tittelen
				if (preg_match_all($regex, $row['data']['node_title'], $matches))
				{
					$ok = true;
					$p += count($matches[0]);
				}
				if (preg_match_all($search_list2[$key], $row['data']['node_title'], $matches))
				{
					$ok = true;
					$p2 += count($matches[0]);
				}
				
				if ($ok) continue;
				$found = false;
				break;
			}
			
			// fant?
			if ($found)
			{
				$result[] = $row;
				$points[] = $p;
				$points2[] = $p2;
			}
		}
		
		// vis søkeresultater
		if (count($result) == 0)
		{
			echo '
<p style="font-weight: bold">Ingen treff ble funnet.</p>';
		}
		
		else
		{
			// sorter søkeresultatene
			array_multisort($points, SORT_DESC, SORT_NUMERIC, $points2, SORT_DESC, SORT_NUMERIC, $result);
			
			echo '
<h2>Søkeresultater</h2>';
			
			ess::$b->page->add_css('
.nodes_search_node {
	position: relative;
	background-color: #222;
	padding: 0 10px;
	overflow: hidden;
	margin: 10px 0;
}
.nodes_search_path {
	
}
.nodes_search_points {
	position: absolute;
	right: 5px;
	bottom: 5px;
	color: #AAA;
	margin: 0;
	font-size: 11px;
	text-align: right;
}');
			
			redirect::store("/node/search", redirect::ROOT);
			$pagei = new pagei(pagei::TOTAL, count($result), pagei::PER_PAGE, 15, pagei::ACTIVE_GET, "side");
			
			$result = array_slice($result, $pagei->start, $pagei->per_page, true);
			foreach ($result as $key => $row)
			{
				$partial = $points2[$key] - $points[$key];
				
				if ($row['data']['node_id'] == nodes::$default_node)
				{
					$url = ess::$s['relative_path'].'/node';
				}
				else
				{
					$url = ess::$s['relative_path'].'/node/'.$row['data']['node_id'];
				}
				
				echo '
<div class="nodes_search_node">
	<h3><a href="'.$url.'">'.htmlspecialchars($row['data']['node_title']).'</a></h3>
	<p class="nodes_search_path">'.implode(" &raquo; ", $paths[$row['data']['node_id']]).'</p>
	<p class="nodes_search_points">'.($points[$key] > 0 ? $points[$key].' treff' : '').($partial > 0 ? '<br />'.fwords("%d delvis treff", "%d delvise treff", $partial) : '').'</p>
</div>';
				
			}
			
			if ($pagei->pages > 1)
			{
				echo '
<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
	}
	
	/**
	 * Last inn side
	 */
	public static function load_page()
	{
		$content = @ob_get_contents();
		@ob_clean();
		
		echo '
	<div class="node_a_path">
		<ul>'.nodes::build_path('
			<li>', '</li>
			<li>&raquo;</li>
			<li>', '</li>').'
		</ul>
	</div>
	<div class="node_a_left">
		<div class="node_a_leftsep"></div>
		<div class="node_a_menu">'.nodes::build_menu(0, 0, '
			').'
			<form action="&rpath;/node/search" method="get">
				<p class="c">
					<input type="text" name="q" class="styled w80" style="width: 60%" />
					'.show_sbutton("Søk").'
				</p>
			</form>
		</div>
	</div>
	<div class="node_a_right">
		<div class="node_a_rightsep"></div>
		<div class="node_a_content"><boxes />'.$content.'</div>
	</div>
	<div style="clear: both"></div>';
		
		ess::$b->page->load();
	}
}

class page_node_admin
{
	public static function main()
	{
		// har vi ikke tilgang?
		if (!access::has("crewet", null, null, "login"))
		{
			redirect::handle("/node", redirect::ROOT);
		}
		
		ess::$b->page->add_title("Innholdsredigering");
		nodes::add_node(0, "Innholdsredigering", ess::$s['relative_path']."/node/a");
		
		if (isset($_POST['abort']) && !isset($_GET['node_id']))
		{
			ess::$b->page->add_message("Handlingen ble avbrutt.");
			redirect::handle();
		}
		
		// opprette ny node?
		if (isset($_GET['new_node']))
		{
			$parent_node = getval("parent_node", 0);
			$previous_node = getval("previous_node", 0);
			
			// kontroller parent node
			if ($parent_node != 0 && !isset(nodes::$nodes[$parent_node]))
			{
				ess::$b->page->add_message("Fant ikke forelder til elementet. Prøv på nytt.", "error");
				redirect::handle();
			}
			
			// kontroller previous node
			$siblings = isset(nodes::$nodes_sub[$parent_node]) ? nodes::$nodes_sub[$parent_node] : array();
			if ($previous_node != 0 && !in_array($previous_node, $siblings))
			{
				ess::$b->page->add_message("Fant ikke forrige side. Prøv på nytt.", "error");
				redirect::handle();
			}
			
			// finn priority
			if ($previous_node == 0)
			{
				$priority = 1;
				$priority_num = 1;
			}
			else
			{
				// hent priority til previous node
				$result = ess::$b->db->query("SELECT node_priority FROM nodes WHERE node_parent_node_id = {$parent_node} AND node_id = {$previous_node} AND node_deleted = 0");
				if (mysql_num_rows($result) == 0)
				{
					ess::$b->page->add_message("Noe gikk galt. Prøv igjen.", "error");
					redirect::handle();
				}
				$priority = mysql_result($result, 0);
				
				// hent priority til den vi skal "erstatte"
				$result = ess::$b->db->query("SELECT node_priority FROM nodes WHERE node_parent_node_id = {$parent_node} AND node_priority > $priority AND node_deleted = 0 ORDER BY node_priority LIMIT 1");
				if (mysql_num_rows($result) > 0)
				{
					$priority = mysql_result($result, 0);
				}
				else
				{
					$priority++;
				}
				
				// hent nummer
				$result = ess::$b->db->query("SELECT COUNT(node_id) FROM nodes WHERE node_parent_node_id = {$parent_node} AND node_priority < $priority AND node_deleted = 0");
				$priority_num = mysql_result($result, 0) + 1;
			}
			
			// legge til?
			if (isset($_POST['title']) && isset($_POST['type']))
			{
				// ok tittel?
				$title = trim(postval("title"));
				$type = postval("type");
				
				if (empty($title))
				{
					ess::$b->page->add_message("Du må fylle ut en tittel.", "error");
				}
				
				elseif (!isset(nodes::$types[$type]))
				{
					ess::$b->page->add_message("Ugyldig type. Prøv på nytt.", "error");
				}
				
				else
				{
					// sett opp prioritys
					ess::$b->db->query("UPDATE nodes SET node_priority = node_priority + 1 WHERE node_parent_node_id = {$parent_node} AND node_priority >= $priority");
					
					// legg til side
					ess::$b->db->query("INSERT INTO nodes SET node_parent_node_id = $parent_node, node_title = ".ess::$b->db->quote($title).", node_type = ".ess::$b->db->quote(mb_strtolower($type)).", node_priority = $priority, node_change = ".time());
					
					$iid = mysql_insert_id();
					ess::$b->page->add_message("Siden ble lagt til.");
					redirect::handle("node/a?node_id={$iid}", redirect::ROOT);
				}
			}
			
			$parent_title = isset(nodes::$nodes[$parent_node]) ? nodes::$nodes[$parent_node]['node_title'] : 'Toppnivå';
			
			echo '
<h1>Ny side</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Forelder</dt>
		<dd>'.htmlspecialchars($parent_title).'</dd>
		<dt>Plassering</dt>
		<dd>'.$priority_num.'</dd>
		<dt>Tittel</dt>
		<dd><input type="text" name="title" class="styled w100" value="'.htmlspecialchars(postval("title")).'" /></dd>
		<dt>Type</dt>
		<dd>
			<select name="type">';
			
			$selected = postval("type");
			if (!isset(nodes::$types[$selected])) $selected = false;
			
			foreach (nodes::$types as $key => $value)
			{
				echo '
				<option value="'.htmlspecialchars($key).'"'.($selected == $key ? ' selected="selected"' : '').'>'.htmlspecialchars($value).'</option>';
			}
			
			echo '
			</select>
		</dd>
	</dl>
	<p>'.show_sbutton("Opprett side").' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>';
			
			page_node::load_page();
		}
		
		$node = false;
		if (isset($_GET['node_id']))
		{
			$result = nodes::load_node($_GET['node_id'], false);
			
			if (!$result)
			{
				ess::$b->page->add_message("Fant ikke enheten.");
				redirect::handle();
			}
			
			$node = true;
			#ess::$b->page->add_title(nodes::$node_info['node_title']);
			redirect::store("node/a?node_id=".nodes::$node_id, redirect::ROOT);
			
			if (isset($_POST['abort']))
			{
				ess::$b->page->add_message("Handlingen ble avbrutt.");
				redirect::handle();
			}
			
			// flytt
			if (isset($_GET['move']))
			{
				// hent tree
				$root = array(0 => array(
					"number" => 0,
					"prefix" => "",
					"prefix_node" => "",
					"data" => array(
						"node_id" => 0,
						"node_parent_node_id" => 0,
						"node_title" => "Innhold (toppnivå)",
						"node_type" => NULL,
						"node_params" => NULL,
						"node_show_menu" => NULL,
						"node_expand_menu" => NULL,
						"node_enabled" => true,
						"node_priority" => 0
					)
				));
				
				$tree = new tree(nodes::$nodes_sub);
				$data = $tree->generate(0, $root, nodes::$nodes);
				
				// sett opp data og finn ut hvor ting kan plasseres
				$number_last = 1;
				$disabled = 0;
				
				$list = array(0 => 0);
				foreach ($data as &$row)
				{
					if ($disabled != 0 && $row['number'] <= $disabled) $disabled = 0;
					$number_last = $row['number'];
					
					$row['inside'] = $disabled == 0 && nodes::$node_id != $row['data']['node_id'];
					$row['under'] = $disabled == 0 && nodes::$node_id != $row['data']['node_id'];
					
					if (nodes::$node_id == $row['data']['node_id'])
					{
						if (isset($list[$row['number']])) $active = array("under", $list[$row['number']]);
						else $active = array("inside", $list[$row['number']-1]);
						
						$disabled = $row['number'];
					}
					
					$list[$row['number']] = $row['data']['node_id'];
				}
				unset($row);
				$data[0]['under'] = false;
				
				// lagre endringer?
				if (isset($_POST['destination_node_id']))
				{
					$match = preg_match("/^(under_)?(\\d+)$/u", postval("destination_node_id"), $matches);
					$type = $match && $matches[1] == "under_" ? "under" : "inside";
					$dest_node_id = $match ? $matches[2] : -1;
					$parent_node_id = $type == "inside" ? $dest_node_id : nodes::$nodes[$dest_node_id]['node_parent_node_id'];
					
					// finnes?
					if (!isset($data[$dest_node_id]))
					{
						ess::$b->page->add_message("Fant ikke målsiden.", "error");
					}
					
					// kan plasseres her?
					elseif (!$data[$dest_node_id][$type])
					{
						ess::$b->page->add_message("Du kan ikke plassere siden her.", "error");
					}
					
					// samme som nå?
					elseif ($type == $active[0] && $dest_node_id == $active[1])
					{
						ess::$b->page->add_message("Du må velge en ny plassering.", "error");
					}
					
					else
					{
						$new_trans = ess::$b->db->begin();
						
						// flytt de andre kategoriene
						ess::$b->db->query("UPDATE nodes SET node_priority = node_priority - 1 WHERE node_parent_node_id = ".nodes::$node_info['node_parent_node_id']." AND node_priority > ".nodes::$node_info['node_priority']);
						ess::$b->db->query("UPDATE nodes SET node_priority = node_priority + 1 WHERE node_parent_node_id = $parent_node_id".($type == "under" ? " AND node_priority > ".nodes::$nodes[$dest_node_id]['node_priority'] : ""));
						
						// flytt den valgte siden
						ess::$b->db->query("UPDATE nodes SET node_parent_node_id = $parent_node_id, node_priority = ".($type == "inside" ? 0 : nodes::$nodes[$dest_node_id]['node_priority'] + 1)." WHERE node_id = ".nodes::$node_id);
						
						if ($new_trans) ess::$b->db->commit();
						
						ess::$b->page->add_message("Siden ble flyttet.");
						redirect::handle();
					}
				}
				
				// sett opp plasseringen
				$items = array();
				$parent_node = nodes::$node_info['node_parent_node_id'];
				while (isset(nodes::$nodes[$parent_node]) && $item = nodes::$nodes[$parent_node])
				{
					$items[] = $item['node_title'];
					$parent_node = $item['node_parent_node_id'];
				}
				$items[] = "Toppnivå";
				
				// tittel
				ess::$b->page->add_title("Flytt side");
				nodes::add_node(0, "Flytt side", ess::$s['relative_path']."/node/a?node_id=".nodes::$node_id."&amp;move");
				
				echo '
<h1>'.htmlspecialchars(nodes::$node_info['node_title']).'</h1>
<p><a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'">Tilbake</a></p>

<h2>Flytt side</h2>
<form action="" method="post">
	<dl class="dl_2x dd_right">
		<dt>Nåværende plassering</dt>
		<dd>'.implode("\\", array_reverse($items)).'</dd>
		
		<dt>Ny plassering</dt>
		<dd>
			<table class="table" style="margin-left: auto">
				<thead>
					<tr>
						<th>Side</th>
						<th>Inni</th>
						<th>Nedenfor</th>
					</tr>
				</thead>
				<tbody class="c">';
				$i = 0;
				foreach ($data as $row)
				{
					$i++;
					$class = nodes::$node_id == $row['data']['node_id'] ? ' class="highlight"' : ($i % 2 == 0 ? ' class="color"' : '');
					$link = $row['data']['node_id'] == 0 ? $row['data']['node_title'] : '<a href="'.ess::$s['relative_path'].'/node/a?node_id='.$row['data']['node_id'].'">'.htmlspecialchars($row['data']['node_title']).'</a>';
					
					echo '
					<tr'.$class.'>
						<td class="l"><span class="plain">'.$row['prefix'].$row['prefix_node'].'</span> '.$link./*($row['gc']['gc_visible'] == 0 ? ' <span style="color:#FF0000">(skjult)</span>' : '').*/'</td>
						<td>'.($row['inside'] ? '<input type="radio" name="destination_node_id" value="'.$row['data']['node_id'].'"'.($active[0] == "inside" && $active[1] == $row['data']['node_id'] ? ' checked="checked"' : '') : ' x').'</td>
						<td>'.($row['under'] ? '<input type="radio" name="destination_node_id" value="under_'.$row['data']['node_id'].'"'.($active[0] == "under" && $active[1] == $row['data']['node_id'] ? ' checked="checked"' : '') : ' x').'</td>
					</tr>';
				}
				
				echo '
				</tbody>
			</table>
		</dd>
		
		<dt>&nbsp;</dt>
		<dd>'.show_sbutton("Lagre endringer").'</dd>
	</dl>
</form>';
				
				page_node::load_page();
			}
			
			// rediger tittel
			if (isset($_GET['edit_title']))
			{
				// lagre?
				if (isset($_POST['title']))
				{
					$title = postval('title');
					if (empty($title))
					{
						ess::$b->page->add_message("Du må skrive inn en tittel.", "error");
					}
					else
					{
						// oppdater
						ess::$b->db->query("UPDATE nodes SET node_title = ".ess::$b->db->quote($title)." WHERE node_id = ".nodes::$node_id);
						ess::$b->page->add_message("Tittelen ble endret fra &laquo;".htmlspecialchars(nodes::$node_info['node_title'])."&raquo; til &laquo;".htmlspecialchars($title)."&raquo;.");
						redirect::handle();
					}
				}
				
				echo '
<h1>Rediger tittel</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Nåværende tittel</dt>
		<dd>'.htmlspecialchars(nodes::$node_info['node_title']).'</dd>
		<dt>Ny tittel</dt>
		<dd><input type="text" name="title" class="styled w100" value="'.htmlspecialchars(postval("title", nodes::$node_info['node_title'])).'" /></dd>
	</dl>
	<p>'.show_sbutton("Lagre").' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>';
				
				page_node::load_page();
			}
			
			// aktiver/deaktiver
			if (isset($_GET['enabled']))
			{
				$update = false;
				if ($_GET['enabled'] == "false")
				{
					// deaktiver
					if (nodes::$node_info['node_enabled'] > 0)
					{
						$update = 0;
						ess::$b->page->add_message("Siden er nå deaktivert. Alle undersider vil også være utilgjengelige.");
					}
				}
				else
				{
					// aktiver
					if (nodes::$node_info['node_enabled'] == 0)
					{
						$update = 1;
						ess::$b->page->add_message("Siden er nå aktivert. Alle undersider som ikke er deaktivert vil også være tilgjengelige.");
					}
				}
				
				// oppdater?
				if ($update !== false)
				{
					ess::$b->db->query("UPDATE nodes SET node_enabled = $update WHERE node_id = ".nodes::$node_id);
				}
				
				redirect::handle();
			}
			
			// skjul fra/vis i menyen
			if (isset($_GET['show_menu']))
			{
				$update = false;
				if ($_GET['show_menu'] == "false")
				{
					// deaktiver
					if (nodes::$node_info['node_show_menu'] > 0)
					{
						$update = 0;
						ess::$b->page->add_message("Siden blir ikke lengre vist i menyen. Alle undersider vil også bli skjult fra menyen.");
					}
				}
				else
				{
					// aktiver
					if (nodes::$node_info['node_show_menu'] == 0)
					{
						$update = 1;
						ess::$b->page->add_message("Siden blir nå vist i menyen. Alle undersider som ikke er skjult vil også bli vist i menyen.");
					}
				}
				
				// oppdater?
				if ($update !== false)
				{
					ess::$b->db->query("UPDATE nodes SET node_show_menu = $update WHERE node_id = ".nodes::$node_id);
				}
				
				redirect::handle();
			}
			
			// vis/skjul undersider
			if (isset($_GET['expand_menu']))
			{
				$update = false;
				if ($_GET['expand_menu'] == "false")
				{
					// deaktiver
					if (nodes::$node_info['node_expand_menu'] > 0)
					{
						$update = 0;
						ess::$b->page->add_message("Undersidene blir ikke lengre vist i menyen.");
					}
				}
				else
				{
					// aktiver
					if (nodes::$node_info['node_expand_menu'] == 0)
					{
						$update = 1;
						ess::$b->page->add_message("Undersidene blir nå vist i menyen.");
					}
				}
				
				// oppdater?
				if ($update !== false)
				{
					ess::$b->db->query("UPDATE nodes SET node_expand_menu = $update WHERE node_id = ".nodes::$node_id);
				}
				
				redirect::handle();
			}
			
			// slett side
			if (isset($_GET['delete']))
			{
				$table_check = "";
				$where_check = "";
				
				// sjekk om det er noen elementer under denne
				$result = ess::$b->db->query("SELECT COUNT(node_id) FROM nodes WHERE node_parent_node_id = ".nodes::$node_id." AND node_deleted = 0");
				$ant =  mysql_result($result, 0);
				
				if ($ant > 0)
				{
					ess::$b->page->add_message("Du kan ikke slette en side som inneholder undersider. Flytt eller fjern undersidene og prøv på nytt.", "error");
					redirect::handle();
				}
				
				$table_check .= ", (SELECT COUNT(node_id) AS ant FROM nodes WHERE node_parent_node_id = ".nodes::$node_id." AND node_deleted = 0) AS ref_subnodes";
				$where_check .= " AND ref_subnodes.ant = 0";
				
				// sjekk type og spesiell info
				switch (nodes::$node_info['node_type'])
				{
					case "container":
						// sjekk antall enheter
						$result = ess::$b->db->query("SELECT COUNT(ni_id) FROM nodes_items WHERE ni_node_id = ".nodes::$node_id." AND ni_deleted = 0");
						$ant = mysql_result($result, 0);
						
						if ($ant > 0)
						{
							ess::$b->page->add_message("Du kan ikke slette en side som inneholder noen enheter. Fjern enhetene og prøv på nytt.", "error");
							redirect::handle();
						}
						
						$table_check .= ", (SELECT COUNT(ni_id) AS ant FROM nodes_items WHERE ni_node_id = ".nodes::$node_id." AND ni_deleted = 0) AS ref_items";
						$where_check .= " AND ref_items.ant = 0";
					break;
				}
				
				// godkjenn?
				if (isset($_POST['delete']))
				{
					// marker som slettet
					ess::$b->db->query("UPDATE nodes$table_check SET node_deleted = ".time()." WHERE node_id = ".nodes::$node_id.$where_check);
					
					if (ess::$b->db->affected_rows() == 0)
					{
						ess::$b->page->add_message("Noe gikk galt. Prøv på nytt.", "error");
						redirect::handle();
					}
					
					ess::$b->page->add_message("Siden ble markert som slettet og er ikke lenger tilgjengelig.");
					redirect::handle("node/a", redirect::ROOT);
				}
				
				echo '
<h1>Slett side</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Side</dt>
		<dd>'.htmlspecialchars(nodes::$node_info['node_title']).'</dd>
	</dl>
	<p>'.show_sbutton("Slett", 'name="delete"').' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>';
				
				page_node::load_page();
			}
			
			// vanlig side
			if (nodes::$node_info['node_type'] == "container")
			{
				// vis/skjul tittel
				if (isset($_GET['hide_title']))
				{
					$hide = NULL;
					if ($_GET['hide_title'] == "false")
					{
						// vis tittel
						if (nodes::$node_params->get("hide_title"))
						{
							$hide = false;
							ess::$b->page->add_message("Tittelen blir nå vist øverst på siden.");
						}
					}
					else
					{
						// skjul tittel
						if (!nodes::$node_params->get("hide_title"))
						{
							$hide = true;
							ess::$b->page->add_message("Tittelen blir ikke lengre vist øverst på siden.");
						}
					}
					
					// oppdater?
					if (!is_null($hide))
					{
						// hent friske params
						$result = ess::$b->db->query("SELECT node_params FROM nodes WHERE node_id = ".nodes::$node_id." FOR UPDATE");
						$params = new params(mysql_result($result, 0));
						if ($hide) $params->update("hide_title", "1");
						else $params->remove("hide_title");
						
						// oppdater
						ess::$b->db->query("UPDATE nodes SET node_params = ".ess::$b->db->quote($params->build())." WHERE node_id = ".nodes::$node_id);
					}
					
					redirect::handle();
				}
				
				// vis/skjul sist endret dato
				if (isset($_GET['hide_time_change']))
				{
					$hide = NULL;
					if ($_GET['hide_time_change'] == "false")
					{
						// vis tittel
						if (nodes::$node_params->get("hide_time_change"))
						{
							$hide = false;
							ess::$b->page->add_message("Dato for sist endret blir nå vist nederst på siden.");
						}
					}
					else
					{
						// skjul tittel
						if (!nodes::$node_params->get("hide_time_change"))
						{
							$hide = true;
							ess::$b->page->add_message("Dato for sist endret blir ikke lengre vist nederst på siden.");
						}
					}
					
					// oppdater?
					if (!is_null($hide))
					{
						// hent friske params
						$result = ess::$b->db->query("SELECT node_params FROM nodes WHERE node_id = ".nodes::$node_id." FOR UPDATE");
						$params = new params(mysql_result($result, 0));
						if ($hide) $params->update("hide_time_change", "1");
						else $params->remove("hide_time_change");
						
						// oppdater
						ess::$b->db->query("UPDATE nodes SET node_params = ".ess::$b->db->quote($params->build())." WHERE node_id = ".nodes::$node_id);
					}
					
					redirect::handle();
				}
				
				// aktiver/deaktiver enhet
				if (isset($_GET['unit_enable']) || isset($_GET['unit_disable']))
				{
					if (isset($_GET['unit_enable']))
					{
						$value = 1;
						$msg = "Enheten er nå aktivert og blir vist.";
						$unit_id = intval($_GET['unit_enable']);
					}
					else
					{
						$value = 0;
						$msg = "Enheten er nå deaktivert og blir ikke vist.";
						$unit_id = intval($_GET['unit_disable']);
					}
					
					// oppdater
					ess::$b->db->query("UPDATE nodes_items SET ni_enabled = $value WHERE ni_node_id = ".nodes::$node_id." AND ni_id = $unit_id AND ni_deleted = 0");
					if (mysql_affected_rows() > 0)
					{
						ess::$b->db->query("UPDATE nodes SET node_change = ".time()." WHERE node_id = ".nodes::$node_id);
						ess::$b->page->add_message($msg);
					}
					
					redirect::handle("node/a?node_id=".nodes::$node_id."&unit_highlight=$unit_id", redirect::ROOT);
				}
				
				// slett enhet
				if (isset($_GET['unit_delete']))
				{
					// hent enheten
					$unit_id = ess::$b->db->quote($_GET['unit_delete']);
					$result = ess::$b->db->query("SELECT ni_id, ni_type, nir_content, nir_params, nir_description, ni_priority, ni_enabled, nir_time FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_node_id = ".nodes::$node_id." AND ni_id = $unit_id AND ni_deleted = 0");
					
					// fant ikke?
					if (mysql_num_rows($result) == 0)
					{
						ess::$b->page->add_message("Fant ikke enheten. Prøv på nytt.", "error");
						redirect::handle();
					}
					$unit = mysql_fetch_assoc($result);
					
					// slette?
					if (isset($_POST['delete']))
					{
						// marker som slettet
						ess::$b->db->query("UPDATE nodes_items SET ni_deleted = ".time()." WHERE ni_node_id = ".nodes::$node_id." AND ni_id = $unit_id AND ni_deleted = 0");
						ess::$b->page->add_message("Enheten ble markert som slettet og er ikke lenger tilgjengelig.");
						redirect::handle();
					}
					
					echo '
<h1>Slett enhet</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Side</dt>
		<dd>'.htmlspecialchars(nodes::$node_info['node_title']).'</dd>
	</dl>
	<p>'.show_sbutton("Slett enhet", 'name="delete"').' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>
<h2>Innhold av enhet</h2>'.nodes::content_build($unit);
					
					page_node::load_page();
				}
				
				// rediger enhet
				if (isset($_GET['unit_edit']))
				{
					// hent enheten
					$unit_id = ess::$b->db->quote($_GET['unit_edit']);
					$result = ess::$b->db->query("SELECT ni_id, ni_type, nir_content, nir_params, nir_description, ni_priority, ni_enabled, nir_time FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_node_id = ".nodes::$node_id." AND ni_id = $unit_id AND ni_deleted = 0");
					
					// fant ikke?
					if (mysql_num_rows($result) == 0)
					{
						ess::$b->page->add_message("Fant ikke enheten. Prøv på nytt.", "error");
						redirect::handle();
					}
					$unit = mysql_fetch_assoc($result);
					
					// kan endres?
					if (!isset(nodes::$item_types[$unit['ni_type']]) || !nodes::$item_types[$unit['ni_type']][1])
					{
						ess::$b->page->add_message("Denne enheten kan ikke redigeres.", "error");
						redirect::handle();
					}
					
					$params = new params($unit['nir_params']);
					
					// lagre endringer?
					if (isset($_POST['description']) && isset($_POST['content']))
					{
						// ingenting endret?
						if (trim($_POST['description']) == $unit['nir_description'] && trim($_POST['content']) == $unit['nir_content'])
						{
							ess::$b->page->add_message("Ingen endringer ble utført.", "error");
						}
						
						else
						{
							ess::$b->db->query("INSERT INTO nodes_items_rev SET nir_ni_id = {$unit['ni_id']}, nir_params = ".ess::$b->db->quote($params->build()).", nir_content = ".ess::$b->db->quote($_POST['content']).", nir_description = ".ess::$b->db->quote($_POST['description']).", nir_time = ".time());
							$nir_id = ess::$b->db->insert_id();
							ess::$b->db->query("UPDATE nodes_items SET ni_nir_id = $nir_id, ni_nir_count = ni_nir_count + 1 WHERE ni_id = {$unit['ni_id']}");
							ess::$b->db->query("UPDATE nodes SET node_change = ".time()." WHERE node_id = ".nodes::$node_id);
							
							putlog("CREWCHAN", "NODE REDIGERT: ".login::$user->player->data['up_name']." redigerte %u".nodes::$node_info['node_title']."%u ".ess::$s['spath']."/node/".nodes::$node_id);
							
							ess::$b->page->add_message("Enheten ble oppdatert.");
							redirect::handle("node/a?node_id=".nodes::$node_id."&unit_highlight={$unit['ni_id']}", redirect::ROOT);
						}
					}
					
					// vis form osv
					echo '
<h1>Rediger enhet</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Forelder</dt>
		<dd>'.htmlspecialchars(nodes::$node_info['node_title']).'</dd>
		<dt>Type</dt>
		<dd>'.nodes::content_type($unit).'</dd>
		<dt>Beskrivelse</dt>
		<dd>
			<textarea name="description" cols="30" rows="2">'.htmlspecialchars(postval("description", $unit['nir_description'])).'</textarea>
		</dd>';
					
					switch ($unit['ni_type'])
					{
						case 1:
							echo '
		<dt>Innhold (BB-kode)</dt>
		<dd>&nbsp;</dd>
	</dl>
	<p>
		<textarea name="content" cols="30" rows="2" style="width: 530px; height: 300px">'.htmlspecialchars(postval("content", $unit['nir_content'])).'</textarea>
	</p>';
						break;
						
						case 2:
							echo '
		<dt>Innhold (ren HTML)</dt>
		<dd>&nbsp;</dd>
	</dl>
	<p class="clear">
		<textarea name="content" cols="30" rows="2" style="width: 530px; height: 300px">'.htmlspecialchars(postval("content", $unit['nir_content'])).'</textarea>
	</p>';
						break;
						
						case 3:
							tinymce::add_element("ni_content", true);
							echo '
		<dt>Innhold (HTML editor)</dt>
		<dd>&nbsp;</dd>
	</dl>
	<p class="clear">
		<textarea name="content" cols="30" rows="2" id="ni_content" style="width: 530px; height: 400px">'.htmlspecialchars(postval("content", $unit['nir_content'])).'</textarea>
	</p>';
							tinymce::load();
						break;
						
						case 4:
							echo '
		<dt>Ren tekst</dt>
		<dd>&nbsp;</dd>
	</dl>
	<p class="clear">
		<textarea name="content" cols="30" rows="2" style="width: 530px; height: 300px">'.htmlspecialchars(postval("content", $unit['nir_content'])).'</textarea>
	</p';
						break;
						
						default:
							redirect::handle("node/a?node_id=".nodes::$node_id."&unit_highlight={$unit['ni_id']}", redirect::ROOT);
					}
					
					echo '
	<p>'.show_sbutton("Oppdater").' <a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_highlight='.$unit['ni_id'].'" class="button">Avbryt</a></p>
</form>';
					
					page_node::load_page();
				}
				
				// opprette ny enhet?
				if (isset($_GET['unit_new']))
				{
					$previous_unit = getval("previous_unit", 0);
					
					// hent info om previous unit
					if ($previous_unit == 0)
					{
						$priority = 1;
						$priority_num = 1;
					}
					
					else
					{
						// hent priority til forrige
						$result = ess::$b->db->query("SELECT ni_priority FROM nodes_items WHERE ni_node_id = ".nodes::$node_id." AND ni_id = $previous_unit AND ni_deleted = 0");
						if (mysql_num_rows($result) == 0)
						{
							ess::$b->page->add_message("Noe gikk galt. Prøv igjen.", "error");
							redirect::handle();
						}
						$priority = mysql_result($result, 0);
						
						// hent priority til den vi skal "erstatte"
						$result = ess::$b->db->query("SELECT ni_priority FROM nodes_items WHERE ni_node_id = ".nodes::$node_id." AND ni_priority > $priority AND ni_deleted = 0 ORDER BY ni_priority LIMIT 1");
						if (mysql_num_rows($result) > 0)
						{
							$priority = mysql_result($result, 0);
						}
						else
						{
							$priority++;
						}
						
						// hent nummer
						$result = ess::$b->db->query("SELECT COUNT(ni_id) FROM nodes_items WHERE ni_node_id = ".nodes::$node_id." AND ni_priority < $priority AND ni_deleted = 0");
						$priority_num = mysql_result($result, 0) + 1;
					}
					
					// legge til?
					if (isset($_POST['type']))
					{
						// kontroller type
						$type = postval("type");
						
						if (!isset(nodes::$item_types[$type]) || !nodes::$item_types[$type][1])
						{
							ess::$b->page->add_message("Ugyldig type. Prøv på nytt.", "error");
						}
						
						else
						{
							$description = postval("description", NULL);
							
							// sett opp prioritys
							ess::$b->db->query("UPDATE nodes_items SET ni_priority = ni_priority + 1 WHERE ni_node_id = ".nodes::$node_id." AND ni_priority >= $priority AND ni_deleted = 0");
							
							// legg til enhet
							ess::$b->db->query("INSERT INTO nodes_items SET ni_node_id = ".nodes::$node_id.", ni_type = ".ess::$b->db->quote($type).", ni_priority = $priority");
							$ni_id = ess::$b->db->insert_id();
							ess::$b->db->query("INSERT INTO nodes_items_rev SET nir_ni_id = $ni_id, nir_time = ".time().", nir_description = ".ess::$b->db->quote($description));
							$nir_id = ess::$b->db->insert_id();
							ess::$b->db->query("UPDATE nodes_items SET ni_nir_id = $nir_id WHERE ni_id = $ni_id");
							
							ess::$b->page->add_message("Enheten ble lagt til.");
							redirect::handle("node/a?node_id=".nodes::$node_id."&unit_edit=$ni_id", redirect::ROOT);
						}
					}
					
					echo '
<h1>Ny enhet</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Under side</dt>
		<dd>'.htmlspecialchars(nodes::$node_info['node_title']).'</dd>
		<dt>Plassering</dt>
		<dd>'.$priority_num.'</dd>
		<dt>Type</dt>
		<dd>
			<select name="type">';
					
					$selected = postval("type", 3);
					if (!isset(nodes::$item_types[$selected]) || !nodes::$item_types[$selected][1]) $selected = false;
					
					foreach (nodes::$item_types as $key => $info)
					{
						// ikke i bruk?
						if (!$info[1]) continue;
						
						echo '
				<option value="'.htmlspecialchars($key).'"'.($selected == $key ? ' selected="selected"' : '').'>'.htmlspecialchars($info[0]).'</option>';
					}
					
					echo '
			</select>
		</dd>
		<dt>Beskrivelse</dt>
		<dd>
			<textarea name="description" cols="30" rows="2">'.htmlspecialchars(postval("description")).'</textarea>
		</dd>
	</dl>
	<p>'.show_sbutton("Opprett enhet").' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>';
					page_node::load_page();
				}
			}
			
			// adresser
			elseif (nodes::$node_info['node_type'] == "url_relative" || nodes::$node_info['node_type'] == "url_relative")
			{
				// endre adresse
				if (isset($_GET['edit_url']))
				{
					// lagre?
					if (isset($_POST['url']))
					{
						// hent friske params
						$result = ess::$b->db->query("SELECT node_params FROM nodes WHERE node_id = ".nodes::$node_id." FOR UPDATE");
						$params = new params(mysql_result($result, 0));
						$params->update("url", $_POST['url']);
						
						if (isset($_POST['new_window'])) $params->update("new_window", 1);
						else $params->remove("new_window");
						
						// oppdater
						ess::$b->db->query("UPDATE nodes SET node_params = ".ess::$b->db->quote($params->build())." WHERE node_id = ".nodes::$node_id);
						ess::$b->page->add_message("Adressen ble oppdatert.");
						redirect::handle();
					}
					
					if (nodes::$node_info['node_type'] == "url_relative")
					{
						echo '
<h1>Rediger adresse</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Nåværende adresse</dt>
		<dd>'.htmlspecialchars(ess::$s['path']).htmlspecialchars(nodes::$node_params->get("url", " ???")).'</dd>
		<dt>Ny adresse</dt>
		<dd>'.htmlspecialchars(ess::$s['path']).' <input type="text" name="url" class="styled w150" value="'.htmlspecialchars(nodes::$node_params->get("url", "")).'" /></dd>
		<dt>Åpne i nytt vindu</dt>
		<dd><input type="checkbox" name="new_window"'.(nodes::$node_params->get("new_window") ? ' checked="checked"' : '').' /></dd>
	</dl>
	<p>'.show_sbutton("Lagre").' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>';
					}
					
					else
					{
						echo '
<h1>Rediger adresse</h1>
<form action="" method="post">
	<dl class="dd_right dl_2x">
		<dt>Nåværende adresse</dt>
		<dd>'.htmlspecialchars(nodes::$node_params->get("url", "???")).'</dd>
		<dt>Ny adresse</dt>
		<dd><input type="text" name="url" class="styled w250" value="'.htmlspecialchars(nodes::$node_params->get("url", "")).'" /></Dd>
		<dt>Åpne i nytt vindu</dt>
		<dd><input type="checkbox" name="new_window"'.(nodes::$node_params->get("new_window") ? ' checked="checked"' : '').' /></dd>
	</dl>
	<p>'.show_sbutton("Lagre").' '.show_sbutton("Avbryt", 'name="abort"').'</p>
</form>';
					}
					
					page_node::load_page();
				}
			}
		}
		
		if ($node)
		{
			self::show_node_info($node);
		}
		
		self::show_nodes_list();
		
		page_node::load_page();
	}
	
	protected static function show_nodes_list()
	{
		echo '
<h1>Innhold</h1>
<h2>Oversikt over sidene</h2>
<p class="h_right"><a href="'.ess::$s['relative_path'].'/node/a?new_node" class="large_button">Ny side</a></p>
<table class="table tablemb" width="100%">
	<thead>
		<tr>
			<th>Tittel</th>
			<th>Informasjon</th>
			<th>Enheter</th>
			<th>Ny side</th>
			<th>Vis</th>
		</tr>
	</thead>
	<tbody class="c">';
		
		// hent antall items i hver node
		$result = ess::$b->db->query("SELECT ni_node_id, COUNT(ni_id) count FROM nodes_items WHERE ni_deleted = 0 GROUP BY ni_node_id");
		$nodes_items_count = array();
		while ($row = mysql_fetch_assoc($result)) $nodes_items_count[$row['ni_node_id']] = $row['count'];
		foreach (nodes::$nodes as $key => &$value)
		{
			$value['ni_count'] = isset($nodes_items_count[$key]) ? $nodes_items_count[$key] : 0;
		}
		
		// generer data
		$tree = new tree(nodes::$nodes_sub);
		$data = $tree->generate(0, NULL, nodes::$nodes);
		
		$parent_disabled = 0;
		$parent_hidden = 0;
		$i = 0;
		
		foreach ($data as $key => $row)
		{
			if ($parent_disabled != 0 && $row['number'] <= $parent_disabled) $parent_disabled = 0;
			if ($parent_hidden != 0 && $row['number'] <= $parent_hidden) $parent_hidden = 0;
			
			// adresse
			switch ($row['data']['node_type'])
			{
				case "url_absolute":
					$params = new params($row['data']['node_params']);
					$url = htmlspecialchars($params->get("url"));
				break;
				
				case "url_relative":
					$params = new params($row['data']['node_params']);
					$url = ess::$s['relative_path'].htmlspecialchars($params->get("url"));
				break;
				
				default:
					if ($key == nodes::$default_node)
					{
						$url = ess::$s['relative_path'].'/';
					}
					else
					{
						$url = ess::$s['relative_path'].'/node/'.$row['data']['node_id'];
					}
			}
			
			// verktøy
			$tools = array();
			
			// deaktivert?
			if ($row['data']['node_enabled'] == 0)
			{
				$tools[] = '[<span style="color:#FF0000">deaktivert</span>]';
				if ($parent_disabled != 0) $parent_disabled = $row['number'];
			}
			elseif ($parent_disabled)
			{
				$tools[] = '[<span style="color:#FF0000">deaktivert</span> (arvet)]';
			}
			
			// skjult fra menyen
			if ($row['data']['node_show_menu'] == 0)
			{
				$tools[] = '[<span style="color:#FF0000">skjult fra meny</span> - <a href="'.ess::$s['relative_path'].'/node/a?node_id='.$row['data']['node_id'].'&amp;show_menu">vis</a>]';
				if ($parent_hidden != 0) $parent_hidden = $row['number'];
			}
			elseif ($parent_hidden)
			{
				$tools[] = '[<span style="color:#FF0000">skjult fra meny</span> (arvet)]';
			}
			
			$tools = count($tools) == 0 ? '&nbsp;' : '<span style="font-size: 10px">'.implode(" ", $tools).'</span>';
			
			// farge
			$i++;
			$class = nodes::$node_id == $key ? ' class="highlight"' : ($i % 2 == 0 ? ' class="color"' : '');
			
			// informasjon om typen
			switch ($row['data']['node_type'])
			{
				case "url_absolute": $typeinfo = '<span style="font-size: 9px">(absolutt adresse)</span>'; break;
				case "url_relative": $typeinfo = '<span style="font-size: 9px">(relativ adresse)</span>'; break;
				default: $typeinfo = '';
			}
			
			// enheter
			$units = array();
			if ($row['data']['ni_count'] > 0) $units[] = $row['data']['ni_count'];
			if ($typeinfo != "") $units[] = $typeinfo;
			$units = count($units) == 0 ? '&nbsp;' : implode(" ", $units);
			
			echo '
		<tr'.$class.'>
			<td class="l"><span class="plain">'.$row['prefix'].$row['prefix_node'].'</span> <a href="'.ess::$s['relative_path'].'/node/a?node_id='.$key.'">'.htmlspecialchars($row['data']['node_title']).'</a></td>
			<td>'.$tools.'</td>
			<td>'.$units.'</td>
			<td style="font-size: 9px">
				<a href="'.ess::$s['relative_path'].'/node/a?new_node&amp;parent_node='.$key.'" title="Ny side INNI denne">inni</a>
				<a href="'.ess::$s['relative_path'].'/node/a?new_node&amp;parent_node='.$row['data']['node_parent_node_id'].'&amp;previous_node='.$key.'" title="Ny side UNDER denne">under</a>
			</td>
			<td><a href="'.$url.'">vis</a></td>
		</tr>';
			
		}
		
		echo '
	</tbody>
</table>';
	}
	
	protected function show_node_info($node)
	{
		switch (nodes::$node_info['node_type'])
		{
			case "url_absolute": $type = "Adresse (absolutt)"; break;
			case "url_relative": $type = "Adresse (relativ)"; break;
			default: $type = "Vanlig side";
		}
		
		echo '
<h1>
	<!--<span class="h_right red"><a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;delete" class="button">Slett side</a></span>-->
	'.htmlspecialchars(nodes::$node_info['node_title']).'
</h1>
<p class="h_right">
	<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;move" class="large_button">Flytt side</a>
	<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;delete" class="large_button red">Slett side</a>
</p>
<boxes />
<h2>Generell informasjon</h2>
<dl class="dd_right">
	<dt>Tittel</dt>
	<dd>'.htmlspecialchars(nodes::$node_info['node_title']).' [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;edit_title">rediger</a>]</dd>
	<dt>Aktivert</dt>
	<dd>'.(nodes::$node_info['node_enabled'] > 0 ? 'Ja [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;enabled=false">deaktiver</a>]' : '<span style="color:#FF0000">Nei</span> [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;enabled">aktiver</a>]').'</dd>
	<dt>Type</dt>
	<dd>'.$type.'</dd>
	<dt>Vis i menyen</dt>
	<dd>'.(nodes::$node_info['node_show_menu'] > 0 ? 'Ja [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;show_menu=false">skjul</a>]' : '<span style="color:#FF0000">Nei</span> [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;show_menu">vis</a>]').'</dd>
	<dt>Ekspander menyen</dt>
	<dd>'.(nodes::$node_info['node_expand_menu'] > 0 ? 'Ja [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;expand_menu=false">ikke ekspander</a>]' : 'Nei [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;expand_menu">ekspander</a>]').'</dd>
</dl>';
		
		// enheter?
		if (nodes::$node_info['node_type'] == "container")
		{
			// hent enhetene
			$result = ess::$b->db->query("SELECT ni_id, ni_type, nir_content, nir_params, nir_time, ni_enabled, nir_description FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_node_id = ".nodes::$node_id." AND ni_deleted = 0 ORDER BY ni_priority");
			
			echo '
<h2>Innstillinger</h2>
<dl class="dd_right">
	<dt>Vis tittel på siden</dt>
	<dd>'.(nodes::$node_params->get("hide_title") ? 'Nei [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;hide_title=false">vis tittel</a>]' : 'Ja [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;hide_title">skjul tittel</a>]').'</dd>
	<dt>Vis sist endret</dt>
	<dd>'.(nodes::$node_params->get("hide_time_change") ? 'Nei [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;hide_time_change=false">vis dato</a>]' : 'Ja [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;hide_time_change">skjul dato</a>]').'</dd>
</dl>

<h2>Enhetene</h2>
<p class="h_right"><a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_new" class="large_button">Ny enhet</a></p>';
			
			if (mysql_num_rows($result) == 0)
			{
				echo '
<p>
	Ingen enheter er opprettet enda.
</p>';
			}
			
			else
			{
				echo '
<table class="table tablemb" width="100%">
	<thead>
		<tr>
			<th>Beskrivelse</th>
			<th>Type</th>
			<th>Vektøy</th>
		</tr>
	</thead>
	<tbody>';
				
				$i = 0;
				$highlight = getval("unit_highlight");
				
				while ($row = mysql_fetch_assoc($result))
				{
					$i++;
					$class = $highlight == $row['ni_id'] ? ' class="highlight"' : ($i % 2 == 0 ? ' class="color"' : '');
					
					$tools = array();
					
					if ($row['ni_enabled'] == 0) $tools[] = '[<span style="color:#FF0000">deaktivert</span> - <a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_enable='.$row['ni_id'].'">aktiver</a>]';
					else $tools[] = '[<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_disable='.$row['ni_id'].'">deaktiver</a>]';
					
					$tools[] = '[<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_edit='.$row['ni_id'].'">rediger</a>]';
					$tools[] = '[<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_delete='.$row['ni_id'].'">slett</a>]';
					$tools[] = '[ny: <a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;unit_new&amp;previous_unit='.$row['ni_id'].'">under</a>]';
					
					$tools = implode(" ", $tools);
					
					echo '
		<tr'.$class.'>
			<td>'.(empty($row['nir_description']) ? '<span style="color:#888888; font-size: 10px">Ingen beskrivelse</span>' : htmlspecialchars($row['nir_description'])).'</td>
			<td>'.nodes::content_type($row).'</td>
			<td class="r">'.$tools.'</td>
		</tr>';
				}
				
				echo '
	</tbody>
</table>';
			}
		}
		
		// adresse (absolutt)
		elseif (nodes::$node_info['node_type'] == "url_absolute")
		{
			echo '
<h2>Adresseinformasjon</h2>
<p>
	Dette er en direkteadresse som skal inneholde hele adressen til nettstedet.
</p>
<dl class="dd_right">
	<dt>Adresse</dt>
	<dd><a href="'.htmlspecialchars(nodes::$node_params->get("url", '#')).'">'.htmlspecialchars(nodes::$node_params->get("url", 'mangler adresse')).'</a> [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;edit_url">rediger</a>]</dd>
</dl>';
		}
		
		// adresse (relativ)
		elseif (nodes::$node_info['node_type'] == "url_relative")
		{
			echo '
<h2>Adresseinformasjon</h2>
<p>
	Dette er en adresse internt på nettsiden som kun skal inneholde adressen under nettstedet det ønskes å linkes til.
</p>
<dl class="dd_right">
	<dt>Adresse</dt>
	<dd>'.htmlspecialchars(ess::$s['path']).' <a href="'.htmlspecialchars(nodes::$node_params->get("url", '#')).'">'.htmlspecialchars(nodes::$node_params->get("url", 'mangler adresse')).'</a> [<a href="'.ess::$s['relative_path'].'/node/a?node_id='.nodes::$node_id.'&amp;edit_url">rediger</a>]</dd>
</dl>';
		}
	}
}
