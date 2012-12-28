<?php

global $files;

// kjør session for å unngå feil når player::get lastes
session_start();

// eksporter databasen med exportscriptet
require "export_to_devdb.php";

// initialiser crewfiles systemet med SYSTEM-brukeren
crewfiles::init(player::get(SYSTEM_USER_ID), true);

// hvilke filer i crewfiles som skal oppdateres
$data = array(
	array(190, $files[0], "main")#,
	#array(191, $name_1, "archive")
);

// oppdaterer filene i crewfiles
foreach ($data as $r)
{
	// hent filobjekt i crewfiles
	$file = crewfiles::get_file($r[0]);
	if (!$file)
	{
		echo "Fant ikke filen med ID {$r[0]}\n";
		continue;
	}
	
	echo "Kjører {$r[1]} gjennom gzip.\n";
	
	shell_exec("gzip -9 ".escapeshellarg($r[1]));
	$r[1] .= ".gz";
	
	echo "Laster opp {$r[1]} til crewfiles...\n";
	
	$revision = $file->upload($r[1], "Automatisk eksportert", "application/x-gzip-compressed", $r[1], true);
	
	$path = ess::$s['spath'].'/crewstuff/f/rev/'.$revision->id.'-'.urlencode(crewfiles::generate_tagname($revision->info['cfr_title']));
	putlog("CREWCHAN", "Dev-database {$r[2]}: $path");
	
	echo "Lastet opp!\n";
	
	// slett filen
	unlink($r[1]);
}
