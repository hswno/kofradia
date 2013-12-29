<?php

require "../../app/ajax.php";
ajax::require_user();
access::need("crewet");

if (!isset($_POST['u_id']))
{
	ajax::text("ERROR", ajax::TYPE_INVALID);
}

// sjekk bruker
$user = user::get($_POST['u_id']);
if (!$user)
{
	ajax::text("ERROR:USER-404", ajax::TYPE_404);
}

// hente rapporteringer MOT brukeren?
$data = "";
if (postval("a") == "to")
{
	$data .= '
<p class="c">Andre brukere som har rapportert denne brukeren.</p>';
	
	$where = " AND r_up_id = up_id";
}
elseif (postval("a") == "from")
{
	$data .= '
<p class="c">Rapporteringer som brukeren selv har opprettet.</p>';
	
	$where = " AND r_source_up_id = up_id";
}
else
{
	$data .= '
<p class="c">Alle rapporteringer denne brukeren er involvert i.</p>';
	
	$where = " AND (r_source_up_id = up_id OR r_up_id = up_id)";
}

$pagei = new pagei(pagei::ACTIVE_POST, "s", pagei::PER_PAGE, 10);
$result = $pagei->query("
	SELECT r_id, r_source_up_id, r_up_id, r_type, r_type_id, r_time, r_note, r_state, r_crew_up_id, r_crew_note, r_crew_time
	FROM rapportering, users_players
	WHERE up_u_id = {$user->id}$where
	ORDER BY IFNULL(r_crew_time, r_time) DESC");

if ($pagei->total == 0)
{
	ajax::html(parse_html($data . '
<p class="c">Ingen oppføringer ble funnet.</p>'));
}

$raps = array();
while ($row = $result->fetch()) $raps[] = $row;
rapportering::generate_prerequisite($raps);

$data .= '
<p class="c">'.$pagei->pagenumbers_ajax().'</p>';

foreach ($raps as $row)
{
	$data .= '
<div class="rap_wrap">
	<p class="rap_time">Innsendt <span>'.ess::$b->date->get($row['r_time'])->format().'</span></p>
	<p class="rap_w"><user id="'.$row['r_source_up_id'].'" /> rapporterte <span class="rap_u"><user id="'.$row['r_up_id'].'" /></span></p>
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">
				<p>Rapportert: <a href="'.rapportering::generate_link($row).'">'.rapportering::$types[$row['r_type']].'</a></p>';
	
	if ($row['r_state'] <= 0)
	{
		$data .= '
				<p><b>Ubehandlet rapportering</b></p>';
	}
	elseif ($row['r_state'] == 1)
	{
		$data .= '
				<p>Blir behandlet av <user id="'.$row['r_crew_up_id'].'" /><br />('.ess::$b->date->get($row['r_crew_time'])->format().')</p>';
	}
	else
	{
		$data .= '
				<p>Behandlet av <user id="'.$row['r_crew_up_id'].'" /><br />('.ess::$b->date->get($row['r_crew_time'])->format().')</p>';
	}
	
	$data .= '
			</div>
		</div>
		<div class="col_w right">
			<div class="col rap_note">'.game::bb_to_html($row['r_note']).'</div>'.($row['r_state'] == 2 ? '
			<div class="col rap_note">'.game::bb_to_html($row['r_crew_note']).'</div>' : '').'
		</div>
	</div>
</div>';
}

$data .= '
<p class="c">'.$pagei->pagenumbers_ajax().'</p>';

ajax::html(parse_html($data));