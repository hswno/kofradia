<?php

/**
 * Rediger forumsvar
 * 
 * Inndata:
 * - sid
 * - reply_id
 * - last_edit
 * - text
 */

require "../../base/ajax.php";
ajax::validate_sid();

// kontroller lås
ajax::validate_lock(true);

global $_base, $_game;

// mangler forumsvar-id?
if (!isset($_POST['reply_id']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// mangler nytt innhold?
if (!isset($_POST['text']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// mangler sist redigert?
if (!isset($_POST['last_edit']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// hent forumsvaret
$reply = new \Kofradia\Forum\ReplyAjax($_POST['reply_id']);

// fant ikke forumsvaret?
if (!$reply->info)
{
	ajax::text("ERROR:404-REPLY", ajax::TYPE_INVALID);
}

// hent forumtråden til forumsvaret
$reply->get_topic();

// kontroller at svaret ikke har blitt endret siden sist oppdatert
$last_edit = (int) $_POST['last_edit'];
if ($reply->info['fr_last_edit'] && $reply->info['fr_last_edit'] > $last_edit)
{
	ajax::text("ERROR:REPLY-ALREADY-EDITED:{$reply->info['fr_last_edit']}", ajax::TYPE_INVALID);
}

// forsøk å utfør endringer
$reply->edit($_POST['text']);