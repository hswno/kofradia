<?php

require "../../base.php";
global $_base, $__server;

// hent bydel info
$bydel = intval($_GET['bydel']);
$result = $_base->db->query("SELECT id, name FROM bydeler WHERE id = $bydel");
if (mysql_num_rows($result) == 0)
{
	$_base->page->add_message("Fant ingen bydel med id: $bydel!", "error");
	redirect::handle("");
}
$bydel = mysql_fetch_assoc($result);

// legge til krim?
if (isset($_POST['title']))
{
	$title = trim(postval("title"));
	
	// for kort?
	if (strlen($title) < 4)
	{
		$_base->page->add_message("Tittelen må være på 4 eller flere tegn.", "error");
	}
	
	else
	{
		global $time_low, $time_high, $points_low, $points_high, $strength_low, $strength_high, $cash_min, $cash_max;
		
		// finn passende verdier
		$wait_time = rand($time_low, $time_high);
		$points = rand($points_low, $points_high);
		$strength = rand($strength_low, $strength_high);
		$cash_min = rand($cash_min, $cash_max);
		$cash_max = rand($cash_min, $cash_max);
		
		// legg til
		$_base->db->query("INSERT INTO kriminalitet SET b_id = {$bydel['id']}, name = ".$_base->db->quote($title).", wait_time = $wait_time, max_strength = $strength, points = $points, cash_min = $cash_min, cash_max = $cash_max");
		$id = $_base->db->insert_id();
		
		// logg
		putlog("CREWCHAN", "NY KRIM: ".login::$user->player->data['up_name']." la til $title - {$__server['path']}/admin/kriminalitet/krim?id=$id");
		
		redirect::handle("krim?id=$id");
	}
}

$_base->page->add_title("Ny Krim");

echo '
<h1>Ny kriminalitet</h1>
<p class="h_right">
	<a href="./">Tilbake til krimpanel</a>
	<a href="../">Tilbake til administrasjon</a>
</p>
<form action="" method="post">
	<div class="section">
		<h2>Ny krim</h2>
		<dl class="dd_right">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title")).'" class="styled w100" /></dd>
		</dl>
	</div>
	<p class="c">'.show_sbutton("Legg til").'</p>
</form>';

$_base->page->load();