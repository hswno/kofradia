<?php

// TODO: må legge inn resten av strukturen fra gamle firma
echo '
Arkivering av FF er foreløpig deaktivert.';

return;

global $_base;

// hent de familiene som døde ut for mer enn 30 dager siden
$expire = time() - 86400*30;
$result = \Kofradia\DB::get()->query("SELECT ff_id, ff_name FROM ff WHERE ff_inactive != 0 AND ff_inactive_time IS NOT NULL AND ff_inactive_time < $expire");
$ff_ids = array();
$ff_names = array();
while ($row = $result->fetch())
{
	$ff_ids[] = $row['ff_id'];
	$ff_names[] = $row['ff_name']." (#{$row['ff_id']})";
}

// ingen ting som skal flyttes
if (count($ff_ids) == 0) return;
$list = "(".implode(",", $ff_ids).")";

\Kofradia\DB::get()->beginTransaction();

// deaktiver referansesjekk
\Kofradia\DB::get()->exec("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS */");
\Kofradia\DB::get()->exec("/*!40014 SET UNIQUE_CHECKS=0 */");
\Kofradia\DB::get()->exec("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS */");
\Kofradia\DB::get()->exec("/*!40014 SET FOREIGN_KEY_CHECKS=0 */");

// flytt all informasjon om familiene
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".ff SELECT * FROM ff WHERE ff_id IN $list");
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".ff_bank_log SELECT * FROM ff_bank_log WHERE ffbl_ff_id IN $list");
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".ff_log SELECT * FROM ff_log WHERE ffl_ff_id IN $list");
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".ff_members SELECT * FROM ff_members WHERE ffm_ff_id IN $list");
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".f_forum_log
	SELECT f_forum_log.* FROM f_forum_log JOIN f_forum_topics ON ffl_fft_id = fft_id
	WHERE fft_ff_id IN $list");
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".f_forum_replies
	SELECT f_forum_replies.* FROM f_forum_replies JOIN f_forum_topics ON ffr_fft_id = fft_id
	WHERE fft_ff_id IN $list");
\Kofradia\DB::get()->exec("INSERT INTO ".DBNAMEARCHIVE.".f_forum_topics
	SELECT f_forum_topics.* FROM f_forum_topics
	WHERE fft_ff_id IN $list");

// slett gammel informasjon
\Kofradia\DB::get()->exec("DELETE FROM ff WHERE ff_id IN $list");
\Kofradia\DB::get()->exec("DELETE FROM ff_bank_log WHERE ffbl_ff_id IN $list");
\Kofradia\DB::get()->exec("DELETE FROM ff_log WHERE ffl_ff_id IN $list");
\Kofradia\DB::get()->exec("DELETE FROM ff_members WHERE ffm_ff_id IN $list");
\Kofradia\DB::get()->exec("DELETE f_forum_log FROM f_forum_log JOIN f_forum_topics ON ffl_fft_id = fft_id WHERE fft_ff_id IN $list");
\Kofradia\DB::get()->exec("DELETE f_forum_replies FROM f_forum_replies JOIN f_forum_topics ON ffr_fft_id = fft_id WHERE fft_ff_id IN $list");
\Kofradia\DB::get()->exec("DELETE f_forum_topics FROM f_forum_topics WHERE fft_ff_id IN $list");

// aktiver refernsesjekk
\Kofradia\DB::get()->exec("/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */");
\Kofradia\DB::get()->exec("/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */");

\Kofradia\DB::get()->commit();

putlog("CREWCHAN", "%bArkivering:%b Informasjon om broderskapene ".implode(", ", $ff_names)." ble flyttet til arkivdatabasen.");