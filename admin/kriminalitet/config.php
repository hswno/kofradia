<?php

require "../config.php";
global $_base, $time_low, $time_high, $points_low, $points_high, $strength_low, $strength_high, $cash_low, $cash_high;

$_base->page->add_title("Kriminalitet");

// standardverdier for kriminalitet
$time_low = 20;
$time_high = 180;
$points_low = 0.1;
$points_high = 0.17;
$strength_low = 50;
$strength_high = 300;
$cash_low = 100;
$cash_high = 8000;