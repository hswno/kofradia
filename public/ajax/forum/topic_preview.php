<?php

/**
 * Forhåndsvisning av forumtråd
 * 
 * Inndata:
 * - topic_id [optional]
 * - text
 */

require "../../../app/ajax.php";
ajax::require_user();

// kontroller lås
ajax::validate_lock(true);

global $_base, $_game;

// sett opp tekst
$text = postval("text");
if (empty($text)) $text = "Mangler innhold.";

// forhåndsviser vi en redigert forumtråd?
if (isset($_POST['topic_id']))
{
	// hent forumtråden
	$topic = new \Kofradia\Forum\TopicAjax($_POST['topic_id']);
	
	// sett opp data
	$data = $topic->extended_info();
	$data['ft_text'] = $text;
	$data['ft_last_edit'] = time();
	$data['ft_last_edit_up_id'] = login::$user->player->id;
}

// forhåndsviser ny forumtråd (bruk egen brukerdata)
else
{
	// sett opp data
	$data = array(
		"ft_text" => $text
	);
}

ajax::html(parse_html(\Kofradia\Forum\Category::template_topic_preview($data)));