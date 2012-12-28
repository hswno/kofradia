<?php

require "../base.php";

new page_forum();
class page_forum
{
	/**
	 * Forumet
	 * @var forum
	 */
	public $forum;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->forum = new forum(getval("id"));
		$this->forum->require_access();
		$this->forum->add_title();
		
		// slette forumtråder?
		if (isset($_POST['slett_emner']) && $this->forum->fmod)
		{
			$this->delete_topics();
		}
		
		// vis forumet
		$this->show_forum();
		
		$this->forum->load_page();
	}
	
	/**
	 * Vis forumet
	 */
	protected function show_forum()
	{
		// markere som sett?
		if ($this->forum->ff)
		{
			$this->forum->ff->uinfo->forum_seen();
		}
		
		// vise slettede forumtråder?
		$show_deleted = false;
		if (isset($_GET['sd']) && $this->forum->fmod)
		{
			// ff
			if ($this->forum->ff && !access::has("mod"))
			{
				ess::$b->page->add_message("Du viser også forumtråder som ble slettet for mindre enn ".game::timespan(forum_topic::FF_HIDE_TIME, game::TIME_FULL)." siden.");
			}
			
			else
			{
				ess::$b->page->add_message("Du viser også slettede forumtråder.");
			}
			
			$show_deleted = true;
		}
		
		$vis_bokser = isset($_GET['vis_bokser']) && !$show_deleted;
		
		// vis forum informasjon
		echo '
<div class="bg1_c forumw">
	<h1 class="bg1">'.htmlspecialchars($this->forum->get_name()).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_right">
		<a href="topic_new?f='.$this->forum->id.'">Opprett ny forumtråd</a>'.($this->forum->fmod && !$vis_bokser && !$show_deleted ? '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array(), array("vis_bokser" => true))).'">Vis merk innlegg knapp</a>' : ($vis_bokser ? '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array("vis_bokser"))).'">Skjul valg</a>' : '')).'
	</p>
	<p class="h_left">
		<a href="sok?s'.$this->forum->id.'">Søk</a>
		<a href="'.ess::$s['relative_path'].'/node/6">Forumregler</a>'.($this->forum->fmod && !$vis_bokser ? ($show_deleted ? '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array("sd"))).'">Skjul slettede</a>' : '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array(), array("sd" => true))).'">Vis slettede</a>') : '').'
	</p>
	<div class="bg1">';
		
		
		// hvor lenge etter den er slettet vi kan vise den
		$access_expire = max(time() - forum_topic::FF_HIDE_TIME, $this->forum->ff ? $this->forum->ff->data['ff_time_reset'] : 0);
		
		// finn ut hvor mange topics det er
		$expire_deleted = $show_deleted ? (!$this->forum->ff || access::has("mod") ? "" : " AND (ft_deleted = 0 OR ft_deleted > $access_expire)") : " AND ft_deleted = 0";
		$result = ess::$b->db->query("
			SELECT COUNT(IF(ft_type = 1, 1, NULL)) AS normal, COUNT(IF(ft_type = 2, 1, NULL)) AS sticky, COUNT(IF(ft_type = 3, 1, NULL)) AS important
			FROM forum_topics
			WHERE ft_fse_id = {$this->forum->id}$expire_deleted");
		$count = mysql_fetch_assoc($result);
		mysql_free_result($result);
		
		
		// alle important og sticky topics skal vises på første siden
		// mens bare 15 normale topics skal vises på hver side
		$pagei = new pagei(pagei::PER_PAGE, 15, pagei::TOTAL, $count['normal'], pagei::ACTIVE_GET, "p");
		
		if (isset($_GET['p']) && (string)$pagei->active != $_GET['p'])
		{
			$add = $pagei->active > 1 ? '&p='.$pagei->active : '';
			redirect::handle("forum?id={$this->forum->id}$add");
		}
		
		// sjekke status?
		$fs_count = 0;
		
		// markere alle emner og svar som lest?
		if (isset($_GET['fs_force']) && login::$logged_in && forum::$fs_check)
		{
			// legg til og oppdater innleggene på denne siden
			ess::$b->db->query("
				INSERT INTO forum_seen (fs_ft_id, fs_u_id, fs_time)
				
				SELECT ft_id, ".login::$user->id.", IFNULL(fr_time, ft_time)
				FROM forum_topics
					LEFT JOIN forum_replies ON ft_last_reply = fr_id
				WHERE ft_fse_id = {$this->forum->id} AND ft_type = 1 AND ft_deleted = 0
				ORDER BY IFNULL(fr_time, ft_time) DESC
				LIMIT {$pagei->start}, {$pagei->per_page}
				
				ON DUPLICATE KEY UPDATE fs_time = VALUES(fs_time)");
			
			redirect::handle(game::address("forum", $_GET, array("fs_force")));
		}
		
		// oppdater tidspunkt for visning hvis crewforum
		if (login::$logged_in && $pagei->active == 1 && $this->forum->id >= 5 && $this->forum->id <= 7)
		{
			login::$user->params->update("forum_{$this->forum->id}_last_view", time(), true);
		}
		
		if (($pagei->active == 1 && ($count['important'] > 0 || $count['sticky'] > 0)) || $count['normal'] > 0)
		{
			echo ($this->forum->fmod && $vis_bokser ? '
<form action="" method="post">' : '').'
<table width="100%" class="table forum tablem">
	<thead>
		<tr>
			<th>Tittel'.($this->forum->fmod && $vis_bokser ? ' (<a href="#" class="box_handle_toggle" rel="emne[]">Merk alle</a>)' : '').'</th>
			<th>Trådstarter</th>
			<th>Svar</th>
			<th><abbr title="Visninger">Vis</abbr></th>
			<th>Siste innlegg</th>
		</tr>
	</thead>
	<tbody'.($this->forum->fmod && $vis_bokser ? ' class="pointer"' : '').'>';
			
			$i = 0;
			
			// skal vi hente important og sticky topics?
			if ($pagei->active == 1 && ($count['important'] > 0 || $count['sticky'] > 0))
			{
				// hent important og sticky forumtråder
				$result = $this->get_topics(false, $show_deleted);
				
				// vis hver topic
				while ($row = mysql_fetch_assoc($result))
				{
					// sjekke status?
					$fs_info = '';
					$fs_link_suffix = '';
					if (forum::$fs_check)
					{
						if (empty($row['fs_time']))
						{
							$fs_info = ' <span class="fs_ft_new">NY!</span>';
							$fs_count++;
						}
						
						elseif ($row['fs_time'] < $row['fr_time'])
						{
							$fs_info = ' <span class="fs_fr_new">'.$row['fs_new'].' <span class="fs_fr_newi">NY'.($row['fs_new'] == 1 ? '' : 'E').'</span></span>';
							$fs_link_suffix = '&amp;fs';
						}
					}
					
					echo '
		<tr class="'.(++$i % 2 == 0 ? 'color2_1' : 'color2_0').($row['ft_deleted'] != 0 ? ' ft_deleted' : '').'" id="emne_'.$row['ft_id'].'">
			<td class="f"><a href="topic?id='.$row['ft_id'].$fs_link_suffix.'">'.htmlspecialchars($row['ft_title']).'</a> '.($row['ft_type'] == 3 ? '<span style="color: #CCFF00; font-weight: bold">(Viktig)</span>' : '<span style="color: #CCFF00">(Sticky)</span>').($row['ft_locked'] == 1 ? ' <span class="forum_lock">(låst)</span>' : '').($row['ft_deleted'] != 0 ? ' (slettet)' : '').$fs_info.'</td>
			<td class="t_uinfo">'.game::profile_link($row['ft_up_id'], $row['up_name'], $row['up_access_level']).'<br /><span class="f_time">'.ess::$b->date->get($row['ft_time'])->format().'</span></td>
			<td>'.game::format_number($row['ft_replies']).'</td>
			<td>'.game::format_number($row['ft_views']).'</td>
			<td class="t_uinfo">'.($row['fr_time'] ? game::profile_link($row['fr_up_id'], $row['r_up_name'], $row['r_up_access_level']).'<br /><span class="f_time2"><a href="topic?id='.$row['ft_id'].'&amp;replyid='.$row['fr_id'].'" title="Gå til dette svaret">'.game::timespan($row['fr_time'], game::TIME_ABS).' &raquo;</a></span>' : '<span style="color: #AAA">Ingen</span>').'</td>
		</tr>';
				}
			}
			
			// hent vanlige forumtråder
			$result = $this->get_topics(true, $show_deleted, $pagei->start, $pagei->per_page);
			
			// vis hver topic
			while ($row = mysql_fetch_assoc($result))
			{
				// sjekke status?
				$fs_info = '';
				$fs_link_suffix = '';
				if (forum::$fs_check)
				{
					if (empty($row['fs_time']))
					{
						$fs_info = ' <span class="fs_ft_new">NY!</span>';
						$fs_count++;
					}
					
					elseif ($row['fs_time'] < $row['fr_time'])
					{
						$fs_info = ' <span class="fs_fr_new">'.$row['fs_new'].' <span class="fs_fr_newi">NY'.($row['fs_new'] == 1 ? '' : 'E').'</span></span>';
						$fs_link_suffix = '&amp;fs';
						$fs_count++;
					}
				}
				
				$i++;
				echo '
		<tr'.(is_int($i/2) ? ' class="color'.($row['ft_deleted'] != 0 ? ' ft_deleted' : '').($this->forum->fmod && $vis_bokser ? ' box_handle' : '').'"' : ($this->forum->fmod && $vis_bokser ? ' class="box_handle"' : ($row['ft_deleted'] != 0 ? ' class="ft_deleted"' : ''))).'>
			<td class="f">'.($this->forum->fmod && $vis_bokser ? '<input type="checkbox" name="emne[]" value="'.$row['ft_id'].'" />' : '').'<a href="topic?id='.$row['ft_id'].$fs_link_suffix.'">'.(empty($row['ft_title']) ? '<i>Mangler tittel</i>' : ucfirst(htmlspecialchars($row['ft_title']))).'</a>'.($row['ft_locked'] == 1 ? ' <span class="forum_lock">(låst)</span>' : '').($row['ft_deleted'] != 0 ? ' (slettet)' : '').$fs_info.'</td>
			<td class="t_uinfo">'.game::profile_link($row['ft_up_id'], $row['up_name'], $row['up_access_level']).'<br /><span class="f_time">'.ess::$b->date->get($row['ft_time'])->format().'</span></td>
			<td>'.game::format_number($row['ft_replies']).'</td>
			<td>'.game::format_number($row['ft_views']).'</td>
			<td class="t_uinfo">'.($row['fr_time'] ? game::profile_link($row['fr_up_id'], $row['r_up_name'], $row['r_up_access_level']).'<br /><span class="f_time2"><a href="topic?id='.$row['ft_id'].'&amp;replyid='.$row['fr_id'].'" title="Gå til dette svaret">'.game::timespan($row['fr_time'], game::TIME_ABS).' &raquo;</a></span>' : '<span style="color: #AAA">Ingen</span>').'</td>
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
			
			// merk alle som lest?
			if ($fs_count > 0 && login::$logged_in)
			{
				echo '
<p class="c" style="margin-top:0"><a href="'.htmlspecialchars(game::address("forum", $_GET, array(), array("fs_force" => true))).'">Marker alle emner og svar som lest</a></p>';
			}
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
<p class="c" style="margin-top:0">
	'.$pagei->pagenumbers().'
</p>';
			}
			
			// slett emner knapp
			if ($this->forum->fmod && $vis_bokser)
			{
				echo '
<p class="c red" style="margin-top:0">'.show_sbutton("Slett merkede emner", 'name="slett_emner" onclick="return confirm(\'Er du sikker på at du vil slette valgte emner?\')"').'</p>
</form>';
			}
		}
		
		
		// ingen emner?
		else
		{
			echo '
<p align="center">Dette forumet er tomt. Bli den første til å opprette en forumtråd ved å <a href="topic_new?f='.$this->forum->id.'">trykke her</a>.</p>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Slette forumtråder
	 */
	protected function delete_topics()
	{
		if (!isset($_POST['emne']))
		{
			ess::$b->page->add_message("Du merket ingen forumtråder!");
		}
		elseif (!is_array($_POST['emne']))
		{
			ess::$b->page->add_message("Ugyldig forumtråder (ingen array)!", "error");
		}
		else
		{
			$ant = 0;
			$slettet = array();
			$time = time();
			$idlist = array();
			
			foreach ($_POST['emne'] as $id)
			{
				$idlist[] = intval($id);
			}
			
			// ingen forumtråder?
			if (count($idlist) == 0)
			{
				ess::$b->page->add_message("Du må merke noen forumtråder.", "error");
				redirect::handle(game::address("forum", $_GET));
			}
			
			// hent forumtrådene
			$result = ess::$b->db->query("SELECT ft_id, ft_title, ft_up_id FROM forum_topics WHERE ft_deleted = 0 AND ft_fse_id = {$this->forum->id} AND ft_id IN (".implode(",", $idlist).") FOR UPDATE");
			
			// ingen forumtråder?
			if (mysql_num_rows($result) == 0)
			{
				ess::$b->page->add_message("Fant ingen av de merkede forumtrådene.", "error");
				redirect::handle(game::address("forum", $_GET));
			}
			
			// sett opp liste
			$deleted = array();
			$time = time();
			$del_list = array();
			$log_list = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$log_list[] = "({$row['ft_id']}, 1, ".login::$user->player->id.", $time)";
				$del_list[] = $row['ft_id'];
				$deleted[] = "{$row['ft_id']}:{$row['ft_up_id']}:".urlencode($row['ft_title']);
			}
			
			// slett forumtrådene
			ess::$b->db->query("UPDATE forum_topics SET ft_deleted = $time WHERE ft_id IN (".implode(",", $del_list).")");
			
			// opprett forumlogg
			ess::$b->db->query("INSERT INTO forum_log (flg_ft_id, flg_action, flg_up_id, flg_time) VALUES ".implode(", ", $log_list));
			
			// opprett crewlogg
			if (!$this->forum->ff || $this->forum->ff->uinfo->crew)
			{
				crewlog::log("forum_topics_delete", NULL, count($deleted), array(
					"data" => implode("\n", $deleted)
				));
			}
			
			$ant = count($del_list);
			putlog("LOG", "FORUMTRÅDER SLETTET: '".login::$user->player->data['up_name']."' slettet {$ant} forumtråder; ID: ".implode(", ",  $del_list));
			
			ess::$b->page->add_message("<b>".game::format_number($ant)."</b> forumtråd".($ant == 1 ? '' : 'er')." ble slettet!");
		}
		
		redirect::handle(game::address("forum", $_GET));
	}
	
	/**
	 * Hent forumtrådene
	 * @param boolean $normal hent normale tråder
	 * @param integer $limit_from
	 * @param integer $limit_num
	 */
	protected function get_topics($normal, $show_deleted = null, $limit_from = 0, $limit_num = 0)
	{
		// vise slettede?
		if ($show_deleted)
		{
			// hvor lenge etter den er slettet vi kan vise den
			$access_expire = max(time() - forum_topic::FF_HIDE_TIME, $this->forum->ff ? $this->forum->ff->data['ff_time_reset'] : 0);
			
			// kan vi se alle?
			if (!$this->forum->ff || access::has("mod"))
			{
				$where = "";
			}
			
			// begrens
			else
			{
				$where = " AND (ft_deleted = 0 OR ft_deleted > $access_expire)";
			}
			
			$where = "";
		}
		
		// skjul slettede
		else
		{
			$where = " AND ft_deleted = 0";
		}
		
		// hent topicsene
		$seen_q = login::$logged_in ? "fs_ft_id = ft_id AND fs_u_id = ".login::$user->id : "FALSE";
		$result = ess::$b->db->query("
			SELECT
				ft_id, ft_type, ft_title, ft_time, ft_views, ft_up_id, ft_locked, ft_replies, ft_last_reply, ft_deleted,
				up.up_name, up.up_access_level,
				r.fr_time, r.fr_up_id, r.fr_id,
				rup.up_name r_up_name, rup.up_access_level r_up_access_level,
				fs_time,
				COUNT(rs.fr_id) fs_new
			FROM
				forum_topics
				LEFT JOIN users_players up ON ft_up_id = up.up_id
				LEFT JOIN forum_replies r ON ft_last_reply = r.fr_id
				LEFT JOIN users_players rup ON r.fr_up_id = rup.up_id
				LEFT JOIN forum_seen ON $seen_q
				LEFT JOIN forum_replies rs ON rs.fr_ft_id = ft_id AND rs.fr_time > fs_time AND rs.fr_deleted = 0
			WHERE ft_fse_id = {$this->forum->id} AND ".($normal ? "ft_type = 1" : "ft_type != 1")."$where
			GROUP BY ft_id
			ORDER BY IFNULL(r.fr_time, ft_time) DESC".($limit_num > 0 ? "
			LIMIT $limit_from, $limit_num" : ""));
		
		return $result;
	}
}