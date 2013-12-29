<?php

require "../../app/ajax.php";
ajax::require_user();
global $_base;

// mangler brukerid?
if (!isset($_POST['q']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

$q = $_POST['q'];

// limit
$limit = intval(min(100, max(1, postval("limit", 10))));

// ekskluder?
$exclude = "";
$exclude_ids = array();
if (isset($_POST['exclude']))
{
	$exclude_ids = array_unique(array_map("intval", explode(",", $_POST['exclude'])));
	if (count($exclude_ids) > 0)
	{
		$exclude = "up_id NOT IN (".implode(",", $exclude_ids).") AND ";
	}
}

// ignorere egne deaktiverte spillere?
if (isset($_POST['is'])) // is:ignore_self
{
	$exclude .= "up_u_id != ".login::$user->id." AND ";
}

// hent brukere
$q2 = \Kofradia\DB::quote(str_replace("_", "\\_", $q));
$result = \Kofradia\DB::get()->query("SELECT SQL_CALC_FOUND_ROWS up_id, up_name, up_access_level FROM users_players WHERE {$exclude}up_name LIKE $q2 ORDER BY LENGTH(up_name), up_name LIMIT $limit");
$result2 = \Kofradia\DB::get()->query("SELECT FOUND_ROWS()");
$num = $result2->fetchColumn(0);

// logg
putlog("LOG", "%c3%bFINN-SPILLER:%b%c %u".login::$user->player->data['up_name']."%u søkte etter %u{$q}%u!");

// xml
$data = '<userlist query="'.htmlspecialchars($q).'" limit="'.$limit.'" results="'.$num.'">';

while ($row = $result->fetch())
{
	$data .= '<user up_id="'.$row['up_id'].'" up_name="'.htmlspecialchars($row['up_name']).'">'.htmlspecialchars(game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'])).'</user>';
}


$data .= '</userlist>';
ajax::xml($data);