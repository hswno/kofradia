<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

// hent stats
$result = \Kofradia\DB::get()->query("SELECT name, extra, value, time FROM sitestats");
$sitestats = array();
$max = 0;

while ($row = $result->fetch())
{
	$sitestats[$row['name']][$row['extra']] = (int) $row['value'];
	$max = max($max, $row['value']);
}

$ofc = new OFC();
$c = new OFC_Colours();
$ofc->title(new OFC_Title("Rekord for antall pålogget"));
$ofc->tooltip()->title("font-size: 13px;font-weight:bold");

$info = array(
	"max_online_900" => "15 minutter",
	"max_online_300" => "5 minutter",
	"max_online_60" => "1 minutt",
	"max_online_30" => "30 sekunder"
);

foreach ($info as $key => $title)
{
	$bar = new OFC_Charts_Area();
	$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("Rekord for #x_label#:<br>#val# i løpet av $title");
	$bar->text($title);
	$bar->values(array_values($sitestats[$key]));
	$bar->colour($c->pick());
	$ofc->add_element($bar);
}

$x_label = array();
foreach (array_keys($sitestats['max_online_60']) as $val)
{
	$x_label[] = "$val:00 - ".($val+1).":00";
}

$ofc->axis_x()->label()->rotate(340)->labels($x_label)->steps(2);
$ofc->axis_y()->set_numbers(0, $max);

$ofc->dark_colors();
$ofc->dump();