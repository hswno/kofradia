<?php

define("ALLOW_GUEST", true);

require "base.php";
ess::$b->page->theme_file = "node";

page_node::main();