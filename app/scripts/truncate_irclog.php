<?php

require "../essentials.php";

\Kofradia\DB::get()->exec("TRUNCATE log_irc");

echo "IRC logs truncated.";