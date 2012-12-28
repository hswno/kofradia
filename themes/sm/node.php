<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

require "include_top.php";
ess::$b->page->add_head('<link href="/themes/sm/guest.css?'.@filemtime(dirname(__FILE__)."/guest.css").'" rel="stylesheet" type="text/css" />');
$_base->page->add_head('<link href="/themes/sm/node.css?'.@filemtime(dirname(__FILE__)."/node.css").'" rel="stylesheet" type="text/css" />');

require "helpers.php";
$helper = new theme_helper("node");

echo $helper->draw_guest('
	<div class="node_wrap">'.ess::$b->page->content.'
	</div>');