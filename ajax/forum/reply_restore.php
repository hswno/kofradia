<?php

/**
 * Gjenopprett forumsvar
 * 
 * Inndata:
 * - sid
 * - topic_id
 * - reply_id
 */

require "../../base/ajax.php";
ajax::validate_sid();

// kontroller lås
ajax::validate_lock(true);

// hent forumtråd
essentials::load_module("forum");
$topic = new forum_topic_ajax(postval("topic_id"));

// hent forumsvaret
$reply = $topic->get_reply(postval("reply_id"));

// fant ikke forumsvaret?
if (!$reply)
{
	ajax::text("ERROR:404-REPLY", ajax::TYPE_INVALID);
}

// forsøk å gjenopprette
$reply->restore();