<?php

define("ALLOW_GUEST", true);

$root = "/";
global $__server, $_base, $_game, $_smileys;
require "base.php";

// finn riktig adresse
$url = isset($_SERVER['REDIR_URL']) ? $_SERVER['REDIR_URL'] : '';
if (mb_substr($url, 0, mb_strlen($root)) === $root)
{
	$url = mb_substr($url, mb_strlen($root));
}

$pages = explode("/", $url);
$page = $pages[0];
$pageurl = $__server['relative_path'].$root.$page;

redirect::store($pageurl, redirect::SERVER);

// finn ut hva vi skal vise
switch ($page)
{
	case "polls":
		$_base->page->add_title("Avstemninger");
		
		// administrasjon?
		if (isset($pages[1]) && $pages[1] == "admin")
		{
			require ROOT."/crew/avstemninger.php";
			break;
		}
		
		// avgi stemme?
		if (isset($pages[1]) && $pages[1] == "vote" && access::no_guest())
		{
			if (!isset($_POST['poll']) || !is_array($_POST['poll']) || count($_POST['poll']) > 1)
			{
				$_base->page->add_message("Du må velge et alternativ.", "error");
				redirect::handle("", redirect::ROOT);
			}
			
			$p_id = (int) key($_POST['poll']);
			$po_id = (int) current($_POST['poll']);
			
			// hent avstemning
			$time = time();
			$result = $_base->db->query("
				SELECT p_id, p_title, p_ft_id, p_time_end, pv_po_id, pv_time
				FROM polls
					LEFT JOIN polls_votes ON pv_up_id = ".login::$user->player->id." AND pv_p_id = p_id
				WHERE p_id = $p_id AND p_active != 0 AND p_time_start < $time AND (p_time_end = 0 OR $time <= p_time_end)
				GROUP BY p_id");
			if (mysql_num_rows($result) == 0)
			{
				$_base->page->add_message("Fant ikke avstemningen.", "error");
				redirect::handle("", redirect::ROOT);
			}
			$poll = mysql_fetch_assoc($result);
			
			// allerede stemt?
			if (mysql_result($result, 0, "pv_po_id"))
			{
				$_base->page->add_message("Du har allerede stemt på avstemningen &laquo;".htmlspecialchars($poll['p_title'])."&raquo;.", "error");
				redirect::handle("", redirect::ROOT);
			}
			
			// finn alternativet
			$result = $_base->db->query("SELECT po_id, po_p_id, po_text FROM polls_options WHERE po_p_id = $p_id AND po_id = $po_id");
			if (mysql_num_rows($result) == 0)
			{
				$_base->page->add_message("Ugyldig alternativ.", "error");
				redirect::handle("", redirect::ROOT);
			}
			$option = mysql_fetch_assoc($result);
			
			// legg til stemme
			$_base->db->query("INSERT IGNORE INTO polls_votes SET pv_p_id = $p_id, pv_po_id = $po_id, pv_up_id = ".login::$user->player->id.", pv_time = ".time());
			if ($_base->db->affected_rows() > 0)
			{
				$_base->page->add_message("Du har avgitt stemme på avstemningen &laquo;".htmlspecialchars($poll['p_title'])."&raquo;.");
				$_base->db->query("UPDATE polls_options SET po_votes = po_votes + 1 WHERE po_id = {$option['po_id']}");
				$_base->db->query("UPDATE polls SET p_votes = p_votes + 1 WHERE p_id = $p_id");
				
				// slett cache
				cache::delete("polls_options_list");
			}
			else
			{
				$_base->page->add_message("Din stemme ble ikke registrert.", "error");
			}
			
			// sende til forum tråden?
			if ($poll['p_ft_id'])
			{
				redirect::handle("/forum/topic?id={$poll['p_ft_id']}", redirect::ROOT);
			}
			
			redirect::handle("", redirect::ROOT);
		}
		
		
		// hent avstemningene
		$pagei = new pagei(pagei::PER_PAGE, 10, pagei::ACTIVE_GET, "side");
		if (isset($pages[1])) $pagei->__construct(pagei::ACTIVE, intval($pages[1]));
		
		$time = time();
		$l = login::$logged_in;
		$result = $pagei->query("
			SELECT p_id, p_title, p_text, p_time_start, p_time_end".($l ? ", pv_po_id, pv_time" : "")."
			FROM polls".($l ? "
				LEFT JOIN polls_votes ON pv_up_id = ".login::$user->player->id." AND pv_p_id = p_id" : "")."
			WHERE p_active != 0 AND p_time_start < $time
			GROUP BY p_id
			ORDER BY p_time_end != 0, p_time_end DESC, p_id DESC");
		$polls = array();
		if (mysql_num_rows($result) > 0)
		{
			// les data
			while ($row = mysql_fetch_assoc($result))
			{
				$polls[$row['p_id']] = $row;
				$polls[$row['p_id']]['options'] = array();
				$polls[$row['p_id']]['votes'] = 0;
			}
			
			// hent alternativene
			$result = $_base->db->query("SELECT po_id, po_p_id, po_text, po_votes FROM polls_options WHERE po_p_id IN (".implode(",", array_keys($polls)).")");
			while ($row = mysql_fetch_assoc($result))
			{
				$polls[$row['po_p_id']]['options'][$row['po_id']] = $row;
				$polls[$row['po_p_id']]['votes'] += $row['po_votes'];
			}
		}
		
		if (count($polls) == 0)
		{
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Avstemninger<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Ingen avstemninger er tilgjengelige.</p>
	</div>
</div>';
		}
		
		else
		{
			foreach ($polls as $poll)
			{
				// har vi stemt?
				$voted = login::$logged_in && $poll['pv_po_id'] ? true : false;
				
				echo '
<div class="bg1_c xsmall">
	<h2 class="bg1">'.htmlspecialchars($poll['p_title']).'<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<p><b>Periode:</b><br />'.(empty($poll['p_time_start']) ? 'Til '.(empty($poll['p_time_end']) ? 'ubestemt' : $_base->date->get($poll['p_time_end'])->format()) : 'Fra '.$_base->date->get($poll['p_time_start'])->format().' til '.(empty($poll['p_time_end']) ? 'ubestemt' : $_base->date->get($poll['p_time_end'])->format()).($poll['p_time_end'] > time() ? ' (pågår)' : '')).'</p>
		<p><b>Antall stemmer:</b> '.game::format_number($poll['votes']).'</p>';
				
				if (($bb = game::format_data($poll['p_text'])) != "")
				{
					echo '
		<div class="p">'.$bb.'</div>';
				}
				
				echo '
		<div class="poll_options">';
				
				// finn alternativet med flest stemmer
				$max = 0;
				foreach ($poll['options'] as $option)
				{
					if ($option['po_votes'] > $max) $max = $option['po_votes'];
				}
				
				// alternativene
				foreach ($poll['options'] as $option)
				{
					$p = $poll['votes'] == 0 ? 0 : round($option['po_votes'] / $poll['votes'] * 100, 1);
					$p_w = $max == 0 ? 0 : round($option['po_votes'] / $max * 100, 1);
					$is = $voted && $option['po_id'] == $poll['pv_po_id'];
					
					// resultatet
					echo '
			<div class="poll_option'.($is ? ' voted' : '').'">
				<div class="p">'.game::format_data($option['po_text']).($is ? ' (valgt)' : '').'</div>
				<div class="poll_option_bar_wrap" style="width: 150px">
					<div class="poll_option_bar" style="width: '.round($p_w).'%"><p>'.$p.' %'.($is ? ' (valgt)' : '').'</p></div>
				</div>
			</div>';
				}
				
				echo '
		</div>
	</div>
</div>';
			}
		}
		
		if ($pagei->pages > 1)
		{
			echo '
<p class="c">'.$pagei->pagenumbers(htmlspecialchars($pageurl), htmlspecialchars($pageurl)."/_pageid_").'</p>';
		}
		
		if (access::has("forum_mod"))
		{
			echo '
<p class="c" style="margin-top: 30px"><a href="'.$__server['relative_path'].'/polls/admin">Administrer avstemninger &raquo;</a></p>';
		}
		
		break;
}

$_base->page->load();