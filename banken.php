<?php

define("FORCE_HTTPS", true);

require "base.php";
new page_banken(login::$user->player);