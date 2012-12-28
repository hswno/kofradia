<?php

essentials::load_module("auksjon");
class page_auksjoner extends pages_player
{
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		// kontroller aktive auksjoner
		$this->check_active();
		
		$this->handle();
		ess::$b->page->load();
	}
	
	/**
	 * Kontroller aktive auksjoner
	 */
	protected function check_active()
	{
		// hent auksjoner som skal avsluttes
		$result = ess::$b->db->query("
			SELECT a_id
			FROM auksjoner WHERE a_end <= ".time()." AND a_completed = 0");
		
		while ($row = mysql_fetch_assoc($result))
		{
			// last inn auksjonen slik at den blir avsluttet
			auksjon::get($row['a_id']);
		}
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function handle()
	{
		ess::$b->page->add_title("Auksjoner");
		
		// behandle en bestemt auksjon?
		if (isset($_GET['a_id']))
		{
			new page_auksjoner_auksjon($_GET['a_id'], $this->up);
		}
		
		// vise en kategori?
		elseif (isset($_GET['t']))
		{
			$this->type();
		}
		
		else
		{
			$this->show_types();
		}
	}
	
	/**
	 * Vis auksjoner i en bestemt type
	 */
	protected function type()
	{
		$type = auksjon_type::get(getval("t"));
		if (!$type)
		{
			redirect::handle();
		}
		redirect::store("auksjoner?t=$type->id");
		
		// opprette auksjon for nye kuler?
		if ($type->id == 2 && isset($_GET['new']))
		{
			$this->new_bullets();
		}
		
		echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Auksjoner: '.htmlspecialchars($type->title).'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="auksjoner">Tilbake</a></p>';
		
		// firma?
		if ($type->id == 1)
		{
			echo '
		<p class="j">Når du vinner en firma-auksjon, vil du automatisk bli satt som eier av firmaet. Dersom du allerede eier et firma fra før, vil du måtte kvitte deg med et av firmaene senest like etter at auksjonen er avsluttet. Dersom du allerede er medlem av 3 firmaer, slik at dette blir ditt fjerde, må du forlate ett av firmaene senest like etter at auksjonen er avsluttet.</p>';
		}
		
		// kuler?
		elseif ($type->id == 2)
		{
			if (!$this->up->weapon)
			{
				echo '
		<p class="c">Du har ikke noe våpen, og kan ikke benytte deg av denne typen auksjoner.</p>';
			}
			
			elseif ($this->up->data['up_weapon_bullets'] < auksjon::BULLETS_MIN)
			{
				echo '
		<p class="c">Hvis du ønsker å selge kuler på auksjon må du ha minimum '.auksjon::BULLETS_MIN.' kuler.</p>';
			}
			
			else
			{
				echo '
		<p class="c"><a href="auksjoner?t=2&amp;new">Opprett auksjon for kulesalg &raquo;</a></p>';
			}
		}
		
		// hent alle auksjonene og høyeste bud
		$expire = time() - 86400; // auksjoner er synlige i 24 timer etter de er avsluttet
		$result = ess::$b->db->query("
			SELECT
				a_id, a_type, a_title, a_up_id, a_start, a_end, a_bid_start, a_num_bids, a_params, a_completed,
				ab_bid, ab_up_id, ab_time
			FROM auksjoner
				LEFT JOIN (
					SELECT ab_id, ab_a_id, ab_bid, ab_up_id, ab_time
					FROM auksjoner_bud
					WHERE ab_active != 0
					ORDER BY ab_time DESC
				) AS auksjoner_bud ON a_id = ab_a_id
			WHERE a_active != 0 AND a_type = $type->id AND a_end > $expire
			GROUP BY a_id
			ORDER BY a_end DESC, ab_time DESC, a_title");
		
		$tidligere = array();
		$senere = array();
		$tilgjengelige = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['params'] = new params($row['a_params']);
			if ($row['a_end'] < time() || $row['a_completed'] != 0)
			{
				$tidligere[] = $row;
			}
			elseif ($row['a_start'] > time())
			{
				$senere[] = $row;
			}
			else
			{
				$tilgjengelige[] = $row;
			}
		}
		
		// ingen auksjoner
		if (count($tidligere) == 0 && count($senere) == 0 && count($tilgjengelige) == 0)
		{
			echo '
		<p class="c">Det finnes for øyeblikket ingen auksjoner innenfor denne kategorien.</p>';
		}
		
		// vis tilgjengelige bud
		if (count($tilgjengelige) > 0)
		{
			echo '
		<p>Aktive auksjoner:</p>
		<table class="table game center tablem" width="100%">
			<thead>
				<tr>
					<th>Auksjon</th>'.($type->have_up ? '
					<th>Spiller</th>' : '').'
					<th>Auksjonslutt</th>
					<th colspan="2">Siste bud</th>
					<th>Bud</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($tilgjengelige as $row)
			{
				echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="auksjoner?a_id='.$row['a_id'].'">'.$type->format_title($row).'</a></td>'.($type->have_up ? '
					<td>'.($row['a_up_id'] ? '<user id="'.$row['a_up_id'].'" />' : '&nbsp;').'</td>' : '').'
					<td class="r">'.ess::$b->date->get($row['a_end'])->format(date::FORMAT_SEC).'</td>'.($row['ab_time'] ? '
					<td>
						<user id="'.$row['ab_up_id'].'" /><br />
						'.ess::$b->date->get($row['ab_time'])->format(date::FORMAT_SEC).'
					</td>
					<td class="r">'.game::format_cash($row['ab_bid']).'</td>' : '
					<td colspan="2" class="c">Ingen bud</td>').'
					<td class="c">'.game::format_number($row['a_num_bids']).'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
		}
		
		// vis senere auksjoner
		if (count($senere) > 0)
		{
			echo '
		<p>Kommende auksjoner:</p>
		<table class="table game center tablem" width="100%">
			<thead>
				<tr>
					<th>Auksjon</th>'.($type->have_up ? '
					<th>Spiller</th>' : '').'
					<th>Auksjonsstart</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($senere as $row)
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="auksjoner?a_id='.$row['a_id'].'">'.$type->format_title($row).'</a></td>'.($type->have_up ? '
					<td>'.($row['a_up_id'] ? '<user id="'.$row['a_up_id'].'" />' : '&nbsp;').'</td>' : '').'
					<td class="r">'.ess::$b->date->get($row['a_start'])->format(date::FORMAT_SEC).'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
		}
		
		
		// vis ferdige/utførte auksjoner
		if (count($tidligere) > 0)
		{
			echo '
		<p>Nylig avsluttede auksjoner:</p>
		<table class="table game center tablem" width="100%">
			<thead>
				<tr>
					<th>Auksjon</th>'.($type->have_up ? '
					<th>Spiller</th>' : '').'
					<th>Avsluttet</th>
					<th colspan="2">Vinner bud</th>
					<th>Bud</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($tidligere as $row)
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="auksjoner?a_id='.$row['a_id'].'">'.$type->format_title($row).'</a></td>'.($type->have_up ? '
					<td>'.($row['a_up_id'] ? '<user id="'.$row['a_up_id'].'" />' : '&nbsp;').'</td>' : '').'
					<td class="r">'.ess::$b->date->get($row['a_end'])->format(date::FORMAT_SEC).'</td>'.($row['ab_time'] ? '
					<td>
						<user id="'.$row['ab_up_id'].'" /><br />
						'.ess::$b->date->get($row['ab_time'])->format(date::FORMAT_SEC).'
					</td>
					<td class="r">'.game::format_cash($row['ab_bid']).'</td>' : '
					<td colspan="2" class="c">Ingen bud</td>').'
					<td class="c">'.game::format_number($row['a_num_bids']).'</td>
				</tr>';
				
				if ($i == 10) break; // begrens med 10 siste auksjoner
			}
			
			echo '
			</tbody>
		</table>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Selge kuler på auksjon
	 */
	protected function new_bullets()
	{
		// har ikke noe våpen eller nok kuler?
		if (!$this->up->weapon || $this->up->data['up_weapon_bullets'] < auksjon::BULLETS_MIN)
		{
			redirect::handle();
		}
		
		// opprette auksjon?
		if (isset($_POST['create']))
		{
			$price = game::intval(postval("price"));
			$bullets = (int) postval("bullets");
			$time = (int) postval("time");
			
			// for få kuler?
			if ($bullets < auksjon::BULLETS_MIN)
			{
				ess::$b->page->add_message("Du må legge ut minimum ".auksjon::BULLETS_MIN." kuler på auksjon.", "error");
			}
			
			// for mange kuler?
			elseif ($bullets > auksjon::BULLETS_MAX)
			{
				ess::$b->page->add_message("Du kan ikke legge ut flere enn ".auksjon::BULLETS_MAX." kuler på auksjon.", "error");
			}
			
			// flere enn vi har?
			elseif ($bullets > $this->up->data['up_weapon_bullets'])
			{
				ess::$b->page->add_message("Du har ikke så mange kuler.", "error");
			}
			
			// for lav startpris?
			elseif ($price <= 0)
			{
				ess::$b->page->add_message("Startprisen må være over 0.", "error");
			}
			
			// for kort varighet?
			elseif ($time < auksjon::BULLETS_TIME_MIN)
			{
				ess::$b->page->add_message("Auksjonen må ha en varighet på minimum ".game::timespan(auksjon::BULLETS_TIME_MIN*60, game::TIME_FULL).".", "error");
			}
			
			// for lang varighet?
			elseif ($time > auksjon::BULLETS_TIME_MAX)
			{
				ess::$b->page->add_message("Auksjonen kan ikke vare lengre enn ".game::timespan(auksjon::BULLETS_TIME_MAX*60, game::TIME_FULL).".", "error");
			}
			
			else
			{
				// trekk fra kulene
				ess::$b->db->query("
					UPDATE users_players
					SET up_weapon_bullets = up_weapon_bullets - $bullets, up_weapon_bullets_auksjon = up_weapon_bullets_auksjon + $bullets
					WHERE up_id = {$this->up->id} AND up_weapon_bullets >= $bullets");
				
				// hadde ikke nok kuler?
				if (ess::$b->db->affected_rows() == 0)
				{
					ess::$b->page->add_message("Du har ikke så mange kuler.", "error");
				}
				
				else
				{
					// opprett auksjon
					$params = new params();
					$params->update("bullets", $bullets);
					$params = ess::$b->db->quote($params->build());
					$timen = time();
					$expire = $timen + $time*60;
					ess::$b->db->query("INSERT INTO auksjoner SET a_type = ".auksjon::TYPE_KULER.", a_title = '$bullets kuler', a_up_id = {$this->up->id}, a_start = $timen, a_end = $expire, a_bid_start = $price, a_bid_jump = 50000, a_active = 1, a_params = $params");
					
					$a_id = ess::$b->db->insert_id();
					putlog("INFO", "%bKULEAUKSJON:%b %u{$this->up->data['up_name']}%u opprettet en auksjon for ".$bullets." kuler med budstart på ".game::format_cash($price)." ".ess::$s['spath']."/auksjoner?a_id=$a_id");
					
					$auksjon = auksjon::get($a_id);
					if ($auksjon)
					{
						$this->up->trigger("auksjon_start", array(
								"auksjon" => $auksjon));
					}
					
					// oppdater cache
					auksjon::update_cache();
					
					// live-feed
					#livefeed::add_row('<user id="'.$this->up->id.'" /> opprettet en auksjon for <a href="'.ess::$s['relative_path'].'/auksjoner?a_id='.$a_id.'">'.$bullets.' kuler</a> med startbud på '.game::format_cash($price).'.');
					
					ess::$b->page->add_message("Auksjonen ble opprettet.");
					redirect::handle("auksjoner?a_id=$a_id");
				}
			}
		}
		
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Selg kuler på auksjon<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="auksjoner?t=2">Tilbake</a></p>
		<p>Her har du muligheten til å selge kuler på auksjon. På den måten kan du være heldig å tjene på tiden du har brukt for å få tak i kulene.</p>
		<p>Når du oppretter en auksjon, har du ikke mulighet til å trekke den tilbake. Hvis ingen byr på auksjonen din vil du få kulene returnert.</p>
		<p>Du har for øyeblikket <b>'.$this->up->data['up_weapon_bullets'].'</b> kuler som du kan selge.</p>
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Antall kuler som skal selges</dt>
				<dd><input type="text" class="styled w40" name="bullets" value="'.max(auksjon::BULLETS_MIN, intval(postval("bullets"))).'" /></dd>
				<dt>Varighet for auksjon (minutter)</dt>
				<dd><input type="text" class="styled w40" name="time" value="'.intval(postval("time", auksjon::BULLETS_TIME_MAX)).'" /></dd>
				<dt>Startpris på auksjon</dt>
				<dd><input type="text" class="styled w80" name="price" value="'.game::format_cash(postval("price", 10000)).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Opprett auksjon", 'name="create"').'</p>
		</form>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis oversikt over de forskjellige kategoriene vi kan velge mellom
	 */
	protected function show_types()
	{
		// hent antall aktive auksjoner i de ulike typene
		$result = ess::$b->db->query("
			SELECT a_type, COUNT(a_id) num_a
			FROM auksjoner
			WHERE a_active != 0 AND a_completed = 0 AND a_start < ".time()." AND a_end >= ".time()."
			GROUP BY a_type");
		$num = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$num[$row['a_type']] = $row['num_a'];
		}
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Auksjoner<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';
		
		foreach (auksjon_type::$types as $type_id => $type)
		{
			$n = isset($num[$type_id]) ? $num[$type_id] : 0;
			
			echo '
		<p class="auksjonertype">
			<a href="?t='.$type_id.'">
				<img src="'.$type['img'].'" alt="" />
				'.htmlspecialchars($type['title']).'
				<span class="info">('.fwords("<b>%d</b> aktiv auksjon", "<b>%d</b> aktive auksjoner", $n).')</span>
			</a>
		</p>';
		}
		
		echo '
		<p class="c"><a href="node/9">Informasjon om auksjoner</a></p>
	</div>
</div>';
		
		// oppdater cache for auksjoner
		auksjon::update_cache();
	}
}


class page_auksjoner_auksjon extends pages_player
{
	/**
	 * Auksjonen
	 * @var auksjon
	 */
	protected $auksjon;
	
	/**
	 * Construct
	 */
	public function __construct($a_id, player $up)
	{
		parent::__construct($up);
		
		$this->auksjon = auksjon::get($a_id);
		if (!$this->auksjon)
		{
			ess::$b->page->add_message("Fant ikke auksjonen du lette etter.", "error");
			redirect::handle();
		}
		
		redirect::store("auksjoner?a_id={$this->auksjon->id}");
		
		$this->handle();
	}
	
	/**
	 * Behandle side
	 */
	protected function handle()
	{
		// legg til eller øke bud
		if (isset($_POST['raise_bid']) || isset($_POST['place_bid']))
		{
			$this->bid();
		}
		
		// slette bud
		if (isset($_POST['del_bid']) && $this->auksjon->status == auksjon::STATUS_ACTIVE)
		{
			$this->bid_delete();
		}
		
		// vis oversikt over denne auksjonen
		$this->show();
	}
	
	/**
	 * Øke eller legge til bud
	 */
	protected function bid()
	{
		// nostat?
		if (access::is_nostat() && $this->up->id != 1)
		{
			ess::$b->page->add_message("Du er nostatuser og har ikke tilgang til å by på auksjoner.", "error");
			redirect::handle();
		}
		
		$amount = game::intval(postval("amount"));
		
		// auksjonen avsluttet?
		if ($this->auksjon->status == auksjon::STATUS_FINISHED)
		{
			ess::$b->page->add_message("Denne auksjonen er avsluttet.", "error");
			redirect::handle();
		}
		
		// firma og for lite helse?
		if ($this->auksjon->data['a_type'] == auksjon::TYPE_FIRMA && $this->up->get_health_percent() < player::FF_HEALTH_LOW * 100)
		{
			ess::$b->page->add_message("Du har for lav helse til å kunne by på et firma.", "error");
			redirect::handle();
		}
		
		// over maks?
		/*if (bccomp($amount, auksjon::MAKS_BUD) == 1)
		{
			ess::$b->page->add_message("Du kan ikke by mer enn ".game::format_cash(auksjon::MAKS_BUD).".", "error");
			redirect::handle();
		}*/
		
		// sjekk minste bud vi kan sette
		$result = ess::$b->db->query("SELECT ab_bid + {$this->auksjon->data['a_bid_jump']} AS ab_bid FROM auksjoner_bud WHERE ab_a_id = {$this->auksjon->id} AND ab_active != 0 ORDER BY ab_bid DESC LIMIT 1");
		$bud = mysql_fetch_assoc($result);
		$min_bud = $bud ? $bud['ab_bid'] : $this->auksjon->data['a_bid_start'];
		
		// under min?
		if (bccomp($amount, $min_bud) == -1)
		{
			ess::$b->page->add_message("Du må by minimum ".game::format_cash($min_bud).".", "error");
			redirect::handle();
		}
		
		// hent tidligere bud
		$result = ess::$b->db->query("SELECT ab_id, ab_bid, ab_time FROM auksjoner_bud WHERE ab_a_id = {$this->auksjon->id} AND ab_up_id = {$this->up->id} AND ab_active != 0");
		$bud = mysql_fetch_assoc($result);
		
		$update = false;
		
		// økte bud?
		if ($bud && isset($_POST['raise_bid']))
		{
			// er dette det siste budet?
			$result = ess::$b->db->query("SELECT ab_id FROM auksjoner_bud WHERE ab_a_id = {$this->auksjon->id} AND ab_active != 0 AND ab_time >= ".(time()-auksjon::MAX_TIME_REMOVE)." ORDER BY ab_time DESC LIMIT 1");
			$row = mysql_fetch_assoc($result);
			
			if ($row && $row['ab_id'] == $bud['ab_id'])
			{
				$update = true;
			}
			
			// start transaksjon
			ess::$b->db->begin();
			
			// trekk fra pengene
			ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $amount + {$bud['ab_bid']} WHERE up_id = {$this->up->id} AND up_cash >= $amount - {$bud['ab_bid']}");
		}
		
		// nytt bud?
		elseif (!$bud && isset($_POST['place_bid']))
		{
			// behandle forskjellige auksjonstyper
			switch ($this->auksjon->data['a_type'])
			{
				// firma
				case auksjon::TYPE_FIRMA:
					// har vi bud på en annen auksjon?
					$result = ess::$b->db->query("
						SELECT ab_id
						FROM auksjoner, auksjoner_bud
						WHERE ab_a_id = a_id AND a_completed = 0 AND a_active != 0 AND a_type = ".auksjon::TYPE_FIRMA." AND ab_up_id = {$this->up->id} AND ab_active != 0");
					if (mysql_num_rows($result) > 0)
					{
						ess::$b->page->add_message("Du har allerede bydd på en annen auksjon. Du kan ikke by på flere auksjoner samtidig.", "error");
						redirect::handle();
					}
				break;
				
				// kuler
				case auksjon::TYPE_KULER:
					// har vi ikke noe våpen?
					if (!$this->up->weapon)
					{
						redirect::handle();
					}
					
					// sjekk at vi har plass til flere kuler
					$kuler = (int) $this->auksjon->params->get("bullets");
					if ($kuler)
					{
						if ($this->up->data['up_weapon_bullets'] + $this->up->data['up_weapon_bullets_auksjon'] + $kuler > $this->up->weapon->data['bullets'])
						{
							ess::$b->page->add_message("Du har ikke plass til flere kuler.", "error");
							redirect::handle();
						}
					}
				break;
			}
			
			// start transaksjon
			ess::$b->db->begin();
			
			// trekk fra pengene
			ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $amount WHERE up_id = {$this->up->id} AND up_cash >= $amount");
		}
		
		// ukjent
		else
		{
			redirect::handle();
		}
		
		// ikke nok penger
		if (ess::$b->db->affected_rows() == 0)
		{
			ess::$b->page->add_message("Du har ikke nok penger på hånda.", "error");
			ess::$b->db->rollback();
			redirect::handle();
		}
		
		// behandle forskjellige auksjonstyper
		if (!$bud)
		{
			switch ($this->auksjon->data['a_type'])
			{
				// kuler
				case auksjon::TYPE_KULER:
					// oppdater antall kuler vi kjøper via auksjoner
					$kuler = (int) $this->auksjon->params->get("bullets");
					if ($kuler)
					{
						ess::$b->db->query("UPDATE users_players SET up_weapon_bullets_auksjon = up_weapon_bullets_auksjon + $kuler WHERE up_id = {$this->up->id}");
					}
				break;
			}
		}
		
		// oppdatere oppføringen?
		if ($update)
		{
			ess::$b->db->query("UPDATE auksjoner_bud SET ab_bid = $amount, ab_time = ".time()." WHERE ab_id = {$bud['ab_id']} AND ab_time = {$bud['ab_time']}");
			
			// kunne ikke oppdatere?
			if (ess::$b->db->affected_rows() == 0)
			{
				ess::$b->page->add_message("Du har ikke noe bud.", "error");
				ess::$b->db->rollback();
				redirect::handle();
			}
		}
		
		// ny oppføring
		else
		{
			// tidligere bud som skal fjernes?
			if ($bud)
			{
				ess::$b->db->query("UPDATE auksjoner_bud SET ab_active = 0 WHERE ab_id = {$bud['ab_id']} AND ab_time = {$bud['ab_time']} AND ab_active != 0");
				
				// kunne ikke fjerne tidligere bud?
				if (ess::$b->db->affected_rows() == 0)
				{
					ess::$b->page->add_message("Kunne ikke øke budet.", "error");
					ess::$b->db->rollback();
					redirect::handle();
				}
			}
			
			// legg til oppføringen
			ess::$b->db->query("INSERT INTO auksjoner_bud SET ab_a_id = {$this->auksjon->id}, ab_up_id = {$this->up->id}, ab_bid = $amount, ab_time = ".time());
			
			// oppdater auksjonen
			ess::$b->db->query("UPDATE auksjoner SET a_num_bids = a_num_bids + 1 WHERE a_id = {$this->auksjon->id}");
		}
		
		$msg = '';
		
		// meldinger
		$place = $this->auksjon->data['a_type'] == auksjon::TYPE_FIRMA ? "INFO" : "LOG";
		if ($bud)
		{
			$msg = "Du har økt ditt bud.";
			
			if ($update)
			{
				putlog($place, "%bAUKSJONER:%b %u{$this->up->data['up_name']}%u økte sitt bud til %b".game::format_cash($amount)."%b på %b{$this->auksjon->data['a_title']}%b og leder fortsatt auksjonen ".ess::$s['spath']."/auksjoner?a_id={$this->auksjon->id}");
			}
			else
			{
				putlog($place, "%bAUKSJONER:%b %u{$this->up->data['up_name']}%u økte sitt bud til %b".game::format_cash($amount)."%b på %b{$this->auksjon->data['a_title']}%b og leder nå auksjonen ".ess::$s['spath']."/auksjoner?a_id={$this->auksjon->id}");
			}
		}
		else
		{
			$msg = "Du la inn bud for denne auksjonen.";
			putlog($place, "%bAUKSJONER:%b %u{$this->up->data['up_name']}%u bydde %b".game::format_cash($amount)."%b på %b{$this->auksjon->data['a_title']}%b ".ess::$s['spath']."/auksjoner?a_id={$this->auksjon->id}");
		}
		
		// sørg for at det er minimum 2 min igjen av auksjonen
		$end_min = time()+120;
		if ($this->auksjon->data['a_end'] < $end_min)
		{
			ess::$b->db->query("UPDATE auksjoner SET a_end = $end_min WHERE a_id = {$this->auksjon->id} AND a_end < $end_min");
			$msg .= " Auksjonen ble forlenget til ".ess::$b->date->get($end_min)->format(date::FORMAT_SEC).".";
			
			putlog("LOG", "%bAUKSJONER:%b %b{$this->auksjon->data['a_title']}%b ble utsatt til ".ess::$b->date->get($end_min)->format(date::FORMAT_SEC)." ".ess::$s['spath']."/auksjoner?a_id={$this->auksjon->id}");
		}
		
		ess::$b->page->add_message($msg);
		
		ess::$b->db->commit();
		redirect::handle();
	}
	
	/**
	 * Trekke tilbake bud
	 */
	protected function bid_delete()
	{
		// hent budet
		$result = ess::$b->db->query("SELECT ab_id, ab_bid, ab_time FROM auksjoner_bud WHERE ab_a_id = {$this->auksjon->id} AND ab_up_id = {$this->up->id} AND ab_active != 0");
		$bud = mysql_fetch_assoc($result);
		
		// har ikke noe bud?
		if (!$bud)
		{
			ess::$b->page->add_message("Du har ikke noe bud på denne auksjonen.", "error");
			redirect::handle();
		}
		
		// for gammelt?
		if ($bud['ab_time'] < time()-auksjon::MAX_TIME_REMOVE)
		{
			ess::$b->page->add_message("Budet ditt har stått i mer enn ".game::timespan(auksjon::MAX_TIME_REMOVE, game::TIME_FULL)." og er bindende. Du må vente til noen nyere bud har stått lengre enn ".game::timespan(auksjon::MAX_TIME_REMOVE, game::TIME_FULL).".", "error");
			redirect::handle();
		}
		
		// fjern budet vi har på auksjonen
		if (!auksjon::set_bud_inactive($bud, $this->auksjon, $this->up, true))
		{
			ess::$b->page->add_message("Budet ditt hadde endret seg.", "error");
			redirect::handle();
		}
		
		// behandle forskjellige auksjonstyper
		switch ($this->auksjon->data['a_type'])
		{
			// firma
			case auksjon::TYPE_FIRMA:
				// informer om handlingen
				putlog("INFO", "%bAUKSJONER:%b %u{$this->up->data['up_name']}%u fjernet sitt bud på %b".game::format_cash($bud['ab_bid'])."%b fra %b{$this->auksjon->data['a_title']}%b ".ess::$s['spath']."/auksjoner?a_id={$this->auksjon->id}");
			break;
		}
		
		// var dette det siste budet?
		$result = ess::$b->db->query("SELECT ab_id FROM auksjoner_bud WHERE ab_a_id = {$this->auksjon->id} AND ab_active != 0 AND ab_id > {$bud['ab_id']} LIMIT 1");
		$msg = '';
		if (mysql_num_rows($result) == 0)
		{
			// sørg for at det er minimum 2 min igjen av auksjonen
			$end_min = time()+120;
			if ($this->auksjon->data['a_end'] < $end_min)
			{
				ess::$b->db->query("UPDATE auksjoner SET a_end = $end_min WHERE a_id = {$this->auksjon->id} AND a_end < $end_min");
				$msg .= " Auksjonen ble forlenget til ".ess::$b->date->get($end_min)->format(date::FORMAT_SEC).".";
				
				putlog("LOG", "%bAUKSJONER:%b %b{$this->auksjon->data['a_title']}%b ble utsatt til ".ess::$b->date->get($end_min)->format(date::FORMAT_SEC)." ".ess::$s['spath']."/auksjoner?a_id={$this->auksjon->id}");
			}
		}
		
		ess::$b->page->add_message("Du trakk tilbake budet ditt på ".game::format_cash($bud['ab_bid'])." innen det gikk ".game::timespan(auksjon::MAX_TIME_REMOVE, game::TIME_FULL).".$msg");
		redirect::handle();
	}
	
	/**
	 * Vis auksjonen
	 */
	protected function show()
	{
		// hent budet som leder, evt. vant
		$result = ess::$b->db->query("
			SELECT ab_up_id, ab_bid, ab_time
			FROM auksjoner_bud
			WHERE ab_a_id = {$this->auksjon->id} AND ab_active != 0
			ORDER BY ab_time DESC
			LIMIT 1");
		$bud_lead = mysql_fetch_assoc($result);
		
		// hent alle budene
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 30);
		$result = $pagei->query("
			SELECT ab_up_id, ab_bid, ab_time, ab_active
			FROM auksjoner_bud
			WHERE ab_a_id = {$this->auksjon->id}
			ORDER BY ab_time DESC");
		$bud = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$bud[] = $row;
		}
		
		// sjekk om vi har bud
		$result = ess::$b->db->query("
			SELECT ab_bid, ab_time
			FROM auksjoner_bud
			WHERE ab_a_id = {$this->auksjon->id} AND ab_up_id = {$this->up->id} AND ab_active != 0");
		$bud_own = mysql_fetch_assoc($result);
		$bud_own_locked = time() > $bud_own['ab_time'] + auksjon::MAX_TIME_REMOVE;
		
		$type = auksjon_type::get($this->auksjon->data['a_type']);
		
		// beregn minstepris
		$minstepris = $bud_lead ? bcadd($bud_lead['ab_bid'], $this->auksjon->data['a_bid_jump']) : $this->auksjon->data['a_bid_start'];
		
		// sett opp tittel/beskrivelse
		$title = htmlspecialchars($this->auksjon->data['a_title']);
		
		// firma/familie?
		if ($this->auksjon->data['a_type'] == auksjon::TYPE_FIRMA)
		{
			$ff_id = $this->auksjon->params->get("ff_id");
			if ($ff_id)
			{
				$title = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$ff_id.'">'.$title.'</a>';
			}
		}
		
		echo '
<div class="col2_w" style="margin: 40px"> 
	<div class="col_w left" style="width: 45%">
		<div class="col" style="margin-right: 20px">
			<div class="bg1_c">
				<h1 class="bg1">Auksjon<span class="left"></span><span class="right"></span></h1>
				<div class="bg1">
					<p class="c"><a href="auksjoner?t='.$this->auksjon->data['a_type'].'">Tilbake til oversikt</a></p>
					<dl class="dd_right">
						<dt>Beskrivelse</dt>
						<dd>'.$title.'</dd>'.($type->have_up ? '
						<dt>Spiller</dt>
						<dd>'.($this->auksjon->data['a_up_id'] ? '<user id="'.$this->auksjon->data['a_up_id'].'" />' : 'Ingen spiller').'</dd>' : '');
		
		// ikke startet?
		if ($this->auksjon->status == auksjon::STATUS_WAIT)
		{
			echo '
						<dt>Auksjonsstart</dt>
						<dd>'.ess::$b->date->get($this->auksjon->data['a_start'])->format(date::FORMAT_SEC).'<br />'.game::counter($this->auksjon->data['a_start']-time(), true).'</dd>
						<dt>Auksjonslutt</dt>
						<dd>'.ess::$b->date->get($this->auksjon->data['a_end'])->format(date::FORMAT_SEC).'</dd>';
		}
		
		// aktiv eller ferdig
		else
		{
			echo '
						<dt>Auksjonslutt</dt>
						<dd>'.ess::$b->date->get($this->auksjon->data['a_end'])->format(date::FORMAT_SEC).($this->auksjon->status == auksjon::STATUS_ACTIVE ? '<br />'.game::counter($this->auksjon->data['a_end']-time(), true) : '').'</dd>';
		}
		
		// vis info
		echo '
						<dt>Budstart</dt>
						<dd>'.game::format_cash($this->auksjon->data['a_bid_start']).'</dd>
						<dt>Minste budøkning</dt>
						<dd>'.game::format_cash($this->auksjon->data['a_bid_jump']).'</dd>';
		
		// status
		if ($this->auksjon->status == auksjon::STATUS_WAIT)
		{
			// ikke startet
			echo '
						<dt>Status</dt>
						<dd>Ikke startet</dd>';
		}
		elseif ($this->auksjon->status == auksjon::STATUS_FINISHED)
		{
			// ferdig
			echo '
						<dt>Status</dt>
						<dd>Avsluttet</dd>
						<dt>Vunnet av</dt>';
			
			// vinnerbudet
			if ($bud_lead)
			{
				echo '
						<dd><user id="'.$bud_lead['ab_up_id'].'" /></dd>';
			}
			else
			{
				echo '
						<dd>Ingen vinner</dd>';
			}
		}
		else
		{
			// pågår
			echo '
						<dt>Status</dt>
						<dd>Pågår nå</dd>';
		}
		
		echo '
					</dl>';
		
		// mer info?
		if (!empty($this->auksjon->data['a_info']))
		{
			echo '
					<p>'.game::bb_to_html($this->auksjon->data['a_info']).'</p>';
		}
		
		echo '
				</div>
			</div>
		</div>
	</div>
	<div class="col_w right" style="width: 55%">
		<div class="col" style="margin-left: 20px">
			<div class="bg1_c">
				<h1 class="bg1">Bud<span class="left"></span><span class="right"></span></h1>
				<div class="bg1">';
		
		// auksjon pågår -- legg til nye bud
		if ($this->auksjon->status == auksjon::STATUS_ACTIVE)
		{
			$own = $this->auksjon->data['a_up_id'] == $this->up->id;
			
			if (!$own)
			{
				// første budet?
				if (!$bud_lead)
				{
					echo '
					<p>Dette er det første budet på denne auksjonen. Du må derfor by minimum '.game::format_cash($this->auksjon->data['a_bid_start']).'.</p>';
				}
				else
				{
					echo '
					<p>Du må by minimum '.game::format_cash($this->auksjon->data['a_bid_jump']).' høyere enn '.($bud_lead['ab_up_id'] == $this->up->id ? 'ditt forrige bud' : 'det forrige budet til <user id="'.$bud_lead['ab_up_id'].'" />').' på '.game::format_cash($bud_lead['ab_bid']).'.</p>';
				}
				
				echo '
					<p>Du kan trekke tilbake budet ditt innen det har gått '.game::timespan(auksjon::MAX_TIME_REMOVE, game::TIME_FULL).'. Etter '.game::timespan(auksjon::MAX_TIME_REMOVE, game::TIME_FULL | game::TIME_NOBOLD).' er budet ditt bindende og det kan ikke trekkes tilbake.</p>
					<p>Dersom noen byr over deg, vil budet ditt bli inaktivt og du får pengene igjen etter at budet har stått i '.game::timespan(auksjon::MAX_TIME_REMOVE, game::TIME_FULL | game::TIME_NOBOLD).'.</p>';
			}
			
			// har vi bydd?
			if ($bud_own)
			{
				// gått ut på tid?
				if ($bud_own_locked)
				{
					echo '
					<p>Du har bydd på denne auksjonen og ditt bud er bindende.</p>';
				}
				
				else
				{
					// kan trekke budet
					echo '
					<p>Du har bydd på denne auksjonen og kan fortsatt trekke tilbake ditt bud.</p>';
				}
				
				// vis budøkning
				echo '
					<form action="" method="post">
						<dl class="dd_right">
							<dt>Øk bud til</dt>
							<dd><input class="styled w100 r" type="text" name="amount" value="'.game::format_cash($minstepris).'" /></dd>
						</dl>
						<p class="c">'.show_sbutton("Øk bud", 'name="raise_bid"').(!$bud_own_locked ? ' '.show_sbutton("Slett bud", 'name="del_bid"') : '').'</p>
					</form>';
			}
			
			// har ikke bydd
			elseif (!$own)
			{
				// har ikke noe våpen?
				if ($this->auksjon->data['a_type'] == auksjon::TYPE_KULER && !$this->up->weapon)
				{
					echo '
					<p>Du har ikke noe våpen og kan ikke delta i denne auksjonen.</p>';
				}
				
				// firma og for lite helse?
				elseif ($this->auksjon->data['a_type'] == auksjon::TYPE_FIRMA && $this->up->get_health_percent() < player::FF_HEALTH_LOW * 100)
				{
					echo '
					<p>Du har for lav helse til å kunne by på et firma.</p>';
				}
				
				else
				{
					echo '
					<form action="" method="post">
						<dl class="dd_right">
							<dt>Legg inn bud</dt>
							<dd><input class="styled w100 r" type="text" name="amount" value="'.game::format_cash($minstepris).'" /></dd>
						</dl>
						<p class="c">'.show_sbutton("Legg inn bud", 'name="place_bid"').'</p>
					</form>';
				}
			}
		}
		
		// vis budene
		if (count($bud) == 0)
		{
			if ($this->auksjon->status != auksjon::STATUS_WAIT)
			{
				echo '
					<p class="c">Ingen bud er lagt inn i denne auksjonen.</p>';
			}
		}
		
		else
		{
			ess::$b->page->add_css('.bud_inactive { text-decoration: line-through; color: #888 }');
			
			echo '
					<dl class="dd_right">';
			
			// gå gjennom alle budene
			foreach ($bud as $row)
			{
				echo '
						<dt'.($row['ab_active'] == 0 ? ' class="bud_inactive"' : '').'>'.ess::$b->date->get($row['ab_time'])->format("H:i:s").': <user id="'.$row['ab_up_id'].'" /></dt>
						<dd'.($row['ab_active'] == 0 ? ' class="bud_inactive"' : '').'>'.game::format_cash($row['ab_bid']).'</dd>';
			}
			
			echo '
					</dl>';
			
			if ($pagei->pages > 1)
			{
				echo '
					<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		echo '
				</div>
			</div>
		</div>
	</div>
</div>';
	}
}