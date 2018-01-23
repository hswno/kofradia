<?php

// sett opp reverse for ID => name
foreach (crewlog::$actions as $name => $info)
{
	crewlog::$actions_id[$info[0]] = $name;
}


/**
 * Crewlogg
 * versjon 2
 */
class crewlog
{
	/** Gruppene av handlinger */
	public static $actions_groups = array(
		1 => "Brukerhandling",
		2 => "Forumhandling",
		3 => "Administrasjon",
		4 => "Firma/broderskap",
		5 => "Spillerhandling"
	);
	
	/** De forskjellige handlingene */
	public static $actions = array(
		// syntax: action => array(lca_id, lcg_id, have_a_up_id, have_log, description, data array(data id => array(data name, data type, data summary, data optional), ...))
		"user_password" => array(2, 1, 1, 1, "Endre passord", array(1 => array("pass_old", "text", 0, 0), 2 => array("pass_new", "text", 0, 0))),
		"user_email" => array(3, 1, 1, 1, "Endre e-postadresse", array(1 => array("email_old", "text", 1, 0), 2 => array("email_new", "text", 1, 0))),
		"user_birth" => array(6, 1, 1, 1, "Endre fødselsdato", array(1 => array("birth_old", "text", 1, 0), 2 => array("birth_new", "text", 1, 0))),
		"user_phone" => array(7, 1, 1, 1, "Endre mobilnummer", array(1 => array("phone_old", "text", 1, 0), 2 => array("phone_new", "text", 1, 0))),
		"user_bank_auth" => array(8, 1, 1, 1, "Endre bankpassord", array(1 => array("pass_old", "text", 0, 0), 2 => array("pass_new", "text", 0, 0))),
		"user_activate" => array(16, 1, 1, 1, "Aktiver bruker", array(1 => array("email_sent", "int", 1, 1), 2 => array("email_note", "text", 0, 1))),
		"user_deactivate" => array(17, 1, 1, 1, "Deaktiver bruker", array(1 => array("note", "text", 0, 0), 2 => array("email_sent", "int", 1, 1))),
		"user_forum_ban_active" => array(18, 1, 1, 1, "Sett forum ban", array(1 => array("time_end", "int", 1, 0), 2 => array("note", "text", 0, 0))),
		"user_forum_ban_change" => array(19, 1, 1, 1, "Endre forum ban", array(1 => array("time_end_old", "int", 1, 0), 2 => array("time_end_new", "int", 1, 1), 3 => array("log_old", "text", 1, 0), 4 => array("log_new", "text", 1, 1), 5 => array("note_old", "text", 0, 0), 6 => array("note_new", "text", 0, 1))),
		"user_forum_ban_delete" => array(20, 1, 1, 1, "Fjern forum ban", array(1 => array("time_end", "int", 1, 0), 2 => array("log", "text", 1, 0), 3 => array("note", "text", 0, 0))),
		"user_deactivate_self" => array(21, 1, 1, 1, "Gi deaktiveringsmulighet", array()),
		"user_add_note" => array(22, 1, 1, 1, "Notat", array()),
		"user_ban_active" => array(23, 1, 1, 1, "Sett blokkering", array(1 => array("type", "int", 1, 0), 2 => array("time_end", "int", 1, 0), 3 => array("note", "text", 0, 0))),
		"user_ban_change" => array(24, 1, 1, 1, "Endre blokkering", array(1 => array("type", "int", 1, 0), 2 => array("time_end_old", "int", 1, 0), 3 => array("time_end_new", "int", 1, 1), 4 => array("log_old", "text", 1, 0), 5 => array("log_new", "text", 1, 1), 6 => array("note_old", "text", 0, 0), 7 => array("note_new", "text", 0, 1))),
		"user_ban_delete" => array(25, 1, 1, 1, "Fjern blokkering", array(1 => array("type", "int", 1, 0), 2 => array("time_end", "int", 1, 0), 3 => array("log", "text", 1, 0), 4 => array("note", "text", 0, 0))),
		"user_note_crew" => array(26, 1, 1, 0, "Endre crewnotat", array(1 => array("note_old", "text", 0, 0), 2 => array("note_diff", "text", 0, 0))),
		"user_warning" => array(64, 1, 1, 1, "Advarsel", array(1 => array("type", "text", 1, 0), 2 => array("note", "text", 1, 0), 3 => array("priority", "int", 1, 0), 4 => array("notified", "int", 1, 1), 5 => array("invalidated", "int", 1, 1), 6 => array("notified_id", "int", 0, 1))),
		"user_warning_edit" => array(65, 1, 1, 0, "Advarsel (redigert)", array(1 => array("lc_id", "int", 1, 0), 2 => array("type_old", "text", 0, 1), 3 => array("type_new", "text", 1, 0), 4 => array("priority_old", "int", 0, 1), 5 => array("priority_new", "int", 1, 0), 6 => array("note_old", "text", 0, 1), 7 => array("note_new", "text", 0, 1), 8 => array("log_old", "text", 0, 1), 9 => array("log_new", "text", 0, 1))),
		"user_warning_invalidated" => array(66, 1, 1, 0, "Advarsel (fjernet)", array(1 => array("lc_id", "int", 1, 0), 2 => array("type", "text", 1, 0), 3 => array("priority", "int", 1, 0))),
		"user_deactivate_change" => array(80, 1, 1, 0, "Deaktivering endret", array(1 => array("log_old", "text", 1, 0), 2 => array("log_new", "text", 1, 1), 3 => array("note_old", "text", 0, 0), 4 => array("note_new", "text", 0, 1))),
		"user_send_email" => array(81, 1, 1, 0, "Send e-post", array(1 => array("email", "text", 1, 0), 2 => array("email_subject", "text", 1, 0), 3 => array("email_content", "text", 1, 0))),
		"user_level" => array(82, 1, 1, 1, "Endre tilgangsnivå", array(1 => array("level_old", "int", 0, 0), 2 => array("level_old_text", "text", 1, 0), 3 => array("level_new", "int", 0, 0), 4 => array("level_new_text", "text", 1, 0), 5 => array("up_id", "int", 1, 1), 6 => array("money", "text", 0, 0), 7 => array("points", "int", 0, 0))),
		"forum_topic_delete" => array(30, 2, 1, 2, "Slett emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title", "text", 1, 0))),
		"forum_topic_restore" => array(31, 2, 1, 2, "Gjenopprett emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title", "text", 1, 0))),
		"forum_topic_edit" => array(32, 2, 1, 0, "Rediger emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title_old", "text", 1, 0), 3 => array("topic_title_new", "text", 1, 1), 4 => array("topic_content_old", "text", 0, 0), 5 => array("topic_content_diff", "text", 0, 1))),
		"forum_reply_delete" => array(33, 2, 1, 0, "Slett svar", array(1 => array("topic_id", "int", 1, 0), 2 => array("reply_id", "int", 1, 0), 3 => array("topic_title", "text", 1, 0))),
		"forum_reply_restore" => array(34, 2, 1, 0, "Gjenopprett svar", array(1 => array("topic_id", "int", 1, 0), 2 => array("reply_id", "int", 1, 0), 3 => array("topic_title", "text", 1, 0))),
		"forum_reply_edit" => array(35, 2, 1, 0, "Rediger svar", array(1 => array("topic_id", "int", 1, 0), 2 => array("reply_id", "int", 1, 0), 3 => array("topic_title", "text", 1, 0), 4 => array("reply_content_old", "text", 0, 0), 5 => array("reply_content_diff", "text", 0, 0))),
		"forum_topics_delete" => array(36, 2, 0, 1, "Slett emner", array(1 => array("data", "text", 1, 0))),
		"forum_topic_move" => array(37, 2, 1, 0, "Flytt emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title", "text", 1, 0), 3 => array("topic_f_id_old", "int", 1, 0), 4 => array("topic_f_id_new", "int", 1, 0))),
		"news_add" => array(40, 3, 0, 0, "Ny nyhet", array(1 => array("news_id", "int", 1, 0), 2 => array("news_title", "text", 1, 0))),
		"news_edit" => array(41, 3, 1, 0, "Rediger nyhet", array(1 => array("news_id", "int", 1, 0), 2 => array("news_title_old", "text", 1, 0), 3 => array("news_title_new", "text", 1, 1), 4 => array("news_data_old", "text", 0, 0), 5 => array("news_data_diff", "text", 0, 1), 6 => array("news_type_bb", "int", 0, 1), 7 => array("news_visible", "int", 0, 1))),
		"news_delete" => array(42, 3, 1, 0, "Slett nyhet", array(1 => array("news_id", "int", 1, 0), 2 => array("news_time", "int", 0, 0), 3 => array("news_title", "text", 1, 0), 4 => array("news_data", "text", 0, 0), 5 => array("news_type_bb", "int", 0, 1), 6 => array("news_visible", "int", 0, 1))),
		"ip_ban_add" => array(50, 3, 0, 1, "Ny IP-ban", array(1 => array("ban_id", "int", 1, 0), 2 => array("ip_start", "int", 1, 0), 3 => array("ip_end", "int", 1, 0), 4 => array("time_start", "int", 1, 0), 5 => array("time_end", "int", 1, 0), 6 => array("note", "text", 0, 0))),
		"ip_ban_edit" => array(51, 3, 0, 1, "Rediger IP-ban", array(1 => array("ban_id", "int", 1, 0), 2 => array("ip_start_old", "int", 1, 0), 3 => array("ip_start_new", "int", 1, 1), 4 => array("ip_end_old", "int", 1, 0), 5 => array("ip_end_new", "int", 1, 1), 6 => array("time_start_old", "int", 1, 0), 7 => array("time_start_new", "int", 1, 1), 8 => array("time_end_old", "int", 1, 0), 9 => array("time_end_new", "int", 1, 1), 10 => array("log_old", "text", 1, 0), 11 => array("log_new", "text", 1, 1), 12 => array("note_old", "text", 0, 0), 13 => array("note_new", "text", 0, 1))),
		"ip_ban_delete" => array(52, 3, 0, 1, "Slett IP-ban", array(1 => array("ban_id", "int", 1, 0), 2 => array("ip_start", "int", 1, 0), 3 => array("ip_end", "int", 1, 0), 4 => array("time_start", "int", 1, 0), 5 => array("time_end", "int", 1, 0), 6 => array("log", "text", 1, 0), 7 => array("note", "text", 0, 0))),
		"f_forum_topic_delete" => array(70, 4, 1, 0, "Slett emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title", "text", 1, 0), 3 => array("ff_type", "text", 1, 0), 4 => array("ff_id", "int", 1, 0), 5 => array("ff_name", "text", 1, 0))),
		"f_forum_topic_restore" => array(71, 4, 1, 0, "Gjenopprett emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title", "text", 1, 0), 3 => array("ff_type", "text", 1, 0), 4 => array("ff_id", "int", 1, 0), 5 => array("ff_name", "text", 1, 0))),
		"f_forum_topic_edit" => array(72, 4, 1, 0, "Rediger emne", array(1 => array("topic_id", "int", 1, 0), 2 => array("topic_title_old", "text", 1, 0), 3 => array("topic_title_new", "text", 1, 1), 4 => array("topic_content_old", "text", 0, 0), 5 => array("topic_content_diff", "text", 0, 1), 6 => array("ff_type", "text", 1, 0), 7 => array("ff_id", "int", 1, 0), 8 => array("ff_name", "text", 1, 0))),
		"f_forum_reply_delete" => array(73, 4, 1, 0, "Slett svar", array(1 => array("topic_id", "int", 1, 0), 2 => array("reply_id", "int", 1, 0), 3 => array("topic_title", "text", 1, 0), 4 => array("ff_type", "text", 1, 0), 5 => array("ff_id", "int", 1, 0), 6 => array("ff_name", "text", 1, 0))),
		"f_forum_reply_restore" => array(74, 4, 1, 0, "Gjenopprett svar", array(1 => array("topic_id", "int", 1, 0), 2 => array("reply_id", "int", 1, 0), 3 => array("topic_title", "text", 1, 0), 4 => array("ff_type", "text", 1, 0), 5 => array("ff_id", "int", 1, 0), 6 => array("ff_name", "text", 1, 0))),
		"f_forum_reply_edit" => array(75, 4, 1, 0, "Rediger svar", array(1 => array("topic_id", "int", 1, 0), 2 => array("reply_id", "int", 1, 0), 3 => array("topic_title", "text", 1, 0), 4 => array("reply_content_old", "text", 0, 0), 5 => array("reply_content_diff", "text", 0, 0), 6 => array("ff_type", "text", 1, 0), 7 => array("ff_id", "int", 1, 0), 8 => array("ff_name", "text", 1, 0))),
		"f_forum_topics_delete" => array(76, 4, 0, 0, "Slett emner", array(1 => array("data", "text", 1, 0))),
		"player_name" => array(1, 5, 1, 1, "Endre spillernavn", array(1 => array("user_old", "text", 1, 0), 2 => array("user_new", "text", 1, 0))),
		"player_profile_text" => array(4, 5, 1, 2, "Endre profiltekst", array(1 => array("profile_text_old", "text", 0, 0), 2 => array("profile_text_diff", "text", 0, 0))),
		"player_signature" => array(5, 5, 1, 2, "Endre signatur", array(1 => array("signature_old", "text", 0, 0), 2 => array("signature_diff", "text", 0, 0))),
		"player_rank_inc" => array(9, 5, 1, 1, "Øke rank", array(1 => array("points", "int", 1, 0))),
		"player_rank_dec" => array(10, 5, 1, 1, "Senke rank", array(1 => array("points", "int", 1, 0))),
		"player_image_add" => array(11, 5, 1, 0, "Last opp profilbilde", array(1 => array("image_id", "int", 1, 0))),
		"player_image_del" => array(12, 5, 1, 0, "Slett profilbilde", array(1 => array("image_id", "int", 1, 0), 2 => array("image_data", "text", 0, 0))),
		"player_image_active" => array(13, 5, 1, 0, "Sett aktivt profilbilde", array(1 => array("image_id", "int", 1, 0))),
		"player_image_inactive" => array(14, 5, 1, 0, "Fjern aktivt profilbilde", array(1 => array("image_id", "int", 1, 0))),
		"player_note_crew" => array(15, 5, 1, 0, "Endre admin notat", array(1 => array("note_old", "text", 0, 0), 2 => array("note_diff", "text", 0, 0))),
		"player_activate" => array(60, 5, 1, 1, "Aktiver spiller", array(1 => array("email_sent", "int", 1, 1), 2 => array("email_note", "text", 0, 1))),
		"player_deactivate" => array(61, 5, 1, 1, "Deaktiver spiller", array(1 => array("note", "text", 0, 0), 2 => array("email_sent", "int", 1, 1))),
		"player_add_note" => array(62, 5, 1, 1, "Spillernotat", array()),
		"player_deactivate_change" => array(63, 5, 1, 0, "Deaktivering endret", array(1 => array("log_old", "text", 1, 0), 2 => array("log_new", "text", 1, 1), 3 => array("note_old", "text", 0, 0), 4 => array("note_new", "text", 0, 1))),
		"player_message_delete" => array(83, 5, 1, 0, "Slettet melding", array(1 => array("it_id", "int", 1, 0), 2 => array("im_id", "int", 1, 0), 3 => array("it_title", "text", 1, 0))),
		"player_message_restore" => array(84, 5, 1, 0, "Gjenopprettet melding", array(1 => array("it_id", "int", 1, 0), 2 => array("im_id", "int", 1, 0), 3 => array("it_title", "text", 1, 0))),
		"player_thread_delete" => array(85, 5, 0, 0, "Slettet meldingstråd", array(1 => array("it_id", "int", 1, 0), 2 => array("it_title", "text", 1, 0)))
	);
	
	/** ID->Navn-referanse for de forskjellige handlingene */
	public static $actions_id = array();
	
	/**
	 * Ulike typer man kan velge for en advarsel
	 */
	public static $user_warning_types = array(
		"Forum",
		"Meldinger",
		"Rapporteringer",
		"Support",
		"Profiltekst",
		"Signatur",
		"Profilbilde",
		"Annet"
	);
	public static $user_warning_types_name = array(
		"forum" => 0,
		"messages" => 1,
		"reports" => 2,
		"support" => 3,
		"profiletext" => 4,
		"signature" => 5,
		"profileimg" => 6,
		"other" => 7
	);
	
	/**
	 * Legg til logg for en handling
	 * @param string $action
	 * @param integer $a_up_id
	 * @param string $log
	 * @param array $data
	 * @param integer $up_id hvem som utfører denne handlingen
	 * @return integer
	 */
	public static function log($action, $a_up_id = NULL, $log = NULL, $data = array(), $up_id = NULL)
	{
		global $_base;
		
		// sjekk handling
		if (!isset(crewlog::$actions[$action]))
		{
			throw new HSException("Ukjent handling: ".htmlspecialchars($action));
		}
		
		// bruker id
		if ($up_id == NULL)
		{
			if (!login::$logged_in)
			{
				throw new HSException("Mangler bruker ID.");
			}
			$up_id = login::$user->player->id;
		}
		else
		{
			$up_id = (int) $up_id;
		}
		
		// hent handling info
		$a = crewlog::$actions[$action];
		
		/*
		 * action => array(
		 * 	0 => lca_id,
		 * 	1 => lcg_id,
		 * 	2 => have_a_up_id,
		 * 	3 => have_log,
		 * 	4 => description,
		 * 	5 => data array(
		 * 		data id => array(
		 * 			0 => data name,
		 * 			1 => data type,
		 * 			2 => data summary,
		 * 			3 => data optional
		 *		),
		 * 		...
		 * 	)
		 * )
		 */
		
		// kontroller a_up_id
		if ($a[2] == 1)
		{
			if (is_null($a_up_id))
			{
				// feil: mangler
				throw new HSException("Mangler berørt bruker ID (action: $action)");
			}
			
			$a_up_id = intval($a_up_id);
		}
		elseif (!is_null($a_up_id))
		{
			// feil: skal ikke være med
			throw new HSException("Berørt bruker ID skal ikke være med (action: $action)");
		}
		else
		{
			$a_up_id = 'NULL';
		}
		
		// kontroller logg
		if (!is_null($log) && !is_int($log) && !is_string($log))
		{
			// feil: ugyldig inndata
			throw new HSException("Ugyldig begrunnelse (logg) type (action: $action)");
		}
		if ($a[3] == 0 && !is_null($log))
		{
			// feil: skal ikke være med
			throw new HSException("Begrunnelse (logg) skal ikke være med (action: $action)");
		}
		elseif ($a[3] == 1 && is_null($log))
		{
			// feil: mangler (ikke valgfri)
			throw new HSException("Mangler begrunnelse (logg) (action: $action)");
		}
		elseif (is_null($log))
		{
			$log = 'NULL';
		}
		else
		{
			$log = \Kofradia\DB::quote($log);
		}
		
		// kontroller data
		$data_new = array();
		foreach ($a[5] as $id => $info)
		{
			// finnes denne?
			if (array_key_exists($info[0], $data))
			{
				// sett riktig escaped verdi
				// NULL, integer, 'string'
				$value = is_null($data[$info[0]]) ? array('NULL', 'NULL') : ($info[1] == "int" ? array(intval($data[$info[0]]), 'NULL') : array('NULL', \Kofradia\DB::quote($data[$info[0]])));
				
				// legg til i data som skal legges inn
				$data_new[$id] = $value;
				
				// fjern fra inndata
				unset($data[$info[0]]);
			}
			
			// finnes ikke - og kreves
			elseif ($info[3] == 0)
			{
				throw new HSException("Mangler data for ".$info[0]." (action: $action)");
			}
		}
		
		// har vi noe ekstra inndata (ugyldig)
		if (count($data) > 0)
		{
			throw new HSException("Overflødig inndata: ".implode(", ", array_map("htmlspecialchars", array_keys($data)))." (action: $action)");
		}
		
		// legg til i loggen
		\Kofradia\DB::get()->exec("INSERT INTO log_crew SET lc_up_id = $up_id, lc_time = ".time().", lc_lca_id = {$a[0]}, lc_a_up_id = $a_up_id, lc_log = $log");
		$id = \Kofradia\DB::get()->lastInsertId();
		
		// legg til data
		foreach ($data_new as $data_id => $data)
		{
			\Kofradia\DB::get()->exec("INSERT INTO log_crew_data SET lcd_lca_id = {$a[0]}, lcd_lc_id = $id, lcd_lce_id = $data_id, lcd_data_int = {$data[0]}, lcd_data_text = {$data[1]}");
		}
		
		return $id;
	}
	
	/** Hent informasjon om handlinger */
	public static function load_summary_data($rows)
	{
		global $_crewlog, $_base;
		
		// hent ut ID-ene
		$ids = array();
		foreach ($rows as $id => $row)
		{
			$ids[] = intval($row['lc_id']);
			
			// stemmer ikke id?
			if ($id != $row['lc_id'])
			{
				throw new HSException("Array ID stemmer ikke med lc_id (crewlog::load_data)");
			}
			
			$rows[$id]['data'] = array();
		}
		$ids = array_unique($ids);
		
		// ingenting å hente?
		if (count($ids) == 0) return $rows;
		
		// hent data
		$result = \Kofradia\DB::get()->query("SELECT lcd_lc_id, lcd_lca_id, lcd_lce_id, lcd_data_int, lcd_data_text, lce_type FROM log_crew_data, log_crew_extra WHERE lcd_lc_id IN (".implode(",", $ids).") AND lcd_lca_id = lce_lca_id AND lcd_lce_id = lce_id AND lce_summary = 1");
		while ($row = $result->fetch())
		{
			$data_name = crewlog::$actions[crewlog::$actions_id[$row['lcd_lca_id']]][5][$row['lcd_lce_id']][0];
			$rows[$row['lcd_lc_id']]['data'][$data_name] = $row['lce_type'] == "int" ? $row['lcd_data_int'] : $row['lcd_data_text'];
		}
		
		return $rows;
	}
	
	/**
	 * Sett opp tekst for handlinger
	 * @param array $row
	 * @param array $data
	 * @param boolean $show_a_up vise hvem handlingen ble utført på?
	 * @return string
	 */
	public static function make_summary($row, $data = NULL, $show_a_up = true)
	{
		global $_crewlog, $_base, $__server;
		
		if ($data === NULL) $data = $row['data'];
		$action = crewlog::$actions_id[$row['lc_lca_id']];
		
		$up_til = $show_a_up ? ' til <user id="'.$row['lc_a_up_id'].'" />' : '';
		$up_for = $show_a_up ? ' for <user id="'.$row['lc_a_up_id'].'" />' : '';
		$up_av = $show_a_up ? ' av <user id="'.$row['lc_a_up_id'].'" />' : '';
		$up = $show_a_up ? ' <user id="'.$row['lc_a_up_id'].'" />' : '';
		
		#$log_info = $row['lc_log'] ? '<br /><div class="crewlog_note">'.game::bb_to_html($row['lc_log']).'</div>' : '';
		$log_info = $row['lc_log'] ? '<span class="crewlog_note">'.game::bb_to_html($row['lc_log']).'</span>' : '';
		$more_link = '<a href="'.$__server['relative_path'].'/crew/crewlogg?lc_id='.$row['lc_id'].'">Se informasjon &raquo;</a>';
		
		switch ($action)
		{
			// TODO: hent inn mer data for oppføringene (spesielt i forhold til player_* hvor mye har ubrukt data)
			// TODO: sette hvem som utførte handlingen bakerst, fjerne hvem handlingen ble utført på om det ikke skal være med ($show_a_up)
			case "user_password":
				return 'Bruker: Endret passordet'.$up_til.'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_email":
				return 'Bruker: Endret e-posten'.$up_til.' fra '.htmlspecialchars($data['email_old']).' til '.htmlspecialchars($data['email_new']).'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_send_email":
				return 'Bruker: E-post sendt til '.htmlspecialchars($data['email']).'. (<user id="'.$row['lc_up_id'].'" />)<br /><div class="crewlog_note"><b>Emne: '.game::bb_to_html($data['email_subject']).'</b><br /><span style="font-family: monospace">'.nl2br(htmlspecialchars($data['email_content'])).'</span></div>';
				
			case "user_birth":
				return 'Bruker: Endret fødselsdatoen'.$up_til.' fra '.$data['birth_old'].' til '.$data['birth_new'].'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_phone":
				return 'Bruker: Endret mobilnummeret'.$up_til.' fra '.(empty($data['phone_old']) ? '<i>tomt</i>' : $data['phone_old']).' til '.(empty($data['phone_new']) ? '<i>tomt</i>' : $data['phone_new']).'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_bank_auth":
				return 'Bruker: Endret bankpassordet'.$up_til.'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_image_add":
				return 'Spiller: Lastet opp et nytt profilbilde'.$up_for.' (bilde #'.$data['image_id'].'). (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_image_del":
				return 'Spiller: Slettet et profilbilde'.$up_til.' (bilde #'.$data['image_id'].'). (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_image_active":
				return 'Spiller: Endret aktivt profilbilde'.$up_til.' (til bilde #'.$data['image_id'].'). (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_image_inactive":
				return 'Spiller: Fjernet aktivt profilbilde'.$up_til.' (bilde #'.$data['image_id'].'). (<user id="'.$row['lc_up_id'].'" />)';
				
			case "user_note_crew":
				return 'Bruker: Endret crewnotatet'.$up_til.'. '.$more_link.' (<user id="'.$row['lc_up_id'].'" />)';
				
			case "user_level":
				return 'Bruker: Endret tilgangsnivået'.$up_til.' fra '.htmlspecialchars($data['level_old_text']).' til '.htmlspecialchars($data['level_new_text']).'.'.(empty($data['up_id']) ? ' Spilleren ble ikke berørt.' : ' Spilleren ble også oppdatert.').' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_activate":
				return 'Bruker: Aktiverte brukeren'.$up.'.'.(isset($data['email_sent']) ? ' E-post ble sendt til brukeren.' : ' E-post ikke sendt.').' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_deactivate":
				return 'Bruker: Deaktiverte brukeren'.$up.'.'.(isset($data['email_sent']) ? ' E-post ble sendt til brukeren.' : ' E-post ikke sendt.').' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_deactivate_self":
				return 'Bruker: Gav deaktiveringsmulighet'.$up_til.'. Referanse/logg: '.game::bb_to_html($row['lc_log']).' (<user id="'.$row['lc_up_id'].'" />)';
				
			case "user_deactivate_change":
				return 'Bruker: Endret deaktivering'.$up_til.'. '.$more_link.' (<user id="'.$row['lc_up_id'].'" />)';
				
			case "user_forum_ban_active":
				return 'Bruker: Gav forum ban'.$up_til.'. Til: '.$_base->date->get($data['time_end'])->format().'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_forum_ban_change":
				// TODO: Legge til mer info
				return 'Bruker: Endret forum ban'.$up_til.'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_forum_ban_delete":
				// TODO: Legge til mer info
				return 'Bruker: Fjernet forum ban'.$up_til.'. Ville utløpt '.$_base->date->get($data['time_end'])->format().' ('.game::timespan($data['time_end']-$row['lc_time']).'). (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_add_note":
				return 'Bruker: Tilegnet et notat'.$up_til.': (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_ban_active":
				$type = blokkeringer::get_type($data['type']);
				return 'Blokkering: Gav blokkering ('.htmlspecialchars($type['title']).')'.$up_til.'. Til: '.$_base->date->get($data['time_end'])->format(date::FORMAT_SEC).'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_ban_change":
				// TODO: Legge til mer info
				$type = blokkeringer::get_type($data['type']);
				return 'Blokkering: Endret blokkeringen ('.htmlspecialchars($type['title']).')'.$up_til.'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_ban_delete":
				// TODO: Legge til mer info
				$type = blokkeringer::get_type($data['type']);
				return 'Blokkering: Fjernet blokkeringen ('.htmlspecialchars($type['title']).''.$up_til.'. Ville utløpt '.$_base->date->get($data['time_end'])->format().' ('.game::timespan($data['time_end']-$row['lc_time'], game::TIME_ALL, 5).' gjensto). (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "user_warning":
				$priority = $data['priority'] == 1 ? 'Lav' : ($data['priority'] == 3 ? 'Høy' : 'Moderat');
				return 'Advarsel: Tilegnet en advarsel'.$up_til.' (kategori: '.$data['type'].'). Prioritet: '.$priority.'.'.(isset($data['notified']) && $data['notified'] != 0 ? ' Brukeren ble varslet med logg.' : ' Brukerne ble ikke varslet.').(isset($data['invalidated']) && $data['invalidated'] != 0 ? ' Advarselen har blitt markert som ugyldig.' : ' <a href="'.ess::$s['relative_path'].'/crew/crewlogg?lc_id='.$row['lc_id'].'&amp;edit">Rediger advarsel</a>').' (<user id="'.$row['lc_up_id'].'" />)'.$log_info.'<span class="crewlog_note">'.game::bb_to_html($data['note']).'</span>';
				
			case "user_warning_edit":
				$priority = $data['priority_new'] == 1 ? 'Lav' : ($data['priority_new'] == 3 ? 'Høy' : 'Moderat');
				return 'Advarsel: Redigerte en <a href="'.ess::$s['relative_path'].'/crew/crewlogg?lc_id='.$data['lc_id'].'">tidligere gitt advarsel</a>'.$up_til.' (kategori: '.$data['type_new'].'). Prioritet: '.$priority.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "user_warning_invalidated":
				$priority = $data['priority'] == 1 ? 'Lav' : ($data['priority'] == 3 ? 'Høy' : 'Moderat');
				return 'Advarsel: Slettet en <a href="'.ess::$s['relative_path'].'/crew/crewlogg?lc_id='.$data['lc_id'].'">tidligere gitt advarsel</a>'.$up_til.' (kategori: '.$data['type'].'). Prioritet: '.$priority.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_name":
				return 'Spiller: Endret spillernavnet'.$up_til.' fra '.htmlspecialchars($data['user_old']).' til '.htmlspecialchars($data['user_new']).'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info; 
				
			case "player_profile_text":
				return 'Spiller: Endret profilteksten'.$up_til.'. '.$more_link.' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_signature":
				return 'Spiller: Endret signaturen'.$up_til.'. '.$more_link.' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_rank_inc":
				return 'Spiller: Økte ranken'.$up_til.' med '.game::format_number($data['points']).' poeng. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_rank_dec":
				return 'Spiller: Senket ranken'.$up_til.' med '.game::format_number($data['points']).' poeng. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_deactivate_change":
				return 'Spiller: Endret deaktivering'.$up_til.'. '.$more_link.' (<user id="'.$row['lc_up_id'].'" />)';
			
			case "player_activate":
				return 'Spiller: Aktiverte spilleren'.$up.'.'.(isset($data['email_sent']) ? ' E-post ble sendt til brukeren.' : ' E-post ikke sendt.').' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_deactivate":
				return 'Spiller: Deaktiverte spilleren'.$up.'.'.(isset($data['email_sent']) ? ' E-post ble sendt til brukeren.' : ' E-post ikke sendt.').' (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "player_note_crew":
				return 'Spiller: Endret crewnotatet'.$up_til.'. '.$more_link.' (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_message_delete":
				return 'Spiller: Slettet melding'.$up_av.' i meldingstråden <a href="'.$__server['relative_path'].'/innboks_les?id='.$data['it_id'].'&amp;goto='.$data['im_id'].'">'.htmlspecialchars($data['it_title']).'</a>. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_message_restore":
				return 'Spiller: Gjenopprettet melding'.$up_av.' i meldingstråden <a href="'.$__server['relative_path'].'/innboks_les?id='.$data['it_id'].'&amp;goto='.$data['im_id'].'">'.htmlspecialchars($data['it_title']).'</a>. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "player_thread_delete":
				return 'Spiller: Slettet meldingstråden <a href="'.$__server['relative_path'].'/innboks_les?id='.$data['it_id'].'">'.htmlspecialchars($data['it_title']).'</a>. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "forum_topic_delete":
				return 'Forum: Slettet emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($data['topic_title']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "forum_topic_restore":
				return 'Forum: Gjenopprettet emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($data['topic_title']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "forum_topic_edit":
				// TODO: Legge til mer info
				return 'Forum: Redigerte emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($data['topic_title_old']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "forum_reply_delete":
				return 'Forum: Slettet et svar fra emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'&amp;replyid='.$data['reply_id'].'">'.htmlspecialchars($data['topic_title']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "forum_reply_restore":
				return 'Forum: Gjenopprettet et svar i emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'&amp;replyid='.$data['reply_id'].'">'.htmlspecialchars($data['topic_title']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "forum_reply_edit":
				return 'Forum: Redigerte et svar i emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'&amp;replyid='.$data['reply_id'].'">'.htmlspecialchars($data['topic_title']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "forum_topics_delete":
				// TODO: Legge inn hvilke emner som ble slettet
				return 'Forum: Slettet '.$row['lc_log'].' emne'.($row['lc_log'] == 1 ? '' : 'r').' fra forumet. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "forum_topic_move":
				// TODO: Legge inn hvilket forum emnet ble flyttet fra og til
				return 'Forum: Flyttet emnet <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($data['topic_title']).'</a>'.$up_av.'. (<user id="'.$row['lc_up_id'].'" />)';
				
			case "news_add":
				// TODO: Link til nyheten
				return 'Nyheter: <user id="'.$row['lc_up_id'].'" /> opprettet en ny nyhet ('.htmlspecialchars($data['news_title']).').';
				
			case "news_edit":
				// TODO: Link til nyheten og hva som ble endret
				return 'Nyheter: <user id="'.$row['lc_up_id'].'" /> redigerte nyheten '.htmlspecialchars($data['news_title_old']).'.';
				
			case "news_delete":
				// TODO: Informasjon om nyheten
				return 'Nyheter: <user id="'.$row['lc_up_id'].'" /> slettet nyheten '.htmlspecialchars($data['news_title']).'.';
				
			case "ip_ban_add":
				// TODO: Mer info
				$info = $data['ip_start'] == $data['ip_end'] ? 'IP-en '.long2ip($data['ip_start']) : 'IP-adressene '.long2ip($data['ip_start']).'-'.long2ip($data['ip_end']);
				return 'IP-ban: Ny IP-ban for '.$info.' til '.$_base->date->get($data['time_end'])->format().'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "ip_ban_edit":
				// TODO: Mer info
				$info = $data['ip_start_new'] == $data['ip_end_new'] ? 'IP-en '.long2ip($data['ip_start_new']) : 'IP-adressene '.long2ip($data['ip_start_new']).'-'.long2ip($data['ip_end_new']);
				return 'IP-ban: Redigerte en IP-ban for '.$info.' til '.$_base->date->get($data['time_end_new'])->format().'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
				
			case "ip_ban_delete":
				// TODO: Mer info
				$info = $data['ip_start_new'] == $data['ip_end_new'] ? 'IP-en '.long2ip($data['ip_start_new']) : 'IP-adressene '.long2ip($data['ip_start_new']).'-'.long2ip($data['ip_end_new']);
				return 'IP-ban: Fjernet IP-ban for '.$info.' som skulle gått ut '.$_base->date->get($data['time_end'])->format().'. (<user id="'.$row['lc_up_id'].'" />)'.$log_info;
			
			case "f_forum_topic_delete":
				if (!isset($data['ff_type']) || $data['ff_type'] == "f" || $data['ff_type'] == "fa")
					return self::make_summary_ff($row, $data, '<user id="'.$row['lc_up_id'].'" /> slettet forumtråden '.htmlspecialchars($data['topic_title']).'.');
				return self::make_summary_ff($row, $data, 'Slettet forumtråden <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($data['topic_title']).'</a>.').' (<user id="'.$row['lc_up_id'].'" />)';
			
			case "f_forum_topic_restore":
				if (!isset($data['ff_type']) || $data['ff_type'] == "f" || $data['ff_type'] == "fa")
					return self::make_summary_ff($row, $data, '<user id="'.$row['lc_up_id'].'" /> gjenopprettet forumtråden '.htmlspecialchars($data['topic_title']).'.');
				return self::make_summary_ff($row, $data, 'Gjenopprettet forumtråden <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($data['topic_title']).'</a>.').' (<user id="'.$row['lc_up_id'].'" />)';
			
			case "f_forum_topic_edit":
				$title = isset($data['topic_title_new']) ? $data['topic_title_new'] : $data['topic_title_old'];
				if (!isset($data['ff_type']) || $data['ff_type'] == "f" || $data['ff_type'] == "fa")
					return self::make_summary_ff($row, $data, '<user id="'.$row['lc_up_id'].'" /> redigerte forumtråden '.htmlspecialchars($title).(isset($data['topic_title_new']) ? ' (tidligere '.htmlspecialchars($data['topic_title_old']).')' : '').'.');
				return self::make_summary_ff($row, $data, 'Redigerte forumtråden <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'">'.htmlspecialchars($title).'</a>'.(isset($data['topic_title_new']) ? ' (tidligere '.htmlspecialchars($data['topic_title_old']).')' : '').'.').' (<user id="'.$row['lc_up_id'].'" />)';
			
			case "f_forum_reply_delete":
				if (!isset($data['ff_type']) || $data['ff_type'] == "f" || $data['ff_type'] == "fa")
					return self::make_summary_ff($row, $data, '<user id="'.$row['lc_up_id'].'" /> slettet forumsvaret med ID '.$data['reply_id'].' fra forumtråden '.htmlspecialchars($data['topic_title']).'.');
				return self::make_summary_ff($row, $data, 'Slettet forumsvaret med ID '.$data['reply_id'].' fra forumtråden <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'&amp;replyid='.$data['reply_id'].'">'.htmlspecialchars($data['topic_title']).'</a>.').' (<user id="'.$row['lc_up_id'].'" />)';
			
			case "f_forum_reply_restore":
				if (!isset($data['ff_type']) || $data['ff_type'] == "f" || $data['ff_type'] == "fa")
					return self::make_summary_ff($row, $data, '<user id="'.$row['lc_up_id'].'" /> gjenopprettet forumsvaret med ID '.$data['reply_id'].' i forumtråden '.htmlspecialchars($data['topic_title']).'.');
				return self::make_summary_ff($row, $data, 'Gjenopprettet forumsvaret med ID '.$data['reply_id'].' i forumtråden <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'&amp;replyid='.$data['reply_id'].'">'.htmlspecialchars($data['topic_title']).'</a>.').' (<user id="'.$row['lc_up_id'].'" />)';
			
			case "f_forum_reply_edit":
				if (!isset($data['ff_type']) || $data['ff_type'] == "f" || $data['ff_type'] == "fa")
					return self::make_summary_ff($row, $data, '<user id="'.$row['lc_up_id'].'" /> redigerte forumsvaret med ID '.$data['reply_id'].' i forumtråden '.htmlspecialchars($data['topic_title']).'.');
				return self::make_summary_ff($row, $data, 'Redigerte forumsvaret med ID '.$data['reply_id'].' i forumtråden <a href="'.$__server['relative_path'].'/forum/topic?id='.$data['topic_id'].'&amp;replyid='.$data['reply_id'].'">'.htmlspecialchars($data['topic_title']).'</a>.').' (<user id="'.$row['lc_up_id'].'" />)';
		}
		
		return "missing template ($action)";
	}
	
	/**
	 * Summary for ff
	 */
	protected static function make_summary_ff($row, $data, $text)
	{
		global $__server;
		$prefix = "";
		
		if (isset($data['ff_type']))
		{
			// firma
			if ($data['ff_type'] == "f")
			{
				$prefix = 'Firma: '.htmlspecialchars($data['ff_name']).' (#'.$data['ff_id'].') - ';
			}
			
			// broderskap
			elseif ($data['ff_type'] == "fa")
			{
				 $prefix = 'Broderskap: '.htmlspecialchars($data['ff_name']).' (#'.$data['ff_id'].') - ';
			}
			
			// ff
			else
			{
				$prefix = ucfirst($data['ff_type']).' <a href="'.$__server['relative_path'].'/ff/?ff_id='.$data['ff_id'].'">'.htmlspecialchars($data['ff_name']).'</a> - ';
			}
		}
		
		return $prefix . $text;
	}
	
	/** Eksporter lista over actions så den kan legges i scriptet */
	public static function generate_action_list()
	{
		global $_base;
		
		// hent handlingene
		$actions = array();
		$result = \Kofradia\DB::get()->query("SELECT lca_id, lca_lcg_id, lca_name, lca_have_a_up_id, lca_have_log, lca_description FROM log_crew_actions ORDER BY lca_lcg_id, lca_id");
		while ($row = $result->fetch())
		{
			$row['data'] = array();
			$actions[$row['lca_id']] = $row;
		}
		
		// hent dataene
		$result = \Kofradia\DB::get()->query("SELECT lce_lca_id, lce_id, lce_name, lce_summary, lce_type, lce_optional FROM log_crew_extra ORDER BY lce_lca_id, lce_id");
		while ($row = $result->fetch())
		{
			$actions[$row['lce_lca_id']]['data'][] = "{$row['lce_id']} => array(\"{$row['lce_name']}\", \"{$row['lce_type']}\", {$row['lce_summary']}, {$row['lce_optional']})";
		}
		
		// sett sammen
		foreach ($actions as $id => $row)
		{
			$actions[$id] = "\"{$row['lca_name']}\" => array($id, {$row['lca_lcg_id']}, {$row['lca_have_a_up_id']}, {$row['lca_have_log']}, \"{$row['lca_description']}\", array(".implode(", ", $row['data'])."))";
		}
		
		header("Content-Type: text/plain");
		echo "\t\t".implode(",\n\t\t", $actions);
		
		exit;
	}
}