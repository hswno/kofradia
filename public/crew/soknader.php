<?php

require "config.php";

global $_base;
$_base->page->add_title("Diverse søknader");

// har vi valgt en søknad?
if (isset($_GET['ds_id']))
{
	$ds_id = (int) $_GET['ds_id'];
	
	// hent info
	$soknad = soknader::get($ds_id);
	
	// fant ikke?
	if (!$soknad)
	{
		$_base->page->add_message("Fant ikke søknaden.", "error");
		redirect::handle();
	}
	
	$type = soknader::get_type($soknad['ds_type']);
	$_base->page->add_title("Søknad: ".$type['title']);
	
	// skal vi behandle den?
	if (isset($_POST['reason']) && (isset($_POST['approve']) || isset($_POST['decline'])))
	{
		// allerede behandlet?
		if ($soknad['ds_reply_decision'] != 0)
		{
			$_base->page->add_message("Søknaden er allerede behandlet.", "error");
			redirect::handle();
		}
		
		$decision = isset($_POST['approve']);
		$reason = trim(postval("reason"));
		
		// mangler begrunnelse?
		if (empty($reason))
		{
			$_base->page->add_message("Mangler begrunnelse.", "error");
		}
		
		else
		{
			// forsøk å behandle
			$result = soknader::decide($decision, $ds_id, $_POST['reason']);
			if (!is_array($result))
			{
				if ($result === false)
				{
					$_base->page->add_message("Fant ikke søknaden.", "error");
				}
				
				else
				{
					// vis melding
					$_base->page->add_message($result, "error");
				}
			}
			
			else
			{
				$_base->page->add_message("Søknaden er nå behandlet.");
			}
			
			redirect::handle();
		}
	}
	
	// hent info
	$params = unserialize($soknad['ds_params']);
	$info = soknader::get_info($type['name'], $soknad, $params);
	
	// vis info
	echo '
<div class="bg1_c small">
	<h1 class="bg1">Diverse søknader: '.htmlspecialchars($type['title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="soknader">&laquo; Tilbake</a></p>
	<div class="bg1">
		<dl class="dd_right">
			<dt>Søker</dt>
			<dd><user id="'.$soknad['ds_up_id'].'" /></dd>
			<dt>Tidspunkt sendt inn</dt>
			<dd>'.$_base->date->get($soknad['ds_time'])->format().'</dd>
			<dt>Type søknad</dt>
			<dd>'.htmlspecialchars($type['title']).'</dd>
		</dl>
		<p>
			<b>Søknadsbeskrivelse</b><br />
			'.$info['html'].'
		</p>
		<p>
			<b>Begrunnelse for søknad:</b><br />
			'.game::format_data($soknad['ds_reason'], "bb-opt", "Ingen begrunnelse gitt.").'
		</p>';
	
	switch($type['name'])
	{
		case "firma_name":
			// vis navnhistorikk for firmaet
			$result = ess::$b->db->query("
					SELECT ds_id, ds_up_id, ds_time, ds_params, ds_reply_decision
					FROM div_soknader
					WHERE ds_type = {$soknad['ds_type']} AND ds_rel_id = {$soknad['ds_rel_id']}
					ORDER BY ds_time DESC
					LIMIT 10");
			if (mysql_num_rows($result) == 0)
			{
				echo '
		<p>Ingen tidligere søknader om navnbytte er registrert for dette firmaet.</p>';
			}
			else
			{
				echo '
		<p>Siste registrerte søknader for navnbytte for firmaet:</p>
		<table class="table">
			<thead>
				<tr>
					<th>Tidspunkt</th>
					<th>Søknad</th>
					<th>Søker</th>
				</tr>
			</thead>
			<tbody>';
				
				while ($row = mysql_fetch_assoc($result))
				{
					$params = unserialize($row['ds_params']);
					echo '
				<tr>
					<td><a href="soknader?ds_id=' . $row['ds_id'] . '">' . ess::$b->date->get($row['ds_time'])->format() . '</a></td>
					<td>' . ($row['ds_reply_decision'] == 1 ? 'Fra: ' . htmlspecialchars($params['name_old']) . '<br />Til: ' . htmlspecialchars($params['name']) : 'Til: ' . htmlspecialchars($params['name'])) . '</td>
					<td><user id="' . $row['ds_up_id'] . '" /><br />
						' . ($row['ds_reply_decision'] == 1 ? 'Innvilget' : ($row['ds_reply_decision'] == -1 ? 'Avslått' : 'Under behandling')) . '</td>
				</tr>';
				}
				
				echo '
			</tbody>
		</table>';
			}
		break;
		
		case "familie_name":
			// vis navnhistorikk for familien
			$result = ess::$b->db->query("
					SELECT ds_id, ds_up_id, ds_time, ds_params, ds_reply_decision
					FROM div_soknader
					WHERE ds_type = {$soknad['ds_type']} AND ds_rel_id = {$soknad['ds_rel_id']}
					ORDER BY ds_time DESC
					LIMIT 10");
			if (mysql_num_rows($result) == 0)
			{
				echo '
		<p>Ingen tidligere søknader om navnbytte er registrert for dette broderskapet.</p>';
			}
			else
			{
				echo '
		<p>Siste registrerte søknader for navnbytte for broderskapet:</p>
		<table class="table">
			<thead>
				<tr>
					<th>Tidspunkt</th>
					<th>Søknad</th>
					<th>Søker</th>
				</tr>
			</thead>
			<tbody>';
				
				while ($row = mysql_fetch_assoc($result))
				{
					$params = unserialize($row['ds_params']);
					echo '
				<tr>
					<td><a href="soknader?ds_id=' . $row['ds_id'] . '">' . ess::$b->date->get($row['ds_time'])->format() . '</a></td>
					<td>' . ($row['ds_reply_decision'] == 1 ? 'Fra: ' . htmlspecialchars($params['name_old']) . '<br />Til: ' . htmlspecialchars($params['name']) : 'Til: ' . htmlspecialchars($params['name'])) . '</td>
					<td><user id="' . $row['ds_up_id'] . '" /><br />
						' . ($row['ds_reply_decision'] == 1 ? 'Innvilget' : ($row['ds_reply_decision'] == -1 ? 'Avslått' : 'Under behandling')) . '</td>
				</tr>';
				}
				
				echo '
			</tbody>
		</table>';
			}
		break;
	}
	
	// behandlet?
	if ($soknad['ds_reply_decision'] != 0)
	{
		echo '
		<p>Søknaden ble <b>'.($soknad['ds_reply_decision'] == -1 ? 'avslått' : 'godtatt').'</b> '.$_base->date->get($soknad['ds_reply_time'])->format().' av <user id="'.$soknad['ds_reply_up_id'].'" />.</p>
		<p><b>Begrunnelse for '.($soknad['ds_reply_decision'] == -1 ? 'avslag' : 'godtatt søknad').':</b><br />'.game::format_data($soknad['ds_reply_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>';
	}
	
	// har vi tilgang til å behandle denne søknaden?
	elseif (!access::has($type['access']))
	{
		echo '
		<p><u>Du har ikke tilgang til å behandle denne søknaden. Må behandles av en '.access::name($type['access']).'.</u></p>';
	}
	
	else
	{
		echo '
		<form action="" method="post">
			<p><b>Begrunnelse:</b> (Spilleren blir opplyst om denne begrunnelsen.)</p>
			<p><textarea name="reason" rows="5" cols="40">'.htmlspecialchars(postval("reason")).'</textarea></p>
			<p>
				'.show_sbutton("Godta søknad", 'name="approve"').'
				'.show_sbutton("Avslå søknad", 'name="decline"').'
				<a href="soknader" class="button">Avbryt</a>
			</p>
		</form>';
	}
	
	echo '
	</div>
</div>';
	
	$_base->page->load();
}

// vise alle søknadene?
$all = isset($_GET['all']);
$where = $all ? '1' : 'ds_reply_decision = 0';

// hent alle søknadene
$pagei = new pagei(pagei::PER_PAGE, 5, pagei::ACTIVE_GET, "side");
$result = $pagei->query("SELECT ds_id, ds_type, ds_up_id, ds_time, ds_reply_decision FROM div_soknader WHERE $where ORDER BY ds_time DESC");

echo '
<div class="bg1_c '.($all ? 'xmedium' : 'small').'">
	<h1 class="bg1">Diverse søknader<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';

// ingen søknader?
if (mysql_num_rows($result) == 0)
{
	echo '
		<p>Det er ingen'.($all ? '' : ' ubehandlede').' søknader.</p>';
}

else
{
	echo '
		<table class="table tablemt center" style="width: 100%">
			<thead>
				<tr>
					<th>Type</th>
					<th>Innsender</th>
					<th>Tidspunkt</th>'.($all ? '
					<th>Resultat</th>' : '').'
				</tr>
			</thead>
			<tbody>';
	
	$i = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		$type = soknader::get_type($row['ds_type']);
		$link = htmlspecialchars($type['title']);
		if (access::has($type['access']))
		{
			$link = '<a href="soknader?ds_id='.$row['ds_id'].'" title="Vis søknad">'.$link.'</a>';
		}
		else
		{
			$link .= ' ('.access::name($type['access']).')';
		}
		
		echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.$link.'</td>
					<td><user id="'.$row['ds_up_id'].'" /></td>
					<td>'.$_base->date->get($row['ds_time'])->format().'</td>'.($all ? '
					<td>'.($row['ds_reply_decision'] == 1
						? 'Innvilget'
						: ($row['ds_reply_decision'] == -1
							? 'Avslått'
							: 'Under behandling')).'</td>' : '').'
				</tr>';
	}
	
	echo '
			</tbody>
		</table>';
	
	if ($pagei->pages > 1) echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
}

// link for å vise/skjule alle søknader
if ($all)
{
	echo '
		<p><a href="soknader">Vis kun ubehandlede søknader &raquo;</a></p>';
}
else
{
	echo '
		<p><a href="soknader?all">Vis alle søknader &raquo;</a></p>';
}

echo '
	</div>
</div>';

$_base->page->load();