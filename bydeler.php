<?php

define("ALLOW_GUEST", true);

require "base.php";
new page_bydeler(login::$logged_in ? login::$user->player : null);