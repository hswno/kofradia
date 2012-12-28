<?php

global $_base;

// innstillinger
$time_low = 20;
$time_high = 180;

$points_low = 0.1;
$points_high = 0.17;

$strength_low = 50;
$strength_high = 300;

$cash_low = 100;
$cash_high = 8000;

// hvor mye som skal legges til random
$time_add = $time_high - $time_low;
$points_add = $points_high - $points_low;
$strength_add = $strength_high - $strength_low;
$cash_add = $cash_high - $cash_low;

// sett random verdier - start transaksjon
$result = $_base->db->query("BEGIN");

// varighet
$_base->db->query("UPDATE kriminalitet SET wait_time = ROUND(RAND()*$time_add+$time_low)");

// poeng
$_base->db->query("UPDATE kriminalitet SET points = ROUND(wait_time*(RAND()*$points_add+$points_low))");

// strength
$_base->db->query("UPDATE kriminalitet SET max_strength = ROUND(RAND()*$strength_add+$strength_low)");

// penger
$_base->db->query("UPDATE kriminalitet SET cash_min = ROUND(RAND()*$cash_add+$cash_low)");
$_base->db->query("UPDATE kriminalitet SET cash_max = ROUND(RAND()*($cash_high-cash_min)+cash_min)");

$_base->db->query("COMMIT");