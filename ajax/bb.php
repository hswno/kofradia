<?php

require "../base/ajax.php";

// mangler tekst?
if (!isset($_POST['text']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

global $__server;
ajax::essentials();

// logg
$name = login::$logged_in ? login::$user->player->data['up_name'] : '*ukjent spiller*';
$ref = isset($_SERVER['HTTP_REFERER']) ? ' - referer: ' . $_SERVER['HTTP_REFERER'] : ' - ingen referer';
putlog("LOG", "%c3%bMIN-STATUS:%b%c %u{$name}%u hentet HTML for BB-kode$ref");

// sett opp html
$bb = parse_html(game::bb_to_html($_POST['text']));

// send raw html?
if (isset($_POST['plain']))
{
	ajax::text($bb);
}


// send inni xml element
ajax::xml('<content>'.htmlspecialchars($bb).'</content>');