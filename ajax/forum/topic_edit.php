<?php

/**
 * Rediger forumtråd
 * 
 * Inndata:
 * - sid
 * - topic_id
 * - last_edit
 * - title
 * - text
 * - section [optional]
 * - type [optional forum mod]
 * - locked [optional forum mod]
 */

require "../../base/ajax.php";
ajax::validate_sid();

// kontroller lås
ajax::validate_lock(true);

global $_base, $_game;

// mangler forumtråd-id?
if (!isset($_POST['topic_id']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// mangler data?
if (!isset($_POST['title']) || !isset($_POST['text']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// mangler sist redigert?
if (!isset($_POST['last_edit']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// hent forumtråden
essentials::load_module("forum");
$topic = new forum_topic_ajax($_POST['topic_id']);

// kontroller at forumtråden ikke har blitt endret siden sist oppdatert
$last_edit = (int) $_POST['last_edit'];
if ($topic->info['ft_last_edit'] && $topic->info['ft_last_edit'] > $last_edit)
{
	ajax::text("ERROR:TOPIC-ALREADY-EDITED:{$topic->info['ft_last_edit']}", ajax::TYPE_INVALID);
}

// forsøk å utfør endringer
$type = isset($_POST['type']) && $topic->forum->fmod ? $_POST['type'] : NULL;
$locked = isset($_POST['locked']) && $topic->forum->fmod ? $_POST['locked'] : NULL;
$section = isset($_POST['section']) ? $_POST['section'] : NULL;
$topic->edit($_POST['title'], $_POST['text'], $section, $type, $locked);