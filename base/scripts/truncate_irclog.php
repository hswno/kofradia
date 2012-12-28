<?php

require "../essentials.php";

ess::$b->db->query("TRUNCATE log_irc");

echo "IRC logs truncated.";