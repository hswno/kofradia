<?php

require "../base.php";
global $_base;

// krev admin tilgang
access::need("admin");

$_base->page->add_title("Registrer donasjonsoppføring");

// har vi brukerid?
if (isset($_POST['up_id']))
{
	$up_id = empty($_POST['up_id']) ? false : intval($_POST['up_id']);
	$player = false;
	
	if ($up_id)
	{
		// kontroller at brukeren finnes
		$result = $_base->db->query("SELECT up_id, up_name, u_email, up_access_level FROM users_players, users WHERE up_id = $up_id AND up_u_id = u_id");
		if (mysql_num_rows($result) == 0)
		{
			$_base->page->add_error("Fant ikke brukeren.");
			redirect::handle();
		}
		$player = mysql_fetch_assoc($result);
	}
	
	// registrere donasjon?
	if (isset($_POST['time']) && isset($_POST['amount']) && !isset($_POST['edit']))
	{
		// kontroller dato
		$date = check_date($_POST['time']); // d.m.y H:i:s
		if ($date)
		{
			$time = $_base->date->get();
			$time->setDate($date[3], $date[2], $date[1]);
			$time->setTime($date[4], $date[5], $date[6]);
		}
		
		// kontroller beløp
		$amount = round(str_replace(",", ".", $_POST['amount']), 2);
		
		// ugyldig dato
		if (!$date)
		{
			$_base->page->add_message("Ugyldig dato.", "error");
		}
		
		// ugyldig beløp
		elseif ($amount <= 0)
		{
			$_base->page->add_message("Ugyldig beløp.", "error");
		}
		
		// godkjent?
		elseif (isset($_POST['approve']))
		{
			// legg til
			$_base->db->query("INSERT INTO donations SET d_up_id = ".($up_id ? $up_id : 'NULL').", d_amount = $amount, d_time = ".$time->format("U"));
			
			// tøm cache
			cache::delete("donation_list");
			
			$_base->page->add_message("Donasjonsoppføringen ble lagt til.");
			redirect::handle();
		}
		
		else
		{
			echo box_start("Registrer donasjon", "xsmall").'
		<form action="" method="post">
			<input type="hidden" name="up_id" value="'.$up_id.'" />
			<input type="hidden" name="time" value="'.htmlspecialchars($_POST['time']).'" />
			<input type="hidden" name="amount" value="'.$amount.'" />
			<dl class="dd_right">
				<dt>Bruker</dt>
				<dd>'.($player ? game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']) : 'Anonym').'</dd>
				<dt>Tidspunkt</dt>
				<dd>'.$time->format(date::FORMAT_SEC).'</dd>
				<dt>Beløp</dt>
				<dd>'.game::format_nok($amount).'</dd>
			</dl>
			<p class="c">'.show_sbutton("Legg til oppføringen", 'name="approve"').' '.show_sbutton("Tilbake", 'name="edit"').'</p>
		</form>'.box_end();
			
			$_base->page->load();
		}
	}
	
	// vis skjema
	echo box_start("Registrer donasjon", "xsmall").'
		<form action="" method="post">
			<input type="hidden" name="up_id" value="'.$up_id.'" />
			<dl class="dd_right">
				<dt>Bruker</dt>
				<dd>'.($player ? game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']) : 'Anonym').'</dd>
				<dt>Tidspunkt</dt>
				<dd><input type="text" name="time" value="'.htmlspecialchars(postval("time", $_base->date->get()->format("d.m.Y H:i:s"))).'" class="styled w120" /></dd>
				<dt>Beløp</dt>
				<dd>kr. <input type="text" name="amount" value="'.htmlspecialchars(postval("amount")).'" class="styled w50" /></dd></dd>
			</dl>
			<p class="c">'.show_sbutton("Fortsett").' <a href="registrer_donasjon" class="button">Avbryt</a></p>
		</form>'.box_end();
	
	$_base->page->load();
}

// søke etter bruker ved e-post?
if (isset($_POST['email']) && isset($_POST['value']))
{
	// finn brukere på denne e-posten
	$result = $_base->db->query("SELECT up_id, up_name, up_access_level, up_last_online FROM users, users_players WHERE u_email = ".$_base->db->quote($_POST['value'])." AND up_u_id = u_id ORDER BY up_last_online DESC");
	
	echo box_start("Registrer donasjon - Søk etter bruker (e-post)", "small").'
		<p>Søk (e-post): '.htmlspecialchars($_POST['value']).'</p>';
	
	if (mysql_num_rows($result) == 0)
	{
		echo '
		<p>Fant ingen brukere.</p>';
	}
	
	else
	{
		echo '
		<table class="table tablemb">
			<thead>
				<tr>
					<th>Bruker</th>
					<th>Sist pålogget</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
					<td>'.$_base->date->get($row['up_last_online'])->format(date::FORMAT_SEC).'</td>
					<td><form action="" method="post"><input type="hidden" name="up_id" value="'.$row['up_id'].'" />'.show_sbutton("Velg").'</form></td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>';
	}
	
	echo box_end();
}

// søke etter bruker ved id?
if (isset($_POST['id']) && isset($_POST['value']))
{
	// finn brukeren med denne iden
	$result = $_base->db->query("SELECT up_id, up_name, u_email, up_access_level, up_last_online FROM users, users_players WHERE up_id = ".intval($_POST['value'])." AND u_id = up_u_id");
	
	echo box_start("Registrer donasjon - Søk etter bruker (id)", "small").'
		<p>Søk (id): '.htmlspecialchars($_POST['value']).'</p>';
	
	if (mysql_num_rows($result) == 0)
	{
		echo '
		<p>Fant ingen brukere.</p>';
	}
	
	else
	{
		echo '
		<table class="table tablemb">
			<thead>
				<tr>
					<th>Bruker</th>
					<th>E-post</th>
					<th>Sist pålogget</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
					<td>'.htmlspecialchars($row['u_email']).'</td>
					<td>'.$_base->date->get($row['up_last_online'])->format(date::FORMAT_SEC).'</td>
					<td><form action="" method="post"><input type="hidden" name="up_id" value="'.$row['up_id'].'" />'.show_sbutton("Velg").'</form></td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>';
	}
	
	echo box_end();
}

if (isset($_POST['user']) && isset($_POST['value']))
{
	// finn brukeren med dette spillernavnet
	$result = $_base->db->query("SELECT up_id, up_name, u_email, up_access_level, up_last_online FROM users, users_players WHERE up_name = ".$_base->db->quote($_POST['value'])." AND up_u_id = u_id");
	
	echo box_start("Registrer donasjon - Søk etter spiller", "small").'
		<p>Søk (spiller): '.htmlspecialchars($_POST['value']).'</p>';
	
	if (mysql_num_rows($result) == 0)
	{
		echo '
		<p>Fant ingen spillere.</p>';
	}
	
	else
	{
		echo '
		<table class="table tablemb">
			<thead>
				<tr>
					<th>Bruker</th>
					<th>E-post</th>
					<th>Sist pålogget</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
					<td>'.htmlspecialchars($row['u_email']).'</td>
					<td>'.$_base->date->get($row['up_last_online'])->format(date::FORMAT_SEC).'</td>
					<td><form action="" method="post"><input type="hidden" name="up_id" value="'.$row['up_id'].'" />'.show_sbutton("Velg").'</form></td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>';
	}
	
	echo box_end();
}

show_search_form();
$_base->page->load();

function like_search($value)
{
	return strtr($value, array('_' => '\\_', '%' => '\\%', '*' => '%', '?' => '_'));
}

function show_search_form()
{
	echo box_start("Registrer donasjon - Søk etter bruker", "small").'
		<form action="" method="post">
			<dl class="dd_right">
				<dt><input type="text" class="styled w150" name="value" value="'.htmlspecialchars(postval("value")).'" /></dt>
				<dd>'.show_sbutton("ID", 'name="id"').' '.show_sbutton("Bruker", 'name="user"').' '.show_sbutton("E-post", 'name="email"').'</dd>
			</dl>
		</form>'.box_end();
}

function box_start($h_html, $size = false, $h_level = "h1")
{
	$css_c = $size ? ' '.$size : '';
	
	return '
<div class="bg1_c'.$css_c.'">
	<'.$h_level.' class="bg1">'.$h_html.'<span class="left"></span><span class="right"></span></'.$h_level.'>
	<div class="bg1">';
}

function box_end()
{
	return '
	</div>
</div>';
}