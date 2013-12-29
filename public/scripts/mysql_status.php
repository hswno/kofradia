<?php

require "../base.php";
access::need("crewet");

$global = isset($_POST['noglobal']) ? "" : " GLOBAL";
$result = \Kofradia\DB::get()->query("SHOW$global STATUS");
$result->debug();

//$avg = $variables['Questions'] / $variables['Uptime'];