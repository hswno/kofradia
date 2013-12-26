<?php

if (defined("SCRIPT_START")) return;
require "../base.php";
global $_base;

access::no_guest();
access::need("crewet");

$_base->page->add_title("Crew");