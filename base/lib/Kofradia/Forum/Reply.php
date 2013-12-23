<?php namespace Kofradia\Forum;

/**
 * Forumsvar
 */
class Reply
{
	/** ID-en til forumsvaret */
	public $id;
	
	/** Informasjon om forumsvaret */
	public $info;
	
	/**
	 * Forumtråden
	 * @var \Kofradia\Forum\Topic
	 */
	public $topic;
	
	/**
	 * Constructor
	 * @param integer $reply_id
	 * @param \Kofradia\Forum\Topic $topic
	 */
	public function __construct($reply_id, Topic $topic = NULL)
	{
		$this->id = (int) $reply_id;
		
		// hent informasjon om forumsvaret
		$result = \ess::$b->db->query("
			SELECT fr_id, fr_ft_id, fr_deleted, fr_up_id, fr_text, fr_last_edit, fr_last_edit_up_id
			FROM forum_replies
			WHERE fr_id = $this->id");
		
		// fant ikke forumsvaret?
		$this->info = mysql_fetch_assoc($result);
		if (!$this->info)
		{
			return;
		}
		
		// hent forumtråden
		if ($topic) $this->get_topic($topic);
	}
	
	/**
	 * Hent full informasjon om forumsvaret
	 * For å kunne bruke HTML-malen
	 * @return array
	 */
	public function extended_info()
	{
		$result = \ess::$b->db->query("
			SELECT
				r.fr_id, r.fr_time, r.fr_up_id, r.fr_text, r.fr_last_edit, r.fr_last_edit_up_id, r.fr_deleted,
				up_name, up_access_level, up_forum_signature, up_points, up_profile_image_url,
				upr_rank_pos,
				r_time,
				COUNT(n.fr_id)+2 reply_num
			FROM
				forum_replies AS r
				LEFT JOIN users_players ON up_id = r.fr_up_id
				LEFT JOIN users_players_rank ON upr_up_id = up_id
				LEFT JOIN rapportering ON r_type = ".\rapportering::TYPE_FORUM_REPLY." AND r_type_id = r.fr_id AND r_state < 2
				LEFT JOIN forum_replies n ON n.fr_ft_id = r.fr_ft_id AND n.fr_id < r.fr_id AND n.fr_deleted = 0
			WHERE r.fr_id = $this->id
			GROUP BY r.fr_id
			ORDER BY r.fr_time ASC");
		
		$row = mysql_fetch_assoc($result);
		$row['ft_fse_id'] = $this->topic->forum->id;
		$row['ft_id'] = $this->topic->id;
		$row['fs_new'] = $this->topic->info['fs_time'] < $row['fr_time'] && \Kofradia\Forum\Category::$fs_check;
		
		return $row;
	}
	
	/** Kontroller og krev tilgang til forumsvaret */
	public function require_access()
	{
		if (($this->info['fr_deleted'] != 0 || !\login::$logged_in || $this->info['fr_up_id'] != \login::$user->player->id) && !$this->topic->forum->fmod)
		{
			$this->error_403();
			return false;
		}
		return true;
	}
	
	/**
	 * Hent informasjon om forumtråden
	 * @param \Kofradia\Forum\Topic $topic 
	 */
	public function get_topic($topic = NULL)
	{
		if ($topic)
		{
			$this->topic = $topic;
			
			// kontroller at det er riktig topic
			if ($this->topic->id != $this->info['fr_ft_id'])
			{
				$this->info = false;
				return;
			}
		}
		
		else
		{
			$this->topic = $this->get_topic_obj($this->info['fr_ft_id']);
		}
	}
	
	/** Hent forumtråd objekt */
	protected function get_topic_obj($topic_id)
	{
		return new \Kofradia\Forum\Topic($topic_id);
	}
	
	/** Ikke tilgang til forumsvaret */
	protected function error_403()
	{
		\ess::$b->page->add_message("Du har ikke tilgang til dette forumsvaret.", "error");
		
		// slettet?
		if ($this->info['fr_deleted'] != 0)
		{
			// send til forumtråden
			\redirect::handle("/forum/topic?id={$this->info['fr_ft_id']}", \redirect::ROOT);
		}
		
		else
		{
			// send til forumsvaret
			\redirect::handle("/forum/topic?id={$this->info['fr_ft_id']}&replyid=$this->id", \redirect::ROOT);
		}
	}
	
	/** Fant ikke forumsvaret */
	protected function error_404()
	{
		\ess::$b->page->add_message("Fant ikke forumsvaret.", "error");
		
		// send til forumoversikten
		\redirect::handle("/forum/forum", \redirect::ROOT);
	}
	
	/** Slett forumsvaret */
	public function delete()
	{
		// kontroller tilgang til forumsvaret
		if (!$this->require_access()) return;
		
		// allerede slettet?
		if ($this->info['fr_deleted'] != 0)
		{
			$this->delete_dupe();
			return;
		}
		
		// er forumtråden låst?
		if ($this->topic->info['ft_locked'] != 0 && !$this->topic->forum->fmod)
		{
			$this->delete_error_locked();
			return;
		}
		
		// kontroller blokkering
		if ($this->topic->forum->check_block()) return;
		
		// slett forumsvaret
		if (!$this->delete_action())
		{
			// anta at det allerede er slettet
			$this->delete_dupe();
			return;
		}
		
		// logg
		\Kofradia\Forum\Log::add_reply_deleted($this);
		
		// fullført
		$this->delete_complete();
	}
	
	/** Forumtråden er låst */
	protected function delete_error_locked()
	{
		\ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke slette forumsvaret.", "error");
	}
	
	/** Utfør selve slettingen av forumsvaret */
	protected function delete_action()
	{
		// forsøk å slett forumsvaret
		\ess::$b->db->query("UPDATE forum_replies SET fr_deleted = 1 WHERE fr_id = $this->id AND fr_deleted = 0");
		if (\ess::$b->db->affected_rows() == 0) return false;
		
		// var dette siste forumsvar i forumtråden?
		if ($this->id == $this->topic->info['ft_last_reply'])
		{
			// hent siste forumsvaret i forumtråden
			$result = \ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_deleted = 0 ORDER BY fr_id DESC LIMIT 1");
			$reply_id = mysql_num_rows($result) > 0 ? mysql_result($result, 0) : 0;
			if (!$reply_id) $reply_id = "NULL";
			
			// sett som siste forumsvar
			\ess::$b->db->query("UPDATE forum_topics SET ft_last_reply = $reply_id, ft_replies = ft_replies - 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// senk telleren over antall forumsvar
		else
		{
			\ess::$b->db->query("UPDATE forum_topics SET ft_replies = ft_replies - 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// senk telleren til spilleren over antall forumsvar
		if ($this->topic->forum->ff)
		{
			\ess::$b->db->query("UPDATE ff_members SET ffm_forum_replies = ffm_forum_replies - 1 WHERE ffm_up_id = {$this->info['fr_up_id']} AND ffm_ff_id = {$this->topic->forum->ff->id}");
			\ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_replies = up_forum_ff_num_replies - 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		else
		{
			\ess::$b->db->query("UPDATE users_players SET up_forum_num_replies = up_forum_num_replies - 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		
		return true;
	}
	
	/** Forumsvaret er allerede slettet */
	protected function delete_dupe()
	{
		\ess::$b->page->add_message("Forumsvaret er allerede slettet.", "error");
	}
	
	/** Forumsvaret ble slettet */
	protected function delete_complete()
	{
		\ess::$b->page->add_message("Forumsvaret ble slettet. Antall forumsvar brukeren har hatt ble redusert med 1.");
		
		// hent neste forumsvar
		$result = \ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id > $this->id AND fr_deleted = 0 ORDER BY fr_id LIMIT 1");
		
		// eller hente forrige forumsvar
		if (mysql_num_rows($result) == 0)
		{
			$result = \ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id < $this->id AND fr_deleted = 0 ORDER BY fr_id DESC LIMIT 1");
		}
		
		// har vi noe neste forumsvar?
		if ($row = mysql_fetch_assoc($result))
		{
			// hent antall forumsvar før dette forumsvaret
			$result = \ess::$b->db->query("SELECT COUNT(fr_id) FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id < {$row['fr_id']} AND fr_deleted = 0");
			$skip = mysql_result($result, 0);
			
			// send til riktig forumsvar
			$page = ceil($skip / $this->topic->replies_per_page);
			\redirect::handle("/forum/topic?id={$this->topic->id}&p=$page#m_{$row['fr_id']}", \redirect::ROOT);
		}
		
		\redirect::handle();
	}
	
	/** Gjenopprett forumsvaret */
	public function restore()
	{
		// kontroller tilgang til å gjenopprettet forumsvaret
		if (!$this->topic->forum->fmod)
		{
			$this->error_403();
			return;
		}
		
		// ikke slettet?
		if ($this->info['fr_deleted'] == 0)
		{
			$this->restore_dupe();
		}
		
		// kontroller blokkering
		if ($this->topic->forum->check_block()) return;
		
		// gjenopprett forumsvaret
		if (!$this->restore_action())
		{
			// anta at det allerede er gjenopprettet
			$this->restore_dupe();
			return;
		}
		
		// logg
		\Kofradia\Forum\Log::add_reply_restored($this);
		
		// fullført
		$this->restore_complete();
	}
	
	/** Utfør selve gjenopprettingen av forumsvaret */
	protected function restore_action()
	{
		// forsøk å gjenopprett forumsvaret
		\ess::$b->db->query("UPDATE forum_replies SET fr_deleted = 0 WHERE fr_id = $this->id AND fr_deleted != 0");
		if (\ess::$b->db->affected_rows() == 0) return false;
		
		// er dette det siste forumsvaret i forumtråden?
		if ($this->id > $this->topic->info['ft_last_reply'])
		{
			// sett som siste forumsvar
			\ess::$b->db->query("UPDATE forum_topics SET ft_last_reply = $this->id, ft_replies = ft_replies + 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// øk telleren over antall forumsvar
		else
		{
			\ess::$b->db->query("UPDATE forum_topics SET ft_replies = ft_replies + 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// øk telleren til brukeren over antall forumsvar
		if ($this->topic->forum->ff)
		{
			\ess::$b->db->query("UPDATE ff_members SET ffm_forum_replies = ffm_forum_replies + 1 WHERE ffm_up_id = {$this->info['fr_up_id']} AND ffm_ff_id = {$this->topic->forum->ff->id}");
			\ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_replies = up_forum_ff_num_replies + 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		else
		{
			\ess::$b->db->query("UPDATE users_players SET up_forum_num_replies = up_forum_num_replies + 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		
		return true;
	}
	
	/** Forumsvaret er allerede gjenopprettet */
	protected function restore_dupe()
	{
		\ess::$b->page->add_message("Forumsvaret er allerede gjenopprettet.", "error");
		
		// send til forumsvaret
		\redirect::handle("/forum/topic?id={$this->topic->id}&replyid=$this->id", \redirect::ROOT);
	}
	
	/** Forumsvaret ble gjenopprettet */
	protected function restore_complete()
	{
		\ess::$b->page->add_message("Forumsvaret ble gjenopprettet. Antall forumsvar brukeren har hatt ble økt med 1.");
		
		// send til forumsvaret
		\redirect::handle("/forum/topic?id={$this->topic->id}&replyid=$this->id", \redirect::ROOT);
	}
	
	/**
	 * Rediger forumsvaret
	 * @param string $text nytt innhold
	 */
	public function edit($text)
	{
		if (!\login::$logged_in) throw new HSNotLoggedIn();
		
		// kontroller tilgang til forumsvaret
		if (!$this->require_access()) return;
		
		// er forumtråden låst?
		if ($this->topic->info['ft_locked'] != 0 && !$this->topic->forum->fmod)
		{
			$this->edit_error_locked();
			return;
		}
		
		// kontroller blokkering
		if ($this->topic->forum->check_block()) return;
		
		// kontroller tekstlengde
		$text = trim($text);
		if (\Kofradia\Forum\Category::check_length($text) < \Kofradia\Forum\Category::REPLY_MIN_LENGTH)
		{
			$this->edit_error_length();
			return;
		}
		
		// ingen endringer utført?
		if ($text == $this->info['fr_text'])
		{
			$this->edit_error_nochange();
			return;
		}
		
		// rediger forumsvaret
		\ess::$b->db->query("UPDATE forum_replies SET fr_text = ".\ess::$b->db->quote($text).", fr_last_edit = ".time().", fr_last_edit_up_id = ".\login::$user->player->id." WHERE fr_id = $this->id");
		
		// ble ikke oppdatert?
		if (\ess::$b->db->affected_rows() == 0)
		{
			// mest sannsynlig finnes ikke forumsvaret, eller så er det oppdatert to ganger samme sekund med samme innhold av samme bruker
			$this->edit_error_failed();
			return;
		}
		
		$old_data = array(
			"fr_text" => $this->info['fr_text']
		);
		
		// lagre lokale endringer
		$this->info['fr_text'] = $text;
		$this->info['fr_last_edit'] = time();
		$this->info['fr_last_edit_up_id'] = \login::$user->player->id;
		
		// logg
		\Kofradia\Forum\Log::add_reply_edited($this, $old_data);
		
		// fullført
		$this->edit_complete();
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		\ess::$b->page->add_message("Forumsvaret ble ikke redigert.", "error");
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		\ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke redigere forumsvar i den.", "error");
	}
	
	/** For kort lengde i forumsvaret */
	protected function edit_error_length()
	{
		\ess::$b->page->add_message("Forumsvaret kan ikke inneholde færre enn ".\Kofradia\Forum\Category::REPLY_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		\ess::$b->page->add_message("Ingen endringer ble utført.", "error");
	}
	
	/** Forumsvaret ble redigert */
	protected function edit_complete()
	{
		\ess::$b->page->add_message("Forumsvaret ble redigert.");
		
		// send til forumsvaret
		\redirect::handle("/forum/topic?id={$this->topic->id}&replyid=$this->id", \redirect::ROOT);
	}
	
	/**
	 * Annonser forumsvaret
	 * Kan også brukes for å annonsere et forumsvar på nytt
	 */
	public function announce()
	{
		// finn riktig brukernavn
		if (\login::$logged_in && $this->info['fr_up_id'] == \login::$user->player->id)
		{
			$name = \login::$user->player->data['up_name'];
		}
		else
		{
			$result = \ess::$b->db->query("SELECT up_name FROM users_players WHERE up_id = {$this->info['fr_up_id']}");
			$row = mysql_fetch_assoc($result);
			if (!$row)
			{
				$name = "Ukjent";
			}
			else
			{
				$name = $row['up_name'];
			}
		}
		
		// normalt forum?
		if ($this->topic->forum->id <= 4)
		{
			// logg på IRC
			putlog("INFO", "FORUMSVAR (Crew): (".$this->topic->forum->get_name().") '{$name}' svarte i '{$this->topic->info['ft_title']}' ".\ess::$s['path']."/forum/topic?id={$this->topic->id}&replyid=$this->id");
		}
		
		// crewforumet, crewarkivforumet og idemyldringsforumet
		elseif ($this->topic->forum->id >= 5 && $this->topic->forum->id <= 7)
		{
			// legg til hendelse i spilleloggen
			$type = $this->topic->forum->id == 5 ? 'crewforum_svar' : ($this->topic->forum->id == 6 ? 'crewforuma_svar' : 'crewforumi_svar');
			$access_levels = implode(",", \ess::$g['access']['crewet']);
			\ess::$b->db->query("INSERT INTO users_log SET time = ".time().", ul_up_id = 0, type = ".intval(\gamelog::$items[$type]).", note = ".\ess::$b->db->quote($this->info['fr_up_id']."#".$this->id.":".$this->topic->info['ft_title']).", num = {$this->topic->id}");
			
			$upd = \login::$logged_in ? " AND (u_id != ".\login::$user->id." OR u_log_crew_new > 0)" : "";
			\ess::$b->db->query("UPDATE users SET u_log_crew_new = u_log_crew_new + 1 WHERE u_access_level IN ($access_levels)$upd");
		}
	}
}