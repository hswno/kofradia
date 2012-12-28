<?php

class page_ranklist
{
	/**
	 * Construct
	 */
	public function __construct()
	{
		ess::$b->page->add_title("Ranklist");
		
		$this->handle();
		
		ess::$b->page->load();
	}
	
	/**
	 * Behandle siden
	 */
	protected function handle()
	{
		// oppdatere ranklista?
		if (isset($_GET['update']) && access::has("crewet"))
		{
			ranklist::flush();
			ess::$b->page->add_message("Ranklisten skal nå være oppdatert.");
		}
		
		// vise komplett liste?
		if (isset($_GET['alle']))
		{
			$this->all();
		}
		
		else
		{
			$this->top();
		}
	}
	
	/**
	 * Vise komplett liste
	 */
	protected function all()
	{
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Rangeringsoversikt<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="ranklist">&laquo; Vis kun topp-plaseringene</a></p>';
		
		if (isset($_GET['show_nsu']))
		{
			$nsu = "";
			ess::$b->page->add_message("Du viser også brukere som ikke vises på vanlig statistikk!");
		}
		else
		{
			$nsu = "up_access_level < ".ess::$g['access_noplay']." AND ";
		}
		
		
		// finn ut antall spillere
		$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE {$nsu}up_access_level != 0");
		$antall_spillere = mysql_result($result, 0, 0);
		
		if ($antall_spillere == 0)
		{
			echo '
		<p>
			Det finnes ingen spillere...?!
		</p>';
		}
		
		
		// fortsett
		else
		{
			// sideoppsett
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
			
			// hent folka..
			$result = $pagei->query("
				SELECT up_id, up_name, up_access_level, up_points, up_last_online, upr_rank_pos
				FROM users_players
					LEFT JOIN users_players_rank ON upr_up_id = up_id
				WHERE {$nsu}up_access_level != 0
				ORDER BY up_points DESC");
			
			$colspan = access::has("mod") ? 6 : 4;
			$e = 0;
			
			echo '
		<table class="table tablem" width="100%">
			<thead>
				<tr>
					<th>#</th>
					<th>Spillernavn</th>
					<th>Rank</th>
					<th>Sist pålogget</th>'.(access::has("mod") ? '
					<th>Rankpoeng</th>
					<th>&nbsp;</th>' : '').'
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="'.$colspan.'" class="c">'.$pagei->pagenumbers().'</td>
				</tr>';
			
			// startverdi
			$i = $pagei->start;
			$last_rank = 0;
			
			while ($row = mysql_fetch_assoc($result))
			{
				$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
				echo '
				<tr'.(++$e % 2 == 0 ? ' class="color"' : '').'>
					<td class="r">'.($last_rank != $row['upr_rank_pos'] ? '#'.game::format_number($row['upr_rank_pos']) : '<span style="color: #666">#'.game::format_number($row['upr_rank_pos']).'</span>').'</td>
					<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
					<td class="c">'.$rank['name'].'</td>
					<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>'.(access::has("mod") ? '
					<td class="r">'.game::format_number($row['up_points']).'</td>
					<td><a href="admin/brukere/finn?up_id='.$row['up_id'].'">IP-sjekk</a></td>' : '').'
				</tr>';
				$last_rank = $row['upr_rank_pos'];
			}
			
			echo '
				<tr'.(++$e % 2 == 0 ? ' class="color"' : '').'>
					<td colspan="'.$colspan.'" class="c">'.$pagei->pagenumbers().'</td>
				</tr>
			</tbody>
		</table>';
		}
		echo '
	</div>
</div>';
	}
	
	/**
	 * Vis toppliste
	 */
	protected function top()
	{
		if (access::has("crewet") && !isset($_GET['update']))
		{
			echo '
<p class="c"><a href="ranklist?update">Oppdater ranklista hvis det er feil i den &raquo;</a></p>';
		}
		
		// hent folka..
		$result = ess::$b->db->query("
			SELECT up_id, up_name, up_access_level, up_points, up_last_online, up_profile_image_url, upr_rank_pos
			FROM users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE up_access_level < ".ess::$g['access_noplay']." AND up_access_level != 0
			ORDER BY up_points DESC
			LIMIT 15");
		
		// hent familier hvor spilleren er medlem
		essentials::load_module("ff");
		$result_ff = ess::$b->db->query("
			SELECT ffm_up_id, ffm_priority, ff_id, ff_type, ff_name
			FROM
				(
					SELECT up_id
					FROM users_players
					WHERE up_access_level < ".ess::$g['access_noplay']." AND up_access_level != 0
					ORDER BY up_points DESC
					LIMIT 15
				) ref
				JOIN ff_members ON ffm_up_id = up_id AND ffm_status = ".ff_member::STATUS_MEMBER."
				JOIN ff ON ff_id = ffm_ff_id AND ff_type = 1 AND ff_inactive = 0
			ORDER BY ff_name");
		$familier = array();
		while ($row = mysql_fetch_assoc($result_ff))
		{
			$pos = ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']];
			$text = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ff_name']).'</a>';
			$familier[$row['ffm_up_id']][] = $text;
		}
		
		ess::$b->page->add_css('
.ranklist_box {
	background-color: #1D1D1D;
	margin: 15px auto;
	overflow: hidden;
	padding-left: 10px;
	position: relative;
	/*width: 60%;*/
}
.ranklist_box .profile_image {
	float: left;
	margin: 0 10px 0 -10px;
	border: 0;
}
.ranklist_box_1 {
	max-height: 100px;
	min-height: 80px;
}
.ranklist_box_2 {
	min-height: 60px;
	max-height: 60px;
}
.ranklist_box_2 .profile_image {
	width: 80px;
}
.ranklist_pos {
	position: absolute;
	top: 10px;
	right: 10px;
	font-size: 30px;
}
.ranklist_box_2 .ranklist_pos { font-size: 20px }
.ranklist_player {
	position: absolute;
	top: 10px;
	left: 130px;
}
.ranklist_player img { display: none }
.ranklist_box_2 .ranklist_player { left: 90px }
.rp_up { font-size: 16px }
.rp_rank {
	display: block;
	padding-top: 5px;
	color: #555;
}
.rp_familie {
	position: absolute;
	bottom: 10px;
	right: 10px;
	text-align: right;
}
.rp_no_familie { color: #555 }');
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Rangeringsoversikt<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';
		
		$e = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$e++;
			$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
			
			echo '
		<p class="ranklist_box ranklist_box_'.($e > 5 ? "2" : "1").'">
			<img src="'.htmlspecialchars(player::get_profile_image_static($row['up_profile_image_url'])).'" alt="Profilbilde" class="profile_image" />
			<span class="ranklist_pos">#'.$e.'</span>
			<span class="ranklist_player">
				<span class="rp_up">'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</span><br />
				<span class="rp_rank">'.$rank['name'].'</span>
			</span>
			<span class="rp_familie">'.(!isset($familier[$row['up_id']]) ? '<i class="rp_no_familie">Ingen broderskap</i>' : implode(", ", $familier[$row['up_id']])).'</span>
		</p>';
			
			if ($e == 15) break;
		}
		
		echo '
		<p class="c"><a href="ranklist?alle">Vis komplett liste &raquo;</a></p>
	</div>
</div>';
	}
}