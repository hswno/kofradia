<?php

define("FORCE_HTTPS", true);
require "base.php";

if (isset($_GET['up_id']) && isset($_GET['u_id']))
{
	ess::$b->page->add_message("Ukjent forespørsel.", "error");
	ess::$b->page->load();
}

page_min_side::main();