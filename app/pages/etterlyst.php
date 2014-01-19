<?php

/**
 * Etterlyst (samme som en hitlist)
 */
class page_etterlyst extends pages_player
{
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		ess::$b->page->add_title("Etterlyst");
		
		$this->handle();
		ess::$b->page->load();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function handle()
	{
		// legge til spiller?
		if (isset($_GET['add']))
		{
			$this->show_add_player();
		}
		
		// kjøpe ut spiller?
		elseif (isset($_GET['free']))
		{
			$this->show_free_player();
		}
		
		// vise detaljer?
		elseif (isset($_GET['up_id']) && access::has("mod"))
		{
			$this->show_details();
		}
		
		// trekke tilbake dusør
		elseif (isset($_POST['release']))
		{
			$this->show_release();
		}
		
		else
		{
			$this->show_hitlist();
		}
	}
	
	/**
	 * Vis listen
	 */
	protected function show_hitlist()
	{
		global $__server;
		
		// hent alle oppføringene sortert med høyeste dusør øverst
		$expire = etterlyst::get_freeze_expire();
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT hl_up_id, SUM(hl_amount_valid) AS sum_hl_amount_valid, SUM(IF(hl_time < $expire, hl_amount_valid, 0)) AS sum_can_remove
			FROM hitlist
			GROUP BY hl_up_id
			ORDER BY sum_hl_amount_valid DESC");
		
		echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Etterlyst<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_left"><a href="'.ess::$s['rpath'].'/node/44">Hjelp</a></p>
	<div class="bg1">
		<boxes />';
		
		if ($pagei->total == 0)
		{
			echo '
		<p>Ingen spillere er etterlyst for øyeblikket.</p>';
		}
		
		else
		{
			// sett opp liste over alle spillerene
			$up_ids = array();
			$list = array();
			while ($row = $result->fetch())
			{
				$up_ids[] = $row['hl_up_id'];
				$list[] = $row;
			}
			
			// hent alle FF hvor spilleren var medlem
			$result_ff = \Kofradia\DB::get()->query("
				SELECT ffm_up_id, ffm_priority, ff_id, ff_name, ff_type
				FROM ff_members
					JOIN ff ON ff_id = ffm_ff_id AND ff_inactive = 0 AND ff_is_crew = 0
				WHERE ffm_up_id IN (".implode(",", array_unique($up_ids)).") AND ffm_status = ".ff_member::STATUS_MEMBER."
				ORDER BY ff_name");
			$ff_list = array();
			while ($row = $result_ff->fetch())
			{
				$pos = ucfirst(ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']]);
				$text = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ff_name']).'</a>';
				$ff_list[$row['ffm_up_id']][] = $text;
			}
			
			echo '
		<p>Spillere som er etterlyst:</p>
		<table class="table center">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Broderskap/firma</th>
					<th>Dusør</th>
					<th>Dusør som<br />kan kjøpes ut</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($list as $row)
			{
				$links = array();
				if ($row['hl_up_id'] != $this->up->id) $links[] = '<a href="?add&amp;up_id='.$row['hl_up_id'].'">øk dusør</a>';
				if ($row['sum_can_remove'] > 0) $links[] = '<a href="?free='.$row['hl_up_id'].'">kjøp ut</a>';
				
				$ff = isset($ff_list[$row['hl_up_id']]) ? implode("<br />", $ff_list[$row['hl_up_id']]) : '&nbsp;';
				
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$row['hl_up_id'].'" /></td>
					<td>'.$ff.'</td>'.(access::has("mod") ? '
					<td class="r"><a href="?up_id='.$row['hl_up_id'].'">'.game::format_cash($row['sum_hl_amount_valid']).'</a></td>' : '
					<td class="r">'.game::format_cash($row['sum_hl_amount_valid']).'</td>').'
					<td class="r">'.game::format_cash($row['sum_can_remove']).'</td>
					<td>'.(count($links) == 0 ? '&nbsp;' : implode(" ", $links)).'</td>
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
		<p>Hvis du skader en spiller som det er satt en dusør på vil du motta deler av dusøren. Hvis denne spilleren dør vil du motta det gjenstående av dusøren. Det er ikke mulig å kjøpe ut dusører som har blitt satt de siste 7 dagene. <a href="'.$__server['relative_path'].'/node/44">Mer informasjon &raquo;</a></p>
		<p><a href="?add">Sett dusør på en spiller &raquo;</a></p>
	</div>
</div>';
		
		// hent egne dusører
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side_by");
		$result = $pagei->query("
			SELECT hl_id, hl_up_id, hl_time, hl_amount, hl_amount_valid
			FROM hitlist
			WHERE hl_by_up_id = ".$this->up->id."
			ORDER BY hl_time DESC");
		
		if ($pagei->total > 0)
		{
			echo '
<div class="bg1_c small" style="width: 450px">
	<h2 class="bg1">Mine dusører<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<p>Dette er dusørene du har plassert på andre spillere som fremdeles er gyldige. Hvis du velger å trekke en dusør, får du kun igjen <b>50 %</b> av dusøren.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="release" />
			<table class="table center">
				<thead>
					<tr>
						<th>Spiller og tidspunkt</th>
						<th>Opprinnelig beløp</th>
						<th>Gjenstående beløp</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			while ($row = $result->fetch())
			{
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="hl_id" value="'.$row['hl_id'].'" /><user id="'.$row['hl_up_id'].'" /><br /><span class="dark">'.ess::$b->date->get($row['hl_time'])->format().'</span></td>
						<td class="r">'.game::format_cash($row['hl_amount']).'</td>
						<td class="r">'.game::format_cash($row['hl_amount_valid']).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Trekk tilbake").'</p>
			<p>Gjenstående beløp er det beløpet som enda ikke er kjøpt ut av andre spillere.</p>
		</form>
	</div>
</div>';
		}
	}
	
	/**
	 * Sette dusør på en spiller
	 */
	protected function show_add_player()
	{
		ess::$b->page->add_title("Sett dusør");
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Etterlyst - sett dusør<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1"><boxes />';
		
		// har vi valgt en spiller?
		$player = false;
		if (isset($_GET['up_id']) || isset($_POST['up_id']) || isset($_POST['up_name']))
		{
			$by_id = isset($_GET['up_id']) ? (int) $_GET['up_id'] : (isset($_POST['up_id']) ? (int) $_POST['up_id'] : false);
			
			// finn spilleren
			$search = "";
			if ($by_id !== false)
			{
				$search = "up_id = $by_id";
			}
			else
			{
				$search = "up_name = ".\Kofradia\DB::quote($_POST['up_name'])." ORDER BY up_access_level = 0, up_last_online DESC LIMIT 1";
			}
			
			$result = \Kofradia\DB::get()->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE $search");
			$player = $result->fetch();
			
			// fant ikke?
			if (!$player)
			{
				ess::$b->page->add_message("Fant ikke spilleren.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
			}
			
			// død spiller?
			if ($player && $player['up_access_level'] == 0)
			{
				ess::$b->page->add_message('Spilleren <user id="'.$player['up_id'].'" /> er død og kan ikke etterlyses.', "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
			
			// seg selv?
			if ($player && $player['up_id'] == $this->up->id)
			{
				ess::$b->page->add_message("Du kan ikke sette dusør på deg selv.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
			
			// nostat?
			if ($player['up_access_level'] >= ess::$g['access_noplay'] && !access::is_nostat())
			{
				ess::$b->page->add_message("Du kan ikke sette dusør på en nostat.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
			
			// er nostat og prøver å sette dusør på en spiller som ikke er nostat?
			if (access::is_nostat() && $player['up_access_level'] < ess::$g['access_noplay'] && !access::has("sadmin"))
			{
				ess::$b->page->add_message("Du er nostat og kan ikke sette dusør på en vanlig spiller.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
		}
		
		// bestemme dusør?
		if ($player)
		{
			// hent eventuelle aktive dusører på spilleren
			$result = \Kofradia\DB::get()->query("
				SELECT SUM(hl_amount_valid) AS sum_hl_amount_valid
				FROM hitlist
				WHERE hl_up_id = {$player['up_id']}");
			$a = $result->fetchColumn(0);
			
			// må vi vente?
			$wait = false;
			if ($a == 0 && !access::has("admin"))
			{
				// sjekk når siste hitlist ble utført
				$last = $this->up->params->get("hitlist_last_new", false);
				if ($last && $last + etterlyst::WAIT_TIME > time())
				{
					$wait = $last + etterlyst::WAIT_TIME - time();
				}
			}
			
			// legge til dusøren?
			if (isset($_POST['amount']) && !$wait)
			{
				$amount = game::intval($_POST['amount']);
				
				// høy nok dusør?
				if ($amount < etterlyst::MIN_AMOUNT_SET)
				{
					ess::$b->page->add_message("Dusøren må være på minimum ".game::format_cash(etterlyst::MIN_AMOUNT_SET).".", "error");
				}
				
				else
				{
					\Kofradia\DB::get()->beginTransaction();
					
					// forsøk å trekk fra pengene
					$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $amount WHERE up_id = ".$this->up->id." AND up_cash >= $amount");
					if ($a == 0)
					{
						ess::$b->page->add_message("Du har ikke nok penger på hånda.", "error");
					}
					
					else
					{
						// vellykket
						\Kofradia\DB::get()->exec("INSERT INTO hitlist SET hl_up_id = {$player['up_id']}, hl_by_up_id = ".$this->up->id.", hl_time = ".time().", hl_amount = $amount, hl_amount_valid = $amount");
						\Kofradia\DB::get()->commit();
						
						// legg til i loggen til spilleren
						player::add_log_static("etterlyst_add", NULL, $amount, $player['up_id']);
						
						putlog("LOG", "ETTERLYST: ".$this->up->data['up_name']." la til dusør for UP_ID={$player['up_id']} på ".game::format_cash($amount).'.');
						putlog("INFO", "ETTERLYST: En spiller la til en dusør for {$player['up_name']} på ".game::format_cash($amount)." ".ess::$s['path']."/etterlyst");
						
						ess::$b->page->add_message('Du la til '.game::format_cash($amount).' som dusør for spilleren <user id="'.$player['up_id'].'" />.');
						$this->up->params->update("hitlist_last_new", time(), true);
						
						redirect::handle("etterlyst");
					}
					
					\Kofradia\DB::get()->commit();
				}
			}
			
			ess::$b->page->add_js_domready('$("select_amount").focus();');
			
			echo '
		<p>Valgt spiller: <user id="'.$player['up_id'].'" /></p>
		<form action="" method="post">
			<input type="hidden" name="up_id" value="'.$player['up_id'].'" />'.(!$a ? '
			<p>Denne spilleren har ingen dusør tilnyttet seg fra før.</p>' : '
			<p>Denne spilleren har allerede en dusør på '.game::format_cash($a).'.</p>').($wait ? '
			<p class="error_box">Du må vente '.game::counter($wait).' før du kan plassere en ny spiller på listen.</p>
			<p class="c"><a href="etterlyst">Avbryt</a></p>' : '
			<dl class="dd_right">
				<dt>'.(!$a ? 'Dusør' : 'Øk dusøren med').'</dt>
				<dd><input type="text" name="amount" id="select_amount" value="'.htmlspecialchars(postval("amount")).'" class="styled w100" /></dd>
			</dl>
			<p class="c">'.show_sbutton($a ? "Øk dusøren" : "Legg til dusør").'</p>
			<p class="c"><a href="etterlyst">Avbryt</a> - <a href="etterlyst?add">Velg en annen spiller</a></p>
			<p>Hvis du velger å fjerne dusøren etter du har lagt den til, får du kun 50 % igjen. Hvis noen kjøper ut dusøren får du igjen 50 % av den.</p>').'
		</form>';
		}
		
		// velg spiller
		else
		{
			ess::$b->page->add_js_domready('$("select_up_name").focus();');
			
			echo '
		<p>Du må først velge hvilken spiller du ønsker å legge til dusør på.</p>
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Spiller</dt>
				<dd><input type="text" name="up_name" id="select_up_name" value="'.htmlspecialchars(postval("up_name")).'" class="styled w120" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Finn spiller").'</p>
			<p class="c"><a href="etterlyst">Avbryt</a></p>
		</form>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Kjøpe ut en spiller
	 */
	protected function show_free_player()
	{
		$up_id = (int) getval("free");
		
		// hent informasjon om spilleren
		$expire = etterlyst::get_freeze_expire();
		$result = \Kofradia\DB::get()->query("
			SELECT SUM(hl_amount_valid) AS sum_hl_amount_valid, SUM(IF(hl_time < $expire, hl_amount_valid, 0)) AS sum_can_remove
			FROM hitlist
			WHERE hl_up_id = $up_id
			GROUP BY hl_up_id");
		
		$hl = $result->fetch();
		if (!$hl)
		{
			ess::$b->page->add_message('Spilleren <user id="'.$hl['hl_up_id'].'" /> har ingen dusør på seg.', "error");
			redirect::handle("etterlyst");
		}
		
		// kan ikke kjøpe ut noe?
		if ($hl['sum_can_remove'] == 0)
		{
			ess::$b->page->add_message('Du må vente lenger for å kunne kjøpe ut dusøren til <user id="'.$up_id.'" />.', "error");
			redirect::handle("etterlyst");
		}
		
		$least = min(max(etterlyst::MIN_AMOUNT_BUYOUT, etterlyst::MIN_AMOUNT_BUYOUT_RATIO * $hl['sum_can_remove']), $hl['sum_can_remove']);
		
		// kjøpe ut?
		if (isset($_POST['amount']))
		{
			$amount = game::intval($_POST['amount']);
			
			// under minstebeløpet?
			if ($amount < $least)
			{
				ess::$b->page->add_message("Beløpet kan ikke være mindre enn ".game::format_cash($least).".", "error");
			}
			
			else
			{
				// beregn kostnad
				$m = $up_id == $this->up->id ? 3 : 2;
				$result = \Kofradia\DB::get()->query("SELECT $amount * $m, $amount > {$hl['sum_can_remove']}, $amount * $m > ".$this->up->data['up_cash']);
				$row = $result->fetch(\PDO::FETCH_NUM);
				$price = $row[0];
				
				// for høyt beløp?
				if ($row[1])
				{
					ess::$b->page->add_message("Beløpet var for høyt.", "error");
				}
				
				// har ikke nok penger?
				elseif ($row[2])
				{
					ess::$b->page->add_message("Du har ikke nok penger på hånda. Du må ha ".game::format_cash($price)." på hånda for å kunne betale ut ".game::format_cash($amount).".", "error");
				}
				
				else
				{
					\Kofradia\DB::get()->beginTransaction();
					
					// forsøk å trekk fra pengene
					$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = ".$this->up->id." AND up_cash >= $price");
					if ($a == 0)
					{
						ess::$b->page->add_message("Du har ikke nok penger på hånda. Du må ha ".game::format_cash($price)." på hånda for å kunne betale ut ".game::format_cash($amount).".", "error");
						\Kofradia\DB::get()->commit();
					}
					
					else
					{
						// forsøk å trekk fra pengene fra hitlist
						\Kofradia\DB::get()->exec("SET @t := $amount");
						\Kofradia\DB::get()->exec("
							UPDATE hitlist h, (
								SELECT
									hl_id,
									GREATEST(0, LEAST(@t, hl_amount_valid)) AS to_remove,
									@t := GREATEST(0, @t - hl_amount_valid)
								FROM hitlist
								WHERE hl_up_id = $up_id AND @t > 0 AND hl_time < $expire
								ORDER BY hl_time DESC
							) r
							SET h.hl_amount_valid = h.hl_amount_valid - to_remove
							WHERE h.hl_id = r.hl_id");
						\Kofradia\DB::get()->exec("DELETE FROM hitlist WHERE hl_amount_valid = 0");
						
						// har vi noe til overs?
						$result = \Kofradia\DB::get()->query("SELECT @t");
						$a = $result->fetchColumn(0);
						if ($a > 0)
						{
							\Kofradia\DB::get()->rollback();
							ess::$b->page->add_message("Beløpet var for høyt.", "error");
						}
						
						else
						{
							\Kofradia\DB::get()->commit();
							
							putlog("LOG", "ETTERLYST: ".$this->up->data['up_name']." kjøpte ut dusør for UP_ID=$up_id på ".game::format_cash($amount).'. Betalte '.game::format_cash($price).'.');
							
							if ($up_id == $this->up->id)
							{
								ess::$b->page->add_message("Du kjøpte ut en dusør på ".game::format_cash($amount).' for deg selv. Du måtte betale '.game::format_cash($price).' for dette.');
							}
							else
							{
								ess::$b->page->add_message("Du kjøpte ut en dusør på ".game::format_cash($amount).' for <user id="'.$up_id.'" />. Du måtte betale '.game::format_cash($price).' for dette.');
							}
							
							redirect::handle("etterlyst");
						}
					}
				}
			}
		}
		
		ess::$b->page->add_js_domready('$("select_amount").focus();');
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Etterlyst - kjøp ut spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1"><boxes />
		<dl class="dd_right">
			<dt>Spiller</dt>
			<dd><user id="'.$up_id.'" /></dd>
			<dt>Total dusør</dt>
			<dd>'.game::format_cash($hl['sum_hl_amount_valid']).'</dd>
			<dt>Dusør som kan kjøpes ut</dt>
			<dd>'.game::format_cash($hl['sum_can_remove']).'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="up_id" value="'.$up_id.'" />
			<dl class="dd_right">
				<dt>Dusør å kjøpe ut</dt>
				<dd><input type="text" name="amount" id="select_amount" value="'.htmlspecialchars(postval("amount", game::format_cash($hl['sum_can_remove']))).'" class="styled w100" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Kjøp ut").'</p>
			<p class="c"><a href="etterlyst">Avbryt</a> - <a href="etterlyst?add">Velg en annen spiller</a></p>
			<p>'.($up_id == $this->up->id
				? 'Du må betale 3 ganger beløpet du velger å kjøpe ut for når du kjøper ut deg selv.'
				: 'Du må betale det dobbelte av beløpet du velger å kjøpe ut en annen spiller for.').'</p>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Vis detaljer
	 */
	protected function show_details()
	{
		if (empty($_GET['up_id']) || !access::has("mod")) redirect::handle("etterlyst");
		
		// last inn spiller
		$up_id = (int) $_GET['up_id'];
		$up = player::get($up_id);
		
		if (!$up)
		{
			ess::$b->page->add_message("Ingen spiller med id $up_id.", "error");
			redirect::handle("etterlyst");
		}
		
		$pagei = new pagei(pagei::PER_PAGE, 30, pagei::ACTIVE_GET, 'side');
		$result = $pagei->query("SELECT hl_id, hl_up_id, hl_by_up_id, hl_time, hl_amount, hl_amount_valid FROM hitlist WHERE hl_up_id = $up->id AND hl_amount_valid > 0 ORDER BY hl_time DESC");
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">
		Etterlyst - '.$up->data['up_name'].'
		<span class="left"></span><span class="right"></span>
	</h1>
	<p class="h_left"><a href="etterlyst">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p>Denne listen viser info om alle som har lagt til dusør på spilleren '.$up->profile_link().'.</p>';
		
		if ($pagei->total == 0)
		{
			echo '
		<p><b>Det er ingen som har satt dusør på denne spilleren.</b></p>';
		}
		
		else
		{
			echo '
		<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
			<thead>
				<tr>
					<th>Satt av</th>
					<th>Tid</th>
					<th>Opprinnelig dusør</th>
					<th>Gjenstående dusør</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			while ($row = $result->fetch())
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$row['hl_by_up_id'].'" /></td>
					<td>'.ess::$b->date->get($row['hl_time'])->format().'</td>
					<td class="r">'.game::format_cash($row['hl_amount']).'</td>
					<td class="r">'.game::format_cash($row['hl_amount_valid']).'</td>
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
	 * Trekk tilbake dusør
	 */
	protected function show_release()
	{
		if (!isset($_POST['hl_id']))
		{
			ess::$b->page->add_message("Du må velge en dusør du har satt.", "error");
			redirect::handle("etterlyst");
		}
		
		$hl_id = (int) $_POST['hl_id'];
		
		// hent informasjon
		$result = \Kofradia\DB::get()->query("SELECT hl_up_id, hl_time, hl_amount, hl_amount_valid FROM hitlist WHERE hl_id = $hl_id AND hl_by_up_id = ".$this->up->id." AND hl_amount_valid > 0");
		$hl = $result->fetch();
		
		if (!$hl)
		{
			ess::$b->page->add_message("Fant ikke oppføringen.", "error");
			redirect::handle("etterlyst")
		}
		
		\Kofradia\DB::get()->beginTransaction();
		
		// slett oppføringen
		$a = \Kofradia\DB::get()->exec("DELETE FROM hitlist WHERE hl_id = $hl_id AND hl_amount_valid = {$hl['hl_amount_valid']}");
		if ($a == 0)
		{
			ess::$b->page->add_message("Noen kom deg i forkjøpet og kjøpte ut hele eller deler av dusøren.", "error");
			\Kofradia\DB::get()->commit();
			redirect::handle("etterlyst")
		}
		
		// hvor mye penger skal vi få?
		$result = \Kofradia\DB::get()->query("SELECT ROUND({$hl['hl_amount_valid']}/2)");
		$amount = $result->fetchColumn(0);
		
		// gi penger
		\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + $amount WHERE up_id = ".$this->up->id);
		\Kofradia\DB::get()->commit();
		
		putlog("LOG", "ETTERLYST: ".$this->up->data['up_name']." trakk tilbake dusør for UP_ID={$hl['hl_up_id']} på ".game::format_cash($hl['hl_amount_valid']).'.');
		
		ess::$b->page->add_message('Du trakk tilbake dusøren på <user id="'.$hl['hl_up_id'].'" /> som ble satt '.ess::$b->date->get($hl['hl_time'])->format().' og som hadde igjen '.game::format_cash($hl['hl_amount_valid']).'. Du fikk tilbake '.game::format_cash($amount).'.');
		redirect::handle("etterlyst")
	}
}