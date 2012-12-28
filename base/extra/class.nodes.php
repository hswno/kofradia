<?php

/*
 * Node types:
 *
	1:	bb
	2:	html
	3:	htmleditor
	4:	plaintext
	5:	gallery_section
	6:	gallery_item
	7:	news
	8:	phpinclude
 *
 */

nodes::$node_path = array(
	array(
		"node_id" => 1,
		"node_title" => "Kofradia",
		"node_url" => ess::$s['relative_path']."/node"
	)
);

nodes::main();
class nodes
{
	/**
	 * Standard node (første som skal vises)
	 */
	public static $default_node = 1;
	
	public static $types = array(
		"container" => "Vanlig side",
		"url_absolute" => "Adresse (absolutt)",
		"url_relative" => "Adresse (relativ)"
	);
	
	public static $item_types = array(
		1 => array("BB-kode", true),
		2 => array("HTML", true),
		3 => array("HTML (Editor)", true),
		4 => array("Plain text", true),
		5 => array("Galleri", false),
		6 => array("Galleri bilde", false),
		7 => array("Nyheter", false),
		8 => array("PHP Include", false)
	);
	
	public static $node_id = 0;
	public static $node_info;
	
	/**
	 * @var params
	 */
	public static $node_params;
	public static $node_path;
	
	public static $nodes = array();
	public static $nodes_sub = array();
	public static $nodes_sub_active = array();
	
	/**
	 * Init
	 */
	public static function main()
	{
		// hent alle nodene
		$result = ess::$b->db->query("SELECT node_id, node_parent_node_id, node_title, node_type, node_params, node_show_menu, node_expand_menu, node_enabled, node_priority, node_change FROM nodes WHERE node_deleted = 0 ORDER BY node_priority");
		
		while ($row = mysql_fetch_assoc($result))
		{
			self::$nodes[$row['node_id']] = $row;
			self::$nodes_sub[$row['node_parent_node_id']][] = $row['node_id'];
			
			if ($row['node_enabled'] > 0 && $row['node_show_menu'] > 0)
			{
				self::$nodes_sub_active[$row['node_parent_node_id']][] = $row['node_id'];
			}
		}
	}
	
	/**
	 * Hent innholdet av en node
	 */
	public static function load_node($node_id, $explicit = true)
	{
		$node_id = intval($node_id);
		
		// finnes ikke?
		if (!isset(self::$nodes[$node_id]))
		{
			if ($explicit) page_not_found();
			return false;
		}
		
		self::$node_id = $node_id;
		$parent_enabled = 1;
		
		// finn hvilke parent nodes vi har
		$parent_nodes = array();
		$parent_id = $node_id == 0 || !isset(self::$nodes[self::$nodes[$node_id]['node_parent_node_id']]) ? 0 : self::$nodes[self::$nodes[$node_id]['node_parent_node_id']]['node_id'];
		
		while (isset(self::$nodes[$parent_id]) && $row = self::$nodes[$parent_id])
		{
			$parent_nodes[] = $parent_id;
			$parent_id = $row['node_parent_node_id'];
			
			if ($row['node_enabled'] == 0) $parent_enabled = 0;
		}
		
		// fant ikke tilbake til root?
		if ($parent_id != 0)
		{
			if ($explicit) page_not_found();
			return false;
		}
		
		// hent mer info
		self::$node_info = self::$nodes[$node_id];
		self::$node_info['parent_enabled'] = $parent_enabled;
		self::$node_params = new params(self::$node_info['node_params']);
		
		// sett opp path
		$parent_nodes = array_reverse($parent_nodes);
		$parent_nodes[] = $node_id;
		foreach ($parent_nodes as $id)
		{
			$row = self::$nodes[$id];
			
			// sett opp linken
			switch ($row['node_type'])
			{
				case "url_absolute":
					$params = new params($row['node_params']);
					$url = htmlspecialchars($params->get("url"));
				break;
				
				case "url_relative":
					$params = new params($row['node_params']);
					$url = ess::$s['relative_path'].htmlspecialchars($params->get("url"));
				break;
				
				default: // container
					if ($row['node_id'] == self::$default_node)
					{
						$url = ess::$s['relative_path'].'/node';
					}
					else
					{
						$url = ess::$s['relative_path'].'/node/'.$row['node_id'];
					}
			}
			
			// legg til
			nodes::add_node($row['node_id'], $row['node_title'], $url);
			ess::$b->page->add_title($row['node_title']);
		}
		
		return true;
	}
	
	/**
	 * Vis en node
	 */
	public static function parse_node()
	{
		if (!self::$node_id)
		{
			page_not_found();
		}
		
		// deaktivert?
		if (self::$node_info['node_enabled'] == 0 || self::$node_info['parent_enabled'] == 0)
		{
			if (!access::has("crewet", null, null, "login"))
			{
				page_not_found();
			}
			
			// gi tilgang
			if (self::$node_info['node_enabled'] > 0)
			{
				ess::$b->page->add_message("En av foreldrene til denne siden er deaktivert og derfor er også denne siden deaktivert. Du kan vise den fordi du er logget inn.");
			}
			else
			{
				ess::$b->page->add_message("Denne siden er egentlig deaktivert. Du kan vise den fordi du er logget inn.");
			}
		}
		
		$filter = " AND ni_enabled > 0";
		if (isset($_GET['show_disabled_units']) && access::has("crewet"))
		{
			// hvor mange enheter er deaktivert?
			$result = ess::$b->db->query("SELECT COUNT(ni_id) FROM nodes_items WHERE ni_node_id = ".self::$node_id." AND ni_enabled = 0 AND ni_deleted = 0");
			$ant = mysql_result($result, 0);
			
			if ($ant == 0)
			{
				ess::$b->page->add_message("Det finnes ingen deaktiverte enheter på denne siden.");
			}
			else
			{
				ess::$b->page->add_message("Viser <b>$ant</b> deaktivert".($ant == 1 ? '' : 'e')." enheter.");
			}
			$filter = "";
		}
		
		// tittel
		if (!self::$node_params->get("hide_title"))
		{
			echo '
<h1>'.htmlspecialchars(self::$node_info['node_title']).'</h1>';
		}
		
		// vis enhetene
		$result = ess::$b->db->query("SELECT ni_id, ni_type, nir_content, nir_params, nir_time FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_node_id = ".self::$node_id.$filter." AND ni_deleted = 0 ORDER BY ni_priority");
		
		while ($row = mysql_fetch_assoc($result))
		{
			echo nodes::content_build($row);
		}
		
		if (!self::$node_params->get("hide_time_change"))
		{
			echo '
<p align="right" style="color:#AAAAAA;font-size:10px">
	Sist endret '.ess::$b->date->get(self::$node_info['node_change'])->format().'
</p>';
		}
		
		// linker
		if (access::has("crewet"))
		{
			$url = $_SERVER['REQUEST_URI'];
			if (($pos = strpos($url, "?")) !== false) $url = substr($url, 0, $pos);
			
			echo '
<p style="color:#AAA;text-align:right;font-size:10px">[<a href="'.ess::$s['relative_path'].'/node/a?node_id='.self::$node_id.'">rediger side</a>]'.(isset($_GET['show_disabled_units']) ? ' [<a href="'.game::address($url, $_GET, array("show_disabled_units")).'">skjul deaktiverte enheter</a>]' : ' [<a href="'.game::address($url, $_GET, array(), array("show_disabled_units" => true)).'">vis deaktiverte enheter</a>]').'</p>';
		}
	}
	
	/**
	 * Sett opp innhold til en enhet i noden
	 * @param array $unit
	 */
	public static function content_build($unit)
	{
		switch ($unit['ni_type'])
		{
			case 1: // bb
				$content = '
<div class="p">'.game::format_data($unit['nir_content']).'</div>';
			break;
			
			case 2: // html
			case 3: // htmleditor
				$content = '
'.$unit['nir_content'];
			break;
			
			case 5: // gallery_section
				$content = '
<p>Not implementet.</p>';
			break;
			
			case 6: // gallery_item
				$content = '
<p>Not implementet.</p>';
			break;
			
			case 7: // news
				$params = new params($unit['nir_params']);
				
				// hvilke tags?
				$tags = new container($params->get("tags"));
				$page_name = "ni_s_{$unit['ni_id']}";
				
				$pageinfo = new pagei(PAGEI_ACTIVE_GET, $page_name, PAGEI_PER_PAGE, max(1, min(50, $params->get("per_page", 15))));
				
				// hent nyhetene
				if (count($tags->items) > 0)
				{
					$tags_db = array_map(array(ess::$b->db, "quote"), $tags->items);
					
					// filter via tags
					$query = "n_id, n_title, n_content, n_userid, n_time, n_visible, n_type FROM news, news_tags WHERE n_visible > 0 AND n_id = nt_n_id AND nt_tagname IN (".implode(", ", $tags_db).") GROUP BY n_id ORDER BY n_time DESC";
				}
				else
				{
					$query = "n_id, n_title, n_intro, LEFT(n_content, 5) AS n_content, n_userid, n_time, n_visible, n_type FROM news WHERE n_visible > 0 ORDER BY n_time DESC";
				}
				
				$result = $pageinfo->query($query);
				$content = '';
				
				if (mysql_num_rows($result) == 0)
				{
					$content .= '
<p>Ingen nyheter.</p>';
				}
				
				else
				{
					while ($row = mysql_fetch_assoc($result))
					{
						$content .= '
<h2>'.htmlspecialchars($row['n_title']).'</h2>
<p class="h_right">'.ess::$b->date->get($row['n_time'])->format().'</p>
<div class="p">'.game::format_data($row['n_intro']).'</div>';
						
						// mer info?
						if (strlen($row['n_content']) > 0)
						{
							$content .= '
<p><a href="'.game::address(PHP_SELF, $_GET, array(), array("show_n" => $row['n_id'])).'">Les mer &raquo;</a></p>';
						}
					}
					
					if (!$params->get("hide_select_page") && $pageinfo->pages > 1)
					{
						$content .= '
<p>'.game::pagenumbers(game::address(PHP_SELF, $_GET, array($page_name)), game::address(PHP_SELF, $_GET, array($page_name), array($page_name => true))."=", $pageinfo->pages, $pageinfo->active).'</p>';
					}
				}
			break;
			
			case 8: // php include
				$content = '
<p>Not implementet.</p>';
			break;
			
			default: // plaintext
				$content = '
<p>'.htmlspecialchars($unit['nir_content']).'</p>';
		}
		
		return $content;
	}
	
	/**
	 * Hent ut typenavnet på en enhet i noden
	 * @param array $unit
	 */
	public static function content_type($unit)
	{
		// finn ut typen
		switch ($unit['ni_type'])
		{
			case 1: // bb
				$type = "BB-kode";
			break;
			
			case 2: // html
				$type = "HTML";
			break;
			
			case 3: // htmleditor
				$type = "HTML (Editor)";
			break;
			
			case 5: // gallery_section
				$type = "Galleri";
			break;
			
			case 6: // gallery_item
				$type = "Galleri bilde";
			break;
			
			case 7: // news
				$type = "Nyheter";
			break;
			
			case 8: // php include
				$type = "PHP Include";
			break;
			
			default: // plaintext
				$type = "Plain text";
		}
		
		return $type;
	}
	
	/**
	 * Legg til node i path
	 */
	public static function add_node($id, $title, $url)
	{
		self::$node_path[] = array(
			"node_id" => $id,
			"node_title" => $title,
			"node_url" => $url
		);
	}
	
	/**
	 * Sett opp lenker til path vi befinner oss i
	 */
	public static function build_path($pre, $seperator, $post)
	{
		$nodes = array();
		foreach (self::$node_path as $node)
		{
			$nodes[] = '<a href="'.$node['node_url'].'">'.htmlspecialchars($node['node_title']).'</a>';
		}
		
		return $pre . implode($seperator, $nodes) . $post;
	}
	
	/**
	 * Bygg meny
	 */
	public static function build_menu($parent_node, $expand_max = 0, $prefix = "")
	{
		// finn hvilke parent nodes vi har
		$parent_nodes = array();
		$parent_id = self::$node_id == 0 || !isset(self::$nodes[self::$nodes[self::$node_id]['node_parent_node_id']]) ? 0 : self::$nodes[self::$nodes[self::$node_id]['node_parent_node_id']]['node_id'];
		while (isset(self::$nodes[$parent_id]) && $row = self::$nodes[$parent_id])
		{
			$parent_nodes[] = $parent_id;
			$parent_id = $row['node_parent_node_id'];
		}
		
		// sett opp meny elementene
		$html = '';
		nodes::build_menu_group($html, $prefix, $parent_node, $parent_nodes, $expand_max, 0);
		
		return $html;
	}
	
	/**
	 * Sett opp menygruppe
	 */
	protected static function build_menu_group(&$html, $prefix, $node_id, &$parent_nodes, &$expand_max, $expand)
	{
		if (isset(self::$nodes_sub_active[$node_id]))
		{
			$expand_more = !$expand_max || $expand_max > $expand;
			$expand++;
			$prefix_sub = $prefix . "\t";
			$prefix_next = $prefix_sub . "\t";
			
			$html .= $prefix . "<ul>";
			foreach (self::$nodes_sub_active[$node_id] as $row)
			{
				$row = self::$nodes[$row];
				
				// er vi på denne? (uthev den)
				$active = $row['node_id'] == self::$node_id || (!$expand_more && in_array($row['node_id'], $parent_nodes)) ? ' class="active"' : '';
				
				// innholdet
				switch ($row['node_type'])
				{
					case "url_absolute":
						$params = new params($row['node_params']);
						$row_prefix = '<a href="'.htmlspecialchars($params->get("url")).'"'.$active.($params->get("new_window") ? ' target="_blank"' : '').'>';
						$row_suffix = '</a>';
					break;
					
					case "url_relative":
						$params = new params($row['node_params']);
						$row_prefix = '<a href="'.ess::$s['relative_path'].htmlspecialchars($params->get("url")).'"'.$active.($params->get("new_window") ? ' target="_blank"' : '').'>';
						$row_suffix = '</a>';
					break;
					
					default: // container
						if ($row['node_id'] == self::$default_node)
						{
							$url = ess::$s['relative_path'].'/node';
						}
						else
						{
							$url = ess::$s['relative_path'].'/node/'.$row['node_id'];
						}
						$row_prefix = '<a href="'.$url.'"'.$active.'>';
						$row_suffix = '</a>';
				}
				
				$content = $row_prefix . htmlspecialchars($row['node_title']) . $row_suffix;
				
				// enheten
				$html .= $prefix_sub . '<li'.$active.'>';
				
				// underelementer
				if (($row['node_expand_menu'] > 0 || in_array($row['node_id'], $parent_nodes) || $row['node_id'] == self::$node_id) && $expand_more)
				{
					$html .= $prefix_next . $content;
					nodes::build_menu_group($html, $prefix_next, $row['node_id'], $parent_nodes, $expand_max, $expand);
					$html .= $prefix_sub;
				}
				else
				{
					$html .= $content;
				}
				
				$html .= '</li>';
			}
			$html .= $prefix . "</ul>";
		}
	}
}