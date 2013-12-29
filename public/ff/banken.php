<?php

define("FORCE_HTTPS", true);
require "../base.php";

new page_ff_banken();
class page_ff_banken
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Nostat
	 */
	protected $nostat;
	
	/**
	 * Priority man må ha for å kunne sette inn/ta ut penger
	 */
	protected $priority_write;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		$this->ff->needaccess(2, "Du har ikke tilgang til denne banken.");
		$this->priority_write = $this->ff->get_bank_write_priority();
		
		if (false && !access::has("admin"))
		{
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">'.ucfirst($this->ff->type['type']).'bank stengt<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>'.ucfirst($this->ff->type['type']).'banken er stengt for å unngå distribusjon av penger. Pengenivået vil bli justert til å være ihht. verdiene ved midnatt.</p>
	</div>
</div>';
			
			$this->ff->load_page();
		}
		
		redirect::store("banken?ff_id={$this->ff->id}");
		
		ess::$b->page->add_title("Banken");
		$this->nostat = access::is_nostat() && login::$user->player->id != 1;
		
		// kontroller at vi har bankkonto
		if (!login::$user->player->user->data['u_bank_auth'])
		{
			ess::$b->page->add_message("Banken for {$this->ff->type['refobj']} benytter seg av passordet i din vanlige bank. For å få tilgang til den må du først opprette et passord. Etter du har opprettet et passord kan du gå tilbake til banken til {$this->ff->type['refobj']}.");
			redirect::handle("banken", redirect::ROOT);
		}
		
		// kontroller at vi er logget inn i banken
		$this->auth_verify();
		
		// gi/fjerne tilgang for medeier?
		if ((isset($_POST['pri2_wt']) || isset($_POST['pri2_wf'])) && validate_sid())
		{
			$this->pri2_access();
		}
		
		// vise statistikk
		if (isset($_GET['stats']))
		{
			$this->stats();
		}
		
		// sette inn penger?
		if (isset($_POST['bank_inn']) && !$this->nostat && $this->ff->access($this->priority_write))
		{
			$this->sett_inn();
		}
		
		// ta ut penger
		if (isset($_POST['bank_ut']) && !$this->nostat && $this->ff->access($this->priority_write))
		{
			$this->ta_ut();
		}
		
		// vis banken
		$this->show();
		
		$this->ff->load_page();
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
			if (!password::verify_hash($_POST['passord'], login::$user->player->user->data['u_bank_auth'], "bank_auth"))
			{
				ess::$b->page->add_message("Passordet var ikke riktig. Husk at dette er bank passordet og ikke passordet til brukerkontoen.", "error");
				putlog("ABUSE", "%c4%bUGYLDIG PASSORD I BANKEN (FF):%b%c %u".login::$user->player->data['up_name']."%u ({$_SERVER['REMOTE_ADDR']}) brukte feil passord for å logge inn i banken");
			}
			
			else
			{
				// logget inn
				login::data_set("banken_last_view", time());
				ess::$b->page->add_message("Du er nå logget inn i banken. Du blir logget ut etter ".game::timespan($idle, game::TIME_FULL)." uten å besøke banken.");
			}
			
			redirect::handle();
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
		<p>Du må logge inn for å få tilgang til banken for '.$this->ff->type['refobj'].'.</p>
		<form action="" method="post">
			<dl class="dd_right dl_2x">
				<dt>Bankpassord</dt>
				<dd><input type="password" class="styled w100" name="passord" id="b_pass" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Logg inn").'</p>
			<p class="c"><a href="'.ess::$s['relative_path'].'/banken?rp">Nullstill bankpassord</a></p>
		</form>
	</div>
</div>';
	
		$this->ff->load_page();
	}
	
	/**
	 * Gi/fjerne tilgang for medeier
	 */
	protected function pri2_access()
	{
		if (!$this->ff->bank_write_pri2_change(isset($_POST['pri2_wt'])))
		{
			ess::$b->page->add_message("Ingen endringer utført.");
		}
		
		redirect::handle();
	}
	
	/**
	 * Vis statistikk for FF
	 */
	protected function stats()
	{
		ess::$b->page->add_title("Statistikk");
		redirect::store("banken?ff_id={$this->ff->id}&stats");
		
		// nullstille?
		if (isset($_GET['reset']))
		{
			// bekreftet?
			if (isset($_POST['confirm']) && validate_sid())
			{
				$this->ff->reset_bank_stats();
				
				ess::$b->page->add_message("Statistikken ble nullstilt.");
				redirect::handle();
			}
			
			// vis skjema for å bekrefte
			echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Nullstille Statistikk<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Du er i ferd med å nullstille statistikken over pengeflyten i '.$this->ff->type['refobj'].'.</p>
		<p>Når du nullstiller statistikken vil du beholde en totaloversikt fra '.$this->ff->type['refobj'].' ble opprettet og frem til nå.</p>
		<p>Etter at statistikken blir nullstilt vil det være to oversikter, som hver viser statistikk før og etter nullstillingen.</p>
		<p>Det kan kanskje være ønskelig å ta en kopi av statistikken nå for å kunne sammenlikne senere.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p class="c">'.show_sbutton("Ja, nullstill statistikk", 'name="confirm"').'</p>
			<p class="c"><a href="banken?ff_id='.$this->ff->id.'&amp;stats">Avbryt</a></p>
		</form>
	</div>
</div>';
			
			$this->ff->load_page();
		}
		
		// har vi nullstilt?
		$reset = $this->ff->data['ff_money_reset_time'] ?: null;
		
		// sett opp data
		$stats = array(
			ff::BANK_INNSKUDD => 0,
			ff::BANK_UTTAK => 0,
			ff::BANK_DONASJON => 0,
			ff::BANK_BETALING => 0,
			ff::BANK_TILBAKEBETALING => 0,
			"in" => 0,
			"out" => 0
		);
		$stats = array(
			"before" => $stats,
			"after" => $stats
		);
		
		ess::$b->page->add_css('
.ff_bank_tot { font-weight: bold; color: #555; border-bottom: 1px solid #333333; margin-bottom: 2px; padding-bottom: 2px }
.ff_bank_profit { font-weight: bold; color: #888; border-bottom: 2px solid #333333; padding-bottom: 2px }');
		
		// hent statistikk
		$this->stats_get($stats['before'], $reset);
		$this->stats_get($stats['after'], $reset, true);
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Statistikk over pengeflyt i '.$this->ff->type['refobj'].'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';
		
		if ($reset)
		{
			echo '
		<p>Statistikken ble nullstilt '.ess::$b->date->get($reset)->format().'. Etter dette tidspunktet er følgende gjeldende:</p>';
		}
		
		$this->stats_show($stats['after']);
		
		if ($reset)
		{
			echo '
		<p>Før statistikken ble nullstilt:</p>';
			
			$this->stats_show($stats['before']);
		}
		
		echo '
		<p>Fortjenesten tar ikke med innskudd og uttak.</p>
		<p class="c"><a href="banken?ff_id='.$this->ff->id.'">Tilbake</a> | <a href="banken?ff_id='.$this->ff->id.'&amp;stats&amp;reset">Nullstill statistikk</a></p>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Hent stats
	 */
	protected function stats_get(&$stats, $reset, $is_after = null)
	{
		if (!$is_after && !$reset) return;
		
		$ff_reset = !$reset && $this->ff->data['ff_time_reset'] && !$this->ff->mod ? " AND ffbl_time > {$this->ff->data['ff_time_reset']}" : "";
		
		// hent statistikk fra bankoverføringene
		$result = \Kofradia\DB::get()->query("
			SELECT ffbl_type, SUM(ffbl_amount) sum_ffbl_amount
			FROM ff_bank_log
			WHERE ffbl_ff_id = {$this->ff->id}".($reset ? ($is_after ? " AND ffbl_time >= $reset" : " AND ffbl_time < $reset") : $ff_reset)."
			GROUP BY ffbl_type");
		while ($row = $result->fetch())
		{
			if (!isset($stats[$row['ffbl_type']])) continue;
			$stats[$row['ffbl_type']] = $row['sum_ffbl_amount'];
		}
		
		$stats['in'] = $this->ff->data['ff_money_in'.($reset && !$is_after ? '_total' : '')];
		$stats['out'] = $this->ff->data['ff_money_out'.($reset && !$is_after ? '_total' : '')];
	}
	
	/**
	 * Vis stats
	 */
	protected function stats_show($stats)
	{
		$result = \Kofradia\DB::get()->query("
			SELECT
				{$stats[ff::BANK_INNSKUDD]} + {$stats[ff::BANK_DONASJON]} + {$stats[ff::BANK_TILBAKEBETALING]} + {$stats['in']},
				{$stats[ff::BANK_UTTAK]} + {$stats[ff::BANK_BETALING]} + {$stats['out']},
				
				{$stats[ff::BANK_DONASJON]} + {$stats[ff::BANK_TILBAKEBETALING]} + {$stats['in']}
				- {$stats[ff::BANK_BETALING]} - {$stats['out']}");
		$row = $result->fetch(\PDO::FETCH_NUM);
		$totalt_in = $row[0];
		$totalt_out = $row[1];
		$totalt_profit = $row[2];
		
		echo '
		<div class="center" style="width: 80%">
			<dl class="dd_right">
				<dt class="ff_bank_tot">Totalt inn</dt>
				<dd class="ff_bank_tot">'.game::format_cash($totalt_in).'</dd>
				<dt>Innskudd</dt>
				<dd>'.game::format_cash($stats[ff::BANK_INNSKUDD]).'</dd>
				<dt>Donasjoner</dt>
				<dd>'.game::format_cash($stats[ff::BANK_DONASJON]).'</dd>
				<dt>Tilbakebetalinger</dt>
				<dd>'.game::format_cash($stats[ff::BANK_TILBAKEBETALING]).'</dd>
				<dt>Andre inntekter</dt>
				<dd>'.game::format_cash($stats['in']).'</dd>
			</dl>
			<dl class="dd_right">
				<dt class="ff_bank_tot">Totalt ut</dt>
				<dd class="ff_bank_tot">'.game::format_cash($totalt_out).'</dd>
				<dt>Uttak</dt>
				<dd>'.game::format_cash($stats[ff::BANK_UTTAK]).'</dd>
				<dt>Betalinger</dt>
				<dd>'.game::format_cash($stats[ff::BANK_BETALING]).'</dd>
				<dt>Andre kostnader</dt>
				<dd>'.game::format_cash($stats['out']).'</dd>
			</dl>
			<dl class="dd_right">
				<dt class="ff_bank_profit">Fortjeneste</dt>
				<dd class="ff_bank_profit">'.game::format_cash($totalt_profit).'</dd>
			</dl>
		</div>';
	}
	
	/**
	 * Sett inn penger i banken
	 */
	protected function sett_inn()
	{
		$amount = game::intval($_POST['bank_inn']);
		$note = \Kofradia\DB::quote(postval("note"));
		
		if ($amount < 0)
		{
			ess::$b->page->add_message("Kanskje en fordel med positivt beløp? :)", "error");
		}
		
		elseif ($amount == 0)
		{
			ess::$b->page->add_message("Skal du ikke sette inn noe du da? :(", "error");
		}
		
		elseif ($amount < 15000)
		{
			ess::$b->page->add_message("Minstebeløp på 15 000 kr!", "error");
		}
		
		else
		{
			// forsøk å sett inn
			$a = \Kofradia\DB::get()->exec("
				UPDATE ff, users_players
				SET ff_bank = ff_bank + $amount, up_cash = up_cash - $amount
				WHERE ff_id = {$this->ff->id} AND up_id = ".login::$user->player->id." AND up_cash >= $amount");
			
			// hadde ikke nok penger?
			if ($a == 0)
			{
				ess::$b->page->add_message("Du har ikke nok penger på hånda til å sette inn ".game::format_cash($amount).".", "error");
			}
			
			else
			{
				// balanse
				$result = \Kofradia\DB::get()->query("SELECT ff_bank FROM ff WHERE ff_id = {$this->ff->id}");
				$balance = $result->fetchColumn(0);
				
				// legg til logg
				\Kofradia\DB::get()->exec("INSERT INTO ff_bank_log SET ffbl_ff_id = {$this->ff->id}, ffbl_type = 1, ffbl_amount = $amount, ffbl_up_id = ".login::$user->player->id.", ffbl_time = ".time().", ffbl_balance = $balance, ffbl_note = $note");
				ess::$b->page->add_message("Du satt inn ".game::format_cash($amount).".");
			}
			
			redirect::handle();
		}
	}
	
	/**
	 * Ta ut penger fra banken
	 */
	protected function ta_ut()
	{
		$amount = game::intval($_POST['bank_ut']);
		$note = \Kofradia\DB::quote(postval("note"));
		
		if ($amount < 0)
		{
			ess::$b->page->add_message("Kanskje en fordel med positivt beløp? :)", "error");
		}
		
		elseif ($amount == 0)
		{
			ess::$b->page->add_message("Skal du ikke ta ut noe du da? :(", "error");
		}
		
		elseif ($amount < 15000)
		{
			ess::$b->page->add_message("Minstebeløp på 15 000 kr!", "error");
		}
		
		else
		{
			// forsøk å ta ut
			$a = \Kofradia\DB::get()->exec("
				UPDATE ff, users_players
				SET ff_bank = ff_bank - $amount, up_cash = up_cash + $amount
				WHERE ff_id = {$this->ff->id} AND up_id = ".login::$user->player->id." AND ff_bank >= $amount");
			
			// hadde ikke nok penger?
			if ($a == 0)
			{
				ess::$b->page->add_message("Det er ikke nok penger til å ta ut ".game::format_cash($amount).".", "error");
			}
			else
			{
				// balanse
				$result = \Kofradia\DB::get()->query("SELECT ff_bank FROM ff WHERE ff_id = {$this->ff->id}");
				$balance = $result->fetchColumn(0);
				
				// legg til logg
				\Kofradia\DB::get()->exec("INSERT INTO ff_bank_log SET ffbl_ff_id = {$this->ff->id}, ffbl_type = 2, ffbl_amount = $amount, ffbl_up_id = ".login::$user->player->id.", ffbl_time = ".time().", ffbl_balance = $balance, ffbl_note = $note");
				ess::$b->page->add_message("Du tok ut ".game::format_cash($amount).".");
			}
			
			redirect::handle();
		}
	}
	
	/**
	 * Vis banken
	 */
	protected function show()
	{
		echo '
<h1 class="c">Banken</h1>

<div class="section" style="width: 250px">
	<h2>Bankinformasjon</h2>
	<dl class="dd_right">
		<dt>Balanse</dt>
		<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
	</dl>';
		
		// tilgang til medeier
		if ($this->ff->access(1))
		{
			echo '
	<form action="" method="post">
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />';
			
			if ($this->priority_write == 1)
			{
				echo '
		<p>'.ucfirst($this->ff->type['priority'][2]).' har ikke tilgang til å sette inn/ta ut penger fra denne banken.</p>
		<p class="c">'.show_sbutton("Gi tilgang til {$this->ff->type['priority'][2]}", 'name="pri2_wt"').'</p>';
			}
			
			else
			{
				echo '
		<p>'.ucfirst($this->ff->type['priority'][2]).' <b>har</b> tilgang til å sette inn/ta ut penger fra denne banken.</p>
		<p class="c">'.show_sbutton("Fjern tilgang til {$this->ff->type['priority'][2]}", 'name="pri2_wf"').'</p>';
			}
			
			echo '
	</form>';
		}
		
		echo '
	<p class="c"><a href="banken?ff_id='.$this->ff->id.'&amp;stats">Vis statistikk over pengeflyt i '.$this->ff->type['refobj'].' &raquo;</a></p>
</div>';
		
		// kun boss kan ta ut og sette inn penger
		if ($this->ff->access($this->priority_write) && !$this->nostat)
		{
			echo '
<div style="margin: 0 auto; width: 420px">

<!-- sett inn penger -->
<div style="width: 205px; float: left; margin-right: 10px">
	<div class="section">
		<form action="" method="post">
			<h2>Sett inn penger</h2>
			<dl class="dd_right dl_2x">
				<dt>Beløp</dt>
				<dd><input type="text" name="bank_inn" class="styled w90" value="0" style="margin-right: 3px" />'.show_button("Alt", 'onclick="this.previousSibling.value=\''.game::format_cash(login::$user->player->data['up_cash']).'\'"').'
				
				<dt>Notat</dt>
				<dd><input type="text" name="note" value="" maxlength="50" class="styled w120" /></dd>
			</dl>
			<h4>'.show_sbutton("Sett inn").'</h4>
		</form>
	</div>
</div>

<!-- ta ut penger -->
<div style="width: 205px; float: left">
	<div class="section">
		<form action="" method="post">
			<h2>Ta ut penger</h2>
			<dl class="dd_right dl_2x">
				<dt>Beløp</dt>
				<dd><input type="text" name="bank_ut" class="styled w90" value="0" style="margin-right: 3px" />'.show_button("Alt", 'onclick="this.previousSibling.value=\''.game::format_cash($this->ff->data['ff_bank']).'\'"').'
				
				<dt>Notat</dt>
				<dd><input type="text" name="note" value="" maxlength="50" class="styled w120" /></dd>
			</dl>
			<h4>'.show_sbutton("Ta ut").'</h4>
		</form>
	</div>
</div>
<div class="clear"></div>

</div>';
		}
		
		echo '
<div class="fhr"></div>';
		
		$ff_reset = $this->ff->data['ff_time_reset'] && !$this->ff->mod ? " AND ffbl_time > {$this->ff->data['ff_time_reset']}" : "";
		
		// sideinformasjon - hent siste bevegelser
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 15);
		$result = $pagei->query("SELECT ffbl_type, ffbl_amount, ffbl_up_id, ffbl_note, ffbl_time, ffbl_balance FROM ff_bank_log WHERE ffbl_ff_id = {$this->ff->id}$ff_reset ORDER BY ffbl_time DESC");
		
		if ($result->rowCount() == 0)
		{
			echo '
<p class="c">
	Ingen overføringer er enda registrert.
</p>';
		}
		
		else
		{
			echo '
<h1 id="bevegelser" class="c">Siste bevegelser</h1>
<table class="table center">
	<thead>
		<tr>
			<th>Type</th>
			<th>Person</th>
			<th>Beløp</th>
			<th>Tidspunkt</th>
			<th>Notat</th>
			<th>Balanse</th>
		</tr>
	</thead>
	<tbody class="nowrap">';
			
			$i = 0;
			$typer = array(1 => "bank_inn", "bank_ut", "bank_doner", "bank_betaling", "bank_tbetaling");
			
			while ($row = $result->fetch())
			{
				$type = isset($typer[$row['ffbl_type']]) ? ff::$bank_ikoner[$typer[$row['ffbl_type']]] : 'Ukjent';
				$type .= " " . (isset(ff::$bank_types[$row['ffbl_type']]) ? ff::$bank_types[$row['ffbl_type']] : 'Ukjent');
				
				if ($row['ffbl_type'] == 2 || $row['ffbl_type'] == 4) $row['ffbl_amount'] = "-".$row['ffbl_amount'];
				
				$player = $row['ffbl_up_id'] ? '<user id="'.$row['ffbl_up_id'].'" />' : 'Spillet';
				
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td>'.$type.'</td>
			<td>'.$player.'</td>
			<td class="r">'.game::format_cash($row['ffbl_amount']).'</td>
			<td>'.ess::$b->date->get($row['ffbl_time'])->format(date::FORMAT_SEC).'</td>
			<td class="wrap">'.(empty($row['ffbl_note']) ? '<span style="color: #AAA">Tomt</span>' : game::bb_to_html($row['ffbl_note'])).'</td>
			<td class="r">'.game::format_cash($row['ffbl_balance']).'</td>
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
<p class="c">'.$pagei->pagenumbers(game::address(PHP_SELF, $_GET, array("side"))."#bevegelser", game::address(PHP_SELF, $_GET, array("side"), array("side" => "_pageid_"))."#bevegelser").'</p>';
			}
		}
	}
}