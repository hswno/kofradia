<?php

define("FORCE_HTTPS", true);

require "base.php";

if (!LOCK)
{
	if (isset($_GET['orign'])) redirect::handle($_GET['orign'], redirect::SERVER);
	redirect::handle("");
}

new page_lock();