<?php namespace Kofradia\Forum;

/**
 * Forumtråd
 */
class Topic
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
	 * @param \Kofradia\Forum\Category $forum
	 */
	public function __construct($topic_id, Category $forum = NULL)
	{
		$this->id = (int) $topic_id;
		
		// hent informasjon om forumtråden
		$seen_q = \login::$logged_in ? "fs_ft_id = ft_id AND fs_u_id = ".\login::$user->id : "FALSE";
		$result = \Kofradia\DB::get()->query("
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
				LEFT JOIN rapportering ON r_type = ".\rapportering::TYPE_FORUM_TOPIC." AND r_type_id = ft_id AND r_state < 2
			WHERE ft_id = $this->id
			GROUP BY ft_id");
		
		$this->info = $result->fetch();
		
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
			if ($this->info && ($this->forum->ff ? (\access::has("mod") || ($this->forum->fmod && $this->info['ft_deleted'] > $access_expire)) : $this->forum->fmod))
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
		if (\login::$logged_in && \login::$user->data['u_forum_per_page'] > 1) $this->replies_per_page = max(1, min(100, \login::$user->data['u_forum_per_page'])); 
	}
	
	/**
	 * Hent full informasjon om forumtråden
	 * For å kunne bruke HTML-malen
	 * @return array
	 */
	public function extended_info()
	{
		$data = $this->info;
		$data['fs_new'] = empty($data['fs_time']) && \Kofradia\Forum\Category::$fs_check;
		return $data;
	}
	
	/** Slettet, men tilgang */
	protected function deleted_with_access()
	{
		\ess::$b->page->add_message("Denne forumtråden er slettet. Du har alikevel tilgang til å vise den.");
	}
	
	/** Slettet og uten tilgang, eller finnes ikke */
	protected function error_404()
	{
		\ess::$b->page->add_message("Fant ikke forumtråden.", "error");
		\redirect::handle("/forum/", \redirect::ROOT);
	}
	
	/** Hent informasjon om forumkategorien og kontroller tilgang */
	protected function load_forum()
	{
		$this->forum = new \Kofradia\Forum\Category($this->info['ft_fse_id']);
		$this->forum->require_access();
	}
	
	/** Redirect til forumtråden */
	public function redirect()
	{
		\redirect::handle("/forum/topic?id=$this->id", \redirect::ROOT);
	}
	
	/**
	 * Slett forumtråden
	 */
	public function delete()
	{
		if (!\login::$logged_in) throw new HSNotLoggedIn();
		
		// kontroller tilgang til forumtråden
		if ($this->info['ft_up_id'] != \login::$user->player->id && !$this->forum->fmod)
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
		\Kofradia\Forum\Log::add_topic_deleted($this);
		
		// fullført
		$this->delete_complete();
	}
	
	/** Utfør selve slettingen av forumtråden */
	protected function delete_action()
	{
		// forsøk å slett forumtråden
		$a = \Kofradia\DB::get()->exec("UPDATE forum_topics SET ft_deleted = ".time()." WHERE ft_id = $this->id AND ft_deleted = 0");
		return $a > 0;
	}
	
	/** Forumtråden er allerede slettet */
	protected function delete_dupe()
	{
		\ess::$b->page->add_message("Forumtråden er allerede slettet.", "error");
		$this->forum->redirect();
	}
	
	/** Forumtråden ble slettet */
	protected function delete_complete()
	{
		\ess::$b->page->add_message("Forumtråden ble slettet.");
		$this->forum->redirect();
	}
	
	/** Ikke tilgang til å slette forumtråden */
	protected function delete_error_403()
	{
		\ess::$b->page->add_message("Du har ikke tilgang til å slette denne forumtråden.", "error");
		$this->redirect();
	}
	
	/**
	 * Må vente før forumtråden kan slettes
	 * @param integer $wait ventetid
	 */
	protected function delete_error_wait($wait)
	{
		\ess::$b->page->add_message("Du må vente 5 minutter før du kan slette emnet etter å ha opprettet det. Du må vente i ".\game::counter($wait)." før du kan slette det.", "error");
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
		\Kofradia\Forum\Log::add_topic_restored($this);
		
		// fullført
		$this->restore_complete();
	}
	
	/** Forumtråden er allerede gjenopprettet */
	protected function restore_dupe()
	{
		\ess::$b->page->add_message("Forumtråden er allerede gjenopprettet.", "error");
		$this->redirect();
	}
	
	/** Utfør selve gjenopprettelsen av forumtråden */
	protected function restore_action()
	{
		// forsøk å gjenopprett forumtråden
		$a = \Kofradia\DB::get()->exec("UPDATE forum_topics SET ft_deleted = 0 WHERE ft_id = $this->id AND ft_deleted != 0");
		return $a > 0;
	}
	
	/** Forumtråden ble gjenopprettet */
	protected function restore_complete()
	{
		\ess::$b->page->add_message("Forumtråden ble gjenopprettet.");
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
		if (!\login::$logged_in) throw new HSNotLoggedIn();
		
		// kontroller tilgang til forumtråden
		if ($this->info['ft_up_id'] != \login::$user->player->id && !$this->forum->fmod)
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
		if ($title != $this->info['ft_title'] && (mb_strlen($title) < \Kofradia\Forum\Category::TOPIC_TITLE_MIN_LENGTH || mb_strlen($title) > \Kofradia\Forum\Category::TOPIC_TITLE_MAX_LENGTH))
		{
			$this->edit_error_length_title();
			return;
		}
		
		// kontroller tekstlengde (innhold)
		$text = trim($text);
		if ($text != $this->info['ft_text'] && \Kofradia\Forum\Category::check_length($text) < \Kofradia\Forum\Category::TOPIC_MIN_LENGTH)
		{
			$this->edit_error_length();
			return;
		}
		
		$update = '';
		$only_moved = false;
		
		// bytte seksjon?
		if ($section !== NULL && $section != $this->info['ft_fse_id'] && (!$this->forum->ff || \access::has("mod")))
		{
			// kontroller at den finnes og at vi har tilgang
			$forum = new \Kofradia\Forum\CategoryControl($section);
			
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
			$a = \Kofradia\DB::get()->exec("UPDATE forum_topics SET ft_title = ".\Kofradia\DB::quote($title)."$update WHERE ft_id = $this->id");
			
			// ble ikke oppdatert?
			if ($a == 0)
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
			\Kofradia\Forum\Log::add_topic_moved($this, $old_data);
			
			// fullført
			$this->edit_complete();
		}
		
		// rediger forumtråden
		$a = \Kofradia\DB::get()->exec("UPDATE forum_topics SET ft_title = ".\Kofradia\DB::quote($title).", ft_text = ".\Kofradia\DB::quote($text).", ft_last_edit = ".time().", ft_last_edit_up_id = ".\login::$user->player->id."$update WHERE ft_id = $this->id");
		
		// ble ikke oppdatert?
		if ($a == 0)
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
			\Kofradia\Forum\Log::add_topic_moved($this, $old_data);
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
		$this->info['ft_last_edit_up_id'] = \login::$user->player->id;
		
		// logg
		\Kofradia\Forum\Log::add_topic_edited($this, $old_data);
		
		// fullført
		$this->edit_complete();
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		\ess::$b->page->add_message("Forumtråden ble ikke redigert.", "error");
	}
	
	/** Har ikke tilgang til å redigere forumtråden */
	protected function edit_error_403()
	{
		\ess::$b->page->add_message("Du har ikke tilgang til å redigere denne forumtråden.", "error");
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		\ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke redigere den.", "error");
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function edit_error_length_title()
	{
		\ess::$b->page->add_message("Tittelen kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MAX_LENGTH." tegn.", "error");
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function edit_error_length()
	{
		\ess::$b->page->add_message("Forumtråden kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/** Ugyldig forumkategori */
	protected function edit_error_section()
	{
		\ess::$b->page->add_message("Fant ikke forumkategorien.", "error");
	}
	
	/** Ugyldig type */
	protected function edit_error_type()
	{
		\ess::$b->page->add_message("Ugyldig type.", "error");
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		\ess::$b->page->add_message("Ingen endringer ble utført.", "error");
	}
	
	/** Forumtråden ble redigert */
	protected function edit_complete()
	{
		\ess::$b->page->add_message("Forumtråden ble redigert.");
		
		// send til forumtråden
		\redirect::handle("/forum/topic?id={$this->id}", \redirect::ROOT);
	}
	
	/**
	 * Hent ut et bestemt forumsvar i forumtråden
	 * @param integer $reply_id
	 * @return forum_reply
	 */
	public function get_reply($reply_id)
	{
		// forsøk å hent forumsvaret
		$reply = new \Kofradia\Forum\Reply($reply_id, $this);
		
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
		if (!\login::$logged_in) throw new HSNotLoggedIn();
		
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
		if (\Kofradia\Forum\Category::check_length($text) < \Kofradia\Forum\Category::REPLY_MIN_LENGTH)
		{
			$this->add_reply_error_length();
			return;
		}
		
		// sjekk om vi skal sammenslå dette med det siste forumsvaret
		if (!$no_concatenate)
		{
			// hent siste forumsvaret
			$result = \Kofradia\DB::get()->query("SELECT fr_id, fr_up_id, fr_time FROM forum_replies WHERE fr_ft_id = $this->id AND fr_deleted = 0 ORDER BY fr_time DESC LIMIT 1");
			$row = $result->fetch();
			
			// fant forumsvar, og tilhører brukeren
			// forumsvaret er nyere enn 6 timer
			if ($row && $row['fr_up_id'] == \login::$user->player->id && (time()-$row['fr_time']) < 21600)
			{
				// slå sammen med dette forumsvaret
				$text = "\n\n[hr]\n\n$text";
				\Kofradia\DB::get()->exec("UPDATE forum_replies SET fr_text = CONCAT(fr_text, ".\Kofradia\DB::quote($text)."), fr_last_edit = ".time().", fr_last_edit_up_id = ".\login::$user->player->id." WHERE fr_id = {$row['fr_id']}");
				
				// annonsere forumsvaret?
				if ($announce && $reply = $this->get_reply($row['fr_id']))
				{
					$reply->announce();
				}
				
				// logg
				\Kofradia\Forum\Log::add_reply_concatenated($this, $row['fr_id']);
				
				$this->add_reply_merged($row['fr_id']);
				return;
			}
		}
		
		// legg til som nytt forumsvar
		\Kofradia\DB::get()->exec("INSERT INTO forum_replies SET fr_time = ".time().", fr_up_id = ".\login::$user->player->id.", fr_text = ".\Kofradia\DB::quote($text).", fr_ft_id = $this->id");
		$reply_id = \Kofradia\DB::get()->lastInsertId();
		
		// oppdater forumtråden med antall forumsvar og siste forumsvar
		\Kofradia\DB::get()->exec("UPDATE forum_topics SET ft_replies = ft_replies + 1, ft_last_reply = $reply_id WHERE ft_id = $this->id");
		
		// oppdater spilleren
		if ($this->forum->ff)
		{
			\Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_forum_replies = ffm_forum_replies + 1 WHERE ffm_up_id = ".\login::$user->player->id." AND ffm_ff_id = {$this->forum->ff->id}");
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_forum_ff_num_replies = up_forum_ff_num_replies + 1 WHERE up_id = ".\login::$user->player->id);
		}
		else
		{
			\Kofradia\DB::get()->exec("UPDATE users, users_players SET up_forum_num_replies = up_forum_num_replies + 1, u_forum_reply_time = ".time()." WHERE up_id = ".\login::$user->player->id." AND up_u_id = u_id");
		}
		
		// annonsere forumsvaret?
		if ($announce && $reply = $this->get_reply($reply_id))
		{
			$reply->announce();
		}
		
		// oppdater tid om nødvendig
		$this->forum->update_change_time();
		
		// logg
		\Kofradia\Forum\Log::add_reply_added($this, $reply_id);
		
		// fullført
		$this->add_reply_complete($reply_id);
	}
	
	/** Forumtråden er låst */
	protected function add_reply_error_locked()
	{
		\ess::$b->page->add_message("Denne forumtråden er låst. Du kan ikke opprette forumsvar i den.", "error");
	}
	
	/** Forumtråden er slettet */
	protected function add_reply_error_deleted()
	{
		\ess::$b->page->add_message("Denne forumtråden er slettet. Du kan ikke opprette forumsvar i den.", "error");
	}
	
	/**
	 * Må vente før nytt forumsvar kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_reply_error_wait($wait)
	{
		\ess::$b->page->add_message("Du må vente ".\game::counter($wait)." før du kan opprette forumsvaret.", "error");
	}
	
	/** For kort lengde i forumsvaret */
	protected function add_reply_error_length()
	{
		\ess::$b->page->add_message("Forumsvaret kan ikke inneholde færre enn ".\Kofradia\Forum\Category::REPLY_MIN_LENGTH." bokstaver/tall.", "error");
	}
	
	/**
	 * Forumsvaret ble lagt til (merged)
	 * @param integer $reply_id
	 */
	protected function add_reply_merged($reply_id)
	{
		\ess::$b->page->add_message("Siden det siste forumsvaret tilhørte deg, har teksten blitt redigert inn i det forumsvaret.");
		\redirect::handle("/forum/topic?id={$this->id}&replyid=$reply_id", \redirect::ROOT);
	}
	
	/**
	 * Forumsvaret ble lagt til (som nytt forumsvar)
	 */
	protected function add_reply_complete($reply_id)
	{
		\ess::$b->page->add_message("Forumsvaret ble lagt til.");
		\redirect::handle("/forum/topic?id={$this->id}&replyid=$reply_id", \redirect::ROOT);
	}
}