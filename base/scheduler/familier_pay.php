<?php

global $_base;

/*
 * Dette scriptet tar ut betalinger fra familier som må betale for å overleve
 * Se "Holde familien oppe" i familiedokumentet
 * 
 * Familier med Crewstatus slipper å betale
 */

/*
 * Dette scriptet skal kjøres hver dag kl. 12.00
 * Scriptet skal ikke kjøres før kl. 12.00
 */

// hent oversikt over familier som skulle ha betalt nå
$time = time();
$result = $_base->db->query("
	SELECT ff_id
	FROM ff
	WHERE ff_inactive = 0 AND ff_is_crew = 0 AND ff_pay_status = 1 AND ff_pay_next IS NOT NULL AND ff_pay_next <= $time");
while ($row = mysql_fetch_assoc($result))
{
	$familie = ff::get_ff($row['ff_id'], ff::LOAD_SCRIPT);
	
	putlog("CREWCHAN", "Broderskapet %u{$familie->data['ff_name']}%u har ikke betalt inn broderskapkostnad og blir nå lagt ned.");
	
	// legg ned familien
	$familie->dies();
}

// hent ut de familiene som skal trekkes for familiekostnad automatisk
$result = $_base->db->query("
	SELECT ff_id
	FROM ff
	WHERE ff_inactive = 0 AND ff_is_crew = 0 AND ff_pay_status = 0 AND ff_pay_next IS NOT NULL AND ff_pay_next <= $time");
while ($row = mysql_fetch_assoc($result))
{
	$familie = ff::get_ff($row['ff_id'], ff::LOAD_SCRIPT);
	
	// forsøk å trekk fra familiekostnaden
	$familie->pay_scheduler();
}