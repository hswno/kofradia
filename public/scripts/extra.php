<?php

require "../../app/inc.innstillinger_pre.php";
session_start();

/*if (isset($_POST['use_1'])) { $_SESSION[$GLOBALS['__server']['session_prefix'].'use_test_db'] = true; header("Location: extra"); }
elseif (isset($_POST['use_2'])) { unset($_SESSION[$GLOBALS['__server']['session_prefix'].'use_test_db']); header("Location: extra"); }*/

if (isset($_POST['show_queries_info1']))
{
	setcookie("show_queries_info", 1, NULL, "/");
	header("Location: extra");
}
elseif (isset($_POST['show_queries_info2']))
{
	setcookie("show_queries_info", false, NULL, "/");
	header("Location: extra");
}

if (isset($_POST['hide_online1'])) { unset($_SESSION[$GLOBALS['__server']['session_prefix'].'hide_online']); header("Location: extra"); }
elseif (isset($_POST['hide_online2'])) { $_SESSION[$GLOBALS['__server']['session_prefix'].'hide_online'] = true; header("Location: extra"); }

echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Extra</title>
</head>
<body>';

/*
echo '
<p>
	Test DB<br />
	<br />
	Status: <b>'.(isset($_SESSION[$GLOBALS['__server']['session_prefix'].'use_test_db']) ? 'Aktivert' : 'Deaktivert').'</b>
</p>
<form action="" method="post">
	<p>
		<input type="submit" name="use_1" value="Aktiver" />
		<input type="submit" name="use_2" value="Deaktiver" />
	</p>
</form>
<hr />';
*/

echo '
<p>
	Queries info<br />
	<br />
	Status: <b>'.(isset($_COOKIE['show_queries_info']) ? 'Aktivert' : 'Deaktivert').'</b>
</p>
<form action="" method="post">
	<p>
		<input type="submit" name="show_queries_info1" value="Aktiver" />
		<input type="submit" name="show_queries_info2" value="Deaktiver" />
	</p>
</form>
<hr />

<p>
	Online info<br />
	<br />
	Status: <b>'.(!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'hide_online']) ? 'Aktivert' : 'Deaktivert').'</b>
</p>
<form action="" method="post">
	<p>
		<input type="submit" name="hide_online1" value="Aktiver" />
		<input type="submit" name="hide_online2" value="Deaktiver" />
	</p>
</form>

<hr />

<form action="ranklist" method="get">
	<p>
		<input type="submit" value="Oppdater ranklista" />
	</p>
</form>

<hr />

<form action="user_backup" method="get">
	<p>
		<input type="submit" value="User backup" />
	</p>
</form>

<hr />

<form action="../" method="get">
	<p>
		<input type="submit" value="Forsiden" />
	</p>
</form>

<hr />

<form action="../statistikk" method="get">
	<p>
		<input type="submit" value="Statistikk" />
	</p>
</form>

</body>
</html>';