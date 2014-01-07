<?php

// TODO kontrollere bydeler ved endringer i garasje

class page_gta extends pages_player
{
	/**
	 * GTA objektet
	 * @var gta
	 */
	protected $gta;
	
	/**
	 * Hvilken side vi befinner oss på
	 */
	protected $parts;
	
	/**
	 * Anti-bot
	 * @var antibot
	 */
	protected $antibot;
	
	/**
	 * Skjema
	 * @var form
	 */
	protected $form;
	
	/**
	 * Construct
	 * @param player $up
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		$this->gta = new gta($up);
		$this->handle_page();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function handle_page()
	{
		ess::$b->page->add_title("GTA");
		
		// få temaet til å vise full gta meny
		define("SHOW_GTA_MENU", true);
		
		// finn ut hva vi skal vise
		if (!isset($_SERVER['REDIR_URL']))
		{
			redirect::handle("", redirect::ROOT);
		}
		
		// kontroller diverse ting
		$this->gta->up->fengsel_require_no();
		$this->gta->up->bomberom_require_no();
		
		$this->parts = explode("/", $_SERVER['REDIR_URL']);
		array_shift($this->parts);
		array_shift($this->parts);
		
		$part0 = isset($this->parts[0]) ? $this->parts[0] : "";
		
		// vise garasjen?
		if ($part0 == "garasje")
		{
			redirect::store("/gta/garasje", redirect::ROOT);
			$this->garasje_show();
		}
		
		// vise statistikk?
		elseif ($part0 == "stats")
		{
			$this->stats_show();
		}
		
		// vis biltyveri
		else
		{
			redirect::store("/gta", redirect::ROOT);
			$this->biltyveri_show();
		}
		
		ess::$b->page->load();
	}
	
	/** Kontroller rank */
	protected function check_rank()
	{
		if ($this->gta->check_rank()) return;
		
		echo '
<p align="center" style="color: #888888">
	Du har ikke høy nok rank for å kunne stjele biler!<br />
	<br />
	For å stjele biler trenger du ranken <b>'.game::$ranks['items_number'][$min_rank]['name'].'</b> eller høyere!
</p>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis biltyveri
	 */
	protected function biltyveri_show()
	{
		ess::$b->page->add_title("Biltyveri");
		
		// kontroller ranken vi må ha for å utføre biltyveri
		$this->check_rank();
		
		// kontroller energi
		$this->gta->up->energy_require(gta::ENERGY_BILTYVERI*1.3); // legg til 30 % på kravet
		
		// anti-bot
		$this->antibot = antibot::get("biltyveri", 10);
		$this->antibot->check_required(ess::$s['rpath'].'/gta');
		
		// skjema
		$this->form = \Kofradia\Form::getByDomain("biltyveri", login::$user);
		
		// hent inn alternativene
		$this->gta->load_options();
		
		// ønsker vi å utføre biltyveri?
		if (isset($_POST['option_id']))
		{
			$this->biltyveri_utfor();
		}
		
		// kontroller ventetid
		$wait = $this->gta->calc_wait();
		$wait = $wait[0];
		
		echo '
<div class="col2_w" style="margin: 35px 0">
	<div class="col_w left" style="width: 64%">
		<div class="col">
<div class="bg1_c center" style="width: 350px">
	<h1 class="bg1">Biltyveri<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_right"><a href="&rpath;/node/20">Hjelp</a></p>
	<div class="bg1">
		';
		
		// har vi ikke garasje i denne bydelen?
		$bydeler = $this->gta->get_bydeler_info();
		if (!$bydeler[$this->gta->up->data['up_b_id']]['ff_id'])
		{
			echo '
		<p>Du må ha en garasje i denne bydelen før du kan forsøke å utføre biltyveri.</p>';
		}
		
		// har ikke plass til flere biler?
		elseif ($bydeler[$this->gta->up->data['up_b_id']]['garage_free'] == 0)
		{
			$msg = ess::$b->page->message_get("gta_result");
			if ($msg)
			{
				echo '
		<div class="p c">'.$msg['message'].'</div>';
			}
			
			echo '
		<p>Det er ikke plass til flere kjøretøy i garasjen din. Oppgrader garasje, selg eller flytt biler for å kunne utføre biltyveri.</p>';
		}
		
		// ingen alternativer?
		elseif (count($this->gta->options) == 0)
		{
			echo '
		<p>Det er ingen mulighet for å stjele biler i denne bydelen.</p>';
		}
		
		else
		{
			$id = reset($this->gta->options);
			$id = $id['id'];
			
			$rank = $this->gta->up->rank['need_points'] == 0 ? game::format_number(round(gta::RANK_BILTYVERI/$this->gta->up->rank['points'], 5)*100, 4) : game::format_number(round(gta::RANK_BILTYVERI/$this->gta->up->rank['need_points'], 5)*100, 3);
			
			echo '
		<form action="" method="post" onsubmit="noSubmit(this)">
			'.$this->form->getHTMLInput().'
			<table class="table game center tablemt" style="width: 100%">
				<thead>
					<tr>
						<th>Navn</th>
						<th>Sjanse</th>
						<th>Forsøk</th>
						<th>Vellykkede</th>
					</tr>
				</thead>
				<tbody>';
			
			// vis alternativene
			$valgt = login::data_get("biltyveri_alternativ_".login::$user->player->data['up_b_id']);
			$i = 0;
			foreach ($this->gta->options as $option)
			{
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="option_id" value="'.$option['id'].'"'.($option['id'] == $valgt ? ' checked="checked"' : '').' />'.htmlspecialchars($option['name']).'</td>
						<td class="r">'.game::format_number($option['percent']).' %</td>
						<td class="r">'.game::format_number($option['count']).'</td>
						<td class="r">'.game::format_number($option['success']).' ('.($option['count'] == 0 ? '0,0' : game::format_number($option['success']/$option['count']*100, 1)).' %)</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>';
			
			if (isset(ess::$b->page->messages['gta_result']))
			{
				echo '
			<div class="p c">'.ess::$b->page->messages['gta_result']['message'].'</div>';
				
				unset(ess::$b->page->messages['gta_result']);
			}
			
			echo ($wait > 0 ? '
			<p class="c">Du må vente '.game::counter($wait, true).' før du kan utføre biltyveri igjen.</p>' : '
			<p class="c">'.show_sbutton("Utfør handling").'</p>');
			
			echo '
		</form>';
		}
		
		echo '
	</div>
</div>
		</div>
	</div>
	<div class="col_w right" style="width: 36%">
		<div class="col">
			<p class="c" style="margin-top: 0"><img src="&staticlink;/gta/biltyveri.jpg" alt="Biltyveri" style="border: 3px solid #1F1F1F" /></p>
		</div>
	</div>
</div>';
	}
	
	/**
	 * Utfør biltyveri
	 */
	protected function biltyveri_utfor()
	{
		$wait = $this->gta->calc_wait();
		
		// form sjekking
		if (!$this->form->validateHashOrAlert(null, ($wait[1] ? "Siste=".game::timespan($wait[1], game::TIME_SHORT | game::TIME_NOBOLD).";" : "First;").($wait[0] ? "%c11Ventetid=".game::timespan($wait[0], game::TIME_NOBOLD | game::TIME_SHORT)."%c" : "%c9No-wait%c")))
		{
			return;
		}
		
		// har vi noe ventetid?
		if ($wait[0] > 0)
		{
			redirect::handle();
		}
		
		// har vi ikke garasje i denne bydelen eller ingen ledige plasser?
		$bydeler = $this->gta->get_bydeler_info();
		if (!$bydeler[$this->gta->up->data['up_b_id']]['ff_id'] || $bydeler[$this->gta->up->data['up_b_id']]['garage_free'] == 0)
		{
			redirect::handle();
		}
		
		// finnes alternativet?
		if (!isset($this->gta->options[postval("option_id")]))
		{
			ess::$b->page->add_message("Ukjent alternativ!", "error");
			redirect::handle();
		}
		
		// utfør
		$result = $this->gta->biltyveri_utfor(postval("option_id"));
		
		$fengsel_msg = $result['wanted_change'] > 0 ? ' Wanted nivået økte med '.game::format_number($result['wanted_change']/10, 1).' %.' : '';
		if (!$result['success'])
		{
			ess::$b->page->add_message("Du mislykket forsøket.$fengsel_msg", NULL, NULL, "gta_result");
		}
		
		else
		{
			ess::$b->page->add_message('<div style="overflow: hidden; padding-top: 4px"><img src="'.htmlspecialchars($result['gta']['img_mini']).'" alt="" style="float: left; margin: -4px 5px 0 0; border: 2px solid #292929" />'.$result['message'].$fengsel_msg.'</div>', NULL, NULL, "gta_result");
		}
		
		// lagre alternativ
		login::data_set("biltyveri_alternativ_".$this->gta->up->data['up_b_id'], postval("option_id"));
		
		// oppdater anti-bot
		if (!access::has("mod")) $this->antibot->increase_counter();
		
		redirect::handle();
	}
	
	/**
	 * Vis oversikt over garasjen
	 */
	protected function garasje_show()
	{
		ess::$b->page->add_title("Garasje");
		
		// kjøpe garasje?
		if (isset($this->parts[1]) && $this->parts[1] == "kjop")
		{
			redirect::store("/gta/garasje/kjop", redirect::ROOT);
			return $this->garasje_kjop_show();
		}
		
		// avslutte garasje?
		if (isset($this->parts[1]) && $this->parts[1] == "avslutt")
		{
			redirect::store("/gta/garasje/avslutt", redirect::ROOT);
			return $this->garasje_avslutt_show();
		}
		
		// endre garasje?
		if (isset($this->parts[1]) && $this->parts[1] == "endre")
		{
			redirect::store("/gta/garasje/endre", redirect::ROOT);
			return $this->garasje_endre_show();
		}
		
		// vise detaljer over garasje?
		if (isset($this->parts[1]) && $this->parts[1] == "detaljer")
		{
			redirect::store("/gta/garasje/detaljer", redirect::ROOT);
			return $this->garasje_details_show();
		}
		
		// betale leie?
		if (isset($this->parts[1]) && $this->parts[1] == "betale")
		{
			redirect::store("/gta/garasje/betale", redirect::ROOT);
			return $this->garasje_betale_show();
		}
		
		// skjema
		$this->form = \Kofradia\Form::getByDomain("gta_garasje", login::$user);
		
		// anti-bot
		$this->antibot = antibot::get("biltyveri", 10);
		$this->antibot->check_required(ess::$s['rpath'].'/gta');
		
		// flytte biler?
		if (isset($_POST['flytt']))
		{
			return $this->garasje_flytt_show();
		}
		
		// selge biler?
		if (isset($_POST['selg']))
		{
			$this->garasje_selg_handle();
		}
		
		// hent informasjon om garasjen
		$result = \Kofradia\DB::get()->query("
			SELECT ugg_time, ugg_time_next_rent, ugg_cost_total, ugg_places, ff_id, ff_name
			FROM users_garage
				LEFT JOIN ff ON ff_id = ugg_ff_id
			WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
		$garasje = $result->fetch();
		
		// kan vi betale nå?
		$can_pay = $garasje && gta::can_pay($garasje['ugg_time_next_rent']);
		
		echo '
<div class="col2_w" style="margin: 50px 50px 0">
	<div class="col_w left" style="width: 50%">
		<div class="col">
			<div class="bg1_c center" style="width: 85%">
				<h1 class="bg1">Garasje på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
				<div class="bg1">'.(!$garasje ? '
					<p class="c">Du har ingen garasje i denne bydelen.</p>
					<p class="c"><a href="&rpath;/gta/garasje/kjop">Lei ny garasje</a></p>' : '
					<dl class="dd_right">
						<dt>Utleiefirma</dt>
						<dd><a href="&rpath;/ff/?ff_id='.$garasje['ff_id'].'">'.htmlspecialchars($garasje['ff_name']).'</a></dd>
						<dt>Kapasitet</dt>
						<dd>'.game::format_num($garasje['ugg_places']).'</dd>
						<dt>Neste betalingsfrist</dt>
						<dd>'.ess::$b->date->get($garasje['ugg_time_next_rent'])->format().($can_pay ? '<br /><a href="&rpath;/gta/garasje/betale">Betal leie før fristen</a>' : '').'</dd>
					</dl>
					<p class="c"><a href="&rpath;/gta/garasje/detaljer">Vis flere detaljer</a></p>
					<p>Leie for neste periode må betales innen betalingsfristen'.($can_pay ? '' : ' og blir mulig 3 dager før fristen').'.</p>').'
				</div>
			</div>
		</div>
	</div>
	<div class="col_w right" style="width: 50%">
		<div class="col">
			<p class="c" style="margin-top: 0"><img src="&staticlink;/gta/garasje.jpg" alt="Garasje" style="border: 3px solid #1F1F1F" /></p>
		</div>
	</div>
</div>';
		
		if ($garasje)
		{
			// hent bilene i garasjen
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 15);
			$result = $pagei->query("
				SELECT s.id, s.gtaid, s.time, s.time_last_move, s.b_id_org, s.b_id, g.brand, g.model, g.img_mini, g.value, s.damage
				FROM users_gta AS s LEFT JOIN gta AS g ON s.gtaid = g.id
				WHERE ug_up_id = {$this->gta->up->id} AND s.b_id = {$this->gta->up->data['up_b_id']}
				ORDER BY s.time DESC");
			
			echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Biler i garasjen<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">';
			
			// har vi ingen biler?
			if ($result->rowCount() == 0)
			{
				echo '
		<p>Det er ingen biler plassert i denne garasjen. Bilene du stjeler vil bli plassert i garasjen i bydelen du oppholder deg.</p>';
			}
			
			// vis liste over biler
			else
			{
				echo '
		<form action="" method="post">
			'.$this->form->getHTMLInput().'
			<table class="table tablemt center">
				<thead>
					<tr>
						<th><a href="#" class="box_handle_toggle" rel="bil[]">Merk alle</a></th>
						<th>Merke/Modell</th>
						<th>Dato anskaffet</th>
						<th>Skade</th>
						<th>Verdi</th>
					</tr>
				</thead>
				<tbody>';
				
				$i = 0;
				while ($row = $result->fetch())
				{
					echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="checkbox" id="bil_'.$row['id'].'" name="bil[]" value="'.$row['id'].'" />'.(empty($row['img_mini']) ? '&nbsp;' : '<img src="'.$row['img_mini'].'" alt="Bilde" />').'</td>
						<td>'.htmlspecialchars($row['brand']).'<br /><b>'.htmlspecialchars($row['model']).'</b></td>
						<td>'.ess::$b->date->get($row['time'])->format().'</td>
						<td align="right">'.$row['damage'].' %</td>
						<td align="right">'.game::format_cash($row['value']*((100-$row['damage'])/100)).'</td>
					</tr>';
				}
				
				echo '
				</tbody>
			</table>'.($pagei->pages > 1 ? '
			<p class="c">'.$pagei->pagenumbers().'</p>' : '').'
			<p class="c">
				'.show_sbutton("Selg biler", 'name="selg"').'
				'.show_sbutton("Flytt biler", 'name="flytt"').'
			</p>
		</form>';
			}
			
			echo '
	</div>
</div>';
		}
	}
	
	/**
	 * Flytte biler til en annen garasje
	 */
	protected function garasje_flytt_show()
	{
		ess::$b->page->add_title("Flytt biler");
		
		// hent informasjon om bilene
		$biler = array();
		$biler_q = array();
		if (isset($_POST['bil']))
		{
			if (is_array($_POST['bil'])) $biler_q = array_unique(array_map("intval", $_POST['bil']));
			else $biler_q = array_unique(array_map("intval", explode(",", $_POST['bil'])));
			if (count($biler_q) > 0)
			{
				// hent bilinformasjon
				$result = \Kofradia\DB::get()->query("
					SELECT s.id, s.gtaid, s.time, s.time_last_move, s.b_id_org, s.b_id, g.brand, g.model, g.img_mini, g.value, s.damage
					FROM users_gta AS s JOIN gta AS g ON s.gtaid = g.id
					WHERE ug_up_id = {$this->gta->up->id} AND s.b_id = {$this->gta->up->data['up_b_id']} AND s.id IN (".implode(",", $biler_q).")
					ORDER BY s.time DESC");
				
				$biler_q = array();
				while ($row = $result->fetch())
				{
					$biler[] = $row;
					$biler_q[] = $row['id'];
				}
			}
		}
		
		// ingen biler?
		if (count($biler) == 0)
		{
			ess::$b->page->add_message("Du må merke noen biler du ønsker å flytte.");
			redirect::handle();
		}
		
		// hent garasjeoversikt
		$bydeler = $this->gta->get_bydeler_info();
		
		// flytte bilene til en garasje?
		if (isset($_POST['flyttdo']) && $this->form->validateHashOrAlert(null, "Flytte biler"))
		{	
			// har vi ikke valgt noen bydel?
			if (!isset($_POST['bydel']) || !isset($bydeler[$_POST['bydel']]))
			{
				ess::$b->page->add_message("Du må velge en bydel du ønsker å flytte bilene til.");
			}
			
			else
			{
				// har vi ikke stor nok kapasitet i denne bydelen
				$bydel = $bydeler[$_POST['bydel']];
				if ($bydel['garage_free'] < count($biler))
				{
					ess::$b->page->add_message("Det er ikke ledig plass til alle bilene du ønsker å flytte i bydelen <b>".htmlspecialchars(game::$bydeler[$bydel['b_id']]['name'])."</b>.", "error");
				}
				
				else
				{
					// flytt bilene
					$a = \Kofradia\DB::get()->exec("
						UPDATE users_gta
						SET time_last_move = ".time().", b_id = {$bydel['b_id']}
						WHERE ug_up_id = {$this->gta->up->id} AND b_id = {$this->gta->up->data['up_b_id']} AND id IN (".implode(",", $biler_q).")");
					
					ess::$b->page->add_message("Du flyttet <b>".$a."</b> biler til <b>".htmlspecialchars(game::$bydeler[$bydel['b_id']]['name'])."</b>.");
					
					$this->antibot->increase_counter();
					redirect::handle();
				}
			}
		}
		
		// vis oversikt over garasjer vi kan velge mellom
		echo '
<form action="" method="post">
	'.$this->form->getHTMLInput().'
	<input type="hidden" name="bil" value="'.implode(",", $biler_q).'" />
	<input type="hidden" name="flytt" />
	<div class="bg1_c xsmall">
		<h1 class="bg1">Flytte biler ('.count($biler).' stk)<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p class="c">Velg bydel bilene skal flyttes til:</p>
			<table class="table center">
				<thead>
					<tr>
						<th>Bydel</th>
						<th>Utleiefirma</th>
						<th>Ledige plasser</th>
					</tr>
				</thead>
				<tbody>';
		
		$i = 0;
		foreach ($bydeler as $row)
		{
			$bydel = game::$bydeler[$row['b_id']];
			if (!$bydel['active'] || $row['b_id'] == $this->gta->up->data['up_b_id']) continue;
			
			echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="bydel"'.($row['ff_id'] ? '' : ' disabled="disabled"').' value="'.$row['b_id'].'" />'.htmlspecialchars($bydel['name']).'</td>';
			
			// ingen garasje?
			if (!$row['ff_id'])
			{
				echo '
						<td colspan="2" class="c dark"><i>Ingen garasje</i></td>';
			}
			
			else
			{
				echo '
						<td><a href="&rpath;/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></td>
						<td>'.$row['garage_free'].'</td>';
			}
			
			echo '
					</tr>';
		}
		
		echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Flytt bilene", 'name="flyttdo"').' <a href="&rpath;/gta/garasje">Avbryt</a></p>
		</div>
	</div>
</form>
<div class="bg1_c xmedium">
	<h1 class="bg1">Biler som vil bli flyttet<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<table class="table tablem center">
			<thead>
				<tr>
					<th>&nbsp;</th>
					<th>Merke/Modell</th>
					<th>Dato anskaffet</th>
					<th>Skade</th>
					<th>Verdi</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		foreach ($biler as $row)
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.(empty($row['img_mini']) ? '&nbsp;' : '<img src="'.$row['img_mini'].'" alt="Bilde" />').'</td>
					<td>'.htmlspecialchars($row['brand']).'<br /><b>'.htmlspecialchars($row['model']).'</b></td>
					<td>'.ess::$b->date->get($row['time'])->format().'</td>
					<td align="right">'.$row['damage'].' %</td>
					<td align="right">'.game::format_cash($row['value']*((100-$row['damage'])/100)).'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
	</div>
</div>';
	}
	
	protected function garasje_selg_handle()
	{
		//$this->form->validate(postval("hash"), "Selge biler");
		
		// hent informasjon om bilene
		$biler_q = array();
		if (isset($_POST['bil']) && is_array($_POST['bil']))
		{
			$biler_q = array_unique(array_map("intval", $_POST['bil']));
		}
		
		// ingen biler?
		if (count($biler_q) == 0)
		{
			ess::$b->page->add_message("Du må merke bilene du ønsker å selge.", "error");
			redirect::handle();
		}
		
		// forsøk å selg bilene
		\Kofradia\DB::get()->beginTransaction();
		
		// sett biler som solgt
		\Kofradia\DB::get()->exec("
			UPDATE users_gta, gta, users_players
			SET b_id = 0
			WHERE up_id = {$this->gta->up->id} AND ug_up_id = up_id AND gtaid = gta.id AND b_id = {$this->gta->up->data['up_b_id']} AND users_gta.id IN (".implode(",", $biler_q).")");
		
		// beregn hvor mye vi får for bilene
		$result = \Kofradia\DB::get()->query("
			SELECT SUM(value * (100-damage) / 100)
			FROM users_gta JOIN gta ON gtaid = gta.id
			WHERE ug_up_id = {$this->gta->up->id} AND b_id = 0 AND users_gta.id IN (".implode(",", $biler_q).")");
		$totcash = (int) $result->fetchColumn(0);
		
		// gi penger til spilleren
		$this->up->update_money($totcash, true, true);
		
		// fjern bilene som ble solgt
		$ant = \Kofradia\DB::get()->exec("
			DELETE FROM users_gta
			WHERE ug_up_id = {$this->gta->up->id} AND b_id = 0 AND id IN (".implode(",", $biler_q).")");
		
		\Kofradia\DB::get()->commit();
		
		ess::$b->page->add_message("Du solgte <b>$ant</b> ".fword("bil", "biler", $ant)." og fikk totalt <b>".game::format_cash($totcash)."</b>.");
		redirect::handle();
	}
	
	/**
	 * Oppgradere garasje
	 */
	protected function garasje_kjop_show()
	{
		ess::$b->page->add_title("Leie");
		
		// har vi allerede en garasje i denne bydelen?
		$result = \Kofradia\DB::get()->query("SELECT ugg_id FROM users_garage WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
		if ($result->rowCount() > 0)
		{
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		// hent inn firmaer vi kan velge mellom
		$ff_list = $this->gta->get_ff();
		
		// valgt ff?
		if (isset($_POST['kjop']))
		{
			$places = (int) postval("places");
			
			// bydel forandret seg
			if (postval("b_id") != $this->gta->up->data['up_b_id'])
			{
				ess::$b->page->add_message("Du har reist til en annen bydel siden du sist viste siden.", "error");
				redirect::handle("/gta/garasje", redirect::ROOT);
			}
			
			// kontroller FF
			$data = explode(":", postval("ff_id"));
			if (!isset($ff_list[$data[0]]) || !isset($data[1]))
			{
				ess::$b->page->add_message("Du må velge et firma du ønsker å leie garasje hos.", "error");
			}
			
			elseif ($ff_list[$data[0]]['price'] != $data[1])
			{
				ess::$b->page->add_message("Utleieprisen for firmaet du valgte har forandret seg. Velg ønsket firma på nytt.", "error");
			}
			
			elseif ($places < 1 || $places > $this->gta->get_places_limit())
			{
				ess::$b->page->add_message("Ugyldig antall for antall plasser du ønsker å leie.", "error");
			}
			
			else
			{
				$ff = $ff_list[$data[0]];
				$price = $places * $ff['price'];
				
				$this->garasje_kjop_confirm($ff, $places, $price);
				
				echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Leie garasje på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>For at du skal ha en garasje i en bydel må du leie den av et firma. Innen hver 7. dag må du fornye leien for å ikke miste garasjen og bilene i den. Leien betales forskuddsvis.</p>
		<form action="" method="post">
			<input type="hidden" name="confirm" />
			<input type="hidden" name="ff_id" value="'.$ff['ff_id'].':'.$ff['price'].'" />
			<input type="hidden" name="places" value="'.$places.'" />
			<input type="hidden" name="b_id" value="'.$this->gta->up->data['up_b_id'].'" />
			<dl class="dd_right">
				<dt>Utleiefirma</dt>
				<dd><a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a></dd>
				<dt>Antall plasser</dt>
				<dd>'.game::format_num($places).'</dd>
				<dt>Leiepris første 7 dager</dt>
				<dd>'.game::format_cash($price).'</dd>
			</dl>
			<p class="c">'.show_sbutton("Lei garasje fra firmaet", 'name="kjop"').' '.show_sbutton("Tilbake").'</p>
		</form>
	</div>
</div>';
				
				ess::$b->page->load();
			}
		}
		
		ess::$b->page->add_js_domready('$("gta_places").focus();');
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Leie garasje på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>For at du skal ha en garasje i en bydel må du leie den av et firma. Innen hver 7. dag må du fornye leien for å ikke miste garasjen og bilene i den. Leien betales forskuddsvis.</p>
		<form action="" method="post">
			<input type="hidden" name="b_id" value="'.$this->gta->up->data['up_b_id'].'" />
			<dl class="dd_right">
				<dt>Maksimalt antall plasser</dt>
				<dd>'.game::format_num($this->gta->get_places_limit()).'</dd>
				<dt>Antall plasser som ønskes</dt>
				<dd><input type="text" class="styled w40" name="places" id="gta_places" value="'.game::format_num(intval(postval("places", 1))).'" /></dd>
				<dt>Utleiefirma</dt>
				<dd>
			<table class="table center">
				<thead>
					<tr>
						<th>Utleiefirma</th>
						<th>Pris per plass</th>
					</tr>
				</thead>
				<tbody>';
		
		$i = 0;
		$ff_id = (int) postval("ff_id");
		foreach ($ff_list as $row)
		{
			echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="ff_id" value="'.$row['ff_id'].':'.$row['price'].'"'.($ff_id == $row['ff_id'] ? ' checked="checked"' : '').' /><a href="&rpath;/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></td>
						<td class="c">'.game::format_cash($row['price']).'</td>
					</tr>';
		}
		
		echo '
				</tbody>
			</table>
				</dd>
			</dl>
			<p class="c">Antall plasser du leier hos firmaet kan endres også etter at du har begynte å leie plass.</p>
			<p class="c">'.show_sbutton("Fortsett", 'name="kjop"').'</p>
		</form>';
		
		echo '
		<p class="c"><a href="&rpath;/gta/garasje">Tilbake</a></p>
	</div>
</div>';
	}
	
	protected function garasje_kjop_confirm($ff, $places, $price)
	{
		if (!isset($_POST['confirm'])) return;
		
		// trekk fra penger
		$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = {$this->gta->up->id} AND up_cash >= $price");
		if ($a == 0)
		{
			ess::$b->page->add_message("Du har ikke råd til å leie så mange plasser hos dette firmaet.", "error");
			return;
		}
		
		// sett opp tidspunkt for neste innbetaling
		$next = ess::$b->date->get();
		if ($next->format("H") < 6) $next->modify("-1 day");
		
		$next->setTime(6, 0, 0);
		$next->modify("+7 days");
		
		$next = $next->format("U");
		
		// gi garasje
		$a = \Kofradia\DB::get()->exec("INSERT IGNORE INTO users_garage SET ugg_up_id = {$this->gta->up->id}, ugg_b_id = {$this->gta->up->data['up_b_id']}, ugg_ff_id = {$ff['ff_id']}, ugg_time = ".time().", ugg_time_next_rent = $next, ugg_cost_total = $price, ugg_places = $places");
		
		// kunne ikke gi garasje => allerede kjøpt
		if ($a == 0)
		{
			// gi tilbake pengene
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + $price WHERE up_id = {$this->gta->up->id}");
		}
		
		else
		{
			// gi pengene til firmaet
			ff::bank_static(ff::BANK_TJENT, round($price * ff::GTA_PERCENT), $ff['ff_id']);
			
			ess::$b->page->add_message('Du leier nå garasje med '.fwords("%d plass", "%d plasser", $places).' på <b>'.game::$bydeler[$this->gta->up->data['up_b_id']]['name'].'</b> hos firmaet <a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a> for '.game::format_cash($price).'.');
		}
		
		redirect::handle("/gta/garasje", redirect::ROOT);
	}
	
	/**
	 * Avslutte leie for garasje
	 */
	protected function garasje_avslutt_show()
	{
		ess::$b->page->add_title("Avslutte leie");
		
		// hent informasjon om garasjen
		$result = \Kofradia\DB::get()->query("
			SELECT ugg_time, ugg_time_next_rent, ugg_ff_id, ugg_cost_total, ugg_places
			FROM users_garage
			WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
		$garasje = $result->fetch();
		
		// har vi ingen garasje?
		if (!$garasje)
		{
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		// hent alle firmaene og plukk ut det korrekte
		$ff_list = $this->gta->get_ff();
		if (!isset($ff_list[$garasje['ugg_ff_id']])) throw new HSException("Mangler firma for garasje.");
		$ff = $ff_list[$garasje['ugg_ff_id']];
		
		// har vi biler i garasjen?
		$result = \Kofradia\DB::get()->query("SELECT COUNT(*) FROM users_gta WHERE ug_up_id = {$this->gta->up->id} AND b_id = {$this->gta->up->data['up_b_id']}");
		$num = $result->fetchColumn(0);
		if ($num > 0)
		{
			ess::$b->page->add_message("Du kan ikke ha noen biler i garasjen om du ønsker å legge den ned.", "error");
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		// bekreftet?
		if (isset($_POST['confirm']))
		{
			// bydel forandret seg
			if (postval("b_id") != $this->gta->up->data['up_b_id'])
			{
				ess::$b->page->add_message("Du har reist til en annen bydel siden du sist viste siden.", "error");
				redirect::handle("/gta/garasje", redirect::ROOT);
			}
			
			// fjern garasjen
			\Kofradia\DB::get()->exec("DELETE FROM users_garage WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
			ess::$b->page->add_message("Du har avsluttet ditt leieforhold til utleiefirmaet og har ikke lenger noen garasje i denne bydelen.");
			
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Avslutte garasjeleie på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p class="c"><a href="&rpath;/gta/garasje">Tilbake</a></p>
		<dl class="dd_right">
			<dt>Utleiefirma</dt>
			<dd><a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a></dd>
			<dt>Kapasitet</dt>
			<dd>'.game::format_num($garasje['ugg_places']).'</dd>
			<dt>Leiepris per plass</dt>
			<dd>'.game::format_cash($ff['price']).'</dd>
			<dt>Neste betalingsfrist</dt>
			<dd>'.ess::$b->date->get($garasje['ugg_time_next_rent'])->format().'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="b_id" value="'.$this->gta->up->data['up_b_id'].'" />
			<p class="c">Er du sikker på at du ønsker å avslutte leien av denne garasjen? Du vil da miste garasjen i denne bydelen.</p>
			<p class="c"><span class="red">'.show_sbutton("Avslutt leie", 'name="confirm"').'</span> <a href="&rpath;/gta/garasje">Tilbake</a></p>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Endre garasjekapasitet
	 */
	protected function garasje_endre_show()
	{
		ess::$b->page->add_title("Endre kapasitet");
		
		// hent informasjon om garasjen
		$result = \Kofradia\DB::get()->query("
			SELECT ugg_time, ugg_time_next_rent, ugg_ff_id, ugg_cost_total, ugg_places
			FROM users_garage
			WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
		$garasje = $result->fetch();
		
		// har vi ingen garasje?
		if (!$garasje)
		{
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		// hent alle firmaene og plukk ut det korrekte
		$ff_list = $this->gta->get_ff();
		if (!isset($ff_list[$garasje['ugg_ff_id']])) throw new HSException("Mangler firma for garasje.");
		$ff = $ff_list[$garasje['ugg_ff_id']];
		
		// finn ut antall biler i garasjen
		$result = \Kofradia\DB::get()->query("SELECT COUNT(*) FROM users_gta WHERE ug_up_id = {$this->gta->up->id} AND b_id = {$this->gta->up->data['up_b_id']}");
		$ug_num = $result->fetchColumn(0);
		
		$limit = $this->gta->get_places_limit();
		
		// valgt kapasitet?
		if (isset($_POST['places']))
		{
			$price_place = (int) postval("price");
			$places = (int) postval("places");
			$places_old = (int) postval("placeso");
			
			// bydel forandret seg
			if (postval("b_id") != $this->gta->up->data['up_b_id'])
			{
				ess::$b->page->add_message("Du har reist til en annen bydel siden du sist viste siden.", "error");
				redirect::handle("/gta/garasje", redirect::ROOT);
			}
			
			elseif ($ff['price'] != $price_place && $places > $garasje['ugg_places'])
			{
				ess::$b->page->add_message("Utleieprisen for firmaet har forandret seg.", "error");
			}
			
			elseif ($places_old != $garasje['ugg_places'])
			{
				ess::$b->page->add_message("Kapasiteten i garasjen har endret.", "error");
			}
			
			// endret ikke antall?
			elseif ($places == $garasje['ugg_places'])
			{
				ess::$b->page->add_message("Du må fylle inn en annen kapasitet enn hva den er i dag.", "error");
			}
			
			elseif ($places < 1 || $places > $limit)
			{
				ess::$b->page->add_message("Ugyldig antall for antall plasser du ønsker å leie.", "error");
			}
			
			// har for mange biler?
			elseif ($places < $ug_num)
			{
				ess::$b->page->add_message("Du kan ikke sette kapasiteten lavere enn antall biler som er i garasjen. Det er for øyeblikket ".fwords("%d bil", "%d biler", $ug_num)." i garasjen.", "error");
			}
			
			else
			{
				$change = $places - $garasje['ugg_places'];
				
				// beregn penger
				if ($change > 0)
				{
					$days_left = max(1, ceil(($garasje['ugg_time_next_rent'] - time()) / 86400));
					$price = $change * $ff['price'] * $days_left / 7;
				}
				
				// bekreftet?
				if (isset($_POST['confirm']))
				{
					// senkes?
					if ($change < 0)
					{
						// oppdater
						$a = \Kofradia\DB::get()->exec("UPDATE users_garage SET ugg_places = $places WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']} AND ugg_places = {$garasje['ugg_places']}");
						
						if ($a > 0)
						{
							ess::$b->page->add_message("Du nedjusterte kapasiteten i garasjen fra ".$garasje['ugg_places']." plasser til ".fwords("%d plass", "%d plasser", $places).".");
							redirect::handle("/gta/garasje", redirect::ROOT);
						}
					}
					
					// økes
					else
					{
						// forsøk å trekk fra pengene
						$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = {$this->gta->up->id} AND up_cash >= $price");
						if ($a == 0)
						{
							ess::$b->page->add_message("Du har ikke råd til å leie så mange plasser hos dette firmaet.", "error");
						}
						
						else
						{
							// oppdater antall plasser
							$a = \Kofradia\DB::get()->exec("UPDATE users_garage SET ugg_places = $places, ugg_cost_total = ugg_cost_total + $price WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']} AND ugg_places = {$garasje['ugg_places']}");
							
							if ($a == 0)
							{
								// kunne ikke oppdatere garasje; gi tilbake pengene
								\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + $price WHERE up_id = {$this->gta->up->id}");
							}
							
							else
							{
								// gi pengene til firmaet
								ff::bank_static(ff::BANK_TJENT, round($price * ff::GTA_PERCENT), $ff['ff_id']);
								
								ess::$b->page->add_message('Du oppjusterte kapasiteten i garasjen med '.fwords("%d plass", "%d plasser", $change).' til '.$places.' plasser for '.game::format_cash($price).'.');
								redirect::handle("/gta/garasje", redirect::ROOT);
							}
						}
					}
				}
				
				echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Endre kapasitet for garasje på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<dl class="dd_right">
			<dt>Utleiefirma</dt>
			<dd><a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a></dd>
			<dt>Leiepris per plass</dt>
			<dd>'.game::format_cash($ff['price']).'</dd>
			<dt>Neste betalingsfrist</dt>
			<dd>'.ess::$b->date->get($garasje['ugg_time_next_rent'])->format().'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="price" value="'.$ff['price'].'" />
			<input type="hidden" name="placeso" value="'.$garasje['ugg_places'].'" />
			<input type="hidden" name="places" value="'.$places.'" />
			<input type="hidden" name="b_id" value="'.$this->gta->up->data['up_b_id'].'" />
			<dl class="dd_right">
				<dt>Endring i kapasitet</dt>
				<dd>Fra '.game::format_num($garasje['ugg_places']).' til '.game::format_num($places).'</dd>'.($change > 0 ? '
				<dt>Kostnad for endring</dt>
				<dd>'.game::format_cash($price).'</dd>' : '').'
			</dl>
			<p class="c">'.show_sbutton("Utfør endringer", 'name="confirm"').' <a href="&rpath;/gta/garasje/endre">Tilbake</a></p>
		</form>
	</div>
</div>';
				
				ess::$b->page->load();
			}
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Endre kapasitet for garasje på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p class="c"><a href="&rpath;/gta/garasje/detaljer">Tilbake</a></p>
		<dl class="dd_right">
			<dt>Utleiefirma</dt>
			<dd><a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a></dd>
			<dt>Leiepris per plass</dt>
			<dd>'.game::format_cash($ff['price']).'</dd>
			<dt>Neste betalingsfrist</dt>
			<dd>'.ess::$b->date->get($garasje['ugg_time_next_rent'])->format().'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="price" value="'.$ff['price'].'" />
			<input type="hidden" name="placeso" value="'.$garasje['ugg_places'].'" />
			<input type="hidden" name="b_id" value="'.$this->gta->up->data['up_b_id'].'" />
			<dl class="dd_right">
				<dt>Nåværende kapasitet</dt>
				<dd>'.game::format_num($garasje['ugg_places']).'</dd>
				<dt>Maksimal kapasitet</dt>
				<dd>'.game::format_num($limit).'</dd>
				<dt>Ønsket kapasitet</dt>
				<dd><input type="text" class="styled w40" name="places" id="gta_places" value="'.game::format_num(intval(postval("places", $garasje['ugg_places']))).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Fortsett").'</p>
		</form>
		<p class="c">Husk at det kan hende det lønner seg å bytte til et <a href="&rpath;/gta/garasje/detaljer">annet firma</a>.</p>
	</div>
</div>';
	}
	
	/**
	 * Vis detaljer over garasje
	 */
	protected function garasje_details_show()
	{
		ess::$b->page->add_title("Detaljer");
		
		// hent informasjon om garasjen
		$result = \Kofradia\DB::get()->query("
			SELECT ugg_time, ugg_time_next_rent, ugg_ff_id, ugg_cost_total, ugg_places
			FROM users_garage
			WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
		$garasje = $result->fetch();
		
		// har vi ingen garasje?
		if (!$garasje)
		{
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		// hent alle firmaene og plukk ut det korrekte
		$ff_list = $this->gta->get_ff();
		if (!isset($ff_list[$garasje['ugg_ff_id']])) throw new HSException("Mangler firma for garasje.");
		$ff = $ff_list[$garasje['ugg_ff_id']];
		
		$can_pay = gta::can_pay($garasje['ugg_time_next_rent']);
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Garasjedetaljer på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p class="c"><a href="&rpath;/gta/garasje">Tilbake</a></p>
		<dl class="dd_right">
			<dt>Utleiefirma</dt>
			<dd><a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a></dd>
			<dt>Kapasitet</dt>
			<dd>'.game::format_num($garasje['ugg_places']).'</dd>
			<dt>Leiepris per plass</dt>
			<dd>'.game::format_cash($ff['price']).'</dd>
			<dt>Neste betalingsfrist</dt>
			<dd>'.ess::$b->date->get($garasje['ugg_time_next_rent'])->format().'</dd>
		</dl>
		<dl class="dd_right">
			<dt>Første leie</dt>
			<dd>'.ess::$b->date->get($garasje['ugg_time'])->format().'</dd>
			<dt>Totalt betalt</dt>
			<dd>'.game::format_cash($garasje['ugg_cost_total']).'</dd>
			<dt>Gjennomsnittlig daglig pris</dt>
			<dd>'.game::format_cash($garasje['ugg_cost_total'] / ceil(($garasje['ugg_time_next_rent'] - $garasje['ugg_time']) / 86400)).'</dd>
		</dl>
		<p>Leie for neste periode må betales innen betalingsfristen'.($can_pay ? '. <a href="&rpath;/gta/garasje/betale">Betal nå &raquo;</a>' : ' og blir mulig 3 dager før fristen går ut.').'</p>
		<p class="c"><a href="&rpath;/gta/garasje/endre">Endre kapasitet</a> | <a href="&rpath;/gta/garasje/avslutt">Avslutt leie</a></p>
	</div>
</div>';
		
		// vis andre firmaer
		if (count($ff_list) > 1)
		{
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Andre utleiefirmaer<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Det er mulig å bytte til et annet garasjefirma. Da må leien for denne garasjen først avsluttes, og deretter må ny garasje kjøpes.</p>
			<table class="table center tablemb">
			<thead>
				<tr>
					<th>Utleiefirma</th>
					<th>Pris per plass</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($ff_list as $row)
			{
				if ($row['ff_id'] == $ff['ff_id']) continue;
				echo '
				<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
					<td><a href="&rpath;/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></td>
					<td class="c">'.game::format_cash($row['price']).'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>
	</div>
</div>';
		}
	}
	
	/**
	 * Betale leie for garasje
	 */
	protected function garasje_betale_show()
	{
		ess::$b->page->add_title("Betale leie");
		
		// hent informasjon om garasjen
		$result = \Kofradia\DB::get()->query("
			SELECT ugg_time, ugg_time_next_rent, ugg_ff_id, ugg_cost_total, ugg_places
			FROM users_garage
			WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']}");
		$garasje = $result->fetch();
		
		// har vi ingen garasje eller kan ikke betale leie nå?
		if (!$garasje || !gta::can_pay($garasje['ugg_time_next_rent']))
		{
			redirect::handle("/gta/garasje", redirect::ROOT);
		}
		
		// hent alle firmaene og plukk ut det korrekte
		$ff_list = $this->gta->get_ff();
		if (!isset($ff_list[$garasje['ugg_ff_id']])) throw new HSException("Mangler firma for garasje.");
		$ff = $ff_list[$garasje['ugg_ff_id']];
		
		// beregn pris for neste periode
		$price = $garasje['ugg_places'] * $ff['price'];
		
		// utføre betaling?
		if (isset($_POST['pay']))
		{
			$places = (int) postval("places");
			$price_old = (int) postval("price");
			
			// bydel forandret seg
			if (postval("b_id") != $this->gta->up->data['up_b_id'])
			{
				ess::$b->page->add_message("Du har reist til en annen bydel siden du sist viste siden.", "error");
				redirect::handle("/gta/garasje", redirect::ROOT);
			}
			
			if ($places != $garasje['ugg_places'])
			{
				ess::$b->page->add_message("Kapasiteten i garasjen har endret seg.", "error");
			}
			
			elseif ($price_old != $price)
			{
				ess::$b->page->add_message("Leieprisen hos utleiefirmaet har endret seg.", "error");
			}
			
			else
			{
				// trekk fra penger
				$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = {$this->gta->up->id} AND up_cash >= $price");
				if ($a == 0)
				{
					ess::$b->page->add_message("Du har ikke råd til å betale for denne leien.", "error");
				}
				
				else
				{
					// sett opp tidspunkt for neste innbetaling
					$next = ess::$b->date->get($garasje['ugg_time_next_rent']);
					$next->setTime(6, 0, 0);
					$next->modify("+7 days");
					$next = $next->format("U");
					
					// oppdater garasje
					$a = \Kofradia\DB::get()->exec("UPDATE users_garage SET ugg_time_next_rent = $next, ugg_cost_total = ugg_cost_total + $price WHERE ugg_up_id = {$this->gta->up->id} AND ugg_b_id = {$this->gta->up->data['up_b_id']} AND ugg_time_next_rent = {$garasje['ugg_time_next_rent']} AND ugg_places = {$garasje['ugg_places']}");
					
					// kunne ikke oppdatere garasje
					if ($a == 0)
					{
						// gi tilbake pengene
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + $price WHERE up_id = {$this->gta->up->id}");
					}
					
					else
					{
						// gi pengene til firmaet
						ff::bank_static(ff::BANK_TJENT, round($price * ff::GTA_PERCENT), $ff['ff_id']);
						
						ess::$b->page->add_message('Du har utvidet leieavtalen på <b>'.game::$bydeler[$this->gta->up->data['up_b_id']]['name'].'</b> hos firmaet <a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a> med 7 dager for '.game::format_cash($price).'.');
					}
					
					redirect::handle("/gta/garasje", redirect::ROOT);
				}
			}
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Betale leie for garasje på '.htmlspecialchars($this->gta->up->bydel['name']).'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p class="c"><a href="&rpath;/gta/garasje">Tilbake</a></p>
		<dl class="dd_right">
			<dt>Utleiefirma</dt>
			<dd><a href="&rpath;/ff/?ff_id='.$ff['ff_id'].'">'.htmlspecialchars($ff['ff_name']).'</a></dd>
			<dt>Kapasitet</dt>
			<dd>'.game::format_num($garasje['ugg_places']).'</dd>
			<dt>Leiepris per plass</dt>
			<dd>'.game::format_cash($ff['price']).'</dd>
			<dt>Neste betalingsfrist</dt>
			<dd>'.ess::$b->date->get($garasje['ugg_time_next_rent'])->format().'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="price" value="'.$price.'" />
			<input type="hidden" name="places" value="'.$garasje['ugg_places'].'" />
			<input type="hidden" name="b_id" value="'.$this->gta->up->data['up_b_id'].'" />
			<dl class="dd_right">
				<dt>Total leiepris neste periode</dt>
				<dd>'.game::format_cash($price).'</dd>
			</dl>
			<p class="c">'.show_sbutton("Utfør betaling for neste leie", 'name="pay"').'</p>
		</form>
		<p class="c">Husk at det kan hende det lønner seg å bytte til et <a href="&rpath;/gta/garasje/detaljer">annet firma</a>. Du kan også <a href="&rpath;/gta/garasje/endre">justere kapasiteten</a> før du betaler for å få billigere leie.</p>
	</div>
</div>';
	}
	
	/**
	 * Vise forskjellig statistikk for gta
	 */
	protected function stats_show()
	{
		ess::$b->page->add_title("Statistikk");
		
		// hent antall forsøk og vellykkede spredt på hver bydel
		$stats_totalt = array();
		$result = \Kofradia\DB::get()->query("
			SELECT b_id, MAX(time_last) max_time_last, SUM(count) sum_count, SUM(success) sum_success
			FROM gta_options_status
				JOIN gta_options ON optionid = gta_options.id
			WHERE gos_up_id = {$this->gta->up->id}
			GROUP BY b_id");
		while ($row = $result->fetch())
		{
			$stats_totalt[$row['b_id']] = $row;
		}
		
		// hent informasjon om bydelene
		$bydeler = $this->gta->get_bydeler_info();
		
		echo '
<h1>Statistikk for biltyveri</h1>
<div class="bg1_c small">
	<h1 class="bg1">Bydeler<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">';
		
		if (count($stats_totalt) == 0)
		{
			echo '
		<p>Du har ikke gjennomført noen forsøk på biltyveri.</p>';
		}
		
		else
		{
			echo '
		<table class="table center tablem">
			<thead>
				<tr>
					<th>Bydel</th>
					<th>Antall forsøk</th>
					<th>Antall vellykkede</th>
					<th>Forrige forsøk</th>
				</tr>
			</thead>
			<tbody class="r">';
			
			$i = 0;
			foreach (game::$bydeler as $bydel)
			{
				if ($bydel['active'] == 0) continue;
				
				if (isset($stats_totalt[$bydel['id']]))
				{
					$forsok = $stats_totalt[$bydel['id']]['sum_count'];
					$vellykkede = $stats_totalt[$bydel['id']]['sum_success'];
					$siste = ess::$b->date->get($stats_totalt[$bydel['id']]['max_time_last'])->format();
					
					echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="l">'.htmlspecialchars($bydel['name']).'</td>
					<td>'.$forsok.'</td>
					<td>'.$vellykkede.'</td>
					<td>'.$siste.'</td>
				</tr>';
				}
				
				else
				{
					echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="l">'.htmlspecialchars($bydel['name']).'</td>
					<td class="c dark" colspan="3"><i>Ingen forsøk</i></td>
				</tr>';
				}
			}
			
			echo '
			</tbody>
		</table>';
		}
		
		echo '
	</div>
</div>
<div class="bg1_c xmedium">
	<h1 class="bg1">Garasjer<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<table class="table center tablem">
			<thead>
				<tr>
					<th>Bydel</th>
					<th>Firma</th>
					<th>Kapasitet</th>
					<th>Biler i garasjen</th>
					<th>Ledige plasser</th>
					<th>Neste frist</th>
				</tr>
			</thead>
			<tbody class="c">';
		
		$i = 0;
		foreach ($bydeler as $b_id => $row)
		{
			$bydel = game::$bydeler[$b_id];
			if ($bydel['active'] == 0) continue;
			
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="l">'.htmlspecialchars($bydel['name']).'</td>';
			
			if (!$row['ff_id'])
			{
				echo '
					<td class="dark" colspan="5"><i>Ingen garasje</i></td>';
			}
			
			else
			{
				$ant = $row['garage_max_cars'] - $row['cars'];
				if ($ant < 0) $ant = 0;
				
				echo '
					<td><a href="&rpath;/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></td>
					<td>'.game::format_num($row['garage_max_cars']).'</td>
					<td>'.game::format_num($row['cars']).'</td>
					<td><b>'.game::format_num($ant).'</b></td>
					<td>'.ess::$b->date->get($row['garage_next_rent'])->format("d.m H:i").'</td>';
			}
			
			echo '
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
	</div>
</div>';
	}
}
