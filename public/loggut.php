<?php

require "base.php";
global $_base;

// kontroller link
if (getval("sid") != login::$info['ses_id'])
{
	$_base->page->add_message("Ugyldig inntasting. Prøv på nytt.", "error");
	$_base->page->load();
}

// loggmelding
putlog("NOTICE", "%c5%bLOGG UT%b%c: (%u{$_SERVER['REMOTE_ADDR']}%u) %u".login::$user->player->data['up_name']."%u (".login::$user->data['u_email'].") ({$_SERVER['HTTP_USER_AGENT']})");

// logg ut
login::logout();
redirect::handle("");