<?php

/**
 * Rutiner
 * Dette scriptet kjøres manuelt og utfører rutiner kontinuerlig uten behov for cron
 */

if (!defined("SCRIPT_START"))
{
	require dirname(dirname(__FILE__))."/essentials.php";
	
	// hindre scriptet i å kjøre to ganger
	if (defined("SCHEDULER")) die();
}

set_time_limit(0);

define("SCHEDULER", true);
define("SCHEDULER_REPEATING", true);
sess_start();

echo "Utfører rutine regelmessig.\n";

// kjør rutiner (autoload klassen)
ess::$b->scheduler = new scheduler();

// utfør rutiner regelmessig
while (true)
{
	// finn ut når neste rutine skal utføres
	$result = ess::$b->db->query("
		SELECT GREATEST(s_next, s_expire) next
		FROM scheduler
		WHERE s_active = 1
		ORDER BY next
		LIMIT 1");
	$row = mysql_fetch_assoc($result);
	$next = false;
	if ($row)
	{
		$next = $row['next'];
	}
	
	$t = time();
	$s = ess::$b->date->get($t)->format("s");
	$max = $t + 60 - $s;
	
	if (!$next || $next > $max) $next = $max;
	
	printf("Neste: %s\n", ess::$b->date->get($next)->format(date::FORMAT_SEC));
	
	// sov
	$sleep = max(0.1, $next - microtime(true));
	putlog("LOG", sprintf("Venter %.2f sekunder til neste.\n", $sleep));
	usleep($sleep * 1000000);
	
	ess::$b->scheduler->__construct();
}