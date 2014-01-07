<?php

class page_banken extends pages_player
{
	/**
	 * Startkapital ved opprettelse av første bankkontoen
	 */
	const STARTKAPITAL = 200000;
	
	/**
	 * Informasjon om banken man har
	 * @var page_banken_bank
	 */
	protected $bank;
	
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		ess::$b->page->add_title("Banken");
		$this->page_handle();
		
		ess::$b->page->load();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function page_handle()
	{
		redirect::store("/banken", redirect::ROOT);
		
		// hent inn bankinfo
		$this->bank = page_banken_bank::get($this->up->data['up_bank_ff_id']);
		
		// må vi velge en bankkonto?
		if (!$this->bank)
		{
			$this->bank_set();
		}
		
		// har vi ikke noe bankpassord?
		if (!$this->up->user->data['u_bank_auth'])
		{
			$this->auth_create();
		}
		
		// kontroller at vi er logget inn i banken
		$this->auth_verify();
		
		// logge ut?
		if (isset($_GET['logout']) && !isset(login::$extended_access['authed']))
		{
			login::data_set("banken_last_view", 0);
			ess::$b->page->add_message("Du er nå logget ut av banken.");
			redirect::handle();
		}
		
		// endre bankpassord?
		if (isset($_GET['authc']))
		{
			$this->auth_change();
		}
		
		// bytte bankkonto?
		if (isset($_POST['switch']))
		{
			$this->bank_set(true);
		}
		
		// sette inn penger?
		if (isset($_POST['sett_inn']))
		{
			$this->sett_inn();
		}
		
		// ta ut penger?
		if (isset($_POST['ta_ut']))
		{
			$this->ta_ut();
		}
		
		// overføre penger?
		if (isset($_POST['mottaker']) && !isset($_POST['abort']))
		{
			$this->overfor();
		}
		
		// vis banken
		$this->show();
	}
	
	/**
	 * Velge første eller bytte bankkonto
	 */
	protected function bank_set($switch = null)
	{
		// bytte bank?
		if ($switch)
		{
			// kan vi bytte bank nå?
			$expire = $this->up->data['up_bank_ff_time'] + 604800;
			if ($expire > time())
			{
				if (access::is_nostat())
				{
					ess::$b->page->add_message("Du må egentlig vente til ".ess::$b->date->get($expire)->format(date::FORMAT_SEC)." før du kan bytte bank, men som nostat kan du bytte når som helst.");
				}
				else
				{
					ess::$b->page->add_message("Du må vente til ".ess::$b->date->get($expire)->format(date::FORMAT_SEC)." før du kan bytte bank på nytt.", "error");
					redirect::handle();
				}
			}
			
			// avbryte?
			if (isset($_POST['abort'])) redirect::handle();
		}
		
		// har vi valgt en bank?
		if (isset($_POST['apply']) && ($switch || isset($_POST['bank_first'])))
		{
			if (!isset($_POST['ff_id']))
			{
				ess::$b->page->add_message("Du må velge en bank.", "error");
			}
			
			else
			{
				$ff_id = intval($_POST['ff_id']);
				
				// hent bank
				$bank = page_banken_bank::get($ff_id, true);
				
				// fant ikke?
				if (!$bank)
				{
					ess::$b->page->add_message("Fant ikke banken.", "error");
				}
				
				else
				{
					$more_fields = '';
					$more_info = '';
					
					// er dette vår første konto? startkapital!
					if (!$switch && $this->up->data['up_bank_ff_id'] === NULL && self::STARTKAPITAL)
					{
						$this->up->data['up_bank'] = bcadd($this->up->data['up_bank'], self::STARTKAPITAL);
						$more_fields .= ', up_bank = up_bank + '.self::STARTKAPITAL;
						$more_info .= '<br />Du mottok <b>'.game::format_cash(self::STARTKAPITAL).'</b> i startkapital.';
					}
					
					\Kofradia\DB::get()->exec("UPDATE users_players SET up_bank_ff_id = $ff_id, up_bank_ff_time = ".time()."$more_fields WHERE up_id = ".$this->up->id);
					ess::$b->page->add_message("Du har opprettet en bankkonto hos firmaet <b>".htmlspecialchars($bank->data['ff_name'])."</b> og har nå <b>".game::format_cash($this->up->data['up_bank'])."</b> i din bankkonto.$more_info");
					
					redirect::handle();
				}
			}
		}
		
		// hent bankene
		$result = \Kofradia\DB::get()->query("
			SELECT ff_id, ff_date_reg, ff_bank, ff_name, ff_is_crew, ff_params, br_b_id
			FROM ff
				LEFT JOIN bydeler_resources ON ff_br_id = br_id
			WHERE ff_type = 3 AND ff_inactive = 0
			ORDER BY ff_name");
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">'.($switch ? 'Bytte bank' : 'Opprett bankkonto').'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<form action="" method="post">'.($switch ? '
			<p>Mellom hver gang du bytter bank må det gå én uke.</p>' : '
			<p>Det ser ikke ut til at du eier noen bankkonto! Derfor må du velge en bank du ønsker å opprette konto i.</p>'.($this->up->data['up_bank'] > 0 ? '
			<p>Du har sannsynligvis hatt en bankkonto tidligere, da du vil få '.game::format_cash($this->up->data['up_bank']).' tilgjengelig etter du oppretter bankkontoen.' : '')).'
			<input type="hidden" name="'.($switch ? 'switch' : 'bank_first').'" />
			<table class="table center tablemt" width="100%">
				<thead>
					<tr>
						<th>Bydel</th>
						<th>Bank</th>
						<th>Overføringstap</th>
					</tr>
				</thead>
				<tbody>';
		
		// sett opp listen
		$i = 0;
		while ($row = $result->fetch())
		{
			// bydel
			if (!isset(game::$bydeler[$row['br_b_id']]))
			{
				$bydel = '<span class="dark">Ukjent</span>';
			}
			else
			{
				$bydel = htmlspecialchars(game::$bydeler[$row['br_b_id']]['name']);
			}
			
			// crew?
			if ($row['ff_is_crew'] == 1)
			{
				// har vi tilgang? (kun nostat)
				if (!access::is_nostat()) continue;
				$bydel .= ' <span class="dark">(Crew)</span>';
			}
			
			// den vi har?
			$active = $switch && $row['ff_id'] == $this->bank->id;
			if ($active)
			{
				$bydel .= ' <span class="dark">(Nåværende)</span>';
			}
			
			$params = new params($row['ff_params']);
			$overforing_tap = (float) $params->get("bank_overforing_tap", 0) * 100;
			
			echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="ff_id" value="'.$row['ff_id'].'"'.($active ? ' checked="checked" disabled="disabled"' : '').' /> '.$bydel.'</td>
						<td><a href="ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></td>
						<td class="r">'.$overforing_tap.' %</td>
					</tr>';
		}
		
		echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton($switch ? "Bytt bank" : "Opprett konto", 'name="apply"').($switch ? ' '.show_sbutton("Avbryt", 'name="abort"') : '').'</p>
		</form>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Opprette passord for banken
	 */
	protected function auth_create()
	{
		// lagre passord?
		if (isset($_POST['passord_1']) && isset($_POST['passord_2']))
		{
			$pass1 = postval("passord_1");
			$pass2 = postval("passord_2");
			
			$error = password::validate($pass1, password::LEVEL_LOGIN);
			
			if ($pass1 != $pass2)
			{
				ess::$b->page->add_message("Passordene var ikke like.", "error");
			}
			
			elseif (mb_strlen($pass1) < 6)
			{
				ess::$b->page->add_message("Passordet må inneholde 6 eller flere tegn.", "error");
			}
			
			// for lett passord?
			elseif ($error > 0)
			{
				ess::$b->page->add_message("Du må velge et vanskeligere passord.", "error");
			}
			
			else
			{
				// lag hash
				$hash = password::hash($pass1, null, "bank_auth");
				
				// samme som brukerpassordet?
				if (password::verify_hash($pass1, $this->up->user->data['u_pass'], "user"))
				{
					ess::$b->page->add_message("Bankpassordet kan ikke være det samme som passordet til brukerkontoen.", "error");
				}
				
				else
				{
					// bytt passordet
					\Kofradia\DB::get()->exec("UPDATE users SET u_bank_auth = ".\Kofradia\DB::quote($hash)." WHERE u_id = ".$this->up->user->id);
					
					ess::$b->page->add_message("Ditt bankpassord er nå opprettet.");
					login::data_set("banken_last_view", time());
					redirect::handle();
				}
			}
		}
		
		ess::$b->page->add_js_domready('$("passnew").focus();');
		
		// vis formen
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Banken<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<boxes />
		<p>For å få tilgang til banken må du opprette et passord som kun brukes for å få tilgang til banken.</p>
		<p>Dette er for å sikre at uvedkommende ikke skal få tilgang til dine penger selv om de kommer inn på kontoen din.</p>
		<h2 class="bg1">Opprett bank passord<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1">
			<form action="" method="post">
				<dl class="dd_right dl_2x">
					<dt>Passord</dt>
					<dd><input type="password" class="styled w100" name="passord_1" id="passnew" /></dd>
					
					<dt>Gjenta passord</dt>
					<dd><input type="password" class="styled w100" name="passord_2" /></dd>
				</dl>
				<p class="c">'.show_sbutton("Opprett passord").'</p>
			</form>
		</div>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Bytte passord for banken
	 */
	protected function auth_change()
	{
		// lagre passord?
		if (isset($_POST['passord_old']) && isset($_POST['passord_1']) && isset($_POST['passord_2']))
		{
			$pass0 = postval("passord_old");
			$pass1 = postval("passord_1");
			$pass2 = postval("passord_2");
			
			$hash = password::hash($pass1, null, "bank_auth");
			
			// kontroller det gamle passord
			if (isset(login::$extended_access['authed']) && !password::verify_hash($pass0, $this->up->user->data['u_bank_auth'], "bank_auth"))
			{
				ess::$b->page->add_message("Det gamle passordet var ikke korrekt.", "error");
			}
			
			else
			{
				$error = password::validate($pass1, password::LEVEL_LOGIN);
				
				if ($pass1 != $pass2)
				{
					ess::$b->page->add_message("De nye passordene var ikke like.", "error");
				}
				
				elseif (mb_strlen($pass1) < 6)
				{
					ess::$b->page->add_message("Det nye passordet må inneholde 6 eller flere tegn.", "error");
				}
				
				// for lett passord?
				elseif ($error > 0)
				{
					ess::$b->page->add_message("Du må velge et vanskeligere passord.", "error");
				}
				
				else
				{
					// samme som nåværende?
					if ($pass0 == $pass1)
					{
						ess::$b->page->add_message("Passordene var identisk med det forrige passordet. Du må velge et nytt passord.", "error");
					}
					
					// samme som brukerpassordet?
					elseif (password::verify_hash($pass1, $this->up->user->data['u_pass'], "user"))
					{
						ess::$b->page->add_message("Bankpassordet kan ikke være det samme som passordet til brukerkontoen.", "error");
					}
					
					else
					{
						// bytt passordet
						\Kofradia\DB::get()->exec("UPDATE users SET u_bank_auth = ".\Kofradia\DB::quote($hash)." WHERE u_id = ".$this->up->user->id);
						
						ess::$b->page->add_message("Du endret ditt bankpassord.");
						redirect::handle();
					}
				}
			}
		}
		
		ess::$b->page->add_js_domready('$("passold").focus();');
		
		// vis formen
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Endre bankpassord<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<boxes />
		<p class="c"><a href="banken">Tilbake</a></p>
		<form action="" method="post">'.(isset(login::$extended_access['authed']) ? '
			<p>Du er logget inn med utvidere tilganger. Dette passordet brukes kun hvis du er logget ut av utvidere tilganger.</p>' : '').'
			<dl class="dd_right dl_2x">'.(!isset(login::$extended_access['authed']) ? '
				<dt>Nåværende passord</dt>
				<dd><input type="password" class="styled w100" name="passord_old" id="passold" /></dd>
				' : '').'
				<dt>Nytt Passord</dt>
				<dd><input type="password" class="styled w100" name="passord_1" /></dd>
				
				<dt>Gjenta nytt passord</dt>
				<dd><input type="password" class="styled w100" name="passord_2" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Endre passord").'</p>
		</form>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Kontroller at vi er logget inn i banken
	 */
	protected function auth_verify()
	{
		// alltid logget inn i banken når man er logget inn som crew
		if (isset(login::$extended_access['authed'])) return;
		
		// sjekk om vi er logget inn i banken
		$last = login::data_get("banken_last_view", 0);
		$idle = 1800; // hvor lenge vi kan være inaktiv
		$exceed = max(0, time() - $last - $idle);
		
		// allerede logget inn?
		if ($last != 0 && $exceed == 0)
		{
			login::data_set("banken_last_view", time());
			return;
		}
		
		// logge inn?
		if (isset($_POST['passord']))
		{
			// sjekk passord
			if (!password::verify_hash($_POST['passord'], $this->up->user->data['u_bank_auth'], "bank_auth"))
			{
				ess::$b->page->add_message("Passordet var ikke riktig. Husk at dette er bank passordet og ikke passordet til brukerkontoen.", "error");
				putlog("ABUSE", "%c4%bUGYLDIG PASSORD I BANKEN:%b%c %u".$this->up->data['up_name']."%u ({$_SERVER['REMOTE_ADDR']}) brukte feil passord for å logge inn i banken");
			}
			
			else
			{
				// logget inn
				login::data_set("banken_last_view", time());
				ess::$b->page->add_message("Du er nå logget inn i banken. Du blir logget ut etter ".game::timespan($idle, game::TIME_FULL)." uten å besøke banken.");
			}
			
			redirect::handle();
		}
		
		// glemt passord?
		if (isset($_GET['rp']))
		{
			// validere?
			if (!empty($_GET['rp'])) $this->auth_reset($_GET['rp']);
			
			// be om e-post?
			if (isset($_POST['send']) && validate_sid())
			{
				$this->auth_send_link();
			}
			
			ess::$b->page->add_title("Nullstill bankpassord");
			$requested = $this->up->user->params->get("bankauth_change_rtime");
			$expire = $this->up->user->params->get("bankauth_change_expire");
			
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Nullstill bankpassord<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<boxes />
		<p>For å nullstille passordet til banken må du bekrefte din identitet via e-posten din.</p>';
			
			// allerede sendt e-post?
			if ($expire > time())
			{
				echo '
		<p>Du ba om e-post '.ess::$b->date->get($requested)->format().' for å nullstille ditt passord. Forespørselen er gyldig til '.ess::$b->date->get($expire)->format().'.</p>
		<p>Du må vente til dette klokkeslettet for å be om ny e-post.</p>';
			}
			
			else
			{
				echo '
		<form action="" method="post">
			<input type="hidden" name="rp" />
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p class="c">'.show_sbutton("Send e-post", 'name="send"').'</p>
		</form>';
			}
			
			echo '
		<p class="c"><a href="banken">Tilbake</a></p>
	</div>
</div>';
			
			ess::$b->page->load();
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">
		Banken
		<span class="left"></span><span class="right"></span>
	</h1>
	<div class="bg1">
		<boxes />';
		
		if ($exceed > 0 && $last != 0)
		{
			login::data_set("banken_last_view", 0);
			echo '
		<p>Det gikk for lang tid siden du viste banken og du må logge inn på nytt. Du var '.game::timespan($exceed, game::TIME_FULL).' over tiden.</p>';
		}
		
		// javascript for fokus til passord feltet
		ess::$b->page->add_body_post('<script type="text/javascript">
document.getElementById("b_pass").focus();
</script>');
		
		echo '
		<p>Du må logge inn for å få tilgang til bankkontoen din.</p>
		<p>Denne sikkerheten er her for å hindre uvedkommende i å kvitte seg med pengene dine, selv om de kommer inn på spilleren din.</p>
		<form action="" method="post">
			<dl class="dd_right dl_2x">
				<dt>Bankpassord</dt>
				<dd><input type="password" class="styled w100" name="passord" id="b_pass" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Logg inn").'</p>
			<p class="c"><a href="banken?rp">Nullstill bankpassord</a></p>
		</form>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Send lenke for å endre bankpassordet
	 */
	protected function auth_send_link()
	{
		// er det noen aktive nå?
		$expire = $this->up->user->params->get("bankauth_change_expire");
		if ($expire > time())
		{
			ess::$b->page->add_message("Du må vente ".game::timespan($expire, game::TIME_ABS | game::TIME_FULL)." før du kan be om ny e-post for å bytte bankpassordet ditt.", "error");
			redirect::handle();
		}
		
		// nøkkelen
		$hash = mb_substr(md5(uniqid("", true)), 0, 10);
		
		// gyldig i 30 minutter
		$expire = time() + 3600;
		
		// send e-post
		$email = new email();
		$email->text = 'Hei,

For å bytte ditt bankpassord for spilleren '.$this->up->data['up_name'].' må du åpne denne adressen:
'.ess::$s['path'].'/banken?rp='.$hash.'

--
www.kofradia.no';
		$email->headers['X-SMafia-IP'] = $_SERVER['REMOTE_ADDR'];
		$email->headers['Reply-To'] = "henvendelse@smafia.no";
		$email->send($this->up->user->data['u_email'], "Skifte bankpassord");
		
		// lagre
		$this->up->user->params->update("bankauth_change_expire", $expire);
		$this->up->user->params->update("bankauth_change_rtime", time());
		$this->up->user->params->update("bankauth_change_hash", $hash, true);
		
		ess::$b->page->add_message("E-post for bekreftelse er sendt til <b>".htmlspecialchars($this->up->user->data['u_email'])."</b>.");
		redirect::handle();
	}
	
	/**
	 * Nullstille bankpassordet
	 */
	protected function auth_reset($hash)
	{
		// har vi ikke bedt om å bytte passord?
		if (!$this->up->user->params->exists("bankauth_change_hash"))
		{
			ess::$b->page->add_message("Du har ikke bedt om å bytte passordet i banken din.", "error");
			redirect::handle();
		}
		
		// feil hash?
		if ($hash !== $this->up->user->params->get("bankauth_change_hash"))
		{
			ess::$b->page->add_message("Ugyldig nøkkel.", "error");
			redirect::handle();
		}
		
		$expire = $this->up->user->params->get("bankauth_change_expire");
		
		// brukt for lang tid?
		if ($expire < time())
		{
			ess::$b->page->add_message("Du brukte for lang tid fra vi sendte deg e-posten. Be om ny e-post.", "error");
			
			$this->up->user->params->remove("bankauth_change_expire");
			$this->up->user->params->remove("bankauth_change_rtime");
			$this->up->user->params->remove("bankauth_change_hash", true);
			
			redirect::handle();
		}
		
		// fjern nåværende passord
		$this->up->user->params->remove("bankauth_change_expire");
		$this->up->user->params->remove("bankauth_change_rtime");
		$this->up->user->params->remove("bankauth_change_hash");
		\Kofradia\DB::get()->exec("UPDATE users SET u_bank_auth = NULL WHERE u_id = {$this->up->user->id}");
		$this->up->user->params->commit();
		
		putlog("NOTICE", "NULLSTILLE BANKPASSORD: {$this->up->data['up_name']} nullstilte sitt bankpassord.");
		
		ess::$b->page->add_message("Ditt bankpassord er nå nullstilt og du må opprette nytt passord.");
		redirect::handle();
	}
	
	/**
	 * Sette inn penger
	 */
	protected function sett_inn()
	{
		$amount = game::intval($_POST['sett_inn']);
		
		// negativt?
		if ($amount < 0)
		{
			ess::$b->page->add_message("Ugyldig beløp!", "error");
		}
		
		// mer enn det vi har?
		elseif ($amount > $this->up->data['up_cash'])
		{
			ess::$b->page->add_message("Du har ikke så mye penger på hånda!", "error");
		}
		
		// okay
		elseif ($amount != 0)
		{
			// sett inn
			$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $amount, up_bank = up_bank + $amount WHERE up_id = ".$this->up->id." AND up_cash >= $amount");
			if ($a == 0)
			{
				// mislykket
				ess::$b->page->add_message("Du har ikke så mye penger på hånda!", "error");
			}
			else
			{
				ess::$b->page->add_message("Du satt inn ".game::format_cash($amount)." på bankkontoen.");
				putlog("INT", "BANK SETT INN: (".$this->up->data['up_name'].") satt inn (".game::format_cash($amount).") (før handling: kontant: ".game::format_cash($this->up->data['up_cash'])."; bank: ".game::format_cash($this->up->data['up_bank']).")!");
			}
		}
		
		redirect::handle();
	}
	
	/**
	 * Ta ut penger
	 */
	protected function ta_ut()
	{
		$amount = game::intval($_POST['ta_ut']);
		
		// negativt?
		if ($amount < 0)
		{
			ess::$b->page->add_message("Ugyldig beløp!", "error");
		}
		
		// mer enn det vi har?
		elseif ($amount > $this->up->data['up_bank'])
		{
			ess::$b->page->add_message("Du har ikke så mye penger i banken!", "error");
		}
		
		// okay
		elseif ($amount != 0)
		{
			// sett inn
			$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + $amount, up_bank = up_bank - $amount WHERE up_id = ".$this->up->id." AND up_bank >= $amount");
			if ($a == 0)
			{
				// mislykket
				ess::$b->page->add_message("Du har ikke så mye penger i banken!", "error");
			}
			else
			{
				ess::$b->page->add_message("Du tok ut ".game::format_cash($amount)." fra bankkontoen.");
				putlog("INT", "BANK TA UT: (".$this->up->data['up_name'].") tok ut (".game::format_cash($amount).") (før handling: kontant: ".game::format_cash($this->up->data['up_cash'])."; bank: ".game::format_cash($this->up->data['up_bank']).")!");
			}
		}
		
		redirect::handle();
	}
	
	/**
	 * Overføre penger
	 */
	protected function overfor()
	{
		$mottaker = postval("mottaker");
		$amount = game::intval(postval("amount"));
		
		// kontroller at vi har nok penger
		$result = \Kofradia\DB::get()->query("SELECT $amount <= up_bank FROM users_players WHERE up_id = ".$this->up->id);
		$amount_ok = $result->fetchColumn(0) == 1;
		
		// sjekk beløpet
		if ($amount <= 0)
		{
			ess::$b->page->add_message("Ugyldig beløp.", "error");
			return;
		}
		
		if ($amount < 50)
		{
			ess::$b->page->add_message("Du må sende minimum 50 kr.", "error");
			return;
		}
		
		if (!$amount_ok)
		{
			ess::$b->page->add_message("Du har ikke så mye penger i banken.", "error");
			return;
		}
		
		// har vi ikke tilgang (NoStatUser)
		if (access::is_nostat() && !access::has("admin"))
		{
			ess::$b->page->add_message("Du er NoStatUser og kan ikke sende penger!", "error");
			return;
		}
		
		// sjekk session
		if (postval("sid") != login::$info['ses_id'])
		{
			ess::$b->page->add_message("Startet du ikke overføringen selv? :o", "error");
			return;
		}
		
		
		// sjekk mottaker
		$result = \Kofradia\DB::get()->query("SELECT up_id, up_u_id, up_name, up_access_level, up_bank_ff_id FROM users_players WHERE up_name = ".\Kofradia\DB::quote($mottaker)." ORDER BY up_access_level = 0, up_last_online DESC LIMIT 1");
		$player = $result->fetch();
		
		// ingen gyldig mottaker?
		if (!$player)
		{
			ess::$b->page->add_message("Fant ikke mottakeren.", "error");
			return;
		}
		
		// seg selv?
		if ($player['up_id'] == $this->up->id)
		{
			ess::$b->page->add_message("Du kan ikke sende til deg selv.", "error");
			return;
		}
		
		// død mottaker?
		if ($player['up_access_level'] == 0)
		{
			ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> er død. Hvem skal motta pengene?!');
			return;
		}
		
		
		$result = \Kofradia\DB::get()->query("SELECT uc_info FROM users_contacts WHERE uc_u_id = {$player['up_u_id']} AND uc_contact_up_id = ".$this->up->id." AND uc_type = 2");
		$blokkert = $result->rowCount() > 0;
		$blokkert_info = $blokkert ? $result->fetchColumn(0) : false;
		
		// sjekk bankkontoen til mottaker
		$bank = page_banken_bank::get($player['up_bank_ff_id']);
		
		// ingen bankkonto?
		if (!$bank)
		{
			ess::$b->page->add_message("Mottakeren har ingen bankkonto du kan sende til.", "error");
			return;
		}
		
		// blokkert?
		if ($blokkert && !access::has("crewet"))
		{
			// blokkert
			$reason = game::bb_to_html($blokkert_info);
			$reason = empty($reason) ? '' : ' Begrunnelse: '.$reason;
			ess::$b->page->add_message("Denne spilleren har blokkert deg, og du kan derfor ikke sende personen penger.$reason", "error");
			return;
		}
		
		$note = mb_substr(postval("note"), 0, 100);
		
		// hoppe over overføringstapet?
		$skip_bog = false;
		if (isset($_POST['skip_bog']) && access::is_nostat())
		{
			$skip_bog = true;
			$this->bank->overforingstap = 0;
			$bank->overforingstap = 0;
		}
		
		// regn ut hvor mye penger som skal bli til overs etc
		$result = \Kofradia\DB::get()->query("SELECT ROUND($amount * {$this->bank->overforingstap}), ROUND($amount * $bank->overforingstap), ROUND($amount * {$this->bank->overforingstap}) + ROUND($amount * $bank->overforingstap), $amount - ROUND($amount * {$this->bank->overforingstap}) - ROUND($amount * $bank->overforingstap), $amount - ROUND($amount * {$this->bank->overforingstap})");
		$info = $result->fetch(\PDO::FETCH_NUM);
		// 0 -> tap sender
		// 1 -> tap mottaker
		// 2 -> tap totalt
		// 3 -> til overs (det som mottakeren får)
		// 4 -> mellombeløp (utgangsbeløpet - tap sender)
		
		// kontrollere at overføringen ikke blir utført flere ganger
		$form = \Kofradia\Form::getByDomain("banken_".$player['up_id'], login::$user);
		
		// bekreftet?
		if (isset($_POST['confirm']) && isset($_POST['ovt_s']) && isset($_POST['ovt_m']) && $form->validateHashOrAlert())
		{
			// kontroller overføringstapene (slik at det ikke har skjedd noen endringer)
			$ovt_s = postval("ovt_s");
			$ovt_m = postval("ovt_m");
			
			if ($ovt_s != $this->bank->overforingstap || $ovt_m != $bank->overforingstap)
			{
				// det har endret seg
				login::data_set("banken_ovt_endret", true);
			}
			
			else
			{
				// start transaksjon
				\Kofradia\DB::get()->beginTransaction();
				
				// send pengene
				$a = \Kofradia\DB::get()->exec("UPDATE users_players AS s, users_players AS m SET s.up_bank = s.up_bank - $amount, m.up_bank = m.up_bank + {$info[3]} WHERE s.up_id = ".$this->up->id." AND m.up_id = {$player['up_id']} AND s.up_bank >= $amount");
				
				// mislykket?
				if ($a == 0)
				{
					ess::$b->page->add_message("Noe gikk galt under overføringen.", "error");
					\Kofradia\DB::get()->commit();
				}
				
				// vellykket!
				else
				{
					// lagre overføringslogg
					\Kofradia\DB::get()->exec("INSERT INTO bank_log SET bl_sender_up_id = ".$this->up->id.", bl_receiver_up_id = {$player['up_id']}, amount = {$info[4]}, time = ".time());
					
					// oppdater senderen
					\Kofradia\DB::get()->exec("UPDATE users_players SET up_bank_sent = up_bank_sent + {$info[4]}, up_bank_profit = up_bank_profit - {$info[4]}, up_bank_num_sent = up_bank_num_sent + 1, up_bank_charge = up_bank_charge + {$info[0]} WHERE up_id = ".$this->up->id);
					
					// oppdater mottakeren
					\Kofradia\DB::get()->exec("UPDATE users_players SET up_bank_received = up_bank_received + {$info[4]}, up_bank_profit = up_bank_profit + {$info[4]}, up_bank_num_received = up_bank_num_received + 1, up_bank_charge = up_bank_charge + {$info[1]} WHERE up_id = {$player['up_id']}");
					
					// spillelogg (med melding)
					$player2 = new player($player['up_id']);
					$player2->add_log("bankoverforing", $info[4].":".$note, $this->up->id);
					
					// legg til transaksjonsrader
					if ($info[0] > 0) \Kofradia\DB::get()->exec("INSERT INTO ff_bank_transactions SET ffbt_ff_id = {$this->bank->id}, ffbt_time = ".time().", ffbt_amount = $amount, ffbt_profit = {$info[0]}");
					if ($info[1] > 0) \Kofradia\DB::get()->exec("INSERT INTO ff_bank_transactions SET ffbt_ff_id = {$bank->id}, ffbt_time = ".time().", ffbt_amount = $amount, ffbt_profit = {$info[1]}");
					
					// IRC logg
					putlog("LOG", "%c9%uBANKOVERFØRING:%u%c (%u".$this->up->data['up_name']."%u) sendte (%u".game::format_cash($amount)."%u (%u{$info[3]}%u)) til (%u{$player['up_name']}%u) (TAP: ".game::format_cash($info[2]).") ".(!empty($note) ? 'Melding: ('.$note.')' : 'Ingen melding.'));
					
					ess::$b->page->add_message('Du overførte <b>'.game::format_cash($info[4]).'</b> til <user id="'.$player['up_id'].'" />.' . ($info[0] > 0 ? ' Banken din tok <b>'.game::format_cash($info[0]).'</b> i overføringsgebyr.' : ''));
					\Kofradia\DB::get()->commit();
					
					// trigger
					$this->up->update_money(-$amount, false, false, null);
					$player2->update_money($info[3], false, false, null);
					
					redirect::handle();
				}
			}
		}
		
		ess::$b->page->add_css('.dl_bank dd { text-align: right }');
		
		// vis godkjenn form
		echo '
<h1>Banken - overføring</h1>
<form action="" method="post">
	<input type="hidden" name="mottaker" value="'.htmlspecialchars($mottaker).'" />
	<input type="hidden" name="amount" value="'.$amount.'" />
	<input type="hidden" name="note" value="'.htmlspecialchars($note).'" />
	<input type="hidden" name="ovt_s" value="'.$this->bank->overforingstap.'" />
	<input type="hidden" name="ovt_m" value="'.$bank->overforingstap.'" />
	'.$form->getHTMLInput().'
		
		// hoppe over overføringstapet?
		if ($skip_bog)
		{
			echo '
	<input type="hidden" name="skip_bog" />';
		}
		
		echo '
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<div style="width: 200px; padding-left: 100px; float: left">
		<div class="section">
			<h2>Avsender</h2>
			<dl class="dl_30">
				<dt>Kontoeier</dt>
				<dd>'.game::profile_link().'</dd>
				
				<dt>Bankfirma</dt>
				<dd><a href="ff/?ff_id='.$this->bank->id.'">'.htmlspecialchars($this->bank->data['ff_name']).'</a></dd>
				
				<dt><abbr title="Overføringstap">Overf.tap</abbr></dt>
				<dd>'.($this->bank->overforingstap * 100).' %</dd>
				
				<dt>Plassering</dt>
				<dd>'.(!isset(game::$bydeler[$this->bank->data['br_b_id']]) ? '<span style="color: #777777">Ukjent</span>' : htmlspecialchars(game::$bydeler[$this->bank->data['br_b_id']]['name'])).'</dd>
			</dl>
		</div>
	</div>
	<div style="width: 200px; padding-left: 20px; float: left">
		<div class="section">
			<h2>Mottaker</h2>
			<dl class="dl_30">
				<dt>Kontoeier</dt>
				<dd><user id="'.$player['up_id'].'" /></dd>
				
				<dt>Bankfirma</dt>
				<dd><a href="ff/?ff_id='.$bank->id.'">'.htmlspecialchars($bank->data['ff_name']).'</a></dd>
				
				<dt><abbr title="Overføringstap">Overf.tap</abbr></dt>
				<dd>'.($bank->overforingstap * 100).' %</dd>
				
				<dt>Plassering</dt>
				<dd>'.(!isset(game::$bydeler[$bank->data['br_b_id']]) ? '<span style="color: #777777">Ukjent</span>' : htmlspecialchars(game::$bydeler[$bank->data['br_b_id']]['name'])).'</dd>
			</dl>
		</div>
	</div>
	<div class="clear" style="width: 420px; margin-left: 100px">
		<div class="section">
			<h2>Overføringsinformasjon</h2>
			<dl class="dl_40 dl_bank">
				<dt>Overføringsbeløp</dt>
				<dd>'.game::format_cash($amount).'</dd>';
		
		// hopper over overføringstapet?
		if ($skip_bog)
		{
			echo '
				
				<dt>Hopper over overføringstapet</dt>
				<dd>NoStat</dd>';
		}
		
		echo '
				
				<dt>Overføringstap for avsender</dt>
				<dd>'.game::format_cash($info[0]).'</dd>
				
				<dt>Overføringstap for mottaker</dt>
				<dd>'.game::format_cash($info[1]).'</dd>
				
				<dt>Mottaker får</dt>
				<dd>'.game::format_cash($info[3]).'</dd>
				
				<dt>Melding</dt>
				<dd>'.(empty($note) ? 'Ingen melding.' : game::bb_to_html($note)).'</dd>
			</dl>
			<h4>
				'.show_sbutton("Utfør overføring", 'name="confirm"').'
				'.show_sbutton("Avbryt/endre", 'name="abort"').'
			</h4>
		</div>
	</div>
</form>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis banken
	 */
	protected function show()
	{
		ess::$b->page->add_js('
var user_bank = '.js_encode(game::format_cash($this->up->data['up_bank'])).';
var user_cash = '.js_encode(game::format_cash($this->up->data['up_cash'])).';');
		
		ess::$b->page->add_js_domready('
	$$(".bank_amount_set").each(function(elm)
	{
		var amount = elm.get("rel").substring(0, 4) == "bank" ? user_bank : user_cash;
		var e_id = elm.get("rel").substring(5);
		elm
			.appendText(" (")
			.grab(new Element("a", {"text":"alt"}).addEvent("click", function()
			{
				$(e_id).set("value", amount);
			}))
			.appendText(")");
	});');
		
		echo '
<div class="bg1_c small" style="width: 420px">
	<h1 class="bg1">
		Banken
		<span class="left"></span><span class="right"></span>
	</h1>
	<p class="h_left">
		<a href="'.ess::$s['rpath'].'/node/31">Hjelp</a>
	</p>
	<p class="h_right">'.(!isset(login::$extended_access['authed']) ? '
		<a href="banken?logout">Logg ut av banken</a>' : '').'
		<a href="banken?authc">Endre pass</a>
	</p>
	<div class="bg1" style="padding: 0 15px">
		<!-- bankkonto informasjon -->
		<div style="width: 50%; margin-left: -5px; float: left">
			<h2 class="bg1">Bankkonto informasjon<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">
				<dl class="dd_right">
					<dt>Kontoeier</dt>
					<dd>'.game::profile_link().'</dd>
					<dt>Bankfirma</dt>
					<dd><a href="ff/?ff_id='.$this->bank->id.'">'.htmlspecialchars($this->bank->data['ff_name']).'</a></dd>
					<dt><abbr title="Overføringstap">Overf.tap</abbr></dt>
					<dd>'.($this->bank->overforingstap * 100).' %</dd>
					<dt>Plassering</dt>
					<dd>'.(!isset(game::$bydeler[$this->bank->data['br_b_id']]) ? '<span style="color: #777777">Ukjent</span>' : htmlspecialchars(game::$bydeler[$this->bank->data['br_b_id']]['name'])).'</dd>
					<dt>Balanse</dt>
					<dd>'.game::format_cash($this->up->data['up_bank']).'</dd>
				</dl>
				<p class="c">
					<a href="javascript:void(0)" onclick="this.parentNode.style.display=\'none\'; document.getElementById(\'bank_stats\').style.display=\'block\'">Vis statistikk</a>
				</p>
				<div id="bank_stats" style="display: none">
					<dl class="dd_right">
						<dt>Sendt</dt>
						<dd>'.game::format_number($this->up->data['up_bank_num_sent']).' stk</dd>
						<dd>'.game::format_cash($this->up->data['up_bank_sent']).'</dd>
					</dl>
					<dl class="dd_right">
						<dt>Mottatt</dt>
						<dd>'.game::format_number($this->up->data['up_bank_num_received']).' stk</dd>
						<dd>'.game::format_cash($this->up->data['up_bank_received']).'</dd>
					</dl>
					<dl class="dd_right">
						<dt>Overskudd</dt>
						<dd>'.game::format_cash($this->up->data['up_bank_profit']).'</dd>
					</dl>
					<dl class="dd_right">
						<dt><abbr title="Overføringstap">Overf.tap</abbr></dt>
						<dd>'.game::format_cash($this->up->data['up_bank_charge']).'</dd>
					</dl>
					<dl class="dd_right">
						<dt>Renter</dt>
						<dd>'.game::format_number($this->up->data['up_interest_num']).' stk</dd>
						<dd>'.game::format_cash($this->up->data['up_interest_total']).'</dd>
					</dl>
				</div>
				<form action="" method="post">
					<p class="c">'.show_sbutton("Bytt bank", 'name="switch"').'</p>
				</form>
			</div>
		</div>
		
		<!-- send penger -->
		<div style="width: 50%; margin-right: -5px; float: right">
			<h2 class="bg1">Send penger<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">
				<form action="" method="post">
					<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
					<input type="hidden" name="a" value="send" />
					<dl class="dd_right dl_2x">
						<dt>Mottaker</dt>
						<dd><input type="text" name="mottaker" value="'.htmlspecialchars(postval("mottaker")).'" class="styled w100" /></dd>
		
						<dt>Kontakt?</dt>
						<dd>
							<select onchange="if(this.value==\'\')var name=prompt(\'Brukernavn?\');else var name=this.value;if(name)document.getElementsByName(\'mottaker\')[0].value=name;this.selectedIndex=0" style="width: 110px; overflow: hidden">
								<option>Velg kontakt</option>';
		
		foreach (login::$info['contacts'][1] as $row)
		{
			echo '
								<option value="'.htmlspecialchars($row['up_name']).'">'.htmlspecialchars($row['up_name']).'</option>';
		}
		
		echo '
								<option value="">Egendefinert..</option>
							</select>
						</dd>
		
						<dt class="bank_amount_set" rel="bank,transf_amount">Beløp</dt>
						<dd><input type="text" id="transf_amount" name="amount" class="styled w100" value="'.game::format_cash(postval("amount", 0)).'" /></dd>
		
						<dt>Melding?</dt>
						<dd><input type="text" name="note" value="'.htmlspecialchars(postval("note")).'" class="styled w100" maxlength="100" /></dd>';
		
		// hoppe over overføringsgebyret?
		if (access::is_nostat())
		{
			echo '
						<dt>Uten gebyr?</dt>
						<dd><input type="checkbox" name="skip_bog"'.(isset($_POST['skip_bog']) ? ' checked="checked"' : '').' /></dd>';
		}
		
		echo '
					</dl>
					<p class="c">'.show_sbutton("Fortsett").'</p>
				</form>
			</div>
		</div>
		<div class="clear"></div>
		
		<!-- sett inn penger -->
		<div style="width: 50%; margin-left: -5px; float: left">
			<h2 class="bg1">Sett inn penger<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">
				<form action="" method="post">
					<dl class="dd_right">
						<dt class="bank_amount_set" rel="cash,bank_sett_inn">Beløp</dt>
						<dd><input type="text" name="sett_inn" id="bank_sett_inn" class="styled w100" value="0" /></dd>
					</dl>
					<p class="c">'.show_sbutton("Sett inn").'</p>
				</form>
			</div>
		</div>
		
		<!-- ta ut penger -->
		<div style="width: 50%; margin-right: -5px; float: right">
			<h2 class="bg1">Ta ut penger<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">
				<form action="" method="post">
					<dl class="dd_right">
						<dt class="bank_amount_set" rel="bank,bank_ta_ut">Beløp</dt>
						<dd><input type="text" name="ta_ut" id="bank_ta_ut" class="styled w100" value="0" /></dd>
					</dl>
					<p class="c">'.show_sbutton("Ta ut").'</p>
				</form>
			</div>
		</div>
		<div class="clear"></div>
	</div>
</div>

<div class="bg1_c large" style="margin-top: 40px">
	<h1 class="bg1">Oversikt<span class="left"></span><span class="right"></span></h1>
	<div class="bg1" style="padding: 0 15px">
		<!-- sendte penger -->
		<div style="width: 50%; margin-left: -5px; float: left">
			<h2 class="bg1">Sendte penger<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">';
		
		// sideinformasjon - hent sendte overføringer
		$pagei = new pagei(pagei::ACTIVE_GET, "side_sendte", pagei::PER_PAGE, 8, pagei::TOTAL, $this->up->data['up_bank_num_sent']);
		$result = \Kofradia\DB::get()->query("SELECT bl_receiver_up_id, amount, time FROM bank_log WHERE bl_sender_up_id = ".$this->up->id." ORDER BY time DESC LIMIT $pagei->start, $pagei->per_page");
		if ($result->rowCount() == 0)
		{
			echo '
				<p>
					Ingen sendte overføringer.
				</p>';
		}
		
		else
		{
			echo '
				<table class="table tablemt" width="100%">
					<thead>
						<tr>
							<th>Mottaker</th>
							<th>Beløp</th>
							<th>Tidspunkt</th>
						</tr>
					</thead>
					<tbody>';
			
			$i = 0;
			while ($row = $result->fetch())
			{
				$date = ess::$b->date->get($row['time']);
				
				echo '
						<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
							<td><user id="'.$row['bl_receiver_up_id'].'" /></td>
							<td class="r">'.game::format_cash($row['amount']).'</td>
							<td class="c" style="font-size: 10px">'.$date->format(date::FORMAT_NOTIME).'<br />'.$date->format("H:i:s").'</td>
						</tr>';
			}
		
			echo '
					</tbody>
				</table>
				<p class="c">'.$pagei->pagenumbers(game::address("banken", $_GET, array("side_sendte"))."#sendte", game::address("banken", $_GET, array("side_sendte"), array("side_sendte" => "_pageid_"))."#sendte").'</p>';
		}
		
		echo '
			</div>
		</div>
		
		<!-- mottatte penger -->
		<div style="width: 50%; margin-right: -5px; float: right">
			<h2 class="bg1">Mottatte penger<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">';
		
		// sideinformasjon - hent mottatte overføringer
		$pagei = new pagei(pagei::ACTIVE_GET, "side_mottatte", pagei::PER_PAGE, 8, pagei::TOTAL, $this->up->data['up_bank_num_received']);
		$result = \Kofradia\DB::get()->query("SELECT bl_sender_up_id, amount, time FROM bank_log WHERE bl_receiver_up_id = ".$this->up->id." ORDER BY time DESC LIMIT $pagei->start, $pagei->per_page");
		if ($result->rowCount() == 0)
		{
			echo '
				<p>
					Ingen mottatte overføringer.
				</p>';
		}
		
		else
		{
			echo '
				<table class="table tablemt" width="100%">
					<thead>
						<tr>
							<th>Sender</th>
							<th>Beløp</th>
							<th>Tidspunkt</th>
						</tr>
					</thead>
					<tbody>';
			
			$i = 0;
			while ($row = $result->fetch())
			{
				$date = ess::$b->date->get($row['time']);
				
				echo '
						<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
							<td><user id="'.$row['bl_sender_up_id'].'" /></td>
							<td class="r">'.game::format_cash($row['amount']).'</td>
							<td class="c" style="font-size: 10px">'.$date->format(date::FORMAT_NOTIME).'<br />'.$date->format("H:i:s").'</td>
						</tr>';
			}
			
			echo '
					</tbody>
				</table>
				<p class="c">'.$pagei->pagenumbers(game::address("banken", $_GET, array("side_mottatte"))."#mottatte", game::address("banken", $_GET, array("side_mottatte"), array("side_mottatte" => "_pageid_"))."#mottatte").'</p>';
		}
		
		echo '
			</div>
		</div>
		<div class="clear"></div>
	</div>
</div>';
	}
}

/**
 * Informasjon om en bankkonto
 */
class page_banken_bank
{
	/**
	 * FF ID
	 */
	public $id;
	
	/**
	 * Params til FF
	 * @var params
	 */
	public $params;
	
	/**
	 * Informasjon om banken
	 */
	public $data;
	
	/**
	 * Overføringstapet
	 */
	public $overforingstap;
	
	/**
	 * Hent objekt
	 * @return page_banken_bank
	 */
	public static function get($ff_id, $new = null)
	{
		$bank = new self($ff_id, $new);
		
		if (!$bank->data) return null;
		return $bank;
	}
	
	/**
	 * Construct: Hent detaljer
	 * @param int $ff_id
	 */
	public function __construct($ff_id, $new = null)
	{
		$this->id = (int) $ff_id;
		
		// hente info?
		if ($this->id)
		{
			$result = \Kofradia\DB::get()->query("
				SELECT ff_id, ff_date_reg, ff_bank, ff_name, ff_is_crew, ff_params, br_b_id
				FROM ff
					LEFT JOIN bydeler_resources ON ff_br_id = br_id
				WHERE ff_id = $this->id AND ff_type = 3 AND ff_inactive = 0");
			
			$this->data = $result->fetch();
			if ($this->data)
			{
				// ikke crew?
				if (!$this->data['ff_is_crew'] || !$new || access::is_nostat())
				{
					$this->params = new params($this->data['ff_params']);
					$this->overforingstap = (float) $this->params->get("bank_overforing_tap", 0);
				}
				
				return;
			}
		}
		
		$this->data = null;
	}
}