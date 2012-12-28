<?php

/**
 * Rutiner
 * Dette scriptet kalles av cron og utfører rutinesjekk
 */

if (!defined("SCRIPT_START"))
{
	require dirname(dirname(__FILE__))."/essentials.php";
	
	// hindre scriptet i å kjøre to ganger
	if (defined("SCHEDULER")) die();
}

define("SCHEDULER", true);

// kjør rutiner (autoload klassen)
ess::$b->scheduler = new scheduler();