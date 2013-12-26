<?php namespace Kofradia\Forum;

/**
 * Forumlogg
 * @static
 */
class Log
{
	/** Handling: Forumtråd slettet */
	const TOPIC_DELETED = 1;
	
	/** Handling: Forumtråd gjenopprettet */
	const TOPIC_RESTORED = 5;
	
	/** Handling: Forumsvar slettet */
	const REPLY_DELETED = 2;
	
	/** Handling: Forumsvar gjenopprettet */
	const REPLY_RESTORED = 6;
	
	/**
	 * Legg til i forum_log
	 */
	protected static function add_log($action, $topic_id, $reply_id = NULL)
	{
		if (!\login::$logged_in) throw new HSNotLoggedIn();
		
		if (!$reply_id) $reply_id = "NULL";
		\ess::$b->db->query("INSERT INTO forum_log SET flg_ft_id = $topic_id, flg_fr_id = $reply_id, flg_action = $action, flg_up_id = ".\login::$user->player->id.", flg_time = ".time());
	}
	
	/**
	 * Tunnel til putlog funksjonen
	 * @return unknown
	 */
	protected static function putlog(Category $forum, $location, $msg)
	{
		// ff?
		if ($forum->ff)
		{
			return putlog("FF", ucfirst($forum->ff->type['refobj'])." {$forum->ff->data["ff_name"]} - " . $msg);
		}
		
		return putlog($location, $msg);
	}
	
	/**
	 * Legg til crewlogg
	 */
	protected static function crewlog(Category $forum, $type, $a_up_id, $log, $data)
	{
		if ($forum->ff)
		{
			$ff_prefix = "f_";
			$data = array_merge($data, array(
				"ff_type" => $forum->ff->type['refobj'],
				"ff_id" => $forum->ff->id,
				"ff_name" => $forum->ff->data['ff_name']
			));
		}
		
		else
		{
			$ff_prefix = "";
		}
		
		\crewlog::log($ff_prefix.$type, $a_up_id, $log, $data);
	}
	
	/**
	 * Skal dette logges som crewhandling?
	 */
	protected static function is_crewlog(Category $forum, $up_id = null)
	{
		return (!$forum->ff && $up_id != \login::$user->player->id) || ($forum->ff && $forum->ff->uinfo && $forum->ff->uinfo->crew);
	}
	
	/**
	 * Legg til forumtråd
	 * @param \Kofradia\Forum\Category $forum
	 * @param array $data (ft_id, ft_title, ft_type)
	 */
	public static function add_topic_added(Category $forum, $data)
	{
		// finn ut hvor loggen skal plasseres
		$location = "INFO";
		
		// crewforum, crewforum arkiv eller idémyldringsforumet
		if ($forum->id >= 5 && $forum->id <= 7)
		{
			$location = "CREWCHAN";
			
			// legg til hendelse i spilleloggen
			$type = $forum->id == 5 ? 'crewforum_emne' : ($forum->id == 6 ? 'crewforuma_emne' : 'crewforumi_emne');
			$access_levels = implode(",", \ess::$g['access']['crewet']);
			\ess::$b->db->query("INSERT INTO users_log SET time = ".time().", ul_up_id = 0, type = ".intval(\gamelog::$items[$type]).", note = ".\ess::$b->db->quote(\login::$user->player->id.":".$data['ft_title']).", num = {$data['ft_id']}");
			\ess::$b->db->query("UPDATE users SET u_log_crew_new = u_log_crew_new + 1 WHERE u_access_level IN ($access_levels) AND (u_id != ".\login::$user->id." OR u_log_crew_new > 0)");
			
			// send e-post til crewet
			$email = new email();
			$email->text = "*{$data['ft_title']}* ble opprettet av ".\login::$user->player->data['up_name']."\r\n".\ess::$s['path']."/forum/topic?id={$data['ft_id']}\r\n\r\nForum: ".$forum->get_name()."\r\nAutomatisk melding for Kofradia Crewet";
			$result = \ess::$b->db->query("SELECT u_email FROM users WHERE u_access_level IN ($access_levels) AND u_id != ".\login::$user->id);
			while ($row = mysql_fetch_assoc($result))
			{
				$email->send($row['u_email'], \login::$user->player->data['up_name']." opprettet {$data['ft_title']} -- ".$forum->get_name()."");
			}
		}
		
		//Evalueringsforum
		elseif ($forum->id == 4)
		{
			$location = "";
				
			// legg til hendelse i spilleloggen
			$type = $forum->id == 4 ? 'crewforume_emne' : 'crewforum_emne';
			$access_levels = implode(",", \ess::$g['access']['admin']);
			\ess::$b->db->query("INSERT INTO users_log SET time = ".time().", ul_up_id = 0, type = ".intval(\gamelog::$items[$type]).", note = ".\ess::$b->db->quote(\login::$user->player->id.":".$data['ft_title']).", num = {$data['ft_id']}");
			\ess::$b->db->query("UPDATE users SET u_log_crew_new = u_log_crew_new + 1 WHERE u_access_level IN ($access_levels) AND (u_id != ".\login::$user->id." OR u_log_crew_new > 0)");
		
			// send e-post til crewet
			$email = new email();
			$email->text = "*{$data['ft_title']}* ble opprettet av ".\login::$user->player->data['up_name']."\r\n".\ess::$s['path']."/forum/topic?id={$data['ft_id']}\r\n\r\nForum: ".$forum->get_name()."\r\nAutomatisk melding for Kofradia Crewet";
			$result = \ess::$b->db->query("SELECT u_email FROM users WHERE u_access_level IN ($access_levels) AND u_id != ".\login::$user->id);
			while ($row = mysql_fetch_assoc($result))
			{
				$email->send($row['u_email'], \login::$user->player->data['up_name']." opprettet {$data['ft_title']} -- ".$forum->get_name()."");
			}
		}
		
		elseif (!$forum->ff)
		{
			// live-feed
			\livefeed::add_row('<user id="'.\login::$user->player->id.'" /> opprettet <a href="'.\ess::$s['relative_path'].'/forum/topic?id='.$data['ft_id'].'">'.htmlspecialchars($data['ft_title']).'</a> i '.htmlspecialchars($forum->get_name()).'.');
		}
		
		// legg til som logg
		self::putlog($forum, $location, "FORUMTRÅD: (".$forum->get_name().") '".\login::$user->player->data['up_name']."' opprettet '{$data['ft_title']}' ".\ess::$s['path']."/forum/topic?id={$data['ft_id']}");
	}
	
	/**
	 * Slettet forumtråd
	 * @param \Kofradia\Forum\Topic $topic
	 */
	public static function add_topic_deleted(Topic $topic)
	{
		// legg til i forum_log
		self::add_log(self::TOPIC_DELETED, $topic->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD SLETTET: '".\login::$user->player->data['up_name']."' slettet forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) ".\ess::$s['path']."/forum/topic?id={$topic->id}");
		
		// legg til crewlogg
		if (self::is_crewlog($topic->forum, $topic->info['ft_up_id']))
		{
			self::crewlog($topic->forum, "forum_topic_delete", $topic->info['ft_up_id'], null, array(
				"topic_id" => $topic->id,
				"topic_title" => $topic->info['ft_title']
			));
		}
		
		// ff-log
		if ($topic->forum->ff)
		{
			$topic->forum->ff->add_log("forum_topic_delete", "".\login::$user->player->id.":".$topic->info['ft_id'].":".urlencode($topic->info['ft_title']));
		}
	}
	
	/**
	 * Gjenopprettet forumtråd
	 * @param \Kofradia\Forum\Topic $topic
	 */
	public static function add_topic_restored(Topic $topic)
	{
		// legg til i forum_log
		self::add_log(self::TOPIC_RESTORED, $topic->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD GJENOPPRETTET: '".\login::$user->player->data['up_name']."' gjenoppretttet forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) ".\ess::$s['path']."/forum/topic?id={$topic->id}");
		
		// legg til crewlogg
		if (self::is_crewlog($topic->forum, $topic->info['ft_up_id']))
		{
			self::crewlog($topic->forum, "forum_topic_restore", $topic->info['ft_up_id'], NULL, array(
				"topic_id" => $topic->id,
				"topic_title" => $topic->info['ft_title']
			));
		}
		
		// ff-log
		if ($topic->forum->ff)
		{
			$topic->forum->ff->add_log("forum_topic_restore", "".\login::$user->player->id.":".$topic->info['ft_id'].":".urlencode($topic->info['ft_title']));
		}
	}
	
	/**
	 * Flytt forumtråd
	 * @param \Kofradia\Forum\Topic $topic
	 * @param array $old_data array med data som ble erstattet
	 */
	public static function add_topic_moved(Topic $topic, $old_data)
	{
		$from = $old_data['fse']->get_name();
		$to = $topic->forum->get_name();
		
		// legg til som vanlig logg
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD FLYTTET: '".\login::$user->player->data['up_name']."' flyttet forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) fra $from til $to ".\ess::$s['path']."/forum/topic?id={$topic->id}");
		
		// legg til hendelse
		if ($topic->info['ft_up_id'] != \login::$user->player->id)
		{
			\player::add_log_static("forum_topic_move", "{$topic->id}:".urlencode($topic->info['ft_title']).":".urlencode($from).":".urlencode($to), null, $topic->info['ft_up_id']);
		}
		
		// TODO: er det nødvendig med crewlogg?
	}
	
	/**
	 * Rediger forumtråd
	 * @param \Kofradia\Forum\Topic $topic
	 * @param array $old_data array med data som ble erstattet (title, text, section(obj forum), type, locked)
	 */
	public static function add_topic_edited(Topic $topic, $old_data)
	{
		// legg til som vanlig logg
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD REDIGERT: '".\login::$user->player->data['up_name']."' redigerte forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) ".\ess::$s['path']."/forum/topic?id={$topic->id}");
		
		// legg til crewlogg
		if (self::is_crewlog($topic->forum, $topic->info['ft_up_id']))
		{
			$data = array(
				"topic_id" => $topic->id,
				"topic_title_old" => isset($old_data['ft_title']) ? $old_data['ft_title'] : $topic->info['ft_title'],
				"topic_content_old" => isset($old_data['ft_text']) ? $old_data['ft_text'] : $topic->info['ft_text']
			);
			if (isset($old_data['ft_title'])) $data['topic_title_new'] = $topic->info['ft_title'];
			if (isset($old_data['ft_text'])) $data['topic_content_diff'] = \diff::make($old_data['ft_text'], $topic->info['ft_text']);
			
			self::crewlog($topic->forum, "forum_topic_edit", $topic->info['ft_up_id'], NULL, $data);
		}
		
		// ff-log
		if ($topic->forum->ff)
		{
			$topic->forum->ff->add_log("forum_topic_edit", "".\login::$user->player->id.":".$topic->info['ft_id'].":".urlencode($topic->info['ft_title']));
		}
	}
	
	/**
	 * Legg til forumsvar
	 * @param \Kofradia\Forum\Topic $topic
	 * @param integer $reply_id
	 */
	public static function add_reply_added(Topic $topic, $reply_id)
	{
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMSVAR: (".$topic->forum->get_name().") '".\login::$user->player->data['up_name']."' svarte i '{$topic->info['ft_title']}' ".\ess::$s['path']."/forum/topic?id={$topic->id}&replyid=$reply_id");
	}
	
	/**
	 * Legg til formsvar (sammenslått med forrige svar)
	 * @param \Kofradia\Forum\Topic $topic
	 * @param integer $reply_id
	 */
	public static function add_reply_concatenated(Topic $topic, $reply_id)
	{
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMSVAR (sammenslått): (".$topic->forum->get_name().") '".\login::$user->player->data['up_name']."' svarte i '{$topic->info['ft_title']}' ".\ess::$s['path']."/forum/topic?id={$topic->id}&replyid=$reply_id");
	}
	
	/**
	 * Slett forumsvar
	 * @param \Kofradia\Forum\Reply $reply
	 */
	public static function add_reply_deleted(Reply $reply)
	{
		// legg til i forum_log
		self::add_log(self::REPLY_DELETED, $reply->topic->id, $reply->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $reply->topic->forum->id >= 5 && $reply->topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($reply->topic->forum, $location, "FORUMSVAR SLETTET: '".\login::$user->player->data['up_name']."' slettet forumsvaret med ID {$reply->id} i forumtråden med ID {$reply->topic->id} ({$reply->topic->info['ft_title']}) ".\ess::$s['path']."/forum/topic?id={$reply->topic->id}&replyid=$reply->id");
		
		// legg til crewlogg
		if (self::is_crewlog($reply->topic->forum, $reply->info['fr_up_id']))
		{
			self::crewlog($reply->topic->forum, "forum_reply_delete", $reply->info['fr_up_id'], NULL, array(
				"topic_id" => $reply->topic->id,
				"reply_id" => $reply->id,
				"topic_title" => $reply->topic->info['ft_title']
			));
		}
	}
	
	/**
	 * Gjenopprett forumsvar
	 * @param \Kofradia\Forum\Reply $reply
	 */
	public static function add_reply_restored(Reply $reply)
	{
		// legg til i forum_log
		self::add_log(self::REPLY_RESTORED, $reply->topic->id, $reply->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $reply->topic->forum->id >= 5 && $reply->topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($reply->topic->forum, $location, "FORUMSVAR GJENOPPRETTET: '".\login::$user->player->data['up_name']."' gjenopprettet forumsvaret med ID {$reply->id} i forumtråden med ID {$reply->topic->id} ({$reply->topic->info['ft_title']}) ".\ess::$s['path']."/forum/topic?id={$reply->topic->id}&replyid=$reply->id");
		
		// legg til crewlogg
		if (self::is_crewlog($reply->topic->forum, $reply->info['fr_up_id']))
		{
			self::crewlog($reply->topic->forum, "forum_reply_restore", $reply->info['fr_up_id'], NULL, array(
				"topic_id" => $reply->topic->id,
				"reply_id" => $reply->id,
				"topic_title" => $reply->topic->info['ft_title']
			));
		}
	}
	
	/**
	 * Rediger forumsvar
	 * @param \Kofradia\Forum\Reply $reply
	 * @param array $old_data array med data som ble erstattet (text)
	 */
	public static function add_reply_edited(Reply $reply, $old_data)
	{
		// legg til som vanlig logg
		$location = $reply->topic->forum->id >= 5 && $reply->topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($reply->topic->forum, $location, "FORUMSVAR REDIGERT: '".\login::$user->player->data['up_name']."' redigerte forumsvaret med ID {$reply->id} i forumtråden med ID {$reply->topic->id} ({$reply->topic->info['ft_title']}) ".\ess::$s['path']."/forum/topic?id={$reply->topic->id}&replyid=$reply->id");
		
		// legg til crewlogg
		if (self::is_crewlog($reply->topic->forum, $reply->info['fr_up_id']))
		{
			self::crewlog($reply->topic->forum, "forum_reply_edit", $reply->info['fr_up_id'], NULL, array(
				"topic_id" => $reply->topic->id,
				"reply_id" => $reply->id,
				"topic_title" => $reply->topic->info['ft_title'],
				"reply_content_old" => $old_data['fr_text'],
				"reply_content_diff" => \diff::make($old_data['fr_text'], $reply->info['fr_text'])
			));
		}
	}
}