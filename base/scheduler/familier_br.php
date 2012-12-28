<?php

global $_base;

// hent de familiene som skal dø ut
$expire = time();
$result = $_base->db->query("SELECT fff_id, ff_id FROM ff_free LEFT JOIN ff ON ff_fff_id = fff_id AND ff_inactive = 0 AND ff_is_crew = 0 AND ff_br_id IS NULL WHERE fff_active = 2 AND fff_time_expire_br <= $expire");

$handled = array();
while ($row = mysql_fetch_assoc($result))
{
	// ikke behandlet?
	if (!in_array($row['fff_id'], $handled))
	{
		// sett som behandlet
		$_base->db->query("UPDATE ff_free SET fff_active = 0 WHERE fff_id = {$row['fff_id']}");
		$handled[] = $row['fff_id'];
	}
	
	// familie?
	if ($row['ff_id'])
	{
		// legg ned familien
		$familie = ff::get_ff($row['ff_id'], ff::LOAD_SCRIPT);
		if (!$familie) continue;
		
		putlog("CREWCHAN", "Broderskapet %u{$familie->data['ff_name']}%u har ikke valgt bygning innen fristen.");
		$familie->dies();
	}
}

// sett scheduler til neste familie som skal dø ut hvis ikke valgt bygning
$scheduler_skip_next = true;
$_base->db->query("
	UPDATE scheduler, (
		SELECT MIN(fff_time_expire_br) fff_time, COUNT(fff_id) fff_count FROM ff_free WHERE fff_active = 2
	) ref
	SET s_next = IF(fff_count > 0, fff_time, s_next), s_active = IF(fff_count > 0, 1, 0)
	WHERE s_name = 'familier_br'");