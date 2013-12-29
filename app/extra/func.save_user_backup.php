<?php

function save_user_backup()
{
	global $_base;
	
	static $i = 0;
	$i++;
	
	$date = date("Ymd_His");
	$url = GAMELOG_DIR . "/info_db_".$date."_".$i.".txt";
	$result = \Kofradia\DB::get()->query("SELECT up_id, up_name, up_points, up_bank, up_cash, up_last_online, up_hits, up_interest_last FROM users_players");
	if ($fh = fopen($url, "w"))
	{
		$row = $result->fetch();
		
		$fields = array_keys($row);
		
		fwrite($fh, "column information:\n".implode(",", $fields));

		do
		{
			fwrite($fh, "\n".implode(",", $row));
		} while ($row = $result->fetch());
		fclose($fh);
	}
	else
	{
		echo "error writing to $url\r\n";
	}
	
	return $url;
}