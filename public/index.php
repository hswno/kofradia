<?php

define("ALLOW_GUEST", true);

// the controller will handle https
define("OPTIONAL_HTTPS", true);

require "base.php";
essentials::handle_route();