<?php

require "base.php";

if (isset($_GET['data_file']))
{
	OFC::embed("stats", $_GET['data_file'], "100%", 400);
}

echo '
<h1>Stats</h1>
<form action="" method="get">
	<p>
		<select name="data_file" onchange="this.form.submit()">
			<option>Velg..</option>
			<option value="graphs/users">Antall nye og døde brukere siste periode</option>
			<option value="graphs/record_online">Rekord for antall pålogget</option>
			<option value="graphs/hits">Sidevisninger siste periode</option>
			<option value="graphs/rank">Rankaktivitet siste periode</option>
			<option value="graphs/rank_avg">Gjennomsnittlig rankaktivitet per bruker siste periode</option>
		</select>
	</p>
</form>
<p><span id="stats"></span></p>';

$_base->page->load();