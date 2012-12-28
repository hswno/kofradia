<?php

require "../../base.php";
global $_base, $_game;

// hent alle krimoppføringene
$result = $_base->db->query("SELECT k.id, k.name, k.b_id, COUNT(IF(t.outcome=1,1,NULL)) AS vellykkede, COUNT(IF(t.outcome=2,1,NULL)) AS mislykkede FROM kriminalitet k LEFT JOIN kriminalitet_text t ON k.id = t.krimid GROUP BY k.id ORDER BY name");
$krims = array();

while ($row = mysql_fetch_assoc($result))
{
	$krims[$row['b_id']][] = $row;
}

echo '
<h1>Kriminalitet</h1>
<p class="h_right"><a href="../">Tilbake til administrasjon</a></p>
<p>Denne oversikten viser kriminalitetsalternativene i bydelene med antall vellykkede og mislykkede tekster. Du kan endre og opprette nye alternativer. Fjerning av alternativer er ikke mulig.</p>';

foreach (game::$bydeler as $id => $bydel)
{
	echo '
<div class="section center w300">
	<h2>'.htmlspecialchars($bydel['name']).'</h2>
	<p class="h_right"><a href="ny_krim?bydel='.$id.'">Ny krim</a></p>';
	
	// ikke aktiv?
	if ($bydel['active'] == 0)
	{
		echo '
	<p class="error_box">Denne bydelen er ikke aktiv.</p>';
	}
	
	if (isset($krims[$id]))
	{
		echo '
	<dl class="dd_right">';
		
		foreach ($krims[$id] as $krim)
		{
			echo '
		<dt><a href="krim?id='.$krim['id'].'">'.htmlspecialchars($krim['name']).'</a></dt>
		<dd><b>'.$krim['vellykkede'].'</b> ja/<b>'.$krim['mislykkede'].'</b> nei</dd>';
		}
		
		echo '
	</dl>';
	}
	else
	{
		echo '
	<p>Ingen oppføringer.</p>';
	}
	
	echo '
</div>';
}

$_base->page->load();