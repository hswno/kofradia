<?php

if (isset($_GET['show_source']))
{
	global $php_source_key;
	if (isset($php_source_key))
	{
		if ($php_source_key != $_GET['show_source']) die("Ugyldig nøkkel!");
	}
	
	$fh = fopen($_SERVER['SCRIPT_FILENAME'], "r");
	if (!$fh)
	{
		die("Kunne ikke åpne {$_SERVER['SCRIPT_FILENAME']}!");
	}
	
	$data = fread($fh, filesize($_SERVER['SCRIPT_FILENAME']));
	
	die('<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>Kildekode</title>
<style>
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
-->
</style>
</head>
<body>
<h1>Kildekode</h1>
<p>Kildekode for <b>'.$_SERVER['SCRIPT_FILENAME'].'</b>:<br />
<br />
'.highlight_string($data, true).'</p>
<p class="hsws"><a href="http://hsw.no/">hsw.no</a></p>
</body>
</html>');
}