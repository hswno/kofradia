<?php

require dirname(dirname(__FILE__))."/essentials.php";
$user = getenv("USER");
try {
	@putlog("CREWCHAN", "%bPRODUCTION PULL:%b %u$user%u oppdaterte koden mot master.");
} catch (Exception $e) {}
try {
	@putlog("INFO", "%bPRODUCTION PULL:%b %u$user%u oppdaterte koden mot master.");
} catch (Exception $e) {}
