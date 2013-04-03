<?php

// hente bilde?
if (isset($_GET['a']))
{
	if (mb_strlen($_GET['a']) < 2) die("Mangler params.");
	
	require "../base/inc.innstillinger_pre.php";
	session_start();
	
	$aid = (int) mb_substr($_GET['a'], 0, -1);
	$num = (int) mb_substr($_GET['a'], -1);
	
	// mangler?
	if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'data']['antibot'][$aid][$num]))
	{
		#putlog("ABUSE", "%bUGYLDIG BILDE:%b %u".login::$user->player->data['up_name']."%u forsøkte å vise et anti-bot bilde som ikke fantes: {$_SERVER['REQUEST_URI']}");
		die("Mangler.");
	}
	
	// vis bildet
	$img = $_SESSION[$GLOBALS['__server']['session_prefix'].'data']['antibot'][$aid][$num];
	
	// header
	header("Content-Type: image/jpeg");
	header("Content-Length: ".mb_strlen($img));
	
	header("Expires: Mon, 18 Jul 2005 00:00:00 GMT");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
	
	// HTTP/1.1
	header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
	
	// HTTP/1.0
	header("Pragma: no-cache");
	
	// vis bildet..
	echo $img;
	die;
}

require "../base.php";
new page_antibot();