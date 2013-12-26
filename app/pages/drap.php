<?php

class page_drap extends pages_player
{
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		// vise liste over drapsforsøk for en bestemt spiller
		if (isset($_GET['up_id']) && access::has("mod", NULL, NULL, "login"))
		{
			$up_id = (int) $_GET['up_id'];
			$player = player::get($up_id);
			if ($player === false)
			{
				ess::$b->page->add_message("Fant ikke spilleren med id $up_id.", "error");
				redirect::handle('drap?allef');
			}
			
			$this->show_tries($player);
		}
		
		// vise liste over drapsforsøk rettet mot en bestemt spiller
		elseif (isset($_GET['offer_up_id']) && access::has("mod", NULL, NULL, "login"))
		{
			$up_id = (int) $_GET['offer_up_id'];
			$player = player::get($up_id);
			if ($player === false)
			{
				ess::$b->page->add_message("Fant ikke spilleren med id $up_id.", "error");
				redirect::handle('drap?allef');
			}
			
			$this->show_tries($player, true);
		}
		
		// vise liste over alle drapsforsøk
		elseif (isset($_GET['allef']) && access::has("mod", NULL, NULL, "login"))
		{
			$this->show_tries();
		}
		
		// vise liste over drapsforsøk spilleren selv har utført
		elseif (isset($_GET['forsok']))
		{
			$this->show_tries($this->up);
		}
		
		// vise komplett liste over alle drap
		elseif (isset($_GET['alle']) && access::has("mod", NULL, NULL, "login"))
		{
			$this->show_all();
		}
		
		// vis siste gjennomførte drap
		else
		{
			$this->show_main();
		}
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis liste over drap de siste 7 dagene
	 */
	protected function show_main()
	{
		ess::$b->page->add_title("Drapliste");
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">
		Drapliste
		<span class="left2"></span><span class="right2"></span>
	</h1>'.(access::has("mod") ? '
	<p class="h_left"><a href="drap?allef">Alle drapsforsøk</a></p>' : '').'
	<p class="h_right"><a href="drap?forsok">Mine drapsforsøk</a></p>
	<div class="bg1">
		<p>Dette er en oversikt over de siste spillerne som har blitt drept. Listen viser alle drap 7 dager tilbake i tid.</p>';
		
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$expire = time() - 604800;
		$result = $pagei->query("
			SELECT up_id, up_deactivated_time, up_deactivated_dead
			FROM users_players
			WHERE up_access_level = 0 AND up_deactivated_dead != 0 AND up_deactivated_time > $expire
			ORDER BY up_deactivated_time DESC");
		
		if ($pagei->total == 0)
		{
			echo '
		<p><b>Ingen drap har blitt gjennomført de siste 7 dagene.</b></p>';
		}
		
		else
		{
			// hent alle FF hvor spilleren var medlem
			essentials::load_module("ff");
			$result_ff = ess::$b->db->query("
				SELECT ffm_up_id, ffm_priority, ff_id, ff_inactive, IFNULL(ffm_ff_name, ff_name) ffm_ff_name, ff_type
				FROM
					(
						SELECT up_id
						FROM users_players
						WHERE up_access_level = 0 AND up_deactivated_dead != 0 AND up_deactivated_time > $expire
					) ref
					JOIN ff_members ON ffm_up_id = up_id AND ffm_status = ".ff_member::STATUS_DEACTIVATED."
					JOIN ff ON ff_id = ffm_ff_id
				WHERE ff_is_crew = 0
				ORDER BY ffm_ff_name");
			$ff_list = array(
				"familier" => array(),
				"firmaer" => array()
			);
			$mod = access::has("mod");
			while ($row = mysql_fetch_assoc($result_ff))
			{
				$type = $row['ff_type'] == 1 ? 'broderskap' : 'firmaer';
				$pos = ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']];
				if (!$row['ff_inactive'] || $mod)
				{
					$text = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ffm_ff_name']).'</a>';
				}
				else
				{
					$text = '<span title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ffm_ff_name']).'</span>';
				}
				
				$ff_list[$type][$row['ffm_up_id']][] = $text;
			}
			
			echo '
		<table class="table center'.($pagei->pages == 1 && !access::has("mod") ? ' tablemb' : '').'">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Broderskap</th>
					<th>Firmaer</th>
					<th>Tid</th>
					<th>Omfang</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				$familier = isset($ff_list['familier'][$row['up_id']]) ? implode(",<br />", $ff_list['familier'][$row['up_id']]) : '&nbsp;';
				$firmaer = isset($ff_list['firmaer'][$row['up_id']]) ? implode(",<br />", $ff_list['firmaer'][$row['up_id']]) : '&nbsp;';
				
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$row['up_id'].'" /></td>
					<td>'.$familier.'</td>
					<td>'.$firmaer.'</td>
					<td>'.ess::$b->date->get($row['up_deactivated_time'])->format().'</td>
					<td>'.($row['up_deactivated_dead'] == 1 ? 'Døde momentant' : 'Døde av skader påført tidligere').'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		if (access::has("mod"))
		{
			echo '
		<p class="c"><a href="drap?alle">Komplett liste over alle drap &raquo;</a></p>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Vis liste over alle drap
	 */
	protected function show_all()
	{
		ess::$b->page->add_title("Komplett drapliste");
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">
		Komplett drapliste
		<span class="left2"></span><span class="right2"></span>
	</h1>
	<p class="h_left"><a href="drap">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p>Dette er en oversikt over alle spillerne som har blitt drept.</p>';
		
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT up_id, up_deactivated_time, up_deactivated_up_id, up_deactivated_dead
			FROM users_players
			WHERE up_access_level = 0 AND up_deactivated_dead != 0
			ORDER BY up_deactivated_time DESC");
		
		if ($pagei->total == 0)
		{
			echo '
		<p><b>Ingen drap har blitt gjennomført.</b></p>';
		}
		
		else
		{
			echo '
		<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
			<thead>
				<tr>
					<th>Angriper</th>
					<th>Spiller</th>
					<th>Tid</th>
					<th>Omfang</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.($row['up_deactivated_up_id'] ? '<user id="'.$row['up_deactivated_up_id'].'" />' : '<i>Ingen</i>').'</td>
					<td><user id="'.$row['up_id'].'" /></td>
					<td>'.ess::$b->date->get($row['up_deactivated_time'])->format().'</td>
					<td>'.($row['up_deactivated_dead'] == 1 ? 'Døde momentant' : 'Døde av skader påført tidligere').'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Vis alle drapsforsøk for en spesifikk spiller
	 * @param player $up
	 * @param bool $offer skal vi vise angrep mot spilleren?
	 */
	protected function show_tries(player $up = null, $offer = null)
	{
		$alle = !$up;
		$egen = $up && $up->id == $this->up->id;
		
		if ($alle) ess::$b->page->add_title("Alle drapsforsøk");
		elseif (!$egen) ess::$b->page->add_title("Drapsforsøk ".($offer ? 'mot' : 'for')." '{$up->data['up_name']}'");
		else ess::$b->page->add_title("Mine drapsforsøk");
		
		echo '
<div class="bg1_c '.($alle ? 'large' : 'medium').'">
	<h1 class="bg1">
		'.($alle ? 'Alle drapsforsøk' : 'Drapsforsøk').'
		<span class="left2"></span><span class="right2"></span>
	</h1>
	<p class="h_left"><a href="drap">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p class="c">Dette er en oversikt som viser '.($alle ? 'alle drapsforsøk som er utført' : 'drapsforsøk '.($egen ? 'du har utført' : ($offer ? 'rettet mot spilleren '.$up->profile_link() : 'spilleren '.$up->profile_link().' har utført'))).'.</p>';
		
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT df_attack_up_id, df_defend_up_id, df_time, df_b_id, df_outcome, df_rankpoints, df_type, df_cash, df_hitlist, df_vitner, df_attack_ff_list, df_defend_ff_list
			FROM drapforsok
			WHERE ".($alle ? "" : ($offer ? "df_defend_up_id" : "df_attack_up_id")." = {$up->id} AND ")."(df_type != 1 OR df_outcome != 0)
			ORDER BY df_time DESC");
		
		if ($pagei->total == 0)
		{
			if ($alle)
			{
				echo '
		<p class="c"><b>Det er ingen som har prøvd å drepe noen enda.</b></p>';
			}
			
			elseif ($offer)
			{
				echo '
		<p class="c"><b>Ingen har angrepet '.$up->profile_link().' enda.</b></p>';
			}
			
			else
			{
				echo '
		<p class="c"><b>'.($egen ? 'Du' : $up->profile_link()).' har ikke prøvd å drepe noen enda.</b></p>';
			}
		}
		
		else
		{
			$ff_only_familier = isset($_GET['familier']);
			
			if ($ff_only_familier)
			{
				echo '
		<p class="c"><a href="'.game::address("drap", $_GET, array("familier")).'">Vis også firmaer</a></p>';
			}
			else
			{
				echo '
		<p class="c"><a href="'.game::address("drap", $_GET, array(), array("familier" => true)).'">Vis kun broderskap i listen</a></p>';
			}
			
			echo '
		<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
			<thead>
				<tr>'.($alle || $offer ? '
					<th>Angriper</th>' : '').(!$offer ? '
					<th>Offer</th>' : '').'
					<th>Tid/sted</th>
					<th>Omfang</th>
					<th>Poeng</th>
					<th>Penger / Etterlyst</th>
					<th>Vitner</th>
				</tr>
			</thead>
			<tbody>';
			
			ess::$b->page->add_css('.df_ff_list a { font-size: 10px; color: #777; text-decoration: none } .df_ff_list a:hover { text-decoration: underline }');
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				// sett opp vitner
				$vitner = $row['df_time'] > 1278604000 ? '<span class="dark">Ingen</span>' : '<span class="dark">Ukjent</span>';
				if ($row['df_vitner'])
				{
					$v = unserialize($row['df_vitner']);
					$synlige = array();
					$ukjente = 0;
					foreach ($v as $r)
					{
						if ($r[1] || access::has("mod")) $synlige[] = '<user id="'.$r[0].'" />'.(!$r[1] ? ' <span title="Ble ikke oppdaget">(u)</span>' : '');
						else $ukjente++;
					}
					$vitner = implode("<br />", $synlige);
					if ($ukjente > 0)
					{
						if (count($synlige) > 0) $vitner .= '<br />';
						$vitner .= fwords("%d ukjent", "%d ukjente", $ukjente);
					}
				}
				
				if ($alle || $offer)
				{
					// sett opp familier/firmaer (for angriper)
					$ff_attack = '';
					if ($row['df_attack_ff_list'])
					{
						$v = unserialize($row['df_attack_ff_list']);
						$ff_list = array();
						// $ff[] = array($ffm->ff->data['ff_type'], $ffm->ff->id, $ffm->ff->type['refobj'], $ffm->ff->data['ff_name'], $ffm->data['ffm_priority'], $ffm->get_priority_name());
						foreach ($v as $r)
						{
							if ($ff_only_familier && $r[0] != 1) continue;
							$ff_list[] = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$r[1].'" title="'.htmlspecialchars(ucfirst($r[5])).'">'.htmlspecialchars($r[3]).'</a>';
						}
						$ff_attack = '<br /><span class="df_ff_list">'.implode("<br />", $ff_list).'</span>';
					}
				}
				
				// sett opp familier/firmaer
				$ff = '';
				if ($row['df_defend_ff_list'])
				{
					$v = unserialize($row['df_defend_ff_list']);
					$ff_list = array();
					// $ff[] = array($ffm->ff->data['ff_type'], $ffm->ff->id, $ffm->ff->type['refobj'], $ffm->ff->data['ff_name'], $ffm->data['ffm_priority'], $ffm->get_priority_name());
					foreach ($v as $r)
					{
						if ($ff_only_familier && $r[0] != 1) continue;
						$ff_list[] = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$r[1].'" title="'.htmlspecialchars(ucfirst($r[5])).'">'.htmlspecialchars($r[3]).'</a>';
					}
					$ff = '<br /><span class="df_ff_list">'.implode("<br />", $ff_list).'</span>';
				}
				
				// sett opp bydel
				$bydel = "Ukjent bydel";
				if (!empty($row['df_b_id']) && isset(game::$bydeler[$row['df_b_id']]))
				{
					$bydel = htmlspecialchars(game::$bydeler[$row['df_b_id']]['name']);
				}
				
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>'.($alle || $offer ? '
					<td><user id="'.$row['df_attack_up_id'].'" />'.$ff_attack.'</td>' : '').(!$offer ? '
					<td><user id="'.$row['df_defend_up_id'].'" />'.$ff.'</td>' : '').'
					<td>'.ess::$b->date->get($row['df_time'])->format().'<br />'.$bydel.'</td>
					<td>'.($row['df_outcome'] == 1 ? '<b style="color: #FF0000">Døde</b>' : 'Ble skadet').'<br />
						<span class="dark">'.($row['df_type'] == 1 ? 'Utpressing' : 'Drapsforsøk').'</span></td>
					<td class="r">'.game::format_num($row['df_rankpoints']).'</td>
					<td class="r">'.game::format_cash($row['df_cash']).($row['df_hitlist'] > 0 ? '<br />'.game::format_cash($row['df_hitlist']) : '').'</td>
					<td>'.$vitner.'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		echo '
	</div>
</div>';
	}
}