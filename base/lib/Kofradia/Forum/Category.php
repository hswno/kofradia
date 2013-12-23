<?php namespace Kofradia\Forum;

/**
 * Forum category
 */
class Category {
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
		$result = \ess::$b->db->query("SELECT fse_id, fse_name, fse_description, fse_params, fse_ff_id FROM forum_sections WHERE fse_id = $this->id");
		
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
			$this->ff = \ff::get_ff($this->info['fse_ff_id'], \ff::LOAD_IGNORE);
			if (!$this->ff) throw new \HSException("Fant ikke FF med ID {$this->info['fse_ff_id']}.");
		}
		
		// sett opp params
		$this->params = new \params($this->info['fse_params']);
		
		// har vi forum-mod tilganger til dette forumet?
		if ($this->ff)
		{
			$this->fmod = $this->ff->access(2);
		}
		else
		{
			$this->fmod = \access::has("forum_mod");
		}
		
		// ikke vise NY
		if (!\login::$logged_in) self::$fs_check = false;
	}
	
	/**
	 * Legg til tittel
	 */
	public function add_title()
	{
		// ff?
		if ($this->ff)
		{
			\ess::$b->page->add_title($this->ff->data['ff_name'], "Forum");
		}
		
		else
		{
			\ess::$b->page->add_title("Forum", $this->info['fse_name']);
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
		\ess::$b->page->add_message("Fant ikke forumet.", "error");
		\ess::$b->page->load();
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
		if (($access = $params->get("need_access_name")) && !\access::has($access))
		{
			return false;
		}
		
		return true;
	}
	
	/** Hent ut liste over alle forumkategoriene brukeren har tilgang til */
	public static function get_forum_list()
	{
		// hent alle forumkategoriene
		if (\login::$logged_in)
		{
			$result = \ess::$b->db->query("
				SELECT fse_id, fse_name, ff_name, fse_params, fse_ff_id, ff_inactive, ffm_status
				FROM forum_sections
					LEFT JOIN ff ON ff_id = fse_ff_id
					LEFT JOIN ff_members ON ffm_ff_id = ff_id AND ffm_up_id = ".\login::$user->player->id." AND ffm_status = 1
				ORDER BY fse_ff_id IS NOT NULL, IF(fse_ff_id IS NULL, fse_name, ff_name)");
		}
		else
		{
			$result = \ess::$b->db->query("
				SELECT fse_id, fse_name, NULL ff_name, fse_params, fse_ff_id, NULL ff_inactive, NULL ffm_status
				FROM forum_sections
				ORDER BY fse_ff_id IS NOT NULL, fse_name");
		}
		$sections = array();
		while ($row = mysql_fetch_assoc($result))
		{
			// ff som vi ikke har tilgang til?
			if ($row['fse_ff_id'] && !\access::has("mod"))
			{
				if (!$row['ffm_status'] || $row['ff_inactive']) continue;
			}
			
			// har vi tilgang til denne forumkategorien?
			$params = new \params($row['fse_params']);
			if (\Kofradia\Forum\Category::check_access_params($params))
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
		\ess::$b->page->add_message("Du har ikke tilgang til dette forumet.", "error");
		\ess::$b->page->load();
	}
	
	/** Legg med RSS lenker i head */
	public function rss_links()
	{
		// ingen RSS for FF
		if ($this->ff) return;
		
		\ess::$b->page->add_head('<link rel="alternate" href="'.\ess::$s['relative_path'].'/rss/forum_topics?forum='.$this->id.'" type="application/rss+xml" title="Siste forumtråder i '.htmlspecialchars($this->get_name()).'" />');
		\ess::$b->page->add_head('<link rel="alternate" href="'.\ess::$s['relative_path'].'/rss/forum_replies?forum='.$this->id.'" type="application/rss+xml" title="Siste forumsvar i '.htmlspecialchars($this->get_name()).'" />');
	}
	
	/** Redirect til forumet */
	public function redirect()
	{
		\redirect::handle("/forum/forum?id=$this->id", \redirect::ROOT);
	}
	
	/** Sjekk timere */
	public function check_timers()
	{
		// ingen ventetid for FF
		if ($this->ff) return;
		
		// crewet har ikke sperrer
		if (\access::has("crewet")) return;
		
		// ventetid for ny forumtråd
		if (\login::$user->data['u_forum_topic_time'] > 0)
		{
			$this->wait_topic = max(0, \login::$user->data['u_forum_topic_time'] + \game::$settings['delay_forum_new']['value'] - time());
		}
		
		// ventetid for nytt forumsvar
		if (\login::$user->data['u_forum_reply_time'] > 0)
		{
			$this->wait_reply = max(0, \login::$user->data['u_forum_reply_time'] + \game::$settings['delay_forum_reply']['value'] - time());
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
		$blokkering = \blokkeringer::check(\blokkeringer::TYPE_FORUM);
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
		\ess::$b->page->add_message("Du er blokkert fra å utføre handlinger i forumet. Blokkeringen varer til ".\ess::$b->date->get($blokkering['ub_time_expire'])->format(\date::FORMAT_SEC).".<br />\n"
			."<b>Begrunnelse:</b> ".\game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
	}
	
	/** Kontroller rankkrav for å skrive i en topic */
	public function check_rank()
	{
		// crewet har uansett tilgang
		if (\access::has("crewet")) return true;
		
		// ingen begrensninger i FF
		if ($this->ff) return true;
		
		// kontroller ranken
		return \login::$user->player->rank['number'] >= self::TOPIC_MIN_RANK;
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
		if (!\login::$logged_in) throw new \HSNotLoggedIn();
		
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
		if (mb_strlen($title) < \Kofradia\Forum\Category::TOPIC_TITLE_MIN_LENGTH || mb_strlen($title) > \Kofradia\Forum\Category::TOPIC_TITLE_MAX_LENGTH)
		{
			$this->add_topic_error_length_title();
			return;
		}
		
		// kontroller tekstlengde (innhold)
		$text = trim($text);
		if (\Kofradia\Forum\Category::check_length($text) < \Kofradia\Forum\Category::TOPIC_MIN_LENGTH)
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
		\ess::$b->db->query("INSERT INTO forum_topics SET ft_title = ".\ess::$b->db->quote($title).", ft_text = ".\ess::$b->db->quote($text).", ft_time = ".time().", ft_up_id = ".\login::$user->player->id.", ft_fse_id = $this->id$set");
		$topic_id = \ess::$b->db->insert_id();
		
		// oppdater spilleren
		if ($this->ff)
		{
			\ess::$b->db->query("UPDATE ff_members SET ffm_forum_topics = ffm_forum_topics + 1 WHERE ffm_up_id = ".\login::$user->player->id." AND ffm_ff_id = {$this->ff->id}");
			\ess::$b->db->query("UPDATE users_players SET up_forum_ff_num_topics = up_forum_ff_num_topics + 1 WHERE up_id = ".\login::$user->player->id);
		}
		else
		{
			\ess::$b->db->query("UPDATE users, users_players SET up_forum_num_topics = up_forum_num_topics + 1, u_forum_topic_time = ".time()." WHERE up_id = ".\login::$user->player->id." AND u_id = up_u_id");
		}
		
		// oppdater tid om nødvendig
		$this->update_change_time();
		
		// logg
		\Kofradia\Forum\Log::add_topic_added($this, array(
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
		$rank_info = \game::$ranks['items_number'][self::TOPIC_MIN_RANK][$rank_id];
		
		\ess::$b->page->add_message("Du har ikke høy nok rank for å skrive i dette forumet. Du må ha nådd ranken <b>".htmlspecialchars($rank_info['name'])."</b>.", "error");
	}
	
	/**
	 * Må vente før ny forumtråd kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_topic_error_wait($wait)
	{
		\ess::$b->page->add_message("Du må vente ".\game::counter($wait)." før du kan opprette ny forumtråd.", "error");
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function add_topic_error_length_title()
	{
		\ess::$b->page->add_message("Tittelen kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MAX_LENGTH." tegn.", "error");
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function add_topic_error_length()
	{
		\ess::$b->page->add_message("Forumtråden kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/** Ugyldig type */
	protected function add_topic_error_type()
	{
		\ess::$b->page->add_message("Ugyldig type.", "error");
	}
	
	/** Forumtråden ble redigert */
	protected function add_topic_complete($topic_id)
	{
		\ess::$b->page->add_message("Forumtråden ble opprettet.");
		
		// send til forumtråden
		\redirect::handle("/forum/topic?id=$topic_id", \redirect::ROOT);
	}
	
	/** Oppdater tiden forumet sist ble endret (crewforum og ff) */
	public function update_change_time()
	{
		// oppdater innstillinger hvis crewforum
		if ($this->id >= 5 && $this->id <= 7)
		{
			// oppdatere brukeren for å unngå markering om noe nytt?
			$t = isset(\game::$settings["forum_{$this->id}_last_change"]) ? \game::$settings["forum_{$this->id}_last_change"]['value'] : false;
			$l = \login::$user->params->get("forum_{$this->id}_last_view");
			
			$time = time();
			if ($t && $l >= $t)
			{
				// sett visningstidspunkt til nå for å unngå oppdatering
				\login::$user->params->update("forum_{$this->id}_last_view", $time, true);
			}
			
			\ess::$b->db->query("
				INSERT INTO settings (name, value) VALUES ('forum_{$this->id}_last_change', $time)
				ON DUPLICATE KEY UPDATE value = VALUES(value)");
			\cache::delete("settings");
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
		
		$result = \ess::$b->db->query("
			SELECT ffm_up_id, ff_id, ff_name
			FROM ff_members
				JOIN ff ON ff_id = ffm_ff_id AND ff_inactive = 0 AND ff_is_crew = 0 AND ff_type = 1
			WHERE ffm_up_id IN (".implode(",", $up_ids).") AND ffm_status = 1");
		
		$ff = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$ff[$row['ffm_up_id']][] = '<a href="'.\ess::$s['rpath'].'/ff/?ff_id='.$row['ff_id'].'" title="Broderskap">'.htmlspecialchars($row['ff_name']).'</a>';
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
		$date = \ess::$b->date->get($data['ft_time']);
		
		// sett opp ranken
		$rank = \game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = $this->get_ff_info($data['ft_up_id']);
		
		$html = '
	<div class="forum_topic" id="t'.$data['ft_id'].'">
		<h2 class="forum_title"><a href="topic?id='.$data['ft_id'].'" class="forum_permlink r4">#1</a> - '.$date->format(\date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.\game::profile_link($data['ft_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.(isset($data['fs_new']) && $data['fs_new'] ? ' <span class="fs_new">(Ny)</span>' : '').'</h2>
		<p class="h_left"><a href="#default_container"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>';
		
		$right = '';
		
		// rapportert?
		if ($data['r_time'])
		{
			$right .= '
			<span class="forum_report">Rapportert '.\ess::$b->date->get($data['r_time'])->format().'</span>';
		}
		elseif (\login::$logged_in && $data['ft_up_id'] != \login::$user->player->id)
		{
			$right .= '
			<a href="'.\ess::$s['relative_path'].'/js" rel="ft,'.$data['ft_id'].'" class="report_link forum_report">Rapporter</a>';
		}
		
		// verktøy
		if (\login::$logged_in && ($this->fmod || $data['ft_up_id'] == \login::$user->player->id))
		{
			$right .= '
			<a href="'.\ess::$s['relative_path'].'/forum/post_edit?type=emne&amp;id='.$data['ft_id'].'" class="forum_link_topic_edit"><img src="'.STATIC_LINK.'/other/edit.gif" alt="Rediger" /></a>';
		}
		
		if ($right != "")
		{
			$html .= '
		<p class="h_right">'.$right.'
		</p>';
		}
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.\ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['ft_up_id'].'"><img src="'.htmlspecialchars(\player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.\game::format_data($data['ft_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (!\login::$logged_in || \login::$user->data['u_forum_show_signature'])
		{
			$signatur = \game::format_data($data['up_forum_signature']);
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
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['ft_last_edit_up_id'].'" /> '.\ess::$b->date->get($data['ft_last_edit'])->format(\date::FORMAT_SEC).'</p>';
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
		if (!\login::$logged_in) throw new \HSNotLoggedIn();
		
		$date = isset($data['ft_time']) ? \ess::$b->date->get($data['ft_time']) : \ess::$b->date->get();
		
		// bruk informasjonen til brukeren?
		if (empty($data['ft_up_id']))
		{
			$data['ft_up_id'] = \login::$user->player->id;
			$data['up_name'] = \login::$user->player->data['up_name'];
			$data['up_access_level'] = \login::$user->player->data['up_access_level'];
			$data['up_points'] = \login::$user->player->data['up_points'];
			$data['upr_rank_pos'] = \login::$user->player->data['upr_rank_pos'];
			$data['up_forum_signature'] = \login::$user->player->data['up_forum_signature'];
			$data['up_profile_image_url'] = \login::$user->player->data['up_profile_image_url'];
		}
		
		// sett opp ranken
		$rank = \game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = '';
		
		$html = '
	<div class="forum_topic">
		<h2 class="forum_title"><a href="'.(isset($data['ft_id']) ? 'topic?id='.$data['ft_id'] : 'forum').'" class="forum_permlink r4">#1</a> - '.$date->format(\date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.\game::profile_link($data['ft_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.'</h2>
		<p class="h_left"><a href="#default_container"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>
		<p class="h_right" style="text-transform: uppercase; margin: -17px 10px 0 !important; color: #DDD">Forhåndsvisning</p>';
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.\ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['ft_up_id'].'"><img src="'.htmlspecialchars(\player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.\game::format_data($data['ft_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (\login::$user->data['u_forum_show_signature'])
		{
			$signatur = \game::format_data($data['up_forum_signature']);
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
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['ft_last_edit_up_id'].'" /> '.\ess::$b->date->get($data['ft_last_edit'])->format(\date::FORMAT_SEC).'</p>';
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
		$date = \ess::$b->date->get($data['fr_time']);
		
		// sett opp ranken
		$rank = \game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = $this->get_ff_info($data['fr_up_id']);
		
		$html = '
	<div class="forum_topic'.($data['fr_deleted'] != 0 ? ' forum_reply_deleted' : '').(isset($data['class_extra']) ? ' '.$data['class_extra'] : '').'" id="m_'.$data['fr_id'].'">
		<h2 class="forum_title"'.(isset($data['h2_extra']) ? ' '.$data['h2_extra'] : '').'><a href="topic?id='.$data['ft_id'].'&amp;replyid='.$data['fr_id'].'" class="forum_permlink r4">#'.$data['reply_num'].'</a> - '.$date->format(\date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.\game::profile_link($data['fr_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.(isset($data['fs_new']) && $data['fs_new'] ? ' <span class="fs_new">(Ny)</span>' : '').'</h2>
		<p class="h_left"><a href="#default_header_wrap"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>';
		
		$right = '';
		
		// rapportert?
		if ($data['r_time'])
		{
			$right .= '
			<span class="forum_report">Rapportert '.\ess::$b->date->get($data['r_time'])->format().'</span>';
		}
		elseif (\login::$logged_in && $data['fr_up_id'] != \login::$user->player->id)
		{
			$right .= '
			<a href="'.\ess::$s['relative_path'].'/js" rel="fr,'.$data['fr_id'].'" class="report_link forum_report">Rapporter</a>';
		}
		
		// verktøy
		if ($this->fmod || (\login::$logged_in && $data['fr_up_id'] == \login::$user->player->id))
		{
			$right .= '
			<a href="'.\ess::$s['relative_path'].'/forum/post_edit?type=svar&amp;id='.$data['fr_id'].'" class="forum_link_reply_edit" rel="'.$data['fr_id'].'"><img src="'.STATIC_LINK.'/other/edit.gif" alt="Rediger" /></a>';
			
			// ikke slettet
			if ($data['fr_deleted'] == 0)
			{
				$right .= '
			<a href="'.\ess::$s['relative_path'].'/forum/topic?id='.$data['ft_id'].'&amp;delete_reply='.$data['fr_id'].'&amp;sid='.\login::$info['ses_id'].'" class="forum_link_reply_delete" rel="'.$data['fr_id'].'"><img src="'.STATIC_LINK.'/other/delete.gif" alt="Slett" /></a>';
				
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
			<a href="'.\ess::$s['relative_path'].'/forum/topic?id='.$data['ft_id'].'&amp;restore_reply='.$data['fr_id'].'&amp;sid='.\login::$info['ses_id'].'" class="forum_link_reply_restore" rel="'.$data['fr_id'].'"><img src="'.STATIC_LINK.'/icon/arrow_refresh.png" alt="Gjenopprett" /></a>';
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
			<div class="forum_profile_image"><a href="'.\ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['fr_up_id'].'"><img src="'.htmlspecialchars(\player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.\game::format_data($data['fr_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (!\login::$logged_in || \login::$user->data['u_forum_show_signature'])
		{
			$signatur = \game::format_data($data['up_forum_signature']);
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
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['fr_last_edit_up_id'].'" /> '.\ess::$b->date->get($data['fr_last_edit'])->format(\date::FORMAT_SEC).'</p>';
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
		if (!\login::$logged_in) throw new \HSNotLoggedIn();
		
		$date = \ess::$b->date->get();
		
		// sett opp ranken
		$rank = \game::rank_info($data['up_points'], $data['upr_rank_pos'], $data['up_access_level']);
		$rank = $rank['name'];
		
		$player_ff_position = '';
		
		$html = '
	<div class="forum_topic">
		<h2 class="forum_title"><a href="topic?id='.$data['ft_id'].'" class="forum_permlink r4">#XX</a> - '.$date->format(\date::FORMAT_NOTIME).' <b>'.$date->format("H:i:s").'</b> - Av '.\game::profile_link($data['fr_up_id'], $data['up_name'], $data['up_access_level']).$player_ff_position.(isset($data['fs_new']) && $data['fs_new'] ? ' <span class="fs_new">(Ny)</span>' : '').'</h2>
		<p class="h_left"><a href="#default_header_wrap"><img src="'.STATIC_LINK.'/other/up.gif" title="Til toppen" /></a></p>
		<p class="h_right" style="text-transform: uppercase; margin: -17px 10px 0 !important; color: #DDD">Forhåndsvisning</p>';
		
		// profilbildet og rank
		$img = '
			<div class="forum_profile_image"><a href="'.\ess::$s['relative_path'].'/p/'.$data['up_name'].'/'.$data['fr_up_id'].'"><img src="'.htmlspecialchars(\player::get_profile_image_static($data['up_profile_image_url'])).'" class="profile_image" alt="" /><span class="forum_rank">'.$rank.'</span></a></div>';
		
		// innlegget
		$html .= '
		<div class="forum_text">'.$img.'
			'.\game::format_data($data['fr_text']).'
		</div>';
		
		// signaturen
		$signatur = false;
		if (\login::$user->data['u_forum_show_signature'])
		{
			$signatur = \game::format_data($data['up_forum_signature']);
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
		<p class="forum_last_edit">Sist redigert av <user id="'.$data['fr_last_edit_up_id'].'" /> '.\ess::$b->date->get($data['fr_last_edit'])->format(\date::FORMAT_SEC).'</p>';
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
		$plain = htmlspecialchars_decode(strip_tags(\game::format_data($data)));
		$plain = preg_replace("/[^a-zA-ZæøåÆØÅ0-9]/u", '', $plain);
		
		// sjekk lengden
		return mb_strlen($plain);
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
		
		\ess::$b->page->load();
	}
}