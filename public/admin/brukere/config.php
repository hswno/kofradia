<?php

if (!defined("SCRIPT_START"))
{
	require "../../base.php";
	return;
}

global $_base;

access::no_guest();
access::need("mod");
$_base->page->add_title("Administrasjon", "Brukere");