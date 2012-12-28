<?php

/**
 * Annonser forumsvar
 * 
 * Inndata:
 * - sid
 * - topic_id
 * - reply_id
 */

require "../../base/ajax.php";
ajax::validate_sid();

// kontroller l�s
ajax::validate_lock(true);

// hent forumtr�d
essentials::load_module("forum");
$topic = new forum_topic_ajax(postval("topic_id"));

// hent forumsvaret
$reply = $topic->get_reply(postval("reply_id"));

// fant ikke forumsvaret?
if (!$reply)
{
	ajax::text("ERROR:404-REPLY", ajax::TYPE_INVALID);
}

// ikke et gyldig forum hvor vi kan annonsere forumsvar p� nytt?
if ($topic->forum->id < 5 || $topic->forum->id > 7)
{
	ajax::text("ERROR:INVALID-ANNOUNCE-FORUM", ajax::TYPE_INVALID);
}

// annonser svaret
$reply->announce();

ajax::text("Forumsvaret ble annonsert p� nytt. Du ser ogs� denne oppf�ringen i hendelser.");