<?php

require "../../app/essentials.php";
global $_base, $__server;

// vise for noen bestemte forum?
$ids = array(1,2,3,4);
if (isset($_GET['forum']))
{
	$show = explode(",", $_GET['forum']);
	foreach ($show as $id)
	{
		if (!in_array($id, $ids))
		{
			die("Ugyldig forum: $id");
		}
	}
	
	$ids = array_map("intval", $show);
}

$forums = array(
	1 => "Game",
	2 => "Off-topic",
	3 => "Salg/søknad",
	4 => "Support"
);

$forums_active = array();
foreach ($ids as $id) $forums_active[] = $forums[$id];

// sett opp beskrivelse
$desc = array_map("strtolower", $forums_active);
$last = count($desc) > 1 ? array_pop($desc) : false;
$description = "Forumsvar i ".implode(", ", $desc).($last ? " og $last" : '').' forum'.($last ? 'ene' : 'et').'.';

// sett opp RSS
$rss = new rss("Forumsvar - Kofradia", "https://www.kofradia.no/", $description);
$rss->ttl(1);

// hent forumsvarene
$result = \Kofradia\DB::get()->query("
	SELECT fr_id, fr_text, fr_time, up_name, ft_id, ft_type, ft_title, ft_fse_id
	FROM forum_replies r
		LEFT JOIN users_players ON up_id = fr_up_id,
		forum_topics t
	WHERE fr_ft_id = ft_id AND ft_fse_id IN (".implode(",", $ids).") AND ft_deleted = 0 AND fr_deleted = 0
	ORDER BY fr_time DESC LIMIT 50");

while ($row = $result->fetch())
{
	$item = new rss_item();
	$item->title($row['up_name'].' svarte i &laquo;'.htmlspecialchars($row['ft_title']).'&raquo;'.($row['ft_type'] == 2 ? ' (sticky)' : ($row['ft_type'] == 3 ? ' (viktig)' :'')).' ('.$forums[$row['ft_fse_id']].' forum)');
	$item->description(parse_html(game::bb_to_html($row['fr_text'])));
	$item->author($row['up_name']);
	$item->pubDate($row['fr_time']);
	$item->link($__server['path'].'/forum/topic?id='.$row['ft_id'].'&replyid='.$row['fr_id']);
	$item->guid("fr{$row['fr_id']}", false);
	$rss->item($item);
}

header("Content-Type: application/rss+xml; charset=utf-8");
echo $rss->generate();
