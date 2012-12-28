<?php

require "../base.php";

new page_ff_members();
class page_ff_members
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Navnet på siden (medlemmer eller ansatte)
	 */
	protected $title;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		$this->ff->needaccess(2);
		
		redirect::store("medlemmer?ff_id={$this->ff->id}");
		
		$this->page_handle();
		$this->ff->load_page();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function page_handle()
	{
		$this->title = $this->ff->type['type'] == "familie" ? "Medlemmer" : "Ansatte";
		ess::$b->page->add_title($this->title);
		
		// godta forslag om en spiller?
		if (isset($_POST['suggestion_accept']) && validate_sid())
		{
			$this->suggestion_accept();
		}
		
		// avslå forslag om en spiller?
		if (isset($_POST['suggestion_decline']) && validate_sid())
		{
			$this->suggestion_decline();
		}
		
		// invitere en spiller?
		if (isset($_GET['invite']))
		{
			$this->invite();
		}
		
		// trekke tilbake en invitasjon?
		if (isset($_POST['invite_delete']) && validate_sid())
		{
			$this->invite_pullback();
		}
		
		// kaste ut et medlem?
		if (isset($_POST['kick']))
		{
			$this->kick();
		}
		
		// endre posisjon på et medlem?
		if (isset($_REQUEST['change_priority']))
		{
			$this->change_priority();
		}
		
		// vis oversikt over medlemmer
		$this->show();
	}
	
	/**
	 * Vis oversikt over medlemmer
	 */
	protected function show()
	{
		// vis oversikt over foreslåtte spillere
		if (count($this->ff->members['suggested']) > 0)
		{
			echo '
<h1 class="c">Foreslåtte spillere</h1>
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<table class="table center">
		<thead>
			<tr>
				<th>Spiller</th>
				<th>Stilling</th>
				<th>Dato foreslått</th>
				<th>Sist pålogget</th>
			</tr>
		</thead>
		<tbody>';
			
			$i = 0;
			foreach ($this->ff->members['suggested'] as $member)
			{
				echo '
			<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
				<td><input type="radio" name="up_id" value="'.$member->id.'" />'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'</td>
				<td>'.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'</td>
				<td>'.ess::$b->date->get($member->data['ffm_date_join'])->format().'</td>
				<td>'.ess::$b->date->get($member->data['up_last_online'])->format().'<br />'.game::timespan($member->data['up_last_online'], game::TIME_ABS).'</td>
			</tr>';
			}
			
			echo '
		</tbody>
	</table>
	<p class="c">
		'.show_sbutton("Godta forslag og inviter spiller", 'name="suggestion_accept"').'
		'.show_sbutton("Avslå forslag", 'name="suggestion_decline"').'
	</p>
</form>
<div class="fhr"></div>';
		}
		
		
		// vis oversikt over inviterte spillere
		if (count($this->ff->members['invited']) > 0)
		{
			echo '
<h1 class="c">Inviterte spillere</h1>
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<table class="table center">
		<thead>
			<tr>
				<th>Spiller</th>
				<th>Stilling</th>
				<th>Dato invitert</th>
				<th>Sist pålogget</th>
			</tr>
		</thead>
		<tbody>';
			
			$i = 0;
			foreach ($this->ff->members['invited'] as $member)
			{
				echo '
			<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
				<td><input type="radio" name="up_id" value="'.$member->id.'" />'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'</td>
				<td>'.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'</td>
				<td>'.ess::$b->date->get($member->data['ffm_date_join'])->format().'</td>
				<td>'.ess::$b->date->get($member->data['up_last_online'])->format().'<br />'.game::timespan($member->data['up_last_online'], game::TIME_ABS).'</td>
			</tr>';
			}
			
			echo '
		</tbody>
	</table>
	<p class="c">'.show_sbutton("Trekk tilbake invitasjon", 'name="invite_delete"').'</p>
</form>
<div class="fhr"></div>';
		}
		
		
		// vis oversikt over medlemmer
		echo '
<h1 class="c">'.$this->title.' i '.$this->ff->type['refobj'].'</h1>
<p class="c"><a href="medlemmer?ff_id='.$this->ff->id.'&amp;invite">Inviter spiller &raquo;</a></p>';
		
		// ingen medlemmer?
		if (count($this->ff->members['members']) == 0)
		{
			echo '
<p class="c">'.ucfirst($this->ff->type['refobj']).' har ingen medlemmer.</p>';
		}
		
		// vis hvem som er medlem
		else
		{
			ess::$b->page->add_css('
.ff_list_member_pri3 td {
	
}
.ff_list_member_pri4 td {
	
}');
			
			$table_top = '
<form action="" method="post">
	<table class="table center tablem">
		<thead>
			<tr>
				<th>Medlem</th>
				<th>Dato medlem</th>
				<th>Sist pålogget</th>
				<th>Donert/<span title="Hvor mye spilleren har bidratt til '.$this->ff->type['refobj'].' i form av oppdrag">Bidrag(?)</span></th>
				<th>Tjent</th>
			</tr>
		</thead>
		<tbody>';
			
			$table_bottom = '
		</tbody>
	</table>';
			
			
			// kan ha parent?
			if ($this->ff->type['parent'])
			{
				// list opp eier og medeier
				$i = 0;
				$top = false;
				for ($x = 1; $x <= 2; $x++)
				{
					if (isset($this->ff->members['members_priority'][$x]))
					{
						foreach ($this->ff->members['members_priority'][$x] as $member)
						{
							if (!$top)
							{
								echo $table_top;
								$top = true;
							}
							
							echo $this->show_member_row($member, ++$i);
						}
					}
				}
				if ($top) echo $table_bottom;
				
				// list opp hver pri3 med tilhørende pri4
				$parents = $this->ff->members['members_parent'];
				if (isset($this->ff->members['members_priority'][3]))
				{
					foreach ($this->ff->members['members_priority'][3] as $pri3)
					{
						$i = 0;
						echo $table_top;
						echo $this->show_member_row($pri3, ++$i, 'ff_list_member_pri3');
						
						// pri4s?
						if (isset($this->ff->members['members_parent'][$pri3->id]))
						{
							foreach ($this->ff->members['members_parent'][$pri3->id] as $member)
							{
								echo $this->show_member_row($member, ++$i, 'ff_list_member_pri4');
							}
						}
						unset($parents[$pri3->id]);
						
						echo $table_bottom;
					}
				}
				
				// noen uten pri3?
				foreach ($parents as $pri3_id => $members)
				{
					$i = 0;
					echo $table_top;
					foreach ($members as $member)
					{
						echo $this->show_member_row($member, ++$i, 'ff_list_member_pri4');
					}
					echo $table_bottom;
				}
			}
			
			// vis alle i samme tabell
			else
			{
				echo $table_top;
				
				$i = 0;
				foreach ($this->ff->members['members_priority'] as $rows)
				{
					foreach ($rows as $member)
					{
						echo $this->show_member_row($member, ++$i);
					}
				}
				
				echo $table_bottom;
			}
			
			echo '
	<p class="c">'.show_sbutton("Kast ut", 'name="kick"').' '.show_sbutton("Endre posisjon", 'name="change_priority"').'</p>
</form>';
		}
		
		$this->ff->load_page();
	}
	
	/**
	 * Lag tabellrad for et medlem
	 * @param ff_member $member
	 * @param int $i
	 * @param string $class ekstra class på tr
	 * @return string <tr>
	 */
	protected function show_member_row(ff_member $member, $i, $class = null)
	{
		return '
		<tr class="box_handle'.($i % 2 == 0 ? ' color' : '').($class ? ' '.$class : '').'">
			<td><input type="checkbox" name="up_id[]" value="'.$member->id.'"'.(!$this->ff->mod && ($member->id == login::$user->player->id || $member->data['ffm_priority'] == 1) ? ' disabled="disabled"' : '').' /> '.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'<br /><b>'.ucfirst($member->get_priority_name()).'</b></td>
			<td class="r">'.ess::$b->date->get($member->data['ffm_date_join'])->format().'</td>
			<td class="r">'.ess::$b->date->get($member->data['up_last_online'])->format().'<br />'.game::timespan($member->data['up_last_online'], game::TIME_ABS).'</td>
			<td class="r">'.game::format_cash($member->data['ffm_donate']).'<br />'.game::format_cash($member->data['ffm_earnings_ff']).'</td>
			<td class="r">'.game::format_cash($member->data['ffm_earnings']).'</td>
		</tr>';
	}
	
	/**
	 * Godta forslag om en spiller
	 */
	protected function suggestion_accept()
	{
		// sjekk spilleren
		if (!isset($_POST['up_id']))
		{
			ess::$b->page->add_message("Du må merke en spiller først.", "error");
			redirect::handle();
		}
		
		// er foreslått?
		$up_id = (int) $_POST['up_id'];
		if (!isset($this->ff->members['suggested'][$up_id]))
		{
			ess::$b->page->add_message("Spilleren er ikke foreslått som medlem til {$this->ff->type['refobj']}.", "error");
			redirect::handle();
		}
		
		// hent oversikt over ledige plasser og finn ut hvilken posisjon det gjelder
		$data = $this->ff->check_limits();
		$member = $this->ff->members['suggested'][$up_id];
		
		// har vi ikke plass til flere pri3/4s?
		if ($data['priorities'][$member->data['ffm_priority']]['free'] == 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Det er ikke plass til flere spillere med posisjon {$this->ff->type['priority'][$member->data['ffm_priority']]}.");
			redirect::handle();
		}
		
		if ($this->ff->data['ff_is_crew'] == 0 && !$this->ff->mod)
		{
			if ($this->ff->type['type'] == "familie")
			{
				$limit = ff::MAX_FAMILIES;
				$text = "broderskap";
				$where = "ff_type = 1";
			}
			else
			{
				$limit = ff::FIRMS_MEMBERS_LIMIT;
				$text = "firmaer";
				$where = "ff_type != 1";
			}
			
			// medlem av for mange FF av denne "typen"?
			$result = ess::$b->db->query("
				SELECT COUNT(ff_id)
				FROM ff JOIN ff_members ON ffm_ff_id = ff_id
				WHERE ffm_up_id = $up_id AND $where AND ff_is_crew = 0 AND ff_inactive = 0 AND (ffm_status = 0 OR ffm_status = 1)");
			if (mysql_result($result, 0) >= $limit)
			{
				ess::$b->page->add_message("Spilleren er allerede medlem av eller invitert til for mange $text.", "error");
				redirect::handle();
			}
		}
		
		// godta forslag
		$member->suggestion_accept();
		
		// melding
		ess::$b->page->add_message('Forslaget ble godtatt. <user id="'.$member->id.'" /> er nå invitert til '.$this->ff->type['refobj'].' som '.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'.');
		redirect::handle();
	}
	
	/**
	 * Avslå forslag om spiller
	 */
	protected function suggestion_decline()
	{
		if (!isset($_POST['up_id']))
		{
			ess::$b->page->add_message("Du må merke en spiller først.", "error");
			redirect::handle();
		}
		
		// er foreslått?
		$up_id = (int) $_POST['up_id'];
		if (!isset($this->ff->members['suggested'][$up_id]))
		{
			ess::$b->page->add_message("Spilleren er ikke foreslått som medlem til {$this->ff->type['refobj']}.", "error");
			redirect::handle();
		}
		
		// avslå forslag
		$member = $this->ff->members['suggested'][$up_id];
		$member->suggestion_decline();
		
		// melding
		ess::$b->page->add_message('Du trakk tilbake forslaget om å invitere <user id="'.$member->id.'" /> til '.$this->ff->type['refobj'].' som '.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'.');
		redirect::handle();
	}
	
	/**
	 * Invitere en spiller
	 */
	protected function invite()
	{
		// hent oversikt over ledige plasser
		$limits_data = $this->ff->check_limits();
		
		// har vi noen ledige plasser?
		if ($limits_data['total_free'] == 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Det er ingen ledige plasser i {$this->ff->type['refobj']}.", "error");
			redirect::handle();
		}
		
		ess::$b->page->add_title("Inviter spiller");
		$player = false;
		
		// begrensning i antall ff man kan være med i
		if ($this->ff->type['type'] == "familie")
		{
			$type_limit = ff::MAX_FAMILIES;
			$type_text = "broderskap";
			$type_where = "ff_type = 1";
		}
		else
		{
			$type_limit = ff::FIRMS_MEMBERS_LIMIT;
			$type_text = "firmaer";
			$type_where = "ff_type != 1";
		}
		
		// finne spiller?
		if (isset($_POST['player']) || isset($_REQUEST['up_id']))
		{
			// hent spillerinformasjon
			$where = isset($_REQUEST['up_id']) ? 'up_id = '.intval($_REQUEST['up_id']) : 'up_name = '.ess::$b->db->quote($_POST['player']);
			$more = isset($_REQUEST['up_id']) ? '' : ' ORDER BY up_access_level = 0, up_last_online DESC LIMIT 1';
			$result = ess::$b->db->query("
				SELECT up_id, up_name, up_access_level, up_points, upr_rank_pos, uc_time, uc_info, COUNT(ff_id) ff_num
				FROM users_players
					LEFT JOIN users_players_rank ON upr_up_id = up_id
					JOIN users ON up_u_id = u_id
					LEFT JOIN users_contacts ON uc_u_id = u_id AND uc_contact_up_id = ".login::$user->player->id." AND uc_type = 2
					LEFT JOIN ff_members ON ffm_up_id = up_id AND (ffm_status = 0 OR ffm_status = 1)
					LEFT JOIN ff ON ff_id = ffm_ff_id AND ff_is_crew = 0 AND ff_inactive = 0 AND $type_where
				WHERE $where
				GROUP BY up_id$more");
			$row = mysql_fetch_assoc($result);
			
			// fant ikke spilleren?
			if (!$row || !$row['up_id'])
			{
				ess::$b->page->add_message("Fant ikke spilleren med ".(isset($_REQUEST['up_id']) ? "id #".intval($_REQUEST['up_id']) : "navn <b>".htmlspecialchars($_POST['player'])."</b>").".", "error");
			}
			
			else
			{
				// er i FF?
				if (isset($this->ff->members['list'][$row['up_id']]))
				{
					ess::$b->page->add_message('<user id="'.$row['up_id'].'" /> er allerede foreslått, invitert eller medlem av '.$this->ff->type['refobj'].'.', "error");
				}
				
				// død/deaktivert?
				elseif ($row['up_access_level'] == 0)
				{
					ess::$b->page->add_message('<user id="'.$row['up_id'].'" /> er død og kan ikke inviteres.', "error");
				}
				
				// blokkert?
				elseif ($row['uc_time'] && !$this->ff->mod)
				{
					$reason = game::bb_to_html($row['uc_info']);
					$reason = empty($reason) ? '' : ' Begrunnelse: '.$reason;
					ess::$b->page->add_message('Denne spilleren blokkerte deg '.ess::$b->date->get($row['uc_time'])->format().'. Du kan derfor ikke invitere spilleren til '.$this->ff->type['refobj'].'.'.$reason, "error");
				}
				
				// medlem av for mange FF?
				elseif ($this->ff->data['ff_is_crew'] == 0 && $row['ff_num'] >= $type_limit && !$this->ff->mod)
				{
					ess::$b->page->add_message("Spilleren er allerede medlem av eller invitert til for mange $type_text.", "error");
				}
				
				else
				{
					$player = $row;
				}
			}
		}
		
		// har ikke funnet spiller?
		if (!$player || $_SERVER['REQUEST_METHOD'] == "GET")
		{
			// vis skjema for å finne spiller
			ess::$b->page->add_title("Velg spiller");
			
			echo '
<div class="section" style="width: 200px">
	<h1>Inviter spiller</h1>
	<p class="h_right"><a href="medlemmer?ff_id='.$this->ff->id.'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<p>Skriv inn navn på spilleren du vil invitere til '.$this->ff->type['refobj'].'.</p>
		<dl class="dd_right">
			<dt>Spillernavn</dt>
			<dd><input type="text" name="player" value="'.htmlspecialchars(postval("player", $player ? $player['up_name'] : '')).'" class="styled w100" /></dd>
		</dl>
		<p class="c">
			'.show_sbutton("Finn spiller").'
			<a href="medlemmer?ff_id='.$this->ff->id.'">Tilbake</a>
		</p>
	</form>
</div>';
			
			$this->ff->load_page();
		}
		
		// sett opp rank informasjon for spilleren
		$rank_info = game::rank_info($player['up_points'], $player['upr_rank_pos'], $player['up_access_level']);
		
		// fjern eier og medeier posisjonen om nødvendig
		if (!$this->ff->mod)
		{
			unset($limits_data['priorities'][1]);
			if ($this->ff->uinfo->data['ffm_priority'] > 1) unset($limits_data['priorities'][2]);
		}
		
		// valg posisjon?
		if (isset($_POST['pick_priority']))
		{
			// har ikke valgt posisjon?
			$priority = isset($_POST['priority']) && isset($limits_data['priorities'][$_POST['priority']]) ? $limits_data['priorities'][$_POST['priority']] : false;
			if (!isset($_POST['priority']))
			{
				ess::$b->page->add_message("Du må velge en posisjon.", "error");
			}
			
			// gyldig posisjon?
			elseif (!$priority || ($priority['max'] == -1 && !$this->ff->mod))
			{
				ess::$b->page->add_message("Ugyldig posisjon.", "error");
			}
			
			// har ikke høy nok rank?
			elseif ($rank_info['number'] < $priority['min_rank'] && !$this->ff->mod)
			{
				ess::$b->page->add_message('<user id="'.$row['up_id'].'" /> har ikke høy nok rank for å bli '.$this->ff->type['priority'][$priority['priority']].".", "error");
			}
			
			// ingen ledige plasser?
			elseif ($priority['free'] == 0 && !$this->ff->mod)
			{
				ess::$b->page->add_message("Det er ingen ledige plasser som ".$this->ff->type['priority'][$priority['priority']].".", "error");
			}
			
			// ingen pri3 for overordnet?
			elseif ($this->ff->type['parent'] && $priority['priority'] == 4 && !isset($this->ff->members['members_priority'][3]))
			{
				ess::$b->page->add_message("Det finnes ingen {$this->ff->type['priority'][3]} du kan tilegne en {$this->ff->type['priority'][4]}. Du må først sette en spiller som <b>{$this->ff->type['priority'][3]}</b> før du kan invitere en {$this->ff->type['priority'][4]}.", "error");
			}
			
			else
			{
				$parent = $this->pick_parent($priority, null, $player, 'medlemmer?ff_id='.$this->ff->id.'&amp;invite&amp;up_id='.$player['up_id'], '
		<input type="hidden" name="pick_priority" />', true);
				
				// godkjent?
				if (isset($_POST['confirm']) && validate_sid(false))
				{
					// inviter spilleren
					if ($this->ff->player_invite($player['up_id'], $priority['priority'], $parent))
					{
						ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> ble invitert til '.$this->ff->type['refobj'].' som '.$this->ff->type['priority'][$priority['priority']].($parent ? ' underordnet <user id="'.$parent.'" />' : '').'.');
						redirect::handle();
					}
					else
					{
						ess::$b->page->add_message("Noe gikk galt. Kunne ikke invitere spilleren.", "error");
					}
				}
				
				// vis bekreftskjema
				ess::$b->page->add_title("Godkjenn invitasjon");
				
				echo '
<div class="section" style="width: '.($parent ? '220' : '150').'px">
	<h1>Godkjenn invitasjon</h1>
	<p class="h_right"><a href="medlemmer?ff_id='.$this->ff->id.'&amp;invite&amp;up_id='.$player['up_id'].'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<input type="hidden" name="up_id" value="'.$player['up_id'].'" />
		<input type="hidden" name="priority" value="'.$priority['priority'].'" />
		<input type="hidden" name="pick_priority" />'.($parent ? '
		<input type="hidden" name="parent" value="'.$parent.'" />' : '').'
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<p>Informasjon:</p>
		<dl class="dd_right">
			<dt>Spiller</dt>
			<dd>'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</dd>
			<dt>Posisjon</dt>
			<dd>'.ucfirst($this->ff->type['priority'][$priority['priority']]).($parent ? ' underordnet <user id="'.$parent.'" />' : '').'</dd>
		</dl>'.($this->ff->data['ff_is_crew'] != 0 && $player['ff_num'] >= $type_limit ? '
		<p>Spilleren er egentlig medlem av eller invitert til for mange andre '.$type_text.'.</p>' : '').($rank_info['number'] < $priority['min_rank'] ? '
		<p>Spilleren har i utgangspunktet for lav rank.</p>' : '').($priority['free'] == 0 ? '
		<p>Det er i utgangspunktet ingen ledige plasser for denne posisjonen.</p>' : '').'
		<p class="c">
			'.show_sbutton("Inviter spiller", 'name="confirm"').'
			<a href="medlemmer?ff_id='.$this->ff->id.'&amp;invite&amp;up_id='.$player['up_id'].'">Tilbake</a>
		</p>
	</form>
</div>';
				$this->ff->load_page();
			}
		}
		
		ess::$b->page->add_title("Velg posisjon");
		
		// maks antall medlemmer en familie kan ha
		$members_limit_max = $this->ff->competition ? ff::MEMBERS_LIMIT_TOTAL_MAX_COMP : ff::MEMBERS_LIMIT_TOTAL_MAX;
		
		// vis oversikt over de ulike posisjonene man kan velge
		echo '
<div class="section" style="width: 400px">
	<h1>Inviter spiller</h1>
	<p class="h_right"><a href="medlemmer?ff_id='.$this->ff->id.'&amp;invite">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<p>Valgt spiller: '.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</p>
		<input type="hidden" name="player" value="'.htmlspecialchars($player['up_name']).'" />
		<input type="hidden" name="up_id" value="'.$player['up_id'].'" />
		<p>Du må nå velge en posisjon du ønsker spilleren skal få i '.$this->ff->type['refobj'].'. Maks antall plasser i '.$this->ff->type['refobj'].': '.$limits_data['max'].'.'.($this->ff->type['type'] == "familie" && $limits_data['max'] < $members_limit_max ? ' <a href="panel?ff_id='.$this->ff->id.'&amp;a=members_limit">Øk begrensning &raquo;</a>' : '').'</p>';
		
		$this->pick_position($limits_data, $rank_info['number']);
		
		echo '
		<p class="c">
			'.show_sbutton("Velg posisjon", 'name="pick_priority"').'
			<a href="medlemmer?ff_id='.$this->ff->id.'&amp;invite&amp;up_id='.$player['up_id'].'">Tilbake</a>
		</p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Trekk tilbake en invitasjon
	 */
	protected function invite_pullback()
	{
		if (!isset($_POST['up_id']))
		{
			ess::$b->page->add_message("Du må merke en spiller først.", "error");
			redirect::handle();
		}
		
		// er invitert?
		$up_id = (int) $_POST['up_id'];
		if (!isset($this->ff->members['invited'][$up_id]))
		{
			ess::$b->page->add_message("Spilleren er ikke invitert til {$this->ff->type['refobj']}.", "error");
			redirect::handle();
		}
		
		// trekk tilbake invitasjonen
		$member = $this->ff->members['invited'][$up_id];
		$member->invite_pullback();
		
		// melding
		ess::$b->page->add_message('Du trakk tilbake invitasjonen for å invitere <user id="'.$member->id.'" /> til '.$this->ff->type['refobj'].' som '.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'.');
		redirect::handle();
	}
	
	/**
	 * Kaste ut et medlem
	 */
	protected function kick()
	{
		if (!isset($_POST['up_id']) && !isset($_POST['up_ids']))
		{
			ess::$b->page->add_message("Du må merke en eller flere spillere.", "error");
			redirect::handle();
		}
		
		// kontroller medlemmene
		$up_ids = isset($_POST['up_ids'])
			? array_map("intval", explode(",", $_POST['up_ids']))
			: array_map("intval", (array) $_POST['up_id']);
		$priority_list = array();
		$members = array();
		foreach ($up_ids as $up_id)
		{
			// er ikke medlem?
			if (!isset($this->ff->members['members'][$up_id]))
			{
				ess::$b->page->add_message('Spilleren <user id="'.$up_id.'" /> er ikke medlem av '.$this->ff->type['refobj'].'.', "error");
				redirect::handle();
			}
			$member = $this->ff->members['members'][$up_id];
			
			// kan ikke kaste ut seg selv
			if ($member->id == login::$user->player->id && !$this->ff->mod)
			{
				ess::$b->page->add_message('Du kan ikke kaste ut deg selv. Forlat '.$this->ff->type['refobj'].' via <a href="panel?ff_id='.$this->ff->id.'">panelet</a>.');
				redirect::handle();
			}
			
			// eier kan ikke kastes ut
			if ($member->data['ffm_priority'] == 1 && !$this->ff->mod)
			{
				ess::$b->page->add_message(ucfirst($this->ff->type['priority'][1])." kan ikke kastes ut av {$this->ff->type['refobj']}.");
				redirect::handle();
			}
			
			// har høyere prioritering?
			if ($member->data['ffm_priority'] <= $this->ff->uinfo->data['ffm_priority'] && !$this->ff->mod)
			{
				ess::$b->page->add_message('Du kan ikke kaste ut <user id="'.$member->id.'" /> som har høyere eller samme posisjon som deg.', "error");
				redirect::handle();
			}
			
			$members[] = $member;
			$priority_list[] = $member->data['ffm_priority'];
		}
		
		// ingen medlemmer?
		if (count($members) == 0)
		{
			ess::$b->page->add_message("Du må merke en eller flere spillere.", "error");
			redirect::handle();
		}
		
		// godkjent?
		if (isset($_POST['confirm']) && validate_sid())
		{
			// sorter slik at medlemmene med lavest posisjon kommer først
			array_multisort($priority_list, SORT_DESC, $members);
			
			// kast ut medlemmene
			foreach ($members as $member)
			{
				$member->kick(postval("note"));
			}
			
			// infomelding
			if (count($members) > 0)
			{
				$list = array();
				foreach ($members as $member)
				{
					$list[] = '<li><user id="'.$member->id.'" /> ('.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').')</li>';
				}
				ess::$b->page->add_message('Du kastet ut følgende spillere fra '.$this->ff->type['refobj'].':<ul>'.implode("", $list).'</ul>');
			}
			
			else
			{
				$member = reset($members);
				ess::$b->page->add_message('Du kastet ut <user id="'.$member->id.'" /> fra sin posisjon som '.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'.');
			}
			redirect::handle();
		}
		
		// sorter slik at medlemmene med høyest posisjon kommer først
		array_multisort($priority_list, $members);
		
		// vis skjema
		echo '
<form action="" method="post">
	<input type="hidden" name="kick" value="1" />
	<input type="hidden" name="up_ids" value="'.implode(",", $up_ids).'" />
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<div class="section" style="width: '.(count($members) == 1 ? '230' : '400').'px">
		<h1>Kast ut spiller</h1>
		<p class="h_right"><a href="medlemmer?ff_id='.$this->ff->id.'">Tilbake</a></p>';
		
		if (count($members) == 1)
		{
			$member = reset($members);
			
			echo '
		<p>Du er i ferd med å kaste ut <user id="'.$member->id.'" /> fra '.$this->ff->type['refobj'].'.</p>
		<dl class="dd_right">
			<dt>Posisjon</dt>
			<dd>'.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'</dd>
			<dt>Ble medlem</dt>
			<dd>'.ess::$b->date->get($member->data['ffm_date_join'])->format().'</dd>
			<dt>Sist pålogget</dt>
			<dd>'.ess::$b->date->get($member->data['up_last_online'])->format().'</dd>
		</dl>';
		}
		
		else
		{
			echo '
		<p>Du er i ferd med å kaste ut følgende spillere fra '.$this->ff->type['refobj'].':</p>
		<table class="table">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Posisjon</th>
					<th>Ble medlem</th>
					<th>Sist pålogget</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($members as $member)
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$member->id.'" /></td>
					<td>'.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'</td>
					<td class="nowrap">'.ess::$b->date->get($member->data['ffm_date_join'])->format().'</td>
					<td class="nowrap">'.ess::$b->date->get($member->data['up_last_online'])->format().'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
		}
		
		echo '
		<p>Begrunnelse: <i>(valgfritt)</i></p>
		<textarea name="note" rows="3" cols="5" style="width: 90%">'.htmlspecialchars(postval("note")).'</textarea>
		<p>Begrunnelsen vil bli gitt til spilleren og lagt til i loggen. Merk at '.fwords("spilleren", "spillerene", count($members)).' normalt vil være tilknyttet statistikken for '.$this->ff->type['refobj'].' i 12 timer etter utkastelse.</p>';
		
		// kontroller for underordnede spillere
		if ($this->ff->type['parent'])
		{
			$subs = array();
			$pri3_count = 0;
			foreach ($members as $member)
			{
				if ($member->data['ffm_priority'] == 3) $pri3_count++;
				if ($member->data['ffm_priority'] == 3 && isset($this->ff->members['members_parent'][$member->id]))
				{
					foreach ($this->ff->members['members_parent'][$member->id] as $member_sub)
					{
						// hopp over om det er en av de som skal kastes ut
						if (in_array($member_sub->id, $up_ids)) continue;
						
						$subs[] = '<li><user id="'.$member_sub->id.'" /></li>';
					}
				}
			}
			
			if (count($subs) > 0)
			{
				// er det noen pri3 etter alle blir kastet ut?
				$pri3 = count($this->ff->members['members_priority'][3]) - $pri3_count > 0;
				
				if (count($members) == 1)
				{
					echo '
		<p>Medlemmet har følgende medlemmer underordnet seg:</p>';
				}
				
				else
				{
					echo '
		<p>Følgende medlemmer er underordnet av en av spillerene du har valgt:</p>';
				}
				
				echo '
		<ul>'.implode("", $subs).'</ul>'.($pri3 ? '
		<p>Underordnede medlemmer vil bli flyttet til tilfeldige spillere med posisjon '.$this->ff->type['priority'][3].'.</p>' : '
		<p>Spilleren med posisjonen '.$this->ff->type['priority'][4].' som har vært medlem lengst bli utvalgt til '.$this->ff->type['priority'][3].'.').'</p>';
			}
		}
		
		echo '
		<p class="c">
			'.show_sbutton("Kast ut", 'name="confirm"').'
			<a href="medlemmer?ff_id='.$this->ff->id.'">Avbryt</a>
		</p>
	</div>
</form>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Endre posisjon for et medlem
	 */
	protected function change_priority()
	{
		ess::$b->page->add_title("Endre posisjon på medlem");
		
		// har ikke merket av en spiller?
		if (!isset($_REQUEST['up_id']) && !isset($_REQUEST['up_ids']))
		{
			ess::$b->page->add_message("Du må merke en eller flere spillere.", "error");
			redirect::handle();
		}
		
		// kontroller medlemmene
		$up_ids = isset($_REQUEST['up_ids'])
			? array_map("intval", explode(",", $_REQUEST['up_ids']))
			: array_map("intval", (array) $_REQUEST['up_id']);
		$priority_list = array();
		$members = array();
		$rank_points_low = null; // rankpoengene til spilleren med dårligst rank
		foreach ($up_ids as $up_id)
		{
			// er ikke medlem?
			if (!isset($this->ff->members['members'][$up_id]))
			{
				ess::$b->page->add_message('Spilleren <user id="'.$up_id.'" /> er ikke medlem av '.$this->ff->type['refobj'].'.', "error");
				redirect::handle();
			}
			$member = $this->ff->members['members'][$up_id];
			
			// kan ikke endre posisjon på seg selv
			if ($member->id == login::$user->player->id && !$this->ff->mod)
			{
				ess::$b->page->add_message('Du kan ikke omplassere deg selv.');
				redirect::handle();
			}
			
			// eier kan ikke omplasseres
			if ($member->data['ffm_priority'] == 1 && !$this->ff->mod)
			{
				ess::$b->page->add_message(ucfirst($this->ff->type['priority'][1])." kan ikke omplasseres.");
				redirect::handle();
			}
			
			// har høyere prioritering?
			if ($member->data['ffm_priority'] <= $this->ff->uinfo->data['ffm_priority'] && !$this->ff->mod)
			{
				ess::$b->page->add_message('Du kan ikke omplassere <user id="'.$member->id.'" /> som har høyere eller samme posisjon som deg.', "error");
				redirect::handle();
			}
			
			// sett opp rank informasjon for spilleren
			if ($rank_points_low === null || $member->data['up_points'] < $rank_points_low)
			{
				$rank_points_low = $member->data['up_points'];
			}
			
			$members[] = $member;
			$priority_list[] = $member->data['ffm_priority'];
		}
		
		// ingen medlemmer?
		$c = count($members);
		if ($c == 0)
		{
			ess::$b->page->add_message("Du må merke en eller flere spillere.", "error");
			redirect::handle();
		}
		
		// sorter slik at medlemmene med høyest posisjon kommer først
		array_multisort($priority_list, $members);
		ksort($priority_list);
		
		// sett opp nåværende prioritering hvis det kun er 1 spiller eller alle har samme prioritering
		$priority_old = array_unique($priority_list);
		if (count($priority_old) == 1)
		{
			$priority_old = $priority_old[0];
		}
		else
		{
			$priority_old = null;
		}
		
		// rank info for den dårligste ranken
		$rank_info = game::rank_info($rank_points_low);
		$rank_number = $rank_info['number'];
		
		// hent oversikt over ledige plasser og fjern eier/medeier posisjon om nødvendig
		$limits_data = $this->ff->check_limits($members);
		if (!$this->ff->mod)
		{
			unset($limits_data['priorities'][1]);
			if ($this->ff->uinfo->data['ffm_priority'] > 1) unset($limits_data['priorities'][2]);
		}
		
		// valg posisjon?
		if ((isset($_POST['pick_priority']) || isset($_POST['priority'])) && validate_sid())
		{
			// har ikke valgt posisjon?
			$priority = isset($_POST['priority']) && isset($limits_data['priorities'][$_POST['priority']]) ? $limits_data['priorities'][$_POST['priority']] : false;
			if (!isset($_POST['priority']))
			{
				ess::$b->page->add_message("Du må velge en posisjon.", "error");
			}
			
			// gyldig posisjon?
			elseif (!$priority || ($priority['max'] == -1 && !$this->ff->mod))
			{
				ess::$b->page->add_message("Ugyldig posisjon.", "error");
			}
			
			// har ikke høy nok rank?
			elseif ($rank_number < $priority['min_rank'] && !$this->ff->mod && $priority['priority'] != $priority_old)
			{
				ess::$b->page->add_message('En eller flere av spillerene valgt har ikke høy nok rank for å bli '.$this->ff->type['priority'][$priority['priority']].".", "error");
			}
			
			// ingen ledige plasser?
			elseif ($priority['free'] < $c && !$this->ff->mod && $priority['priority'] != $priority_old)
			{
				ess::$b->page->add_message("Det er ingen ledige plasser som ".$this->ff->type['priority'][$priority['priority']].".", "error");
			}
			
			// ingen pri3 for overordnet?
			elseif ($this->ff->type['parent'] && $priority['priority'] == 4 && $limits_data['priorities'][3]['members'] == 0)
			{
				ess::$b->page->add_message("Det finnes ingen spillere med posisjon {$this->ff->type['priority'][3]} du kan tilegne en {$this->ff->type['priority'][4]}. Du må først sette en spiller som {$this->ff->type['priority'][3]} før du kan sette en {$this->ff->type['priority'][4]}.", "error");
			}
			
			// ikke valgt ny posisjon?
			elseif ($priority['priority'] == $priority_old && (!$this->ff->type['parent'] || $priority['priority'] != 4))
			{
				ess::$b->page->add_message("Du må velge en annen posisjon enn den som er satt.");
			}
			
			else
			{
				// fjern de som er valgt og som allerede har denne prioriteringen
				if ($priority_old === null && ($priority['priority'] != 4 || !$this->ff->type['parent']))
				{
					foreach ($members as $key => $member)
					{
						if ($member->data['ffm_priority'] == $priority['priority'])
						{
							unset($members[$key]);
							unset($priority_list[$key]);
						}
					}
					$c = count($members);
				}
				
				$parent = $this->pick_parent($priority, $members, null, 'medlemmer?ff_id='.$this->ff->id.'&amp;change_priority&amp;up_ids='.implode(",", $up_ids), '
		<input type="hidden" name="change_priority" />
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />');
				
				// fjern de som er valgt og som har samme parent
				if ($priority['priority'] == 4 && $this->ff->type['parent'])
				{
					foreach ($members as $key => $member)
					{
						if ($member->data['ffm_priority'] == $priority['priority'] && $member->data['ffm_parent_up_id'] == $parent)
						{
							unset($members[$key]);
							unset($priority_list[$key]);
						}
					}
					$c = count($members);
				}
				
				// godkjent?
				if (isset($_POST['confirm']) && validate_sid(false))
				{
					// sorter slik at medlemmene med lavest posisjon kommer først
					array_multisort($priority_list, SORT_DESC, $members);
					
					// flytt spillerene
					$changed = array();
					$error = array();
					foreach ($members as $member)
					{
						// flytt spilleren
						$old_priority = $member->data['ffm_priority'];
						$old_parent = $member->data['ffm_parent_up_id'];
						if ($member->change_priority($priority['priority'], $parent))
						{
							$changed[] = '<user id="'.$member->id.'" /> fra '.$this->ff->type['priority'][$old_priority].($old_parent ? ' underordnet <user id="'.$old_parent.'" />' : '');
						}
						else
						{
							$error[] = '<user id="'.$member->id.'" /> ('.$this->ff->type['priority'][$old_priority].($old_parent ? ' underordnet <user id="'.$old_parent.'" />' : '').')';
						}
					}
					
					if (count($changed) == 1)
					{
						ess::$b->page->add_message("Du endret posisjonen til {$changed[0]} til ".$this->ff->type['priority'][$priority['priority']].($parent ? ' underordnet <user id="'.$parent.'" />' : '').'.');
					}
					elseif (count($changed) > 1)
					{
						ess::$b->page->add_message("Du endret posisjonene til følgende spillere til ".$this->ff->type['priority'][$priority['priority']].($parent ? ' underordnet <user id="'.$parent.'" />' : '').':<ul><li>'.implode("</li><li>", $changed).'</li></ul>');
					}
					
					if (count($error) == 1)
					{
						ess::$b->page->add_message("Posisjonen til {$error[0]} kunne ikke bli endret til ".$this->ff->type['priority'][$priority['priority']].($parent ? ' underordnet <user id="'.$parent.'" />' : '').'.', "error");
					}
					elseif (count($error) > 1)
					{
						ess::$b->page->add_message("Posisjonene til følgende spillere kunne ikke bli satt til ".$this->ff->type['priority'][$priority['priority']].($parent ? ' underordnet <user id="'.$parent.'" />' : '').':<ul><li>'.implode("</li><li>", $error).'</li></ul>');
					}
					
					redirect::handle();
				}
				
				// vis bekreftskjema
				ess::$b->page->add_title("Bekreft endring av posisjon");
				
				$width = 180;
				if ($c > 1) $width = 300;
				elseif ($parent || $members[0]->data['ffm_parent_up_id']) $width = 300;
				
				echo '
<div class="section" style="width: '.$width.'px">
	<h1>Bekreft endring av posisjon</h1>
	<p class="h_right"><a href="medlemmer?ff_id='.$this->ff->id.'&amp;change_priority&amp;up_ids='.implode(",", $up_ids).'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<input type="hidden" name="change_priority" />
		<input type="hidden" name="up_ids" value="'.implode(",", $up_ids).'" />
		<input type="hidden" name="priority" value="'.$priority['priority'].'" />'.($parent ? '
		<input type="hidden" name="parent" value="'.$parent.'" />' : '').'
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />';
				
				if ($c == 1)
				{
					$member = $members[0];
					
					echo '
		<dl class="dd_right">
			<dt>Spiller</dt>
			<dd>'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'</dd>
			<dt>Nåværende posisjon</dt>
			<dd>'.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'</dd>
			<dt>Ny posisjon</dt>
			<dd>'.ucfirst($this->ff->type['priority'][$priority['priority']]).($parent ? ' underordnet <user id="'.$parent.'" />' : '').'</dd>
		</dl>';
				}
				
				else
				{
					$list = array();
					foreach ($members as $member)
					{
						$list[] = '<li><user id="'.$member->id.'" /> ('.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').')</li>';
					}
					
					echo '
		<p>Spillere som flyttes:</p>
		<ul>'.implode("", $list).'</ul>
		<dl class="dd_right">
			<dt>Ny posisjon</dt>
			<dd>'.ucfirst($this->ff->type['priority'][$priority['priority']]).($parent ? ' underordnet <user id="'.$parent.'" />' : '').'</dd>
		</dl>';
				}
				
				echo ($rank_number < $priority['min_rank'] ? '
		<p>En eller flere spillere har i utgangspunktet for lav rank.</p>' : '').($priority['free'] < $c && $priority['priority'] != $priority_old ? '
		<p>Det er i utgangspunktet ikke mange nok ledige plasser for denne posisjonen.</p>' : '');
				
				// kontroller for underordnede spillere
				if ($this->ff->type['parent'])
				{
					$subs = array();
					$pri3_count = 0;
					foreach ($members as $member)
					{
						if ($member->data['ffm_priority'] == 3) $pri3_count++;
						if ($member->data['ffm_priority'] == 3 && isset($this->ff->members['members_parent'][$member->id]))
						{
							foreach ($this->ff->members['members_parent'][$member->id] as $member_sub)
							{
								// hopp over om det er en av de som skal bytte plass
								if (in_array($member_sub->id, $up_ids)) continue;
								
								$subs[] = '<li><user id="'.$member_sub->id.'" />'.($c == 1 ? '' : '(Underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />)').'</li>';
							}
						}
					}
					
					if (count($subs) > 0)
					{
						// er det noen pri3 etter alle blir kastet ut?
						$pri3 = count($this->ff->members['members_priority'][3]) - $pri3_count > 0;
						
						if ($c == 1)
						{
							echo '
		<p>Medlemmet har følgende medlemmer underordnet seg:</p>';
						}
						
						else
						{
							echo '
		<p>Følgende medlemmer er underordnet av en av spillerene du har valgt:</p>';
						}
						
						echo '
		<ul>'.implode("", $subs).'</ul>'.($pri3 ? '
		<p>Underordnede medlemmer vil bli flyttet til tilfeldige spillere med posisjon '.$this->ff->type['priority'][3].'.</p>' : '
		<p>Spilleren med posisjonen '.$this->ff->type['priority'][4].' som har vært medlem lengst bli utvalgt til '.$this->ff->type['priority'][3].'.').'</p>';
					}
				}
				
				echo '
		<p class="c">
			'.show_sbutton("Endre posisjon", 'name="confirm"').'
			<a href="medlemmer?ff_id='.$this->ff->id.'&amp;change_priority&amp;up_ids='.implode(",", $up_ids).'">Tilbake</a>
		</p>
	</form>
</div>';
						
				$this->ff->load_page();
			}
		}
		
		ess::$b->page->add_title("Velg ny posisjon");
		
		// vis oversikt over de ulike posisjonene man kan velge
		echo '
<div class="section" style="width: 400px">
	<h1>Velg ny posisjon</h1>
	<p class="h_right"><a href="medlemmer?ff_id='.$this->ff->id.'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">';
		
		if ($c == 1)
		{
			$member = reset($members);
			
			echo '
		<p>Valgt spiller: '.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).' ('.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').')</p>';
		}
		else
		{
			$list = array();
			foreach ($members as $member)
			{
				$list[] = '<li>'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).' ('.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').')</li>';
			}
			
			echo '
		<p>Valgte spillere:</p>
		<ul>'.implode("", $list).'</ul>';
		}
		
		echo '
		<input type="hidden" name="change_priority" />
		<input type="hidden" name="up_ids" value="'.implode(",", $up_ids).'" />
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<p>Du må nå velge ny posisjon du ønsker '.fword("spilleren", "spillerene", $c).' skal få i '.$this->ff->type['refobj'].'.</p>';
		
		$this->pick_position($limits_data, $rank_info['number'], $members);
		
		echo '
		<p class="c">
			'.show_sbutton("Velg posisjon", 'name="pick_priority"').'
			<a href="medlemmer?ff_id='.$this->ff->id.'">Tilbake</a>
		</p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Plukk ut en parent
	 * @param array $priority
	 * @param array $members spillerene som skal flyttes
	 * @param array $player (enten $member eller $player må sendes med, den andre null)
	 * @param string $back_link (skal være html safe)
	 */
	protected function pick_parent($priority, $members = null, $player, $back_link, $form_html = null, $invite = false)
	{
		// ikke pri4 eller parent aktivert?
		if ($priority['priority'] != 4 || !$this->ff->type['parent']) return null;
		
		$parent = null;
		
		// har vi valgt pri3?
		if (isset($_POST['parent']))
		{
			$ok = true;
			
			// gyldig pri3?
			$pri3_id = (int) $_POST['parent'];
			if (!isset($this->ff->members['members_priority'][3][$pri3_id]))
			{
				ess::$b->page->add_message("Fant ikke valgt {$this->ff->type['priority'][3]}.", "error");
				$ok = false;
			}
			
			// kontroller at det er en gyldig pri3 og om ingen parents endret?
			if ($ok && $members)
			{
				$changed = false;
				foreach ($members as $member)
				{
					if (!$changed && ($priority['priority'] != $member->data['ffm_priority'] || $pri3_id != $member->data['ffm_parent_up_id']))
					{
						$changed = true;
					}
					
					// seg selv?
					if ($member->id == $pri3_id)
					{
						ess::$b->page->add_message("Du kan ikke velge en spiller du skal flytte.", "error");
						$ok = false;
						break;
					}
				}
				
				if (!$changed)
				{
					ess::$b->page->add_message(fword("Medlemmet", "Medlemmene", count($members))." er allerede underordnet denne {$this->ff->type['priority'][3]}.", "error");
					$ok = false;
				}
			}
			
			if ($ok)
			{
				return $pri3_id;
			}
		}
		
		// vis oversikt over pri3 man kan velge mellom
		ess::$b->page->add_title("Velg overordnet {$this->ff->type['priority'][3]}");
		
		$text = "";
		$up_ids = array();
		if (!$members || count($members) == 1)
		{
			$member = $members ? $members[0] : null;
			$up_ids[] = $member ? $member->id : $player['up_id'];
			$text = '
		<p>Du må velge en '.$this->ff->type['priority'][3].' som skal være overordnet for '.($member ? game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']) : game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level'])).' som vil '.($invite ? 'invitert' : 'få posisjonen').' som '.$this->ff->type['priority'][4].'.'.($member ? ' (Nåværende posisjon: '.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').')' : '').'</p>';
		}
		
		else
		{
			$list = array();
			foreach ($members as $member)
			{
				$up_ids[] = $member->id;
				$list[] = '<li><user id="'.$member->id.'" /> ('.$member->get_priority_name().($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').')</li>';
			}
			
			$text = '
		<p>Du må velge en '.$this->ff->type['priority'][3].' som skal være overordnet for følgende spillere:</p>
		<ul>'.implode("", $list).'</ul>';
		}
		
		echo '
<div class="section" style="width: 400px">
	<h1>Velg overordnet '.$this->ff->type['priority'][3].'</h1>
	<p class="h_right"><a href="'.$back_link.'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">'.$form_html.'
		<input type="hidden" name="up_id'.($members ? 's' : '').'" value="'.($members ? implode(",", $up_ids) : $player['up_id']).'" />
		<input type="hidden" name="priority" value="'.$priority['priority'].'" />'.$text.'
		<table class="table center">
			<thead>
				<tr>
					<th>'.ucfirst($this->ff->type['priority'][3]).'</th>
					<th>Sist pålogget</th>
					<th>'.ucfirst($this->ff->type['priority'][4]).'</th>
					<th>Inviterte '.$this->ff->type['priority'][4].'</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		foreach ($this->ff->members['members_priority'][3] as $member_parent)
		{
			if ($members && in_array($member_parent->id, $up_ids)) continue;
			
			echo '
				<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
					<td><input type="radio" name="parent" value="'.$member_parent->id.'" /> '.game::profile_link($member_parent->id, $member_parent->data['up_name'], $member_parent->data['up_access_level']).'</td>
					<td class="r">'.ess::$b->date->get($member_parent->data['up_last_online'])->format().'</td>
					<td class="r">'.(isset($this->ff->members['members_parent'][$member_parent->id]) ? count($this->ff->members['members_parent'][$member_parent->id]) : 0).'</td>
					<td class="r">'.(isset($this->ff->members['invited_parent'][$member_parent->id]) ? count($this->ff->members['invited_parent'][$member_parent->id]) : 0).'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
		<p class="c">
			'.show_sbutton("Velg {$this->ff->type['priority'][3]}").'
			<a href="'.$back_link.'">Tilbake</a>
		</p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Skjema for å velge en posisjon
	 * @param array $limits_data
	 * @param int $rank_number
	 * @param array $members ff_members liste over medlemmene som flyttes
	 */
	protected function pick_position($limits_data, $rank_number, $members = null)
	{
		echo '
		<table class="table center">
			<thead>
				<tr>
					<th>Posisjon</th>
					<th>'.$this->title.'</th>
					<th>Invitert</th>
					<th>Maks</th>
					<th>Ledige</th>
					<th>Rankkrav</th>
				</tr>
			</thead>
			<tbody>';
		
		$active_priority = null;
		if ($members)
		{
			foreach ($members as $member)
			{
				if ($active_priority === null) $active_priority = $member->data['ffm_priority'];
				elseif ($active_priority != $member->data['ffm_priority'])
				{
					$active_priority = null;
					break;
				} 
			}
		}
		
		$i = 0;
		foreach ($limits_data['priorities'] as $priority)
		{
			// ikke vise?
			if ($priority['max'] == -1 && !$this->ff->mod) continue;
			
			$rank_ok = $rank_number >= $priority['min_rank'];
			$disabled = (!$rank_ok || $priority['free'] == 0) && $priority['priority'] != $active_priority;
			
			echo '
				<tr class="r box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
					<td class="l"><input type="radio" name="priority" value="'.$priority['priority'].'"'.($disabled && !$this->ff->mod ? ' disabled="disabled"' : '').($priority['priority'] == $active_priority ? ' checked="checked"' : '').' /> '.ucfirst($this->ff->type['priority'][$priority['priority']]).'</td>
					<td>'.$priority['members'].'</td>
					<td>'.$priority['invited'].'</td>
					<td>'.($priority['max'] == -1 ? '<i>Deaktivert</i>' : ($priority['max'] == 0 ? '<i>Ingen</i>' : $priority['max'])).'</td>
					<td'.($priority['free'] == 0 ? ' style="background-color: #FF0000 !important"' : '').'>'.$priority['free'].'</td>
					<td class="l"'.(!$rank_ok ? ' style="background-color: #FF0000 !important"' : '').'>'.game::$ranks['items_number'][$priority['min_rank']]['name'].'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>';
	}
}