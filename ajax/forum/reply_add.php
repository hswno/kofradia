<?php

/**
 * Legg til nytt forumsvar
 * 
 * Inndata:
 * - sid
 * - topic_id
 * - text
 */

require "../../base/ajax.php";
ajax::validate_sid();

// kontroller lås
ajax::validate_lock(true);

global $_base, $_game;

// mangler forum id?
if (!isset($_POST['topic_id']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// hent forumtråd
essentials::load_module("forum");
$topic = new forum_topic_ajax($_POST['topic_id']);

// forsøk å legg til forumsvaret
$topic->add_reply(postval("text"), postval("no_concatenate") == "1", postval("announce") == "1");