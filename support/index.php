<?php

define("FORCE_HTTPS", true);
if (isset($_POST['load_status']))
{
	// ajax - hente status
	require "../base/ajax.php";
	ajax::essentials();
	ajax::require_user();
	support::init();
	die;
}

require "../base.php";
support::init();