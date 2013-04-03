<?php

define("ALLOW_GUEST", true);

require "base.php";
global $_base;

$_base->page->add_title("Søknader");

// administrere?
if (isset($_GET['admin']) && access::has("crewet"))
{
	redirect::store("soknader?admin");
	$_base->page->add_title("Administrasjon");
	
	// valgt søknad?
	if (isset($_GET['so_id']))
	{
		$_base->page->add_css('.highlightred td { background-color: #663333 }');
		
		// hent søknaden
		$so_id = intval(getval("so_id"));
		$result = $_base->db->query("SELECT so_id, so_title, so_preinfo, so_info, so_created, so_expire, so_status FROM soknader_oversikt WHERE so_id = $so_id");
		$soknad = mysql_fetch_assoc($result);
		
		if (!$soknad)
		{
			$_base->page->add_message("Fant ikke søknaden.", "error");
			redirect::handle("soknader?admin");
		}
		
		redirect::store("soknader?admin&so_id={$soknad['so_id']}");
		$_base->page->add_title($soknad['so_title']);
		
		// sett inaktiv
		if (isset($_POST['make_inactive']))
		{
			if ($soknad['so_status'] != 0)
			{
				$_base->db->query("UPDATE soknader_oversikt SET so_status = 0 WHERE so_id = {$soknad['so_id']}");
				$_base->page->add_message("Status endret til inaktiv.");
			}
			redirect::handle();
		}
		
		// sett aktiv
		elseif (isset($_POST['make_active']))
		{
			if ($soknad['so_status'] != 1)
			{
				$_base->db->query("UPDATE soknader_oversikt SET so_status = 1 WHERE so_id = {$soknad['so_id']}");
				$_base->page->add_message("Status endret til aktiv.");
			}
			redirect::handle();
		}
		
		// sett inaktiv
		elseif (isset($_POST['make_in_work']))
		{
			if ($soknad['so_status'] != 2)
			{
				$_base->db->query("UPDATE soknader_oversikt SET so_status = 2 WHERE so_id = {$soknad['so_id']}");
				$_base->page->add_message("Status endret til behandles.");
			}
			redirect::handle();
		}
		
		// sett inaktiv
		elseif (isset($_POST['make_finished']))
		{
			if ($soknad['so_status'] != 3)
			{
				$_base->db->query("UPDATE soknader_oversikt SET so_status = 3 WHERE so_id = {$soknad['so_id']}");
				$_base->page->add_message("Status endret til avsluttet.");
			}
			redirect::handle();
		}
		
		// hent felt til søknaden
		$result = $_base->db->query("SELECT sf_id, sf_title, sf_extra, sf_default_value, sf_params FROM soknader_felt WHERE sf_so_id = {$soknad['so_id']} ORDER BY sf_sort");
		$felt = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['params'] = new params($row['sf_params']);
			$felt[$row['sf_id']] = $row;
		}
		
		// status
		$status = $soknad['so_status'] == 0 ? "Inaktiv" : ($soknad['so_status'] == 1 ? "Aktiv" : ($soknad['so_status'] == 2 ? "Behandles" : "Avsluttet"));
		$buttons = array(
			0 => show_sbutton("Inaktiv", 'name="make_inactive"'),
			1 => show_sbutton("Aktiv", 'name="make_active"'),
			2 => show_sbutton("Behandles", 'name="make_in_work"'),
			3 => show_sbutton("Avsluttet", 'name="make_finished"')
		);
		unset($buttons[$soknad['so_status']]);
		$buttons = implode(" ", $buttons);
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Søknader (administrasjon) -- '.htmlspecialchars($soknad['so_title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="soknader?admin">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p>Søknadsfrist: '.$_base->date->get($soknad['so_expire'])->format(date::FORMAT_SEC).'</p>
		<form action="" method="post">
			<p class="noprint">Status: '.$status.' | Endre til '.$buttons.'</p>
		</form>
		<p><a href="soknader_vis?so_id='.$soknad['so_id'].'">Vis informasjon om søknaden &raquo;</a></p>
		
		<h2 class="bg1 noprint" style="margin-top: 20px">Leverte søknader<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1 noprint">';
		
		$date = $_base->date->get();
		$n_day = $date->format("j");
		$n_month = $date->format("n");
		$n_year = $date->format("Y");
		
		// hent alle søknadene som er levert
		$result = $_base->db->query("SELECT sa_id, sa_up_id, IF(sa_updated=0, sa_added, sa_updated) AS sa_updated, SUM(LENGTH(saf_value)) total_length, sa_comment, sa_weight, sa_verified, sa_verified_up_id, up_name, up_access_level, u_birth FROM soknader_applicants LEFT JOIN soknader_applicants_felt ON saf_sa_id = sa_id, users_players, users WHERE sa_so_id = {$soknad['so_id']} AND sa_status = 1 AND sa_up_id = up_id AND up_u_id = u_id GROUP BY sa_id ORDER BY IF(sa_weight = 0, 1, 0) DESC, IF(sa_updated > sa_verified, 1, 0) DESC, sa_weight DESC, sa_updated DESC");
		$levert = mysql_num_rows($result);
		if ($levert == 0)
		{
			echo '
			<p>Ingen søknader er levert.</p>';
		}
		
		else
		{
			$i = 0;
			$hidden = 0;
			$sa_id = intval(getval("sa_id"));
			while ($row = mysql_fetch_assoc($result))
			{
				$rating = $row['sa_weight'];
				$comment = game::format_data($row['sa_comment']);
				if (empty($comment)) $comment = 'Ingen';
				else
				{
					$comment = strip_tags($comment);
					$max = 20;
					$comment = mb_strlen($comment) > $max ? mb_substr($comment, 0, $max-4)." ..." : $comment;
					$comment = htmlspecialchars($comment);
				}
				
				// alder
				$birth = explode("-", $row['u_birth']);
				$age = count($birth) == 3
					? $n_year - $birth[0] - (($n_month < $birth[1] || ($birth[1] == $n_month && $n_day < $birth[2])) ? 1 : 0)
					: 'Ukjent';
				
				if ($hidden > 0) $hidden++;
				$first = $i == 0;
				$extra = "";
				if ($row['sa_updated'] > $row['sa_verified'] && $row['sa_verified'] != 0) $extra = '<br />(oppdatert)';
				elseif ($row['sa_weight'] < 0 && $hidden == 0)
				{
					if ($i > 0) echo '
				</tbody>
			</table>
			</div>';
					
					$hidden++;
					$first = true;
					
					echo '
			<div class="negative_soknader10 hide">
			<table class="table tablem" width="100%">'.($i == 0 ? '
				<thead>
					<tr>
						<th>Bruker</th>
						<th>Alder</th>
						<th>Sendt inn</th>
						<th><abbr title="Rating / ca. total lengde for feltene">R/L</abbr></th>
						<th>Kommentar</th>
						<th>&nbsp;</th>
					</tr>
				</thead>' : '').'
				<tbody>';
				}
				
				if ($i == 0 && $hidden == 0)
				{
					echo '
			<div>
			<table class="table tablem" width="100%">
				<thead>
					<tr>
						<th>Bruker</th>
						<th>Alder</th>
						<th>Sendt inn</th>
						<th><abbr title="Rating / ca. total lengde for feltene">R/L</abbr></th>
						<th>Kommentar</th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>';
				}
				
				$i++;
				echo '
					<tr'.($sa_id == $row['sa_id'] ? ' class="highlightred"' : ($i % 2 == 0 ? ' class="color"' : '')).'>
						<td>'.game::profile_link($row['sa_up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td'.($first ? ' width="25"' : '').' class="r" title="'.htmlspecialchars($row['u_birth']).'">'.$age.'</td>
						<td'.($first ? ' width="95"' : '').' class="c" style="font-size: 10px">'.$_base->date->get($row['sa_updated'])->format(date::FORMAT_NOTIME).'<br />'.$_base->date->get($row['sa_updated'])->format("H:i:s").$extra.'</td>
						<td'.($first ? ' width="35"' : '').' class="r"><span class="dark">'.$rating.'</span><br />'.game::format_number($row['total_length']).'</td>
						<td'.($first ? ' width="120"' : '').' class="c" style="font-size: 10px">'.($row['sa_verified'] == 0 ?
							'Ingen' :
							$comment.'<br />'.$_base->date->get($row['sa_verified'])->format(date::FORMAT_NOTIME).' '.$_base->date->get($row['sa_verified'])->format("H:i:s")).'</td>
						<td'.($first ? ' width="25"' : '').' class="c">'.($sa_id == $row['sa_id'] ? 'Valgt' : '<a href="soknader?admin&amp;so_id='.$soknad['so_id'].'&amp;sa_id='.$row['sa_id'].'">Vis</a>').'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			</div>';
			
			if ($hidden > 0)
			{
				echo '
			<p class="negative_soknader11"><a href="#" onclick="handleClass(\'.negative_soknader10\', \'.negative_soknader11\', event)">Vis søknadene med negative verdier ('.$hidden.' stk) &raquo</a></p>
			<p class="negative_soknader10 hide"><a href="#" onclick="handleClass(\'.negative_soknader11\', \'.negative_soknader10\', event)">Skjul søknadene med negative verdier ('.$hidden.' stk) &raquo</a></p>';
			}
		}
		
		echo '
		</div>';
		
		// bestemt søknad?
		if (isset($_GET['sa_id']))
		{
			// hent søknaden
			$sa_id = intval(getval("sa_id"));
			$result = $_base->db->query("SELECT sa_id, sa_up_id, sa_added, sa_status, IF(sa_updated=0, sa_added, sa_updated) AS sa_updated, sa_comment, sa_weight, sa_verified, sa_verified_up_id FROM soknader_applicants WHERE sa_id = $sa_id AND sa_so_id = {$soknad['so_id']}");
			$applicant = mysql_fetch_assoc($result);
			
			if (!$applicant)
			{
				$_base->page->add_message("Fant ikke søknaden.", "error");
				redirect::handle();
			}
			
			redirect::store("soknader?admin&so_id={$soknad['so_id']}&sa_id={$applicant['sa_id']}");
			$_base->page->add_title("Søknad #{$applicant['sa_id']}");
			
			// lagre rating og kommentar?
			if (isset($_POST['rating']) && isset($_POST['comment']))
			{
				$rating = intval(postval("rating"));
				$comment = trim(postval("comment"));
				
				$_base->db->query("UPDATE soknader_applicants SET sa_weight = $rating, sa_comment = ".$_base->db->quote($comment).", sa_verified = ".time().", sa_verified_up_id = ".login::$user->player->id." WHERE sa_id = {$applicant['sa_id']}");
				$_base->page->add_message("Informasjonen ble lagret.");
				redirect::handle();
			}
			
			$result = $_base->db->query("SELECT saf_sf_id, saf_value FROM soknader_applicants_felt WHERE saf_sa_id = {$applicant['sa_id']}");
			$applicant_felt = array();
			
			while ($row = mysql_fetch_assoc($result))
			{
				$applicant_felt[$row['saf_sf_id']] = $row['saf_value'];
			}
			
			echo '
		<h2 class="bg1" style="margin-top: 20px" id="scroll_here">Søknad #'.$applicant['sa_id'].'<span class="left2"></span><span class="right2"></span></h2>
		<p class="h_left"><a href="soknader?admin&amp;so_id='.$soknad['so_id'].'">&laquo; Tilbake</a></p>
		<div class="bg1">
			<boxes />';
			
			if ($applicant['sa_status'] == 0)
			{
				echo '
			<p>Søknaden er <u>ikke</u> sendt inn. Tilhører <user id="'.$applicant['sa_up_id'].'" /> og ble sist oppdatert '.$_base->date->get($applicant['sa_updated'])->format(date::FORMAT_SEC).'.</p>';
			}
			else
			{
				echo '
			<p>Innsendt av <user id="'.$applicant['sa_up_id'].'" /> '.$_base->date->get($applicant['sa_updated'])->format(date::FORMAT_SEC).'.</p>';
			}
			
			// kommentaren
			if ($applicant['sa_verified'] != 0  && ($d = game::format_data($applicant['sa_comment'])))
			{
				echo '
			<div class="warning">
				<p><u>Full kommentar</u> (rating: '.$applicant['sa_weight'].')</p>
				<div class="p">'.$d.'</div>
			</div>';
			}
			
			foreach ($felt as $key => $info)
			{
				$value = isset($applicant_felt[$key]) ? $applicant_felt[$key] : $info['sf_default_value'];
				$bb = false;
				
				switch ($info['params']->get("type"))
				{
					case "text":
						$bb = true;
						break;
				}
				
				$value = trim($value);
				if ($value == "")
				{
					echo '
			<p>
				<u>'.htmlspecialchars($info['sf_title']).'</u><br />
				<i>Mangler verdi.</i>
			</p>';
				}
				
				else
				{
					echo '
			<div class="p">
				<u>'.htmlspecialchars($info['sf_title']).'</u><br />
				'.($bb ? game::format_data($value) : htmlspecialchars($value)).(($post = $info['params']->get("post")) != "" ? ' '.$post : '').'
			</div>';
				}
			}
			
			echo '
			<div class="noprint">
				<div class="hr"></div>
				<form action="" method="post">
					<dl class="dl_20">
						<dt><u>Rating</u> (tallverdi)</dt>
						<dd><input type="text" name="rating" class="styled w40" value="'.intval($applicant['sa_weight']).'" /></dd>
					</dl>
					<p>
						<u>Kommentar</u><br />
						<textarea name="comment" rows="10" cols="30" style="width: 400px; margin-top: 5px">'.htmlspecialchars($applicant['sa_comment']).'</textarea>
					</p>'.($applicant['sa_verified'] != 0 ? '
					<p>Sist oppdatert '.$_base->date->get($applicant['sa_verified'])->format(date::FORMAT_SEC).' av <user id="'.$applicant['sa_verified_up_id'].'" />.</p>' : '').'
					<p>'.show_sbutton("Lagre").'</p>
				</form>
			</div>
		</div>';
		}
		
		echo '
	</div>
</div>';
		
		$_base->page->load();
	}
	
	echo '
<div class="bg1_c medium">
	<h1 class="bg1">Søknader (administrasjon)<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="soknader">&laquo; Tilbake</a></p>
	<div class="bg1">';
	
	
	// hent alle søknader
	$result = $_base->db->query("SELECT so_id, so_title, so_expire, so_status, COUNT(sa_id) AS sa_count FROM soknader_oversikt LEFT JOIN soknader_applicants ON so_id = sa_so_id AND sa_status = 1 GROUP BY so_id ORDER BY so_expire DESC, so_title");
	
	if (mysql_num_rows($result) == 0)
	{
		echo '
		<p>Ingen søknader eksisterer.</p>';
	}
	
	else
	{
		echo '
		<p>Velg en søknad:</p>
		<table class="table tablemb" width="100%">
			<thead>
				<tr>
					<th>Tittel</th>
					<th>Søknadsfrist</th>
					<th>Leverte søknader</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$status = $row['so_status'] == 0 ? "Inaktiv" : ($row['so_status'] == 1 ? "Aktiv" : ($row['so_status'] == 2 ? "Behandles" : "Avsluttet"));
			
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="soknader?admin&amp;so_id='.$row['so_id'].'">'.htmlspecialchars($row['so_title']).'</a></td>
					<td class="r">'.$_base->date->get($row['so_expire'])->format(date::FORMAT_SEC).'</td>
					<td class="r">'.$row['sa_count'].'</td>
					<td>'.$status.'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>';
	}
	
	echo '
	</div>
</div>';
	
	$_base->page->load();
}

echo '
<div class="bg1_c small">
	<h1 class="bg1">
		Søknader
		<span class="left"></span>
		<span class="right"></span>
	</h1>'.(access::has("crewet") ? '
	<p class="h_left"><a href="soknader?admin">Administrer søknader</a></p>' : '').'
	<div class="bg1">
		<!--<p>Her vil det bli lagt ut søknader for alle ting vi søker folk til. Måtte det være nye folk i crewet eller noe helt annet.</p>-->';

// hent åpne søknader
$result = $_base->db->query("SELECT so_id, so_title, so_preinfo, so_expire FROM soknader_oversikt WHERE so_expire > ".time()." AND so_status = 1 ORDER BY so_expire, so_title");

if (mysql_num_rows($result) == 0)
{
	echo '
		<p class="c">Det er ingen søknader som er åpne for øyeblikket.</p>';
}

else
{
	while ($row = mysql_fetch_assoc($result))
	{
		echo '
		<h2 class="bg1">
			'.htmlspecialchars($row['so_title']).'
			<span class="left2"></span>
			<span class="right2"></span>
		</h2>
		<div class="bg1">';
		
		// preinfo
		if (!empty($row['so_preinfo']))
		{
			echo '
			<div class="p">'.game::bb_to_html($row['so_preinfo']).'</div>';
		}
		
		echo '
			<p>Søknadsfrist: '.$_base->date->get($row['so_expire'])->format(date::FORMAT_SEC).'</p>
			<p><a href="soknader_vis?so_id='.$row['so_id'].'">Vis søknad &raquo;</a></p>
		</div>';
	}
}

echo '
	</div>
</div>';


// hent lukkede søknader
$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 20);
$result = $pagei->query("SELECT so_id, so_title, so_expire, so_status FROM soknader_oversikt WHERE so_expire <= ".time()." AND so_status != 0 ORDER BY so_expire DESC, so_title");

if (mysql_num_rows($result) > 0)
{
	echo '
<div class="bg1_c small">
	<h1 class="bg1">
		Tidligere søknader
		<span class="left"></span>
		<span class="right"></span>
	</h1>
	<div class="bg1">
		<dl class="dd_right">';
	
	$statuses = array(
		0 => "Inaktiv",
		1 => "Ubehandlet",
		2 => "Behandles",
		3 => "Avsluttet"
	);
	while ($row = mysql_fetch_assoc($result))
	{
		echo '
			<dt><a href="soknader_vis?so_id='.$row['so_id'].'">'.htmlspecialchars($row['so_title']).'</a> <span class="dark">['.$statuses[$row['so_status']].']</span></dt>
			<dd>Søknadsfrist: '.$_base->date->get($row['so_expire'])->format(date::FORMAT_SEC).'</dd>';
	}
	
	echo '
		</dl>
	</div>
</div>';
}

$_base->page->load();