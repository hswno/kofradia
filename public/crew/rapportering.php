<?php

require "../base.php";
access::need("crewet");

ess::$b->page->add_title("Rapportering");

// endre status for en rapportering
if (isset($_REQUEST['r_id']))
{
	// hent info
	$r_id = (int)$_REQUEST['r_id'];
	$result = \Kofradia\DB::get()->query("SELECT r_id, r_source_up_id, r_up_id, r_type, r_type_id, r_time, r_note, r_state, r_crew_up_id, r_crew_note, r_crew_time FROM rapportering WHERE r_id = $r_id");
	
	// fant ikke?
	$r = $result->fetch();
	if (!$r)
	{
		ess::$b->page->add_message("Fant ikke rapporteringen.", "error");
		redirect::handle();
	}
	
	// sette som under behandling
	if (isset($_POST['start']))
	{
		// allerede under behandling?
		if ($r['r_state'] == 1)
		{
			ess::$b->page->add_message('Rapporteringen er allerede satt som under behandling av <user id="'.$r['r_crew_up_id'].'" />.', "error");
		}
		
		// ferdig behandlet?
		elseif ($r['r_state'] == 2)
		{
			ess::$b->page->add_message("Rapporteringen er allerede behandlet.", "error");
		}
		
		// prøv å oppdater
		else
		{
			$a = \Kofradia\DB::get()->exec("UPDATE rapportering SET r_state = 1, r_crew_time = ".time().", r_crew_up_id = ".login::$user->player->id." WHERE r_id = $r_id AND r_state = 0");
			
			// ikke oppdatert?
			if ($a == 0)
			{
				ess::$b->page->add_message("En annen bruker var før deg.", "error");
			}
			
			else
			{
				// alt ok
				ess::$b->page->add_message("Du er nå satt som ansvarlig for å behandle rapporteringen.");
				
				redirect::handle("rapportering?r_id=$r_id&finish");
			}
		}
		
		redirect::handle();
	}
	
	// trekke tilbake fra behandling
	if (isset($_POST['reset']))
	{
		// ikke under behandling?
		if ($r['r_state'] != 1)
		{
			ess::$b->page->add_message("Rapporteringen er ikke under behandling.", "error");
		}
		
		// forsøk å trekk tilbake
		else
		{
			$a = \Kofradia\DB::get()->exec("UPDATE rapportering SET r_state = 0 WHERE r_id = $r_id AND r_state = 1 AND r_crew_up_id = ".login::$user->player->id);
			
			// ikke oppdatert?
			if ($a == 0)
			{
				ess::$b->page->add_message("Du er ikke satt opp som ansvarlig for denne rapporteringen.", "error");
			}
			
			else
			{
				// alt ok
				ess::$b->page->add_message("Rapporteringen er ikke lengre tilknyttet deg.");
			}
		}
		
		redirect::handle();
	}
	
	// sette som ferdig behandlet?
	if (isset($_POST['finish']) || isset($_GET['finish']))
	{
		// ikke under behandling?
		if ($r['r_state'] != 1)
		{
			ess::$b->page->add_message("Denne rapporteringen er ikke satt som under behandling.", "error");
		}
		
		// skrevet melding?
		elseif (isset($_POST['note']))
		{
			$note = trim($_POST['note']);
			#if (empty($note))
			#{
			#	ess::$b->page->add_message("Notatet kan ikke være tomt.", "error");
			#}
			
			// tilhører en annen, ikke godkjent overstyring
			if (!isset($_POST['override']) && $r['r_crew_up_id'] != login::$user->player->id)
			{
				ess::$b->page->add_message("Denne rapporteringen tilhører ikke deg, og du har ikke merket av for å overstyre.", "error");
			}
			
			else
			{
				// oppdater
				$a = \Kofradia\DB::get()->exec("UPDATE rapportering SET r_state = 2, r_crew_time = ".time().", r_crew_up_id = ".login::$user->player->id.", r_crew_note = ".\Kofradia\DB::quote($note)." WHERE r_id = $r_id");
				
				// ikke oppdatert?
				if ($a == 0)
				{
					ess::$b->page->add_message("Denne rapporteringen er ikke satt som under behandling.", "error");
				}
				
				else
				{
					// senk telleren
					tasks::mark("rapporteringer");
					
					ess::$b->page->add_message("Rapporteringen er nå ferdig behandlet.");
					redirect::handle();
				}
			}
		}
		
		// hent data for linker
		rapportering::generate_prerequisite(array($r));
		
		// vis skjema
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Rapportering<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="rapportering">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p>Før du kan sette rapporteringen som behandlet, må du fylle inn en kort notis/logg.</p>
		<h2 class="bg1">Info<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1">
			<p><user id="'.$r['r_source_up_id'].'" /> rapporterte <user id="'.$r['r_up_id'].'" /> ('.ess::$b->date->get($r['r_time'])->format().').</p>
			<p>Rapportert: <a href="'.rapportering::generate_link($r).'">'.rapportering::$types[$r['r_type']].'</a></p>';
		
		if ($r['r_state'] <= 0)
		{
			echo '
			<p><b>Ubehandlet rapportering</b></p>';
		}
		
		elseif ($r['r_state'] == 1)
		{
			echo '
			<p>Blir behandlet av <user id="'.$r['r_crew_up_id'].'" /> ('.ess::$b->date->get($r['r_crew_time'])->format().')</p>';
		}
		
		else
		{
			echo '
			<p>Ferdig behandlet av <user id="'.$r['r_crew_up_id'].'" /> ('.ess::$b->date->get($r['r_crew_time'])->format().')</p>';
		}
		
		echo '
			<div class="section">
				<h3>Begrunnelse for rapportering</h3>
				<div class="p">'.game::bb_to_html($r['r_note']).'</div>
			</div>';
		
		if ($r['r_state'] == 2)
		{
			echo '
			<div class="section">
				<h3>Crewnotat</h3>
				<div class="p">'.game::bb_to_html($r['r_crew_note']).'</div>
			</div>';
		}
		
		echo '
			<form action="" method="post">
				<input type="hidden" name="r_id" value="'.$r_id.'" />
				<div class="section">
					<h3>Notat</h3>
					<p><textarea name="note" rows="5" cols="10" style="width: 96%">'.htmlspecialchars(postval("note")).'</textarea></p>
				</div>'.($r['r_crew_up_id'] != login::$user->player->id ? '
				<p>Denne rapporteringen er ikke satt som under behandling for deg. <input type="checkbox" name="override" id="r_override"'.(isset($_POST['override']) ? ' checked="checked"' : '').' /><label for="r_override"> Overstyr</p>' : '').'
				<p>'.show_sbutton("Sett som ferdig behandlet", 'name="finish"').'</p>
			</form>
		</div>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	redirect::handle();
}



$mode = isset($_GET['old']) ? "old" : "active";

echo '
<div class="bg1_c medium">
	<h1 class="bg1">Rapportering<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';

// vise behandelde rapporteringer?
if ($mode == "old")
{
	echo '
		<h2>Behandlede rapporteringer</h2>
		<p><a href="rapportering">Vis aktive rapporteringer &raquo;</a></p>';
	
	$where = "r_state = 2";
}

else
{
	echo ' 
		<h2>Aktive rapporteringer</h2>
		<p><a href="rapportering?old">Vis gamle behandlede rapporteringer &raquo;</a></p>';
	
	$where = "r_state < 2";
}

// hent rapporteringer
$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 10);
$result = $pagei->query("SELECT r_id, r_source_up_id, r_up_id, r_type, r_type_id, r_time, r_note, r_state, r_crew_up_id, r_crew_note, r_crew_time FROM rapportering WHERE $where ORDER BY IF(r_crew_time>0, 1, 0), r_crew_time DESC, r_time DESC");

// ingen rapporteringer?
if ($result->rowCount() == 0)
{
	if ($mode == "old")
	{
		echo '
		<p>Det finnes ingen rapporteringer som har blitt behandlet.</p>';
	}
	else
	{
		echo '
		<p>Det finnes ingen <b>aktive</b> rapporteringer for øyeblikket.</p>';
	}
}

else
{
	// hent data og lag data for linker
	$rap = array();
	while ($row = $result->fetch())
	{
		$rap[] = $row;
	}
	rapportering::generate_prerequisite($rap);
	
	ess::$b->page->add_css('
.rap_wrap {
	margin: 1em 0;
	background-color: #222222;
	position: relative;
	overflow: auto;
}
.rap_time {
	position: absolute;
	top: 8px;
	right: 5px;
	margin: 0;
	color: #777777;
}
.rap_time span {
	color: #EEEEEE;
}
.rap_w {
	margin: 0;
	padding: 5px;
	background-color: #282828;
}
.rap_u {
	font-size: 14px;
}

.rap_wrap .col2_w { margin: 0 }
.rap_wrap .col_w.left { width: 40% }
.rap_wrap .col_w.right { width: 60% }
.rap_wrap .col_w.left .col { margin: 0 0 0 5px }
.rap_wrap .col_w.right .col { margin: 5px 5px 5px 0 }

.rap_note {
	background-color: #1C1C1C;
	padding: 5px !important;
	overflow: auto;
	border: 1px dotted #525252
}
');
	
	if ($pagei->pages > 1)
	{
		echo '
		<p class="c">'.address::make($_GET, $pagei).'</p>';
	}
	
	foreach ($rap as $row)
	{
		echo '
		<div class="rap_wrap">
			<p class="rap_time">Innsendt <span>'.ess::$b->date->get($row['r_time'])->format().'</span></p>
			<p class="rap_w"><user id="'.$row['r_source_up_id'].'" /> rapporterte <span class="rap_u"><user id="'.$row['r_up_id'].'" /></span></p>
			<div class="col2_w">
				<div class="col_w left">
					<div class="col">
						<p>Rapportert: <a href="'.rapportering::generate_link($row).'">'.rapportering::$types[$row['r_type']].'</a></p>';
		
		if ($row['r_state'] <= 0)
		{
			echo '
						<p><b>Ubehandlet rapportering</b></p>
						<form action="" method="post">
							<input type="hidden" name="r_id" value="'.$row['r_id'].'" />
							<p>'.show_sbutton("Behandle", 'name="start"').'</p>
						</form>';
		}
		elseif ($row['r_state'] == 1)
		{
			echo '
						<p>Blir behandlet av <user id="'.$row['r_crew_up_id'].'" /><br />('.ess::$b->date->get($row['r_crew_time'])->format().')</p>
						<form action="" method="post">
							<input type="hidden" name="r_id" value="'.$row['r_id'].'" />'.($row['r_crew_up_id'] == login::$user->player->id ? '
							<p>'.show_sbutton("Ferdig behandlet", 'name="finish"').' '.show_sbutton("Ikke behandle", 'name="reset"').'</p>' : '
							<p>'.show_sbutton("Behandlet (overstyr)", 'name="finish"').'</p>').'
						</form>';
		}
		else
		{
			echo '
						<p>Behandlet av <user id="'.$row['r_crew_up_id'].'" /><br />('.ess::$b->date->get($row['r_crew_time'])->format().')</p>';
		}
		
		echo '
					</div>
				</div>
				<div class="col_w right">
					<div class="col rap_note">'.game::bb_to_html($row['r_note']).'</div>'.($row['r_state'] == 2 ? '
					<!--<p class="col">Crewnotat:</p>-->
					<div class="col rap_note">'.game::bb_to_html($row['r_crew_note']).'</div>' : '').'
				</div>
			</div>
		</div>';
	}
	
	if ($pagei->pages > 1)
	{
		echo '
		<p class="c">'.address::make($_GET, $pagei).'</p>';
	}
}


echo '
	</div>
</div>';

ess::$b->page->load();