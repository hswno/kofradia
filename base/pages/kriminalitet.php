<?php

class page_kriminalitet extends pages_player
{
	/**
	 * Kriminalitet-objektet
	 * @var kriminalitet
	 */
	protected $krim;
	
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
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		$this->krim = new kriminalitet($up);
		$this->handle_page();
		
		ess::$b->page->load();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function handle_page()
	{
		$this->krim->up->fengsel_require_no();
		$this->krim->up->bomberom_require_no();
		$this->krim->up->energy_require(kriminalitet::ENERGY_KRIM*1.3); // legg til 30 % på kravet
		
		// sett opp skjema
		$this->form = new form("kriminalitet");
		
		// sett opp antibot og sjekk om den skal utføres nå
		$this->antibot = antibot::get("kriminalitet", 12);
		$this->antibot->check_required();
		
		ess::$b->page->add_title("Kriminalitet");
		
		// hent informasjon om forrige forsøk
		$this->krim->get_last_info();
		
		// hent inn alternativene
		$this->krim->options_load();
		
		// utføre handling?
		if (isset($_POST['theid']))
		{
			$this->utfor();
		}
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Kriminalitet<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_right"><a href="'.ess::$s['rpath'].'/node/3">Hjelp</a></p>
	<div class="bg1" style="padding-bottom: 1em">
		<p>Her kan du gjøre enkle forbrytelser. Type forbrytelse er forskjellig fra bydel til bydel. Jo flere ganger du utfører handlingen vil du få en høyere sannsynlighet for å klare det.</p>';
		
		// siste utført?
		if ($this->krim->last)
		{
			echo '
		<p>Du utførte kriminalitet sist den '.ess::$b->date->get($this->krim->last['last'])->format(date::FORMAT_SEC).' på '.game::$bydeler[$this->krim->last['b_id']]['name'].'.</p>';
		}
		
		echo '
		<boxes />';
		
		// er det noe ventetid?
		if ($this->krim->wait)
		{
			echo '
		<p>Du må vente '.game::counter($this->krim->wait, true).' før du kan utføre kriminalitet på nytt.</p>';
		}
		
		// har vi ingen alternativer?
		if (count($this->krim->options) == 0)
		{
			echo '
		<p>Det er ingen alternativer å utføre i denne bydelen. Prøv en annen bydel.</p>';
		}
		
		// vis alternativene
		else
		{
			ess::$b->page->add_css('
.krim_boks {
	clear: both;
	position: relative;
	padding: 0 0 0 85px;
	border: 1px solid #232323;
	background-color: #222222;
	margin: 0.7em 0 0;
	background-repeat: no-repeat;
	background-position: 0;
	height: 75px;
}
.krim_boks p, .krim_boks h4 { margin: 0; padding: 0; position: absolute }
.krim_boks p { color: #CCCCCC }
.krim_boks.krim_color {
	background-color: #262626;
}
.krim_boks.krim_last {
	background-color: #2D1E1E;
}
.krim_boks .krim_img {
	float: left;
	margin-right: 5px;
}
.krim_boks h4 {
	top: 8px;
}
.krim_strength {
	bottom: 23px;
}
.krim_rank {
	bottom: 23px;
	right: 8px;
}
.krim_info {
	bottom: 8px;
}
.krim_wait {
	right: 8px;
	bottom: 8px;
}
.krim_cash {
	right: 8px;
	top: 8px;
	font-weight: bold;
}');
			
			if ($this->krim->wait)
			{
				ess::$b->page->add_js_domready('
	$$("div.krim_boks input").setStyle("display", "none");');
			}
			
			else
			{
				ess::$b->page->add_js_domready('
	$$("div.krim_boks").each(function(elm)
	{
		elm.setStyle("cursor", "pointer");
		elm.addEvents({
			"mouseover": function()
			{
				this.setStyle("background-color", "#181818");
			},
			"mouseout": function()
			{
				this.setStyle("background-color", "");
			},
			"click": function()
			{
				$("theid")
					.set("value", this.get("rel"))
					.form.submit();
			}
		});
		elm.set("title", "Klikk for å utføre");
		elm.getElement("input").setStyle("display", "none");
	});');
			}
			
			echo '
		<form action="" method="post">
			<input type="hidden" name="hash" value="'.$this->form->create().'" />
			<input type="hidden" name="theid" value="" id="theid" />';
			
			$i = 0;
			$show_id = ess::session_get("krim_last_id");
			foreach ($this->krim->options as $row)
			{
				$rank = game::format_num($row['points']);
				
				echo '
			<div class="krim_boks'.(++$i % 2 == 0 ? ' krim_color' : '').($show_id == $row['id'] ? ' krim_last' : '').'" style="background-image: url('.STATIC_LINK.'/krim/'.(empty($row['img']) ? 'none.png' : $row['img']).')" rel="'.$row['id'].'">
				<h4>
					<input type="submit" name="id'.$row['id'].'" value="Utfør" />
					'.htmlspecialchars($row['name']).'
				</h4>
				<p class="krim_strength">'.game::format_num(round($row['prob']*100, 1), 1).' % sannsynlighet</p>
				<p class="krim_rank">Poeng: '.$rank.'</p>
				<p class="krim_info">'.game::format_number($row['success']).' av '.game::format_number($row['count']).' vellykkede forsøk ('.($row['count'] == 0 ? '0' : game::format_number($row['success']/$row['count']*100, 1)).' %)</p>
				<p class="krim_wait">Ventetid: '.$row['wait_time'].' sek.</p>
				<p class="krim_cash">'.game::format_cash($row['cash_min']).' til '.game::format_cash($row['cash_max']).'</p>
			</div>';
			}
			
			echo '
		</form>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Utfør kriminalitet
	 */
	protected function utfor()
	{
		// form sjekking
		$this->form->validate(postval('hash'), ($this->krim->last ? "Siste=".game::timespan($this->krim->last['last'], game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD).";" : "First;").($this->krim->wait ? "%c11Ventetid=".game::timespan($this->krim->wait, game::TIME_SHORT | game::TIME_NOBOLD)."%c" : "%c9No-wait%c"));
		
		// kontroller at vi ikke har noe ventetid
		if ($this->krim->wait)
		{
			redirect::handle();
		}
		
		// finn id
		$id = intval(postval("theid"));
		if (!$id)
		{
			$found = false;
			
			foreach ($_POST as $name => $val)
			{
				$matches = false;
				if (preg_match("/^id([1-9]+|[1-9][0-9]+)$/D", $name, $matches))
				{
					$id = $matches[1];
				}
			}
		}
		
		// har ikke oppføringen?
		if (!isset($this->krim->options[$id]))
		{
			ess::$b->page->add_message("Ugyldig valg.", "error");
			redirect::handle();
		}
		
		// lagre valget
		ess::session_put("krim_last_id", $id);
		
		// utfør kriminalitet
		$result = $this->krim->utfor($id);
		
		$fengsel_msg = $result['wanted_change'] > 0 ? ' Wanted nivået økte med '.game::format_number($result['wanted_change']/10, 1).' %.' : '';
		$msg = $result['success']
			? $this->krim->get_random_message($id, true, $result['cash'], $result['rank'])
			: $this->krim->get_random_message($id, false);
		
		ess::$b->page->add_message($msg.$fengsel_msg);
		
		// oppdater anti-bot
		$this->antibot->increase_counter();
			
		// oppdater siden
		redirect::handle();
	}
}