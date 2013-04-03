<?php

/**
 * Forhåndsvisning av forumsvar
 * 
 * Inndata:
 * - topic_id
 * - reply_id [optional]
 * - text
 */

require "../../base/ajax.php";
ajax::require_user();

// kontroller lås
ajax::validate_lock(true);

global $_base, $_game;

// mangler forumtråd id?
if (!isset($_POST['topic_id']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

$text = postval("text");
if (empty($text)) $text = "Mangler innhold.";

// forhåndsviser vi et redigert forumsvar?
if (isset($_POST['reply_id']))
{
	// hent forum modulen
	essentials::load_module("forum");
	
	// hent forumtråden og forumsvaret
	$topic = new forum_topic_ajax($_POST['topic_id']);
	$reply = $topic->get_reply($_POST['reply_id']);
	
	// fant ikke forumsvaret?
	if (!$reply)
	{
		ajax::text("ERROR:404-REPLY", ajax::TYPE_INVALID);
	}
	
	// sett opp data
	$data = $reply->extended_info();
	$data['fr_text'] = $text;
	$data['fr_last_edit'] = time();
	$data['fr_last_edit_up_id'] = login::$user->player->id;
}

// forhåndsviser nytt forumsvar (bruk egen brukerdata)
else
{
	// sett opp data
	$data = array(
		"ft_id" => (int) $_POST['topic_id'],
		"fr_text" => $text,
		"fr_up_id" => login::$user->player->id,
		"up_name" => login::$user->player->data['up_name'],
		"up_access_level" => login::$user->player->data['up_access_level'],
		"up_points" => login::$user->player->data['up_points'],
		"upr_rank_pos" => login::$user->player->data['upr_rank_pos'],
		"up_forum_signature" => login::$user->player->data['up_forum_signature'],
		"up_profile_image_url" => login::$user->player->data['up_profile_image_url'],
		"fs_new" => forum::$fs_check
	);
}

ajax::html(parse_html(forum::template_topic_reply_preview($data)));