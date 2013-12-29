<?php

define("ALLOW_GUEST", true);

require "base.php";
global $_base;

$_base->page->add_title("Spillere pålogget");

// spesielle verdier
$other = array(
	"today" => array(true, 0),
	"d1" => array(true, 86400),
	"d2" => array(true, 172800),
	"d3" => array(true, 259200),
	"24t" => array(false, 86400),
	"1u" => array(false, 604800)
);

// benytte spesiell vedi?
if (isset($_GET['t']) && array_key_exists($_GET['t'], $other))
{
	$elm = $other[$_GET['t']];
	
	// relativ fra nå eller absolutt fra 00:00
	if ($elm[0])
	{
		// absolutt verdi
		$time = date("H") * 3600 + date("i") * 60 + date("s") +  $elm[1];
	}
	else
	{
		// relativ verdi
		$time = $elm[1];
	}
}

// bruk standard eller tallverdi
else
{
	$time = isset($_GET['t']) ? intval($_GET['t']) : 0;
	
	// utenfor grensen på 10 sekunder og 1 time?
	if ($time < 10 || $time > 3600)
	{
		// sett tiden til 15 min
		$time = 900;
	}
}

echo '
<div class="bg1_c medium bg1_padding">
	<h1 class="bg1">Spillere pålogget<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<form action="" method="get">
			<div style="float: right; margin-top: 0.8em; margin-left: 10px">
				<select name="t" onchange="this.form.submit()">
					<option value="">Velg periode</option>
					<option value="30">Siste halvminuttet</option>
					<option value="60">Siste minuttet</option>
					<option value="300">Siste 5 minuttene</option>
					<option value="600">Siste 10 minuttene</option>
					<option value="900">Siste 15 minuttene</option>
					<option value="1200">Siste 20 minuttene</option>
					<option value="1500">Siste 25 minuttene</option>
					<option value="1800">Siste 30 minuttene</option>
					<option value="3600">Siste timen</option>
					<option value="today">I dag</option>
					<option value="d1">Siden i går</option>
					<option value="d2">Siden i forigårs</option>
					<option value="d3">Siden 3 dager</option>
					<option value="24t">Siste 24 timer</option>
					<option value="1u">Siste uken</option>
				</select>
				<noscript>
					<input type="submit" value="Go" />
				</noscript>
			</div>
			<p>Denne listen viser hvem som har vært aktive i løpet av siste '.game::timespan($time, game::TIME_FULL).'</p>
		</form>';

// hent brukerne
$result = \Kofradia\DB::get()->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE up_last_online >= ".(time()-$time)." ORDER BY up_name");

// sett opp alfabetisk liste
$liste = array();
while ($row = $result->fetch())
{
	$liste[mb_strtolower(mb_substr($row['up_name'], 0, 1))][] = game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']);
}

echo '
		<table class="table tablemb">
			<thead>
				<tr>
					<th colspan="2">Spillere pålogget - '.game::format_number($result->rowCount()).' spiller'.($result->rowCount() == 1 ? '' : 'e').'</th>
				</tr>
			</thead>
			<tbody>';

foreach ($liste as $char => $rows)
{
	echo '
				<tr>
					<th>'.htmlspecialchars($char).'</th>
					<td>'.implode(", ", $rows).'</td>
				</tr>';
}

echo '
			</tbody>
		</table>
	</div>
</div>';

$_base->page->load();