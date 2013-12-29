<?php

require "../base.php";
global $_base;

$global = isset($_POST['noglobal']) ? "" : " GLOBAL";
$result = \Kofradia\DB::get()->query("SHOW$global STATUS");
$result->debug();

//$avg = $variables['Questions'] / $variables['Uptime'];