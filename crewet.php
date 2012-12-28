<?php

define("ALLOW_GUEST", true);

require "base.php";
global $_game, $_base;

$_base->page->add_title("Crew");
$stats = (isset($_GET['stats']) && access::has("mod"));

echo '
<div class="bg1_c xsmall" style="width: 280px">
	<h1 class="bg1">Crewet<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c">Her er en liten liste med Crewet her på Kofradia!</p>
		<p class="c">Trenger du hjelp til noe relatert til spillet? Les på <a href="&path;/node">hjelpesidene</a> eller <a href="&path;/support/">send inn en supporthenvendelse</a>.</p>';

visliste("Administratorer", "up_access_level IN ({$_game['access']['admin'][0]}, {$_game['access']['sadmin'][0]})", "Ingen administratorer!");
visliste("Moderatorer", "up_access_level = {$_game['access']['mod'][0]}", "Ingen moderatorer!");
visliste("Forummoderatorer", "up_access_level IN ({$_game['access']['forum_mod'][0]}, {$_game['access']['forum_mod_nostat'][0]})", "Ingen forum-moderatorer!");
visliste("Utvikler", "up_access_level = {$_game['access']['developer'][0]}");
visliste("Ressurs", "up_access_level IN ({$_game['access']['ressurs'][0]}, {$_game['access']['ressurs_nostat'][0]})");

echo '
	</div>
</div>';

function visliste($name, $where)
{
	global $_base;
	$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_last_online FROM users_players WHERE $where ORDER BY up_name");
	
	// hopp over hvis det ikke finnes noen
	if (mysql_num_rows($result) == 0) return;
	
	echo '
		<h2 class="bg1">'.$name.'<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1">
			<dl class="dd_right">';
	
	while ($row = mysql_fetch_assoc($result))
	{
		echo '
				<dt>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</dt>
				<dd>'.game::timespan($row['up_last_online'], game::TIME_ABS | game::TIME_NOBOLD).'</dd>';
	}
	
	echo '
			</dl>
		</div>';
}

// hente liste over spillere som ikke har samme spillernivå som brukernivå?
if (access::has("crewet"))
{
	// skal vi sette spillernivået til et brukernivå?
	if (isset($_POST['u_to_up']) && access::has("admin"))
	{
		$up_id = (int) postval("up_id");
		if (!$up_id)
		{
			ess::$b->page->add_message("Du må velge en spiller.", "error");
			redirect::handle();
		}
		
		// hent informasjon om spilleren
		$result = ess::$b->db->query("SELECT u_id, u_access_level, up_access_level, up_id, up_name FROM users, users_players WHERE u_active_up_id = up_id AND up_id = $up_id");
		$up = mysql_fetch_assoc($result);
		if (!$up)
		{
			ess::$b->page->add_message("Fant ikke spilleren.", "error");
			redirect::handle();
		}
		
		// nivå er det samme?
		if ($up['u_access_level'] == $up['up_access_level'])
		{
			ess::$b->page->add_message("Nivået mellom bruker og spiller er det samme.", "error");
			redirect::handle();
		}
		
		// er brukeren deaktivert?
		if ($up['u_access_level'] == 0)
		{
			ess::$b->page->add_message('Brukeren til <user id="'.$up['up_id'].'" /> er deaktivert. Endringer må gjøres manuelt.', "error");
			redirect::handle();
		}
		
		// er spilleren deaktivert?
		if ($up['up_access_level'] == 0)
		{
			ess::$b->page->add_message('Spilleren <user id="'.$up['up_id'].'" /> er deaktivert. Endringer må gjøres manuelt.', "error");
			redirect::handle();
		}
		
		// overfør nivå
		ess::$b->db->query("UPDATE users, users_players SET up_access_level = u_access_level WHERE u_active_up_id = up_id AND up_id = {$up['up_id']} AND u_access_level != 0 AND up_access_level != 0");
		ess::$b->page->add_message('Tilgangsnivået til brukeren <user id="'.$up['up_id'].'" /> ble overført til spilleren.');
		
		// ranklista
		ess::$b->db->query("UPDATE users, users_players_rank SET upr_up_access_level = u_access_level WHERE upr_up_id = {$up['up_id']} AND upr_up_id = u_active_up_id");
		ranklist::update();
		
		// logg
		putlog("CREWCHAN", "TILGANGSNIVÅ OVERFØRT: Tilgangsnivået til {$up['up_name']} ble overført fra brukeren (nivå: {$up['u_access_level']}) til spilleren (gammelt nivå: {$up['up_access_level']}).");
		redirect::handle();
	}
	
	$result = ess::$b->db->query("
		SELECT u_id, u_access_level, up_access_level, up_id, up_name, up_last_online
		FROM users, users_players
		WHERE u_active_up_id = up_id AND up_u_id = u_id AND u_access_level != up_access_level AND (up_access_level != 0 || u_access_level != 1)
		ORDER BY up_name");
	if (mysql_num_rows($result) > 0)
	{
		$admin = access::has("admin");
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Forskjell mellom brukernivå og spillernivå<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />'.($admin ? '
		<form action="" method="post">' : '').'
		<table class="table '.($admin ? 'tablemt' : 'tablem').' center">
			<thead>
				<tr>
					<th>U_ID</th>
					<th>UP_ID</th>
					<th>Spiller</th>
					<th>Brukernivå</th>
					<th>Spillernivå</th>
					<th>Sist pålogget</th>
				</tr>
			</thead>
			<tbody>';
		
		while ($row = mysql_fetch_assoc($result))
		{
			// spesielle tilganger?
			$u_access = "Ukjent";
			$type = access::type($row['u_access_level']);
			$type_name = access::name($type);
			if (!empty($type_name))
			{
				$u_access = htmlspecialchars($type_name);
			}
			$up_access = "Ukjent";
			$type = access::type($row['up_access_level']);
			$type_name = access::name($type);
			if (!empty($type_name))
			{
				$up_access = htmlspecialchars($type_name);
			}
			
			echo '
				<tr'.($admin ? ' class="box_handle"' : '').'>
					<td>'.($admin ? '<input type="radio" name="up_id" value="'.$row['up_id'].'" />' : '').$row['u_id'].'</td>
					<td>'.$row['up_id'].'</td>
					<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
					<td>'.$row['u_access_level'].' ('.$u_access.')</td>
					<td>'.$row['up_access_level'].' ('.$up_access.')</td>
					<td>'.ess::$b->date->get($row['up_last_online'])->format().'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>'.($admin ? '
		<p class="c">'.show_sbutton("Overfør brukernivå til spillernivå", 'name="u_to_up"').'</p>
		</form>' : '').'
	</div>
</div>';
	}
}

ess::$b->page->load();