<?php

require "../essentials.php";

// skal vi eksportere utvidet informasjon (nostat info)
$extended = isset($argv[1]) && $argv[1] == "extended";

/**
 * @return array(felt det skal settes inn i, felt som skal hentes ut/select)
 */
function export_map(array $cols)
{
	$m0 = array();
	$m1 = array();
	
	foreach ($cols as $d0 => $d1)
	{
		if (is_int($d0))
		{
			$m0[] = $d1;
		}
		
		else
		{
			$m0[] = $d0;
		}
		
		$m1[] = $d1;
	}
	
	return array(implode(", ", $m0), implode(", ", $m1));
}

// navn på databasene vi skal opprette
$nc = array("smafia_database_export");

// navn på databasene vi skal hente data fra
$no = array("smafia_database");

// navn på filene som eksporteres
$export_names = array("main");

\Kofradia\DB::get()->exec("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0");

// opprett midlertidig database
echo "* Oppretter midlertidig database ".sentences_list($nc)."\n";
foreach ($nc as $name) {
	\Kofradia\DB::get()->exec("CREATE DATABASE $name DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");
}

// kopier over tabellstruktur fra tabellene
$tabeller = array(
	// smafia_database
	array(
        "achievements",
		"auksjoner",
		"auksjoner_bud",
		"autologin",
		"available_group",
		"available_items",
		"available_users",
		"available_votes",
		"ban_ip",
		"bank_log",
		"bullets",
		"bydeler",
		"bydeler_resources",
		"crewfiles_directories",
		"crewfiles_files",
		"crewfiles_revisions",
		"div_soknader",
		"donations",
		"drapforsok",
		"email_blacklist",
		"ff",
		"ff_members",
		"ff_free",
		"ff_bank_log",
		"ff_log",
		"ff_bank_transactions",
		"ff_newspapers",
		"ff_newspapers_articles",
		"ff_newspapers_payments",
		"ff_stats_daily",
		"ff_stats_pay",
		"ff_stats_pay_members",
		"forms",
		"forum_log",
		"forum_replies",
		"forum_sections",
		"forum_seen",
		"forum_topics",
		"github_log",
		"gta",
		"gta_options",
		"gta_options_status",
		"gta_options_text",
		"hall_of_fame",
		"henvendelser",
		"henvendelser_messages",
		"hitlist",
		"inbox_data",
		"inbox_messages",
		"inbox_rel",
		"inbox_threads",
        "julekalender",
        "julekalender_bidrag",
		"kriminalitet",
		"kriminalitet_status",
		"kriminalitet_text",
		"livefeed",
		"log_crew",
		"log_crew_actions",
		"log_crew_data",
		"log_crew_extra",
		"log_irc",
		"log_referers",
		"lotto",
		"lotto_vinnere",
		"mailer",
		"news",
		"nodes",
		"nodes_items",
		"nodes_items_rev",
		"oppdrag",
		"phinxlog",
		"poker",
		"polls",
		"polls_options",
		"polls_votes",
		"profile_images",
		"ranks",
		"ranks_pos",
		"rapportering",
		"regex_checks",
		"registration",
		"scheduler",
		"sessions",
		"settings",
		"sitestats",
		"soknader_applicants",
		"soknader_applicants_felt",
		"soknader_felt",
		"soknader_oversikt",
		"stats",
		"stats_daily",
		"stats_time",
		"stats_whatpulse",
		"support",
		"support_messages",
		"tasks",
		"users",
		"users_antibot",
		"users_antibot_validate",
		"users_ban",
		"users_contacts",
		"users_garage",
		"users_gta",
		"users_history",
		"users_history_previous",
		"users_hits",
		"users_log",
		"users_oppdrag",
		"users_players",
		"users_players_rank",
		"users_poker",
		"users_views",
		"utpressinger",
        "up_achievements"
	)
);

echo "\n* Oppretter tabeller:\n";
foreach ($tabeller as $x => $rows)
{
	foreach ($rows as $row)
	{
		echo "  Oppretter tabellen {$nc[$x]}.$row\n";
		\Kofradia\DB::get()->exec("CREATE TABLE {$nc[$x]}.$row LIKE {$no[$x]}.$row");
	}
}


// flytt over all data for spesifiserte tabeller
$alldata = array(
	// smafia_database
	array(
	    "achievements",
		"auksjoner",
		"auksjoner_bud",
		"bydeler",
		"bydeler_resources",
		"ff",
		"ff_free",
		"ff_newspapers",
		"ff_newspapers_articles",
		"ff_newspapers_payments",
		"forum_sections",
		"github_log",
		"gta",
		"gta_options",
		"gta_options_text",
		"hall_of_fame",
		"kriminalitet",
		"kriminalitet_text",
		"livefeed",
		"log_crew_actions",
		"log_crew_extra",
		"news",
		"nodes",
		"nodes_items",
		"nodes_items_rev",
		"oppdrag",
		"phinxlog",
		"ranks",
		"ranks_pos",
		"regex_checks",
		"scheduler",
		"settings",
		"sitestats",
		"soknader_felt",
		"soknader_oversikt",
		"stats_daily",
		#"stats_time",
		"tasks",
        "up_achievements"
	)
);

// nostat info
if ($extended)
{
	$alldata[0][] = "ff_members";
	$alldata[0][] = "ff_bank_log";
	$alldata[0][] = "ff_log";
	$alldata[0][] = "ff_stats_daily";
	$alldata[0][] = "ff_stats_pay";
	$alldata[0][] = "ff_stats_pay_members";
}

echo "\n* Kopierer data:\n";
foreach ($alldata as $x => $rows)
{
	foreach ($rows as $row)
	{
		echo "  Kopierer data fra {$no[$x]}.$row til {$nc[$x]}.$row..\n";
		\Kofradia\DB::get()->exec("INSERT INTO {$nc[$x]}.$row SELECT * FROM {$no[$x]}.$row");
	}
}


// hent kun noen kolonner fra enkelte tabeller

// users
echo "\n* Behandler data fra users..\n";
$map = export_map(array(
	"u_id",
	"u_pass" => "'dummy'",
	#"u_email" => "IF(u_access_level != 0 AND u_access_level != 1, u_email, 'dummy@hsw.no')",
	"u_email" => "'dummy@hsw.no'",
	"u_online_time",
	"u_created_time",
	"u_created_referer",
	"u_recruiter_u_id",
	"u_recruiter_points_last",
	"u_recruiter_points_now",
	"u_recruiter_points",
	"u_recruiter_points_bonus",
	"u_access_level",
	"u_access_level_up_id",
	"u_active_up_id",
	"u_hits",
	"u_hits_redirect",
	"u_tos_version",
	"u_tos_accepted_time",
	#"u_birth" => "IF(u_access_level != 0 AND u_access_level != 1, u_birth, NULL)",
	"u_birth" => "1989-01-01",
	"u_deactivated_reason" => "IF(u_deactivated_reason IS NULL, NULL, 'dummy')",
	"u_deactivated_time",
	"u_deactivated_up_id",
	"u_deactivated_note" => "IF(u_deactivated_note IS NULL, NULL, 'dummy')"
));
\Kofradia\DB::get()->exec("INSERT INTO {$nc[0]}.users
	({$map[0]})
	SELECT {$map[1]}
	FROM {$no[0]}.users");

// users_players
echo "\n* Behandler data fra users_players..\n";
$data = array(
	"up_id",
	"up_u_id",
	"up_name",
	"up_access_level",
	"up_created_time",
	"up_last_online",
	"up_recruiter_up_id",
	"up_b_id",
	"up_hits",
	"up_hits_redirect",
	"up_points",
	"up_points_rel",
	"up_points_recruited",
	"up_health",
	"up_health_max",
	"up_health_ff_time",
	"up_energy",
	"up_energy_max",
	"up_rank_pos",
	"up_profile_hits",
	"up_bank_ff_id",
	"up_bank_ff_time",
	"up_forum_num_topics",
	"up_forum_num_replies",
	"up_forum_ff_num_topics",
	"up_forum_ff_num_replies",
	"up_inbox_num_threads",
	"up_inbox_num_messages",
	"up_deactivated_time",
	"up_deactivated_up_id",
	"up_deactivated_reason" => "IF(up_deactivated_reason IS NULL, NULL, 'dummy')",
	"up_deactivated_note" => "IF(up_deactivated_note IS NULL, NULL, 'dummy')",
	"up_deactivated_points",
	"up_deactivated_rank_pos",
	"up_fengsel_num"
);

// nostat info
if ($extended)
{
	$data[] = "up_weapon_id";
	$data[] = "up_weapon_training";
	$data[] = "up_weapon_bullets";
	$data[] = "up_protection_id";
	$data[] = "up_protection_state";
	$data[] = "up_brom_ff_id";
	$data[] = "up_brom_expire";
	$data[] = "up_brom_up_id";
}

$map = export_map($data);
\Kofradia\DB::get()->exec("INSERT INTO {$nc[0]}.users_players
	({$map[0]})
	SELECT {$map[1]}
	FROM {$no[0]}.users_players");

// andre forandringer
echo "\n* Andre endringer..\n";
\Kofradia\DB::get()->exec("UPDATE {$nc[0]}.scheduler SET s_active = 0 WHERE s_name IN ('bank_renter_info', 'trac_rss', 'wordpress_entries')");

// korrigerer tasks
\Kofradia\DB::get()->exec("UPDATE {$nc[0]}.tasks SET t_ant = 0, t_last = 0");

// generer dummy-data
// TODO: forumet
// TODO: polls
// TODO: rapporteringer
// TODO: support

// dummy: donasjoner
$result = \Kofradia\DB::get()->query("SELECT up_id FROM {$no[0]}.users_players WHERE up_access_level != 0 AND up_last_online > ".(time()-86400*7)." ORDER BY RAND() LIMIT 15");
while ($row = $result->fetch())
{
	\Kofradia\DB::get()->exec("INSERT INTO {$nc[0]}.donations SET d_up_id = {$row['up_id']}, d_amount = ".rand(10,100).", d_time = ".(time()-rand(0, 604800)));
}

\Kofradia\DB::get()->exec("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS");

$files = array();
foreach ($export_names as $name) {
	$files[] = "export_to_devdb.".ess::$b->date->get()->format("Ymd-His").($extended ? '.extended' : '').".$name.sql";
}

// eksporter databasene
foreach ($nc as $key => $name) {
	echo "\n* Eksporterer databasen $name..\n";
	echo shell_exec("mysqldump --hex-blob --skip-extended-insert --skip-add-drop-table $name > {$files[$key]}");
	echo "  Data eksportert til {$files[$key]}\n";
}

// dropp databasene
echo "\n* Dropper databasene ".sentences_list($nc)."\n";
foreach ($nc as $name)
{
	\Kofradia\DB::get()->exec("DROP DATABASE $name");
}

echo "\nFullført.\n";
