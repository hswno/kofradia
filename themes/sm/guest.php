<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

global $class_browser;

require "include_top.php";
ess::$b->page->add_head('<link href="/themes/sm/guest.css?'.@filemtime(dirname(__FILE__)."/guest.css").'" rel="stylesheet" type="text/css" />');

require "helpers.php";
$helper = new theme_helper("guest");

echo $helper->draw_guest();