<?php

class page_min_side_user
{
	public static function main()
	{
		echo '
<p class="minside_toplinks sublinks">
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/information.png" alt="" />Info', "").'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/money.png" alt="" />Pluss-tjenester', "pluss").'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/group.png" alt="" />Vervede', "vervede").(page_min_side::$active_own || access::has("mod") ? '
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/computer.png" alt="" />Økter', "ses").'
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/asterisk_orange.png" alt="" />Innstillinger', "set") : '');
		
		if (page_min_side::$active_user->active && (page_min_side::$active_own || access::has("mod")))
		{
			echo '
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/delete.png" alt="" />Deaktiver', "deact");
		}
		
		if (access::has("crewet")) echo '
	'.page_min_side::link('<img src="'.STATIC_LINK.'/icon/key.png" alt="" />Crew', "crew");
		
		// spesielle tilganger?
		$access = "";
		if (page_min_side::$active_user->data['u_access_level'] != 0 && page_min_side::$active_user->data['u_access_level'] != 1)
		{
			$type = access::type(page_min_side::$active_user->data['u_access_level']);
			$type_name = access::name($type);
			if (!empty($type_name))
			{
				$class = access::html_class($type);
				$access .= '<span class="'.$class.'">'.htmlspecialchars($type_name).'</span>';
			}
		}
		
		ess::$b->page->add_css('
.minside_access {
	text-align: center;
	font-size: 15px;
	margin: 1em 0;
}
	');
		
		echo '
</p>
<div id="page_user_info" class="user">'.(page_min_side::$active_own ? '' : '
	<h1>'.htmlspecialchars(page_min_side::$active_user->data['u_email']) . ' (#'.page_min_side::$active_user->id.')'.(page_min_side::$active_user->data['u_access_level'] != 0 ? '' : '<br />(deaktivert '.ess::$b->date->get(page_min_side::$active_user->data['u_deactivated_time'])->format(date::FORMAT_NOTIME).')').'<br />
		' . page_min_side::$active_player->profile_link() . ' (#'.page_min_side::$active_player->id.')</h1>').($access != "" ? '
	<div class="minside_access">'.$access.'</div>' : '');
		
		// informasjon
		if (page_min_side::$subpage == "")
			self::page_default();
		
		// pluss-tjeneste
		elseif (page_min_side::$subpage == "pluss")
			self::page_pluss();
		
		// vervede spillere
		elseif (page_min_side::$subpage == "vervede")
			self::page_vervede();
		
		// økter
		elseif (page_min_side::$subpage == "ses" && (page_min_side::$active_own || access::has("mod")))
			self::page_ses();
		
		// innstillinger
		elseif (page_min_side::$subpage == "set" && (page_min_side::$active_own || access::has("mod")))
			self::page_set();
		
		// crewlogg
		elseif (page_min_side::$subpage == "crewlog" && access::has("crewet", NULL, NULL, "login"))
			self::page_crewlog();
		
		// deaktivere brukeren som moderator
		elseif (page_min_side::$subpage == "deact" && access::has("mod"))
			self::page_deact_mod();
		
		// endre deaktivering
		elseif (page_min_side::$subpage == "cdeact" && access::has("mod"))
			self::page_cdeact();
		
		// deaktivere brukeren
		elseif (page_min_side::$subpage == "deact" && page_min_side::$active_own)
			self::page_deact();
		
		// aktivere brukeren
		elseif (page_min_side::$subpage == "activate" && access::has("mod"))
			self::page_activate();
		
		// crew
		elseif (page_min_side::$subpage == "crew" && access::has("crewet", NULL, NULL, "login"))
			self::page_crew();
		
		// spillere tilhørende brukeren
		elseif (page_min_side::$subpage == "up" && (page_min_side::$active_own || access::is_nostat()))
			self::page_up();
		
		else
			redirect::handle(page_min_side::addr(""));
		
		echo '
</div>';
	}
	
	protected static function page_default()
	{
		global $_lang;
		$mod = access::has("mod");
		
		// 	fødselsdato
		$birth = explode("-", page_min_side::$active_user->data['u_birth']);
		
		// alder
		$date = ess::$b->date->get();
		$n_day = $date->format("j");
		$n_month = $date->format("n");
		$n_year = $date->format("Y");
		
		if (!empty(page_min_side::$active_user->data['u_birth']))
		{
			$age = ($n_year - $birth[0] - (($n_month < $birth[1] || ($birth[1] == $n_month && $n_day < $birth[2])) ? 1 : 0));
		}
		
		echo '
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Basisinformasjon<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						<dl class="dd_right">
							<dt>Brukerens ID</dt>
							<dd>#'.page_min_side::$active_user->id.'</dd>
							<dt>Opprettet</dt>
							<dd>'.ess::$b->date->get(page_min_side::$active_user->data['u_created_time'])->format().'</dd>'.(page_min_side::$active_user->id != login::$user->id ? '
							<dt>Sist pålogget</dt>
							<dd>'.ess::$b->date->get(page_min_side::$active_user->data['u_online_time'])->format().'</dd>' : '').'
							<dt>E-postadresse</dt>
							<dd>'.(page_min_side::$active_own || access::has("mod")
								? '<a href="'.htmlspecialchars(page_min_side::addr("set", "b=email")).'" class="user_edit_box" rel="email">'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</a>'.($mod ? ' (<a href="admin/brukere/finn?email='.urlencode(page_min_side::$active_user->data['u_email']).'">søk</a>)' : '')
								: htmlspecialchars(page_min_side::$active_user->data['u_email'])).'</dd>
							<dt>Fødselsdato</dt>
							<dd>'.(access::has("mod") ? '<a href="'.htmlspecialchars(page_min_side::addr("crew", "b=birth")).'">' : '').(empty(page_min_side::$active_user->data['u_birth']) || page_min_side::$active_user->data['u_birth'] == "0000-00-00"
								? 'Ukjent'
								: intval($birth[2]).". ".$_lang['months'][intval($birth[1])]." ".$birth[0].' ('.$age.' år)').(access::has("mod") ? '</a>' : '').'</dd>'.(!empty(page_min_side::$active_user->data['u_phone']) || access::has("mod") ? '
							<dt>Mobilnummer</dt>
							<dd>'.(access::has("mod") ? '<a href="'.htmlspecialchars(page_min_side::addr("crew", "b=phone")).'" title="Endre nummer">' : '').(empty(page_min_side::$active_user->data['u_phone']) ? 'Ikke registrert' : htmlspecialchars(page_min_side::$active_user->data['u_phone'])).(access::has("mod") ? '</a>' : '').'</dd>' : '').'
							<dt>IP-adresse registrert med</dt>'.(empty(page_min_side::$active_user->data['u_created_ip']) ? '
							<dd class="dark">Ukjent</dd>' : '
							<dd>'.($mod ? '<a href="admin/brukere/finn?ip='.urlencode(page_min_side::$active_user->data['u_created_ip']).'">'.htmlspecialchars(page_min_side::$active_user->data['u_created_ip']).'</a>' : htmlspecialchars(page_min_side::$active_user->data['u_created_ip'])).'</dd>').'
							<dt>Nåværende IP-adresse</dt>
							<dd>'.($mod ? '<a href="admin/brukere/finn?ip='.urlencode(page_min_side::$active_user->data['u_online_ip']).'">'.htmlspecialchars(page_min_side::$active_user->data['u_online_ip']).'</a>' : htmlspecialchars(page_min_side::$active_user->data['u_online_ip'])).'</dd>';
		
		if (page_min_side::$active_user->data['u_created_referer'] != "")
		{
			$referer = preg_replace("/\\|/", "\n", page_min_side::$active_user->data['u_created_referer'], 1);
			echo '
							<dt>Henvisning</dt>
							<dd>'.game::format_data($referer).'</dd>';
		}
		
		// har vi blitt vervet av noen?
		$result = ess::$b->db->query("SELECT r.up_id, r.up_name, r.up_access_level FROM users_players r JOIN users_players ref ON ref.up_u_id = ".page_min_side::$active_user->id." AND ref.up_recruiter_up_id = r.up_id LIMIT 1");
		if ($row = mysql_fetch_assoc($result))
		{
			echo '
							<dt>Rekrutert av</dt>
							<dd>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</dd>';
		}
		
		echo '
						</dl>
					</div>
				</div>
			</div>
		</div>';
		
		// høyre kolonne
		echo '
		<div class="col_w right">
			<div class="col">';
		
		// deaktivert?
		if (page_min_side::$active_user->data['u_access_level'] == 0)
		{
			// deaktivert av seg selv?
			$deact_self = false;
			if (!empty(page_min_side::$active_user->data['u_deactivated_up_id']))
			{
				$result = ess::$b->db->query("SELECT u_id FROM users JOIN users_players ON u_id = up_u_id WHERE up_id = ".page_min_side::$active_user->data['u_deactivated_up_id']);
				$row = mysql_fetch_assoc($result);
				mysql_free_result($result);
				if ($row && $row['u_id'] == page_min_side::$active_user->id) $deact_self = true;
			}
			
			echo '
				<div class="bg1_c">
					<h1 class="bg1">Deaktivert<span class="left2"></span><span class="right2"></span></h1>'.(access::has("mod") ? '
					<p class="h_right"><a href="'.htmlspecialchars(page_min_side::addr("cdeact")).'">rediger</a> <a href="'.htmlspecialchars(page_min_side::addr("activate")).'">aktiver</a></p>' : '').'
					<div class="bg1">'.($deact_self ? '
						<p>Denne brukeren deaktiverte seg selv '.ess::$b->date->get(page_min_side::$active_user->data['u_deactivated_time'])->format(date::FORMAT_SEC).'.</p>' : '
						<p>Denne brukeren ble deaktivert '.ess::$b->date->get(page_min_side::$active_user->data['u_deactivated_time'])->format(date::FORMAT_SEC).' av '.(empty(page_min_side::$active_user->data['u_deactivated_up_id']) ? 'en ukjent bruker' : '<user id="'.page_min_side::$active_user->data['u_deactivated_up_id'].'" />').'.</p>').'
						<div class="p"><b>Begrunnelse:</b> '.(empty(page_min_side::$active_user->data['u_deactivated_reason']) ? 'Ingen begrunnelse oppgitt.' : game::bb_to_html(page_min_side::$active_user->data['u_deactivated_reason'])).'</div>'.(!$deact_self || !empty(page_min_side::$active_user->data['u_deactivated_note']) ? '
						<div class="p"><b>Intern informasjon:</b> '.(access::has("mod") ? (empty(page_min_side::$active_user->data['u_deactivated_note']) ? 'Ingen intern informasjon oppgitt.' : game::bb_to_html(page_min_side::$active_user->data['u_deactivated_note'])) : 'Du har ikke tilgang til å se intern informasjon.').'</div>' : '').'
					</div>
				</div>';
		}
		
		// har brukeren full tilgang?
		elseif (page_min_side::$active_user->lock_state && access::has("crewet"))
		{
			echo '
				<div class="bg1_c">
					<h1 class="bg1">Begrenset tilgang<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">
						<p>Denne brukeren har begrenset tilgang fordi:</p>
						<ul class="spacer">';
			
			foreach (page_min_side::$active_user->lock as $l)
			{
				switch ($l)
				{
					case "birth":
						echo '
							<li>Brukeren har ikke lagt inn fødselsdatoen.</li>';
					break;
					
					case "player":
						echo '
							<li>Brukeren har ingen levende spiller.</li>';
					break;
				}
			}
			
			echo '
						</ul>
					</div>
				</div>';
		}
		
		// hent spillerene tilhørende denne personen
		$pagei = new pagei(pagei::ACTIVE_GET, "side_up", pagei::PER_PAGE, 7);
		$result = $pagei->query("
			SELECT up_id, up_name, up_access_level, up_created_time, up_last_online, up_points, up_deactivated_time, upr_rank_pos
			FROM users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE up_u_id = ".page_min_side::$active_user->id."
			ORDER BY up_last_online DESC");
		
		echo '
				<div class="bg1_c">
					<h1 class="bg1">Spillere tilhørende brukeren<span class="left2"></span><span class="right2"></span></h1>'.(access::is_nostat() || page_min_side::$active_own ? '
					<p class="h_right">'.page_min_side::link("Mer info &raquo;", "up").'</p>' : '').'
					<div class="bg1">
						<table class="table '.($pagei->pages == 1 ? 'tablem' : 'tablemt').'" style="width: 100%">
							<thead>
								<tr>
									<th>Spiller</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>';
		
		while ($row = mysql_fetch_assoc($result))
		{
			$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
			echo '
								<tr>
									<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'], true, 'min_side?up_id='.$row['up_id']).'<br /><span style="font-size: 10px">'.$rank['name'].'</span></td>
									<td style="font-size: 10px">
										Opprettet: '.ess::$b->date->get($row['up_created_time'])->format().'<br />'.($row['up_access_level'] == 0 ? '
										Deaktivert: '.ess::$b->date->get($row['up_deactivated_time'])->format() : '
										Status: I live<br />
										Sist pålogget: '.ess::$b->date->get($row['up_last_online'])->format()).'
									</td>
								</tr>';
		}
		
		echo '
							</tbody>
						</table>'.($pagei->pages > 1 ? '
						<p class="c">'.$pagei->pagenumbers().'</p>' : '').'
					</div>
				</div>
			</div>
		</div>
	</div>';
	}
	
	/**
	 * Pluss-tjenester
	 */
	protected static function page_pluss()
	{
		echo '
	<p class="c"><a href="'.ess::$s['rpath'].'/node/46">Les mer om pluss-tjenester</a></p>';
	}
	
	/**
	 * Vervede spillere
	 */
	protected static function page_vervede()
	{
		global $__server;
		ess::$b->page->add_title("Vervede spillere");
		
		// sortering
		$sort = new sorts("sort");
		$sort->append("asc", "Spillernavn", "rec.up_name");
		$sort->append("desc", "Spillernavn", "rec.up_name DESC");
		$sort->append("asc", "Sist pålogget", "rec.up_last_online");
		$sort->append("desc", "Sist pålogget", "rec.up_last_online DESC");
		$sort->append("asc", "Registrert", "rec.up_created_time");
		$sort->append("desc", "Registrert", "rec.up_created_time DESC");
		$sort->append("asc", "Rankbonus", "u2.u_recruiter_points_bonus");
		$sort->append("desc", "Rankbonus", "u2.u_recruiter_points_bonus DESC");
		$sort->set_active(requestval("sort"), 5);
		
		// hent spillerene vi har vervet
		$sort_info = $sort->active();
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
		$result = $pagei->query("
			SELECT rec.up_id, rec.up_name, rec.up_access_level, rec.up_last_online, rec.up_created_time, u2.u_recruiter_points_bonus
			FROM
				users_players rec,
				users_players self,
				users u1,
				users u2
			WHERE u1.u_id = ".page_min_side::$active_user->id." AND self.up_u_id = u1.u_id AND self.up_id = rec.up_recruiter_up_id AND u2.u_id = rec.up_u_id
			ORDER BY {$sort_info['params']}");
		
		echo '
	<div class="bg1_c xmedium">
		<h1 class="bg1">Vervede spillere<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<ol>
				<li>Du gir ut denne linken til en du ønsker å verve: <a href="'.$__server['path'].'/'.page_min_side::$active_player->id.'" target="_blank">'.$__server['path'].'/'.page_min_side::$active_player->id.'</a></li>
				<li>Personen åpner linken</li>
				<li>Når personen registrerer seg vil brukeren være vervet av deg</li>
			</ol>
			<p class="c"><a href="'.ess::$s['relative_path'].'/node/60">Mer informasjon om verving &raquo;</a></p>';
		
		if ($pagei->total == 0)
		{
			echo '
			<p class="c">Du har ikke vervet noen spillere.</p>';
		}
		
		else
		{
			echo '
			<p>Du har vervet '.$pagei->total.' spiller'.($pagei->total == 1 ? '' : 'e').':</p>
			<table class="table spacerfix center'.($pagei->pages == 1 ? ' tablemb' : '').'">
				<thead>
					<tr>
						<td>Spiller <nobr>'.$sort->show_link(0, 1).'</nobr></td>
						<td>Sist pålogget <nobr>'.$sort->show_link(2, 3).'</nobr></td>
						<td>Tid vervet <nobr>'.$sort->show_link(4, 5).'</nobr></td>
						<td>Rankbonus <nobr>'.$sort->show_link(6, 7).'</nobr></td>
					</tr>
				</thead>
				<tbody>';
			
			$color = true;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
					<tr'.($color = !$color ? ' class="color"' : '').'>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>
						<td class="r">'.ess::$b->date->get($row['up_created_time'])->format().'</td>
						<td class="r">'.game::format_number($row['u_recruiter_points_bonus']).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>'.($pagei->pages > 1 ? '
			<p class="c">'.$pagei->pagenumbers().'</p>' : '');
		}
		
		echo '
		</div>
	</div>';
	}
	
	/**
	 * Økter
	 */
	protected static function page_ses()
	{
		function vis_ip_list($ip)
		{
			static $mod = NULL;
			if (is_null($mod)) $mod = access::has("mod");
			
			$list = explode(";", $ip);
			if ($mod)
			{
				foreach ($list as &$val)
				{
					$val = '<a href="'.ess::$s['rpath'].'/admin/brukere/finn?ip='.$val.'">'.$val.'</a>';
				}
			}
			
			return implode("<br />\n", $list);
		}
		
		// logge ut noen økter
		if (isset($_POST['delete']))
		{
			$delete = array();
			if (isset($_POST['session']))
			{
				foreach ($_POST['session'] as $del)
				{
					$del = intval($del);
					if ($del != 0) $delete[] = $del;
				}
			}
			
			if (count($delete) == 0)
			{
				ess::$b->page->add_message("Fant ingen økter å logge ut.", "error");
				redirect::handle(page_min_side::addr());
			}
			
			else
			{
				// forsøk å logg ut de merkede øktene
				$delete = implode(",", $delete);
				ess::$b->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_active = 1 AND ses_expire_time > ".time()." AND ses_u_id = ".page_min_side::$active_user->id." AND ses_id != ".login::$info['ses_id']." AND FIND_IN_SET(ses_id, '$delete')");
				
				$dels = ess::$b->db->affected_rows();
				ess::$b->page->add_message("<b>$dels</b> økt".($dels == 1 ? '' : 'er')." ble logget ut.");
				redirect::handle(page_min_side::addr());
			}
		}
		
		ess::$b->page->add_title("Økter");
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1">Aktive økter<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Her er en oversikt over alle stedene hvor brukeren er logget inn uten å ha blitt logget ut manuelt og som fortsatt er aktive.</p>';
		
		$time = time();
		$result = ess::$b->db->query("SELECT * FROM sessions WHERE ses_u_id = ".page_min_side::$active_user->id." AND ses_expire_time > $time AND ses_active = 1 ORDER BY ses_id DESC");
		
		echo '
			<form action="" method="post">
				<table class="table center">
					<thead>
						<tr>
							<th>Opprettet (<a href="#" class="box_handle_toggle" rel="session[]">Merk alle</a>)</th>
							<th>IP-er</th>
							<th>Type</th>
							<th>Varighet</th>
							<th>Hits</th>
							<th>Siste visning</th>
						</tr>
					</thead>
					<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$class = new attr("class");
			if ($row['ses_id'] != login::$info['ses_id']) $class->add("box_handle");
			
			$i++;
			if ($row['ses_id'] == login::$info['ses_id']) $class->add("highlight"); 
			elseif ($i % 2 == 0) $class->add("color");
			
			$type = $row['ses_expire_type'];
			$type = $type == LOGIN_TYPE_TIMEOUT ? 'Tidsavbrudd' : ($type == LOGIN_TYPE_BROWSER ? 'Lukke nettleser' : 'Alltid innlogget');
			echo '
						<tr'.$class->build().'>
							<td class="r">'.($row['ses_id'] == login::$info['ses_id'] ? '' : '<input type="checkbox" name="session[]" value="'.$row['ses_id'].'" /> ').ess::$b->date->get($row['ses_created_time'])->format("d.m.Y H:i").'</td>
							<td>'.vis_ip_list($row['ses_ip_list']).'</td>
							<td class="c">'.$type.'</td>
							<td class="c">'.($row['ses_expire_type'] == LOGIN_TYPE_ALWAYS ? 'Alltid' : game::timespan($row['ses_expire_time'], game::TIME_ABS)).'</td>
							<td class="r">'.game::format_number($row['ses_hits']).'</td>
							<td class="r">'.ess::$b->date->get($row['ses_last_time'])->format("d.m.Y H:i").($row['ses_last_time'] != 0 ? '<br />'.game::timespan($row['ses_last_time'], game::TIME_ABS) : '').'</td>
						</tr>';
		}
		
		echo '
					</tbody>
				</table>
				<p class="c">'.show_sbutton("Logg ut merkede", 'name="delete"').'</p>
			</form>
		</div>
	</div>';
		
		// hent øktene på denne siden
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 7);
		$result = $pagei->query("SELECT ses_id, ses_created_time, ses_ip_list, ses_expire_type, ses_expire_time, ses_active, ses_hits, ses_last_time, ses_last_ip FROM sessions WHERE ses_u_id = ".page_min_side::$active_user->id." ORDER BY ses_last_time DESC");
		
		echo '
	<div class="bg1_c">
		<h1 class="bg1">Tidligere økter<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Dette er en oversikt over alle innlogginger på brukeren.</p>
			<table class="table'.($pagei->pages == 1 ? ' tablemb' : '').' center">
				<thead>
					<tr>
						<th>ID</th>
						<th>Opprettet</th>
						<th>IP</th>
						<th>Type</th>
						<th>Status</th>
						<th>Hits</th>
						<th>Siste visning</th>
					</tr>
				</thead>
				<tbody>';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$type = $row['ses_expire_type'];
			$type = $type == LOGIN_TYPE_TIMEOUT ? 'Tidsavbrudd' : ($type == LOGIN_TYPE_BROWSER ? 'Lukke nettleser' : 'Alltid innlogget');
			echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
						<td class="r">'.$row['ses_id'].'</td>
						<td class="r">'.ess::$b->date->get($row['ses_created_time'])->format("d.m.Y H:i").'<br /><span style="color: #888">'.game::timespan($row['ses_created_time'], game::TIME_ABS).'</span></td>
						<td class="c">'.vis_ip_list($row['ses_ip_list']).'</td>
						<td class="c">'.$type.'</td>
						<td class="c">'.($row['ses_active'] == 1 ? ($row['ses_expire_time'] < $time ? 'Ikke aktiv' : ($row['ses_expire_type'] == LOGIN_TYPE_ALWAYS ? '<b>Aktiv</b><br />Alltid logget inn' : '<b>'.game::timespan($row['ses_expire_time'], game::TIME_ABS).'</b>')) : 'Logget ut').'</td>
						<td class="r">'.game::format_number($row['ses_hits']).'</td>
						<td class="r">'.ess::$b->date->get($row['ses_last_time'])->format("d.m.Y H:i").($row['ses_last_time'] != 0 ? '<br /><span style="color: #888">'.game::timespan($row['ses_last_time'], game::TIME_ABS).'</span>' : '').'</td>
					</tr>';
		}
		
		echo '
				</tbody>
			</table>'.($pagei->pages > 1 ? '
			<p class="c">'.$pagei->pagenumbers().'</p>' : '').'
		</div>
	</div>';
	}
	
	/**
	 * Innstillinger
	 */
	protected static function page_set()
	{
		global $__server;
		
		$subpage2 = getval("b");
		redirect::store(page_min_side::addr(NULL, ($subpage2 != "" ? "b=" . $subpage2 : '')));
		ess::$b->page->add_title("Innstillinger");
		ess::$b->page->add_css('
.minside_set_links .active { color: #CCFF00 }');
		
		echo '
	<p class="c minside_set_links">
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "")).'"'.($subpage2 == "" ? ' class="active"' : '').'>Generelt</a> |
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=email")).'"'.($subpage2 == "email" ? ' class="active"' : '').'>Skift e-postadresse</a> |
		<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=pass")).'"'.($subpage2 == "pass" ? ' class="active"' : '').'>Skift passord</a>
	</p>';
		
		// endre passord?
		if ($subpage2 == "pass")
		{
			ess::$b->page->add_title("Endre passord");
			
			// må logge inn med utvidede tilganger
			if (isset(login::$extended_access) && !login::$extended_access['authed'])
			{
				echo '
	<div class="bg1_c center" style="width: 350px">
		<h1 class="bg1">Skift passord<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<p>Du må logge inn med utvidede tilganger for å få tilgang til denne funksjonen.</p>
		</div>
	</div>';
			}
			
			// moderator?
			elseif (access::has("mod") && (page_min_side::$active_user->id != login::$user->id))
			{
				// kan ikke endre denne brukerens passord?
				if (page_min_side::$active_user->data['u_access_level'] != 0 && page_min_side::$active_user->data['u_access_level'] != 1 && !access::has("sadmin"))
				{
					echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Skift passord<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du har ikke rettigheter til å endre passordet til denne brukeren. Kun senioradministrator kan gjøre det.</p>
		</div>
	</div>';
				}
				
				else
				{
					// lagre passordet?
					if (isset($_POST['pass']))
					{
						$pass = trim(postval("pass"));
						$error = password::validate($pass, password::LEVEL_LOGIN);
						$log = trim(postval("log"));
						
						if ($error > 0)
						{
							$errors = array();
							
							if ($error & password::ERROR_SHORT)
							{
								$errors[] = 'Passordet er for kort. Må være minimum 8 tegn.';
							}
							
							if ($error & password::ERROR_NONCAP || $error & password::ERROR_CAP || $error & password::ERROR_NUM)
							{
								$errors[] = 'Passordet må inneholde både små bokstaver, store bokstaver og tall.';
							}
							
							ess::$b->page->add_message(implode('<br />', $errors), "error");
						}
						
						// mangler logg?
						elseif ($log == "")
						{
							ess::$b->page->add_message("Mangler logg melding.", "error");
						}
						
						else
						{
							// samme passord?
							if (password::verify_hash($pass, page_min_side::$active_user->data['u_pass'], 'user'))
							{
								ess::$b->page->add_message("Passordet er det samme som nåværende. Velg et annet.", "error");
							}
							
							else
							{
								$pass_new = password::hash($pass, null, 'user');

								// lagre endringer
								ess::$b->db->query("UPDATE users SET u_pass = ".ess::$b->db->quote($pass_new)." WHERE u_id = ".page_min_side::$active_user->id);
								
								// legg til crewlogg
								crewlog::log("user_password", page_min_side::$active_player->id, $log, array("pass_old" => page_min_side::$active_user->data['u_pass'], "pass_new" => $pass_new));
								
								ess::$b->page->add_message("Passordet ble endret.");
							}
						}
					}
					
					echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Endre passord<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p class="r">Tilgangsnivå: Moderator</p>
			<p>Her endrer du passordet til '.page_min_side::$active_player->profile_link().'.</p>
			<form action="" method="post" autocomplete="off">
				<dl class="dd_right dl_2x">
					<dt>Nytt passord</dt>
					<dd><input type="password" value="" name="pass" id="pass" class="styled w120" /></dd>
					<dt>Begrunnelse for endring</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
					<dd>'.show_sbutton("Lagre").'</dd>
				</dl>
			</form>
		</div>
	</div>';
				}
			}
			
			else
			{
				// lagre passord
				if (isset($_POST['save_pass']))
				{
					// kontroller alle feltene
					$pass_old = trim(postval("pass_old"));
					$pass_new = trim(postval("pass_new"));
					$pass_repeat = trim(postval("pass_repeat"));
					
					// kontroller at alle feltene er fylt ut
					if ($pass_old == "" || $pass_new == "" || $pass_repeat == "")
					{
						ess::$b->page->add_message("Alle feltene må fylles ut.", "error");
					}
					
					// kontroller gammelt passord
					elseif (!password::verify_hash($pass_old, page_min_side::$active_user->data['u_pass'], 'user'))
					{
						ess::$b->page->add_message("Det gamle passordet stemte ikke.", "error");
					}
					
					// kontroller nytt passord og repeat
					elseif ($pass_new != $pass_repeat)
					{
						ess::$b->page->add_message("De nye passordene var ikke like.", "error");
					}
					
					// samme passord som før?
					elseif ($pass_old == $pass_new)
					{
						ess::$b->page->add_message("Du må velge et nytt passord.", "error");
					}
					
					// kontroller krav (minst 6 tegn)
					elseif (strlen($pass_new) < 6)
					{
						ess::$b->page->add_message("Det nye passordet må inneholde minimum 6 tegn.", "error");
					}
					
					// for enkelt passord?
					elseif (password::validate($pass_new, password::LEVEL_LOGIN) != 0)
					{
						ess::$b->page->add_message("Du må velge et vanskeligere passord.", "error");
					}
					
					// samme passord som i banken?
					elseif (password::verify_hash($pass_new, page_min_side::$active_user->data['u_bank_auth'], 'bank_auth'))
					{
						ess::$b->page->add_message("Velg et annet passord enn du har i banken.");
					}
					
					// endre passordet
					else
					{
						ess::$b->db->query("UPDATE users SET u_pass = ".ess::$b->db->quote(password::hash($pass_new, null, 'user'))." WHERE u_id = ".page_min_side::$active_user->id);
						
						// melding
						ess::$b->page->add_message("Passordet ble endret. Alle andre steder brukeren var logget inn er nå logget ut.");
						putlog("NOTICE", "%bPASSORD-ENDRING%b: %u".page_min_side::$active_player->data['up_name']."%u byttet passordet på sin bruker. {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
						
						// logg ut alle andre brukere
						ess::$b->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_active = 1 AND ses_u_id = ".page_min_side::$active_user->id." AND ses_id != ".login::$info['ses_id']);
						
						redirect::handle();
					}
				}
				
				echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Skift passord<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>For å kunne skifte passord må alle 3 feltene være fylt ut.</p>
			<form action="" method="post" autocomplete="off">
				<dl class="dd_right dl_2x">
					<dt>Nåværende passord</dt>
					<dd><input type="password" class="styled w100" name="pass_old" /></dd>
					<dt>Nytt passord</dt>
					<dd><input type="password" class="styled w100" name="pass_new" /></dd>
					<dt>Gjenta nytt passord</dt>
					<dd><input type="password" class="styled w100" name="pass_repeat" /></dd>
				</dl>
				<p class="c">'.show_sbutton("Skift passordet", 'name="save_pass"').'</p>
			</form>
		</div>
	</div>';
			}
		}
		
		// endre e-postadresse?
		elseif ($subpage2 == "email")
		{
			// skifte e-postadresse?
			/* Trinn i skifte e-postadresse:
				1. Skriver inn ønsket e-postadresse man vil skifte til
				2. E-post blir sendt til gammel e-postadresse med info og link til validering
				3. Validering av gammel e-postadresse (step 1)
				4. E-post blir sendt til ny e-postadresse med info og link til validering
				5. E-potadresse blir skiftet (step 2)
			*/
			
			ess::$b->page->add_title("Skifte e-postadresse");
			
			// må logge inn med utvidede tilganger
			if (isset(login::$extended_access) && !login::$extended_access['authed'])
			{
				echo '
	<div class="bg1_c center" style="width: 350px">
		<h1 class="bg1">Skifte e-postadresse<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<p>Du må logge inn med utvidede tilganger for å få tilgang til denne funksjonen.</p>
		</div>
	</div>';
			}
			
			// moderator?
			elseif (access::has("mod") && (page_min_side::$active_user->id != login::$user->id || isset($_GET['o'])))
			{
				// kan ikke endre denne brukerens e-postadresse?
				if (page_min_side::$active_user->data['u_access_level'] != 0 && page_min_side::$active_user->data['u_access_level'] != 1 && !access::has("sadmin"))
				{
					echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Skifte e-postadresse<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du har ikke rettigheter til å skifte e-postadressen til denne brukeren. Kun senioradministrator kan gjøre det.</p>
		</div>
	</div>';
				}
				
				else
				{
					// lagre ny e-post?
					$email_ex = false;
					if (isset($_POST['email']))
					{
						$email = trim(postval("email"));
						$log = trim(postval("log"));
						
						// sjekk om e-postadressen allerede er i bruk
						$result = ess::$b->db->query("SELECT u_id, up_id, up_name, up_access_level FROM users LEFT JOIN users_players ON up_id = u_active_up_id WHERE u_email = ".ess::$b->db->quote($email)." AND u_access_level != 0");
						$email_ex = mysql_fetch_assoc($result);
						
						// ikke gyldig e-postadresse?
						if (!game::validemail($email))
						{
							ess::$b->page->add_message("Ugyldig e-postadresse.", "error");
						}
						
						// mangler logg?
						elseif (empty($log))
						{
							ess::$b->page->add_message("Du må fylle inn en loggmelding.", "error");
						}
						
						// samme e-postadresse?
						elseif ($email == page_min_side::$active_user->data['u_email'])
						{
							ess::$b->page->add_message("Du må skrive inn en ny e-postadresse.");
						}
						
						// finnes e-posten allerede?
						elseif ($email_ex && !isset($_POST['ignore_ex']))
						{
							ess::$b->page->add_message("Denne e-posten er allerede i bruk av ".game::profile_link($email_ex['up_id'], $email_ex['up_name'], $email_ex['up_access_level']).". Bekreft at du ønsker å la begge brukerene ha denne e-postadresse, evt. endre til en annen e-postadresse.");
						}
						
						else
						{
							// lagre endringer
							ess::$b->db->query("UPDATE users SET u_email = ".ess::$b->db->quote($email)." WHERE u_id = ".page_min_side::$active_user->id);
							
							// legg til crewlogg
							crewlog::log("user_email", page_min_side::$active_player->id, $log, array("email_old" => page_min_side::$active_user->data['u_email'], "email_new" => $email));
							
							// fjern mulige params for egen bytting av e-post
							page_min_side::$active_user->params->remove("change_email_step");
							page_min_side::$active_user->params->remove("change_email_new_address");
							page_min_side::$active_user->params->remove("change_email_hash");
							page_min_side::$active_user->params->remove("change_email_time", true);
							
							ess::$b->page->add_message("E-postadressen ble endret.");
							redirect::handle(page_min_side::addr(""));
						}
					}
					
					echo '
	<div class="bg1_c center" style="width: 350px">
		<h1 class="bg1">Skifte e-postadresse<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<form action="" method="post" autocomplete="off">
				<dl class="dd_right dl_2x">
					<dt>Nåværende e-postadresse</dt>
					<dd>'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</dd>
					<dt>Ny e-postadresse</dt>
					<dd><input type="text" value="'.htmlspecialchars(postval("email", page_min_side::$active_user->data['u_email'])).'" name="email" id="email" class="styled w150" /></dd>
					<dt>Begrunnelse for endring</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p class="c">'.($email_ex ? show_sbutton("Lagre ny e-postadresse, ignorer advarsel", 'name="ignore_ex"') : show_sbutton("Lagre ny e-postadresse")).'</p>
			</form>
		</div>
	</div>';
				}
			}
			
			else
			{
				// blokkert fra å skifte e-postadressen?
				$blokkering = blokkeringer::check(blokkeringer::TYPE_EPOST);
				if ($blokkering)
				{
					ess::$b->page->add_message("Du er blokkert fra å skifte e-postadressen din. Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
					redirect::handle(page_min_side::addr(""));
				}
				
				// hent status
				$status = page_min_side::$active_user->params->get("change_email_step", false);
				$email_addr = page_min_side::$active_user->params->get("change_email_new_address");
				
				$html_pre = '
	<div class="bg1_c center" style="width: 350px">
		<h1 class="bg1">Skifte e-postadresse<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />';
				
				$html_suf = '
		</div>
	</div>';
				
				$in_use = false;
				$expire = false;
				if ($status)
				{
					// se om e-postadressen allerede er i bruk
					$result = ess::$b->db->query("SELECT COUNT(u_id) FROM users WHERE u_email = ".ess::$b->db->quote($email_addr)." AND u_access_level != 0");
					$in_use = mysql_result($result, 0) > 0;
				}
				
				// gått for lang tid?
				elseif ($status && page_min_side::$active_user->params->get("change_email_time")+86400 < time())
				{
					$expire = true;
				}
				
				// avbryte?
				if ((isset($_POST['abort']) && $status) || $in_use || $expire)
				{
					if ($in_use)
					{
						// logg
						putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u kunne ikke skifte e-postadresse fordi den nye adressen er i bruk (ville skifte fra %u".page_min_side::$active_user->data['u_email']."%u til %u".$email_addr."%u) {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
						
						// info
						ess::$b->page->add_message("E-postadressen <b>".htmlspecialchars($email_addr)."</b> har blitt benyttet av en annen bruker. Du kan ikke skifte til denne e-postadressen.", "error");
					}
					
					elseif ($expire)
					{
						// logg
						putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u kunne ikke skifte e-postadresse fordi det ble brukt for lang tid (egentlig startet ".ess::$b->date->get(page_min_side::$active_user->params->get("change_email_time"))->format().") (ville skifte fra %u".page_min_side::$active_user->data['u_email']."%u til %u$email_addr%u) {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
						
						// info
						ess::$b->page->add_message("Du brukte for lang tid med å bekrefte e-postadressen. Skifting av e-post er avbrutt.", "error");
					}
					
					else
					{
						// logg
						putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u avbrøt skifting av e-postadresse (ville skifte fra %u".page_min_side::$active_user->data['u_email']."%u til %u$email_addr%u) {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
						
						// info
						ess::$b->page->add_message("Du har avbrutt skifting av e-postadresse.");
					}
					
					// fjern fra params
					page_min_side::$active_user->params->remove("change_email_step");
					page_min_side::$active_user->params->remove("change_email_new_address");
					page_min_side::$active_user->params->remove("change_email_hash");
					page_min_side::$active_user->params->remove("change_email_time", true);
					
					redirect::handle();
				}
				
				// behandle trinn 1
				if (isset($_GET['old']))
				{
					// er ikke på trinn 1 eller feil kode?
					if ($status != 1 || page_min_side::$active_user->params->get("change_email_hash") != $_GET['old'])
					{
						ess::$b->page->add_message("E-posten du har blitt henvist fra gjelder ikke lenger.", "error");
						redirect::handle();
					}
					
					// gå videre til neste trinn?
					if (isset($_POST['continue']) && validate_sid(false))
					{
						// generer kode
						$hash = substr(md5(uniqid("kofradia_")), 0, 16);
						
						// sett status
						page_min_side::$active_user->params->update("change_email_step", 2);
						page_min_side::$active_user->params->update("change_email_new_address", $email_addr);
						page_min_side::$active_user->params->update("change_email_hash", $hash);
						page_min_side::$active_user->params->update("change_email_time", time(), true);
						
						// send e-post til nye e-posten
						$email = new email();
						$email->text = 'Hei,

Du har bedt om å skifte e-postadressen for din spiller '.page_min_side::$active_player->data['up_name'].' på '.$__server['path'].'.
Den gamle e-postadressen har blitt bekreftet.

Gammel/nåværende e-postadresse: '.page_min_side::$active_user->data['u_email'].'
Ny e-postadresse: '.$email_addr.'

For å godta eller avslå dette gå inn på følgende adresse:
'.$__server['path'].'/min_side?u&a=set&b=email&new='.$hash.'

--
www.kofradia.no';
						$email->headers['X-SMafia-IP'] = $_SERVER['REMOTE_ADDR'];
						$email->headers['Reply-To'] = "henvendelse@smafia.no";
						$email->send($email_addr, "Skifte e-postadresse (bekrefte ny adresse)");
						
						// logg
						putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u har bekreftet gammel e-postadresse (%u".page_min_side::$active_user->data['u_email']."%u) og skal nå bekrefte %u$email_addr%u {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
						
						// info
						ess::$b->page->add_message("En e-post har blitt sendt til <b>".htmlspecialchars($email_addr)."</b> for bekreftelse.");
						redirect::handle();
					}
					
					echo $html_pre . '
		<p>Du har bekreftet nåværende e-postadresse.</p>
		<dl class="dd_right">
			<dt>Nåværende e-postadresse</dt>
			<dd>'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</dd>
			<dt>Ny ønsket e-postadresse</dt>
			<dd>'.htmlspecialchars($email_addr).'</dd>
		</dl>
		<p><u>Du må nå bekrefte den nye e-postadressen.</u></p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p class="c">
				'.show_sbutton("Send e-post for bekreftelse", 'name="continue"').'
				'.show_sbutton("Avbryt", 'name="abort"').'
			</p>
		</form>' . $html_suf;
				}
				
				// behandle trinn 2
				elseif (isset($_GET['new']))
				{
					// er ikke på trinn 2 eller feil kode?
					if ($status != 2 || page_min_side::$active_user->params->get("change_email_hash") != $_GET['new'])
					{
						ess::$b->page->add_message("E-posten du har blitt henvist fra gjelder ikke lenger.", "error");
						redirect::handle();
					}
					
					// fullføre skifting av e-postadresse?
					if (isset($_POST['confirm']) && validate_sid(false))
					{
						$note = trim(postval("note"));
						
						// mangler logg?
						if ($note == "")
						{
							ess::$b->page->add_message("Mangler begrunnelse.");
						}
						
						else
						{
							// lagre endringer
							ess::$b->db->query("UPDATE users SET u_email = ".ess::$b->db->quote($email_addr)." WHERE u_id = ".page_min_side::$active_user->id);
							
							// legg til crewlogg
							crewlog::log("user_email", page_min_side::$active_player->id, $note, array("email_old" => page_min_side::$active_user->data['u_email'], "email_new" => $email_addr));
							
							// logg
							putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u skiftet e-postadresse fra %u".page_min_side::$active_user->data['u_email']."%u til %u".$email_addr."%u {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
							
							// info
							ess::$b->page->add_message("E-postadressen ble skiftet fra <b>".htmlspecialchars(page_min_side::$active_user->data['u_email'])."</b> til <b>".htmlspecialchars($email_addr)."</b>.");
							
							// fjern fra params
							page_min_side::$active_user->params->remove("change_email_step");
							page_min_side::$active_user->params->remove("change_email_new_address");
							page_min_side::$active_user->params->remove("change_email_hash");
							page_min_side::$active_user->params->remove("change_email_time", true);
							
							redirect::handle();
						}
					}
					
					echo $html_pre . '
		<p>Du har bekreftet både den nåværende og den nye e-postadressen.</p>
		<dl class="dd_right">
			<dt>Nåværende e-postadresse</dt>
			<dd>'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</dd>
			<dt>Ny ønsket e-postadresse</dt>
			<dd><b><u>'.htmlspecialchars($email_addr).'</u></b></dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<dl class="dd_right">
				<dt>Begrunnelse</dt>
				<dd><textarea name="note" rows="5" cols="10" class="w200">'.htmlspecialchars(postval("note")).'</textarea></dd>
			</dl>
			<p class="c">
				'.show_sbutton("Skift e-postadresse", 'name="confirm"').'
				'.show_sbutton("Avbryt", 'name="abort"').'
			</p>
		</form>' . $html_suf;
				}
				
				// har ikke startet endring av e-postadrese?
				elseif (!$status)
				{
					// velge ny e-postadresse
					if (isset($_POST['new_email']) && validate_sid(false))
					{
						// se om e-postadressen allerede er i bruk
						$result = ess::$b->db->query("SELECT COUNT(u_id) FROM users WHERE u_email = ".ess::$b->db->quote($_POST['new_email'])." AND u_access_level != 0");
						$in_use = mysql_result($result, 0) > 0;
						
						// valider e-post
						$email_addr = $_POST['new_email'];
						$email_valid = game::validemail($email_addr);
						
						// kontroller om e-postadressen eller domenet er blokkert
						if ($email_valid)
						{
							$pos = strpos($email_addr, "@");
							$domain = strtolower(substr($email_addr, $pos + 1));
							
							$result = ess::$b->db->query("SELECT eb_id, eb_type FROM email_blacklist WHERE (eb_type = 'address' AND eb_value = ".ess::$b->db->quote($email_addr).") OR (eb_type = 'domain' AND eb_value = ".ess::$b->db->quote($domain).") ORDER BY eb_type = 'address' LIMIT 1");
							$error_email = mysql_fetch_assoc($result);
						}
						
						// ugyldig e-postadresse?
						if (!$email_valid)
						{
							ess::$b->page->add_message("Ugyldig e-postadresse.", "error");
						}
						
						// allerede i bruk?
						elseif ($in_use)
						{
							putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u forsøkte å skifte e-postadresse fra %u".page_min_side::$active_user->data['u_email']."%u til %u$email_addr%u som allerde er i bruk {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
							ess::$b->page->add_message("E-posten du ønsker å skifte til er allerede benyttet av en annen bruker.");
						}
						
						// blokkert e-postadresse?
						elseif ($error_email)
						{
							if ($error_email['eb_type'] == "address")
							{
								ess::$b->page->add_message("E-postadressen <b>".htmlspecialchars($email_addr)."</b> er blokkert og kan ikke benyttes.", "error");
							}
							else
							{
								ess::$b->page->add_message("Domenet <b>".htmlspecialchars($domain)."</b> er blokkert og kan ikke benyttes.", "error");
							}
						}
						
						else
						{
							// generer kode
							$hash = substr(md5(uniqid("kofradia_")), 0, 16);
							
							// sett status
							page_min_side::$active_user->params->update("change_email_step", 1);
							page_min_side::$active_user->params->update("change_email_new_address", $email_addr);
							page_min_side::$active_user->params->update("change_email_hash", $hash);
							page_min_side::$active_user->params->update("change_email_time", time(), true);
							
							// send e-post til gamle e-posten
							$email = new email();
							$email->text = 'Hei,

Du har bedt om å skifte e-postadressen for din spiller '.page_min_side::$active_player->data['up_name'].' på '.$__server['path'].'.

Gammel/nåværende e-postadresse: '.page_min_side::$active_user->data['u_email'].'
Ny e-postadresse: '.$email_addr.'

For å godta eller avslå dette gå inn på følgende adresse:
'.$__server['path'].'/min_side?u&a=set&b=email&old='.$hash.'

--
www.kofradia.no';
							$email->headers['X-SMafia-IP'] = $_SERVER['REMOTE_ADDR'];
							$email->headers['Reply-To'] = "henvendelse@smafia.no";
							$email->send(page_min_side::$active_user->data['u_email'], "Skifte e-postadresse (bekrefte gammel adresse)");
							
							// logg
							putlog("CREWCHAN", "%u".page_min_side::$active_player->data['up_name']."%u har startet skifting av e-postadresse fra %u".page_min_side::$active_user->data['u_email']."%u til %u$email_addr%u {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
							
							// info
							ess::$b->page->add_message("En e-post har blitt sendt til <b>".htmlspecialchars(page_min_side::$active_user->data['u_email'])."</b> for bekreftelse.");
							redirect::handle();
						}
					}
					
					echo $html_pre . '
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<dl class="dd_right">
				<dt>Nåværende e-postadresse</dt>
				<dd>'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</dd>
				<dt>Ny ønsket e-postadresse</dt>
				<dd><input type="text" name="new_email" value="'.htmlspecialchars(postval("new_email", "")).'" class="styled w150" /></dd>
			</dl>
			<p>Du vil motta en e-post for bekreftelse på den gamle e-postadressen, for deretter å få en bekreftelse på den nye før e-postadressen blir skiftet.</p>
			<p>Husk at du ikke har lov til å gi bort eller selge brukeren til andre personer. <u>Brukeren skal ikke brukes av andre enn deg.</u></p>
			<p class="c">'.show_sbutton("Fortsett").'</p>'.(access::has("mod") ? '
			<p class="c"><a href="'.htmlspecialchars(page_min_side::addr("set", "b=email&o")).'">Endre e-postadresse som moderator</a></p>' : '').'
		</form>' . $html_suf;
				}
				
				// vis info
				else
				{
					echo $html_pre . '
		<p>Du er i ferd med å skifte e-postadresse for brukeren din.</p>
		<p>Informasjon:</p>
		<dl class="dd_right">
			<dt>Nåværende e-postadresse</dt>
			<dd>'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</dd>
			<dt>Ny ønsket e-postadresse</dt>
			<dd>'.htmlspecialchars($email_addr).'</dd>
			<dt>Status</dt>
			<dd>'.($status == 1 ? 'Venter på bekreftelse av <b>gammel</b> e-postadresse.' : 'Venter på bekreftelse av <b>ny</b> e-postadresse').'</dd>
		</dl>
		<form action="" method="post">
			<p class="c">'.show_sbutton("Avbryt", 'name="abort"').'</p>
		</form>' . $html_suf;
				}
			}
		}
		
		elseif ($subpage2 == "")
		{
			// lagre innstillinger?
			if (isset($_POST['save']))
			{
				if (page_min_side::$active_user->id != login::$user->id && !access::has("sadmin"))
				{
					ess::$b->page->add_message("Du har ikke tilgang til å redigere disse innstillingene for andre brukere.", "error");
					redirect::handle();
				}
				
				$show_signature = LOCK ? page_min_side::$active_user->data['u_forum_show_signature'] : (isset($_POST['show_signature']) ? 1 : 0);
				$forum_page = LOCK ? page_min_side::$active_user->data['u_forum_per_page'] : max(5, min(100, intval(postval("forum_page"))));
				
				$force_ssl = isset($_POST['force_ssl']) ? 1 : 0;
				$music_auto = LOCK ? page_min_side::$active_user->data['u_music_auto'] : (isset($_POST['music']) && $_POST['music'] == "auto" ? 1 : 0);
				$music_manual = LOCK ? (page_min_side::$active_user->params->get("music_manual") ? true : false) : (isset($_POST['music']) && $_POST['music'] == "manual");
				$hide_progressbar_left = LOCK ? (page_min_side::$active_user->params->get("hide_progressbar_left") ? true : false) : isset($_POST['hide_progressbar_left']);
				
				$changed = false;
				$user_change = array();
				
				if ($force_ssl != page_min_side::$active_user->data['u_force_ssl']) $user_change[] = "u_force_ssl = $force_ssl";
				if ($music_auto != page_min_side::$active_user->data['u_music_auto']) $user_change[] = "u_music_auto = $music_auto";
				if ($show_signature != page_min_side::$active_user->data['u_forum_show_signature']) $user_change[] = "u_forum_show_signature = $show_signature";
				if ($forum_page != page_min_side::$active_user->data['u_forum_per_page']) $user_change[] = "u_forum_per_page = $forum_page";
				
				if ($hide_progressbar_left != (page_min_side::$active_user->params->get("hide_progressbar_left") ? true : false))
				{
					if (!$hide_progressbar_left) page_min_side::$active_user->params->remove("hide_progressbar_left");
					else page_min_side::$active_user->params->update("hide_progressbar_left", "1");
					$changed = true;
				}
				
				if ($music_manual != (page_min_side::$active_user->params->get("music_manual") ? true : false))
				{
					if (!$music_manual) page_min_side::$active_user->params->remove("music_manual");
					else page_min_side::$active_user->params->update("music_manual", "1");
					$changed = true;
				}
				
				if ($changed) page_min_side::$active_user->params->commit();
				
				// noe som skal endres?
				if (count($user_change) > 0)
				{
					ess::$b->db->query("UPDATE users SET ".implode(", ", $user_change)." WHERE u_id = ".page_min_side::$active_user->id);
					$changed = true;
				}
				
				if ($changed)
				{
					ess::$b->page->add_message("Endringene ble lagret.");
				}
				else
				{
					ess::$b->page->add_message("Ingen endringer ble utført.");
				}
				
				redirect::handle();
			}
			
			ess::$b->page->add_css('
.minside_set { margin: 0 20px; background-color: #222222; padding: 1px 5px }
.minside_set p { margin: 5px 0 }');
			
			echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Innstillinger<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<form action="" method="post">
				<p><b>Forumalternativer:</b></p>
				<div class="minside_set">
					<p><input type="checkbox" name="show_signature" id="show_signature"'.(page_min_side::$active_user->data['u_forum_show_signature'] != 0 ? ' checked="checked"' : '').' /><label for="show_signature"> Vis signaturer i forumet</label></p>
					<p>Antall foruminnlegg per side: <input type="text" name="forum_page" maxlength="2" value="'.page_min_side::$active_user->data['u_forum_per_page'].'" class="styled w40" /></p>
				</div>
				
				<p><b>Sikkerhet:</b></p>
				<div class="minside_set">
					<p><input type="checkbox" name="force_ssl" id="force_ssl"'.(page_min_side::$active_user->data['u_force_ssl'] != 0 ? ' checked="checked"' : '').' /><label for="force_ssl"> Alltid benytt sikker tilkobling (SSL)</label>'.(page_min_side::$active_user->data['u_access_level'] != 0 && page_min_side::$active_user->data['u_access_level'] != 1 ? ' <span class="dark">(Som crew benytter du uansett alltid SSL.)</span>' : '').'</p>
				</div>
				
				<p><b>Spillinnstillinger:</b></p>
				<div class="minside_set">
					<p><input type="checkbox" name="hide_progressbar_left" id="hide_progressbar_left"'.(page_min_side::$active_user->params->get("hide_progressbar_left") ? ' checked="checked"' : '').' /><label for="hide_progressbar_left"> Skjul &laquo;Rank&raquo; og &laquo;Wanted nivå&raquo; fra toppen av siden</label></p>
				</div>
				
				<p><b>Annet:</b></p>
				<div class="minside_set">
					<p>Musikkalternativer:<br />
						<input type="radio" name="music" value="auto" id="music_auto"'.(!page_min_side::$active_user->params->get("music_manual") && page_min_side::$active_user->data['u_music_auto'] ? ' checked="checked"' : '').' /><label for="music_auto"> Spill av musikk automatisk</label><br />
						<input type="radio" name="music" value="preload" id="music_preload"'.(!page_min_side::$active_user->params->get("music_manual") && !page_min_side::$active_user->data['u_music_auto'] ? ' checked="checked"' : '').' /><label for="music_preload"> Last inn musikkfil, men ikke spill av automatisk</label><br />
						<input type="radio" name="music" value="manual" id="music_manual"'.(page_min_side::$active_user->params->get("music_manual") ? ' checked="checked"' : '').' /><label for="music_manual"> Ikke last inn musikkfil -- trykk på spiller for å laste inn</label>
					</p>
				</div>'.(page_min_side::$active_user->id != login::$user->id ? '
				<p class="c">Du har ikke tilgang til å endre disse innstillingene</p>' : '
				<p class="c">'.show_sbutton("Lagre endringer", 'name="save"').'</p>').'
			</form>
		</div>
	</div>';
		}
	}
	
	/**
	 * Crewlogg
	 */
	protected static function page_crewlog()
	{
		global $_game;
		
		ess::$b->page->add_title("Crewhendelser");
		ess::$b->page->add_css('
.gamelog { width: 80%; margin: 0 auto }
.gamelog .time { color: #888888; padding-right: 2px }
.log_section {
	background-color: #1C1C1C;
	padding: 15px 15px 5px;
	margin: 30px 0;
	border: 10px solid #111111;
}');
		echo '
	<div class="gamelog">';
		
		$gamelog = new gamelog();
		
		// liste over hva vi har av typer
		$types = array(
			gamelog::$items['crewforum_emne'],
			gamelog::$items['crewforum_svar'],
			gamelog::$items['crewforuma_emne'],
			gamelog::$items['crewforuma_svar'],
			gamelog::$items['crewforumi_emne'],
			gamelog::$items['crewforumi_svar']
		);
		
		// finn ut hva som er tilgjengelig
		$result = ess::$b->db->query("SELECT type, COUNT(id) AS count FROM users_log WHERE ul_up_id = 0 AND type IN (".implode(",", $types).") GROUP BY type");
		$in_use = array();
		$count = array();
		$total = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$in_use[] = $row['type'];
			$count[$row['type']] = $row['count'];
		}
		
		$tilgjengelig = array();
		foreach (gamelog::$items_id as $id => $name)
		{
			if (in_array($id, $in_use)) $tilgjengelig[$id] = $id;
		}
		
		$i_bruk = $tilgjengelig;
		$total = array_sum($count);
		
		// nye hendelser (viser også nye hendelser i firma/familie)?
		if (page_min_side::$active_user->data['u_log_crew_new'] > 0 && login::$user->id == page_min_side::$active_user->id && count($i_bruk) > 0)
		{
			echo '
		<h1 class="c">Nye crewhendelser</h1>';
			ess::$b->page->add_css('.ny { color: #FF0000 }');
			
			$where = ' AND type IN ('.implode(",", $i_bruk).')';
			$result = ess::$b->db->query("SELECT time, type, note, num FROM users_log WHERE ul_up_id = 0$where ORDER BY time DESC, id DESC LIMIT ".page_min_side::$active_user->data['u_log_crew_new']);
			
			if (mysql_num_rows($result) == 0)
			{
				echo '
		<p class="c">Ingen crewhendelser ble funnet.</p>';
			}
			
			else
			{
				// vis hendelsene
				$logs = array();
				while ($row = mysql_fetch_assoc($result))
				{
					$day = ess::$b->date->get($row['time'])->format(date::FORMAT_NOTIME);
					$data = $gamelog->format_log($row['type'], $row['note'], $row['num']);
					
					$logs[$day][] = '
				<p><span class="time"><span class="ny">Ny!</span> - '.ess::$b->date->get($row['time'])->format("H:i").':</span> '.$data.'</p>';
				}
				
				foreach ($logs as $day => $items)
				{
					echo '
		<div class="bg1_c">
			<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">';
					
					foreach ($items as $item)
					{
						echo $item;
					}
					
					echo '
			</div>
		</div>';
				}
				
				echo '
				<p class="c">Viser '.page_min_side::$active_user->data['u_log_crew_new'].' <b>ny'.(page_min_side::$active_user->data['u_log_crew_new'] == 1 ? '' : 'e').'</b> crewhendelse'.(page_min_side::$active_user->data['u_log_crew_new'] == 1 ? '' : 'r').'<br /><a href="'.htmlspecialchars(page_min_side::addr()).'">Se full oversikt</a></p>';
				
				ess::$b->db->query("UPDATE users SET u_log_crew_new = 0 WHERE u_id = ".page_min_side::$active_user->id);
				page_min_side::$active_user->data['u_log_crew_new'] = 0;
			}
		}
		
		// vis vanlig visning
		else
		{
			if (page_min_side::$active_user->data['u_log_crew_new'] > 0 && login::$user->id == page_min_side::$active_user->id)
			{
				ess::$b->db->query("UPDATE users SET u_log_crew_new = 0 WHERE u_id = ".page_min_side::$active_user->id);
				page_min_side::$active_user->data['u_log_crew_new'] = 0;
			}
			
			// filter
			$filter = array();
			foreach ($_GET as $name => $val)
			{
				$matches = NULL;
				if (preg_match("/^f([0-9]+)$/D", $name, $matches) && in_array($matches[1], $tilgjengelig))
				{
					$filter[] = $matches[1];
				}
			}
			if (count($filter) == 0) $filter = false;
			else
			{
				$i_bruk = $filter;
				$filter = true;
				
				ess::$b->page->add_message("Du har aktivert et filter og viser kun bestemte enheter.");
			}
			
			// hva skal vi vise?
			if (!$filter)
			{
				echo '
		<p class="c filterbox"><a href="#" onclick="toggle_display(\'.filterbox\', event)">Vis filteralternativer</a></p>';
			}
			
			echo '
		<div'.(!$filter ? ' style="display: none"' : '').' class="filterbox bg1_c">
			<h1 class="bg1">Filter<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">
				<p class="c">Velg filter (<a href="#" class="box_handle_toggle" rel="f[]">Merk alle</a>)</p>
				<form action="" method="get">'.(!page_min_side::$active_own ? '
					<input type="hidden" name="u_id" value="'.page_min_side::$active_user->id.'" />' : '
					<input type="hidden" name="u" value="1" />').'
					<input type="hidden" name="a" value="crewlog" />
					<table class="table center" width="100%">
						<tbody>';
			
			$tbody = new tbody(3); // 3 kolonner
			foreach ($tilgjengelig as $id)
			{
				$title = gamelog::$items_name[$id];
				$aktivt = in_array($id, $i_bruk) && $filter;
				$ant = $count[$id];
				$tbody->append('<input type="checkbox" name="f'.$id.'" rel="f[]" value=""'.($aktivt ? ' checked="checked"' : '').' />'.htmlspecialchars($title).' <span class="dark">('.$ant.' stk)</span>', 'class="box_handle"');
			}
			$tbody->clean();
			
			echo '
						</tbody>
					</table>
					<p class="c">'.show_sbutton("Oppdater").'</p>
				</form>
			</div>
		</div>';
			
			$i_bruk[] = "NULL";
			$where = ' AND type IN ('.implode(",", $i_bruk).')';
			
			// sideinformasjon - hent loggene på denne siden
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, max(50, page_min_side::$active_user->data['u_log_crew_new']));
			$result = $pagei->query("SELECT time, type, note, num FROM users_log WHERE ul_up_id IN (0, ".page_min_side::$active_player->id.")$where ORDER BY time DESC, id DESC");
			
			if (mysql_num_rows($result) == 0)
			{
				echo '
		<p class="c">Ingen hendelser ble funnet.</p>';
			}
			
			else
			{
				echo '
		<p class="c">Totalt har du <b>'.game::format_number($total).'</b> crewhendelse'.($total == 1 ? '' : 'r').'.</p>';
				
				if ($pagei->pages > 1)
				{
					echo '
		<p class="c">'.address::make($_GET, "", $pagei).'</p>';
				}
				
				// hendelsene
				$logs = array();
				$i = 0;
				$e = $pagei->start;
				while ($row = mysql_fetch_assoc($result))
				{
					$day = ess::$b->date->get($row['time'])->format(date::FORMAT_NOTIME);
					$data = $gamelog->format_log($row['type'], $row['note'], $row['num']);
					
					$ny = $e < page_min_side::$active_user->data['u_log_crew_new'];
					
					$logs[$day][] = '
				<p><span class="time">'.($ny ? '<span class="ny">Ny!</span> - ' : '').''.ess::$b->date->get($row['time'])->format("H:i").':</span> '.$data.'</p>';
					$e++;
				}
				
				foreach ($logs as $day => $items)
				{
					echo '
		<div class="bg1_c">
			<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">';
					
					foreach ($items as $item)
					{
						echo $item;
					}
					
					echo '
			</div>
		</div>';
				}
				
				echo '
		<p class="c">Viser '.$pagei->count_page.' av '.$pagei->total.' crewhendelse'.($pagei->total == 1 ? '' : 'r').'</p>';
				
				if ($pagei->pages > 1)
				{
					echo '
		<p class="c">'.address::make($_GET, "", $pagei).'</p>';
				}
			}
		}
		
		echo '
	</div>';
	}
	
	/**
	 * Deaktivere brukeren som moderator
	 */
	protected static function page_deact_mod()
	{
		ess::$b->page->add_title("Deaktiver bruker");
		
		// er deaktivert?
		if (page_min_side::$active_user->data['u_access_level'] == 0)
		{
			ess::$b->page->add_message("Denne brukeren er allerede deaktivert.");
			redirect::handle(page_min_side::addr(""));
		}
		
		// deaktivere?
		if (isset($_POST['deaktiver']))
		{
			$log = trim(postval("log"));
			$note = trim(postval("note"));
			$send_email = isset($_POST['email']);
			
			// mangler logg?
			if ($log == "")
			{
				ess::$b->page->add_message("Mangler begrunnelse.", "error");
			}
			
			// mangler intern informasjon?
			elseif ($note == "")
			{
				ess::$b->page->add_message("Mangler intern informasjon.", "error");
			}
			
			// ikke normal bruker?
			elseif (page_min_side::$active_user->data['u_access_level'] != 1 && !access::has("sadmin"))
			{
				ess::$b->page->add_message("Crewmedlemmer og brukere med spesielle tilganger kan kun deaktiveres av en Senioradministrator.");
			}
			
			else
			{
				// transaksjon
				$transaction_before = ess::$b->db->transaction;
				ess::$b->db->begin();
				
				// deaktiver brukeren
				$player_deact = page_min_side::$active_player->active;
				if (page_min_side::$active_user->deactivate($log, $note, login::$user->player))
				{
					// legg til crewlogg
					$data = array("note" => $note);
					if ($send_email) $data["email_sent"] = 1;
					crewlog::log("user_deactivate", page_min_side::$active_player->id, $log, $data);
					
					// fullfør transaksjon
					if (!$transaction_before) ess::$b->db->commit();
					
					// send e-post
					if ($send_email)
					{
						$email = new email();
						$email->text = 'Hei,

Din bruker har blitt deaktivert av Crewet.'.($player_deact ? '

Dette har også medført at din spiller '.page_min_side::$active_player->data['up_name'].' har blitt deaktivert.' : '').'

Begrunnelse for deaktivering:
'.strip_tags(game::bb_to_html($log)).'

--
www.kofradia.no
Du vil ikke lenger motta e-post fra oss om nyheter og annen informasjon.';
						$email->send(page_min_side::$active_user->data['u_email'], "Din bruker har blitt deaktivert");
					}
					
					ess::$b->page->add_message("Brukeren ble deaktivert".($send_email ? " og e-post ble sendt til ".page_min_side::$active_user->data['u_email'] : "").".");
				}
				
				else
				{
					// fullfør transaksjon
					if (!$transaction_before) ess::$b->db->commit();
				}
				
				redirect::handle(page_min_side::addr(""));
			}
		}
		
		echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Deaktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<p>Du er i ferd med å deaktivere denne brukeren.</p>'.(page_min_side::$active_player->active ? '
			<p>Dette vil også medføre at spilleren '.page_min_side::$active_player->profile_link().' vil bli deaktivert. Du kan alternativt kun <a href="'.htmlspecialchars(page_min_side::addr("deact", "", "player")).'">deaktivere spilleren</a>.</p>' : '').'
			<form action="" method="post">
				<dl class="dd_right">
					<dt>Begrunnelse for deaktivering</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
					<dt>Intern informasjon</dt>
					<dd><textarea name="note" id="note" cols="30" rows="5">'.htmlspecialchars(postval("note")).'</textarea></dd>
				</dl>
				<p><input type="checkbox" id="email" name="email"'.($_SERVER['REQUEST_METHOD'] != "POST" || isset($_POST['email']) ? ' checked="checked"' : '').' /><label for="email"> Send e-post med begrunnelse for deaktivering til '.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</label></p>
				<p class="c">'.show_sbutton("Deaktiver bruker", 'name="deaktiver"').'</p>
			</form>
		</div>
	</div>';
	}
	
	/**
	 * Endre informasjon om deaktivering
	 */
	protected static function page_cdeact()
	{
		ess::$b->page->add_title("Endre deaktivering");
		
		// er ikke deaktivert?
		if (page_min_side::$active_user->data['u_access_level'] != 0)
		{
			redirect::handle(page_min_side::addr("deact"));
		}
		
		// lagre endringer?
		if (isset($_POST['save']))
		{
			$log = trim(postval("log"));
			$note = trim(postval("note"));
			$log_change = $log != page_min_side::$active_user->data['u_deactivated_reason'];
			$note_change = $note != page_min_side::$active_user->data['u_deactivated_note'];
			
			// mangler logg?
			if ($log == "")
			{
				ess::$b->page->add_message("Mangler begrunnelse.", "error");
			}
			
			// mangler intern informasjon?
			elseif ($note == "")
			{
				ess::$b->page->add_message("Mangler intern informasjon.", "error");
			}
			
			// ingen endringer?
			if (!$log_change && !$note_change)
			{
				ess::$b->page->add_message("Du har ikke gjort noen endringer.", "error");
			}
			
			else
			{
				// lagre endringer
				ess::$b->db->query("UPDATE users SET u_deactivated_reason = ".ess::$b->db->quote($log).", u_deactivated_note = ".ess::$b->db->quote($note)." WHERE u_id = ".page_min_side::$active_user->id);
				
				// lagre crewlog
				$data = array("log_old" => page_min_side::$active_user->data['u_deactivated_reason'], "note_old" => page_min_side::$active_user->data['u_deactivated_note']);
				if ($log_change) $data['log_new'] = $log;
				if ($note_change) $data['note_new'] = $note;
				crewlog::log("user_deactivate_change", page_min_side::$active_player->id, NULL, $data);
				
				ess::$b->page->add_message("Endringene ble lagret.");
				redirect::handle(page_min_side::addr(""));
			}
		}
		
		echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Endre informasjon om deaktivering<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<form action="" method="post">
				<p>Brukeren vil ikke bli informert om disse endringene, annet enn at brukeren får oppgitt den nye begrunnelsen ved forsøk på innlogginger.</p>
				<dl class="dd_right">
					<dt>Begrunnelse for deaktivering</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log", page_min_side::$active_user->data['u_deactivated_reason'])).'</textarea></dd>
					<dt>Intern informasjon</dt>
					<dd><textarea name="note" id="note" cols="30" rows="5">'.htmlspecialchars(postval("note", page_min_side::$active_user->data['u_deactivated_note'])).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Lagre endringer", 'name="save"').'</p>
			</form>
		</div>
	</div>';
	}
	
	/**
	 * Deaktivere brukeren
	 */
	protected static function page_deact()
	{
		global $__server;
		ess::$b->page->add_title("Deaktiver bruker");
		
		// er deaktivert?
		if (page_min_side::$active_user->data['u_access_level'] == 0)
		{
			ess::$b->page->add_message("Denne brukeren er allerede deaktivert.");
			redirect::handle(page_min_side::addr(""));
		}
		
		// blokkert fra å deaktivere brukeren?
		$blokkering = blokkeringer::check(blokkeringer::TYPE_DEAKTIVER);
		if ($blokkering)
		{
			echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du er blokkert fra å deaktivere brukeren din.</p>
			<p>Blokkeringen varer til '.ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).'.</p>
			<p><b>Begrunnelse:</b> '.game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>
		</div>
	</div>';
		}
		
		// spesielle tilganger?
		elseif (page_min_side::$active_user->data['u_access_level'] != 1)
		{
			echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Din bruker har spesielle rettigheter og kan ikke deaktiveres uten videre.</p>
		</div>
	</div>';
		}
		
		else
		{
			// deaktivere seg selv -- status: sjekk om brukeren kan deaktivere seg selv
			$deactivate_expire = page_min_side::$active_user->params->get("deactivate_expire");
			$deactivate_expire_time = 3600;
			
			// må be om e-post?
			if (!$deactivate_expire || $deactivate_expire < time())
			{
				if (isset($_POST['deactivate']))
				{
					// opprett nøkkel
					$key = uniqid();
					$expire = time()+$deactivate_expire_time;
					
					page_min_side::$active_user->params->update("deactivate_expire", $expire);
					page_min_side::$active_user->params->update("deactivate_key", $key);
					page_min_side::$active_user->params->update("deactivate_time", time(), true);
					
					// opprett e-post
					$email = new email();
					$email->text = 'Hei,

Du har bedt om å deaktivere din bruker på Kofradia.
For din egen skyld sender vi deg denne e-posten for å være sikker på at ingen uvedkommende forsøker å deaktivere brukeren din.

Brukerinformasjon:
Bruker ID: '.page_min_side::$active_user->id.'
E-post: '.page_min_side::$active_user->data['u_email'].'
Spiller: '.page_min_side::$active_player->data['up_name'].' (#'.page_min_side::$active_player->id.')

For å godta eller avslå deaktivering:
'.$__server['path'].'/min_side?u&a=deact&key='.urlencode($key).'

--
www.kofradia.no';
					$email->send(page_min_side::$active_user->data['u_email'], "Deaktiver bruker");
					
					putlog("CREWCHAN", "%bDeaktiveringsmulighet%b: ".page_min_side::$active_user->data['u_email']." (".page_min_side::$active_player->data['up_name'].") ba om e-post for å deaktivere brukeren -- {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
					ess::$b->page->add_message("E-post med detaljer ble sendt til <b>".htmlspecialchars(page_min_side::$active_user->data['u_email'])."</b>.");
					
					redirect::handle();
				}
				
				if (($deactivate_expire && $deactivate_expire < time()) || isset($_GET['key']))
				{
					if (isset($_GET['key']))
					{
						ess::$b->page->add_message("Du brukte for lang tid fra e-posten ble sendt. Alternativt er du logget inn på feil bruker.", "error");
					}
					else
					{
						ess::$b->page->add_message("Du brukte for lang tid fra e-posten ble sendt om å deaktivere brukeren din. Alternativt er du logget inn på feil bruker.", "error");
					}
					
					if ($deactivate_expire && $deactivate_expire < time())
					{
						// fjern oppføringene
						page_min_side::$active_user->params->remove("deactivate_expire");
						page_min_side::$active_user->params->remove("deactivate_key");
						page_min_side::$active_user->params->remove("deactivate_time", true);
					}
					
					redirect::handle();
				}
				
				$deactivate_expire = false;
			}
			
			else
			{
				// ikke normal bruker
				if (page_min_side::$active_user->data['u_access_level'] != 1 && false)
				{
					// fjern oppføringene
					page_min_side::$active_user->params->remove("deactivate_expire");
					page_min_side::$active_user->params->remove("deactivate_key");
					page_min_side::$active_user->params->remove("deactivate_time", true);
					
					redirect::handle();
				}
				
				// avbryte?
				if (isset($_GET['abort']))
				{
					ess::$b->page->add_message("Du har trukket tilbake ditt ønske om deaktivering.", "error");
					
					// fjern oppføringene
					page_min_side::$active_user->params->remove("deactivate_expire");
					page_min_side::$active_user->params->remove("deactivate_key");
					page_min_side::$active_user->params->remove("deactivate_time", true);
					
					redirect::handle();
				}
				
				// kode fra e-post?
				if (isset($_GET['key']))
				{
					// kontroller kode
					$key = getval("key");
					if ($key != page_min_side::$active_user->params->get("deactivate_key"))
					{
						ess::$b->page->add_message("Lenken er feil. Sørg for at du kopierer hele lenken.", "error");
						redirect::handle();
					}
					
					// bekreftet?
					elseif (isset($_POST['pass']))
					{
						// kontroller note og passord
						$pass = postval("pass");
						$note = trim(postval("note"));
						
						if ($note == "")
						{
							ess::$b->page->add_message("Mangler begrunnelse.", "error");
						}
						
						elseif ($pass == "")
						{
							ess::$b->page->add_message("Du må fylle inn passordet ditt.", "error");
						}
						
						elseif (!password::verify_hash($pass, page_min_side::$active_user->data['u_pass'], 'user'))
						{
							ess::$b->page->add_message("Passordet stemte ikke.", "error");
						}
						
						else
						{
							// deaktiver bruker
							$player_deact = page_min_side::$active_player->active;
							if (page_min_side::$active_user->deactivate($note, NULL))
							{
								ess::$b->page->add_message("Brukeren er nå deaktivert.");
								
								// send e-post
								$email = new email();
								$email->text = 'Hei,

Du har deaktivert din bruker.'.($player_deact ? '

Dette har også medført at din spiller '.page_min_side::$active_player->data['up_name'].' har blitt deaktivert.' : '').'

Din begrunnelse for deaktivering:
'.game::bb_to_html($note).'

--
www.kofradia.no
Du vil ikke lenger motta e-post fra oss om nyheter og annen informasjon.';
								$email->send(page_min_side::$active_user->data['u_email'], "Din bruker har blitt deaktivert");
								
								redirect::handle("");
							}
						}
					}
				}
			}
			
			// venter på kode
			if ($deactivate_expire !== false)
			{
				// har kode?
				if (isset($_GET['key']))
				{
					echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<div class="warning">
				<p>Du er i ferd med å deaktivere brukeren din. Når brukeren din blir deaktivert vil du ikke få tilgang til noe av dataen som er lagret i din bruker.</p>'.(page_min_side::$active_player->active ? '
				<p>Spilleren '.page_min_side::$active_player->profile_link().' vil bli automatisk deaktivert siden denne tilhører deg.</p>' : '').'
			</div>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Passord</dt>
					<dd><input type="password" name="pass" class="styled w100" /></dd>
					<dt>Begrunnelse</dt>
					<dd><textarea name="note" cols="30" rows="5">'.htmlspecialchars(postval("note")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Deaktiver bruker").'</p>
				<p class="c"><a href="'.htmlspecialchars(page_min_side::addr(NULL, "abort")).'">Avbryt - ønsker ikke å deaktivere brukeren</a></p>
			</form>
		</div>
	</div>';
				}
				
				else
				{
					echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du skal ha mottatt en e-post med link til å deaktivere din bruker.</p>
			<p><a href="'.htmlspecialchars(page_min_side::addr(NULL, "abort")).'">Avbryt - ønsker ikke å deaktivere brukeren</a></p>
		</div>
	</div>';
				}
			}
			
			else
			{
				echo '
	<div class="bg1_c" style="width: 300px">
		<h1 class="bg1">Deaktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Ved å deaktivere brukeren din mister du tilgang til all informasjon som er lagret i brukeren. Dette omfatter statistikk om tidligere spillere, meldinger m.v.</p>
			<p>Av sikkerhetsmessige grunner vil du motta en e-post med nærmere instrukser for å deaktivere brukeren.</p>'.(page_min_side::$active_player->active ? '
			<p>Hvis du ønsker å opprette en <u>ny spiller</u>, vil du ikke deaktivere brukeren din. Da vil du heller <a href="'.htmlspecialchars(page_min_side::addr("deact", "", "player")).'">deaktivere spilleren din</a>!</p>' : '').'
			<form action="" method="post">
				<p class="c">'.show_sbutton("Be om e-post", 'name="deactivate"').'</p>
			</form>
		</div>
	</div>';
			}
		}
	}
	
	/**
	 * Aktiver bruker
	 */
	protected static function page_activate()
	{
		global $__server;
		ess::$b->page->add_title("Aktiver bruker");
		
		// er ikke deaktivert?
		if (page_min_side::$active_user->data['u_access_level'] != 0)
		{
			ess::$b->page->add_message("Denne brukeren er ikke deaktivert.");
			redirect::handle(page_min_side::addr(""));
		}
		
		// aktivere?
		if (isset($_POST['aktiver']))
		{
			$log = trim(postval("log"));
			$note = trim(postval("note"));
			$send_email = isset($_POST['email']);
			
			// mangler logg?
			if ($log == "")
			{
				ess::$b->page->add_message("Mangler begrunnelse.", "error");
			}
			
			else
			{
				// aktiver brukeren
				if (page_min_side::$active_user->activate())
				{
					// legg til crewlogg
					if ($send_email) $data = array("email_sent" => 1, "email_note" => $note);
					else $data = array();
					crewlog::log("user_activate", page_min_side::$active_player->id, $log, $data);
					
					// send e-post
					if ($send_email)
					{
						$email = new email();
						$email->text = 'Hei,

Din bruker har blitt aktivert igjen av Crewet.

Du kan nå logge inn igjen på Kofradia:
'.$__server['path'].'/'.(!empty($note) ? '

Begrunnelse for aktivering:
'.$note : '').'

--
www.kofradia.no';
						$email->send(page_min_side::$active_user->data['u_email'], "Din bruker har blitt aktivert igjen");
					}
					
					ess::$b->page->add_message("Brukeren ble aktivert igjen".($send_email ? " og e-post ble sendt til ".page_min_side::$active_user->data['u_email'].(empty($note) ? " uten begrunnelse" : " med begrunnelse")."." : ". Brukeren har ikke blitt informert om dette."));
				}
				
				redirect::handle(page_min_side::addr(""));
			}
		}
		
		ess::$b->page->add_js_domready('
	$("email").addEvent("change", function()
	{
		$("note").set("disabled", !this.get("checked"));
		if (this.get("checked")) $("email-info").removeClass("email-info-dis");
		else $("email-info").addClass("email-info-dis");
	});');
		
		ess::$b->page->add_css('.email-info-dis { color: #555 }');
		echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Aktiver bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du er i ferd med å aktivere denne brukeren.</p>
			<p>Merk at dette <u>ikke</u> automatisk vil aktivere spilleren også. Du kan alternativt velge å <a href="'.htmlspecialchars(page_min_side::addr("activate", "", "player")).'">aktivere spilleren</a> ('.page_min_side::$active_player->profile_link().') slik at både brukeren og spilleren blir aktivert samtidig.</p>
			<form action="" method="post">
				<dl class="dd_right">
					<dt>Begrunnelse for aktivering<br />(internt for crewet)</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p>
					<input type="checkbox" id="email" name="email"'.($_SERVER['REQUEST_METHOD'] != "POST" || isset($_POST['email']) ? ' checked="checked"' : '').' />
					<label for="email"> Send e-post til '.htmlspecialchars(page_min_side::$active_user->data['u_email']).' for å informere om at brukeren er aktivert igjen</label>
				</p>
				<dl class="dd_right">
					<dt id="email-info"'.($_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['email']) ? ' class="email-info-dis"' : '').'>Tilleggsinformasjon til spilleren<br />(ikke BB-kode)<br /><br />(Blir oppgitt som begrunnelse<br />for aktivering hvis fylt ut)</dt>
					<dd><textarea name="note" id="note" cols="30" rows="5"'.($_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['email']) ? ' disabled="disabled"' : '').'>'.htmlspecialchars(postval("note")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Aktiver igjen", 'name="aktiver"').'</p>
			</form>
		</div>
	</div>';
	}
	
	/**
	 * Crewside
	 */
	protected static function page_crew()
	{
		global $__server, $_lang;
		ess::$b->page->add_title("Crew");
		
		$subpage2 = getval("b");
		redirect::store(page_min_side::addr(NULL, ($subpage2 != "" ? "b=" . $subpage2 : '')));
		ess::$b->page->add_css('
.minside_crew_links .active { color: #CCFF00 }');
		
		$links = array();
		$links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "", "player")).'">Min spiller</a>';
		$links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "")).'"'.($subpage2 == "" ? ' class="active"' : '').'>Oversikt / logg</a>';
		if (access::has("forum_mod")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=addlog")).'"'.($subpage2 == "addlog" ? ' class="active"' : '').'>Nytt notat</a>';
		$links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=blokk")).'"'.($subpage2 == "blokk" ? ' class="active"' : '').'>Blokkeringer</a>';
		if (access::has("mod")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=banka")).'"'.($subpage2 == "banka" ? ' class="active"' : '').'>Bankpassord</a>';
		if (access::has("mod")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=birth")).'"'.($subpage2 == "birth" ? ' class="active"' : '').'>Fødselsdato</a>';
		if (access::has("mod")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr("set", "b=pass")).'">Passord</a>';
		if (access::has("admin")) $links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=level")).'"'.($subpage2 == "level" ? ' class="active"' : '').'>Tilgangsnivå</a>';
		$links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=send_email")).'"'.($subpage2 == "send_email" ? ' class="active"' : '').'>Send e-post</a>';
		$links[] = '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=warning")).'"'.($subpage2 == "warning" ? ' class="active"' : '').'>Gi advarsel</a>';
		
		echo '
	<p class="c minside_crew_links">'.implode(" | ", $links).'</p>';
		
		if ($subpage2 == "")
		{
			// javascript for rapporteringer
			ess::$b->page->add_js_domready('
	var w = $("minside_reports");
	var xhr = new Request({
		url: relative_path + "/ajax/minside_report",
		data: { u_id: '.page_min_side::$active_user->id.' },
		evalScripts: function(script)
		{
			ajax.js += script;
		}
	});
	xhr.addEvent("success", function(text)
	{
		w.set("html", text);
		w.getElements(".pagenumbers").each(function(elm)
		{
			elm.addEvent("set_page", function(s) { load(null, s, true); });
		});
		ajax.refresh();
	});
	xhr.addEvent("failure", function(x)
	{
		var p = new Element("p", {html: "Feil: " + x}).inject(w.empty());
	});
	function load(a, s, goto)
	{
		if (a !== null) xhr.options.data.a = a;
		if (s) xhr.options.data.s = s;
		if (goto) w.getParent().goto(-10);
		w.set("html", "<p>Laster inn data..</p>");
		xhr.send();
	}
	$("minside_reports_from").addEvent("click", function() { load("from", 1, true); });
	$("minside_reports_to").addEvent("click", function() { load("to", 1, true); });
	$("minside_reports_all").addEvent("click", function() { load("", 1, true); });
	load();');
			// css for rapporteringer
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
			// faner
			ess::$b->page->add_js_domready('
	$$(".minside_fane_link").addEvent("click", function(elm)
	{
		$$(".minside_fane").setStyle("display", "none");
		$$(".minside_fane_link").removeClass("minside_fane_active");
		this.addClass("minside_fane_active");
		$(this.get("rel")).setStyle("display", "");
	});
	$$(".minside_fane_active").fireEvent("click");');
				ess::$b->page->add_css('
.minside_fane_active, .minside_fane_active:hover {
	color: #CCFF00;
}');
			
			echo '
	<div class="col2_w">
		<div class="col_w left">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Oversikt<span class="left2"></span><span class="right2"></span></h1>
					<div class="bg1">';
			
			
			// hent blokkeringer for brukeren
			$result = ess::$b->db->query("SELECT ub_id, ub_type, ub_time_expire, ub_reason FROM users_ban WHERE ub_u_id = ".page_min_side::$active_user->id." AND ub_time_expire > ".time());
			if (mysql_num_rows($result) > 0)
			{
				while ($row = mysql_fetch_assoc($result))
				{
					$access = access::has(blokkeringer::$types[$row['ub_type']]['access']);
					
					echo '
						<p>Blokkert: '.($access ? '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=blokk&t={$row['ub_type']}")).'">' : '').htmlspecialchars(blokkeringer::$types[$row['ub_type']]['title']).($access ? '</a>' : '').' (til '.ess::$b->date->get($row['ub_time_expire'])->format(date::FORMAT_SEC).', '.game::counter($row['ub_time_expire']-time()).')</p>';
				}
			}
			
			echo '
						<p>Trykk deg inn på de forskjellige spillerene til brukeren for å se informasjon knyttet opp mot dem.</p>
					</div>
				</div>
			</div>
		</div>
		<div class="col_w right">
			<div class="col">
				<div class="bg1_c">
					<h1 class="bg1">Crewnotat for brukeren<span class="left2"></span><span class="right2"></span></h1>
					<p class="h_right"><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=enote")).'">rediger</a></p>
					<div class="bg1">
						<p>Her kan hvem som helst i crewet legge til eller endre et notat for denne brukeren for å memorere ting som har med <u>brukeren</u> å gjøre.</p>'.(empty(page_min_side::$active_user->data['u_note_crew']) ? '
						<p>Ingen notat er registrert.</p>' : '
						<div class="p">'.game::bb_to_html(page_min_side::$active_user->data['u_note_crew']).'</div>').'
					</div>
				</div>
			</div>
		</div>
	</div>
	<p class="c"><a class="minside_fane_link minside_fane_active" rel="minside_fane2">Loggoppføringer</a> | <a class="minside_fane_link" rel="minside_fane1">Rapporteringer</a></p>
	<div id="minside_fane1" class="minside_fane">
		<p class="c">Filter: <a id="minside_reports_from">Brukerens egne rapporteringer</a> | <a id="minside_reports_to">Andres rapporteringer</a> | <a id="minside_reports_all">Alle</a></p>
		<div id="minside_reports">
			<p>Laster inn..</p>
		</div>
	</div>
	<div id="minside_fane2" class="minside_fane">
	<p class="c">Loggoppføringer for denne brukeren</p>';
			
			// hent loggene for denne brukeren
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 50);
			$result = $pagei->query("SELECT lc_id, lc_up_id, lc_time, lc_lca_id, lc_a_up_id, lc_log FROM log_crew JOIN users_players ON up_u_id = ".page_min_side::$active_user->id." WHERE lc_a_up_id = up_id ORDER BY lc_time DESC");
			
			// ingen handlinger?
			if (mysql_num_rows($result) == 0)
			{
				echo '
	<p class="c">Ingen oppføringer eksisterer.</p>';
			}
			
			else
			{
				$rows = array();
				while ($row = mysql_fetch_assoc($result)) $rows[$row['lc_id']] = $row;
				$data = crewlog::load_summary_data($rows);
				
				$logs = array();
				foreach ($data as $row)
				{
					// hent sammendrag
					$summary = crewlog::make_summary($row, NULL, $row['lc_a_up_id'] != page_min_side::$active_player->id);
					$day = ess::$b->date->get($row['lc_time'])->format(date::FORMAT_NOTIME);
					
					$logs[$day][] = '<p><span class="time">'.ess::$b->date->get($row['lc_time'])->format("H:i").':</span> '.$summary.'</p>';
				}
				
				ess::$b->page->add_css('.crewlog .time { color: #888888; padding-right: 5px }');
				
				foreach ($logs as $day => $items)
				{
					echo '
	<div class="bg1_c">
		<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1 crewlog">
			'.implode('
			', $items).'
		</div>
	</div>';
				}
				
				echo '
	<p class="c">'.$pagei->pagenumbers().'</p>';
			}
			
			echo '
	</div>';
		}
		
		elseif ($subpage2 == "addlog" && access::has("forum_mod"))
		{
			// legge til?
			if (isset($_POST['notat']))
			{
				$notat = trim(postval("notat"));
				$notat_bb = trim(game::bb_to_html($notat));
				
				if (empty($notat_bb))
				{
					ess::$b->page->add_message("Notatet kan ikke være tomt.", "error");
				}
				
				else
				{
					// legg til i crewloggen
					crewlog::log("user_add_note", page_min_side::$active_player->id, $notat);
					
					ess::$b->page->add_message("Notatet ble registrert.");
					redirect::handle(page_min_side::addr());
				}
			}
			
			ess::$b->page->add_title("Nytt notat");
			ess::$b->page->add_js_domready('$("notat_felt").focus();');
			
			echo '
	<div class="bg1_c">
		<h1 class="bg1">Legg til notat i crewloggen<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Notat: (Vil bli lagt til som vanlig logg i <a href="'.htmlspecialchars(page_min_side::addr(NULL)).'">Crewloggen</a>.)</p>
			<form action="" method="post">
				<p><textarea name="notat" id="notat_felt" rows="10" cols="30" style="width: 98%; overflow: auto">'.htmlspecialchars(postval("notat")).'</textarea></p>
				<p>'.show_sbutton("Legg til notat").'</p>
			</form>
		</div>
	</div>';
		}
		
		// blokkeringer
		elseif ($subpage2 == "blokk")
		{
			ess::$b->page->add_title("Blokkeringer");
			
			$type = false;
			if (isset($_GET['t']))
			{
				// kontroller type
				$type_id = intval($_GET['t']);
				
				// fant ikke?
				if (!isset(blokkeringer::$types[$type_id]))
				{
					ess::$b->page->add_message("Ugyldig type '.$type_id.'.", "error");
				}
				else
				{
					$type = blokkeringer::$types[$type_id];
					
					// har vi tilgang til å gjøre noe med denne blokkeringen?
					if (!access::has($type['access']))
					{
						ess::$b->page->add_message('Du har ikke tilgang til denne typen blokkering. ('.htmlspecialchars($type['title']).')', "error");
						$type = false;
					}
				}
			}
			
			// vise en type blokkering?
			if ($type)
			{
				redirect::store(page_min_side::addr(NULL, "b=blokk&t={$type_id}"));
				
				// sjekk om det er en aktiv blokkering for denne typen
				$active = blokkeringer::check($type_id, page_min_side::$active_user->id);
				if ($active)
				{
					// hent informasjon om blokkeringen
					$info = blokkeringer::get_info($active['ub_id']);
				}
				
				// handling: legg til blokkering
				if (isset($_POST['add']) && $active)
				{
					ess::$b->page->add_message("Det er allerede en blokkering på brukeren som varer til ".ess::$b->date->get($active['ub_time_expire'])->format().".", "error");
				}
				elseif (isset($_POST['add']))
				{
					// kontroller verdier
					$date_type = isset($_POST['date_type']) && $_POST['date_type'] == "abs" ? "abs" : "rel";
					$rel_weeks = intval(postval("rel_weeks"));
					$rel_days = intval(postval("rel_days"));
					$rel_hours = intval(postval("rel_hours"));
					$rel_mins = intval(postval("rel_mins"));
					$abs_date = postval("abs_date");
					$abs_time = postval("abs_time");
					
					// sjekk type og verdiene
					$expire = false;
					
					// bestemt dato/tidspunkt
					if ($date_type == "abs")
					{
						// kontroller datoen
						if (!($abs_date_m = check_date($abs_date, "%y-%m-%d")))
						{
							ess::$b->page->add_message('Datoen du skrev inn er ikke gyldig.', "error");
						}
						
						// kontroller tidspunktet
						elseif (!($abs_time_m = check_date($abs_time, "%h:%i:%s")))
						{
							ess::$b->page->add_message('Tidspunktet du skrev inn er ikke gyldig.', "error");
						}
						
						else
						{
							// ok
							$date = ess::$b->date->get();
							$date->setTime($abs_time_m[1], $abs_time_m[2], $abs_time_m[3]);
							$date->setDate($abs_date_m[1], $abs_date_m[2], $abs_date_m[3]);
							$expire = $date->format("U");
						}
					}
					
					// relativt tidspunkt
					else
					{
						// sjekk uker
						if ($rel_weeks < 0 || $rel_weeks > 9)
						{
							ess::$b->page->add_message('Antall uker kan ikke være under 0 eller over 9.', "error");
						}
						
						// sjekk dager
						elseif ($rel_days < 0 || $rel_days > 6)
						{
							ess::$b->page->add_message('Antall dager kan ikke være under 0 eller over 6.', "error");
						}
						
						// sjekk timer
						elseif ($rel_hours < 0 || $rel_hours > 23)
						{
							ess::$b->page->add_message('Antall timer kan ikke være under 0 eller over 23.', "error");
						}
						
						// sjekk minutter
						elseif ($rel_mins < 0 || $rel_mins > 59)
						{
							ess::$b->page->add_message('Antall minutter kan ikke være under 0 eller over 59.', "error");
						}
						
						else
						{
							// ok
							$expire = time()+$rel_weeks*604800+$rel_days*86400+$rel_hours*3600+$rel_mins*60;
						}
					}
					
					// sjekke videre?
					if ($expire)
					{
						// sjekk at datoen er minst 1 min fremover i tid
						if ($expire < time()+60)
						{
							ess::$b->page->add_message('Du kan ikke legge til en blokkering for mindre enn 1 minutt.', "error");
						}
						
						else
						{
							// kontroller begrunnelse og intern informasjon
							$log = trim(postval("log"));
							$note = trim(postval("note"));
							
							// mangler begrunnelse?
							if ($log == "")
							{
								ess::$b->page->add_message('Mangler begrunnelse.', "error");
							}
							
							// mangler intern informasjon?
							elseif ($note == "")
							{
								ess::$b->page->add_message("Mangler intern informasjon", "error");
							}
							
							else
							{
								// forsøk å legg til blokkeringen
								$add = blokkeringer::add(page_min_side::$active_user->id, $type_id, $expire, $log, $note);
								if ($add !== true)
								{
									ess::$b->page->add_message("Det er allerede en blokkering på brukeren som varer til ".ess::$b->date->get($add['ub_time_expire'])->format().".", "error");
								}
								
								else
								{
									// legg til crewlogg
									crewlog::log("user_ban_active", page_min_side::$active_player->id, $log, array("type" => $type_id, "time_end" => $expire, "note" => $note));
									
									ess::$b->page->add_message('Brukeren er nå blokkert til '.ess::$b->date->get($expire)->format().'. ('.htmlspecialchars($type['title']).')');
									redirect::handle();
								}
							}
						}
					}
				}
				
				// handling: rediger blokkering
				elseif (isset($_POST['edit']) && !$active)
				{
					// ingen blokkering å redigere?
					ess::$b->page->add_message("Brukeren har ikke lengre denne blokkeringen.", "error");
				}
				elseif (isset($_POST['edit']))
				{
					// godkjent handling?
					if (isset($_POST['log_change']))
					{
						// kontroller verdier
						$date = postval("date");
						$time = postval("time");
						
						// kontroller datoen
						if (!($date_m = check_date($date, "%y-%m-%d")))
						{
							ess::$b->page->add_message('Datoen du skrev inn er ikke gyldig.', "error");
						}
						
						// kontroller tidspunktet
						elseif (!($time_m = check_date($time, "%h:%i:%s")))
						{
							ess::$b->page->add_message('Tidspunktet du skrev inn er ikke gyldig.', "error");
						}
						
						// tidspunktene er gyldige
						else
						{
							$date = ess::$b->date->get();
							$date->setTime($time_m[1], $time_m[2], $time_m[3]);
							$date->setDate($date_m[1], $date_m[2], $date_m[3]);
							$expire = $date->format("U");
							
							// sjekk at datoen er minst 1 min fremover i tid
							if ($expire < time()+60)
							{
								ess::$b->page->add_message('Du kan ikke legge til en blokkering for mindre enn 1 minutt.', "error");
							}
							
							else
							{
								// kontroller begrunnelse for utestengelse, begrunnelse for endring og intern informasjon
								$log_ban = trim(postval("log_ban"));
								$log_change = trim(postval("log_change"));
								$note = trim(postval("note"));
								
								// mangler begrunnelse for endring?
								if ($log_change == "")
								{
									ess::$b->page->add_message('Mangler begrunnelse for endring.', "error");
								}
								
								// mangler begrunnelse for utestengelse?
								elseif ($log_ban == "")
								{
									ess::$b->page->add_message('Mangler begrunnelse for utestengelse.', "error");
								}
								
								// mangler intern informasjon?
								elseif ($note == "")
								{
									ess::$b->page->add_message('Mangler intern informasjon.', "error");
								}
								
								// ingen endringer?
								elseif ($expire == $info['ub_time_expire'] && $log_ban == $info['ub_reason'] && $note == $info['ub_note'])
								{
									ess::$b->page->add_message('Ingen endringer ble utført.', "error");
								}
								
								else
								{
									// oppdater blokkeringen
									$edit = blokkeringer::edit($active['ub_id'], $expire, $log_ban, $note);
									if ($edit == 0)
									{
										ess::$b->page->add_message("Blokkeringen kunne ikke bli oppdatert. Den er mest sannsynlig ikke lengre aktiv.", "error");
									}
									
									else
									{
										// legg til crewlogg
										$data = array(
											"type" => $type_id,
											"time_end_old" => $info['ub_time_expire'],
											"log_old" => $info['ub_reason'],
											"note_old" => $info['ub_note']
										);
										if ($expire != $info['ub_time_expire'])
										{
											$data["time_end_new"] = $expire;
										}
										if ($log_ban != $info['ub_reason'])
										{
											$data["log_new"] = $log_ban;
										}
										if ($note != $info['ub_note'])
										{
											$data["note_new"] = $note;
										}
										crewlog::log("user_ban_change", page_min_side::$active_player->id, $log_change, $data);
										
										ess::$b->page->add_message('Du har oppdatert blokkeringen. Brukeren er nå blokkert til '.ess::$b->date->get($expire)->format().'. ('.htmlspecialchars($type['title']).')');
										redirect::handle();
									}
								}
							}
						}
					}
				}
				
				// handling: slett blokkering
				elseif (isset($_POST['delete']) && !$active)
				{
					// ingen blokkering å slette?
					ess::$b->page->add_message("Brukeren har ikke lengre denne blokkeringen.", "error");
				}
				
				elseif (isset($_POST['delete']))
				{
					// godkjent handling?
					if (isset($_POST['log']))
					{
						$log = trim(postval("log"));
						
						// mangler logg?
						if ($log == "")
						{
							ess::$b->page->add_message('Mangler begrunnelse.', "error");
						}
						
						else
						{
							// fjern blokkeringen
							$delete = blokkeringer::delete($active['ub_id']);
							if ($delete == 0)
							{
								ess::$b->page->add_message("Blokkeringen kunne ikke bli oppdatert. Den er mest sannsynlig ikke lengre aktiv.", "error");
							}
							
							else
							{
								// legg til crewlogg
								crewlog::log("user_ban_delete", page_min_side::$active_player->id, $log, array("type" => $type_id, "time_end" => $info['ub_time_expire'], "log" => $info['ub_reason'], "note" => $info['ub_note']));
								
								ess::$b->page->add_message('Du har fjernet blokkeringen. ('.htmlspecialchars($type['title']).')');
								redirect::handle();
							}
						}
					}
				}
				
				echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Blokkering: '.htmlspecialchars($type['title']).'<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<p class="r">Tilgangsnivå: '.access::name($type['access']).'</p>
			<p><u>Hensikt:</u> '.$type['description'].'</p>';
				
				// blokkert?
				if ($active)
				{
					echo '
			<p>Brukeren er blokkert.</p>
			<dl class="dd_right">
				<dt>Lagt til</dt>
				<dd>'.ess::$b->date->get($info['ub_time_added'])->format(date::FORMAT_SEC).'<br />'.game::timespan($info['ub_time_added'], game::TIME_ABS | game::TIME_ALL, 5).'</dd>
				<dt>Utestengt til</dt>
				<dd>'.ess::$b->date->get($info['ub_time_expire'])->format(date::FORMAT_SEC).'<br />'.game::counter($info['ub_time_expire']-time()).'</dd>
			</dl>
			<div class="section">
				<h2>Begrunnelse</h2>
				<div class="p">'.(($reason = game::bb_to_html($info['ub_reason'])) == "" ? 'Ikke oppgitt.' : $reason).'</div>
				<h2>Intern informasjon</h2>
				<div class="p">'.(($note = game::bb_to_html($info['ub_note'])) == "" ? 'Ikke oppgitt.' : $note).'</div>
			</div>';
					
					// handling: redigere blokkering
					if (isset($_POST['edit']))
					{
						echo '
			<p>Du er i ferd med å endre blokkeringen til brukeren.</p>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Til</dt>
					<dd>
						Dato:
						<input type="text" name="date" id="ban_date" value="'.htmlspecialchars(postval("date", ess::$b->date->get($info['ub_time_expire'])->format("Y-m-d"))).'" class="styled w80" />
						<input type="text" name="time" id="ban_time" value="'.htmlspecialchars(postval("time", ess::$b->date->get($info['ub_time_expire'])->format("H:i:s"))).'" class="styled w80" />
					</dd>
					<dt>Begrunnelse for endring</dt>
					<dd><textarea name="log_change" cols="30" rows="5">'.htmlspecialchars(postval("log_change")).'</textarea></dd>
					<dt>Begrunnelse for blokkering</dt>
					<dd><textarea name="log_ban" cols="30" rows="5">'.htmlspecialchars(postval("log_ban", $info['ub_reason'])).'</textarea></dd>
					<dt>Intern informasjon</dt>
					<dd><textarea name="note" cols="30" rows="5">'.htmlspecialchars(postval("note", $info['ub_note'])).'</textarea></dd>
					<dd>
						'.show_sbutton("Lagre endringer", 'name="edit"').'
						'.show_sbutton("Avbryt").'
					</dd>
				</dl>
			</form>';
					}
					
					// handling: slette blokkering
					elseif (isset($_POST['delete']))
					{
						echo '
			<p>Du er i ferd med å fjerne blokkeringen til brukeren.</p>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Begrunnelse for fjerning</dt>
					<dd><textarea name="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
					<form action="" method="post">
						<dd>
							'.show_sbutton("Fjern", 'name="delete"').'
							'.show_sbutton("Avbryt").'
						</dd>
					</form>
				</dl>
			</form>';
					}
					
					else
					{
						echo '
			<form action="" method="post">
				<p>
					'.show_sbutton("Endre", 'name="edit"').'
					'.show_sbutton("Fjern", 'name="delete"').'
					<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=blokk")).'" class="button">Tilbake</a>
				</p>
			</form>';
					}
				}
				
				// ingen blokkeringen finnes - vis skjema for å legge til blokkering
				else
				{
					$date_type = isset($_POST['type']) && $_POST['type'] == "abs" ? "abs" : "rel";
					$hide_rel = $date_type == "rel" ? '' : ' hide';
					$hide_abs = $date_type == "abs" ? '' : ' hide';
					
					echo '
			<p>Brukeren har ingen aktiv blokkering.</p>
			<form action="" method="post">
				<input type="hidden" name="date_type" value="'.$date_type.'" />
				<dl class="dd_right dl_2x">
					<dt class="date_rel'.$hide_rel.'">Varighet (<a href="#" onclick="handleClass(\'.date_abs\', \'.date_rel\', event, this.parentNode.parentNode); $(\'date_type\').value=\'abs\'">velg dato</a>)</dt>
					<dd class="date_rel'.$hide_rel.'">
						<input type="text" name="rel_weeks" class="styled w30 r" style="width: 10px" value="'.intval(postval("rel_weeks")).'" maxlength="1" /> uker
						<input type="text" name="rel_days" class="styled w30 r" style="width: 10px" value="'.intval(postval("rel_days")).'" maxlength="1" /> dager
						<input type="text" name="rel_hours" class="styled w30 r" style="width: 17px" value="'.intval(postval("rel_hours")).'" maxlength="2" /> timer
						<input type="text" name="rel_mins" class="styled w30 r" style="width: 17px" value="'.intval(postval("rel_mins")).'" maxlength="2" /> minutter
					</dd>
					<dt class="date_abs'.$hide_abs.'">Til (<a href="#" onclick="handleClass(\'.date_rel\', \'.date_abs\', event, this.parentNode.parentNode); $(\'date_type\').value=\'rel\'">velg varighet</a>)</dt>
					<dd class="date_abs'.$hide_abs.'">
						Dato:
						<input type="text" name="abs_date" value="'.htmlspecialchars(postval("abs_date", ess::$b->date->get()->format("Y-m-d"))).'" class="styled w80" />
						<input type="text" name="abs_time" value="'.htmlspecialchars(postval("abs_time", ess::$b->date->get()->format("H:i:s"))).'" class="styled w60" />
					</dd>
					<dt>Begrunnelse</dt>
					<dd><textarea name="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
					<dt>Intern informasjon</dt>
					<dd><textarea name="note" cols="30" rows="5">'.htmlspecialchars(postval("note")).'</textarea></dd>
					<dd>
						'.show_sbutton("Legg til blokkering", 'name="add"').'
						<a href="'.htmlspecialchars(page_min_side::addr(NULL, "a=blokk")).'" class="button">Tilbake</a>
					</dd>
				</dl>
			</form>';
				}
				
				echo '
		</div>
	</div>';
			}
			
			else
			{
				// filtrer ut de blokkeringene vi har tilgang til å sette
				$types = blokkeringer::$types;
				$links = array();
				foreach ($types as $id => $type)
				{
					if (!access::has($type['access']))
					{
						continue;
					}
					
					$links[$type['title']] = '
				<li><a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=blokk&t=$id")).'" title="'.htmlspecialchars($type['description']).'">'.htmlspecialchars($type['title']).'</a></li>';
				}
				
				// sorter
				ksort($links);
				$links = implode('', $links);
				
				// vis oversikt
				echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Blokkeringer<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Velg type:</p>';
				
				if ($links == '')
				{
					echo '
			<p>Du har ikke tilgang til noen blokkeringstyper.</p>';
				}
				
				else
				{
					echo '
			<ul>'.$links.'
			</ul>';
				}
				
				echo '
		</div>
	</div>';
				
				// hent alle aktive blokkeringer
				$result = ess::$b->db->query("SELECT ub_type, ub_time_expire, ub_reason FROM users_ban WHERE ub_u_id = ".page_min_side::$active_user->id." AND ub_time_expire > ".time()." ORDER BY ub_time_expire");
				if (mysql_num_rows($result) > 0)
				{
					echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Aktive blokkeringer<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<table class="table tablem" style="width: 100%">
				<thead>
					<tr>
						<th>Type</th>
						<th>Dato</th>
						<th>Begrunnelse</th>
					</tr>
				</thead>
				<tbody>';
					
					$i = 0;
					while ($row = mysql_fetch_assoc($result))
					{
						$type = blokkeringer::get_type($row['ub_type']);
						$access = access::has($type['access']);
						
						echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
						<td>'.($access ? '<a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=blokk&t={$row['ub_type']}")).'">' : '').htmlspecialchars($type['title']).($access ? '</a>' : '').'</td>
						<td>
							'.ess::$b->date->get($row['ub_time_expire'])->format(date::FORMAT_SEC).'<br />
							('.game::timespan($row['ub_time_expire'], game::TIME_ABS | game::TIME_ALL, 5).')
						</td>
						<td>'.game::format_data($row['ub_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</td>
					</tr>';
					}
					
					echo '
				</tbody>
			</table>
		</div>
	</div>';
				}
			}
		}
		
		// sende e-post?
		elseif ($subpage2 == "send_email")
		{
			ess::$b->page->add_title("Send e-post");
			
			// har tekst?
			$show_form = true;
			if (isset($_POST['text']) && !isset($_POST['edit']))
			{
				$subject = trim(postval("subject"));
				$text = trim(postval("text"));
				
				// mangler emne?
				if (empty($subject))
				{
					ess::$b->page->add_message("Du må fylle ut emnefeltet.", "error");
				}
				
				// mangler meldingen?
				elseif (empty($text))
				{
					ess::$b->page->add_message("Du må fylle ut innholdet.", "error");
				}
				
				else
				{
					$email_subject = $subject;
					$email_text = $text . "

--
".login::$user->player->data['up_name']."
www.kofradia.no

Denne meldingen ble sendt til ".page_min_side::$active_user->data['u_email']." som tilhører ".page_min_side::$active_player->data['up_name'];
					
					// godkjent?
					if (isset($_POST['send']))
					{
						// send e-posten
						$email = new email();
						$email->text = $email_text;
						$email->headers['BCC'] = "henvendelse@smafia.no";
						$email->headers['Reply-To'] = "henvendelse@smafia.no";
						$email->send(page_min_side::$active_user->data['u_email'], $email_subject);
						
						// legg til crewlogg
						crewlog::log("user_send_email", page_min_side::$active_player->id, NULL, array(
							"email" => page_min_side::$active_user->data['u_email'],
							"email_subject" => $email_subject,
							"email_content" => $email_text
						));
						
						ess::$b->page->add_message("E-posten ble sendt til ".htmlspecialchars(page_min_side::$active_user->data['u_email']).".");
						redirect::handle(page_min_side::addr(""));
					}
					
					echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Send e-post<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p><b>Mottaker:</b> '.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</p>
			<p><b>Emne:</b> '.htmlspecialchars($email_subject).'</p>
			<p style="font-family: monospace">'.nl2br(htmlspecialchars($email_text)).'</p>
			<form action="" method="post">
				<input type="hidden" id="email_subject" name="subject" value="'.htmlspecialchars($subject).'" />
				<input type="hidden" id="email_text" name="text" value="'.htmlspecialchars($text).'" />
				<p>'.show_sbutton("Send e-posten", 'name="send"').' '.show_sbutton("Tilbake / endre", 'name="edit"').'</p>
			</form>
		</div>
	</div>';
					
					$show_form = false;
				}
			}
			
			if ($show_form)
			{
				ess::$b->page->add_js_domready('$("email_subject").focus();');
				echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Send e-post<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<boxes />
			<p>Her sender du e-post til brukeren på vegne av Kofradia. Avsender vil være den normale avsendere all e-post fra Kofradia blir sendt fra.</p>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Mottaker</dt>
					<dd>'.htmlspecialchars(page_min_side::$active_user->data['u_email']).'</dd>
					<dt>Emne</dt>
					<dd><input type="text" value="'.htmlspecialchars(postval("subject")).'" name="subject" id="email_subject" class="styled w200" /></dd>
					<dt>Innhold</dt>
					<dd><textarea name="text" id="email_text" cols="50" rows="10">'.htmlspecialchars(postval("text", "Hei,\n\n")).'</textarea></dd>
					<dd>'.show_sbutton("Forhåndsvis / fortsett").'</dd>
				</dl>
			</form>
		</div>
	</div>';
			}
		}
		
		elseif ($subpage2 == "warning")
		{
			ess::$b->page->add_title("Gi advarsel til brukeren");
			
			$types = crewlog::$user_warning_types;
			
			// legge til advarsel?
			if (isset($_POST['log']))
			{
				$log = trim(postval("log"));
				$note = trim(postval("note"));
				$type = postval("type");
				$priority = (int) postval("priority");
				$notify = isset($_POST['notify']);
				
				if (empty($log) || empty($note))
				{
					ess::$b->page->add_message("Både begrunnelse og intern informasjon må fylles ut.", "error");
				}
				
				elseif (!isset($types[$type]))
				{
					ess::$b->page->add_message("Ugyldig kategori.", "error");
				}
				
				elseif ($priority < 1 || $priority > 3)
				{
					ess::$b->page->add_message("Ugylig alvorlighet.", "error");
				}
				
				else
				{
					$data = array(
						"type" => $types[$type],
						"note" => $note,
						"priority" => $priority
					);
					
					// legge til spillerlogg?
					if ($notify)
					{
						$data['notified'] = 1;
						$data['notified_id'] = player::add_log_static(gamelog::$items['advarsel'], urlencode($types[$type]).':'.urlencode($log), NULL, page_min_side::$active_player->id);
						ess::$b->page->add_message("Advarselen ble lagret. Brukeren ble informert.");
					}
					
					else
					{
						ess::$b->page->add_message("Advarselen ble lagret. Du har ikke informert brukeren om denne advarselen.");
					}
					
					// legg til advarselen
					crewlog::log("user_warning", page_min_side::$active_player->id, $log, $data);
					
					redirect::handle();
				}
			}
			
			echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Gi advarsel til brukeren<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<form action="" method="post">
				<boxes />
				<p>Dette kan benyttes som et verktøy for å gi advarsler til brukere. Det kan velges om brukeren skal motta advarselen eller ikke. Hvis man ikke velger å informere brukeren om noe, blir det alikevel søkbart i crewloggen for brukeren.</p>
				<p>Alvorligheten av advarselen blir benyttet for å automatisere en poengsum brukeren får avhengig av antall advarseler. En advarsel med høy alvorlighet varer lenger og teller mer enn en med lav alvorlighet.</p>
				<dl class="dd_right">
					<dt>Kategori</dt>
					<dd>
						<select name="type">';
			
			$type = isset($_POST['type']) && isset($types[$_POST['type']]) ? intval($_POST['type']) : false;
			if ($type === false) echo '
							<option value="">Velg ..</option>';
			
			foreach ($types as $key => $row)
			{
				echo '
							<option value="'.$key.'"'.($key === $type ? ' selected="selected"' : '').'>'.htmlspecialchars($row).'</option>';
			}
			
			echo '
						</select>
					</dd>
					<dt>Alvorlighet/prioritet</dt>
					<dd>
						<select name="priority">';
			
			$priority = isset($_POST['priority']) && is_numeric($_POST['priority']) && $_POST['priority'] >= 1 && $_POST['priority'] <= 3 ? $_POST['priority'] : 2;
			echo '
							<option value="1"'.($priority == 1 ? ' selected="selected"' : '').'>Lav</option>
							<option value="2"'.($priority == 2 ? ' selected="selected"' : '').'>Moderat</option>
							<option value="3"'.($priority == 3 ? ' selected="selected"' : '').'>Høy</option>
						</select>
					</dd>
				</dl>
				<p>Begrunnelse:</p>
				<p><textarea name="log" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(postval("log")).'</textarea></p>
				<p>Intern informasjon:</p>
				<p><textarea name="note" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(postval("note")).'</textarea></p>
				<p><input type="checkbox" name="notify"'.($_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['notify']) ? '' : ' checked="checked"').' id="warning_notify" /><label for="warning_notify"> Gi brukeren informasjon om denne advarselen. Kun kategori og begrunnelse vil bli oppgitt til brukeren som en logg i hendelser.</label></p>
				<p class="c">'.show_sbutton("Lagre").'</p>
			</form>
		</div>
	</div>';
			
			// analyser advarsler
			$lca_id = crewlog::$actions['user_warning'][0];
			
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 15);
			$result = $pagei->query("
				SELECT lc_id, lc_up_id, lc_time, lc_log, lcd_data_int
				FROM log_crew
					JOIN users_players ON lc_a_up_id = up_id AND up_u_id = ".page_min_side::$active_user->id."
					LEFT JOIN log_crew_data ON lcd_lc_id = lc_id AND lcd_lce_id = 5
				WHERE lc_lca_id = $lca_id AND (lcd_data_int IS NULL OR lcd_data_int = 0)
				ORDER BY lc_time DESC");
			
			$data = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$data[$row['lc_id']] = $row;
			}
			
			// sett opp data
			$data = crewlog::load_summary_data($data);
			
			echo '
	<div class="bg1_c '.(count($data) == 0 ? 'xsmall' : 'medium').'">
		<h1 class="bg1">Tidligere advarsler<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">';
			
			if (count($data) == 0)
			{
				echo '
			<p>Brukeren har ingen tidligere advarsler.</p>';
			}
			
			else
			{
				ess::$b->page->add_css('
.advarsel { border: 1px solid #292929; margin: 10px 0; padding: 0 10px }');
				
				foreach ($data as $row)
				{
					$priority = $row['data']['priority'] == 1 ? "lav" : ($row['data']['priority'] == 2 ? "moderat" : "høy");
					
					echo '
			<div class="advarsel">
				<p><b>'.ess::$b->date->get($row['lc_time'])->format().'</b>: '.$row['data']['type'].' (alvorlighet: <b>'.$priority.'</b>):</p>
				<ul>
					<li>'.game::format_data($row['lc_log']).'</li>
					<li>Internt notat: '.game::format_data($row['data']['note']).'</li>
				</ul>
				<p>'.(empty($row['data']['notified']) ? 'Ble IKKE varslet.' : 'Ble varslet.').' Av <user id="'.$row['lc_up_id'].'" /></p>
			</div>';
				}
				
				echo '
			<p class="c">'.$pagei->pagenumbers().'</p>';
			}
			
			echo '
		</div>
	</div>';
		}
		
		elseif ($subpage2 == "enote")
		{
			ess::$b->page->add_title("Endre notat for bruker");
			
			// lagre endringer?
			if (isset($_POST['notat']))
			{
				$notat = postval("notat");
				if ($notat == page_min_side::$active_user->data['u_note_crew'])
				{
					ess::$b->page->add_message("Ingen endringer ble utført.", "error");
				}
				
				else
				{
					ess::$b->db->query("UPDATE users SET u_note_crew = ".ess::$b->db->quote($notat)." WHERE u_id = ".page_min_side::$active_user->id);
					
					// legg til crewlogg
					crewlog::log("user_note_crew", page_min_side::$active_player->id, NULL, array(
						"note_old" => page_min_side::$active_user->data['u_note_crew'],
						"note_diff" => diff::make(page_min_side::$active_user->data['u_note_crew'], $notat))
					);
					
					ess::$b->page->add_message("Notatet ble endret.");
					redirect::handle();
				}
			}
			
			echo '
	<div class="bg1_c" style="width: 400px">
		<h1 class="bg1">Endre crewnotat for bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<form action="" method="post">
				<p>Dette endrer notatet som er tilknyttet brukeren. Du kan også tilknytte <a href="'.htmlspecialchars(page_min_side::addr(NULL, "b=enote", "player")).'">informasjon til spilleren</a>, hvis det heller er ønskelig.</p>
				<p>Notat:</p>
				<p><textarea name="notat" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(page_min_side::$active_user->data['u_note_crew']).'</textarea></p>
				<p class="c">'.show_sbutton("Lagre").'</p>
			</form>
		</div>
	</div>';
		}
		
		elseif ($subpage2 == "level" && access::has("admin"))
		{
			// nivåer man kan bytte til
			static $levels = array(
				1 => "Vanlig bruker",
				14 => "Skjult nostat (crewtilgang)",
				-4 => "Ressurs",
				12 => "Ressurs (nostat)",
				13 => "Utvikler",
				4 => "Forummoderator",
				6 => "Forummoderator (nostat)",
				5 => "Moderator",
			);
			if (access::has("sadmin")) $levels[7] = "Administrator";
			if (access::has("sadmin")) $levels[8] = "Superadministrator";
			
			// kan vi ikke endre brukernivået til denne brukeren?
			if (!isset($levels[page_min_side::$active_user->data['u_access_level']]))
			{
				ess::$b->page->add_message("Du har ikke rettigheter til å endre tilgangsnivået til denne brukeren.", "error");
				redirect::handle(page_min_side::addr());
			}
			
			// endre brukernivå?
			if (isset($_POST['level']))
			{
				$level = intval($_POST['level']);
				$log = trim(postval("log"));
				
				// samme brukernivå?
				if ($level == page_min_side::$active_user->data['u_access_level'])
				{
					ess::$b->page->add_message("Du må velge et nytt tilgangsnivå.", "error");
				}
				
				// ikke gyldig brukernivå?
				elseif (!isset($levels[$level]))
				{
					ess::$b->page->add_message("Ugyldig tilgangsnivå.");
				}
				
				// mangler begrunnelse?
				elseif (empty($log))
				{
					ess::$b->page->add_message("Mangler begrunnelse.");
				}
				
				else
				{
					// endre tilgangsnivå
					$old = page_min_side::$active_user->data['u_access_level'];
					if (page_min_side::$active_user->change_level($level, isset($_POST['no_update_up'])))
					{
						// e-post logg
						sysreport::log("Endring av tilgangsnivå: ".login::$user->player->data['up_name']." endret tilgangsnivået til ".page_min_side::$active_user->data['u_email']." (".page_min_side::$active_player->data['up_name'].") fra {$levels[$old]} til {$levels[$level]} {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id."\n\nBegrunnelse: ".strip_tags(game::format_data($log)), "Kofradia: Endring av tilgangsnivå for ".page_min_side::$active_user->data['u_email']." (".page_min_side::$active_player->data['up_name'].")");
						
						// finn totalt beløp spilleren har
						$result = ess::$b->db->query("SELECT up_cash + up_bank FROM users_players WHERE up_id = ".page_min_side::$active_player->id);
						$money = mysql_result($result, 0);
						
						// crewlogg
						$data = array(
							"level_old" => $old,
							"level_old_text" => $levels[$old],
							"level_new" => $level,
							"level_new_text" => $levels[$level],
							"money" => $money,
							"points" => page_min_side::$active_player->data['up_points']
						);
						if (page_min_side::$active_player->active && !isset($_POST['no_update_up'])) $data['up_id'] = page_min_side::$active_player->id;
						crewlog::log("user_level", page_min_side::$active_player->id, $log, $data);
						
						putlog("CREWCHAN", "%bEndring av tilgangsnivå%b: ".login::$user->player->data['up_name']." endret tilgangsnivået til ".page_min_side::$active_user->data['u_email']." (".page_min_side::$active_player->data['up_name'].") fra {$levels[$old]} til {$levels[$level]} {$__server['path']}/min_side?u_id=".page_min_side::$active_user->id);
						ess::$b->page->add_message('Tilgangsnivået ble endret fra <b>'.htmlspecialchars($levels[$old]).'</b> til <b>'.htmlspecialchars($levels[$level]).'</b>.');
					}
					else
					{
						ess::$b->page->add_message("Tilgangsnivået kunne ikke endres.", "error");
					}
					redirect::handle();
				}
			}
			
			echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Endre tilgangsnivå for bruker<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">'.(page_min_side::$active_player->active ? '
			<p>Dette vil automatisk berøre spilleren '.page_min_side::$active_player->profile_link().'.<p>' : '
			<p>Dette vil kun ha innvirkning på brukeren, siden det ikke er noen aktiv spiller.</p>').'
			<form action="" method="post">
				<dl class="dd_right">
					<dt>Nåværende tilgangsnivå</dt>
					<dd>'.$levels[page_min_side::$active_user->data['u_access_level']].'</dd>
					<dt>Nytt tilgangsnivå</dt>
					<dd>
						<select name="level">';
					
					$level = intval(postval("level", page_min_side::$active_user->data['u_access_level']));
					foreach ($levels as $id => $name)
					{
						echo '
							<option value="'.$id.'"'.($level == $id ? ' selected="selected"' : '').'>'.htmlspecialchars($name).'</option>';
					}
					
					echo '
						</select>
					</dd>
					<dt>Begrunnelse</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>'.(page_min_side::$active_player->active ? '
				<p><input type="checkbox" id="no_update_up" name="no_update_up"'.(isset($_POST['no_update_up']) ? ' checked="checked"' : '').' /><label for="no_update_up"> Ikke oppdater det visuelle tilgangsnivået til '.page_min_side::$active_player->profile_link().'</label></p>' : '').'
				<p class="c">'.show_sbutton("Endre tilgangsnivå").'</p>
			</form>
		</div>
	</div>';
		}
		
		// endre bankpassord
		elseif ($subpage2 == "banka" && access::has("mod"))
		{
			// lagre nytt passord
			if (isset($_POST['bank_auth']))
			{
				$bank_auth = postval("bank_auth");
				$log = trim(postval("log"));
				
				// for kort?
				if (strlen($bank_auth) < 6)
				{
					ess::$b->page->add_message("Passordet må inneholde minst 6 tegn.", "error");
				}
				
				// samme som nåværende?
				elseif (password::verify_hash($bank_auth, page_min_side::$active_user->data['u_bank_auth'], 'bank_auth'))
				{
					ess::$b->page->add_message("Passordet er det samme som nåværende.", "error");
				}
				
				// mangler begrunnelse?
				elseif ($log == "")
				{
					ess::$b->page->add_message("Mangler begrunnelse.", "error");
				}
				
				else
				{
					$newpass = password::hash($bank_auth, null, 'bank_auth');
					ess::$b->db->query("UPDATE users SET u_bank_auth = ".ess::$b->db->quote($newpass)." WHERE u_id = ".page_min_side::$active_user->id);
					
					// crewlogg
					crewlog::log("user_bank_auth", page_min_side::$active_player->id, $log, array(
						"pass_old" => page_min_side::$active_user->data['u_bank_auth'],
						"pass_new" => $newpass));
					
					ess::$b->page->add_message("Bankpassordet ble endret.");
					redirect::handle();
				}
			}
			
			ess::$b->page->add_title("Endre bankpassord");
			
			echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Endre bankpassord<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<form action="" method="post" autocomplete="off">
				<dl class="dd_right">
					<dt>Nytt bankpassord</dt>
					<dd><input type="password" id="bank_auth" class="styled w120" /></dd>
					<dt>Begrunnelse for endring</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Lagre").'</p>
			</form>
		</div>
	</div>';
		}
		
		// endre mobilnummer
		elseif ($subpage2 == "phone" && access::has("mod"))
		{
			// lagre nytt nummer?
			if (isset($_POST['phone']))
			{
				$phone = postval("phone");
				$log = trim(postval("log"));
				if (!preg_match("/^47\\d{8}$/D", $phone) && $phone != "")
				{
					ess::$b->page->add_message("Ugyldig telefonnummer. Må bestå av 10 tall inkludert 47 først.", "error");
				}
				
				else
				{
					// kontroller at nummeret ikke er lagt inn fra før
					$result = ess::$b->db->query("SELECT u_id, u_email, up_id, up_name, up_access_level FROM users, users_players WHERE u_phone = ".ess::$b->db->quote($phone)." AND u_id != ".page_min_side::$active_user->id." AND up_id = u_active_up_id LIMIT 1");
					if (mysql_num_rows($result) > 0)
					{
						$row = mysql_fetch_assoc($result);
						ess::$b->page->add_message('Nummeret er allerede i bruk av <a href="min_side?u_id='.$row['u_id'].'">'.htmlspecialchars($row['u_email']).'</a> ('.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).').', "error");
					}
					
					// samme nummer?
					elseif ($phone == page_min_side::$active_user->data['u_phone'])
					{
						ess::$b->page->add_message("Nummeret er det samme som nåværende nummer.", "error");
					}
					
					// mangler logg?
					elseif ($log == "")
					{
						ess::$b->page->add_message("Mangler logg melding.");
					}
					
					else
					{
						// lagre nytt nummer
						ess::$b->db->query("UPDATE users SET u_phone = ".ess::$b->db->quote($phone)." WHERE u_id = ".page_min_side::$active_user->id);
						
						crewlog::log("user_phone", page_min_side::$active_player->id, $log, array(
							"phone_old" => page_min_side::$active_user->data['u_phone'],
							"phone_new" => $phone));
						
						ess::$b->page->add_message('Mobilnummeret ble endret fra <b>'.(empty(page_min_side::$active_user->data['u_phone']) ? 'tomt' : htmlspecialchars(page_min_side::$active_user->data['u_phone'])).'</b> til <b>'.(empty($phone) ? 'tomt' : $phone).'</b>.');
					}
				}
			}
			
			ess::$b->page->add_title("Endre mobilnummer");
			
			echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Endre mobilnummer<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Her endrer du mobilnummeret til brukeren. Dette kan bli brukt til å sende ut forskjellig informasjon.</p>
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Nåværende nummer</dt>
					<dd>'.(empty(page_min_side::$active_user->data['u_phone']) ? 'Tomt' : htmlspecialchars(page_min_side::$active_user->data['u_phone'])).'</dd>
					<dt>Nytt nummer</dt>
					<dd><input type="text" maxlength="10" value="'.htmlspecialchars(postval("phone", page_min_side::$active_user->data['u_phone'])).'" name="phone" class="styled w80" /></dd>
					<dt>Begrunnelse for endring</dt>
					<dd><textarea name="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Lagre").'</p>
			</form>
		</div>
	</div>';
		}
		
		// endre fødselsdato
		elseif ($subpage2 == "birth" && access::has("mod"))
		{
			// lagre ny fødselsdato?
			if (isset($_POST['birth_day']) && isset($_POST['birth_month']) && isset($_POST['birth_year']))
			{
				$birth = postval("birth");
				
				// sjekk fødselsdato
				$birth_day = intval(postval("birth_day"));
				$birth_month = intval(postval("birth_month"));
				$birth_year = intval(postval("birth_year"));
				
				$date = ess::$b->date->get();
				$n_day = $date->format("j");
				$n_month = $date->format("n");
				$n_year = $date->format("Y");
				
				$age = $n_year - $birth_year - (($n_month < $birth_month || ($birth_month == $n_month && $n_day < $birth_day)) ? 1 : 0);
				$birth = $birth_year."-".str_pad($birth_month, 2, "0", STR_PAD_LEFT)."-".str_pad($birth_day, 2, "0", STR_PAD_LEFT);
				
				// sjekk om fødselsdatoen er gyldig
				$birth_date = ess::$b->date->get();
				$birth_date->setDate($birth_year, $birth_month, $birth_day);
				$birth_valid = $birth_date->format("Y-m-d") == $birth;
				
				$log = trim(postval("log"));
				
				// ugyldig dag?
				if ($birth_day < 0 || $birth_day > 31)
				{
					ess::$b->page->add_message("Du må velge en gyldig dag.", "error");
				}
				
				// ugyldig måned?
				elseif ($birth_month < 0 || $birth_month > 12)
				{
					ess::$b->page->add_message("Du må velge en gyldig måned.", "error");
				}
				
				// ugyldig år
				elseif (($birth_year < 1900 || $birth_year > $n_year) && $birth_year !== 0)
				{
					ess::$b->page->add_message("Du må velge et gyldig år.", "error");
				}
				
				// ugyldig fødselsdato?
				elseif (!$birth_valid && $birth !== '0-00-00')
				{
					ess::$b->page->add_message("Datoen du fylte inn for fødselsdatoen din eksisterer ikke.");
				}
				
				// samme som tidligere?
				elseif ($birth == page_min_side::$active_user->data['u_birth'])
				{
					ess::$b->page->add_message("Fødselsdatoen ble ikke endret.", "error");
				}
				
				// mangler begrunnelse?
				elseif ($log == "")
				{
					ess::$b->page->add_message("Mangler begrunnelse.", "error");
				}
				
				else
				{
					// oppdater
					ess::$b->db->query("UPDATE users SET u_birth = ".ess::$b->db->quote($birth)." WHERE u_id = ".page_min_side::$active_user->id);
					
					// legg til crewlogg
					crewlog::log("user_birth", page_min_side::$active_player->id, $log, array(
						"birth_old" => page_min_side::$active_user->data['u_birth'],
						"birth_new" => $birth));
					
					// alder
					if ($age < 13)
					{
						ess::$b->page->add_message("Fødselsdatoen ble satt til <b>$birth</b> ($age år). Brukeren oppfyller <u>ikke</u> kravet om alder jf. betingelsene.");
					}
					
					else
					{
						ess::$b->page->add_message("Fødselsdatoen ble satt til <b>$birth</b> ($age år).");
					}
					
					redirect::handle();
				}
			}
			
			$birth = explode("-", page_min_side::$active_user->data['u_birth']);
			$birth_day = isset($birth[2]) ? intval($birth[2]) : 0;
			$birth_month = isset($birth[1]) ? intval($birth[1]) : 0;
			$birth_year = isset($birth[0]) ? intval($birth[0]) : 0;
			
			ess::$b->page->add_title("Endre fødselsdato");
			
			echo '
	<div class="bg1_c" style="width: 350px">
		<h1 class="bg1">Endre fødselsdato<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Nåværende fødselsdato</dt>
					<dd>'.(empty(page_min_side::$active_user->data['u_birth']) ? 'Ikke registrert' : htmlspecialchars(page_min_side::$active_user->data['u_birth'])).'</dd>
					<dt>Ny fødselsdato</dt>
					<dd>
						<select name="birth_day">
							<option value="">Dag</option>
							<option value="0">0</option>';
			
			$active = postval("birth_day", $birth_day);
			for ($i = 1; $i <= 31; $i++)
			{
				echo '
							<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.$i.'</option>';
			}
			
			echo '
						</select>
						<select name="birth_month">
							<option value="">Måned</option>
							<option value="0">Tom</option>';
			
			$active = postval("birth_month", $birth_month);
			for ($i = 1; $i <= 12; $i++)
			{
				echo '
							<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.ucfirst($_lang['months'][$i]).'</option>';
			}
			
			echo '
						</select>
						<select name="birth_year">
							<option value="">År</option>
							<option value="0">0000</option>';
			
			$active = postval("birth_year", $birth_year);
			for ($i = ess::$b->date->get()->format("Y"); $i >= 1900; $i--)
			{
				echo '
							<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.$i.'</option>';
			}
			
			echo '
						</select>
					</dd>
					<dt>Begrunnelse for endring</dt>
					<dd><textarea name="log" id="log" cols="30" rows="5">'.htmlspecialchars(postval("log")).'</textarea></dd>
				</dl>
				<p class="c">'.show_sbutton("Lagre").'</p>
			</form>
		</div>
	</div>';
		}
	}
	
	/**
	 * Spillere tilhørende brukeren
	 */
	protected static function page_up()
	{
		// hent spillerene tilhørende denne personen
		$pagei = new pagei(pagei::ACTIVE_GET, "side_up", pagei::PER_PAGE, 15);
		$result = $pagei->query("
			SELECT up_id, up_name, up_access_level, up_created_time, up_points, up_deactivated_time, up_hits, up_cash+up_bank money, upr_rank_pos
			FROM users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE up_u_id = ".page_min_side::$active_user->id."
			ORDER BY up_last_online DESC");
		
		echo '
		<div class="bg1_c">
			<h1 class="bg1">Spillere tilhørende brukeren<span class="left2"></span><span class="right2"></span></h1>
			<div class="bg1">
				<table class="table '.($pagei->pages == 1 ? 'tablem' : 'tablemt').'" style="width: 100%">
					<thead>
						<tr>
							<th>Spiller</th>
							<th>Opprettet</th>
							<th>Rank</th>
							<th>Penger</th>
							<th>Visninger</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>';
		
		while ($row = mysql_fetch_assoc($result))
		{
			$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
			echo '
						<tr>
							<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'], true, 'min_side?up_id='.$row['up_id']).'</td>
							<td>'.ess::$b->date->get($row['up_created_time'])->format().'</td>
							<td>'.$rank['name'].($rank['orig'] ? '<br />('.$rank['orig'].')' : '').'</td>
							<td class="r">'.game::format_cash($row['money']).'</td>
							<td class="r">'.game::format_number($row['up_hits']).'</td>
							<td>'.($row['up_access_level'] == 0 ? 'Deaktivert:<br />'.ess::$b->date->get($row['up_deactivated_time'])->format() : 'Status: I live').'</td>
						</tr>';
		}
		
		echo '
					</tbody>
				</table>'.($pagei->pages > 1 ? '
				<p class="c">'.$pagei->pagenumbers().'</p>' : '').'
			</div>
		</div>';
	}
}
