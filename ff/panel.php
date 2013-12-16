<?php

// sørg for sikker tilkobling pga. passord hvis FF skal legges ned
if (isset($_GET['a']) && $_GET['a'] == "drop") define("FORCE_HTTPS", true);

require "../base.php";

new page_ff_panel();
class page_ff_panel
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		$this->ff->needaccess(true);
		
		redirect::store("panel?ff_id={$this->ff->id}");
		
		$this->page_handle();
		$this->ff->load_page();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function page_handle()
	{
		// navnbytte?
		if (getval("a") == "navnbytte" && $this->ff->access(1))
		{
			if ($this->ff->mod) $this->page_navnbytte_mod_handle();
			else $this->page_navnbytte_handle();
		}
		
		// selgen?
		// må være medeier for å kunne kjøpe
		if (getval("a") == "sell" && $this->ff->access(2))
		{
			$this->page_selge_handle();
		}
		
		// skifte logo?
		if (getval("a") == "logo" && $this->ff->access(1))
		{
			$this->page_logo_handle();
		}
		
		// pri3 foreslår en spiller som medlem
		if (getval("a") == "suggestion" && $this->ff->uinfo->data['ffm_priority'] == 3)
		{
			$this->page_suggestion_handle();
		}
		
		// endre beskrivelse?
		if (getval("a") == "beskrivelse" && $this->ff->access(2))
		{
			$this->page_description_handle();
		}
		
		// øke medlemsbegrensning
		if (getval("a") == "members_limit" && $this->ff->access(1) && $this->ff->type['type'] == "familie")
		{
			$this->page_members_limit_handle();
		}
		
		// velge bygning
		if (getval("a") == "br" && $this->ff->access(2))
		{
			$this->page_br_handle();
		}
		
		// legge ned FF
		if (getval("a") == "drop" && $this->ff->access(1))
		{
			$this->page_drop_handle();
		}
		
		// legge til forum lenke
		if (isset($_POST['forum_link_add']))
		{
			$this->forum_link_add();
		}
		
		// fjerne forum lenke
		if (isset($_POST['forum_link_remove']))
		{
			$this->forum_link_remove();
		}
		
		// forlate FF?
		if (isset($_POST['leave']))
		{
			$this->page_leave_handle();
		}
		
		// donere
		if (isset($_POST['donate']))
		{
			$this->page_donate_handle();
		}
		
		// vis oversikt over medlemmene og informasjon om dem
		if (getval("a") == "mi" && $this->ff->type['type'] == "familie" && (!$this->ff->data['ff_is_crew'] || $this->ff->mod))
		{
			$this->page_members_handle();
		}
		
		// vise informasjon om å holde FF oppe
		if (getval("a") == "pay" && $this->ff->data['ff_is_crew'] == 0 && $this->ff->type['type'] == "familie")
		{
			$this->page_pay_handle();
		}
		
		// vis statistikk over driftskostnad-betalinger
		if (getval("a") == "paystats" && $this->ff->data['ff_is_crew'] == 0 && $this->ff->type['type'] == "familie")
		{
			$this->page_paystats();
		}
		
		// sette inn kuler?
		if (isset($_POST['bullets_in']))
		{
			$this->bullets_in();
		}
		
		// ta ut kuler
		if  (isset($_POST['bullets_out']))
		{
			$this->bullets_out();
		}
		
		// vis panelet
		$this->page_panel();
	}
	
	/**
	 * Panelet
	 */
	protected function page_panel()
	{
		ess::$b->page->add_title("Panel");
		
		ess::$b->page->add_css('
.ff_panel {
	width: 490px;
	margin: 0 auto;
	overflow: hidden;
}
.ff_panel .section {
	margin-bottom: 20px;
}');
		
		// hent info om innbetaling
		$pay_info = $this->ff->pay_info();
		
		// sjekk om vi er i forum lista
		$forum_added = $this->ff->uinfo->forum_link() !== NULL;
		
		// medlemsbegrensning
		$limits = $this->ff->get_limits();
		
		echo '
<div class="ff_panel">
	<div style="width: 235px; float: left">
		
		<!-- ffinformasjon -->
		<div class="section">
			<h2>Informasjon om '.$this->ff->type['refobj'].'</h2>
			<dl class="dd_right">
				<dt>Navn</dt>
				<dd>'.htmlspecialchars($this->ff->data['ff_name']).'</dd>
				<dt>Opprettet</dt>
				<dd>'.ess::$b->date->get($this->ff->data['ff_date_reg'])->format().'</dd>
				<dt>Pengebeholdning</dt>
				<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
				<dt>Medlemsbegrensning</dt>
				<dd>'.$limits[0].' medlemmer</dd>
			</dl>'.($this->ff->data['ff_is_crew'] ? '
			<p>'.ucfirst($this->ff->type['refobj']).' har status som nostat. Den teller ikke med i spillstatistikken til spilleren.</p>' : '').($this->ff->uinfo->data['ffm_priority'] == 3 ? '
			<p class="c"><a href="panel?ff_id='.$this->ff->id.'&amp;a=suggestion">Foreslå '.($this->ff->type['type'] == "familie" ? 'nytt medlem' : 'ny ansatt').' til '.$this->ff->type['refobj'].' &raquo;</a></p>' : '').'
			<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;stats">Vis statistikk for '.$this->ff->type['refobj'].'</a></p>
		</div>
		
		<!-- egen informasjon -->
		<div class="section">
			<h2>Min informasjon</h2>
			<dl class="dl_30 dd_right">
				<dt>Ble med</dt>
				<dd>'.ess::$b->date->get($this->ff->uinfo->data['ffm_date_join'])->format().'</dd>
				<dt>Posisjon</dt>
				<dd>'.ucfirst($this->ff->uinfo->get_priority_name()).'</dd>
				<dt>Donert</dt>
				<dd>'.game::format_cash($this->ff->uinfo->data['ffm_donate']).'</dd>
			</dl>
			<form action="" method="post">
				<p class="c">'.show_sbutton("Forlat {$this->ff->type['refobj']}", 'name="leave"').'</p>
			</form>
		</div>';
		
		// kulelager for familie
		if ($this->ff->type['type'] == "familie")
		{
			$cap = $this->ff->get_bullets_capacity();
			$bullets = $this->ff->params->get("bullets", 0);
			
			echo '
		
		<!-- kulelager for broderskap -->
		<div class="section">
			<h2>Kulelager for broderskapet</h2>
			<dl class="dd_right">
				<dt>Kapasitet</dt>
				<dd>'.game::format_num($cap).'</dd>
				<dt>Antall kuler</dt>
				<dd>'.game::format_num($bullets).'</dd>
			</dl>';
			
			if (login::$user->player->weapon)
			{
				$up_cap = login::$user->player->weapon->data['bullets'];
				$up_bullets = login::$user->player->data['up_weapon_bullets'];
				$up_bullets_a = login::$user->player->data['up_weapon_bullets_auksjon'];
				
				echo '
			<p style="margin-bottom: 0"><b>Din oversikt</b></p>
			<dl class="dd_right" style="margin-top: 0">
				<dt>Kapasitet</dt>
				<dd>'.game::format_num($up_cap).'</dd>
				<dt>Antall kuler</dt>
				<dd>'.game::format_num($up_bullets).($up_bullets_a ? ' ('.game::format_num($up_bullets_a).')' : '').'</dd>
			</dl>';
				
				// de som ikke er nostat skal ikke få ta ut kuler fra Kofradia Crew
				if ((!access::is_nostat() && ($this->ff->data['ff_id'] == 1))
				{
					echo '
					<p>Du kan ikke ta ut kuler fra '.htmlspecialchars($this->ff->data['ff_name']).'</p>';
					return;
				}

				// kan vi ikke ta ut kuler?
				$p = $this->ff->uinfo->data['ffm_priority'];
				if ($p > 3)
				{
					echo '
			<p>Du kan ikke ta ut kuler, men kan få <user id="'.$this->ff->uinfo->data['ffm_parent_up_id'].'" /> til å gi deg kuler fra broderskapet.</p>';
				}
				
				else
				{
					// spillere vi kan ta ut kuler til
					$s_up = postval("bullets_up");
					$other = array();
					
					// kan vi ta ut kuler for kun underordnede under seg selv? (har pri 3)
					if ($p == 3 && isset($this->ff->members['members_parent'][login::$user->player->id]))
					{
						foreach ($this->ff->members['members_parent'][login::$user->player->id] as $ffm) $other[] = $ffm;
					}
					
					// kan vi ta ut kuler for alle underordnede? (har pri 1 og 2)
					elseif ($p < 3 && isset($this->ff->members['members_priority'][4]))
					{
						foreach ($this->ff->members['members_priority'][4] as $ffm) $other[] = $ffm;
					}
					
					// har vi noen underordnede?
					$sub = '';
					if ($other)
					{
						$sub = '
					<select name="bullets_up">
						<option value="">Til meg</option>';
						
						foreach ($other as $ffm)
						{
							$sub .= '
						<option value="'.$ffm->data['ffm_up_id'].'"'.($s_up == $ffm->data['ffm_up_id'] ? ' selected="selected"' : '').'>'.htmlspecialchars($ffm->data['up_name']).'</option>';
						}
						
						$sub .= '
					</select>';
					}
					
					echo '
			<form action="" method="post">
				<p class="c">'.$sub.'
					<input type="text" name="bullets_out" value="'.htmlspecialchars(postval("bullets_out")).'" class="styled w30" />
					'.show_sbutton("Ta ut").'
				</p>
			</form>';
				}
				
				echo '
			<form action="" method="post">
				<p class="c">
					<input type="text" name="bullets_in" value="'.htmlspecialchars(postval("bullets_in")).'" class="styled w30" />
					'.show_sbutton("Sett inn kuler").'
				</p>
			</form>';
			}
			
			else
			{
				echo '
			<p>Du har ikke noe våpen og kan ikke sette inn/ta ut kuler.</p>';
			}
			
			echo '
		</div>';
		}
		
		echo '
		
		<!-- donasjon til FF -->
		<div class="section">
			<h2>Donér til '.$this->ff->type['refobj'].'</h2>
			<form action="" method="post">
				<dl class="dd_right">
					<dt>Beløp</dt>
					<dd><input type="text" name="donate" value="'.game::format_cash(game::intval(postval("donate"))).'" class="styled w75" /></dd>
					<dt>Melding/notat</dt>
					<dd><input type="text" name="note" value="'.htmlspecialchars(postval("note")).'" maxlength="50" class="styled w100" /></dd>
				</dl>
				<p class="c">'.show_sbutton("Donér").'</p>
			</form>
		</div>
		
	</div>
	<div style="margin-left: 20px; float: left; width: 235px">'.($pay_info ? '
		
		<!-- driftskostnad -->
		<div class="section">
			<h2>Driftskostnad</h2>
			<p>For at '.$this->ff->type['refobj'].' ikke skal dø ut, må det betales inn et beløp på <u>100 mill</u> i tillegg til kostnad per ekstra medlemsplass til spillet hver 10. dag.</p>
			<p>Når medlemmene i '.$this->ff->type['refobj'].' ranker, vil beløpet som må innbetales synke med 1 og 1 mill avhengig av hvor mye som rankes.</p>
			<p>'.ucfirst($this->ff->type['refobj']).' mister ikke ranken hvis et medlem forlater '.$this->ff->type['refobj'].'.</p>
			<p>Beløpet vil bli trukket fra banken automatisk ved innbetalingstidspunkt. Hvis det ikke er nok penger i banken, vil '.$this->ff->type['refobj'].' få frist på å innbetale beløpet manuelt innen 24 timer. Beløpet vil da øke med 50 %.</p>'.(!$pay_info['in_time'] ? '
			<div class="section">
				<h2>Betaling av driftskostnad</h2>
				<p class="error_box">'.ucfirst($this->ff->type['refobj']).' har overskredet tidspunktet for innbetaling. Betaling må skje manuelt av '.$this->ff->type['priority'][1].'/'.$this->ff->type['priority'][2].'.</p>
				<dl class="dd_right">
					<dt>Betalingsfrist</dt>
					<dd>'.ess::$b->date->get($pay_info['next'])->format().'<br />'.game::timespan($pay_info['next'], game::TIME_ABS).'</dd>
					<dt>Beløp</dt>
					<dd>'.game::format_cash($pay_info['price']).'</dd>
				</dl>
				<p class="c"><a href="panel?ff_id='.$this->ff->id.'&amp;a=pay" class="button">Fortsett/vis detaljer &raquo;</a></p>
				<p>Hvis beløpet ikke blir betalt innen betalingsfristen vil '.$this->ff->type['refobj'].' dø ut.</p>
			</div>' : '
			<dl class="dd_right">
				<dt>Neste innbetaling</dt>
				<dd>'.ess::$b->date->get($pay_info['next'])->format().'</dd>
				<dt>Foreløpig beløp</dt>
				<dd>'.game::format_cash($pay_info['price']).'</dd>
			</dl>
			<p><a href="panel?ff_id='.$this->ff->id.'&amp;a=pay">Vis oversikt over medlemmers bidrag &raquo;</a></p>
			<p><a href="panel?ff_id='.$this->ff->id.'&amp;a=paystats">Statistikk &raquo;</a></p>').'
		</div>' : '').'
		
		<!-- forum ting -->
		<div class="section">
			<h2>Forum</h2>
			<p class="h_right"><a href="../forum/forum?id='.$this->ff->get_fse_id().'">Vis forum</a></p>
			<p>For enkel tilgang til forumet for '.$this->ff->type['refobj'].' kan du legge til en lenke i menyen.</p>
			<form action="" method="post">
				<p><b>Status</b>: '.($forum_added ? 'Lenke <u>vises</u> - '.show_sbutton("Fjern lenke", 'name="forum_link_remove"') : 'Lenke er <u>skjult</u> - '.show_sbutton("Vis lenke", 'name="forum_link_add"')).'</p>
			</form>
		</div>';
		
		// eier info
		if ($this->ff->access(2))
		{
			$high = $this->ff->access(1);
			$groups = array();
			
			$eier = ucfirst($this->ff->type['priority'][1]);
			
			if ($high) $groups["Generelt"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=sell">Selg '.$this->ff->type['refobj'].' til '.$this->ff->type['priority'][2].'</a> ('.$eier.')';
			if ($high) $groups["Generelt"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=navnbytte">'.($this->ff->mod ? 'Endre navn' : 'Søk om navnbytte').'</a> ('.$eier.')';
			$groups["Generelt"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=beskrivelse">Rediger beskrivelse</a>';
			if ($high) $groups["Generelt"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=logo">Bytt logo</a> ('.$eier.')';
			if ($high) $groups["Generelt"][] = '<a href="banken?ff_id='.$this->ff->id.'">Banken</a> ('.$eier.')';
			if ($high) $groups["Generelt"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=drop">Legg ned '.$this->ff->type['refobj'].'</a> ('.$eier.')';
			if ($this->ff->mod) $groups["Generelt"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=br">Velg ny bygning</a> (Moderator)';
			
			// firmaer
			switch ($this->ff->type['type'])
			{
				case "avis":
					$groups["Avisfirma"][] = '<a href="avis?ff_id='.$this->ff->id.'&amp;a">Mine avisartikler</a>';
					$groups["Avisfirma"][] = '<a href="avis?ff_id='.$this->ff->id.'&amp;u">Administrer avisutgivelser</a>';
				break;
				
				case "bank":
					$groups["Bankfirma"][] = '<a href="bank?ff_id='.$this->ff->id.'">Administrer banken</a>';
				break;
			}
			
			$groups["Medlemmer"][] = '<a href="medlemmer?ff_id='.$this->ff->id.'">Medlemskontroll</a>';
			$groups["Medlemmer"][] = '<a href="medlemmer?ff_id='.$this->ff->id.'&amp;invite">Inviter spiller</a>';
			if ($high && $this->ff->type['type'] == "familie") $groups["Medlemmer"][] = '<a href="panel?ff_id='.$this->ff->id.'&amp;a=members_limit">Medlemsbegrensning</a> ('.$eier.')';
			
			$groups["Annet"][] = '<a href="logg?ff_id='.$this->ff->id.'">Vis logg</a>';
			$groups["Annet"][] = '<a href="../forum/forum?id='.$this->ff->get_fse_id().'">Vis forum</a>';
			
			echo '
		<div class="section">
			<h2>Innstillinger/handlinger</h2>';
			
			foreach ($groups as $group => $items)
			{
				echo '
			<h3>'.$group.'</h3>
			<ul>
				<li>'.implode('</li>
				<li>', $items).'</li>
			</ul>';
			}
			
			echo '
		</div>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Behandle navnbytte for moderator
	 */
	protected function page_navnbytte_mod_handle()
	{
		ess::$b->page->add_title("Navnbytte for moderator");
		
		// hent mulig søknad
		$result = ess::$b->db->query("SELECT ds_id, ds_up_id, ds_time, ds_reason, ds_params FROM div_soknader WHERE ds_type = ".soknader::TYPE_FF_NAME." AND ds_rel_id = {$this->ff->id} AND ds_reply_decision = 0");
		$soknad = mysql_fetch_assoc($result);
		
		// har vi en aktiv søknad?
		if ($soknad)
		{
			ess::$b->page->add_message(ucfirst($this->ff->type['refobj']).' har allerede en søknad om navnbytte liggende som må behandles først.', "error");
			redirect::handle();
		}
		
		if (isset($_POST['name']))
		{
			$name = trim(postval("name"));
			
			// fjern evt. flere mellomromstegn etter hverandre
			$name = preg_replace("/  +/u", " ", $name);
			$_POST['name'] = $name;
			
			// samme navn som før?
			if ($name == $this->ff->data['ff_name'])
			{
				ess::$b->page->add_message("Du må velge et nytt navn å søke om.");
			}
			
			// kontroller at navnet ikke har noen ugyldige tegn
			elseif (preg_match("/[^\\p{L}\\d ]/u", $name))
			{
				ess::$b->page->add_message("Navnet kan kun inneholde bokstaver, tall og mellomrom.", "error");
			}
			
			// navnet kan ikke være kortere enn 2 tegn
			elseif (mb_strlen($name) < 2)
			{
				ess::$b->page->add_message("Minimum lengde for navnet er 2 tegn.", "error");
			}
			
			// navnet kan ikke være lengre enn 20 tegn
			elseif (mb_strlen($name) > 20)
			{
				ess::$b->page->add_message("Maksimal lengde for navnet er 20 tegn.", "error");
			}
			
			else
			{
				$name_old = $this->ff->data['ff_name'];
				
				// endre navnet
				$this->ff->change_name($name, null, true);
				
				ess::$b->page->add_message("Navnet på {$this->ff->type['refobj']} ble endret fra ".htmlspecialchars($name_old)." til ".htmlspecialchars($name).".");
				$this->ff->redirect();
			}
		}
		
		echo '
<div class="section w200">
	<h2>Bytte navn for '.$this->ff->type['refobj'].'</h2>
	<boxes />
	<form action="" method="post">
		<p>Som moderator kan du fritt endre navnet til '.$this->ff->type['refobj'].'.</p>
		<dl class="dd_right">
			<dt>Ønsket navn</dt>
			<dd><input type="text" name="name" value="'.htmlspecialchars(postval("name", $this->ff->data['ff_name'])).'" class="styled w100" /></dd>
		</dl>
		<p class="c">'.show_sbutton("Endre navnet").'</p>
		<p><a href="panel?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle navnbytte
	 */
	protected function page_navnbytte_handle()
	{
		ess::$b->page->add_title("Navnbytte");
		
		echo '
<!-- søk om navnbytte -->
<div class="section w200">
	<h2>Søk om navnbytte</h2><boxes />';
		
		// hent mulig søknad
		$result = ess::$b->db->query("SELECT ds_id, ds_up_id, ds_time, ds_reason, ds_params FROM div_soknader WHERE ds_type = ".soknader::TYPE_FF_NAME." AND ds_rel_id = {$this->ff->id} AND ds_reply_decision = 0");
		$soknad = mysql_fetch_assoc($result);
		
		// har vi en aktiv søknad?
		if ($soknad)
		{
			$params = unserialize($soknad['ds_params']);
			
			// trekke tilbake søknaden?
			if (isset($_POST['withdraw']))
			{
				if (soknader::delete($soknad['ds_id']))
				{
					$extra = '';
					
					// gi tilbake penger?
					if (isset($params['cost']) && $params['cost'] > 0)
					{
						$this->ff->bank(ff::BANK_TILBAKEBETALING, $params['cost'], 'Navnsøknad tilbaketrukket: '.$params['name']);
						$extra = ' Beløpet på '.game::format_cash($params['cost']).' ble satt tilbake på bankkontoen.';
					}
					
					ess::$b->page->add_message("Søknaden ble trukket tilbake.$extra");
				}
				
				redirect::handle("panel?ff_id={$this->ff->id}&a=navnbytte");
			}
			
			echo '
	<p>Søknad ble levert av <user id="'.$soknad['ds_up_id'].'" /> '.ess::$b->date->get($soknad['ds_time'])->format().'.</p>
	<dl class="dd_right">
		<dt>Navn som søkes</dt>
		<dd>'.htmlspecialchars($params['name']).'</dd>
		<dt>Kostnad</dt>
		<dd>'.(isset($params['cost']) ? game::format_cash($params['cost']) : '0 kr').'</dd>
	</dl>
	<p><u>Begrunnelse:</u><br />'.game::format_data($soknad['ds_reason'], "bb-opt", "Ingen begrunnelse gitt.").'</p>
	<p>Søknaden er under behandling.</p>
	<form action="" method="post">
		<p>'.show_sbutton("Trekk tilbake søknad", 'name="withdraw"').'</p>
	</form>';
		}
		
		else
		{
			// kan vi levere gratis søknad?
			$soknad_free = $this->ff->mod || $this->ff->competition || !$this->ff->params->get("name_changed") || $this->ff->params->get("name_changed") < $this->ff->params->get("sold") || $this->ff->params->get("name_changed") < $this->ff->data['ff_time_reset'];
			
			// levere søknad?
			if (isset($_POST['name']) && isset($_POST['reason']))
			{
				$name = trim(postval("name"));
				$reason = trim(postval("reason"));
				
				// fjern evt. flere mellomromstegn etter hverandre
				$name = preg_replace("/  +/u", " ", $name);
				$_POST['name'] = $name;
				
				// samme navn som før?
				if ($name == $this->ff->data['ff_name'])
				{
					ess::$b->page->add_message("Du må velge et nytt navn å søke om.");
				}
				
				// kontroller at navnet ikke har noen ugyldige tegn
				elseif (preg_match("/[^\\p{L}\\d ]/u", $name))
				{
					ess::$b->page->add_message("Navnet kan kun inneholde bokstaver, tall og mellomrom.", "error");
				}
				
				// navnet kan ikke være kortere enn 2 tegn
				elseif (mb_strlen($name) < 2)
				{
					ess::$b->page->add_message("Minimum lengde for navnet er 2 tegn.", "error");
				}
				
				// navnet kan ikke være lengre enn 20 tegn
				elseif (mb_strlen($name) > 20)
				{
					ess::$b->page->add_message("Maksimal lengde for navnet er 20 tegn.", "error");
				}
				
				// mangler begrunelse?
				elseif (empty($reason))
				{
					ess::$b->page->add_message("Du må fylle inn en begrunnelse.", "error");
				}
				
				else
				{
					$success = $soknad_free;
					
					// forsøk å trekk fra pengene
					if (!$soknad_free)
					{
						$success = $this->ff->bank(ff::BANK_BETALING, ff::NAME_CHANGE_COST, 'Navnsøknad: '.$name);
					}
					
					// ikke nok penger i banken
					if (!$success)
					{
						ess::$b->page->add_message("Det er ikke nok penger i banken.", "error");
					}
					
					else
					{
						// legg til søknaden
						soknader::add(soknader::TYPE_FF_NAME, array(
							"name" => $name,
							"cost" => $soknad_free ? 0 : ff::NAME_CHANGE_COST
						), $reason, $this->ff->id);
						
						ess::$b->page->add_message("Du har nå levert søknad om nytt navn til {$this->ff->type['refobj']}.".($soknad_free ? '' : ' '.game::format_cash(ff::NAME_CHANGE_COST).' ble trukket fra bankkontoen.'));
						redirect::handle("panel?ff_id={$this->ff->id}&a=navnbytte");
					}
				}
			}
			
			echo '
	<form action="" method="post">'.($soknad_free ? ($this->ff->competition
			? '
		<p>Du kan under hele konkurranseperioden sende inn gratis søknad om navnbytte for '.$this->ff->type['refobj'].'. Så snart konkurranseperioden er over, vil det koste '.game::format_cash(ff::NAME_CHANGE_COST).' å søke om navnbytte dersom navnet ble byttet under konkurranseperioden.</p>'
			: ($this->ff->mod
				? '
		<p>Som moderator kan du sende inn gratis søknader på vegne av '.$this->ff->type['refobj'].'.</p>'
				: '
		<p>Du kan sende inn gratis søknad om navnbytte for '.$this->ff->type['refobj'].' én gang (søknaden må innvilges for å telle). Neste gang vil det koste '.game::format_cash(ff::NAME_CHANGE_COST).'.</p>')) : '').'
		<dl class="dd_right">
			<dt>Ønsket navn</dt>
			<dd><input type="text" name="name" value="'.htmlspecialchars(postval("name", $this->ff->data['ff_name'])).'" class="styled w100" /></dd>
			<dt>Kostnad (ved innvilgelse)</dt>
			<dd>'.($soknad_free ? '0 kr' : game::format_cash(ff::NAME_CHANGE_COST)).'</dd>
		</dl>
		<p>Begrunnelse</p>
		<p class="c"><textarea name="reason" rows="5" cols="30">'.htmlspecialchars(postval("reason")).'</textarea></p>
		<p class="c">'.show_sbutton("Send inn søknad").'</p>
	</form>';
		}
		
		echo '
	<p><a href="panel?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle salg
	 */
	protected function page_selge_handle()
	{
		// crewtilgang?
		if ($this->ff->uinfo->crew || $this->ff->uinfo->data['ffm_priority'] > 2)
		{
			ess::$b->page->add_message("Du kan ikke benytte deg av denne handlingen som moderator. Må være {$this->ff->type['priority'][1]} eller {$this->ff->type['priority'][2]}.", "error");
			redirect::handle();
		}
		
		// finn status på salg av FF
		$status = $this->ff->sell_status();
		
		// tittel
		ess::$b->page->add_title("Salg av {$this->ff->type['refobj']}");
		
		// i salgsmodus?
		if ($status)
		{
			// eier?
			if ($this->ff->access(1) && $status['up_id'] != $this->ff->uinfo->id)
			{
				// trekke tilbake salget?
				if (isset($_POST['abort']) && validate_sid(false))
				{
					// trekk tilbake salget
					$this->ff->sell_abort();
					
					ess::$b->page->add_message("Salget av {$this->ff->type['refobj']} ble avbrutt.");
					redirect::handle("panel?ff_id={$this->ff->id}&a=sell");
				}
				
				$seller = $status['init_up_id'] == $this->ff->uinfo->id;
				
				echo '
<div class="bg1_c xsmall" style="width: 320px">
	<h1 class="bg1">Salg av '.$this->ff->type['refobj'].'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="panel?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<boxes />'.($seller ? '
		<p>Når '.$this->ff->type['priority'][2].' godtar kjøpet, vil hele beløpet bli satt inn i banken din.</p>' : '
		<p>Når '.$this->ff->type['priority'][2].' godtar kjøpet, vil hele beløpet bli satt inn i banken til <user id="'.$status['init_up_id'].'" />.</p>').'
		<p>Salgsgebyret vil bli trukket fra banken, og det må være til stede i banken for at kjøperen skal kunne fullføre.</p>
		<dl class="dd_right">'.($seller ? '' : '
			<dt>Selger</dt>
			<dd><user id="'.$status['init_up_id'].'" /></dd>').'
			<dt>Kjøper ('.$this->ff->type['priority'][2].')</dt>
			<dd><user id="'.$status['up_id'].'" /></dd>
			<dt>Tid salget ble åpnet</dt>
			<dd>'.ess::$b->date->get($status['time'])->format(date::FORMAT_SEC).'</dd>
		</dl>
		<dl class="dd_right">
			<dt>Penger i banken</dt>
			<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
			<dt>Salgsgebyr (trekkes fra banken)</dt>
			<dd>'.game::format_cash($status['fee']).'</dd>
			<dt>Salgsbeløp (overføres fra kjøper)</dt>
			<dd>'.game::format_cash($status['amount']).'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p class="c">'.show_sbutton("Avbryt salg", 'name="abort"').' <a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
		</form>
	</div>
</div>';
			}
			
			elseif ($status['up_id'] == $this->ff->uinfo->id)
			{
				// kjøpe FF?
				if (isset($_POST['approve']) && validate_sid(false))
				{
					// forsøk å kjøp FF
					$result = $this->ff->sell_approve();
					if ($result === true)
					{
						// vellykket
						ess::$b->page->add_message(($status['amount'] == 0 ? 'Du overtok '.$this->ff->type['refobj'] : 'Du kjøpte '.$this->ff->type['refobj'].' for <b>'.game::format_cash($status['amount']).'</b>').', og er nå satt som '.$this->ff->type['priority'][1].'. <user id="'.$status['init_up_id'].'" /> ble satt som '.$this->ff->type['priority'][2].'.');
						redirect::handle("?ff_id={$this->ff->id}");
					}
					
					// har ikke nok penger
					elseif ($result == "player_cash")
					{
						ess::$b->page->add_message("Du har ikke nok penger på hånda for å gjennomføre kjøpet.");
					}
					
					// banken har ikke nok penger
					elseif ($result == "ff_cash")
					{
						ess::$b->page->add_message('Det er ikke nok penger i banken til å dekke salgsgebyret. Donér til '.$this->ff->type['refobj'].' eller be <user id="'.$status['init_up_id'].'" /> sette inn penger. Salgsgebyret er på '.game::format_cash($status['fee']).'.', "error");
					}
					
					else
					{
						ess::$b->page->add_message("Ukjent feil. Prøv på nytt.", "error");
					}
				}
				
				// avslå kjøp?
				if (isset($_POST['reject']) && validate_sid(false))
				{
					// forsøk å avbryt
					$result = $this->ff->sell_reject();
					
					if ($result)
					{
						ess::$b->page->add_message("Du avslo kjøpet av {$this->ff->type['refobj']}.");
						redirect::handle("?ff_id={$this->ff->id}");
					}
					
					else
					{
						ess::$b->page->add_message("Ukjent feil. Prøv på nytt.", "error");
					}
				}
				
				echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Salg av '.$this->ff->type['refobj'].'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="panel?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<boxes />
		<p><user id="'.$status['init_up_id'].'" /> har startet salg av '.$this->ff->type['refobj'].' til deg. Du må enten godta eller avslå salget.</p>
		<p>Salgsgebyret vil bli trukket fra banken. Salgsbeløpet vil bli overført direkte fra deg til <user id="'.$status['init_up_id'].'" /> sin bank.</p>
		<p>Du vil bli satt til '.$this->ff->type['priority'][1].', mens <user id="'.$status['init_up_id'].'" /> vil bli satt til '.$this->ff->type['priority'][2].'.</p>
		<dl class="dd_right">
			<dt>Tid salget ble åpnet</dt>
			<dd>'.ess::$b->date->get($status['time'])->format(date::FORMAT_SEC).'</dd>
		</dl>
		<dl class="dd_right">
			<dt>Penger i banken</dt>
			<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
			<dt>Salgsgebyr</dt>
			<dd>'.game::format_cash($status['fee']).'</dd>
			<dt>Salgsbeløp</dt>
			<dd>'.game::format_cash($status['amount']).'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p class="c">
				'.show_sbutton("Gjennomfør kjøp", 'name="approve"').'
				<span class="red">'.show_sbutton("Avslå kjøp", 'name="reject"').'</span>
			</p>
		</form>
	</div>
</div>';
			}
			
			else
			{
				ess::$b->page->add_message("Du har ikke tilgang til å se detaljer for salget av {$this->ff->type['refobj']}.", "error");
				redirect::handle();
			}
		}
		
		// ikke i salgsmodus
		else
		{
			// medeier har ikke tilgang her
			if (!$this->ff->access(1))
			{
				ess::$b->page->add_message(ucfirst($this->ff->type['refobj'])." er ikke til salgs for øyeblikket.", "error");
				redirect::handle();
			}
			
			// legge ut FF til salg?
			if (isset($_POST['sell_init']) && validate_sid(false))
			{
				$up_id = intval(postval("up_id"));
				$amount = game::intval(postval("amount"));
				
				// for høyt beløp?
				if (mb_strlen($amount) > 12)
				{
					ess::$b->page->add_message("Salgsbeløpet er for høyt.", "error");
				}
				
				// negativt beløp?
				elseif ($amount < 0)
				{
					ess::$b->page->add_message("Salgsbeløpet kan ikke være negativt.", "error");
				}
				
				else
				{
					if (($result = $this->ff->sell_init($up_id, $amount)) === true)
					{
						ess::$b->page->add_message('Du har nå startet salg av '.$this->ff->type['refobj'].' til <user id="'.$up_id.'" /> for '.game::format_cash($amount).'. Salgsgebyret blir trukket fra banken når kjøperen godtar kjøpet.');
						redirect::handle("panel?ff_id={$this->ff->id}&a=sell");
					}
					
					// har ikke høy nok rank
					elseif ($result == "player_rank")
					{
						ess::$b->page->add_message('<user id="'.$up_id.'" /> har ikke høy nok rank til å kunne bli '.$this->ff->type['priority'][1].'. Må være minst '.game::$ranks['items_number'][$this->ff->get_priority_rank(1)]['name'].'.', "error");
					}
					
					else
					{
						ess::$b->page->add_message("Noe gikk galt. Prøv igjen.", "error");
					}
				}
			}
			
			// vis skjema
			ess::$b->page->add_title("Velg salgsbeløp");
			
			echo '
<div class="bg1_c small">
	<h1 class="bg1">Salg av '.$this->ff->type['refobj'].'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="panel?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<boxes />
			<p>Du er i ferd med å starte salg av '.$this->ff->type['refobj'].'. '.ucfirst($this->ff->type['refobj']).' kan kun selges til '.$this->ff->type['priority'][2].'. Når '.$this->ff->type['priority'][2].' har godtatt kjøpet og betalt pengene, vil hele beløpet bli satt inn i banken din.</p>
			<p>Salgsgebyret vil bli trukket fra bankkontoen til '.$this->ff->type['refobj'].' i det '.$this->ff->type['priority'][2].' godtar kjøpet. '.ucfirst($this->ff->type['priority'][2]).' vil ikke kunne godta kjøpet hvis pengene ikke er i banken.</p>';
			
			// er det ingen medeier
			if (!isset($this->ff->members['members_priority'][2]) || count($this->ff->members['members_priority'][2]) == 0)
			{
				echo '
			<p><b>Det finnes ingen '.$this->ff->type['priority'][2].' i '.$this->ff->type['refobj'].'.</b></p>';
			}
			
			else
			{
				$in_dl = true;
				if (count($this->ff->members['members_priority'][2]) > 1)
				{
					$in_dl = false;
					echo '
			<p>Velg '.$this->ff->type['priority'][2].':</p>
			<table class="table">
				<thead>
					<tr>
						<th>Spiller</th>
						<th>Sist pålogget</th>
						<th>Ble medlem</th>
					</tr>
				</thead>
				<tbody>';
					
					$i = 0;
					foreach ($this->ff->members['members_priority'][2] as $member)
					{
						echo '
					<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
						<td><input type="radio" name="up_id" id="sell_up_id" class="box_handle"'.(postval("up_id") == $member->id ? ' selected="selected"' : '').' />'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'</td>
						<td>'.ess::$b->date->get($member->data['up_last_online'])->format().'</td>
						<td>'.ess::$b->date->get($member->data['ffm_date_join'])->format().'</td>
					</tr>';
					}
					
					echo '
				</tbody>
			</table>';
				}
				
				echo '
			<dl class="dd_right">';
				
				if ($in_dl)
				{
					foreach ($this->ff->members['members_priority'][2] as $member)
					{
						echo '
				<input type="hidden" name="up_id" value="'.$member->id.'" />
				<dt>'.ucfirst($this->ff->type['priority'][2]).' (kjøper)</dt>
				<dd>'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'</dd>';
					}
				}
				
				echo '
				<dt>Penger i banken</dt>
				<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
				<dt>Salgsgebyr (trekkes fra banken ved kjøp)</dt>
				<dd>'.game::format_cash(ff::SELL_COST).'</dd>
				<dt>Salgsbeløp (overføres fra kjøper til deg)</dt>
				<dd><input type="text" name="amount" class="styled w100" value="'.game::format_cash(postval("amount", "1 000 000")).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Start salg", 'name="sell_init"').' <a href="panel?ff_id='.$this->ff->id.'">Avbryt</a></p>';
			}
			
			echo '
		</form>
	</div>
</div>';
		}
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle skifting av logo
	 */
	protected function page_logo_handle()
	{
		ess::$b->page->add_title("Ny logo");
		redirect::store("panel?ff_id={$this->ff->id}&a=logo");
		
		// fjerne logo?
		if (isset($_POST['remove']) && $this->ff->data['has_ff_logo'])
		{
			// hent gammel logo
			$result = ess::$b->db->query("SELECT ff_logo, ff_logo_path FROM ff WHERE ff_id = {$this->ff->id}");
			$old = mysql_result($result, 0);
			if (empty($old))
			{
				$this->ff->add_log("logo", login::$user->player->id.":removed");
			}
			else
			{
				$this->ff->add_log("logo", login::$user->player->id.":removed", base64_encode($old));
				
				// forsøk å slett fra disk
				$old_path = mysql_result($result, 0, 1);
				if (MAIN_SERVER && mb_substr($old_path, 0, 2) == "l:")
				{
					$path = PROFILE_IMAGES_FOLDER . "/" . mb_substr($old_path, 2);
					if (file_exists($path)) unlink($path);
				}
			}
			
			// fjern bildet fra databasen
			ess::$b->db->query("UPDATE ff SET ff_logo = NULL, ff_logo_path = NULL WHERE ff_id = {$this->ff->id}");
			
			ess::$b->page->add_message("Logoen ble fjernet.");
			redirect::handle();
		}
		
		// laste opp bilde?
		if (isset($_FILES['logo']) && validate_sid(false))
		{
			// kontroller fil
			if (!is_uploaded_file($_FILES['logo']['tmp_name']))
			{
				ess::$b->page->add_message("Noe gikk galt. Prøv på nytt.", "error");
				redirect::handle();
			}
			
			// hent data
			$data = file_get_contents($_FILES['logo']['tmp_name']);
			if ($data === false)
			{
				ess::$b->page->add_message("Noe gikk galt. Prøv på nytt.", "error");
				redirect::handle();
			}
			
			// åpne med GD
			$img = imagecreatefromstring($data);
			if ($img === false)
			{
				ess::$b->page->add_message("Bildet kunne ikke bli lest. Prøv et annet bilde av type JPEG, PNG, GIF eller WBMP.", "error");
				redirect::handle();
			}
			
			// kontroller bredde/høyde (skal være 110x110) og resize hvis nødvendig
			if (imagesx($img) != 110 || imagesy($img) != 110)
			{
				$new = imagecreatetruecolor(110, 110);
				imagecopyresampled($new, $img, 0, 0, 0, 0, 110, 110, imagesx($img), imagesy($img));
				imagedestroy($img);
				
				$img = $new;
			}
			
			// hent ut bildedata
			@ob_clean();
			imagepng($img);
			$data = ob_get_contents();
			
			imagedestroy($img);
			
			// hent gammel logo
			$result = ess::$b->db->query("SELECT ff_logo, ff_logo_path FROM ff WHERE ff_id = {$this->ff->id}");
			$old = mysql_result($result, 0);
			if (empty($old))
			{
				$this->ff->add_log("logo", login::$user->player->id);
			}
			else
			{
				$this->ff->add_log("logo", login::$user->player->id, base64_encode($old));
				
				// forsøk å slett fra disk
				$old_path = mysql_result($result, 0, 1);
				if (MAIN_SERVER && mb_substr($old_path, 0, 2) == "l:")
				{
					$path = PROFILE_IMAGES_FOLDER . "/" . mb_substr($old_path, 2);
					if (file_exists($path)) unlink($path);
				}
			}
			
			// lagre bildet på disk
			$url = null;
			if (MAIN_SERVER)
			{
				// sett opp navn for bildet
				$uniq = uniqid();
				$img_navn = "ff_{$this->ff->id}_$uniq.png";
				
				$filename = PROFILE_IMAGES_FOLDER . "/$img_navn";
				file_put_contents($filename, $data);
				
				$url = "l:$img_navn";
			}
			
			// lagre bildet til databasen
			ess::$b->db->query("UPDATE ff SET ff_logo = ".ess::$b->db->quote($data).", ff_logo_path = ".ess::$b->db->quote($url)." WHERE ff_id = {$this->ff->id}");
			
			ess::$b->page->add_message("Logoen ble oppdatert.");
			redirect::handle("panel?ff_id={$this->ff->id}");
		}
		
		ess::$b->page->add_js('
function vis_bilde(elm)
{
	var e = $("img_preview");
	var s = "file://" + elm.value;
	e.innerHTML = \'<img src="" width="110" height="110" style="background-color: #000000" alt="Valgt bilde" />\';
	e.firstChild.src = s;
}');
		
		echo '
<boxes />
<div class="section" style="width: 300px">
	<h2>Ny logo</h2>
	<p class="h_right"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	<p>Her kan du laste opp en ny logo for '.$this->ff->type['refobj'].'. Størrelsen på logoen vil bli gjort om til 110px i bredde og 110px i høyden. Du får derfor best kvalitet ved å laste opp et bilde som er i denne størrelsen.</p>
	<p>Den gamle logoen vil bli oppført i loggen sammen med ditt spillernavn.</p>
	<form action="" method="post" enctype="multipart/form-data">
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<dl class="dd_right dl_2x">
			<dt>Velg bilde</dt>
			<dd><input type="file" name="logo" onchange="vis_bilde(this)" /></dd>
			
			<dt>Forhåndsvisning</dt>
			<dd><div id="img_preview">Venter</div></dd>
		</dl>
		<h4>'.show_sbutton("Last opp logo").($this->ff->data['has_ff_logo'] ? ' '.show_sbutton("Fjern logo", 'name="remove"') : '').'</h4>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle forslag av medlem
	 */
	protected function page_suggestion_handle()
	{
		ess::$b->page->add_title("Foreslå en spiller som medlem");
		$player = false;
		
		// hvilken posisjon kan vi foreslå til?
		$limits = $this->ff->get_limits();
		$priority = isset($limits[4]) && $limits[4] >= 0 ? 4 : 3;
		
		// finne spiller?
		if (isset($_POST['player']) || isset($_REQUEST['up_id']))
		{
			// hent spillerinformasjon
			$where = isset($_REQUEST['up_id']) ? 'up_id = '.intval($_REQUEST['up_id']) : 'up_name = '.ess::$b->db->quote($_POST['player']);
			$more = isset($_REQUEST['up_id']) ? '' : ' ORDER BY up_access_level = 0, up_last_online DESC LIMIT 1';
			$result = ess::$b->db->query("
				SELECT up_id, up_name, up_access_level, up_points, upr_rank_pos, uc_time, uc_info, COUNT(IF(ff_is_crew = 0 AND ff_inactive = 0, 1, NULL)) ff_num
				FROM users_players
					LEFT JOIN users_players_rank ON upr_up_id = up_id
					JOIN users ON up_u_id = u_id
					LEFT JOIN users_contacts ON uc_u_id = u_id AND uc_contact_up_id = ".login::$user->player->id." AND uc_type = 2
					LEFT JOIN ff_members ON ffm_up_id = up_id AND (ffm_status = 0 OR ffm_status = 1)
					LEFT JOIN ff ON ff_id = ffm_ff_id
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
				// sett opp rank informasjon for spilleren
				$rank_info = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
				
				// er i FF?
				if (isset($this->ff->members['list'][$row['up_id']]))
				{
					ess::$b->page->add_message('<user id="'.$row['up_id'].'" /> er allerede foreslått, invitert eller medlem av '.$this->ff->type['refobj'].'.', "error");
				}
				
				// død/deaktivert?
				elseif ($row['up_access_level'] == 0)
				{
					ess::$b->page->add_message('<user id="'.$row['up_id'].'" /> er død og kan ikke foreslås til '.$this->ff->type['refobj'].'.', "error");
				}
				
				// blokkert?
				elseif ($row['uc_time'] && !$this->ff->mod)
				{
					$reason = game::bb_to_html($row['uc_info']);
					$reason = empty($reason) ? '' : ' Begrunnelse: '.$reason;
					ess::$b->page->add_message('Denne spilleren blokkerte deg '.ess::$b->date->get($row['uc_time'])->format().'. Du kan derfor ikke foreslå spilleren til '.$this->ff->type['refobj'].'.'.$reason, "error");
				}
				
				// har ikke høy nok rank?
				elseif ($rank_info['number'] < $this->ff->get_priority_rank($priority))
				{
					ess::$b->page->add_message('<user id="'.$row['up_id'].'" /> har ikke høy nok rank for å bli '.$this->ff->type['priority'][$priority].'. Må være minst '.game::$ranks['items_number'][$this->ff->get_priority_rank($priority)]['name'].'.', "error");
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
	<h1>Foreslå medlem til '.$this->ff->type['refobj'].'</h1>
	<p class="h_right"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<p>Skriv inn navnet på spilleren du ønsker å foreslå som medlem til '.$this->ff->type['refobj'].'.</p>
		<p>'.ucfirst($this->ff->type['priority'][1]).'/'.$this->ff->type['priority'][2].' vil få opp dette forslaget, og kan akseptere det slik at spilleren blir invitert som '.$this->ff->type['priority'][$priority].($this->ff->type['parent'] ? ' underordnet deg' : '').'.</p>
		<dl class="dd_right">
			<dt>Spiller</dt>
			<dd><input type="text" name="player" value="'.htmlspecialchars(postval("player", $player ? $player['up_name'] : '')).'" class="styled w100" /></dd>
		</dl>
		<p class="c">
			'.show_sbutton("Finn spiller").'
			<a href="panel?ff_id='.$this->ff->id.'">Tilbake</a>
		</p>
	</form>
</div>';
			
			$this->ff->load_page();
		}
		
		// bekreftet?
		if (isset($_POST['confirm']) && validate_sid(false))
		{
			// legg til forslag
			if ($this->ff->player_suggest($player['up_id']))
			{
				ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> ble foreslått til '.$this->ff->type['refobj'].' som '.$this->ff->type['priority'][$priority].($this->ff->type['parent'] ? ' underordnet deg' : '').'.');
				redirect::handle();
			}
			else
			{
				ess::$b->page->add_message("Noe gikk galt. Kunne ikke foreslå spilleren.", "error");
			}
		}
		
		// vis bekreftskjema
		ess::$b->page->add_title("Bekreft forslag");
		
		echo '
<div class="section" style="width: 220px">
	<h1>Bekreft forslag</h1>
	<p class="h_right"><a href="panel?ff_id='.$this->ff->id.'&amp;a=suggestion">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<input type="hidden" name="up_id" value="'.$player['up_id'].'" />
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<p>Informasjon:</p>
		<dl class="dd_right">
			<dt>Spiller</dt>
			<dd>'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</dd>
			<dt>Posisjon</dt>
			<dd>'.ucfirst($this->ff->type['priority'][$priority]).($this->ff->type['parent'] ? ' underordnet <user id="'.$this->ff->uinfo->id.'" />' : '').'</dd>
		</dl>
		<p class="c">
			'.show_sbutton("Bekreft forslag", 'name="confirm"').'
			<a href="panel?ff_id='.$this->ff->id.'&amp;a=suggestion">Tilbake</a>
		</p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Konstruer OFC objekt og legg det til
	 */
	protected function stats_ofc_build($id, $data, $labels, $height = 250)
	{
		$ofc = new OFC();
		
		$min = 0;
		$max = 0;
		foreach ($data as $row)
		{
			$this->stats_ofc_line($ofc, $row[0], $row[1], $row[2], $row[3]);
			$min = min($min, min($row[2]));
			$max = max($max, max($row[2]));
		}
		
		$ofc->axis_x()->label()->steps(1)->rotate(330)->labels($labels);
		$ofc->axis_y()->set_numbers($min, $max);
		
		$ofc->dark_colors();
		
		ess::$b->page->add_js('
function ofc_get_data_'.$id.'() { return '.js_encode((string) $ofc).'; }');
		
		$elm_id = "ff_stats_{$id}";
		ess::$b->page->add_js_file(LIB_HTTP.'/swfobject/swfobject.js');
		ess::$b->page->add_js_domready('swfobject.embedSWF("'.LIB_HTTP.'/ofc/open-flash-chart.swf", "'.$elm_id.'", "100%", '.$height.', "9.0.0", "", {"get-data": "ofc_get_data_'.$id.'"});');
		
		return $elm_id;
	}
	
	/**
	 * Opprett OFC-linje
	 */
	protected function stats_ofc_line($ofc, $text, $tip, $values, $color)
	{
		$bar = new OFC_Charts_Line();
		$bar->text($text);
		$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip($tip);
		$bar->values($values);
		$bar->colour($color);
		$ofc->add_element($bar);
	}
	
	/**
	 * Behandle endring av beskrivelse
	 */
	protected function page_description_handle()
	{
		ess::$b->page->add_title("Beskrivelse for {$this->ff->type['refobj']}");
		redirect::store("panel?ff_id={$this->ff->id}&a=beskrivelse");
		
		// lagre?
		if (isset($_POST['description']) && isset($_POST['save']) && validate_sid(false))
		{
			if ($_POST['description'] == $this->ff->data['ff_description'])
			{
				ess::$b->page->add_message("Beskrivelsen ble ikke endret.");
				redirect::handle();
			}
			
			// oppdater
			ess::$b->db->query("UPDATE ff SET ff_description = ".ess::$b->db->quote($_POST['description'])." WHERE ff_id = {$this->ff->id}");
			
			ess::$b->page->add_message("Beskrivelsen er nå oppdatert.");
			$this->ff->add_log("description", login::$user->player->id, $this->ff->data['ff_description']);
			redirect::handle("panel?ff_id={$this->ff->id}");
		}
		
		ess::$b->page->add_js_domready('
	$("previewButton").addEvent("click", function()
	{
		$("previewContainer").set("html", "<p>Laster inn forhåndsvisning..</p>");
		$("previewOuter").setStyle("display", "block");
		if ($("previewOuter").getPosition().y > window.getScroll().y + window.getSize().y)
		{
			$("previewOuter").goto(-15);
		}
		preview($("textContent").get("value"), $("previewContainer"));
	});');
		
		echo '
<div class="section" style="width: 500px">
	<form action="" method="post">
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<h2>Beskrivelse av '.$this->ff->type['refobj'].'</h2>
		<p class="h_right"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
		<p>Dette er hva som vil stå øverst på forsiden av '.$this->ff->type['refobj'].' og er ment som en beskrivelse av '.$this->ff->type['refobj'].'.</p>
		<p><textarea name="description" rows="20" cols="75" style="width: 490px" id="textContent">'.htmlspecialchars(postval("description", $this->ff->data['ff_description'])).'</textarea></p>
		<p>
			'.show_sbutton("Lagre", 'name="save" accesskey="s"').'
			'.show_button("Forhåndsvis", 'accesskey="p" id="previewButton"').'
		</p>
	</form>
</div>
<div style="display: none" id="previewOuter">
	<p class="c">Forhåndsvisning:</p>
	<div class="p" style="'.($this->ff->type['type'] == "familie" ? 'background-color: #1A1A1A; width: 408px; border: 5px solid #292929; padding: 5px 5px; margin: 10px auto' : 'margin: 10px 0; padding: 5px 0; border: 5px solid #292929; border-left: 0; border-right: 0').'" id="previewContainer"></div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle endring av medemsgrense
	 */
	protected function page_members_limit_handle()
	{
		ess::$b->page->add_title("Medlemsbegrensning");
		redirect::store("panel?ff_id={$this->ff->id}&a=members_limit");
		
		// hent tall
		$max = $this->ff->members_limit_max_info();
		
		// øke begrensningen?
		if (isset($_POST['increase']) && validate_sid())
		{
			// ingen grense?
			if ($max['active'] == 0)
			{
				ess::$b->page->add_message("Det er ingen medlemsbegrensning.", "error");
			}
			
			// har vi allerede nådd grensa?
			elseif ($max['active'] >= $max['max'])
			{
				ess::$b->page->add_message("Det er ikke mulig å øke medlemsbegrensningen noe mer.".($this->ff->competition ? ' Etter konkurranseperioden vil det være mulig å øke antall medlemmer ytterligere.' : ''), "error");
			}
			
			// forandret seg?
			elseif (postval("count") != $max['active'])
			{
				ess::$b->page->add_message("Medlemsbegrensningen har endret seg siden du viste siden. Prøv på nytt om du fremdeles ønsker.", "error");
			}
			
			else
			{
				// forsøk å øk begrensningen
				if ($this->ff->members_limit_increase()) redirect::handle();
			}
		}
		
		// senke begrensningen?
		if (isset($_POST['decrease']) && validate_sid())
		{
			// ingen grense?
			if ($max['active'] == 0)
			{
				ess::$b->page->add_message("Det er ingen medlemsbegrensning.", "error");
			}
			
			// har vi allerede nådd grensa?
			elseif ($max['active'] <= $max['min'])
			{
				ess::$b->page->add_message("Det er ikke mulig å senke medlemsbegrensningen noe mer.", "error");
			}
			
			// har for mange medlemmer?
			elseif (count($this->ff->members['members']) + count($this->ff->members['invited']) >= $max['active'])
			{
				ess::$b->page->add_message("Det er for mange medlemmer/inviterte til broderskapet, og medlemsbegrensningen kan ikke senkes mer uten å kaste ut/trekke tilbake invitasjon til en spiller.", "error");
			}
			
			// forandret seg?
			elseif (postval("count") != $max['active'])
			{
				ess::$b->page->add_message("Medlemsbegrensningen har endret seg siden du viste siden. Prøv på nytt om du fremdeles ønsker.", "error");
			}
			
			else
			{
				// forsøk å senk begrensningen
				if ($this->ff->members_limit_decrease()) redirect::handle();
			}
		}
		
		// ingen begrensning?
		if ($max['active'] == 0)
		{
			echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Medlemsbegrensning<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Det er ingen medlemsbegrensning for '.$this->ff->type['refobj'].'.</p>
		<p class="c"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
			
			$this->ff->load_page();
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Medlemsbegrensning<span class="left"></span><span class="right"></span></h1>
	<div class="bg1 j">
		<boxes />
		<p>Medlemsbegrensningen bestemmer hvor mange medlemmer du kan ha i '.$this->ff->type['refobj'].'.</p>
		<dl class="dd_right">
			<dt>Nåværende begrensning</dt>
			<dd><b>'.$max['active'].'</b></dd>
			<dt>Antall medlemmer og inviterte</dt>
			<dd><b>'.(count($this->ff->members['members']) + count($this->ff->members['invited'])).'</b></dd>
			<dt>Minste mulige begrensning</dt>
			<dd'.($max['active'] == $max['min'] ? ' style="color: #F00"' : '').'>'.$max['min'].'</dd>
			<dt>Maksimale mulige begrensning</dt>
			<dd'.($max['active'] == $max['max'] ? ' style="color: #F00"' : '').'>'.$max['max'].'</dd>
			<dt>Begrensning for driftskostnad</dt>
			<dd>'.($max['min'] + $max['extra_max']).'</dd>
		</dl>'.($this->ff->competition ? '
		<p>Etter broderskapkonkurransen er ferdig vil du kunne øke medlemsbegrensningen ytterligere.</p>' : '').'
		<p>Når medlemsbegrensningen øker, vil utgangspunktet til driftskostnaden øke med <b>'.game::format_cash(ff::PAY_COST_INCREASE_FFM).'</b> per medlem. I tillegg må det betales <b>'.game::format_cash(ff::MEMBERS_LIMIT_INCREASE_COST).'</b> fra banken til '.$this->ff->type['refobj'].' i det begrensningen økes.</p>
		<p>Når medlemsbegrensningen settes ned må man vente til neste periode for driftskostnad før dette antallet blir satt ned igjen.</p>
		<dl class="dd_right">
			<dt>Penger i <a href="banken?ff_id='.$this->ff->id.'">banken</a> til '.$this->ff->type['refobj'].'</dt>
			<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="count" value="'.$max['active'].'" />'.($max['active'] < $max['max'] ? '
			<p class="c">'.show_sbutton("Øk med én plass (".game::format_cash(ff::MEMBERS_LIMIT_INCREASE_COST).")", 'name="increase"').'</p>' : '').($max['active'] > $max['min'] ? '
			<p class="c">'.show_sbutton("Senk med én plass", 'name="decrease"').'</p>' : '').'
		</form>
		<p class="c"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle valg av bygning
	 */
	protected function page_br_handle()
	{
		// allerede valgt bygning?
		if ($this->ff->data['br_id'] && !$this->ff->mod)
		{
			ess::$b->page->add_message(ucfirst($this->ff->type['refobj'])." er allerede tilknyttet en bygning.");
			redirect::handle();
		}
		
		ess::$b->page->add_title("Velg bygning");
		
		$bydel = login::$user->player->bydel;
		
		// hent ledige bygninger i denne bydelen
		$result = ess::$b->db->query("
			SELECT br_id, br_pos_x, br_pos_y
			FROM bydeler_resources
				LEFT JOIN (SELECT DISTINCT ff_br_id FROM ff WHERE ff_inactive = 0 AND ff_br_id IS NOT NULL) ref ON ff_br_id = br_id
			WHERE br_b_id = {$bydel['id']} AND br_type = 1 AND ff_br_id IS NULL");
		$resources_free = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$resources_free[$row['br_id']] = $row;
		}
		
		// velge bygning?
		if (isset($_POST['br']))
		{
			$br_id = (int) $_POST['br'];
			
			// ikke gyldig bygning?
			if (!isset($resources_free[$br_id]))
			{
				ess::$b->page->add_message("Fant ikke valgt bygning.");
			}
			
			else
			{
				// oppdater FF
				ess::$b->db->query("UPDATE ff SET ff_br_id = $br_id WHERE ff_id = {$this->ff->id}");
				
				global $__server;
				putlog("INFO", ucfirst($this->ff->type['refobj'])." %u{$this->ff->data['ff_name']}%u har nå valgt".($this->ff->data['br_id'] ? ' ny' : '')." bygning på {$bydel['name']}. {$__server['path']}/ff/?ff_id={$this->ff->id}");
				putlog("CREWCHAN", ucfirst($this->ff->type['refobj'])." %u{$this->ff->data['ff_name']}%u har nå valgt".($this->ff->data['br_id'] ? ' ny' : '')." bygning på {$bydel['name']}. {$__server['path']}/ff/?ff_id={$this->ff->id}");
				
				// live-feed
				livefeed::add_row(ucfirst($this->ff->refstring).' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->ff->id.'">'.htmlspecialchars($this->ff->data['ff_name']).'</a> har valgt bygning på '.htmlspecialchars($bydel['name']).'.');
				
				// første familien i spillet?
				if ($this->ff->type['type'] == "familie")
				{
					hall_of_fame::trigger("familie", $this->ff);
					hall_of_fame::trigger("ff_owner", $this->ff);
				}
				
				ess::$b->page->add_message("Du har valgt bygning for {$this->ff->type['refobj']} på {$bydel['name']}.");
				redirect::handle("?ff_id={$this->ff->id}");
			}
		}
		
		
		// hent FF-ene i denne bydelen
		$result = ess::$b->db->query("SELECT ff_id, ff_name, br_pos_x, br_pos_y FROM ff JOIN bydeler_resources ON ff_br_id = br_id WHERE br_b_id = {$bydel['id']} AND ff_inactive = 0");
		$resources = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$resources[$row['ff_id']] = $row;
		}
		
		ess::$b->page->add_css('
#default_main { overflow: visible }
.bydel_parent { position: relative }
.bydel_resource { position: absolute }
.bydel_resource img { cursor: pointer; z-index: 100 }
.bydel_resource div {
	position: absolute;
	left: 28px;
	top: 2px;
	font-size: 11px;
	background-color: #0B0B0B;
	color: #AAA;
	padding: 2px;
	z-index: 1000;
	white-space: nowrap;
}
');
		ess::$b->page->add_js_file("../js/bydeler.js");
		ess::$b->page->add_js_domready('
	var bydel_resources = new Hash('.js_encode($resources).');
	var bydel_resources_free = new Hash('.js_encode($resources_free).');
	var bydel_x = '.$bydel['b_coords_x'].';
	var bydel_y = '.$bydel['b_coords_y'].';
	
	var kart = $("bydelskart");
	kart.getParent().setStyle("position", "relative");
	var pos = kart.getPosition(kart.getParent());
	var bydel_container = new Element("div", {"styles": {"position": "absolute", "left": 4, "top": 0}}).inject(kart, "before");
	
	bydel_resources.each(function(value)
	{
		new BydelResourceFF(value, bydel_container, bydel_x, bydel_y);
	});
	
	var select_br = function()
	{
		if (confirm("Er du sikker på at du vil velge denne bygningen for '.$this->ff->type['refobj'].'? Dette kan ikke endres senere."))
		{
			$("br_id").set("value", this.options.data["br_id"]).form.submit();
		}
	};
	
	bydel_resources_free.each(function(value)
	{
		new BydelResourceSelect(value, bydel_container, bydel_x, bydel_y, select_br);
	});');
		
		echo '
<div class="bg1_c" style="width: '.($bydel['b_size_x']+30).'px; margin: 40px auto">
	<h1 class="bg1">Du er på '.$bydel['name'].'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="./?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
	<div class="bg1" style="overflow: visible; padding-top: 1px; margin-top: -1px">
		<form action="" method="post">
			<input type="hidden" name="br" id="br_id" />
			<boxes />'.(count($resources_free) == 0 ? '
			<p>Det er ingen ledige plasser i denne bydelen.</p>' : '
			<p>Det er '.count($resources_free).' '.fword("ledig plass", "ledige plasser", count($resources_free)).' i denne bydelen.</p>').'
			<p class="c bydel_parent"><img src="'.IMGS_HTTP.'/bydeler/bydel_'.$bydel['id'].'.png" id="bydelskart" alt="Bydelskart for '.htmlspecialchars($bydel['name']).'" /></p>
		</form>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle nedleggelse
	 */
	protected function page_drop_handle()
	{
		// allerede lagt ned?
		if (!$this->ff->active)
		{
			ess::$b->page->add_message(ucfirst($this->ff->type['refobj'])." er allerde oppløst.");
			redirect::handle();
		}
		
		// sjekk for aktiv auksjon
		$result = ess::$b->db->query("SELECT a_id, a_params FROM auksjoner WHERE a_type = ".auksjon::TYPE_FIRMA." AND a_end >= ".time()." AND a_completed = 0 AND a_active != 0");
		while ($row = mysql_fetch_assoc($result))
		{
			$params = new params($row['a_params']);
			if ($params->get("ff_id") == $this->ff->id)
			{
				ess::$b->page->add_message(ucfirst($this->ff->type['refobj']).' ligger allerede ute på auksjon og kan ikke legges ned på nytt nå.', "error");
				redirect::handle("/auksjoner?a_id={$row['a_id']}", redirect::ROOT);
			}
		}
		
		ess::$b->page->add_title("Legg ned {$this->ff->type['refobj']}");
		
		// godkjent å legge ned FF?
		if (isset($_POST['confirm']) && (isset($_POST['pass']) || $this->ff->mod) && validate_sid())
		{
			// kontroller passordet
			if (!$this->ff->mod && !password::verify_hash($_POST['pass'], login::$user->data['u_pass'], 'user'))
			{
				ess::$b->page->add_message("Passordet du skrev inn stemmer ikke.", "error");
			}
			
			else
			{
				// melding
				putlog("CREWCHAN", "%u".login::$user->player->data['up_name']."%u la ned {$this->ff->type['refobj']} %u{$this->ff->data['ff_name']}%u");
				
				// legg ned FF
				$this->ff->dies();
				
				ess::$b->page->add_message("Du har lagt ned {$this->ff->type['refobj']} {$this->ff->data['ff_name']}.");
				redirect::handle("");
			}
		}
		
		echo '
<div class="section" style="width: 220px">
	<h1>Legg ned '.$this->ff->type['refobj'].'</h1>
	<p class="h_right"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	<boxes />
	<form action="" method="post">
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<p>Du er i ferd med å <u>legge ned '.$this->ff->type['refobj'].'</u>. Når du legger ned '.$this->ff->type['refobj'].' vil '.$this->ff->type['refobj'].' bli oppløst. Du og alle medlemmer vil miste tilgang til '.$this->ff->type['refobj'].' og dets forum.</p>'.($this->ff->type['type'] == 'familie' ? ($this->ff->competition || $this->ff->params->get("die_no_new") ? '' : '
		<p>En ny broderskapkonkurranse vil bli opprettet som vil gjøre det mulig om å konkurrere om et nytt broderskap som tar denne sin plass.') : '
		<p>Firmaet vil bli lagt ut på en auksjon, og vinneren av auksjonen vil fortsette driften av firmaet. Du vil ikke motta noe fra denne auksjonen.</p>').'
		<p>Du kan alternativt <a href="panel?ff_id='.$this->ff->id.'&amp;a=sell">selge</a> '.$this->ff->type['refobj'].'.</p>'.($this->ff->competition ? '
		<p><b>Merk:</b> Du har ikke mulighet til å opprette ny '.$this->ff->type['refobj'].' i samme konkurranse etter at du har lagt ned '.$this->ff->type['refobj'].'.</p>' : '').'
		<dl class="dd_right">
			<dt>Penger i banken</dt>
			<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
			<dt>Antall medlemmer</dt>
			<dd>'.count($this->ff->members['members']).'</dd>
		</dl>'.(!$this->ff->mod ? '
		<dl class="dd_right">
			<dt>Brukerpassord</dt>
			<dd><input type="password" name="pass" class="styled w100" /></dd>
		</dl>' : '').'
		<p class="c">
			<span class="red">'.show_sbutton("Bekreft, legg ned {$this->ff->type['refobj']}", 'name="confirm"').'</span>
			<a href="panel?ff_id='.$this->ff->id.'">Tilbake</a>
		</p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Legg til forum lenke
	 */
	protected function forum_link_add()
	{
		// har vi allerede forum lenken på plass?
		if ($this->ff->uinfo->forum_link() !== null)
		{
			ess::$b->page->add_message("Lenken er allerede lagt til.");
			redirect::handle();
		}
		
		// legg til lenken
		$this->ff->uinfo->forum_link(true);
		
		ess::$b->page->add_message("Lenke i menyen til forumet for {$this->ff->type['refobj']} er nå opprettet.");
		redirect::handle();
	}
	
	/**
	 * Fjern forum lenke
	 */
	protected function forum_link_remove()
	{
		// er ikke forum lenken lagt til?
		if ($this->ff->uinfo->forum_link() === null)
		{
			ess::$b->page->add_message("Lenken finnes ikke fra før av.");
			redirect::handle();
		}
		
		// fjern linken
		$this->ff->uinfo->forum_link(false);
		
		ess::$b->page->add_message("Lenke i menyen til forumet for {$this->ff->type['refobj']} er nå fjernet.");
		redirect::handle();
	}
	
	/**
	 * Behandle forlatelse
	 */
	protected function page_leave_handle()
	{
		// crew?
		if ($this->ff->uinfo->crew)
		{
			ess::$b->page->add_message("Du har crewtilgang til denne {$this->ff->type['refobj']} og må logge ut av utvidede tilganger for å midlertidig forlate {$this->ff->type['refobj']}.", "error");
			redirect::handle();
		}
		
		// eier av FF?
		if ($this->ff->uinfo->data['ffm_priority'] == 1)
		{
			ess::$b->page->add_message("Du kan ikke forlate som eier. Det eneste valget du har er å selge {$this->ff->type['refobj']}.", "error");
			redirect::handle();
		}
		
		// avbryte?
		if (isset($_POST['abort']))
		{
			redirect::handle();
		}
		
		// forlat FF
		elseif (isset($_POST['confirm']) && validate_sid(false))
		{
			$this->ff->uinfo->leave();
			ess::$b->page->add_message("Du forlot {$this->ff->type['refobj']}.");
			$this->ff->redirect();
		}
		
		echo '
<!-- forlat FF -->
<div class="section w200">
	<h2>Forlat '.$this->ff->type['refobj'].'</h2>
	<p>Er du sikker på at du ønsker å forlate denne '.$this->ff->type['refobj'].'?</p>
	<p>Dette tilsvarer å bli sparket fra '.$this->ff->type['refobj'].'.</p>
	<form action="" method="post">
		<input type="hidden" name="leave" />
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<p class="c">'.show_sbutton("Forlat {$this->ff->type['refobj']}", 'name="confirm"').' '.show_sbutton("Avbryt", 'name="abort"').'</p>
	</form>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Behandle donasjon
	 */
	protected function page_donate_handle()
	{
		$amount = game::intval($_POST['donate']);
		$note = trim(postval("note"));
		
		// nostat?
		if (access::is_nostat())
		{
			ess::$b->page->add_message("Du er nostat og har ikke tilgang til å donere til {$this->ff->type['refobj']}.", "error");
		}
		
		// negativt beløp?
		elseif ($amount < 0)
		{
			ess::$b->page->add_message("Beløpet kan ikke være negativt..", "error");
		}
		
		// minimum: 15 000 kr
		elseif ($amount < 15000)
		{
			ess::$b->page->add_message("Minstebeløp å donere er 15 000 kr.", "error");
		}
		
		// godkjent?
		else
		{
			// finn ut når vi donerte siste gang - kan ikke donere oftere enn en gang i timen
			$result = ess::$b->db->query("SELECT ffbl_time FROM ff_bank_log WHERE ffbl_ff_id = {$this->ff->id} AND ffbl_type = 3 AND ffbl_up_id = ".login::$user->player->id." ORDER BY ffbl_time DESC LIMIT 1");
			$last = mysql_fetch_assoc($result);
			
			if ($last && $last['ffbl_time']+3600 > time())
			{
				ess::$b->page->add_message("Du kan ikke donere oftere enn én gang per time. Du må vente ".game::counter($last['ffbl_time']+3600-time())." før du kan donere på nytt.", "error");
			}
			
			// ikke nok penger?
			elseif ($amount > login::$user->player->data['up_cash'])
			{
				ess::$b->page->add_message("Du har ikke nok penger på hånda til å donere ".game::format_cash($amount)." til {$this->ff->type['refobj']}.", "error");
			}
			
			// godkjent?
			elseif (isset($_POST['approve']) && validate_sid(false))
			{
				// forsøk å donere
				ess::$b->db->query("UPDATE ff, users_players SET ff_bank = ff_bank + $amount, up_cash = up_cash - $amount WHERE ff_id = {$this->ff->id} AND up_id = ".login::$user->player->id." AND up_cash >= $amount");
				
				// hadde ikke nok penger?
				if (ess::$b->db->affected_rows() == 0)
				{
					ess::$b->page->add_message("Du har ikke nok penger på hånda til å donere ".game::format_cash($amount)." til {$this->ff->type['refobj']}.", "error");
				}
				
				else
				{
					// finn balanse
					$result = ess::$b->db->query("SELECT ff_bank FROM ff WHERE ff_id = {$this->ff->id}");
					$balance = mysql_result($result, 0);
					
					// legg til logg
					ess::$b->db->query("INSERT INTO ff_bank_log SET ffbl_ff_id = {$this->ff->id}, ffbl_type = 3, ffbl_amount = $amount, ffbl_up_id = ".login::$user->player->id.", ffbl_time = ".time().", ffbl_balance = $balance, ffbl_note = ".ess::$b->db->quote($note));
					
					// legg til i spillerinfo
					ess::$b->db->query("UPDATE ff_members SET ffm_donate = ffm_donate + $amount WHERE ffm_up_id = ".login::$user->player->id." AND ffm_ff_id = {$this->ff->id} AND ffm_status = 1");
					ess::$b->page->add_message("Du donerte ".game::format_cash($amount)." til {$this->ff->type['refobj']}.");
					
					// legg til daglig stats
					$this->ff->stats_update("money_in", $amount, true);
					
					redirect::handle();
				}
			}
			
			elseif (!isset($_POST['skip']))
			{
				ess::$b->page->add_title("Donér til {$this->ff->type['refobj']}");
				
				// vis skjema for godkjenning
				echo '
<!-- donasjon -->
<div class="section w200">
	<h2>Donér til '.$this->ff->type['refobj'].'</h2>
	<p>Du er i ferd med å donére til '.$this->ff->type['refobj'].'.</p>
	<p>Beløp: '.game::format_cash($amount).'</p>
	<p>Melding/notat: '.game::format_data($note, "bb-opt", "Uten melding").'</p>
	<form action="" method="post">
		<input type="hidden" name="donate" value="'.$amount.'" />
		<input type="hidden" name="note" value="'.htmlspecialchars($note).'" />
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<p class="c">
			'.show_sbutton("Utfør donasjon", 'name="approve"').'
			'.show_sbutton("Avbryt", 'name="skip"').'
		</p>
	</form>
</div>';
				$this->ff->load_page();
			}
		}
	}
	
	/**
	 * Vis informasjon om betaling for FF
	 */
	protected function page_pay_handle()
	{
		$pay_info = $this->ff->pay_info();
		
		if (isset($_POST['pay']))
		{
			// har vi ikke gått over tiden
			if ($pay_info['in_time'])
			{
				ess::$b->page->add_message("Du har ikke mulighet til å betale nå.");
			}
			
			// har vi nok penger?
			elseif (login::$user->player->data['up_cash'] <= $pay_info['price'])
			{
				ess::$b->page->add_message("Du har ikke nok penger på hånda.");
			}
			
			else
			{
				// utfør betalingen
				$result = $this->ff->pay_action();
				if ($result !== false)
				{
					ess::$b->page->add_message("Du har betalt driftskostnaden på <b>".game::format_cash($result)."</b>. Neste gang driftskostnaden skal trekkes er ".ess::$b->date->get($this->ff->data['ff_pay_next'])->format().".");
					$this->ff->redirect();
				}
				
				else
				{
					ess::$b->page->add_message("Noe gikk galt ved betaling. Prøv på nytt.");
					redirect::handle("panel?ff_id={$this->ff->id}&a=pay");
				}
			}
		}
		
		ess::$b->page->add_title("Driftskostnad");
		
		// hent oversikt over medlemmer og vis hvor mye hver av dem har ranket som teller for FF
		$rank_info = $this->ff->get_rank_info();
		
		ess::$b->page->add_css('
.ff_panel_pay { padding-bottom: 1em }
.ff_panel_pay .progressbar p { color: #EEEEEE }
.ff_panel_pay .progressbar { margin-bottom: 2px; background-color: #2D2D2D }
.ff_panel_pay .progressbar .progress { background-color: #434343 }');
		
		echo '
<div class="section w350 ff_panel_pay">
	<h2>Driftskostnad</h2>
	<p class="h_right"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	<boxes />
	<p>For at '.$this->ff->type['refobj'].' ikke skal dø ut, må det betales inn et beløp på <u>100 mill</u> i tillegg til kostnad per ekstra medlemsplass til spillet hver 10. dag.</p>
	<p>Når medlemmene i '.$this->ff->type['refobj'].' ranker, vil beløpet som må innbetales synke med 1 og 1 mill avhengig av hvor mye som rankes.</p>
	<p>'.ucfirst($this->ff->type['refobj']).' mister ikke ranken hvis et medlem forlater '.$this->ff->type['refobj'].'.</p>
	<p>Beløpet vil bli trukket fra banken for '.$this->ff->type['refobj'].' automatisk ved innbetalingstidspunkt. Hvis det ikke er nok penger i banken, vil '.$this->ff->type['refobj'].' få frist på å innbetale beløpet manuelt innen 24 timer. Beløpet vil da øke med 50 %.</p>'.(!$pay_info['in_time'] ? '
	<div class="section" style="width: 220px">
		<h2>Betaling av driftskostnad</h2>
		<p class="error_box">'.ucfirst($this->ff->type['refobj']).' har overskredet tidspunktet for innbetaling. Betaling må skje manuelt av '.$this->ff->type['priority'][1].'/'.$this->ff->type['priority'][2].'.</p>
		<dl class="dd_right">
			<dt>Betalingsfrist</dt>
			<dd>'.ess::$b->date->get($pay_info['next'])->format().'<br />'.game::timespan($pay_info['next'], game::TIME_ABS).'</dd>
			<dt>Beløp</dt>
			<dd>'.game::format_cash($pay_info['price']).'</dd>
		</dl>'.($this->ff->access(2) ? '
		<form action="panel?ff_id='.$this->ff->id.'&amp;a=pay" method="post">
			<p class="c">'.show_sbutton("Betal driftskostnaden", 'name="pay"').'</p>
		</form>' : '').'
		<p>Hvis beløpet ikke blir betalt innen betalingsfristen vil '.$this->ff->type['refobj'].' bli oppløst.</p>
	</div>' : '
	<dl class="dd_right">
		<dt>Neste innbetaling</dt>
		<dd>'.ess::$b->date->get($pay_info['next'])->format().'</dd>
		<dt>Utgangspunkt for beløp ('.fwords("%d eksta spillerplass", "%d ekstra spillerplasser", $pay_info['members_limit']).')</dt>
		<dd>'.game::format_cash($pay_info['price_max']).'</dd>
		<dt>Foreløpig beløp</dt>
		<dd>'.game::format_cash($pay_info['price']).'</dd>
	</dl>').'
	<p>Medlemmers bidrag:</p>';
		
		
		if (count($rank_info['players']) == 0)
		{
			echo '
	<p>'.ucfirst($this->ff->type['refobj']).' har ingen medlemmer.</p>';
		}
		else
		{
			foreach ($rank_info['players'] as $info)
			{
				echo '
	<div class="progressbar">
		<div class="progress'.($info['points'] < 0 ? ' ff_progress_negative' : '').'" style="width: '.round($info['percent_bar']).'%">
			<p>'.game::profile_link($info['member']->id, $info['member']->data['up_name'], $info['member']->data['up_access_level']).' ('.$info['member']->get_priority_name().($info['member']->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$info['member']->data['ffm_parent_up_id'].'" />' : '').') ('.game::format_number($info['percent_text'], 1).' %)</p>
		</div>
	</div>';
			}
		}
		
		if (isset($rank_info['others']))
		{
			echo '
	<div class="progressbar">
		<div class="progress'.($rank_info['others']['points'] < 0 ? ' ff_progress_negative' : '').'" style="width: '.round($rank_info['others']['percent_bar']).'%">
			<p>Tidligere medlemmer av '.$this->ff->type['refobj'].' ('.game::format_number($rank_info['others']['percent_text'], 1).' %)</p>
		</div>
	</div>';
		}
		
		echo '
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Statistikk over betalinger for driftskostnad
	 */
	protected function page_paystats()
	{
		ess::$b->page->add_title("Statistikk over driftskostnad");
		
		// antall betalinger vi skal vise
		$limit = 15;
		
		$ff_reset = "";
		if ($this->ff->data['ff_time_reset'] && !$this->ff->mod)
		{
			$date = ess::$b->date->get($this->ff->data['ff_time_reset']);
			$ff_reset = " AND ffsp_date > '".$date->format("Y-m-d")."'";
		}
		
		// hent statistikk
		$result = ess::$b->db->query("
			SELECT ffsp_id, ffsp_date, ffsp_manual, ffsp_points, ffsp_up_limit, ffsp_cost
			FROM ff_stats_pay
			WHERE ffsp_ff_id = {$this->ff->id}$ff_reset
			ORDER BY ffsp_date
			LIMIT $limit");
		$periods = array();
		$ffsp_ids = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$periods[] = $row;
			$ffsp_ids[] = $row['ffsp_id'];
		}
		$periode = array_reverse($periods);
		$ffsp_ids = implode(",", $ffsp_ids);
		
		// ingen statistikk?
		if (count($periods) == 0)
		{
			ess::$b->page->add_message("Det er ikke utført noen betalinger enda og ingen statistikk foreligger.", "error");
			redirect::handle();
		}
		
		$limit_data = $this->ff->members_limit_max_info();
		
		// sett opp liste for data
		$stats = array();
		$labels = array();
		$cost = array();
		$up_limit = array();
		$points_max = 0; // for å finne 100 %
		foreach ($periods as $row)
		{
			$stats[0][$row['ffsp_id']] = 0;
			$labels[] = $row['ffsp_date'];
			$cost[] = (int) round($row['ffsp_cost'] / 1000000);
			$up_limit[] = (int) $row['ffsp_up_limit'];
			foreach ($this->ff->members['members'] as $member)
			{
				$stats[$member->id][$row['ffsp_id']] = 0;
			}
		}
		foreach ($this->ff->members['members'] as $member)
		{
			$v = 0;
			if ($member->data['up_access_level'] < ess::$g['access_noplay']) // ikke nostat
			{
				$v = $member->data['up_points_rel'] - $member->data['ffm_pay_points'];
			}
			
			$stats[$member->id][] = $v;
			$points_max = max($points_max, abs($v));
		}
		
		$pay_info = $this->ff->pay_info();
		
		$labels[] = "Inneværende periode";
		$cost[] = round($pay_info['price'] / 1000000);
		$up_limit[] = $limit_data['min'] + $limit_data['extra_max'];
		$stats[0][] = (int) $this->ff->data['ff_pay_points'];
		$points_max = max($points_max, abs($this->ff->data['ff_pay_points']));
		
		// hent inn statistikk over medlemmene
		$result = ess::$b->db->query("
			SELECT ffspm_ffsp_id, ffspm_up_id, ffspm_points
			FROM ff_stats_pay_members
			WHERE ffspm_ffsp_id IN ($ffsp_ids)");
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($stats[$row['ffspm_up_id']][$row['ffspm_ffsp_id']]))
			{
				$stats[0][$row['ffspm_ffsp_id']] += $row['ffspm_points'];
				$points_max = max($points_max, abs($stats[0][$row['ffspm_ffsp_id']]));
			}
			
			else
			{
				$stats[$row['ffspm_up_id']][$row['ffspm_ffsp_id']] += $row['ffspm_points'];
				$points_max = max($points_max, abs($stats[$row['ffspm_up_id']][$row['ffspm_ffsp_id']]));
			}
		}
		
		// omregn til prosentverdier
		foreach ($stats as &$ffsp)
		{
			foreach ($ffsp as &$num)
			{
				$num = round($num / $points_max * 100, 2);
			}
		}
		unset($num);
		
		essentials::load_module("OFC");
		
		// sett opp data for rank
		$data_rank = array(array("Tidligere medlemmer", "Tidligere medlemmer: #val# %", array_values($stats[0]), OFC_Colours::$colours[0]));
		$i = 0;
		foreach ($this->ff->members['members'] as $member)
		{
			$data_rank[] = array(
				$member->data['up_name'],
				"{$member->data['up_name']}: #val# %",
				array_values($stats[$member->id]),
				OFC_Colours::$colours[++$i % count(OFC_Colours::$colours)]
			);
		}
		
		// sett opp data for pengeflyt og medlemsbegrensning
		$data_flow = array(
			array("Driftskostnad", "Driftskostnad: #val# mill kr", $cost, OFC_Colours::$colours[0]),
			array("Medlemsbegrensning", "Medlemsbegrensning: #val#", $up_limit, OFC_Colours::$colours[1])
		);
		
		// sett opp diagrammer
		$elm_id_rank = $this->stats_ofc_build("pay_rank", $data_rank, $labels, 400);
		$elm_id_flow = $this->stats_ofc_build("pay_flow", $data_flow, $labels, 400);
		
		echo '
<div class="bg1_c">
	<h1 class="bg1">Statistikk over driftskostnad for '.$this->ff->type['refobj'].'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Denne siden viser hvor mye en spiller i '.$this->ff->type['refobj'].' har ranket mellom hver driftsperiode. Statistikken gjelder for maksimalt 15 driftskostnader tilbake i tid, i tillegg til driftskostnaden så langt i nåværende periode.</p>
		<p class="c"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
		<p>Rankutvikling:</p>
		<div style="margin: 10px 0"><div id="'.$elm_id_rank.'"></div></div>
		<p>Driftskostnad og medlemsbegrensning: <span class="dark">(Driftskostnaden er i millioner kr.)</span></p>
		<div style="margin: 10px 0"><div id="'.$elm_id_flow.'"></div></div>
		<p class="c"><a href="panel?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Vis informasjon om medlemmene
	 */
	protected function page_members_handle()
	{
		ess::$b->page->add_title("Medlemsdetaljer");
		
		// ingen medlemmer?
		if (count($this->ff->members['members']) == 0)
		{
			redirect::handle();
		}
		
		// sett opp medlemsliste og hent utvidet informasjon
		$members_id = array();
		foreach ($this->ff->members['members'] as $member)
		{
			$members_id[] = $member->id;
		}
		
		$members = array();
		$result = ess::$b->db->query("
			SELECT up_id, up_health, up_health_max, up_b_id, up_brom_expire
			FROM users_players
			WHERE up_id IN (".implode(",", $members_id).")");
		while ($row = mysql_fetch_assoc($result))
		{
			$members[$row['up_id']] = $row;
		}
		
		echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Medlemsdetaljer<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<table class="table tablem center">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Sist pålogget</th>
					<th>Plassering</th>
					<th>I bomberom?</th>
					<th>Helse</th>
				</tr>
			</thead>
			<tbody>';
		
		// vis oversikt over medlemmene
		$i = 0;
		foreach ($this->ff->members['members'] as $member)
		{
			$info = $members[$member->id];
			$brom = $info['up_brom_expire'] > time() ? 'Ja (til '.ess::$b->date->get($info['up_brom_expire'])->format(date::FORMAT_SEC).')' : 'Nei';
			$helse_p = $info['up_health'] / $info['up_health_max'];
			$helse = $helse_p > 0.9 ? 'Over 90 %' : ($helse_p < 0.1 ? 'Under 10 %' : ($helse_p < 0.5 ? 'Under 50 %' : 'Over 50 %'));
			
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.game::profile_link($member->id, $member->data['up_name'], $member->data['up_access_level']).'<br /><b>'.ucfirst($member->get_priority_name()).'</b></td>
					<td class="r">'.ess::$b->date->get($member->data['up_last_online'])->format().'<br />'.game::timespan($member->data['up_last_online'], game::TIME_ABS).'</td>
					<td>'.htmlspecialchars(game::$bydeler[$info['up_b_id']]['name']).'</td>
					<td>'.$brom.'</td>
					<td class="r">'.$helse.'</td> 
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Sette inn kuler
	 */
	protected function bullets_in()
	{
		if (!login::$user->player->weapon) redirect::handle();
		$num = (int) postval("bullets_in", 0);
		if ($num <= 0) redirect::handle();
		
		$ret = $this->ff->bullets_in($num, login::$user->player);
		switch ($ret)
		{
			case "missing": ess::$b->page->add_message("Du har ikke så mange kuler.", "error"); break;
			case "full": ess::$b->page->add_message("Det er ikke plass til så mange kuler i broderskapet.", "error"); break;
			default: ess::$b->page->add_message("Du satt inn ".fwords("%d kule", "%d kuler", $num)." i kulelageret til broderskapet."); redirect::handle();
		}
	}
	
	/**
	 * Ta ut kuler
	 */
	protected function bullets_out()
	{
		if (!login::$user->player->weapon) redirect::handle();
		$num = (int) postval("bullets_out", 0);
		if ($num <= 0) redirect::handle();
		
		// på vegne av en spiller?
		$up = login::$user->player;
		$real_up = null;
		
		if ($this->ff->uinfo->data['ffm_priority'] != 4 && !empty($_POST['bullets_up']))
		{
			// har vi ikke ansvar for denne spilleren?
			$id = postval("bullets_up");
			if (!isset($this->ff->members['members'][$id])
				|| ($this->ff->uinfo->data['ffm_priority'] == 3 && $this->ff->members['members'][$id]->data['ffm_parent_up_id'] != login::$user->player->id)
				|| ($this->ff->uinfo->data['ffm_priority'] < 3 && $this->ff->members['members'][$id]->data['ffm_priority'] != 4))
			{
				ess::$b->page->add_message("Ugyldig spillervalg.", "error");
				redirect::handle();
			}
			
			$real_up = $up;
			$up = player::get($id);
			
			// har ikke våpen?
			if (!$up->weapon)
			{
				ess::$b->page->add_message('Spilleren <user id="'.$up->id.'" /> har ikke noe våpen og har derfor ikke plass til noen kuler.', "error");
				redirect::handle();
			}
		}
		
		$ret = $this->ff->bullets_out($num, $up, $real_up);
		switch ($ret)
		{
			case "missing": ess::$b->page->add_message("Det er ikke så mange kuler i broderskapet.", "error"); break;
			case "full":
				if ($real_up)
				{
					$f = max(0, $up->weapon->data['bullets']-$up->data['up_weapon_bullets']-$up->data['up_weapon_bullets_auksjon']);
					ess::$b->page->add_message('<user id="'.$up->id.'" /> har '.($f == 0 ? 'ikke plass til flere kuler' : 'bare plass til '.fwords("%d kule til", "%d kuler til", $f)).'.', "error");
				}
				else ess::$b->page->add_message("Du har ikke plass til så mange kuler.", "error");
			break;
			default:
				if ($real_up) ess::$b->page->add_message("Du gav ".fwords("%d kule", "%d kuler", $num).' til <user id="'.$up->id.'" /> fra kulelageret til broderskapet.');
				else ess::$b->page->add_message("Du tok ut ".fwords("%d kule", "%d kuler", $num)." fra kulelageret til broderskapet.");
				redirect::handle();
		}
	}
}