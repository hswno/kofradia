<?php

set_time_limit(0);
require "../base/inc.innstillinger_pre.php";

if (MAIN_SERVER)
{
	global $__server;
	@header("Location: ".$__server['path']."/");
	die;
}

if ($_SERVER['REMOTE_ADDR'] != $_SERVER['SERVER_ADDR'])
{
	die("Kan kun kjøres lokalt.");
}

// kontroller mysql-program
$ret = shell_exec("mysql --version");
if (empty($ret)) die("Det ser ikke ut som mysql kommandoen er tilgjengelig på systemet.");

/** Egen exception type */
class HSException extends Exception {}

require "../base/extra/class.db_wrap.php";

// last inn databaseobjekt
$db = new db_wrap();

// koble til databasen
$db->connect(DBHOST, DBUSER, DBPASS);

// laste opp ny database?
if (isset($_FILES['sqlfile']))
{
	$src = $_FILES['sqlfile']['tmp_name'];
	if (!file_exists($src) || !is_uploaded_file($src))
	{
		die("Filen ble ikke korrekt lastet opp.");
	}
	
	// kontroller at dette er en MySQL dump
	$fh = fopen($src, "r");
	if (!$fh) die("Filen $src kunne ikke bli åpnet.");
	$first = fgets($fh, 1024);
	if (mb_strpos($first, "MySQL dump") === false)
	{
		die("Dette ser ikke ut som en 'MySQL dump'-fil");
	}
	
	// drop databasen
	$db->query("DROP DATABASE IF EXISTS ".DBNAME);
	
	// opprett ny database
	$db->query("CREATE DATABASE ".DBNAME." CHARSET=utf8 COLLATE=utf8_unicode_ci");
	
	// velg den nye databasen
	$db->set_database(DBNAME);
	
	// importer fil
	exec("mysql --user=".escapeshellarg(DBUSER)." --pass=".escapeshellarg(DBPASS)." ".escapeshellarg(DBNAME)." < ".escapeshellarg($src));
	
	die('Filen '.$src.' ble importert. <a href="replace_db">Tilbake</a> <a href="login">Logg inn</a>');
}

echo '
<h1>Ersatt database</h1>
<p><a href="./">Tilbake</a></p>
<form action="" method="post" enctype="multipart/form-data">
	<p><b>Advarsel:</b> Dette vil fjerne alt i databasen med navn <b>'.htmlspecialchars(DBNAME).'</b>.</p>
	<p><b>Velg MySQL-dump fil</b></p>
	<p><input type="file" name="sqlfile" /></p>
	<p><input type="submit" value="Last opp og erstatt databasen" /></p>
	<p>Merk at handlingen kan ta lang tid. <b>Ikke avbryt handlingen for å prøve på nytt.</b></p>
</form>';