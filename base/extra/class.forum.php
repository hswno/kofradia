<?php

/**
 * Forumkategori
 */
class forum
{
	public $id;
	public $info;
	public $params;
	
	/** Ventetid før ny forumtråd kan opprettes */
	public $wait_topic = 0;
	
	/** Ventetid før nytt forumsvar kan legges til */
	public $wait_reply = 0;
	
	/** Rankkrav for ny forumtråd */
	const TOPIC_MIN_RANK = 4;
	
	/** Minste tillatt lengde i tittelen til en forumtråd */
	const TOPIC_TITLE_MIN_LENGTH = 5;
	
	/** Lengde tillatt lengde i tittelen til en forumtråd */
	const TOPIC_TITLE_MAX_LENGTH = 40;
	
	/** Minste tillatt lengde i en forumtråd */
	const TOPIC_MIN_LENGTH = 25;
	
	/** Minste tillatt lengde i et forumsvar */
	const REPLY_MIN_LENGTH = 2;
	
	/** Om vi skal vise "nye svar" eller ikke */
	public static $fs_check = true;
	
	/**
	 * FF forumet tilhører
	 * @var ff
	 */
	public $ff;
	
	/**
	 * Har vi forum-mod tilgang til dette forumet?
	 */
	public $fmod;
	
	/**
	 * Relasjon mellom spillere og familier for templatene
	 */
	public $ff_rel;
	
	/**
	 * Constructor
	 * @param integer $s_id seksjonID
	 */
	public function __construct($s_id)
	{
		$this->id = (int) $s_id;
		
		// hent informasjon om forumet
		$result = ess::$b->db->query("SELECT fse_id, fse_name, fse_description, fse_params, fse_ff_id FROM forum_sections WHERE fse_id = $this->id");
		
		// fant ikke forumet?
		$this->info = mysql_fetch_assoc($result);
		if (!$this->info)
		{
			$this->error_404();
			return;
		}
		
		// ff?
		if ($this->info['fse_ff_id'])
		{
			$this->ff = ff::get_ff($this->info['fse_ff_id'], ff::LOAD_IGNORE);
			if (!$this->ff) throw new HSException("Fant ikke FF med ID {$this->info['fse_ff_id']}.");
		}
		
		// sett opp params
		$this->params = new params($this->info['fse_params']);
		
		// har vi forum-mod tilganger til dette forumet?
		if ($this->ff)
		{
			$this->fmod = $this->ff->access(2);
		}
		else
		{
			$this->fmod = access::has("forum_mod");
		}
		
		// ikke vise NY
		if (!login::$logged_in) self::$fs_check = false;
	}
	
	/**
	 * Legg til tittel
	 */
	public function add_title()
	{
		// ff?
		if ($this->ff)
		{
			ess::$b->page->add_title($this->ff->data['ff_name'], "Forum");
		}
		
		else
		{
			ess::$b->page->add_title("Forum", $this->info['fse_name']);
		}
	}
	
	/**
	 * Hent navn på forumet
	 */
	public function get_name()
	{
		if ($this->ff) return $this->ff->data['ff_name'];
		return $this->info['fse_name'];
	}
	
	/** Feil: 404 */
	protected function error_404()
	{
		ess::$b->page->add_message("Fant ikke forumet.", "error");
		ess::$b->page->load();
	}
	
	/** Sjekk om vi har tilgang */
	public function check_access()
	{
		// tilhører et ff?
		if ($this->ff && !$this->ff->access(true))
		{
			return false;
		}
		
		// kontroller tilgang satt i params
		return self::check_access_params($this->params);
	}
	
	/**
	 * Sjekk om vi har tilgang (ved params)
	 * @param params $params
	 */
	public static function check_access_params($params)
	{
		// har ikke tilgang?
		if (($access = $params->get("need_access_name")) && !access::has($access))
		{
			return false;
		}
		
		return true;
	}
	
	/** Hent ut liste over alle forumkategoriene brukeren har tilgang til */
	public static function get_forum_list()
	{
		// hent alle forumkategoriene
		if (login::$logged_in)
		{
			$result = ess::$b->db->query("
				SELECT fse_id, fse_name, ff_name, fse_params, fse_ff_id, ff_inactive, ffm_status
				FROM forum_sections
					LEFT JOIN ff ON ff_id = fse_ff_id
					LEFT JOIN ff_members ON ffm_ff_id = ff_id AND ffm_up_id = ".login::$user->player->id." AND ffm_status = 1
				ORDER BY fse_ff_id IS NOT NULL, IF(fse_ff_id IS NULL, fse_name, ff_name)");
		}
		else
		{
			$result = ess::$b->db->query("
				SELECT fse_id, fse_name, NULL ff_name, fse_params, fse_ff_id, NULL ff_inactive, NULL ffm_status
				FROM forum_sections
				ORDER BY fse_ff_id IS NOT NULL, fse_name");
		}
		$sections = array();
		while ($row = mysql_fetch_assoc($result))
		{
			// ff som vi ikke har tilgang til?
			if ($row['fse_ff_id'] && !access::has("mod"))
			{
				if (!$row['ffm_status'] || $row['ff_inactive']) continue;
			}
			
			// har vi tilgang til denne forumkategorien?
			$params = new params($row['fse_params']);
			if (forum::check_access_params($params))
			{
				$row['name'] = $row['ff_name'] ? $row['ff_name'] : $row['fse_name'];
				if ($row['fse_ff_id']) $row['name'] .= " (#{$row['fse_ff_id']})";
				if ($row['ff_inactive']) $row['name'] .= " (deaktivert)";
				$sections[$row['fse_id']] = $row;
			}
		}
		
		return $sections;
	}
	
	/** Krev tilgang */
	public function require_access()
	{
		if (!$this->check_access())
		{
			$this->error_403();
			return false;
		}
		
		return true;
	}
	
	/** Feil: 403 (ikke tilgang) */
	protected function error_403()
	{
		ess::$b->page->add_message("Du har ikke tilgang til dette forumet.", "error");
		ess::$b->page->load();
	}
	
	/** Legg med RSS lenker i head */
	public function rss_links()
	{
		// ingen RSS for FF
		if ($this->ff) return;
		
		ess::$b->page->add_head('<link rel="alternate" href="'.ess::$s['relative_path'].'/rss/forum_topics?forum='.$this->id.'" type="application/rss+xml" title="Siste forumtråder i '.htmlspecialchars($this->get_name()).'" />');
		ess::$b->page->add_head('<link rel="alternate" href="'.ess::$s['relative_path'].'/rss/forum_replies?forum='.$this->id.'" type="application/rss+xml" title="Siste forumsvar i '.htmlspecialchars($this->get_name()).'" />');
	}
	
	/** Redirect til forumet */
	public function redirect()
	{
		redirect::handle("/forum/forum?id=$this->id", redirect::ROOT);
	}
	
	/** Sjekk timere */
	public function check_timers()
	{
		// ingen ventetid for FF
		if ($this->ff) return;
		
		// crewet har ikke sperrer
		if (access::has("crewet")) return;
		
		// ventetid for ny forumtråd
		if (login::$user->data['u_forum_topic_time'] > 0)
		{
			$this->wait_topic = max(0, login::$user->data['u_forum_topic_time'] + game::$settings['delay_forum_new']['value'] - time());
		}
		
		// ventetid for nytt forumsvar
		if (login::$user->data['u_forum_reply_time'] > 0)
		{
			$this->wait_reply = max(0, login::$user->data['u_forum_reply_time'] + game::$settings['delay_forum_reply']['value'] - time());
		}
	}
	
	/**
	 * Sjekk om vi er blokkert fra å utføre forumhandlinger
	 * @return boolean (true=blokkert, false=ikke)
	 */
	public function check_block()
	{
		// blokkering gjelder ikke i FF
		if ($this->ff) return false;
		
		// sjekk om vi er blokkert
		$blokkering = blokkeringer::check(blokkeringer::TYPE_FORUM);
		if ($blokkering)
		{
			$this->blocked($blokkering);
			return true;
		}
		
		// ikke blokkert
		return false;
	}
	
	/** Blokkert fra å utføre forumhandlinger */
	protected function blocked($blokkering)
	{
		ess::$b->page->add_message("Du er blokkert fra å utføre handlinger i forumet. Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br />\n"
			."<b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
	}
	
	/** Kontroller rankkrav for å skrive i en topic */
	public function check_rank()
	{
		// crewet har uansett tilgang
		if (access::has("crewet")) return true;
		
		// ingen begrensninger i FF
		if ($this->ff) return true;
		
		// kontroller ranken
		return login::$user->player->rank['number'] >= self::TOPIC_MIN_RANK;
	}
	
	/**
	 * Legg til ny forumtråd
	 * @param string $title
	 * @param string $text
	 * @param integer $type
	 * @param boolean $locked
	 */
	public function add_topic($title, $text, $type = NULL, $locked = NULL)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		// kontroller blokkering
		if ($this->check_block()) return;
		
		// kontroller rankkrav
		if (!$this->check_rank())
		{
			$this->add_topic_error_rank();
			return;
		}
		
		// kontroller ventetid før nytt forumsvar kan legges til
		$this->check_timers();
		if ($this->wait_topic > 0)
		{
			$this->add_topic_error_wait($this->wait_topic);
			return;
		}
		
		// kontroller tekstlengde (tittel)
		$title = trim($title);
		if (strlen($title) < forum::TOPIC_TITLE_MIN_LENGTH || strlen($title) > forum::TOPIC_TITLE_MAX_LENGTH)
		{
			$this->add_topic_error_length_title();
			return;
		}
		
		// kontroller tekstlengde (innhold)
		$text = trim($text);
		if (forum::check_length($text) < forum::TOPIC_MIN_LENGTH)
		{
			$this->add_topic_error_length();
			return;
		}
		
		$set = '';
		
		// sette type
		if ($type !== NULL)
		{
			// kontroller type
			$type = (int) $type;
			if ($type < 1 || $type > 3)
			{
				$this->add_topic_error_type();
				return;
			}
			
			$set .= ", ft_type = $type";
		}
		
		// sette som låst/ulåst
		if ($locked !== NULL)
		{
			$set .= ", ft_locked = ".($locked ? 1 : 0);
		}
		
		// legg til forumtråden
		ess::$b->db->query("INSERT INTO forum_topics SET ft_title = ".ess::$b->db->quote($title).", ft_text = ".ess::$b->db->quote($text).", ft_time = ".time().", ft_up_id = ".login::$user->player->id.", ft_fse_id = $this->id$set");
		$topic_id = ess::$b->db->insert_id();
		
		// oppdater spilleren
		if ($this->ff)
		{
			ess::$b->db->query("UPDATE ff_members SET ffm_forum_topics = ffm_forum_topics + 1 WHERE ffm_up_id = ".login::$user->player->id." AND ffm_ff_id = {$this->ff->id}");
			ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_topics = up_forum_ff_num_topics + 1 WHERE up_id = ".login::$user->player->id);
		}
		else
		{
			ess::$b->db->query("UPDATE users, users_players SET up_forum_num_topics = up_forum_num_topics + 1, u_forum_topic_time = ".time()." WHERE up_id = ".login::$user->player->id." AND u_id = up_u_id");
		}
		
		// oppdater tid om nødvendig
		$this->update_change_time();
		
		// logg
		forum_log::add_topic_added($this, array(
			"ft_id" => $topic_id,
			"ft_title" => $title,
			"ft_type" => ($type !== NULL ? $type : 1)
		));
		
		// fullført
		$this->add_topic_complete($topic_id);
	}
	
	/** Har ikke høy nok rank for å skrive i forumet */
	protected function add_topic_error_rank()
	{
		// sett opp ranknavnet
		$rank_info = game::$ranks['items_number'][self::TOPIC_MIN_RANK][$rank_id];
		
		ess::$b->page->add_message("Du har ikke høy nok rank for å skrive i dette forumet. Du må ha nådd ranken <b>".htmlspecialchars($rank_info['name'])."</b>.", "error");
	}
	
	/**
	 * Må vente før ny forumtråd kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_topic_error_wait($wait)
	{
		ess::$b->page->add_message("Du må vente ".game::counter($wait)." før du kan opprette ny forumtråd.", "error");
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function add_topic_error_length_title()
	{
		ess::$b->page->add_message("Tittelen kan ikke inneholde færre enn ".forum::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".forum::TOPIC_TITLE_MAX_LENGTH." tegn.", "error");
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function add_topic_error_length()
	{
		ess::$b->page->add_message("Forumtråden kan ikke inneholde færre enn ".forum::TOPIC_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/** Ugyldig type */
	protected function add_topic_error_type()
	{
		ess::$b->page->add_message("Ugyldig type.", "error");
	}
	
	/** Forumtråden ble redigert */
	protected function add_topic_complete($topic_id)
	{
		ess::$b->page->add_message("Forumtråden ble opprettet.");
		
		// send til forumtråden
		redirect::handle("/forum/topic?id=$topic_id", redirect::ROOT);
	}
	
	/** Oppdater tiden forumet sist ble endret (crewforum og ff) */
	public function update_change_time()
	{
		// oppdater innstillinger hvis crewforum
		if ($this->id >= 5 && $this->id <= 7)
		{
			// oppdatere brukeren for å unngå markering om noe nytt?
			$t = isset(game::$settings["forum_{$this->id}_last_change"]) ? game::$settings["forum_{$this->id}_last_change"]['value'] : false;
			$l = login::$user->params->get("forum_{$this->id}_last_view");
			
			$time = time();
			if ($t && $l >= $t)
			{
				// sett visningstidspunkt til nå for å unngå oppdatering
				login::$user->params->update("forum_{$this->id}_last_view", $time, true);
			}
			
			ess::$b->db->query("
				INSERT INTO settings (name, value) VALUES ('forum_{$this->id}_last_change', $time)
				ON DUPLICATE KEY UPDATE value = VALUES(value)");
			cache::delete("settings");
		}
		
		// ff
		elseif ($this->ff)
		{
			$this->ff->forum_changed();
		}
	}
	
	/**
	 * Last inn relasjon mellom spillere og familier
	 */
	public function ff_get_familier($up_ids)
	{
		if (count($up_ids) == 0)
		{
			$this->ff_rel = null;
			return;
		}
		
		$result = ess::$b->db->query("
			SELECT ffm_up_id, ff_id, ff_name
			FROM ff_members
				JOIN ff ON ff_id = ffm_ff_id AND ff_inactive = 0 AND ff_is_crew = 0 AND ff_type = 1
			WHERE ffm_up_id IN (".implode(",", $up_ids).") AND ffm_status = 1");
		
		$ff = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$ff[$row['ffm_up_id']][] = '<a href="'.ess::$s['rpath'].'/ff/?ff_id='.$row['ff_id'].'" title="Broderskap">'.htmlspecialchars($row['ff_name']).'</a>';
		}
		
		foreach ($ff as $up => &$rows)
		{
			$rows = sentences_list($rows);
		}
		
		$this->ff_rel = $ff;
	}
	
	/**
	 * Hent posisjon og info om forumstats for FF
	 */
	protected function get_ff_info($up_id)
	{
		if (!$this->ff)
		{
			// ikke FF, vis medlemskap i familier
			if (!isset($this->ff_rel[$up_id])) return '';
			return $ret = ' <span class="f_stilling">[<span class="f_stilling_i">'.$this->ff_rel[$up_id].'</span>]</span>';
		}
		
		// ikke medlem lenger
		if (!isset($this->ff->members['members'][$up_id])) return '';
		
		$member = $this->ff->members['members'][$up_id];
		
		$ret = ' <span class="f_stilling">[<span class="f_stilling_i">'.ucfirst($member->get_priority_name()).'</span>]</span>';
		$ret .= ' <span class="f_stilling">[<span class="f_stilling_i">'.fwords("%d tråd", "%d tråder", $member->data['ffm_forum_topics']).', '.$member->data['ffm_forum_replies'].' svar</span>]</span>';
		
		return $ret;
	}
	
	/**
	 * Lag HTML for hovedinnlegget i en forumtråd
	 * @param array $data
	 * 
	 * $data må inneholde:
	 * 
	 * ft_id
	 * ft_time
	 * ft_text
	 * ft_last_edit
	 * ft_last_edit_up_id
	 * 
	 * ft_up_id
	 * up_name
	 * up_access_level
	 * up_points
	 * upr_rank_pos
	 * up_forum_signature
	 * up_profile_image_url
	 * 
	 * r_time (tidspunkt rapportert)
	 * fs_new [bool, optional]
	 */
	public function template_topic($data)
	{
		$date = ess::$b->date->get($data['ft_time']);
		
		// sett opp ranken
		$rank = game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = $this->get_ff_info($data['ft_up_id']);
		
		$html = '
	<div class="forum_topic" id="t'.$data['ft_id'].'">
		<h2 class="forum_title"><a href="topic?id='.$data['ft_id'].'" class="forum_permlink r4">#1</a> - '.$date->format(date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.game::profile_link($data['ft_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.(isset($data['fs_new']) && $data['fs_new'] ? ' <span class="fs_new">(Ny)</span>' : '').'</h2>
		<p class="h_left"><a href="#default_container"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>';
		
		$right = '';
		
		// rapportert?
		if ($data['r_time'])
		{
			$right .= '
			<span class="forum_report">Rapportert '.ess::$b->date->get($data['r_time'])->format().'</span>';
		}
		elseif (login::$logged_in && $data['ft_up_id'] != login::$user->player->id)
		{
			$right .= '
			<a href="'.ess::$s['relative_path'].'/js" rel="ft,'.$data['ft_id'].'" class="report_link forum_report">Rapporter</a>';
		}
		
		// verktøy
		if (login::$logged_in && ($this->fmod || $data['ft_up_id'] == login::$user->player->id))
		{
			$right .= '
			<a href="'.ess::$s['relative_path'].'/forum/post_edit?type=emne&amp;id='.$data['ft_id'].'" class="forum_link_topic_edit"><img src="'.STATIC_LINK.'/other/edit.gif" alt="Rediger" /></a>';
		}
		
		if ($right != "")
		{
			$html .= '
		<p class="h_right">'.$right.'
		</p>';
		}
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['ft_up_id'].'"><img src="'.htmlspecialchars(player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.game::format_data($data['ft_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (!login::$logged_in || login::$user->data['u_forum_show_signature'])
		{
			$signatur = game::format_data($data['up_forum_signature']);
		}
		if (!empty($signatur))
		{
			$html .= '
		<div class="forum_signature">'.$signatur.'</div>';
		}
		
		// sist endret
		if (!empty($data['ft_last_edit']))
		{
				$html .= '
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['ft_last_edit_up_id'].'" /> '.ess::$b->date->get($data['ft_last_edit'])->format(date::FORMAT_SEC).'</p>';
		}
		
		$html .= '
	</div>';
		
		return $html;
	}
	
	/**
	 * Lag HTML for forhåndsvisning av hovedinnlegget i en forumtråd
	 * @param array $data
	 * 
	 * $data må inneholde:
	 * 
	 * ft_id [optional]
	 * ft_time [optional]
	 * ft_text
	 * 
	 * ft_up_id [optional]
	 * up_name [optional with ft_up_id]
	 * up_access_level [optional with ft_up_id]
	 * up_points [optional with ft_up_id]
	 * upr_rank_pos [optional with ft_up_id]
	 * up_forum_signature [optional with ft_up_id]
	 * up_profile_image_url [optional with ft_up_id]
	 */
	public static function template_topic_preview($data)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		$date = isset($data['ft_time']) ? ess::$b->date->get($data['ft_time']) : ess::$b->date->get();
		
		// bruk informasjonen til brukeren?
		if (empty($data['ft_up_id']))
		{
			$data['ft_up_id'] = login::$user->player->id;
			$data['up_name'] = login::$user->player->data['up_name'];
			$data['up_access_level'] = login::$user->player->data['up_access_level'];
			$data['up_points'] = login::$user->player->data['up_points'];
			$data['upr_rank_pos'] = login::$user->player->data['upr_rank_pos'];
			$data['up_forum_signature'] = login::$user->player->data['up_forum_signature'];
			$data['up_profile_image_url'] = login::$user->player->data['up_profile_image_url'];
		}
		
		// sett opp ranken
		$rank = game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = '';
		
		$html = '
	<div class="forum_topic">
		<h2 class="forum_title"><a href="'.(isset($data['ft_id']) ? 'topic?id='.$data['ft_id'] : 'forum').'" class="forum_permlink r4">#1</a> - '.$date->format(date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.game::profile_link($data['ft_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.'</h2>
		<p class="h_left"><a href="#default_container"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>
		<p class="h_right" style="text-transform: uppercase; margin: -17px 10px 0 !important; color: #DDD">Forhåndsvisning</p>';
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['ft_up_id'].'"><img src="'.htmlspecialchars(player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.game::format_data($data['ft_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (login::$user->data['u_forum_show_signature'])
		{
			$signatur = game::format_data($data['up_forum_signature']);
		}
		if (!empty($signatur))
		{
			$html .= '
		<div class="forum_signature">'.$signatur.'</div>';
		}
		
		// sist endret
		if (!empty($data['ft_last_edit']))
		{
				$html .= '
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['ft_last_edit_up_id'].'" /> '.ess::$b->date->get($data['ft_last_edit'])->format(date::FORMAT_SEC).'</p>';
		}
		
		$html .= '
	</div>';
		
		return $html;
	}
	
	/**
	 * Lag HTML for et forumsvar
	 * @param array $data
	 * 
	 * $data må inneholde:
	 * 
	 * ft_fse_id
	 * ft_id
	 * fr_id
	 * reply_num
	 * fr_time
	 * fr_deleted
	 * fr_text
	 * fr_last_edit
	 * fr_last_edit_up_id
	 * 
	 * fr_up_id
	 * up_name
	 * up_access_level
	 * up_points
	 * upr_rank_pos
	 * up_forum_signature
	 * up_profile_image_url
	 * 
	 * r_time (tidspunkt rapportert)
	 * class_extra [optional]
	 * h2_extra [optional]
	 * fs_new [bool, optional]
	 */
	public function template_topic_reply($data)
	{
		$date = ess::$b->date->get($data['fr_time']);
		
		// sett opp ranken
		$rank = game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = $this->get_ff_info($data['fr_up_id']);
		
		$html = '
	<div class="forum_topic'.($data['fr_deleted'] != 0 ? ' forum_reply_deleted' : '').(isset($data['class_extra']) ? ' '.$data['class_extra'] : '').'" id="m_'.$data['fr_id'].'">
		<h2 class="forum_title"'.(isset($data['h2_extra']) ? ' '.$data['h2_extra'] : '').'><a href="topic?id='.$data['ft_id'].'&amp;replyid='.$data['fr_id'].'" class="forum_permlink r4">#'.$data['reply_num'].'</a> - '.$date->format(date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.game::profile_link($data['fr_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.(isset($data['fs_new']) && $data['fs_new'] ? ' <span class="fs_new">(Ny)</span>' : '').'</h2>
		<p class="h_left"><a href="#default_header_wrap"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>';
		
		$right = '';
		
		// rapportert?
		if ($data['r_time'])
		{
			$right .= '
			<span class="forum_report">Rapportert '.ess::$b->date->get($data['r_time'])->format().'</span>';
		}
		elseif (login::$logged_in && $data['fr_up_id'] != login::$user->player->id)
		{
			$right .= '
			<a href="'.ess::$s['relative_path'].'/js" rel="fr,'.$data['fr_id'].'" class="report_link forum_report">Rapporter</a>';
		}
		
		// verktøy
		if ($this->fmod || (login::$logged_in && $data['fr_up_id'] == login::$user->player->id))
		{
			$right .= '
			<a href="'.ess::$s['relative_path'].'/forum/post_edit?type=svar&amp;id='.$data['fr_id'].'" class="forum_link_reply_edit" rel="'.$data['fr_id'].'"><img src="'.STATIC_LINK.'/other/edit.gif" alt="Rediger" /></a>';
			
			// ikke slettet
			if ($data['fr_deleted'] == 0)
			{
				$right .= '
			<a href="'.ess::$s['relative_path'].'/forum/topic?id='.$data['ft_id'].'&amp;delete_reply='.$data['fr_id'].'&amp;sid='.login::$info['ses_id'].'" class="forum_link_reply_delete" rel="'.$data['fr_id'].'"><img src="'.STATIC_LINK.'/other/delete.gif" alt="Slett" /></a>';
				
				// annonsere svaret på nytt?
				if ($data['ft_fse_id'] >= 5 && $data['ft_fse_id'] <= 7)
				{
					$right .= '
			<a href="#" class="forum_link_reply_announce" rel="'.$data['fr_id'].'" title="Annonser svaret på nytt i spillelogg og crewkanal"><img src="'.STATIC_LINK.'/icon/arrow_out.png" alt="Annonser" /></a>';
				}
			}
			
			// slettet
			else
			{
				$right .= '
			<a href="'.ess::$s['relative_path'].'/forum/topic?id='.$data['ft_id'].'&amp;restore_reply='.$data['fr_id'].'&amp;sid='.login::$info['ses_id'].'" class="forum_link_reply_restore" rel="'.$data['fr_id'].'"><img src="'.STATIC_LINK.'/icon/arrow_refresh.png" alt="Gjenopprett" /></a>';
			}
		}
		
		if ($right != "")
		{
			$html .= '
		<p class="h_right">'.$right.'
		</p>';
		}
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['fr_up_id'].'"><img src="'.htmlspecialchars(player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.game::format_data($data['fr_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (!login::$logged_in || login::$user->data['u_forum_show_signature'])
		{
			$signatur = game::format_data($data['up_forum_signature']);
		}
		if (!empty($signatur))
		{
			$html .= '
		<div class="forum_signature">'.$signatur.'</div>';
		}
		
		// sist endret
		if (!empty($data['fr_last_edit']))
		{
				$html .= '
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['fr_last_edit_up_id'].'" /> '.ess::$b->date->get($data['fr_last_edit'])->format(date::FORMAT_SEC).'</p>';
		}
		
		$html .= '
	</div>';
		
		return $html;
	}
	
	/**
	 * Lag HTML for forhåndsvisning av et forumsvar
	 * @param array $data
	 * 
	 * $data må inneholde:
	 *
	 * ft_id
	 * fr_text
	 * fr_last_edit [optional]
	 * fr_last_edit_up_id [optional]
	 * 
	 * fr_up_id
	 * up_name
	 * up_access_level
	 * up_points
	 * upr_rank_pos
	 * up_forum_signature
	 * up_profile_image_url
	 * 
	 * fs_new [boolean, optional]
	 */
	public static function template_topic_reply_preview($data)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		$date = ess::$b->date->get();
		
		// sett opp ranken
		$rank = game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = '';
		
		$html = '
	<div class="forum_topic">
		<h2 class="forum_title"><a href="topic?id='.$data['ft_id'].'" class="forum_permlink r4">#XX</a> - '.$date->format(date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.game::profile_link($data['fr_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.(isset($data['fs_new']) && $data['fs_new'] ? ' <span class="fs_new">(Ny)</span>' : '').'</h2>
		<p class="h_left"><a href="#default_header_wrap"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>
		<p class="h_right" style="text-transform: uppercase; margin: -17px 10px 0 !important; color: #DDD">Forhåndsvisning</p>';
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['fr_up_id'].'"><img src="'.htmlspecialchars(player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.game::format_data($data['fr_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (login::$user->data['u_forum_show_signature'])
		{
			$signatur = game::format_data($data['up_forum_signature']);
		}
		if (!empty($signatur))
		{
			$html .= '
		<div class="forum_signature">'.$signatur.'</div>';
		}
		
		// sist endret
		if (!empty($data['fr_last_edit']))
		{
			$html .= '
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['fr_last_edit_up_id'].'" /> '.ess::$b->date->get($data['fr_last_edit'])->format(date::FORMAT_SEC).'</p>';
		}
		
		$html .= '
	</div>';
		
		return $html;
	}
	
	/**
	 * Sjekk lengde for en bb-tekst
	 * Tillatter kun a-z, A-Z, æøåÆØÅ, 0-9 som tegn
	 * @param string $data
	 */
	public static function check_length($data)
	{
		// gjør om data til kun tillatte tegn
		$data = trim($data);
		$plain = htmlspecialchars_decode(strip_tags(game::format_data($data)));
		$plain = preg_replace("/[^a-zA-ZæøåÆØÅ0-9]/", '', $plain);
		
		// sjekk lengden
		return strlen($plain);
	}
	
	/**
	 * Last inn siden
	 */
	public function load_page()
	{
		if ($this->ff)
		{
			$this->ff->load_page();
		}
		
		ess::$b->page->load();
	}
}

/**
 * For AJAX handlinger i forumet
 */
class forum_ajax extends forum
{
	/** Feil: 404 */
	protected function error_404()
	{
		ajax::text("ERROR:404-FORUM", ajax::TYPE_INVALID);
	}
	
	/** Feil: 403 (ikke tilgang) */
	protected function error_403()
	{
		ajax::text("ERROR:403-FORUM", ajax::TYPE_INVALID);
	}
	
	/** Blokkert fra å utføre forumhandlinger */
	protected function blocked($blokkering)
	{
		ajax::html("Du er blokkert fra å utføre handlinger i forumet.<br />Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC)." (".game::counter($blokkering['ub_time_expire']-time()).").<br />\n"
			."<b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), ajax::TYPE_INVALID);
	}
	
	/** Har ikke høy nok rank for å skrive i forumet */
	protected function add_topic_error_rank()
	{
		// sett opp ranknavnet
		$rank_info = game::$ranks['items_number'][self::TOPIC_MIN_RANK][$rank_id];
		
		ajax::html("Du har ikke høy nok rank for å skrive i dette forumet. Du må ha nådd ranken <b>".htmlspecialchars($rank_info['name'])."</b>.", ajax::TYPE_INVALID);
	}
	
	/**
	 * Må vente før ny forumtråd kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_topic_error_wait($wait)
	{
		ajax::html("Du må vente ".game::counter($wait)." før du kan opprette ny forumtråd.", ajax::TYPE_INVALID);
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function add_topic_error_length_title()
	{
		ajax::html("Tittelen kan ikke inneholde færre enn ".forum::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".forum::TOPIC_TITLE_MAX_LENGTH." tegn.", ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function add_topic_error_length()
	{
		ajax::html("Forumtråden kan ikke inneholde færre enn ".forum::TOPIC_MIN_LENGTH." bokstaver/tall.", ajax::TYPE_INVALID);
	}
	
	/** Ugyldig type */
	protected function add_topic_error_type()
	{
		ajax::html("Ugyldig type.", ajax::TYPE_INVALID);
	}
	
	/** Forumtråden ble redigert */
	protected function add_topic_complete($topic_id)
	{
		ess::$b->page->add_message("Forumtråden ble opprettet.");
		
		ajax::text("REDIRECT:".ess::$s['relative_path']."/forum/topic?id=$topic_id");
	}
}

/**
 * For kontrollering av forum
 */
class forum_control extends forum
{
	/** Feil: 404 */
	protected function error_404(){}
}

/**
 * Forumtråd
 */
class forum_topic
{
	/** Antall forumsvar per side */
	public $replies_per_page = 20;
	
	/** Ventetid før en forumtråd kan slettes */
	const WAIT_DELETE_TOPIC = 300; // 5 minutter
	
	/** ID-en til forumtråden */
	public $id;
	
	/** Informasjon om forumtråden */
	public $info;
	
	/**
	 * Objekt for forumkategorien
	 * @var forum
	 */
	public $forum;
	
	/**
	 * Etter hvor lang tid en forumtråd blir slettet at den blir utilgjengelig for FF
	 */
	const FF_HIDE_TIME = 172800; // 48 timer
	
	/**
	 * Constructor
	 * @param integer $topic_id
	 * @param forum $forum
	 */
	public function __construct($topic_id, forum $forum = NULL)
	{
		$this->id = (int) $topic_id;
		
		// hent informasjon om forumtråden
		$seen_q = login::$logged_in ? "fs_ft_id = ft_id AND fs_u_id = ".login::$user->id : "FALSE";
		$result = ess::$b->db->query("
			SELECT
				ft_id, ft_type, ft_title, ft_time, ft_up_id, ft_text, ft_fse_id, ft_locked, ft_deleted, ft_last_edit, ft_last_edit_up_id, ft_replies, ft_last_reply,
				up_name, up_forum_signature, up_access_level, up_points, up_profile_image_url,
				upr_rank_pos,
				fs_time,
				r_time
			FROM
				forum_topics
				LEFT JOIN users_players ON ft_up_id = up_id
				LEFT JOIN users_players_rank ON upr_up_id = up_id
				LEFT JOIN forum_seen ON $seen_q
				LEFT JOIN rapportering ON r_type = ".rapportering::TYPE_FORUM_TOPIC." AND r_type_id = ft_id AND r_state < 2
			WHERE ft_id = $this->id
			GROUP BY ft_id");
		
		$this->info = mysql_fetch_assoc($result);
		
		// hent informasjon om forumkategorien og kontroller tilgang
		if ($this->info)
		{
			if ($forum)
			{
				$this->forum = $forum;
			}
			else
			{
				$this->load_forum();
			}
		}
		
		// fant ikke forumtråden eller slettet uten tilgang?
		if (!$this->info || $this->info['ft_deleted'] != 0)
		{
			// hvor lenge etter den er slettet vi kan vise den
			$access_expire = max(time() - self::FF_HIDE_TIME, $this->info && $this->forum->ff ? $this->forum->ff->data['ff_time_reset'] : 0);
			
			// har tilgang?
			if ($this->info && ($this->forum->ff ? (access::has("mod") || ($this->forum->fmod && $this->info['ft_deleted'] > $access_expire)) : $this->forum->fmod))
			{
				$this->deleted_with_access();
			}
			
			// ikke tilgang eller finnes ikke
			else
			{
				$this->error_404();
			}
		}
		
		// sett opp antall forumsvar som skal vises på hver side
		if (login::$logged_in && login::$user->data['u_forum_per_page'] > 1) $this->replies_per_page = max(1, min(100, login::$user->data['u_forum_per_page'])); 
	}
	
	/**
	 * Hent full informasjon om forumtråden
	 * For å kunne bruke HTML-malen
	 * @return array
	 */
	public function extended_info()
	{
		$data = $this->info;
		$data['fs_new'] = empty($data['fs_time']) && forum::$fs_check;
		return $data;
	}
	
	/** Slettet, men tilgang */
	protected function deleted_with_access()
	{
		ess::$b->page->add_message("Denne forumtråden er slettet. Du har alikevel tilgang til å vise den.");
	}
	
	/** Slettet og uten tilgang, eller finnes ikke */
	protected function error_404()
	{
		ess::$b->page->add_message("Fant ikke forumtråden.", "error");
		redirect::handle("/forum/", redirect::ROOT);
	}
	
	/** Hent informasjon om forumkategorien og kontroller tilgang */
	protected function load_forum()
	{
		$this->forum = new forum($this->info['ft_fse_id']);
		$this->forum->require_access();
	}
	
	/** Redirect til forumtråden */
	public function redirect()
	{
		redirect::handle("/forum/topic?id=$this->id", redirect::ROOT);
	}
	
	/**
	 * Slett forumtråden
	 */
	public function delete()
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		// kontroller tilgang til forumtråden
		if ($this->info['ft_up_id'] != login::$user->player->id && !$this->forum->fmod)
		{
			$this->delete_error_403();
			return;
		}
		
		// allerede slettet?
		if ($this->info['ft_deleted'] != 0)
		{
			$this->delete_dupe();
			return;
		}
		
		// kontroller blokkering
		if ($this->forum->check_block()) return;
		
		// kontroller ventetid før forumtråden kan slettes
		if (!$this->forum->fmod)
		{
			$wait = $this->info['ft_time'] - time() + self::WAIT_DELETE_TOPIC;
			if ($wait > 0)
			{
				// må vente
				$this->delete_error_wait($wait);
				return;
			}
		}
		
		// slett forumtråden
		if (!$this->delete_action())
		{
			// anta at den allerede er slettet
			$this->delete_dupe();
			return;
		}
		
		// logg
		forum_log::add_topic_deleted($this);
		
		// fullført
		$this->delete_complete();
	}
	
	/** Utfør selve slettingen av forumtråden */
	protected function delete_action()
	{
		// forsøk å slett forumtråden
		ess::$b->db->query("UPDATE forum_topics SET ft_deleted = ".time()." WHERE ft_id = $this->id AND ft_deleted = 0");
		return ess::$b->db->affected_rows() > 0;
	}
	
	/** Forumtråden er allerede slettet */
	protected function delete_dupe()
	{
		ess::$b->page->add_message("Forumtråden er allerede slettet.", "error");
		$this->forum->redirect();
	}
	
	/** Forumtråden ble slettet */
	protected function delete_complete()
	{
		ess::$b->page->add_message("Forumtråden ble slettet.");
		$this->forum->redirect();
	}
	
	/** Ikke tilgang til å slette forumtråden */
	protected function delete_error_403()
	{
		ess::$b->page->add_message("Du har ikke tilgang til å slette denne forumtråden.", "error");
		$this->redirect();
	}
	
	/**
	 * Må vente før forumtråden kan slettes
	 * @param integer $wait ventetid
	 */
	protected function delete_error_wait($wait)
	{
		ess::$b->page->add_message("Du må vente 5 minutter før du kan slette emnet etter å ha opprettet det. Du må vente i ".game::counter($wait)." før du kan slette det.", "error");
		$this->redirect();
	}
	
	/** Gjenopprett forumtråden */
	public function restore()
	{
		// er ikke slettet?
		if ($this->info['ft_deleted'] == 0)
		{
			$this->restore_dupe();
			return;
		}
		
		// gjenopprett forumtråden
		if (!$this->restore_action())
		{
			// anta at den allerede er gjenopprettet
			$this->restore_dupe();
			return;
		}
		
		// logg
		forum_log::add_topic_restored($this);
		
		// fullført
		$this->restore_complete();
	}
	
	/** Forumtråden er allerede gjenopprettet */
	protected function restore_dupe()
	{
		ess::$b->page->add_message("Forumtråden er allerede gjenopprettet.", "error");
		$this->redirect();
	}
	
	/** Utfør selve gjenopprettelsen av forumtråden */
	protected function restore_action()
	{
		// forsøk å gjenopprett forumtråden
		ess::$b->db->query("UPDATE forum_topics SET ft_deleted = 0 WHERE ft_id = $this->id AND ft_deleted != 0");
		return ess::$b->db->affected_rows() > 0;
	}
	
	/** Forumtråden ble gjenopprettet */
	protected function restore_complete()
	{
		ess::$b->page->add_message("Forumtråden ble gjenopprettet.");
		$this->redirect();
	}
	
	/**
	 * Rediger forumtråden
	 * @param string $title
	 * @param string $text
	 * @param integer $section (forumkategori)
	 * @param integer $type (trådtype)
	 * @param boolean $locked
	 */
	public function edit($title, $text, $section = NULL, $type = NULL, $locked = NULL)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		// kontroller tilgang til forumtråden
		if ($this->info['ft_up_id'] != login::$user->player->id && !$this->forum->fmod)
		{
			$this->edit_error_403();
			return;
		}
		
		// er tråden låst?
		if ($this->info['ft_locked'] != 0 && !$this->forum->fmod)
		{
			$this->edit_error_locked();
			return;
		}
		
		// kontroller blokkering
		if ($this->forum->check_block()) return;
		
		// kontroller tekstlengde (tittel)
		$title = trim($title);
		if ($title != $this->info['ft_title'] && (strlen($title) < forum::TOPIC_TITLE_MIN_LENGTH || strlen($title) > forum::TOPIC_TITLE_MAX_LENGTH))
		{
			$this->edit_error_length_title();
			return;
		}
		
		// kontroller tekstlengde (innhold)
		$text = trim($text);
		if ($text != $this->info['ft_text'] && forum::check_length($text) < forum::TOPIC_MIN_LENGTH)
		{
			$this->edit_error_length();
			return;
		}
		
		$update = '';
		$only_moved = false;
		
		// bytte seksjon?
		if ($section !== NULL && $section != $this->info['ft_fse_id'] && (!$this->forum->ff || access::has("mod")))
		{
			// kontroller at den finnes og at vi har tilgang
			$forum = new forum_control($section);
			
			// fant ikke forumet eller ikke tilgang?
			if (!$forum->info || !$forum->check_access())
			{
				$this->edit_error_section();
				return;
			}
			
			$update .= ", ft_fse_id = $forum->id";
			$only_moved = true;
		}
		
		// sette type
		if ($type !== NULL && $type != $this->info['ft_type'])
		{
			// kontroller type
			$type = (int) $type;
			if ($type < 1 || $type > 3)
			{
				$this->edit_error_type();
				return;
			}
			
			$update .= ", ft_type = $type";
			$only_moved = false;
		}
		
		// sette som låst/ulåst
		if ($locked !== NULL && $locked != ($this->info['ft_locked'] != 0))
		{
			$update .= ", ft_locked = ".($locked ? 1 : 0);
			$only_moved = false;
		}
		
		// ingenting endret?
		if ($update == '' && $this->info['ft_title'] == $title && $this->info['ft_text'] == $text)
		{
			$this->edit_error_nochange();
			return;
		}
		
		// bare flyttet?
		if ($only_moved && $this->info['ft_title'] == $title && $this->info['ft_text'] == $text)
		{
			// rediger forumtråden
			ess::$b->db->query("UPDATE forum_topics SET ft_title = ".ess::$b->db->quote($title)."$update WHERE ft_id = $this->id");
			
			// ble ikke oppdatert?
			if (ess::$b->db->affected_rows() == 0)
			{
				// mest sannsynlig finnes ikke forumtråden, eller så er det oppdatert to ganger samme sekund med samme innhold av samme bruker
				$this->edit_error_failed();
				return;
			}
			
			// oppdater lokal data
			$old_data = array();
			$old_data['fse'] = $this->forum;
			$this->info['ft_fse_id'] = $forum->id;
			$this->forum = $forum;
			
			// logg
			forum_log::add_topic_moved($this, $old_data);
			
			// fullført
			$this->edit_complete();
		}
		
		// rediger forumtråden
		ess::$b->db->query("UPDATE forum_topics SET ft_title = ".ess::$b->db->quote($title).", ft_text = ".ess::$b->db->quote($text).", ft_last_edit = ".time().", ft_last_edit_up_id = ".login::$user->player->id."$update WHERE ft_id = $this->id");
		
		// ble ikke oppdatert?
		if (ess::$b->db->affected_rows() == 0)
		{
			// mest sannsynlig finnes ikke forumtråden, eller så er det oppdatert to ganger samme sekund med samme innhold av samme bruker
			$this->edit_error_failed();
			return;
		}
		
		// oppdater lokal data
		$old_data = array();
		if (isset($forum))
		{
			// kategori
			$old_data['fse'] = $this->forum;
			$this->info['ft_fse_id'] = $forum->id;
			$this->forum = $forum;
			
			// logg
			forum_log::add_topic_moved($this, $old_data);
		}
		if ($type !== NULL && $type != $this->info['ft_type'])
		{
			// type
			$old_data['ft_type'] = $this->info['ft_type'];
			$this->info['ft_type'] = $type;
		}
		if ($locked !== NULL && $locked != ($this->info['ft_locked'] != 0))
		{
			// låst/ulåst
			$old_data['ft_locked'] = $this->info['ft_locked'] != 0;
			$this->info['ft_locked'] = ($locked ? 1 : 0);
		}
		if ($this->info['ft_title'] != $title)
		{
			// tittel
			$old_data['ft_title'] = $this->info['ft_title'];
			$this->info['ft_title'] = $title;
		}
		if ($this->info['ft_text'] != $text)
		{
			// innhold
			$old_data['ft_text'] = $this->info['ft_text'];
			$this->info['ft_text'] = $text;
		}
		$this->info['ft_last_edit'] = time();
		$this->info['ft_last_edit_up_id'] = login::$user->player->id;
		
		// logg
		forum_log::add_topic_edited($this, $old_data);
		
		// fullført
		$this->edit_complete();
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		ess::$b->page->add_message("Forumtråden ble ikke redigert.", "error");
	}
	
	/** Har ikke tilgang til å redigere forumtråden */
	protected function edit_error_403()
	{
		ess::$b->page->add_message("Du har ikke tilgang til å redigere denne forumtråden.", "error");
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke redigere den.", "error");
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function edit_error_length_title()
	{
		ess::$b->page->add_message("Tittelen kan ikke inneholde færre enn ".forum::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".forum::TOPIC_TITLE_MAX_LENGTH." tegn.", "error");
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function edit_error_length()
	{
		ess::$b->page->add_message("Forumtråden kan ikke inneholde færre enn ".forum::TOPIC_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/** Ugyldig forumkategori */
	protected function edit_error_section()
	{
		ess::$b->page->add_message("Fant ikke forumkategorien.", "error");
	}
	
	/** Ugyldig type */
	protected function edit_error_type()
	{
		ess::$b->page->add_message("Ugyldig type.", "error");
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		ess::$b->page->add_message("Ingen endringer ble utført.", "error");
	}
	
	/** Forumtråden ble redigert */
	protected function edit_complete()
	{
		ess::$b->page->add_message("Forumtråden ble redigert.");
		
		// send til forumtråden
		redirect::handle("/forum/topic?id={$this->id}", redirect::ROOT);
	}
	
	/**
	 * Hent ut et bestemt forumsvar i forumtråden
	 * @param integer $reply_id
	 * @return forum_reply
	 */
	public function get_reply($reply_id)
	{
		// forsøk å hent forumsvaret
		$reply = new forum_reply($reply_id, $this);
		
		// fant ikke?
		if (!$reply->info)
		{
			return false;
		}
		
		return $reply;
	}
	
	/**
	 * Legg til nytt forumsvar
	 * @param string $text
	 * @param boolean $no_concatenate ikke sammenslå med evt. forrige forumsvar
	 * @param boolean $announce annonser på IRC/spilleloggen
	 */
	public function add_reply($text, $no_concatenate, $announce)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		// er forumtråden låst?
		if ($this->info['ft_locked'] != 0 && !$this->forum->fmod)
		{
			$this->add_reply_error_locked();
			return;
		}
		
		// er forumtråden slettet?
		if ($this->info['ft_deleted'] != 0)
		{
			$this->add_reply_error_deleted();
			return;
		}
		
		// kontroller blokkering
		if ($this->forum->check_block()) return;
		
		// kontroller ventetid før nytt forumsvar kan legges til
		$this->forum->check_timers();
		if ($this->forum->wait_reply > 0)
		{
			$this->add_reply_error_wait($this->forum->wait_reply);
			return;
		}
		
		// kontroller tekstlengde
		$text = trim($text);
		if (forum::check_length($text) < forum::REPLY_MIN_LENGTH)
		{
			$this->add_reply_error_length();
			return;
		}
		
		// sjekk om vi skal sammenslå dette med det siste forumsvaret
		if (!$no_concatenate)
		{
			// hent siste forumsvaret
			$result = ess::$b->db->query("SELECT fr_id, fr_up_id, fr_time FROM forum_replies WHERE fr_ft_id = $this->id AND fr_deleted = 0 ORDER BY fr_time DESC LIMIT 1");
			$row = mysql_fetch_assoc($result);
			
			// fant forumsvar, og tilhører brukeren
			// forumsvaret er nyere enn 6 timer
			if ($row && $row['fr_up_id'] == login::$user->player->id && (time()-$row['fr_time']) < 21600)
			{
				// slå sammen med dette forumsvaret
				$text = "\n\n[hr]\n\n$text";
				ess::$b->db->query("UPDATE forum_replies SET fr_text = CONCAT(fr_text, ".ess::$b->db->quote($text)."), fr_last_edit = ".time().", fr_last_edit_up_id = ".login::$user->player->id." WHERE fr_id = {$row['fr_id']}");
				
				// annonsere forumsvaret?
				if ($announce && $reply = $this->get_reply($row['fr_id']))
				{
					$reply->announce();
				}
				
				// logg
				forum_log::add_reply_concatenated($this, $row['fr_id']);
				
				$this->add_reply_merged($row['fr_id']);
				return;
			}
		}
		
		// legg til som nytt forumsvar
		ess::$b->db->query("INSERT INTO forum_replies SET fr_time = ".time().", fr_up_id = ".login::$user->player->id.", fr_text = ".ess::$b->db->quote($text).", fr_ft_id = $this->id");
		$reply_id = ess::$b->db->insert_id();
		
		// oppdater forumtråden med antall forumsvar og siste forumsvar
		ess::$b->db->query("UPDATE forum_topics SET ft_replies = ft_replies + 1, ft_last_reply = $reply_id WHERE ft_id = $this->id");
		
		// oppdater spilleren
		if ($this->forum->ff)
		{
			ess::$b->db->query("UPDATE ff_members SET ffm_forum_replies = ffm_forum_replies + 1 WHERE ffm_up_id = ".login::$user->player->id." AND ffm_ff_id = {$this->forum->ff->id}");
			ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_replies = up_forum_ff_num_replies + 1 WHERE up_id = ".login::$user->player->id);
		}
		else
		{
			ess::$b->db->query("UPDATE users, users_players SET up_forum_num_replies = up_forum_num_replies + 1, u_forum_reply_time = ".time()." WHERE up_id = ".login::$user->player->id." AND up_u_id = u_id");
		}
		
		// annonsere forumsvaret?
		if ($announce && $reply = $this->get_reply($reply_id))
		{
			$reply->announce();
		}
		
		// oppdater tid om nødvendig
		$this->forum->update_change_time();
		
		// logg
		forum_log::add_reply_added($this, $reply_id);
		
		// fullført
		$this->add_reply_complete($reply_id);
	}
	
	/** Forumtråden er låst */
	protected function add_reply_error_locked()
	{
		ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke opprette forumsvar i den.", "error");
	}
	
	/** Forumtråden er slettet */
	protected function add_reply_error_deleted()
	{
		ess::$b->page->add_message("Denne forumtråden er slettet. Du kan ikke opprette forumsvar i den.", "error");
	}
	
	/**
	 * Må vente før nytt forumsvar kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_reply_error_wait($wait)
	{
		ess::$b->page->add_message("Du må vente ".game::counter($wait)." før du kan opprette forumsvaret.", "error");
	}
	
	/** For kort lengde i forumsvaret */
	protected function add_reply_error_length()
	{
		ess::$b->page->add_message("Forumsvaret kan ikke inneholde færre enn ".forum::REPLY_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/**
	 * Forumsvaret ble lagt til (merged)
	 * @param integer $reply_id
	 */
	protected function add_reply_merged($reply_id)
	{
		ess::$b->page->add_message("Siden det siste forumsvaret tilhørte deg, har teksten blitt redigert inn i det forumsvaret.");
		redirect::handle("/forum/topic?id={$this->id}&replyid=$reply_id", redirect::ROOT);
	}
	
	/**
	 * Forumsvaret ble lagt til (som nytt forumsvar)
	 */
	protected function add_reply_complete($reply_id)
	{
		ess::$b->page->add_message("Forumsvaret ble lagt til.");
		redirect::handle("/forum/topic?id={$this->id}&replyid=$reply_id", redirect::ROOT);
	}
}

/**
 * Forumtråd (ajax)
 */
class forum_topic_ajax extends forum_topic
{
	/** Slettet, men tilgang */
	protected function deleted_with_access(){}
	
	/** Slettet og uten tilgang, eller finnes ikke */
	protected function error_404()
	{
		ajax::text("ERROR:404-TOPIC", ajax::TYPE_INVALID);
	}
	
	/** Hent informasjon om forumkategorien og kontroller tilgang */
	protected function load_forum()
	{
		$this->forum = new forum_ajax($this->info['ft_fse_id']);
		$this->forum->require_access();
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		ajax::text("ERROR:EDIT-FAILED", ajax::TYPE_INVALID);
	}
	
	/** Har ikke tilgang til å redigere forumtråden */
	protected function edit_error_403()
	{
		ajax::text("ERROR:403-TOPIC", ajax::TYPE_INVALID);
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		ajax::text("Forumtråden er låst. Du kan ikke redigere den.", ajax::TYPE_INVALID);
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function edit_error_length_title()
	{
		ajax::text("Tittelen kan ikke inneholde færre enn ".forum::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".forum::TOPIC_TITLE_MAX_LENGTH." tegn.", ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function edit_error_length()
	{
		ajax::text("Forumtråden kan ikke inneholde færre enn ".forum::TOPIC_MIN_LENGTH." bokstaver/tall.", ajax::TYPE_INVALID);
	}
	
	/** Ugyldig forumkategori */
	protected function edit_error_section()
	{
		ajax::text("ERROR:404-NEW-FORUM", ajax::TYPE_INVALID);
	}
	
	/** Ugyldig type */
	protected function edit_error_type()
	{
		ajax::text("ERROR:INVALID-TYPE", ajax::TYPE_INVALID);
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		ajax::text("Ingen endringer ble utført.", ajax::TYPE_INVALID);
	}
	
	/** Forumtråden ble redigert */
	protected function edit_complete()
	{
		ess::$b->page->add_message("Endringene i forumtråden ble lagret.");
		
		ajax::text("REDIRECT:".ess::$s['relative_path']."/forum/topic?id={$this->id}");
	}
	
	/** Forumet er låst */
	protected function add_reply_error_locked()
	{
		ajax::text("Forumtråden er låst. Du kan ikke legge til nye forumsvar.", ajax::TYPE_INVALID);
	}
	
	/** Forumet er låst */
	protected function add_reply_error_deleted()
	{
		ajax::text("Forumtråden er slettet. Du kan ikke legge til nye forumsvar.", ajax::TYPE_INVALID);
	}
	
	/**
	 * Må vente før nytt forumsvar kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_reply_error_wait($wait)
	{
		ajax::html("Du må vente ".game::counter($wait)." før du kan opprette forumsvaret.", ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i forumsvaret */
	protected function add_reply_error_length()
	{
		ajax::text("Forumsvaret kan ikke inneholde færre enn ".forum::REPLY_MIN_LENGTH." bokstaver/tall.", ajax::TYPE_INVALID);
	}
	
	/**
	 * Forumsvaret ble lagt til (merged)
	 * @param integer $reply_id
	 */
	protected function add_reply_merged($reply_id)
	{
		ess::$b->page->add_message("Siden det siste forumsvaret tilhørte deg, har teksten blitt redigert inn i det forumsvaret.");
		
		ajax::text("REDIRECT:".ess::$s['relative_path']."/forum/topic?id={$this->id}&replyid=$reply_id");
	}
	
	/**
	 * Forumsvaret ble lagt til (som nytt forumsvar)
	 */
	protected function add_reply_complete($reply_id)
	{
		ajax::text("REDIRECT:".ess::$s['relative_path']."/forum/topic?id={$this->id}&replyid=$reply_id");
	}
	
	/**
	 * Hent ut et bestemt forumsvar i forumtråden
	 * @param integer $reply_id
	 * @return forum_reply_ajax
	 */
	public function get_reply($reply_id)
	{
		// forsøk å hent forumsvaret
		$reply = new forum_reply_ajax($reply_id, $this);
		
		// fant ikke?
		if (!$reply->info)
		{
			return false;
		}
		
		return $reply;
	}
}

/**
 * Forumsvar
 */
class forum_reply
{
	/** ID-en til forumsvaret */
	public $id;
	
	/** Informasjon om forumsvaret */
	public $info;
	
	/**
	 * Forumtråden
	 * @var forum_topic
	 */
	public $topic;
	
	/**
	 * Constructor
	 * @param integer $reply_id
	 * @param forum_topic $topic
	 */
	public function __construct($reply_id, forum_topic $topic = NULL)
	{
		$this->id = (int) $reply_id;
		
		// hent informasjon om forumsvaret
		$result = ess::$b->db->query("
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
		$result = ess::$b->db->query("
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
				LEFT JOIN rapportering ON r_type = ".rapportering::TYPE_FORUM_REPLY." AND r_type_id = r.fr_id AND r_state < 2
				LEFT JOIN forum_replies n ON n.fr_ft_id = r.fr_ft_id AND n.fr_id < r.fr_id AND n.fr_deleted = 0
			WHERE r.fr_id = $this->id
			GROUP BY r.fr_id
			ORDER BY r.fr_time ASC");
		
		$row = mysql_fetch_assoc($result);
		$row['ft_fse_id'] = $this->topic->forum->id;
		$row['ft_id'] = $this->topic->id;
		$row['fs_new'] = $this->topic->info['fs_time'] < $row['fr_time'] && forum::$fs_check;
		
		return $row;
	}
	
	/** Kontroller og krev tilgang til forumsvaret */
	public function require_access()
	{
		if (($this->info['fr_deleted'] != 0 || !login::$logged_in || $this->info['fr_up_id'] != login::$user->player->id) && !$this->topic->forum->fmod)
		{
			$this->error_403();
			return false;
		}
		return true;
	}
	
	/**
	 * Hent informasjon om forumtråden
	 * @param forum_topic $topic 
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
		return new forum_topic($topic_id);
	}
	
	/** Ikke tilgang til forumsvaret */
	protected function error_403()
	{
		ess::$b->page->add_message("Du har ikke tilgang til dette forumsvaret.", "error");
		
		// slettet?
		if ($this->info['fr_deleted'] != 0)
		{
			// send til forumtråden
			redirect::handle("/forum/topic?id={$this->info['fr_ft_id']}", redirect::ROOT);
		}
		
		else
		{
			// send til forumsvaret
			redirect::handle("/forum/topic?id={$this->info['fr_ft_id']}&replyid=$this->id", redirect::ROOT);
		}
	}
	
	/** Fant ikke forumsvaret */
	protected function error_404()
	{
		ess::$b->page->add_message("Fant ikke forumsvaret.", "error");
		
		// send til forumoversikten
		redirect::handle("/forum/forum", redirect::ROOT);
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
		forum_log::add_reply_deleted($this);
		
		// fullført
		$this->delete_complete();
	}
	
	/** Forumtråden er låst */
	protected function delete_error_locked()
	{
		ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke slette forumsvaret.", "error");
	}
	
	/** Utfør selve slettingen av forumsvaret */
	protected function delete_action()
	{
		// forsøk å slett forumsvaret
		ess::$b->db->query("UPDATE forum_replies SET fr_deleted = 1 WHERE fr_id = $this->id AND fr_deleted = 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		// var dette siste forumsvar i forumtråden?
		if ($this->id == $this->topic->info['ft_last_reply'])
		{
			// hent siste forumsvaret i forumtråden
			$result = ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_deleted = 0 ORDER BY fr_id DESC LIMIT 1");
			$reply_id = mysql_num_rows($result) > 0 ? mysql_result($result, 0) : 0;
			if (!$reply_id) $reply_id = "NULL";
			
			// sett som siste forumsvar
			ess::$b->db->query("UPDATE forum_topics SET ft_last_reply = $reply_id, ft_replies = ft_replies - 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// senk telleren over antall forumsvar
		else
		{
			ess::$b->db->query("UPDATE forum_topics SET ft_replies = ft_replies - 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// senk telleren til spilleren over antall forumsvar
		if ($this->topic->forum->ff)
		{
			ess::$b->db->query("UPDATE ff_members SET ffm_forum_replies = ffm_forum_replies - 1 WHERE ffm_up_id = {$this->info['fr_up_id']} AND ffm_ff_id = {$this->topic->forum->ff->id}");
			ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_replies = up_forum_ff_num_replies - 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		else
		{
			ess::$b->db->query("UPDATE users_players SET up_forum_num_replies = up_forum_num_replies - 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		
		return true;
	}
	
	/** Forumsvaret er allerede slettet */
	protected function delete_dupe()
	{
		ess::$b->page->add_message("Forumsvaret er allerede slettet.", "error");
	}
	
	/** Forumsvaret ble slettet */
	protected function delete_complete()
	{
		ess::$b->page->add_message("Forumsvaret ble slettet. Antall forumsvar brukeren har hatt ble redusert med 1.");
		
		// hent neste forumsvar
		$result = ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id > $this->id AND fr_deleted = 0 ORDER BY fr_id LIMIT 1");
		
		// eller hente forrige forumsvar
		if (mysql_num_rows($result) == 0)
		{
			$result = ess::$b->db->query("SELECT fr_id FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id < $this->id AND fr_deleted = 0 ORDER BY fr_id DESC LIMIT 1");
		}
		
		// har vi noe neste forumsvar?
		if ($row = mysql_fetch_assoc($result))
		{
			// hent antall forumsvar før dette forumsvaret
			$result = ess::$b->db->query("SELECT COUNT(fr_id) FROM forum_replies WHERE fr_ft_id = {$this->topic->id} AND fr_id < {$row['fr_id']} AND fr_deleted = 0");
			$skip = mysql_result($result, 0);
			
			// send til riktig forumsvar
			$page = ceil($skip / $this->topic->replies_per_page);
			redirect::handle("/forum/topic?id={$this->topic->id}&p=$page#m_{$row['fr_id']}", redirect::ROOT);
		}
		
		redirect::handle();
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
		forum_log::add_reply_restored($this);
		
		// fullført
		$this->restore_complete();
	}
	
	/** Utfør selve gjenopprettingen av forumsvaret */
	protected function restore_action()
	{
		// forsøk å gjenopprett forumsvaret
		ess::$b->db->query("UPDATE forum_replies SET fr_deleted = 0 WHERE fr_id = $this->id AND fr_deleted != 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		// er dette det siste forumsvaret i forumtråden?
		if ($this->id > $this->topic->info['ft_last_reply'])
		{
			// sett som siste forumsvar
			ess::$b->db->query("UPDATE forum_topics SET ft_last_reply = $this->id, ft_replies = ft_replies + 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// øk telleren over antall forumsvar
		else
		{
			ess::$b->db->query("UPDATE forum_topics SET ft_replies = ft_replies + 1 WHERE ft_id = {$this->topic->id}");
		}
		
		// øk telleren til brukeren over antall forumsvar
		if ($this->topic->forum->ff)
		{
			ess::$b->db->query("UPDATE ff_members SET ffm_forum_replies = ffm_forum_replies + 1 WHERE ffm_up_id = {$this->info['fr_up_id']} AND ffm_ff_id = {$this->topic->forum->ff->id}");
			ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_replies = up_forum_ff_num_replies + 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		else
		{
			ess::$b->db->query("UPDATE users_players SET up_forum_num_replies = up_forum_num_replies + 1 WHERE up_id = {$this->info['fr_up_id']}");
		}
		
		return true;
	}
	
	/** Forumsvaret er allerede gjenopprettet */
	protected function restore_dupe()
	{
		ess::$b->page->add_message("Forumsvaret er allerede gjenopprettet.", "error");
		
		// send til forumsvaret
		redirect::handle("/forum/topic?id={$this->topic->id}&replyid=$this->id", redirect::ROOT);
	}
	
	/** Forumsvaret ble gjenopprettet */
	protected function restore_complete()
	{
		ess::$b->page->add_message("Forumsvaret ble gjenopprettet. Antall forumsvar brukeren har hatt ble økt med 1.");
		
		// send til forumsvaret
		redirect::handle("/forum/topic?id={$this->topic->id}&replyid=$this->id", redirect::ROOT);
	}
	
	/**
	 * Rediger forumsvaret
	 * @param string $text nytt innhold
	 */
	public function edit($text)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
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
		if (forum::check_length($text) < forum::REPLY_MIN_LENGTH)
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
		ess::$b->db->query("UPDATE forum_replies SET fr_text = ".ess::$b->db->quote($text).", fr_last_edit = ".time().", fr_last_edit_up_id = ".login::$user->player->id." WHERE fr_id = $this->id");
		
		// ble ikke oppdatert?
		if (ess::$b->db->affected_rows() == 0)
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
		$this->info['fr_last_edit_up_id'] = login::$user->player->id;
		
		// logg
		forum_log::add_reply_edited($this, $old_data);
		
		// fullført
		$this->edit_complete();
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		ess::$b->page->add_message("Forumsvaret ble ikke redigert.", "error");
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke redigere forumsvar i den.", "error");
	}
	
	/** For kort lengde i forumsvaret */
	protected function edit_error_length()
	{
		ess::$b->page->add_message("Forumsvaret kan ikke inneholde færre enn ".forum::REPLY_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		ess::$b->page->add_message("Ingen endringer ble utført.", "error");
	}
	
	/** Forumsvaret ble redigert */
	protected function edit_complete()
	{
		ess::$b->page->add_message("Forumsvaret ble redigert.");
		
		// send til forumsvaret
		redirect::handle("/forum/topic?id={$this->topic->id}&replyid=$this->id", redirect::ROOT);
	}
	
	/**
	 * Annonser forumsvaret
	 * Kan også brukes for å annonsere et forumsvar på nytt
	 */
	public function announce()
	{
		// finn riktig brukernavn
		if (login::$logged_in && $this->info['fr_up_id'] == login::$user->player->id)
		{
			$name = login::$user->player->data['up_name'];
		}
		else
		{
			$result = ess::$b->db->query("SELECT up_name FROM users_players WHERE up_id = {$this->info['fr_up_id']}");
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
			putlog("INFO", "FORUMSVAR (Crew): (".$this->topic->forum->get_name().") '{$name}' svarte i '{$this->topic->info['ft_title']}' ".ess::$s['path']."/forum/topic?id={$this->topic->id}&replyid=$this->id");
		}
		
		// crewforumet, crewarkivforumet og idemyldringsforumet
		elseif ($this->topic->forum->id >= 5 && $this->topic->forum->id <= 7)
		{
			// legg til hendelse i spilleloggen
			$type = $this->topic->forum->id == 5 ? 'crewforum_svar' : ($this->topic->forum->id == 6 ? 'crewforuma_svar' : 'crewforumi_svar');
			$access_levels = implode(",", ess::$g['access']['crewet']);
			ess::$b->db->query("INSERT INTO users_log SET time = ".time().", ul_up_id = 0, type = ".intval(gamelog::$items[$type]).", note = ".ess::$b->db->quote($this->info['fr_up_id']."#".$this->id.":".$this->topic->info['ft_title']).", num = {$this->topic->id}");
			
			$upd = login::$logged_in ? " AND (u_id != ".login::$user->id." OR u_log_crew_new > 0)" : "";
			ess::$b->db->query("UPDATE users SET u_log_crew_new = u_log_crew_new + 1 WHERE u_access_level IN ($access_levels)$upd");
		}
	}
}

/**
 * Forumsvar (ajax)
 */
class forum_reply_ajax extends forum_reply
{
	/** Hent forumtråd objekt */
	protected function get_topic_obj($topic_id)
	{
		return new forum_topic_ajax($topic_id);
	}
	
	/** Ikke tilgang til forumsvaret */
	protected function error_403()
	{
		ajax::text("ERROR:403-REPLY", ajax::TYPE_INVALID);
	}
	
	/** Forumtråden er låst */
	protected function delete_error_locked()
	{
		ajax::text("Forumtråden er låst. Du kan ikke slette forumsvaret.", ajax::TYPE_INVALID);
	}
	
	/** Forumsvaret er allerede slettet */
	protected function delete_dupe()
	{
		$this->delete_complete();
	}
	
	/** Forumsvaret ble slettet */
	protected function delete_complete()
	{
		// hent utvidet informasjon og returner HTML-malen
		ajax::html(parse_html($this->topic->forum->template_topic_reply($this->extended_info())));
	}
	
	/** Forumsvaret er allerede gjenopprettet */
	protected function restore_dupe()
	{
		$this->restore_complete();
	}
	
	/** Forumsvaret ble gjenopprettet */
	protected function restore_complete()
	{
		// hent utvidet informasjon og returner HTML-malen
		ajax::html(parse_html($this->topic->forum->template_topic_reply($this->extended_info())));
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		ajax::text("ERROR:EDIT-FAILED", ajax::TYPE_INVALID);
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		ajax::text("Forumtråden er låst. Du kan ikke redigere forumsvaret.", ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i forumsvaret */
	protected function edit_error_length()
	{
		ajax::text("Forumsvaret kan ikke inneholde færre enn ".forum::REPLY_MIN_LENGTH." bokstaver/tall.", ajax::TYPE_INVALID);
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		ajax::text("Ingen endringer ble utført.", ajax::TYPE_INVALID);
	}
	
	/** Forumsvaret ble redigert */
	protected function edit_complete()
	{
		// hent utvidet informasjon og returner HTML-malen inni XML
		ajax::xml('<data><reply id="'.$this->id.'" last_edit="'.$this->info['fr_last_edit'].'">'.htmlspecialchars(parse_html($this->topic->forum->template_topic_reply($this->extended_info()))).'</reply></data>');
	}
}

/**
 * Forumlogg
 * @static
 */
class forum_log
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
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		if (!$reply_id) $reply_id = "NULL";
		ess::$b->db->query("INSERT INTO forum_log SET flg_ft_id = $topic_id, flg_fr_id = $reply_id, flg_action = $action, flg_up_id = ".login::$user->player->id.", flg_time = ".time());
	}
	
	/**
	 * Tunnel til putlog funksjonen
	 * @return unknown
	 */
	protected static function putlog(forum $forum, $location, $msg)
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
	protected static function crewlog(forum $forum, $type, $a_up_id, $log, $data)
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
		
		crewlog::log($ff_prefix.$type, $a_up_id, $log, $data);
	}
	
	/**
	 * Skal dette logges som crewhandling?
	 */
	protected static function is_crewlog(forum $forum, $up_id = null)
	{
		return (!$forum->ff && $up_id != login::$user->player->id) || ($forum->ff && $forum->ff->uinfo && $forum->ff->uinfo->crew);
	}
	
	/**
	 * Legg til forumtråd
	 * @param forum $forum
	 * @param array $data (ft_id, ft_title, ft_type)
	 */
	public static function add_topic_added(forum $forum, $data)
	{
		// finn ut hvor loggen skal plasseres
		$location = "INFO";
		
		// crewforum, crewforum arkiv eller idémyldringsforumet
		if ($forum->id >= 5 && $forum->id <= 7)
		{
			$location = "CREWCHAN";
			
			// legg til hendelse i spilleloggen
			$type = $forum->id == 5 ? 'crewforum_emne' : ($forum->id == 6 ? 'crewforuma_emne' : 'crewforumi_emne');
			$access_levels = implode(",", ess::$g['access']['crewet']);
			ess::$b->db->query("INSERT INTO users_log SET time = ".time().", ul_up_id = 0, type = ".intval(gamelog::$items[$type]).", note = ".ess::$b->db->quote(login::$user->player->id.":".$data['ft_title']).", num = {$data['ft_id']}");
			ess::$b->db->query("UPDATE users SET u_log_crew_new = u_log_crew_new + 1 WHERE u_access_level IN ($access_levels) AND (u_id != ".login::$user->id." OR u_log_crew_new > 0)");
			
			// send e-post til crewet
			$email = new email();
			$email->text = "*{$data['ft_title']}* ble opprettet av ".login::$user->player->data['up_name']."\r\n".ess::$s['path']."/forum/topic?id={$data['ft_id']}\r\n\r\nForum: ".$forum->get_name()."\r\nAutomatisk melding for Kofradia Crewet";
			$result = ess::$b->db->query("SELECT u_email FROM users WHERE u_access_level IN ($access_levels) AND u_id != ".login::$user->id);
			while ($row = mysql_fetch_assoc($result))
			{
				$email->send($row['u_email'], login::$user->player->data['up_name']." opprettet {$data['ft_title']} -- ".$forum->get_name()."");
			}
		}
		
		//Evalueringsforum
		elseif ($forum->id == 4)
		{
			$location = "";
				
			// legg til hendelse i spilleloggen
			$type = $forum->id == 4 ? 'crewforume_emne' : 'crewforum_emne';
			$access_levels = implode(",", ess::$g['access']['admin']);
			ess::$b->db->query("INSERT INTO users_log SET time = ".time().", ul_up_id = 0, type = ".intval(gamelog::$items[$type]).", note = ".ess::$b->db->quote(login::$user->player->id.":".$data['ft_title']).", num = {$data['ft_id']}");
			ess::$b->db->query("UPDATE users SET u_log_crew_new = u_log_crew_new + 1 WHERE u_access_level IN ($access_levels) AND (u_id != ".login::$user->id." OR u_log_crew_new > 0)");
		
			// send e-post til crewet
			$email = new email();
			$email->text = "*{$data['ft_title']}* ble opprettet av ".login::$user->player->data['up_name']."\r\n".ess::$s['path']."/forum/topic?id={$data['ft_id']}\r\n\r\nForum: ".$forum->get_name()."\r\nAutomatisk melding for Kofradia Crewet";
			$result = ess::$b->db->query("SELECT u_email FROM users WHERE u_access_level IN ($access_levels) AND u_id != ".login::$user->id);
			while ($row = mysql_fetch_assoc($result))
			{
				$email->send($row['u_email'], login::$user->player->data['up_name']." opprettet {$data['ft_title']} -- ".$forum->get_name()."");
			}
		}
		
		elseif (!$forum->ff)
		{
			// live-feed
			livefeed::add_row('<user id="'.login::$user->player->id.'" /> opprettet <a href="'.ess::$s['relative_path'].'/forum/topic?id='.$data['ft_id'].'">'.htmlspecialchars($data['ft_title']).'</a> i '.htmlspecialchars($forum->get_name()).'.');
		}
		
		// legg til som logg
		self::putlog($forum, $location, "FORUMTRÅD: (".$forum->get_name().") '".login::$user->player->data['up_name']."' opprettet '{$data['ft_title']}' ".ess::$s['path']."/forum/topic?id={$data['ft_id']}");
	}
	
	/**
	 * Slettet forumtråd
	 * @param forum_topic $topic
	 */
	public static function add_topic_deleted(forum_topic $topic)
	{
		// legg til i forum_log
		self::add_log(self::TOPIC_DELETED, $topic->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD SLETTET: '".login::$user->player->data['up_name']."' slettet forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) ".ess::$s['path']."/forum/topic?id={$topic->id}");
		
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
			$topic->forum->ff->add_log("forum_topic_delete", "".login::$user->player->id.":".$topic->info['ft_id'].":".urlencode($topic->info['ft_title']));
		}
	}
	
	/**
	 * Gjenopprettet forumtråd
	 * @param forum_topic $topic
	 */
	public static function add_topic_restored(forum_topic $topic)
	{
		// legg til i forum_log
		self::add_log(self::TOPIC_RESTORED, $topic->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD GJENOPPRETTET: '".login::$user->player->data['up_name']."' gjenoppretttet forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) ".ess::$s['path']."/forum/topic?id={$topic->id}");
		
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
			$topic->forum->ff->add_log("forum_topic_restore", "".login::$user->player->id.":".$topic->info['ft_id'].":".urlencode($topic->info['ft_title']));
		}
	}
	
	/**
	 * Flytt forumtråd
	 * @param forum_topic $topic
	 * @param array $old_data array med data som ble erstattet
	 */
	public static function add_topic_moved(forum_topic $topic, $old_data)
	{
		$from = $old_data['fse']->get_name();
		$to = $topic->forum->get_name();
		
		// legg til som vanlig logg
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD FLYTTET: '".login::$user->player->data['up_name']."' flyttet forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) fra $from til $to ".ess::$s['path']."/forum/topic?id={$topic->id}");
		
		// legg til hendelse
		if ($topic->info['ft_up_id'] != login::$user->player->id)
		{
			player::add_log_static("forum_topic_move", "{$topic->id}:".urlencode($topic->info['ft_title']).":".urlencode($from).":".urlencode($to), null, $topic->info['ft_up_id']);
		}
		
		// TODO: er det nødvendig med crewlogg?
	}
	
	/**
	 * Rediger forumtråd
	 * @param forum_topic $topic
	 * @param array $old_data array med data som ble erstattet (title, text, section(obj forum), type, locked)
	 */
	public static function add_topic_edited(forum_topic $topic, $old_data)
	{
		// legg til som vanlig logg
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMTRÅD REDIGERT: '".login::$user->player->data['up_name']."' redigerte forumtråden med ID {$topic->id} ({$topic->info['ft_title']}) ".ess::$s['path']."/forum/topic?id={$topic->id}");
		
		// legg til crewlogg
		if (self::is_crewlog($topic->forum, $topic->info['ft_up_id']))
		{
			$data = array(
				"topic_id" => $topic->id,
				"topic_title_old" => isset($old_data['ft_title']) ? $old_data['ft_title'] : $topic->info['ft_title'],
				"topic_content_old" => isset($old_data['ft_text']) ? $old_data['ft_text'] : $topic->info['ft_text']
			);
			if (isset($old_data['ft_title'])) $data['topic_title_new'] = $topic->info['ft_title'];
			if (isset($old_data['ft_text'])) $data['topic_content_diff'] = diff::make($old_data['ft_text'], $topic->info['ft_text']);
			
			self::crewlog($topic->forum, "forum_topic_edit", $topic->info['ft_up_id'], NULL, $data);
		}
		
		// ff-log
		if ($topic->forum->ff)
		{
			$topic->forum->ff->add_log("forum_topic_edit", "".login::$user->player->id.":".$topic->info['ft_id'].":".urlencode($topic->info['ft_title']));
		}
	}
	
	/**
	 * Legg til forumsvar
	 * @param forum_topic $topic
	 * @param integer $reply_id
	 */
	public static function add_reply_added(forum_topic $topic, $reply_id)
	{
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMSVAR: (".$topic->forum->get_name().") '".login::$user->player->data['up_name']."' svarte i '{$topic->info['ft_title']}' ".ess::$s['path']."/forum/topic?id={$topic->id}&replyid=$reply_id");
	}
	
	/**
	 * Legg til formsvar (sammenslått med forrige svar)
	 * @param forum_topic $topic
	 * @param integer $reply_id
	 */
	public static function add_reply_concatenated(forum_topic $topic, $reply_id)
	{
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $topic->forum->id >= 5 && $topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($topic->forum, $location, "FORUMSVAR (sammenslått): (".$topic->forum->get_name().") '".login::$user->player->data['up_name']."' svarte i '{$topic->info['ft_title']}' ".ess::$s['path']."/forum/topic?id={$topic->id}&replyid=$reply_id");
	}
	
	/**
	 * Slett forumsvar
	 * @param forum_reply $reply
	 */
	public static function add_reply_deleted(forum_reply $reply)
	{
		// legg til i forum_log
		self::add_log(self::REPLY_DELETED, $reply->topic->id, $reply->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $reply->topic->forum->id >= 5 && $reply->topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($reply->topic->forum, $location, "FORUMSVAR SLETTET: '".login::$user->player->data['up_name']."' slettet forumsvaret med ID {$reply->id} i forumtråden med ID {$reply->topic->id} ({$reply->topic->info['ft_title']}) ".ess::$s['path']."/forum/topic?id={$reply->topic->id}&replyid=$reply->id");
		
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
	 * @param forum_reply $reply
	 */
	public static function add_reply_restored(forum_reply $reply)
	{
		// legg til i forum_log
		self::add_log(self::REPLY_RESTORED, $reply->topic->id, $reply->id);
		
		// hvor skal loggen? (vanlig logg eller crewchan)
		$location = $reply->topic->forum->id >= 5 && $reply->topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($reply->topic->forum, $location, "FORUMSVAR GJENOPPRETTET: '".login::$user->player->data['up_name']."' gjenopprettet forumsvaret med ID {$reply->id} i forumtråden med ID {$reply->topic->id} ({$reply->topic->info['ft_title']}) ".ess::$s['path']."/forum/topic?id={$reply->topic->id}&replyid=$reply->id");
		
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
	 * @param forum_reply $reply
	 * @param array $old_data array med data som ble erstattet (text)
	 */
	public static function add_reply_edited(forum_reply $reply, $old_data)
	{
		// legg til som vanlig logg
		$location = $reply->topic->forum->id >= 5 && $reply->topic->forum->id <= 7 ? 'CREWCHAN' : 'LOG';
		self::putlog($reply->topic->forum, $location, "FORUMSVAR REDIGERT: '".login::$user->player->data['up_name']."' redigerte forumsvaret med ID {$reply->id} i forumtråden med ID {$reply->topic->id} ({$reply->topic->info['ft_title']}) ".ess::$s['path']."/forum/topic?id={$reply->topic->id}&replyid=$reply->id");
		
		// legg til crewlogg
		if (self::is_crewlog($reply->topic->forum, $reply->info['fr_up_id']))
		{
			self::crewlog($reply->topic->forum, "forum_reply_edit", $reply->info['fr_up_id'], NULL, array(
				"topic_id" => $reply->topic->id,
				"reply_id" => $reply->id,
				"topic_title" => $reply->topic->info['ft_title'],
				"reply_content_old" => $old_data['fr_text'],
				"reply_content_diff" => diff::make($old_data['fr_text'], $reply->info['fr_text'])
			));
		}
	}
}