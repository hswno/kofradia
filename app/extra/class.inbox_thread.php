<?php

/**
 * Innboks tråd
 */
class inbox_thread
{
	/** MeldingsID-en */
	public $id;
	
	/** Informasjon om meldingstråden */
	public $data_thread;
	
	/** Informasjon om relasjon ifm. bruker/spiller */
	public $data_rel;
	
	/** Alle mottakerene */
	public $receivers = array();
	
	/** Mottakerene som kan motta meldinger */
	public $receivers_accept = array();
	
	/** Har vi tilgang til å svare i meldingen? */
	public $can_reply_access = true;
	
	/** Er det noen mottakere som kan motta meldingen? */
	public $can_reply_receivers = false;
	
	/** Kan vi sende til denne meldinstråden fordi mottaker er crew og vi er deaktivert? */
	public $can_reply_receivers_crew = false;
	
	/** Har vi ikke full tilgang til meldingstråden, så vi ikke kan se alle meldingene? */
	public $restrict = true;
	
	/** Ventetid mellom hver melding man sender */
	const TIME_WAIT_REPLY = 20; // 20 sekunder
	
	/** Hent informasjon */
	public function __construct($it_id)
	{
		// hent info
		$this->id = (int) $it_id;
		$result = \Kofradia\DB::get()->query("SELECT it_id, it_title FROM inbox_threads WHERE it_id = $this->id");
		$this->data_thread = $result->fetch();
		if (!$this->data_thread) return;
	}
	
	/**
	 * Forsøk å hent meldingstråd
	 * @param integer $it_id
	 * @return inbox_thread
	 */
	public static function get($it_id)
	{
		$t = new self($it_id);
		if (!$t->data_thread)
		{
			$t->handle_ret(self::RET_ERROR_404);
			return false;
		}
		return $t;
	}
	
	/** Sjekk om meldingstråden er eller har vært rappotert */
	public function reported()
	{
		// moderator+ har tilgang
		if (!access::has("mod")) return false;
		
		// sjekk om noen meldinger er rapportert
		$result = \Kofradia\DB::get()->query("SELECT r_id FROM rapportering, inbox_messages WHERE im_it_id = $this->id AND r_type = ".rapportering::TYPE_PM." AND r_type_id = im_id LIMIT 1");
		
		return $result->rowCount() > 0;
	}
	
	const RET_INFO_DELETED_OWN = 1;
	const RET_INFO_DELETED = 2;
	const RET_INFO_REPORTED = 3;
	const RET_ERROR_404 = 4;
	const RET_ERROR_CANNOT_REPLY = 5;
	const RET_ERROR_NO_RECEIVERS = 6;
	const RET_ERROR_BAN_CREW = 7;
	const RET_ERROR_BAN = 8;
	const RET_ERROR_BLOCKED = 9;
	const RET_INFO_BLOCKED = 10;
	const RET_ERROR_WAIT = 11;
	const RET_ERROR_CONTENT_SHORT = 12;
	
	// markering av tråd
	const RET_ERROR_MARK_NO_REL = 13;
	const RET_INFO_MARK_ALREADY = 14;
	const RET_INFO_MARK_TRUE = 15;
	const RET_INFO_MARK_FALSE = 16;
	
	/** Behandle respons */
	public function handle_ret($id, $data = NULL)
	{
		switch ($id)
		{
			case self::RET_INFO_DELETED_OWN:
				echo '
<p class="info_box">Du viser en av dine egne meldinger som har blitt slettet.</p>';
			break;
			
			case self::RET_INFO_DELETED:
				echo '
<p class="info_box">Denne meldingen tilhører ikke deg.</p>';
			break;
			
			case self::RET_INFO_REPORTED:
				echo '
<p class="info_box">Du har tilgang til denne meldingstråden fordi den er eller har vært rapportert.</p>';
			break;
			
			case self::RET_ERROR_404:
				ess::$b->page->add_message("Fant ikke meldingstråden.", "error");
				redirect::handle("innboks");
			break;
			
			case self::RET_ERROR_CANNOT_REPLY:
				ess::$b->page->add_message("Du kan ikke svare på denne meldingstråden.", "error");
				redirect::handle();
			break;
			
			case self::RET_ERROR_NO_RECEIVERS:
				ess::$b->page->add_message("Det er ingen mottakere du kan sende svar til.", "error");
				redirect::handle();
			break;
			
			case self::RET_ERROR_BAN_CREW:
				ess::$b->page->add_message("Du er blokkert fra å sende meldinger til andre enn Crewet. Du kan kun ha én mottaker. Blokkeringen varer til ".ess::$b->date->get($data['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($data['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
			break;
			
			case self::RET_ERROR_BAN:
				ess::$b->page->add_message("Du er blokkert fra å sende meldinger til andre enn Crewet. Blokkeringen varer til ".ess::$b->date->get($data['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($data['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
			break;
			
			case self::RET_ERROR_BLOCKED:
				foreach ($data as &$row)
				{
					$row = game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).(($reason = game::bb_to_html($row['uc_info'])) == "" ? "" : ' - begrunnelse: '.$reason);
				}
				
				ess::$b->page->add_message("Du kan ikke svare på denne meldingstråden fordi følgende brukere har blokkert deg:<ul><li>".implode("</li><li>", $data)."</li></ul>", "error");
			break;
			
			case self::RET_INFO_BLOCKED:
				foreach ($data as &$row)
				{
					$row = game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).(($reason = game::bb_to_html($row['uc_info'])) == "" ? "" : ' - begrunnelse: '.$reason);
				}
				
				ess::$b->page->add_message("Følgende brukere har egentlig blokkert deg:<ul><li>".implode("</li><li>", $data)."</li></ul>");
			break;
			
			case self::RET_ERROR_WAIT:
				global $__server;
				ess::$b->page->add_message('Du må vente '.game::counter($data).' før du kan sende en melding!', "error");
				putlog("LOG", "%c13%bMELDING FORSØK%b%c: %u".login::$user->player->data['up_name']."%u forsøkte å svare på en melding til it_id $this->id (%u{$this->data_thread['it_title']}%u). Må vente $data sekunder. {$__server['path']}/innboks_les?id=$this->id");
			break;
			
			case self::RET_ERROR_CONTENT_SHORT:
				ess::$b->page->add_message("Meldingen kan ikke inneholde færre enn 3 bokstaver/tall.", "error");
			break;
			
			case self::RET_ERROR_MARK_NO_REL:
				ess::$b->page->add_message("Du har ikke mulighet til å markere denne meldingstråden da du ikke er en deltaker i den.", "error");
			break;
			
			case self::RET_INFO_MARK_ALREADY:
				if ($this->data_rel['ir_marked'])
					ess::$b->page->add_message("Meldingstråden er allerede markert som merket.");
				else
					ess::$b->page->add_message("Meldingstråden er ikke merket fra før.");
			break;
			
			case self::RET_INFO_MARK_TRUE:
				ess::$b->page->add_message("Meldingstråden er nå markert for oppfølging.");
			break;
			
			case self::RET_INFO_MARK_FALSE:
				ess::$b->page->add_message("Meldingstråden er ikke lenger markert for oppfølging.");
			break;
			
			default:
				throw new HSException("Ukjent behandler.");
		}
	}
	
	/**
	 * Hent relasjonsinformasjon og kontroller tilgang
	 * @return boolean true hvis vi har tilgang
	 */
	public function check_rel()
	{
		// sjekk relasjonsinfo (eier)
		$result = \Kofradia\DB::get()->query("
			SELECT ir_up_id, ir_unread, ir_deleted, ir_restrict_im_time, ir_marked, up_name
			FROM inbox_rel JOIN users_players ON ir_up_id = up_id AND up_u_id = ".login::$user->id."
			WHERE ir_it_id = $this->id
			ORDER BY up_last_online DESC
			LIMIT 1");
		$this->data_rel = $result->fetch();
		
		// finnes ikke eller slettet?
		$deleted = $this->data_rel && $this->data_rel['ir_deleted'] != 0;
		if (!$this->data_rel || $deleted)
		{
			// har vi tilgang til alle meldingstrådene?
			if (access::has("admin") && KOFRADIA_DEBUG)
			{
				$this->restrict = false;
				
				// slettet?
				$this->handle_ret($deleted ? self::RET_INFO_DELETED_OWN : self::RET_INFO_DELETED);
			}
			
			else
			{
				// rapportert? evt. ikke gi tilgang
				if ($this->reported())
				{
					$this->restrict = false;
					$this->handle_ret(self::RET_INFO_REPORTED);
				}
				else
				{
					$this->handle_ret(self::RET_ERROR_404);
					return false;
				}
			}
			
			return true;
		}
		
		elseif (access::has("admin") && KOFRADIA_DEBUG) $this->restrict = false;
		
		// begrens mulighet til å svare hvis det er aktuelt
		$this->can_reply_access = !$this->data_rel || (login::$logged_in && login::$user->player->id == $this->data_rel['ir_up_id']) || (access::has("admin") && KOFRADIA_DEBUG);
	}
	
	/** Hent mottakere */
	public function get_receivers()
	{
		$restrict = $this->restrict ? " AND ir_restrict_im_time <= {$this->data_rel['ir_restrict_im_time']} AND im_deleted = 0" : "";
		
		// hent deltakere
		$me = $this->data_rel ? $this->data_rel['ir_up_id'] : false;
		$result = \Kofradia\DB::get()->query("
			SELECT ir_up_id, ir_unread, ir_views, ir_deleted, ir_restrict_im_time, ir_marked, COUNT(im_id) AS num_messages, u_active_up_id, u_access_level, up_access_level
			FROM inbox_rel
				LEFT JOIN inbox_messages ON im_it_id = ir_it_id AND im_up_id = ir_up_id$restrict
				LEFT JOIN users_players ON up_id = ir_up_id
				LEFT JOIN users ON u_id = up_u_id
			WHERE ir_it_id = $this->id
			GROUP BY ir_up_id
			ORDER BY up_name");
		$this->receivers = array();
		$this->receivers_accept = array();
		$this->can_reply_receivers = false;
		$this->can_reply_receivers_crew = false;
		$c = access::has("crewet");
		$n = $result->rowCount();
		while ($row = $result->fetch())
		{
			// er dette spilleren?
			if ($row['ir_up_id'] == $me)
			{
				$row['ir_views']++;
				$row['ir_unread'] = 0;
			}
			elseif ($row['ir_deleted'] == 0 && $row['up_access_level'] != 0)
			{
				$this->receivers_accept[] = $row['ir_up_id'];
				$this->can_reply_receivers = true;
			}
			elseif ($row['ir_deleted'] == 0 && $c && $n == 2 && $row['u_access_level'] != 0 && $row['u_active_up_id'] == $row['ir_up_id'])
			{
				$this->receivers_accept[] = $row['ir_up_id'];
				$this->can_reply_receivers = true;
				$this->can_reply_receivers_crew = true;
			}
			
			$this->receivers[$row['ir_up_id']] = $row;
		}
		
		// er spilleren deaktivert --> sjekk om det kun er én mottaker, og mottakeren er crew
		if (!login::$user->player->active && !$c && $this->can_reply_access)
		{
			if ($n > 2) $this->can_reply_access = false;
			else
			{
				foreach ($this->receivers_accept as $id)
				{
					if (!in_array("crewet", access::types($this->receivers[$id]['up_access_level'])))
					{
						$this->can_reply_access = false;
						break;
					}
				}
			}
		}
	}
	
	/**
	 * Oppdater statistikk for visninger
	 */
	public function stats_view_update()
	{
		if (!$this->data_rel) return;
		
		\Kofradia\DB::get()->exec("
			UPDATE inbox_rel SET ir_views = ir_views + 1
			WHERE ir_it_id = $this->id AND ir_up_id = {$this->data_rel['ir_up_id']}");
	}
	
	/**
	 * Sett antall nye meldinger til null
	 */
	public function counter_new_reset()
	{
		if (!$this->data_rel) return;
		
		// ingen nye i denne meldingstråden?
		if ($this->data_rel['ir_unread'] == 0) return;
		
		// oppdater uleste meldinger i denne tråden
		\Kofradia\DB::get()->exec("
			UPDATE inbox_rel SET ir_unread = GREATEST(0, ir_unread - {$this->data_rel['ir_unread']})
			WHERE ir_it_id = $this->id AND ir_up_id = {$this->data_rel['ir_up_id']}");
		
		// oppdater uleste meldinger hos brukeren
		\Kofradia\DB::get()->exec("
			UPDATE users, (
				SELECT up_u_id, SUM(ABS(ir_unread)) c
				FROM users_players LEFT JOIN inbox_rel ON ir_up_id = up_id AND ir_deleted = 0
				WHERE up_u_id = ".login::$user->id."
			) r
			SET u_inbox_new = c
			WHERE u_id = up_u_id");
		
		// sett ned telleren i spillerobjektet
		login::$user->data['u_inbox_new'] -= $this->data_rel['ir_unread'];
	}
	
	/**
	 * Finn ut antall meldinger i meldingstråden
	 * @return integer
	 */
	public function num_messages()
	{
		// finn ut hvor mange meldinger vi kan se
		$restrict_where = $this->restrict ? " AND im_time <= {$this->data_rel['ir_restrict_im_time']} AND im_deleted = 0" : "";
		$result = \Kofradia\DB::get()->query("SELECT COUNT(im_id) FROM inbox_messages WHERE im_it_id = $this->id$restrict_where");
		return $result->fetchColumn(0);
	}
	
	/**
	 * Finn posisjonen til en bestemt melding
	 * @param integer $id
	 * @return boolean false on error, integer number on success
	 */
	public function message_locate($im_id)
	{
		$im_id = (int) $im_id;
		
		// forsøk å finn meldingen
		$restrict_where = $this->restrict ? " AND im_time <= {$this->data_rel['ir_restrict_im_time']} AND im_deleted = 0" : "";
		$result = \Kofradia\DB::get()->query("SELECT im_it_id FROM inbox_messages WHERE im_id = $im_id AND im_it_id = $this->id$restrict_where");
		if ($result->rowCount() == 0)
		{
			return false;
		}
		
		// finn ut antall meldinger som har kommet etter denne
		$result = \Kofradia\DB::get()->query("SELECT COUNT(im_id) FROM inbox_messages WHERE im_it_id = $this->id AND im_id > $im_id$restrict_where");
		$ant = $result->fetchColumn(0) + 1;
		
		return $ant;
	}
	
	/**
	 * Slett svar
	 */
	public function reply_delete_try()
	{
		if ($this->restrict) return;
		validate_sid(false);
		
		$im_id = (int) getval("im_del");
		
		// hent status
		$result = \Kofradia\DB::get()->query("SELECT im_deleted, im_up_id FROM inbox_messages WHERE im_it_id = $this->id AND im_id = $im_id");
		$row = $result->fetch();
		if (!$row)
		{
			ess::$b->page->add_message("Fant ikke svaret som skulle bli slettet.", "error");
			return;
		}
		
		// allerede slettet?
		if ($row['im_deleted'] != 0)
		{
			ess::$b->page->add_message("Svaret er allerede slettet.", "error");
		}
		
		else
		{
			// forsøk å slett svaret
			$a = \Kofradia\DB::get()->exec("UPDATE inbox_messages SET im_deleted = 1 WHERE im_id = $im_id AND im_deleted = 0");
			if ($a > 0)
			{
				ess::$b->page->add_message("Svaret ble slettet.", "error");
				crewlog::log(
					"player_message_delete",
					$row['im_up_id'],
					NULL,
					array("it_id" => $this->id, "im_id" => $im_id, "it_title" => $this->data_thread['it_title']));
			}
			
			else
			{
				// vi vet svaret finnes, så da må det har blitt slettet samtidig
				ess::$b->page->add_message("Svaret er allerede slettet.", "error");
			}
		}
		
		redirect::handle("innboks_les?id=$this->id&goto=$im_id");
	}
	
	/**
	 * Slett svar
	 */
	public function reply_restore_try()
	{
		if ($this->restrict) return;
		validate_sid(false);
		
		$im_id = (int) getval("im_restore");
		
		// hent status
		$result = \Kofradia\DB::get()->query("SELECT im_deleted, im_up_id FROM inbox_messages WHERE im_it_id = $this->id AND im_id = $im_id");
		$row = $result->fetch();
		if (!$row)
		{
			ess::$b->page->add_message("Fant ikke svaret som skulle bli gjenopprettet.", "error");
			return;
		}
		
		// er ikke slettet?
		if ($row['im_deleted'] == 0)
		{
			ess::$b->page->add_message("Svaret er ikke slettet.", "error");
		}
		
		else
		{
			// forsøk å slett svaret
			$a = \Kofradia\DB::get()->exec("UPDATE inbox_messages SET im_deleted = 0 WHERE im_id = $im_id AND im_deleted != 0");
			if ($a > 0)
			{
				ess::$b->page->add_message("Svaret ble gjenopprettet.", "error");
				crewlog::log(
					"player_message_restore",
					$row['im_up_id'],
					NULL,
					array("it_id" => $this->id, "im_id" => $im_id, "it_title" => $this->data_thread['it_title']));
			}
			
			else
			{
				// vi vet svaret finnes, så da må det har blitt gjenopprettet samtidig
				ess::$b->page->add_message("Svaret er ikke slettet.", "error");
			}
		}
		
		redirect::handle("innboks_les?id=$this->id&goto=$im_id");
	}
	
	/**
	 * Slett hele meldingstråden
	 */
	public function delete()
	{
		// marker som slettet
		$a = \Kofradia\DB::get()->exec("
			UPDATE inbox_rel
				JOIN users_players ON ir_up_id = up_id
				JOIN users ON up_u_id = u_id
			SET ir_deleted = 1, u_inbox_new = GREATEST(0, u_inbox_new - ir_unread)
			WHERE ir_it_id = $this->id AND ir_deleted = 0 AND ir_up_id != ".login::$user->player->id);
		
		if ($a > 0)
		{
			crewlog::log(
				"player_thread_delete",
				NULL,
				NULL,
				array("it_id" => $this->id, "it_title" => $this->data_thread['it_title']));
			
			putlog("LOG", "MELDINGSTRÅD SLETTET: ".login::$user->player->data['up_name']." slettet hele meldingstråden '{$this->data_thread['it_title']}' ".ess::$s['path']."/innboks_les?id=$this->id");
			
			// melding
			ess::$b->page->add_message("Meldingstråden ble slettet.");
		}
		
		else
		{
			// melding
			ess::$b->page->add_message("Meldingstråden er allerede markert slettet.");
		}
		
		redirect::handle("innboks_les?id=$this->id");
	}
	
	/**
	 * Kontroller mulighet til å svare på meldingentråden
	 * @return boolean true hvis vi kan fortsette sending
	 */
	public function reply_test()
	{
		// er ikke logget inn?
		if (!login::$logged_in) throw new HSException("Ikke logget inn.");
		
		// kan vi ikke svare på denne meldingen?
		if (!$this->can_reply_access)
		{
			$this->handle_ret(self::RET_ERROR_CANNOT_REPLY);
			return false;
		}
		
		// er det ingen mottakere vi kan sende til?
		if (!$this->can_reply_receivers)
		{
			$this->handle_ret(self::RET_ERROR_NO_RECEIVERS);
			return false;
		}
		
		// hent kontaktstatus for mottakerene
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, uc_id, uc_info
			FROM users_players, users LEFT JOIN users_contacts ON u_id = uc_u_id AND uc_contact_up_id = ".login::$user->player->id." AND uc_type = 2
			WHERE up_u_id = u_id AND up_id IN (".implode(",", $this->receivers_accept).")");
		$blocked = array();
		while ($row = $result->fetch())
		{
			// blokkert?
			if ($row['uc_id'])
			{
				$blocked[] = $row;
			}
		}
		
		// blokkert fra å sende meldinger? (kan kun sende til Crewet og med 1 mottaker)
		$blokkering = blokkeringer::check(blokkeringer::TYPE_MELDINGER);
		$blokkering_ok = true;
		if ($blokkering && count($this->receivers_accept) == 1)
		{
			// kontroller at den ene mottakeren vi har er i Crewet (tilgang til "crewet")
			if (!in_array("crewet", access::types($this->receivers[reset($this->receivers_accept)]['up_access_level'])))
			{
				$blokkering_ok = false;
			}
		}
		
		// blokkert og for mange mottakere?
		if ($blokkering && count($this->receivers_accept) > 1)
		{
			$this->handle_ret(self::RET_ERROR_BAN_CREW);
			return false;
		}
		
		// blokkert og mottaker er ikke i Crewet?
		if (!$blokkering_ok)
		{
			$this->handle_ret(self::RET_ERROR_BAN);
			return false;
		}
		
		// sjekk om noen av brukerene har blokkert personen
		if (count($blocked) > 0 && !access::has("crewet"))
		{
			$this->handle_ret(self::RET_ERROR_BLOCKED, $blocked);
			return false;
		}
		
		// har noen egentlig blokkert oss?
		if (count($blocked) > 0)
		{
			$this->handle_ret(self::RET_INFO_BLOCKED, $blocked);
		}
		
		// kan fortsette med sending
		return true;
	}
	
	/**
	 * Test for ventetid
	 * @return boolean true hvis vi kan fortsette sending
	 */
	public function reply_test_wait()
	{
		// crewet har ikke ventetid uansett
		if (access::has("crewet"))
		{
			return true;
		}
		
		// finn ut ventetid
		$wait = max(0, login::$user->data['u_inbox_sent_time'] + self::TIME_WAIT_REPLY - time());
		if ($wait > 0)
		{
			$this->handle_ret(self::RET_ERROR_WAIT, $wait);
			return false;
		}
		
		return true;
	}
	
	/** Legg til svar i meldingstråden */
	public function reply_add($text)
	{
		global $__server;
		$text = trim($text);
		
		// kontroller lengde
		$plain = strip_tags(game::bb_to_html($text));
		$plain = preg_replace("/[^a-zA-ZæøåÆØÅ0-9]/u", '', $plain);
		if (mb_strlen($plain) < 3)
		{
			$this->handle_ret(self::RET_ERROR_CONTENT_SHORT);
			return false;
		}
		
		$time = time();
		
		// sjekk om vi skal øke telleren til brukeren
		// (ingen melding vil bli gitt til brukeren om at den blir økt eller ikke)
		// skal kun gjøres dersom forrige svar var fra en annen bruker,
		// ELLER dersom det har gått mer enn 1 time siden forrige svar
		$result = \Kofradia\DB::get()->query("SELECT im_up_id, im_time FROM inbox_messages WHERE im_it_id = $this->id ORDER BY im_time DESC LIMIT 1");
		$add_count = true;
		if ($row = $result->fetch())
		{
			if ($row['im_up_id'] == login::$user->player->id && $row['im_time'] > $time-3600)
			{
				$add_count = false;
			}
		}
		
		\Kofradia\DB::get()->beginTransaction();
		
		// legg til meldingen
		\Kofradia\DB::get()->exec("INSERT INTO inbox_messages SET im_it_id = $this->id, im_up_id = ".login::$user->player->id.", im_time = $time");
		
		// data
		$insert_id = \Kofradia\DB::get()->lastInsertId();
		\Kofradia\DB::get()->exec("INSERT INTO inbox_data SET id_im_id = $insert_id, id_text = ".\Kofradia\DB::quote($text));
		
		// oppdater relasjoner og brukere
		$where = $this->can_reply_receivers_crew ? " AND (up_access_level != 0 || (u_access_level != 0 AND u_active_up_id = up_id))" : " AND up_access_level != 0";
		\Kofradia\DB::get()->exec("
			UPDATE inbox_rel, users, users_players
			SET ir_unread = ABS(ir_unread) + 1, ir_restrict_im_time = $time, u_inbox_new = u_inbox_new + IF(ir_deleted != 0, ABS(ir_unread), 0) + 1
			WHERE ir_it_id = $this->id".($this->data_rel ? " AND ir_up_id != {$this->data_rel['ir_up_id']}" : "")." AND ir_up_id = up_id$where AND ir_deleted = 0 AND u_id = up_u_id");
		
		// oppdater egen info
		\Kofradia\DB::get()->exec("
			UPDATE users, users_players
			SET ".($add_count ? 'up_inbox_num_messages = up_inbox_num_messages + 1, ' : '')."u_inbox_sent_time = $time
			WHERE u_id = ".login::$user->id." AND up_id = ".login::$user->player->id);
		if ($this->data_rel)
		{
			\Kofradia\DB::get()->exec("
				UPDATE inbox_rel
				SET ir_restrict_im_time = $time
				WHERE ir_it_id = $this->id AND ir_up_id = {$this->data_rel['ir_up_id']}");
		}
		
		\Kofradia\DB::get()->commit();
		
		putlog("LOG", "%c13%bMELDING%b%c: %u".login::$user->player->data['up_name']."%u sendte melding til it_id $this->id (%u{$this->data_thread['it_title']}%u). Lengde: ".mb_strlen($plain)."/".mb_strlen($text)." bytes! {$__server['path']}/innboks_les?id=$this->id");
		
		ess::$b->page->add_message("Meldingen ble lagt til.");
		redirect::handle();
	}
	
	/**
	 * Sett opp HTML for svar
	 */
	public function reply_format($row, $num, $highlight, $new)
	{
		global $__server;
		
		$ret = '
	<div class="thread'.($highlight ? ' thread_highlight scroll_here' : '').($new ? ' thread_ny' : '').($num == 1 ? ' first' : '').($row['im_deleted'] != 0 ? ' deleted' : '').'" id="m'.$row['im_id'].'">
		<div class="title">
			<div class="title_left">#'.$num.' - Av <user id="'.$row['im_up_id'].'" />'.($new ? ' <span class="ny">(Ny!)</span>' : '').'</div>'.(!$this->restrict ? ($row['im_deleted'] == 0 ? '
			<a href="'.game::address($__server['relative_path'].'/innboks_les', $_GET, array(), array("im_del" => $row['im_id'], "sid" => login::$info['ses_id'])).'">Slett</a>' : '
			<a href="'.game::address($__server['relative_path'].'/innboks_les', $_GET, array(), array("im_restore" => $row['im_id'], "sid" => login::$info['ses_id'])).'">Gjenopprett</a>') : '').'
			'.ess::$b->date->get($row['im_time'])->format(date::FORMAT_SEC).'
		</div>
		<div class="text">
			<div class="p">'.game::bb_to_html($row['id_text']).'</div>
		</div>';
		
		// rapportering
		if ($row['im_up_id'] != login::$user->player->id)
		{
			// rapportert?
			if ($row['r_time']) $ret .= '
			<p class="inbox_report_link">Rapportert '.ess::$b->date->get($row['r_time'])->format().'</p>';
			else $ret .= '
			<p class="inbox_report_link"><a href="js" class="report_link" rel="pm,'.$row['im_id'].',1">Rapporter melding</a></p>';
		}
		
		$ret .= '
	</div>';
		
		return $ret;
	}
	
	/**
	 * Hent meldinger på aktuell side
	 * @return resultset
	 */
	public function get_messages($start = NULL, $limit = NULL, $where = NULL)
	{
		$where = ($where ? " AND $where" : "") . ($this->restrict ? " AND im_time <= {$this->data_rel['ir_restrict_im_time']} AND im_deleted = 0" : "");
		$result = \Kofradia\DB::get()->query("
			SELECT im_id, im_up_id, im_time, im_deleted, id_text, r_time
			FROM inbox_messages
				LEFT JOIN rapportering ON r_type = ".rapportering::TYPE_PM." AND r_type_id = im_id AND r_source_up_id = ".login::$user->player->id." AND r_state < 2,
				inbox_data
			WHERE im_it_id = $this->id AND im_id = id_im_id$where
			GROUP BY im_id
			ORDER BY im_time DESC".($start !== NULL ? "
			LIMIT $start, $limit" : ""));
		
		return $result;
	}
	
	/**
	 * Markere meldingstråd for oppfølging
	 * @param boolean $mark
	 */
	public function mark($mark)
	{
		// har vi ikke relasjoner mot denne tråden?
		if (!$this->data_rel)
		{
			$this->handle_ret(self::RET_ERROR_MARK_NO_REL);
			return false;
		}
		
		if ($mark)
		{
			if ($this->data_rel['ir_marked'] != 0)
			{
				$this->handle_ret(self::RET_INFO_MARK_ALREADY);
			}
			else
			{
				// marker tråden
				\Kofradia\DB::get()->exec("
					UPDATE inbox_rel
					SET ir_marked = 1
					WHERE ir_it_id = $this->id AND ir_up_id = {$this->data_rel['ir_up_id']}");
				
				$this->handle_ret(self::RET_INFO_MARK_TRUE);
			}
		}
		
		else
		{
			if ($this->data_rel['ir_marked'] == 0)
			{
				$this->handle_ret(self::RET_INFO_MARK_ALREADY);
			}
			else
			{
				// marker tråden
				\Kofradia\DB::get()->exec("
					UPDATE inbox_rel
					SET ir_marked = 0
					WHERE ir_it_id = $this->id AND ir_up_id = {$this->data_rel['ir_up_id']}");
				
				$this->handle_ret(self::RET_INFO_MARK_FALSE);
			}
		}
		
		return true;
	}
}

class inbox_thread_ajax extends inbox_thread
{
	/**
	 * Forsøk å hent meldingstråd
	 * @param integer $it_id
	 * @return inbox_thread_ajax
	 */
	public static function get($it_id)
	{
		$t = new self($it_id);
		if (!$t->data_thread)
		{
			$t->handle_ret(self::RET_ERROR_404);
			return false;
		}
		return $t;
	}
	
	/** Behandle respons */
	public function handle_ret($id, $data = NULL)
	{
		switch ($id)
		{
			case self::RET_INFO_DELETED_OWN:
			case self::RET_INFO_DELETED:
			case self::RET_INFO_REPORTED:
			break;
			
			case self::RET_ERROR_404:
				ajax::text("Fant ikke meldingstråden.", ajax::TYPE_404);
			break;
			
			case self::RET_ERROR_MARK_NO_REL:
				ajax::text("NO-RELATION", ajax::TYPE_INVALID);
			break;
			
			case self::RET_INFO_MARK_ALREADY:
				if ($this->data_rel['ir_marked'])
					ajax::text("MARK-TRUE");
				else
					ajax::text("MARK-FALSE");
			break;
			
			case self::RET_INFO_MARK_TRUE:
				ajax::text("MARK-TRUE");
			break;
			
			case self::RET_INFO_MARK_FALSE:
				ajax::text("MARK-FALSE");
			break;
			
			default:
				throw new HSException("Ukjent behandler.");
		}
	}
}
