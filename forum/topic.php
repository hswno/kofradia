<?php

require "../base.php";

new page_forum_topic();
class page_forum_topic
{
	/**
	 * Forumtråden
	 * @var forum_topic
	 */
	protected $topic;
	
	protected $fmod;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		// endre signatur i forumet
		if (login::$logged_in && (isset($_GET['show_signature']) || isset($_GET['hide_signature'])))
		{
			if (isset($_GET['show_signature']) && login::$user->data['u_forum_show_signature'] == 0)
			{
				ess::$b->db->query("UPDATE users SET u_forum_show_signature = 1 WHERE u_id = ".login::$user->id);
			}
			elseif (isset($_GET['hide_signature']) && login::$user->data['u_forum_show_signature'] == 1)
			{
				ess::$b->db->query("UPDATE users SET u_forum_show_signature = 0 WHERE u_id = ".login::$user->id);
			}
			
			redirect::handle(game::address("topic", $_GET, array("show_signature", "hide_signature")));
		}
		
		// hent forumtråd
		essentials::load_module("forum");
		$this->topic = new forum_topic(getval("id"));
		$this->fmod = $this->topic->forum->fmod;
		
		// sett standard redirect
		redirect::store("topic?id={$this->topic->id}");
		
		// slette forumtråden?
		if (isset($_POST['delete']))
		{
			// forsøk å slette forumtråden
			validate_sid();
			$this->topic->delete();
		}
		
		// gjenopprette forumtråden?
		if (isset($_POST['restore']))
		{
			// forsøk å gjenopprette forumtråden
			validate_sid();
			$this->topic->restore();
		}
		
		// slette forumsvar?
		if (isset($_GET['delete_reply']))
		{
			validate_sid();
			
			// finn forumsvaret
			if ($reply = $this->topic->get_reply($_GET['delete_reply']))
			{
				// forsøk å slett forumsvaret
				$reply->delete();
			}
			
			else
			{
				ess::$b->page->add_message("Fant ikke forumsvaret.", "error");
				redirect::handle();
			}
		}
		
		// gjenopprette forumsvar?
		if (isset($_GET['restore_reply']))
		{
			validate_sid();
			
			// finn forumsvaret
			if ($reply = $this->topic->get_reply($_GET['restore_reply']))
			{
				// forsøk å gjenopprett forumsvaret
				$reply->restore();
			}
			
			else
			{
				ess::$b->page->add_message("Fant ikke forumsvaret.", "error");
				redirect::handle();
			}
		}
		
		// legge til nytt svar?
		if (isset($_GET['reply']) && isset($_POST['post']) && isset($_POST['text']))
		{
			// ikke slå sammen?
			$no_concatenate = isset($_POST['no_concatenate']) && access::has("forum_mod");
			
			// annonsere?
			$announce = isset($_POST['announce']) && access::has("forum_mod");
			
			// har vi ingen aktiv spiller?
			if (count(login::$user->lock) == 1 && in_array("player", login::$user->lock))
			{
				ess::$b->page->add_message("Du har ingen aktiv spiller.", "error");
				redirect::handle();
			}
			
			// forsøk å legg til svaret
			$this->topic->add_reply($_POST['text'], $no_concatenate, $announce);
		}
		
		
		// den aktuelle siden (sjekk for replyid før vi retter sidetall)
		$pagei = new pagei(pagei::ACTIVE_GET, "p", pagei::PER_PAGE, $this->topic->replies_per_page);
		
		
		// sjekk om vi skal vise slettede svar
		if (isset($_GET['show_deleted']) && $this->fmod)
		{
			$show_deleted = true;
			$deleted = "";
		}
		else
		{
			$show_deleted = false;
			$deleted = " AND fr_deleted = 0";
		}
		
		
		// skal vi vise status for meldingene?
		$fs_id = 0;
		
		
		// skal vi vise et bestemt forumsvar?
		$reply_id = false;
		if (isset($_GET['replyid']))
		{
			// hent forumsvaret
			$reply_id = intval($_GET['replyid']);
			$result = ess::$b->db->query("SELECT fr_id, fr_deleted FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id = $reply_id");
			$row = mysql_fetch_assoc($result);
			
			// fant ikke forumsvaret, eller slettet uten tilgang?
			if (!$row || ($row['fr_deleted'] != 0 && !$this->fmod))
			{
				ess::$b->page->add_message("Fant ikke forumsvaret du refererte til.", "error");
				redirect::handle();
			}
			
			// slettet?
			if ($row['fr_deleted'] != 0 && !$show_deleted)
			{
				$show_deleted = true;
				$deleted = "";
			}
			
			// finn ut antall forumsvar før
			$result = ess::$b->db->query("SELECT COUNT(fr_id) FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id < $reply_id$deleted");
			$reply_num = mysql_result($result, 0) + 1;
			
			// sett opp sidetallet og sett til aktiv side
			$pagei->__construct(pagei::ACTIVE, ceil($reply_num / $this->topic->replies_per_page));
		}
		
		// skal vi gå til nyeste melding?
		elseif (isset($_GET['fs']) && forum::$fs_check)
		{
			// har vi ikke status?
			if (empty($this->topic->info['fs_time']))
			{
				// sørg for at vi er på side 1
				if ($pagei->active != 1)
				{
					// gå til første side
					redirect::handle(game::address(PHP_SELF, $_GET, array("p")), redirect::SERVER);
				}
			}
			
			// kontroller at vi er på riktig side
			else
			{
				// finn neste forumsvar etter fs_time
				$result = ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_time > {$this->topic->info['fs_time']}$deleted ORDER BY fr_time LIMIT 1");
				$row = mysql_fetch_assoc($result);
				
				// fant ikke noe forumsvar?
				if (!$row)
				{
					// finn det siste innlegget
					$result = ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id}$deleted ORDER BY fr_time DESC LIMIT 1");
					$row = mysql_fetch_assoc($result);
				}
				
				// fremdeles ingen forumsvar å gå til?
				if (!$row)
				{
					// sørg for at vi er på side 1
					if ($pagei->active != 1)
					{
						// gå til første side
						redirect::handle(game::address(PHP_SELF, $_GET, array("p")), redirect::SERVER);
					}
				}
				
				// gå til nyeste forumsvar
				else
				{
					// finn ut antall forumsvar før det vi skal gå til
					$result = ess::$b->db->query("SELECT COUNT(fr_id) FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id < {$row['fr_id']}$deleted");
					$reply_num = mysql_result($result, 0) + 1;
					
					// sett opp sidetallet og kontroller at vi er på riktig side
					$page = ceil($reply_num / $this->topic->replies_per_page);
					if ($pagei->active != $page)
					{
						// videresend til den riktige siden
						redirect::handle(game::address(PHP_SELF, $_GET, array("p"), array("p" => $page)), redirect::SERVER);
					}
					
					$fs_id = $row['fr_id'];
				}
			}
		}
		
		
		// viser vi slettede meldinger?
		if ($show_deleted)
		{
			// finn ut hvor mange meldinger som er slettet
			$result = ess::$b->db->query("SELECT COUNT(fr_id) FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_deleted != 0");
			$count = mysql_result($result, 0);
			
			ess::$b->page->add_message("Du viser slettede forumsvar. Denne forumtråden har <b>$count</b> ".fword("slettet forumsvar", "slettede forumsvar", $count).".", NULL, "top");
		}
		
		
		// øk visningstelleren hvis vi ikke har besøkt denne forumtråden de siste 10 min
		if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'forum_topics_visited'][$this->topic->id]) || $_SESSION[$GLOBALS['__server']['session_prefix'].'forum_topics_visited'][$this->topic->id] + 600 <= time())
		{
			ess::$b->db->query("UPDATE forum_topics SET ft_views = ft_views + 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// lagre som vist
		$_SESSION[$GLOBALS['__server']['session_prefix'].'forum_topics_visited'][$this->topic->id] = time();
		
		
		// tittel på siden
		$this->topic->forum->add_title();
		ess::$b->page->add_title($this->topic->info['ft_title']);
		
		
		// finn ut antall svar vi har synlige
		if ($show_deleted)
		{
			$result = ess::$b->db->query("SELECT COUNT(fr_id) FROM forum_replies WHERE fr_ft_id = {$this->topic->id}$deleted");
			$replies_count = mysql_result($result, 0);
		}
		else
		{
			$replies_count = $this->topic->info['ft_replies'];
		}
		
		// korriger aktiv side
		$pagei->__construct(pagei::TOTAL, $replies_count);
		
		// skal vi vise svarskjema?
		$reply_form = login::$logged_in && isset($_GET['reply']) && !$reply_id;
		if ($reply_form)
		{
			// sørg for at vi er på siste siden
			$pagei->__construct(pagei::ACTIVE_LAST);
		}
		
		
		echo '
<div class="bg1_c forumw">
	<h1 class="bg1">'.htmlspecialchars($this->topic->info['ft_title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="forum?id='.$this->topic->forum->id.'">'.htmlspecialchars($this->topic->forum->get_name()).'</a></p>
	<p class="h_right">'.($this->topic->info['ft_locked'] == 1 ? '
		Låst emne!' : '').(login::$logged_in && $this->topic->info['ft_deleted'] == 0 && ($this->topic->info['ft_locked'] != 1 || $this->fmod) ? '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array("replyid"), array("reply" => true))).'" class="forum_link_replyform">Opprett svar</a>' : '').($this->fmod ? ($show_deleted ? '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array("show_deleted", "replyid"))).'">Skjul slettede svar</a>' : '
		<a href="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array(), array("show_deleted" => true))).'">Vis slettede svar</a>') : '').'
	</p>
	<div class="bg1">
<div class="forum" id="forum_topic_container">';
		
		
		// vise sidetall øverst?
		if ($pagei->pages > 1)
		{
			echo '
	<p class="c">'.$pagei->pagenumbers(game::address(PHP_SELF, $_GET, array("p", "replyid", "fs")), game::address(PHP_SELF, $_GET, array("p", "replyid", "fs"), array("p" => "_pageid_"))).'</p>';
		}
		
		
		// hent forumsvar
		$replies = array();
		$up_ids = array();
		$id_list = array();
		$last_time = 0;
		$replies_last_edit = array();
		
		if ($replies_count > 0)
		{
			// hent svarene
			$result = ess::$b->db->query("
				SELECT
					fr_id, fr_time, fr_up_id, fr_text, fr_deleted, fr_last_edit, fr_last_edit_up_id,
					up_name, up_access_level, up_forum_signature, up_points, up_profile_image_url,
					upr_rank_pos,
					r_time
				FROM
					forum_replies
					LEFT JOIN users_players ON up_id = fr_up_id
					LEFT JOIN users_players_rank ON upr_up_id = up_id
					LEFT JOIN rapportering ON r_type = ".rapportering::TYPE_FORUM_REPLY." AND r_type_id = fr_id AND r_state < 2
				WHERE fr_ft_id = {$this->topic->id}$deleted
				GROUP BY fr_id
				ORDER BY fr_time ASC
				LIMIT {$pagei->start}, {$pagei->per_page}");
			
			while ($row = mysql_fetch_assoc($result))
			{
				$id_list[] = $row['fr_id'];
				$up_ids[] = $row['fr_up_id'];
				$last_time = $row['fr_time'];
				$replies_last_edit[$row['fr_id']] = $row['fr_last_edit'];
				
				$replies[] = $row;
			}
		}
		
		// hent inn familierelasjoner
		$up_ids[] = $this->topic->info['ft_up_id'];
		$this->topic->forum->ff_get_familier($up_ids);
		
		// vis hovedinnlegget
		echo $this->topic->forum->template_topic($this->topic->extended_info());
		
		// vis forumsvar
		if (count($replies) > 0)
		{
			// scrolle til første forumsvar på andre enn første side
			if ($pagei->active > 1 && !$reply_form && !$reply_id && !$fs_id)
			{
				echo '
	<div id="forum_scroll_here"></div>';
			}
			
			$reply_num = $pagei->per_page * ($pagei->active - 1) + 1;
			foreach ($replies as $row)
			{
				$row['ft_fse_id'] = $this->topic->forum->id;
				$row['ft_id'] = $this->topic->id;
				$row['reply_num'] = ++$reply_num;
				$row['fs_new'] = forum::$fs_check && $this->topic->info['fs_time'] < $row['fr_time'];
				
				if ($reply_id == $row['fr_id'])
					$row['class_extra'] = 'forum_focus';
				
				if ($reply_id == $row['fr_id'] || $fs_id == $row['fr_id'])
				{
					$row['h2_extra'] = 'id="forum_scroll_here"';
					
					// vis bokser her
					if ($reply_id == $row['fr_id'] || $fs_id == $row['fr_id'])
					{
						echo '
	<boxes />';
					}
				}
				
				// vis html for svaret
				echo $this->topic->forum->template_topic_reply($row);
			}
		}
		
		// oppdatere sist sett?
		$time = $last_time != 0 ? $last_time : $this->topic->info['ft_time'];
		
		// legge til?
		if (login::$logged_in && empty($this->topic->info['fs_time']))
		{
			ess::$b->db->query("INSERT IGNORE INTO forum_seen SET fs_ft_id = {$this->topic->id}, fs_u_id = ".login::$user->id.", fs_time = $time");
		}
		
		// oppdater
		elseif (login::$logged_in && $time > $this->topic->info['fs_time'])
		{
			ess::$b->db->query("UPDATE forum_seen SET fs_time = GREATEST(fs_time, $time) WHERE fs_ft_id = {$this->topic->id} AND fs_u_id = ".login::$user->id);
		}
		
		
		echo '
</div>';
		
		
		// vis svarskjema
		echo '
<div'.($reply_form ? '' : ' style="display: none"').' id="container_reply">'.($reply_form ? '
	<boxes />' : '').'
	<form action="'.htmlspecialchars(game::address("topic", $_GET, array("replyid", "fs"), array("reply" => true))).'" method="post"'.($reply_form ? ' id="forum_scroll_here"' : '').'>
		<div class="section forum_reply_edit_c">
			<h2>Svar</h2>
			<dl class="dl_2x">
				<dt>Innhold</dt>
				<dd><textarea name="text" rows="20" cols="75" id="replyText">'.htmlspecialchars(postval("text")).'</textarea></dd>';
		
		// vise ekstra alternativer?
		if (access::has("forum_mod") || ($this->topic->forum->id >= 5 && $this->topic->forum->id <= 7))
		{
			$no_concat = isset($_POST['no_concatenate']) || ($_SERVER['REQUEST_METHOD'] != "POST" && $this->topic->forum->id >= 5 && $this->topic->forum->id <= 7);
			$announce_text = $this->topic->forum->id >= 5 && $this->topic->forum->id <= 7
				? 'Legg til logg i spilleloggen til medlemmer av Crewet.'
				: 'Annonser på #kofradia kanalen';
			
			echo '
				<dt>Ekstra</dt>
				<dd>'.(!$this->topic->forum->ff ? '
					<input type="checkbox" name="announce" id="announce"'.(isset($_POST['announce']) ? ' checked="checked"' : '').' /><label for="announce"> '.$announce_text.'</label><br />' : '').'
					<input type="checkbox" name="no_concatenate" id="no_concatenate"'.($no_concat ? ' checked="checked"' : '').' /><label for="no_concatenate"> <u>Ikke</u> kombiner sammen med siste melding.</label>
				</dd>';
		}
		
		echo '
			</dl>
			<p class="c">
				'.show_sbutton("Legg til svar", 'name="post" accesskey="s" id="forum_reply_button_add"').'
				'.show_sbutton("Forhåndsvis", 'name="preview" accesskey="p" id="forum_reply_button_preview"').'
			</p>
		</div>
		<div id="reply_preview" class="forum">';
		
		// forhåndsvise?
		if (login::$logged_in && isset($_POST['preview']))
		{
			$data = array(
				"ft_id" => $this->topic->id,
				"fr_text" => postval("text"),
				"fr_up_id" => login::$user->player->id,
				"up_name" => login::$user->player->data['up_name'],
				"up_access_level" => login::$user->player->data['up_access_level'],
				"up_points" => login::$user->player->data['up_points'],
				"upr_rank_pos" => login::$user->player->data['upr_rank_pos'],
				"up_forum_signature" => login::$user->player->data['up_forum_signature'],
				"up_profile_image_url" => login::$user->player->data['up_profile_image_url'],
				"fs_new" => forum::$fs_check
			);
			
			echo forum::template_topic_reply_preview($data);
		}
		
		echo '</div>
	</form>
</div>';
		
		
		// linker i bunn
		if (login::$logged_in)
		{
			echo '
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<div class="forum_footer_links">';
			
			// slette/gjenopprette lenker
			if ($this->fmod || $this->topic->info['ft_up_id'] == login::$user->player->id)
			{
				echo '
		<p class="left">'.($this->topic->info['ft_deleted'] == 0 ? '
			<span class="red">'.show_sbutton("Slett emnet", 'name="delete" onclick="return confirm(\'Sikker?!\')"').'</span>' : '
			<span class="green">'.show_sbutton("Gjenopprett emnet", 'name="restore" onclick="return confirm(\'Sikker?!\')"').'</span>').'
		</p>';
		}
			
			// alternativer
			echo '
		<p class="right">';
			
			// reply lenke
			if (!$reply_form && $this->topic->info['ft_deleted'] == 0 && ($this->topic->info['ft_locked'] == 0 || $this->fmod))
			{
				echo '
			<a href="'.htmlspecialchars(game::address("topic", $_GET, array("replyid"), array("reply" => true))).'" class="button forum_link_replyform" accesskey="r">Opprett svar</a>';
			}
			
			// signatur lenker
			echo (login::$user->data['u_forum_show_signature'] == 1 ? '
			<a href="'.htmlspecialchars(game::address("topic", $_GET, array("show_signature"), array("hide_signature" => true))).'" class="button">Skjul signaturer</a>' : '
			<a href="'.htmlspecialchars(game::address("topic", $_GET, array("hide_signature"), array("show_signature" => true))).'" class="button">Vis signaturer</a>');
			
			echo '
		</p>';
		}
		
		// sidetall
		if ($pagei->pages > 1)
		{
			echo '
		<p class="center">'.$pagei->pagenumbers(game::address(PHP_SELF, $_GET, array("p", "replyid", "fs", "reply")), game::address(PHP_SELF, $_GET, array("p", "replyid", "fs", "reply"), array("p" => "_pageid_"))).'</p>';
		}
		
		echo '
	</div>
</form>
	</div>
</div>';
		
		
		// div javascript
		// sørg for at meldingene blir oppdatert og at nye meldinger blr hentet hvis vi er på siste side
		ess::$b->page->add_js_file(ess::$s['relative_path']."/js/forum.js");
		ess::$b->page->add_js('
		sm_scripts.report_links();');
		ess::$b->page->add_js_domready('
	var topic = new ForumTopic('.$this->topic->id.', '.js_encode($id_list).', '.js_encode($replies_last_edit).', '.($pagei->pages == $pagei->active ? 'true' : 'false').', '.($show_deleted ? 'true' : 'false').', '.($this->fmod ? 'true' : 'false').', '.((int)$this->topic->info['ft_last_edit']).');'.($reply_form ? '
	topic.reply_form_show();' : ''));
		
		$this->topic->forum->load_page();
	}
}