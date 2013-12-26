<?php

/**
 * Hent raw data for et forumsvar
 * 
 * Inndata:
 * - sid
 * - topic_id
 * - reply_id
 */

require "../../../app/ajax.php";
ajax::validate_sid();

// kontroller lås
ajax::validate_lock(true);

// hent forumtråd
$topic = new \Kofradia\Forum\TopicAjax(postval("topic_id"));

// hent forumsvaret
$reply = $topic->get_reply(postval("reply_id"));

// fant ikke forumsvaret?
if (!$reply)
{
	ajax::text("ERROR:404-REPLY", ajax::TYPE_INVALID);
}

// krev tilgang til forumsvaret
$reply->require_access();

// send tilbake data
ajax::text($reply->info['fr_text']);