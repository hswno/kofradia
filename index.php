<?php

define("OPTIONAL_HTTPS", true);
define("ALLOW_GUEST", true);
require "base.php";

// logge inn?
// tar seg også av eventuell nødvendig reauth ved ukjent IP
if (!login::$logged_in)
{
	new page_logginn();
	die;
}

// videresende?
if (isset($_GET['orign']))
{
	redirect::handle($_GET['orign'], redirect::SERVER, login::$info['ses_secure']);
}

new page_forsiden(login::$user->player);