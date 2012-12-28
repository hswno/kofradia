<?php

class database_archive
{
	/**
	 * Uke scheduler - kjøres 1 gang i uka (mandager)
	 */
	public static function run_weekly()
	{
		if (DBARCHIVE_DIR === null) return; // hopp over
		@set_time_limit(0);
		
		self::handle_table_users_log();
		self::handle_table_sessions();
		self::handle_table_poker();
		# TODO: se ticket #351 self::handle_table_lotto_vinnere();
		self::handle_table_ff_bank_transactions();
		self::handle_tables_inbox();
		self::handle_tables_forum();
	}
	
	/**
	 * 24-timers scheduler - kjøres 1 gang i døgnet
	 */
	public static function run_24h()
	{
		if (DBARCHIVE_DIR === null) return; // hopp over
		@set_time_limit(0);
		
		self::handle_table_users_views();
		self::handle_table_log_referers();
	}
	
	/**
	 * 6-timers scheduler - kjøres 4 ganger i døgnet
	 */
	public static function run_6h()
	{
		if (DBARCHIVE_DIR === null) return; // hopp over
		@set_time_limit(0);
		
		self::handle_table_users_antibot_validate();
		self::handle_table_forms();
	}
	
	/**
	 * Opprett en ny tabell for backup
	 * @return string navn på ny tabell
	 */
	public static function create_backup_table($table_name)
	{
		$new_table_name = $table_name . "_" . ess::$b->date->get()->format("Ymd_His");
		ess::$b->db->query("CREATE TABLE $new_table_name LIKE $table_name");
		
		return $new_table_name;
	}
	
	/**
	 * Eksporter backup tabell og evt. slett tabellen
	 * @param string tabellen
	 * @param bool slette tabellen etter backup?
	 */
	public static function backup_table_export($table, $delete = null, $is_backup = null)
	{
		$backup = $is_backup ? '.backup' : '';
		$date = ess::$b->date->get()->format("Ymd.His");
		$x = "";
		$i = 1;
		do
		{
			$file = escapeshellarg(DBARCHIVE_DIR . "/mysqldump$backup.".DBNAME.".$table.$date$x.sql");
			$x = "-".($i++);
		} while (file_exists($file));
		
		$host = substr(DBHOST, 0, 1) == "/" ? "" : " --host=".escapeshellarg(DBHOST);
		$cmd = "mysqldump --add-drop-table=FALSE$host --user=".escapeshellarg(DBUSER)." --pass=".escapeshellarg(DBPASS)." ".escapeshellarg(DBNAME)." ".escapeshellarg($table)." >$file";
		
		// eksporter dataen
		exec($cmd, $value, $status);
		
		if ($status !== 0)
		{
			throw new HSException("Kunne ikke eksportere data fra tabellen $table: ".implode("\n", $value));
		}
		
		// skal vi slette tabellen?
		if ($delete)
		{
			ess::$b->db->query("DROP TABLE $table");
		}
		
		putlog("NOTICE", "DATABASE DUMP: $file");
		return $file;
	}
	
	/**
	 * Behandle users_antibot_validate tabellen
	 * Slett oppføringer eldre enn 6 timer
	 */
	public static function handle_table_users_antibot_validate()
	{
		$expire = time() - 3600*6;
		ess::$b->db->query("DELETE FROM users_antibot_validate WHERE time < $expire");
	}
	
	/**
	 * Behandle users_views tabellen
	 * Oppføringer som er eldre enn 1 uke vil ikke bli vist i en profil og kan derfor slettes
	 */
	public static function handle_table_users_views()
	{
		$expire = time() - 604800; // 1 uke
		ess::$b->db->query("DELETE FROM users_views WHERE time < $expire");
	}
	
	/**
	 * Behandle forms tabellen
	 * Alett oppføringer som ble opprettet for mer enn 6 timer siden
	 */
	public static function handle_table_forms()
	{
		$expire = time() - 3600*6;
		ess::$b->db->query("DELETE FROM forms WHERE forms_created_time < $expire");
	}
	
	/**
	 * Behandle log_referers tabellen
	 * Ta backup av oppføringene eldre enn 24 timer og slett fra databasen
	 */
	public static function handle_table_log_referers()
	{
		$expire = time() - 86400;
		
		// er det noen oppføringer?
		$result = ess::$b->db->query("SELECT COUNT(*) FROM log_referers WHERE lr_time < $expire");
		$num = mysql_result($result, 0);
		
		if ($num == 0) return; // ingen oppføringer å behandle
		
		// opprett tabell for backup
		$table = self::create_backup_table("log_referers");
		
		// overfør data
		ess::$b->db->query("INSERT INTO $table SELECT * FROM log_referers WHERE lr_time < $expire");
		
		// slett data
		ess::$b->db->query("DELETE FROM log_referers WHERE lr_time < $expire");
		
		// ta backup av tabell og slett backup-tabellen
		$file = self::backup_table_export($table, true);
	}
	
	/**
	 * Behandle users_log tabellen
	 */
	public static function handle_table_users_log()
	{
		// opprett backup tabell
		$table = self::create_backup_table("users_log");
		
		$expire30 = time()-86400*30;
		$expire60 = time()-86400*60;
		
		$ids['days30'] = array(
			"utpressing",
			"poker",
			"support",
			"auksjon_kuler_no_bid",
			"auksjon_kuler_won",
			"forum_topic_move"
		);
		
		$ids['days30crew'] = array(
			"crewforum_emne",
			"crewforum_svar",
			"crewforuma_emne",
			"crewforuma_svar",
			"crewforumi_emne",
			"crewforumi_svar"
		);
		
		$ids['days60'] = array(
			"renter",
			"fengsel",
			"fengsel_dusor_return",
			"bankoverforing",
			"lotto",
			"bomberom_set",
			"etterlyst_deactivate"
		);
		
		foreach ($ids as $key => $value)
		{
			$v = array();
			foreach ($value as $row)
			{
				$v[] = gamelog::$items[$row];
			}
			$ids[$key] = implode(",", $v);
		}
		
		
		// slett crewhendelser eldre enn 30 dager
		ess::$b->db->query("
			INSERT INTO $table
			SELECT users_log.*
			FROM users_log
			WHERE type IN ({$ids['days30crew']})");
		
		// slett oppføringene som be flyttet over
		ess::$b->db->query("
			DELETE users_log t2
			FROM $table t1, users_log t2
			WHERE t1.id = t2.id");
		
		
		// alle hendelser til deaktiverte brukere fjernes hvis de er eldre enn 30 dager
		ess::$b->db->query("
			INSERT INTO $table
			SELECT users_log.*
			FROM users_log
				JOIN users_players ON up_id = ul_up_id
				JOIN users ON u_id = up_u_id
			WHERE u_access_level = 0");
		
		// slett oppføringene som be flyttet over
		ess::$b->db->query("
			DELETE users_log t2
			FROM $table t1, users_log t2
			WHERE t1.id = t2.id");
		
		
		// behandle for spillere
		ess::$b->db->query("
			INSERT INTO $table
			SELECT users_log.*
			FROM users_log
			WHERE
				(time < $expire30 AND type IN ({$ids['days30']}))
				OR
				(time < $expire60 AND type IN ({$ids['days60']}))");
		
		// slett oppføringene som be flyttet over
		ess::$b->db->query("
			DELETE users_log t2
			FROM $table t1, users_log t2
			WHERE t1.id = t2.id");
		
		
		// antall som ble arkivert
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num = mysql_result($result, 0);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num rader ble arkivert fra users_log");
		self::backup_table_export($table, true);
	}
	
	/**
	 * Behandle sessions tabellen
	 * Sletter sessions som ikke har vært aktive på 60 dager
	 */
	public static function handle_table_sessions()
	{
		$expire = time() - 86400*60;
		
		// opprett backup tabell
		$table = self::create_backup_table("sessions");
		
		// flytt over data
		ess::$b->db->query("
			INSERT INTO $table
			SELECT *
			FROM sessions
			WHERE ses_last_time < $expire");
		
		// slett det som ble flyttet over
		ess::$b->db->query("DELETE FROM sessions WHERE ses_last_time < $expire");
		
		// antall som ble arkivert
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num = mysql_result($result, 0);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num rader ble arkivert fra sessions");
		self::backup_table_export($table, true);
	}
	
	/**
	 * Behandle poker tabellen
	 * Sletter pokeroppføringer som er eldre enn 90 dager
	 */
	public static function handle_table_poker()
	{
		$expire = time() - 86400*90;
		
		// opprett backup tabell
		$table = self::create_backup_table("poker");
		
		// flytt over data
		ess::$b->db->query("
			INSERT INTO $table
			SELECT *
			FROM poker
			WHERE poker_time_start < $expire");
		
		// slett det som ble flyttet over
		ess::$b->db->query("DELETE FROM poker WHERE poker_time_start < $expire");
		
		// antall som ble arkivert
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num = mysql_result($result, 0);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num rader ble arkivert fra poker");
		self::backup_table_export($table, true);
	}
	
	/**
	 * Behandle lotto_vinnere tabellen
	 * Sletter oppføringer eldre enn 60 dager
	 */
	public static function handle_table_lotto_vinnere()
	{
		$expire = time() - 86400*60;
		
		// opprett backup tabell
		$table = self::create_backup_table("lotto_vinnere");
		
		// flytt over data
		ess::$b->db->query("
			INSERT INTO $table
			SELECT *
			FROM lotto_vinnere
			WHERE time < $expire");
		
		// slett det som ble flyttet over
		ess::$b->db->query("DELETE FROM lotto_vinnere WHERE time < $expire");
		
		// antall som ble arkivert
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num = mysql_result($result, 0);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num rader ble arkivert fra lotto_vinnere");
		self::backup_table_export($table, true);
	}
	
	/**
	 * Behandle ff_bank_transactions tabellen
	 * Sletter overføringer som er eldre enn 24 timer
	 */
	public static function handle_table_ff_bank_transactions()
	{
		$expire = time() - 86400;
		
		// opprett backup tabell
		$table = self::create_backup_table("ff_bank_transactions");
		
		// flytt over data
		ess::$b->db->query("
			INSERT INTO $table
			SELECT *
			FROM ff_bank_transactions
			WHERE ffbt_time < $expire");
		
		// slett det som ble flyttet over
		ess::$b->db->query("DELETE FROM ff_bank_transactions WHERE ffbt_time < $expire");
		
		// antall som ble arkivert
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num = mysql_result($result, 0);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num rader ble arkivert fra ff_bank_transactions");
		self::backup_table_export($table, true);
	}
	
	/**
	 * Behandle inbox_* tabellene
	 */
	public static function handle_tables_inbox()
	{
		// sett opp en oversikt over alle trådene som alle medlemmer har slettet og som ikke har vært aktivt på 30 dager
		ess::$b->db->query("CREATE TEMPORARY TABLE inbox_threads_temp (itt_it_id int(11) unsigned, primary key (itt_it_id))");
		
		$expire = time() - 86400 * 30;
		ess::$b->db->query("
			INSERT INTO inbox_threads_temp
			SELECT it_id FROM (
				SELECT it_id, MAX(IF(ir_restrict_im_time > $expire OR (ir_deleted = 0 AND u_id IS NOT NULL AND u_access_level != 0), 1, 0)) no_delete
				FROM
					inbox_threads
					LEFT JOIN inbox_rel ON ir_it_id = it_id
					LEFT JOIN users_players ON up_id = ir_up_id
					LEFT JOIN users ON u_id = up_u_id
				GROUP BY it_id
				HAVING no_delete = 0
			) ref");
		
		// ingenting å slette?
		$result = ess::$b->db->query("SELECT COUNT(*) FROM inbox_threads_temp");
		$num_threads = mysql_result($result, 0);
		if ($num_threads == 0)
		{
			// slett temp tabell
			ess::$b->db->query("DROP TEMPORARY TABLE inbox_threads_temp");
			
			return;
		}
		
		
		// sett opp liste over alt som blir slettet
		$result = ess::$b->db->query("SELECT itt_it_id FROM inbox_threads_temp");
		
		$date = ess::$b->date->get()->format("Ymd.His");
		$file = DBARCHIVE_DIR . "/inbox_threads_deleted_list.$date.txt";
		$data = "Oversikt over meldinger arkivert (it_id):";
		while ($row = mysql_fetch_assoc($result))
		{
			$data .= "\n{$row['itt_it_id']}";
		}
		
		// lagre
		if (!file_put_contents($file, $data))
		{
			throw new HSException("Kunne ikke lagre $file.");
		}
		
		
		// flytt over trådene
		$table = self::create_backup_table("inbox_threads");
		ess::$b->db->query("
			INSERT INTO $table
			SELECT inbox_threads.*
			FROM inbox_threads, inbox_threads_temp
			WHERE it_id = itt_it_id");
		
		// slett
		ess::$b->db->query("
			DELETE inbox_threads
			FROM inbox_threads, inbox_threads_temp
			WHERE it_id = itt_it_id");
		
		// eksporter
		self::backup_table_export($table, true);
		
		
		// flytt over relasjoner
		$table = self::create_backup_table("inbox_rel");
		ess::$b->db->query("
			INSERT INTO $table
			SELECT inbox_rel.*
			FROM inbox_rel, inbox_threads_temp
			WHERE ir_it_id = itt_it_id");
		
		// tell opp antall relasjoner
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num_rel = mysql_result($result, 0);
		
		// slett
		ess::$b->db->query("
			DELETE inbox_rel
			FROM inbox_rel, inbox_threads_temp
			WHERE ir_it_id = itt_it_id");
		
		// eksporter
		self::backup_table_export($table, true);
		
		
		// flytt over meldingsdata
		$table = self::create_backup_table("inbox_data");
		ess::$b->db->query("
			INSERT INTO $table
			SELECT inbox_data.*
			FROM inbox_data, inbox_messages, inbox_threads_temp
			WHERE im_it_id = itt_it_id AND im_id = id_im_id");
		
		// tell opp antall meldinger
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num_messages = mysql_result($result, 0);
		
		// slett
		ess::$b->db->query("
			DELETE inbox_data
			FROM inbox_data, inbox_messages, inbox_threads_temp
			WHERE im_it_id = itt_it_id AND im_id = id_im_id");
		
		// eksporter
		self::backup_table_export($table, true);
		
		
		// flytt over meldinger
		$table = self::create_backup_table("inbox_messages");
		ess::$b->db->query("
			INSERT INTO $table
			SELECT inbox_messages.*
			FROM inbox_messages, inbox_threads_temp
			WHERE im_it_id = itt_it_id");
		
		// slett
		ess::$b->db->query("
			DELETE inbox_messages
			FROM inbox_messages, inbox_threads_temp
			WHERE im_it_id = itt_it_id");
		
		// eksporter
		self::backup_table_export($table, true);
		
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num_threads meldingstråder, $num_messages meldinger og $num_rel meldingsrelasjoner ble arkivert fra innbokssystemet");
	}
	
	/**
	 * Behandle forumet
	 * Arkiverer tråder som ble slettet for mer enn 30 dager siden
	 * Ikke slett fra crewforum
	 */
	public static function handle_tables_forum()
	{
		$crewforum = "5,6,7";
		
		// finn trådene som må slettes
		$table_ft = self::create_backup_table("forum_topics");
		$expire = time() - 86400 * 30;
		ess::$b->db->query("
			INSERT INTO $table_ft
			SELECT forum_topics.*
			FROM forum_topics
				LEFT JOIN forum_sections ON fse_id = ft_fse_id
				LEFT JOIN ff ON ff_id = fse_ff_id
			WHERE
				((ft_deleted != 0 AND ft_deleted < $expire) OR (ff_id IS NOT NULL AND ff_inactive != 0 AND ff_inactive_time < $expire))
				AND ft_fse_id NOT IN ($crewforum)");
		
		// tell opp antall tråder
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table_ft");
		$num_ft = mysql_result($result, 0);
		
		// slett oppføringene som be flyttet over
		ess::$b->db->query("
			DELETE forum_topics t2
			FROM $table_ft t1, forum_topics t2
			WHERE t1.ft_id = t2.ft_id");
		
		// behandle svarene i trådene
		$table = self::create_backup_table("forum_replies");
		ess::$b->db->query("
			INSERT INTO $table
			SELECT forum_replies.*
			FROM forum_replies, $table_ft
			WHERE fr_ft_id = ft_id");
		
		// tell opp antall svar
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num_fr = mysql_result($result, 0);
		
		// slett oppføringene som ble flyttet over
		ess::$b->db->query("
			DELETE forum_replies
			FROM forum_replies, $table_ft
			WHERE fr_ft_id = ft_id");
		
		// eksporter svarene
		self::backup_table_export($table, true);
		
		
		// behandle forum_seen
		$table = self::create_backup_table("forum_seen");
		ess::$b->db->query("
			INSERT INTO $table
			SELECT forum_seen.*
			FROM forum_seen, $table_ft
			WHERE fs_ft_id = ft_id");
		
		// tell opp antall referanser
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num_fs = mysql_result($result, 0);
		
		// slett oppføringene som ble flyttet over
		ess::$b->db->query("
			DELETE forum_seen
			FROM forum_seen, $table_ft
			WHERE fs_ft_id = ft_id");
		
		// eksporter referansene
		self::backup_table_export($table, true);
		
		
		// eksporter forumtrådene
		self::backup_table_export($table_ft, true);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num_ft forumtråder, $num_fr forumsvar og $num_fs forum-sett-relasjoner ble arkivert fra forumet");
	}
	
	/**
	 * Arkiver alle medlemmer i et FF (utenom de som er markert som døde)
	 */
	public static function handle_ff_members($ff_id)
	{
		$ff_id = (int) $ff_id;
		$table = self::create_backup_table("ff_members");
		
		// flytt over medlemmene
		ess::$b->db->query("INSERT INTO $table SELECT * FROM ff_members WHERE ffm_ff_id = $ff_id");
		
		// tell opp antall
		$result = ess::$b->db->query("SELECT COUNT(*) FROM $table");
		$num = mysql_result($result, 0);
		
		// nullstill data for deaktiverte medlemmer
		essentials::load_module("ff");
		ess::$b->db->query("
			UPDATE ff_members
			SET ffm_donate = 0, ffm_params = NULL, ffm_forum_topics = 0, ffm_forum_replies = 0, ffm_earnings = 0, ffm_earnings_ff = 0, ffm_pay_points = NULL, ffm_log_new = 0
			WHERE ffm_ff_id = $ff_id AND ffm_status = ".ff_member::STATUS_DEACTIVATED);
		
		// slett oppføringene som ble flyttet over med unntak av deaktiverte spillere
		ess::$b->db->query("DELETE FROM ff_members WHERE ffm_ff_id = $ff_id AND ffm_status != ".ff_member::STATUS_DEACTIVATED);
		
		// eksporter data
		self::backup_table_export($table, true, true);
		
		putlog("NOTICE", "DATABASE ARKIVERING: $num oppføringer ble arkivert fra medlemsdatabasen til FF med ID #$ff_id");
	}
	
	/**
	 * Ta backup av FF
	 */
	public static function handle_ff($ff_id)
	{
		$ff_id = (int) $ff_id;
		$table = self::create_backup_table("ff");
		
		// kopier over data
		ess::$b->db->query("INSERT INTO $table SELECT * FROM ff WHERE ff_id = $ff_id");
		
		// eksporter data
		self::backup_table_export($table, true, true);
		
		putlog("NOTICE", "DATABASE ARKIVERING: FF-oppføringen med ID #$ff_id ble tatt backup av");
	}
	
	/**
	 * Ta backup av en hvilken som helst tabell
	 */
	public static function backup_table($table_org, $where = null)
	{
		$table = self::create_backup_table($table_org);
		$where = $where ? " WHERE $where" : "";
		
		// kopier over data
		ess::$b->db->query("INSERT INTO $table SELECT * FROM $table_org$where");
		
		// eksporter data
		self::backup_table_export($table, true, true);
		
		putlog("NOTICE", "DATABASE ARKIVERING: Tabellen {$table_org} ble tatt backup av".($where ? " (med kriterier)" : ""));
	}
}