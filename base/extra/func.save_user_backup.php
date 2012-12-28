<?php

function save_user_backup()
{
	global $_base;
	
	static $i = 0;
	$i++;
	
	$date = date("Ymd_His");
	$url = GAMELOG_DIR . "/info_db_".$date."_".$i.".txt";
	$result = $_base->db->query("SELECT up_id, up_name, up_points, up_bank, up_cash, up_last_online, up_hits, up_interest_last FROM users_players");
	if ($fh = fopen($url, "w"))
	{
		$fields = array();
		while ($field = mysql_fetch_field($result)) {
			$fields[] = $field->name;
		}
		mysql_data_seek($result, 0);
		
		fwrite($fh, "column information:\n".implode(",", $fields));

		while ($row = mysql_fetch_assoc($result))
		{
			fwrite($fh, "\n".implode(",", $row));
		}
		fclose($fh);
	}
	else
	{
		echo "error writing to $url\r\n";
	}
	
	return $url;
}