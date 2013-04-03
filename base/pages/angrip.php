<?php

class page_angrip extends pages_player
{
	/** Energi vi må ha for å kunne utføre drapsforsøk */
	const ENERGY_MUST_HAVE = 5000;
	
	/** Energi vi bruker når vi ikke finner en spiller */
	const ENERGY_NOT_FOUND = 1500;
	
	/**
	 * Anti-bot for angrep
	 * @var antibot
	 */
	protected $antibot;
	
	/**
	 * Skjema for angrep
	 * @var form
	 */
	protected $form;
	
	/**
	 * Anti-bot for våpentrening
	 * @var antibot
	 */
	protected $training_antibot;
	
	/**
	 * Skjema for våpentrening
	 * @var form
	 */
	protected $training_form;
	
	/**
	 * Spilleren vi skal angripe
	 * @var player
	 */
	protected $up_offer;
	
	/**
	 * Skal vi vise våpentrening?
	 */
	protected $show_training = true;
	
	/**
	 * De ulike treningsvalgene for våpentrening
	 */
	protected static $trainings = array(
		1 => array(
			"price" => 5000,
			"wait" => 120,
			"percent" => .4
		),
		array(
			"price" => 7500,
			"wait" => 300,
			"percent" => .6
		),
		array(
			"price" => 10000,
			"wait" => 900,
			"percent" => 1
		)
	);
	
	/**
	 * Maksimalt man kan øke våpentreninga<br />
	 * Denne faktoren multipliseres med prosentverdien i hvert treningsalternativ, og multipliseres så med treningsprosenten man IKKE har opptjent
	 */
	const TRAINING_MAX = 0.05;
	
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		ess::$b->page->add_title("Angrip spiller");
		login::$user->player->fengsel_require_no();
		login::$user->player->bomberom_require_no();
		
		if (!isset($_POST['wt'])) $this->page_attack_show();
		
		// vise våpentrening?
		if ($this->show_training && login::$user->player->weapon)
		{
			$this->page_training_show();
		}
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis skjema og behandle angrep på annen spiller
	 */
	protected function page_attack_show()
	{
		// kan vi ikke angripe nå?
		$lock = array(
			array(1356325200, 1356411600, "Angrepsfunksjonen er stengt på julaften frem til kl 06:00 1. juledag. Endringer i tidspunkt kan komme."), // julaften 2012 (kl 06 den 24 - kl 06 den 25)
			array(1356973200, 1357059600, "Angrepsfunksjonen er stengt på nyttårsaften frem til kl 18:00 1. januar.") // nyttår 2012-2013 (kl 18 den 31 - kl 18 den 1)
		);
		$locked = false;
		foreach ($lock as $period)
		{
			if ($period[0] <= time() && $period[1] >= time())
			{
				$locked = $period[2];
			}
		}
		if ($locked)
		{
			echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>'.$locked.'</p>
	</div>
</div>';
				
			return;
		}
		
		// har vi ikke noe våpen?
		if (!login::$user->player->weapon)
		{
			echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Du må kjøpe et våpen før du kan gjennomføre et angrep mot en annen spiller. Våpen kjøpes hos våpen og beskyttelse-firma.</p>
	</div>
</div>';
			
			return;
		}
		
		// for lav energi?
		if (!login::$user->player->energy_check(self::ENERGY_MUST_HAVE))
		{
			echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Du har ikke nok energi for å utføre et drapsforsøk for øyeblikket.</p>
	</div>
</div>';
			
			return;
		}
		
		// kan vi ikke utføre angrep nå?
		if (DISABLE_ANGREP && !access::has("mod"))
		{
			echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Funksjonen er for øyeblikket deaktivert.</p>
	</div>
</div>';
			
			return;
		}
		
		// sett opp og test for anti-bot
		$this->antibot = new antibot(login::$user->id, "angrip", 2);
		if (MAIN_SERVER) $this->antibot->check_required();
		
		// valgt spiller?
		if (isset($_POST['up']) || isset($_POST['up_id']))
		{
			$this->show_training = false;
			if ($this->player_check()) return;
		}
		
		ess::$b->page->add_js_domready('$("angrip_up").focus();');
		
		echo '
<form action="" method="post">
	<div class="bg1_c xsmall">
		<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">'.($_SERVER['REQUEST_METHOD'] == "POST" ? '
			<boxes />' : '').'
			<p>Her kan du angripe en spiller. Du må først spesifisere hvilken spiller du skal angripe. Deretter spesifiserer du antall kuler, før du faktisk forsøker å angripe spilleren.</p>
			<dl class="dd_right">
				<dt>Spiller som skal angripes</dt>
				<dd><input type="text" name="up" id="angrip_up" class="styled w80" value="'.htmlspecialchars(postval("up")).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Fortsett").'</p>
			<p class="c"><a href="node/42">Informasjon om funksjonen</a></p>
		</div>
	</div>
</form>';
	}
	
	/**
	 * Kontroller spiller
	 */
	protected function player_check()
	{
		// søke etter spiller?
		if (isset($_POST['up']))
		{
			$this->up_offer = player::get($_POST['up'], NULL, true);
		}
		
		// har ID?
		else
		{
			$this->up_offer = player::get(postval("up_id"));
		}
		
		// fant ikke spilleren?
		if (!$this->up_offer)
		{
			ess::$b->page->add_message("Fant ikke spilleren.", "error");
			return false;
		}
		
		// seg selv?
		if ($this->up_offer->id == login::$user->player->id)
		{
			ess::$b->page->add_message("Du kan ikke angripe deg selv.", "error");
			return false;
		}
		
		// død?
		if (!$this->up_offer->active)
		{
			ess::$b->page->add_message('Spilleren <user id="'.$this->up_offer->id.'" /> er ikke levende og kan ikke angripes.', "error");
			return false;
		}
		
		// angriper nostat?
		if ($this->up_offer->is_nostat() && !login::$user->player->is_nostat())
		{
			ess::$b->page->add_message('<user id="'.$this->up_offer->id.'" /> er nostat og kan ikke angripes.', "error");
			return false;
		}
		
		// nostat angriper andre?
		if (login::$user->player->is_nostat() && !$this->up_offer->is_nostat())
		{
			ess::$b->page->add_message('Du er nostat og kan derfor ikke angripe <user id="'.$this->up_offer->id.'" />.', 'error');
			return false;
		}
		
		// kan ikke angripe spillere registrert for under 1 uke siden og som ikke har nådd ridder
		$expire = time()-604800;
		if ($this->up_offer->data['up_created_time'] > $expire && $this->up_offer->rank['number'] < 8)
		{
			ess::$b->page->add_message('<user id="'.$this->up_offer->id.'" /> har vært registrert i under 7 dager med lav rank og kan ikke angripes', "error");
			return false;
		}
		
		// sett opp skjema
		$this->form = new form("angrip");
		
		// utføre et angrep?
		if (isset($_POST['attack']))
		{
			$this->handle_attack();
		}
		
		echo '
<form action="" method="post">
	<input type="hidden" name="up_id" value="'.$this->up_offer->id.'" />
	<input type="hidden" name="un" value="'.$this->form->create().'" />
	<div class="bg1_c xsmall">
		<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
		<div class="bg1">
			<p>Du er i ferd med å angripe '.$this->up_offer->profile_link().' som har ranken '.$this->up_offer->rank['name'].' og er plassert som nummer '.$this->up_offer->data['upr_rank_pos'].' på ranklista.</p>
			<p>Du befinner deg på '.login::$user->player->bydel['name'].' og har en <b>'.htmlspecialchars(login::$user->player->weapon->data['name']).'</b> med <b>'.game::format_num(login::$user->player->data['up_weapon_bullets']).'</b> '.fword('kule', 'kuler', login::$user->player->data['up_weapon_bullets']).' og en våpentrening på <b>'.game::format_num(login::$user->player->data['up_weapon_training']*100, 1).' %</b>.</p>';
		
		// har vi ingen kuler?
		if (login::$user->player->data['up_weapon_bullets'] == 0)
		{
			echo '
			<p><b>Du må kjøpe kuler før du kan utføre et angrep.</b> Kuler får du kjøpt hos våpen og beskyttelse-firmaet.</p>';
		}
		
		else
		{
			ess::$b->page->add_js_domready('$("angrep_kuler").focus();');
			
			echo '
			<dl class="dd_right">
				<dt>Antall kuler som skal benyttes</dt>
				<dd><input type="text" id="angrep_kuler" name="kuler" class="styled w40" value="'.intval(postval("kuler", "")).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Utfør angrep", 'name="attack"').'</p>';
		}
		
		echo '
			<p class="c"><a href="angrip">Avbryt</a></p>
		</div>
	</div>
</form>
<div class="bg1_c xsmall">
	<h1 class="bg1">Informasjon<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Hvis spilleren du angriper ikke befinner seg i <b>'.login::$user->player->bydel['name'].'</b>, er i <b>bomberom</b> eller <b>fengsel</b> eller hvis du rett og slett ikke klarer å oppdage spilleren, vil du miste en del energi og bli plassert i fengsel i en kort varighet.</p>
		<p>Hvis du klarer å oppdage spilleren, vil du skade spilleren. Hvis angrepet er så kraftig at spilleren dør vil du:</p>
		<ul>
			<li>Overta pengene spilleren hadde på hånda</li>
			<li>Motta en del rank, avhengig av ranken til offeret</li>
		</ul>
		<p>Hvis spilleren overlever, vil du:</p>
		<ul>
			<li>Komme i fengsel en periode</li>
			<li>Miste en god del energi</li>
			<li>Motta litt rank som spilleren du angriper mister</li>
		</ul>
		<p>Når du utfører et angrep mot en annen spiller, risikerer du å bli oppdaget av vitner. Hvis du oppdager vitnene i det du utfører angrepet, vil du også få vite hvem du oppdaget som vitnet angrepet.</p>
	</div>
</div>';
		
		return true;
	}
	
	/**
	 * Behandle angrep
	 */
	protected function handle_attack()
	{
		// kontroller skjema
		if (MAIN_SERVER) $this->form->validate(postval("un"), "Angrip spiller: {$this->up_offer->data['up_name']}");
		
		// har vi ingen kuler?
		if (login::$user->player->data['up_weapon_bullets'] == 0) return;
		
		$bullets = max(0, (int) postval("kuler"));
		
		// har vi ikke så mange kuler?
		if ($bullets > login::$user->player->data['up_weapon_bullets'])
		{
			ess::$b->page->add_message("Du har ikke så mange kuler.", "error");
			return;
		}
		
		// har ikke skrevet inn noe?
		if ($bullets == 0)
		{
			ess::$b->page->add_message("Du må fylle inn antall kuler du ønsker å benytte.", "error");
			return;
		}
		
		// er offeret i fengsel?
		if ($this->up_offer->fengsel_check())
		{
			ess::$b->page->add_message('<user id="'.$this->up_offer->id.'" /> er i fengsel og kan ikke angripes nå.', "error");
			return;
		}
		
		// oppdater tidspunkt for siste angrep
		ess::$b->db->query("UPDATE users_players SET up_df_time = ".time()." WHERE up_id = ".login::$user->player->id);
		
		// er i annen bydel, bomberom eller vi klarte ikke å finne spilleren?
		$not_found_b = $this->up_offer->data['up_b_id'] != login::$user->player->data['up_b_id'];
		$not_found_brom = $this->up_offer->bomberom_check();
		$prob = rand(1, 100);
		$find_prob = $this->up_offer->calc_find_player_prob() * 100;
		$not_found_prob = $prob > $find_prob;
		if ($not_found_b || $not_found_brom || $not_found_prob)
		{
			// logg
			if ($not_found_b)
			{
				$reason = 'Ikke i samme bydel ('.login::$user->player->bydel['name'].' mot '.$this->up_offer->bydel['name'].').';
				if ($not_found_brom) $reason .= ' Offeret er også i bomberom.';
			}
			elseif ($not_found_brom)
			{
				$reason = 'Offeret er i bomberom.';
			}
			else
			{
				$reason = 'Traff ikke på sannsynligheten ('.$prob.' > '.ceil($find_prob).').';
			}
			putlog("DF", "ANGREP FEILET: ".login::$user->player->data['up_name']." skulle angripe%c3 ".$this->up_offer->data['up_name']."%c med $bullets ".fword("kule", "kuler", $bullets).". $reason");
			
			// øk telleren over antall ganger vi ikke har funnet spiller
			ess::$b->db->query("UPDATE users_players SET up_attack_failed_num = up_attack_failed_num + 1 WHERE up_id = ".login::$user->player->id);
			
			// øk telleren over antall ganger vi ikke har funnet spiller (for familien spilleren er medlem i)
			login::$user->player->attack_ff_update("failed");
			
			// øk teller for ff for offeret
			$this->up_offer->attacked_ff_update("failed");
			
			// sett i fengsel i 2-4 minutter
			$fengsel = login::$user->player->fengsel_rank(100, true, true, rand(120, 240));
			
			// mist energi
			login::$user->player->energy_use(self::ENERGY_NOT_FOUND);
			
			// øk anti-bot
			$this->antibot->increase_counter();
			
			// trigger
			login::$user->player->trigger("attack_notfound", array(
					"not_found_b" => $not_found_b,
					"not_found_brom" => $not_found_brom,
					"not_found_prob" => $not_found_prob,
					"bullets" => $bullets,
					"up" => $this->up_offer));
			
			// vis resultat og last inn siden
			$this->attack_result_notfound_show($fengsel, $bullets);
			
			redirect::handle();
		}
		
		// angrip spilleren
		$result = login::$user->player->weapon->attack($this->up_offer, $bullets);
		
		// sett ned antall kuler spilleren har
		ess::$b->db->query("UPDATE users_players SET up_weapon_bullets = GREATEST(0, up_weapon_bullets - $bullets) WHERE up_id = ".login::$user->player->id);
		login::$user->player->data['up_weapon_bullets'] = max(0, login::$user->player->data['up_weapon_bullets'] - $bullets);
		
		// trigger
		login::$user->player->trigger("attack", array(
				"attack" => $result,
				"up" => $this->up_offer));
		
		// vis resultat og last inn siden
		$this->attack_result_show($result, $bullets);
	}
	
	/**
	 * Vis resultat fra angrep hvor spilleren ikke ble funnet
	 */
	protected function attack_result_notfound_show($fengsel, $bullets)
	{
		// fjern fengselmelding
		ess::$b->page->message_get("fengsel");
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Angrip spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Du forsøkte å angripe '.$this->up_offer->profile_link().' med ranken '.$this->up_offer->rank['name'].' og plassering nummer '.$this->up_offer->data['upr_rank_pos'].' på ranklista med '.$bullets.' '.fword("kule", "kuler", $bullets).'.</p>
		<p>Spilleren ble ikke funnet, og angrepet kunne ikke bli gjennomført.</p>
		<p>Du kom i fengsel og slipper ut om '.game::counter(login::$user->player->data['up_fengsel_time']-time()).'. Wanted nivået er nå på '.game::format_num(login::$user->player->data['up_wanted_level']/10, 1).' %.</p>
		<p class="c"><a href="angrip">Tilbake</a></p>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis resultat fra angrep
	 */
	protected function attack_result_show($result, $bullets)
	{
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">'.($result['drept'] ? 'Spiller drept' : 'Spiller skadet').'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">';
		
		// sett opp hva vi fikk
		$got = array();
		$got[] = game::format_num($result['rankpoeng']).' poeng'.($result['drept'] ? '' : ' fra spilleren');
		if (isset($result['penger']) && $result['penger'] > 0) $got[] = game::format_cash($result['penger']).' som offeret hadde på hånda';
		if (isset($result['penger_bank']) && $result['penger_bank'] > 0) $got[] = game::format_cash($result['penger_bank']).' som deler av det offeret hadde i banken';
		if (isset($result['hitlist']) && $result['hitlist'] > 0) $got[] = game::format_cash($result['hitlist']).' fra hitlista';
		$got = sentences_list($got);
		
		// vellykket?
		if ($result['drept'])
		{
			$place = bydeler::get_random_place(login::$user->player->bydel['id']);
			echo '
		<p>Du fant '.$this->up_offer->profile_link().' som hadde ranken '.$this->up_offer->rank['name'].' og var plassert som nummer '.$this->up_offer->data['upr_rank_pos'].' på ranklista'.($place ? ' ved '.$place : '').' og angrep spilleren med '.$bullets.' '.fword("kule", "kuler", $bullets).'.</p>
		<p>Spilleren døde av angrepet ditt. Du fikk '.$got.'.'.($result['penger'] == 0 ? ' Offeret hadde ingen penger på hånda.' : '').'</p>';
		}
		
		else
		{
			$place = bydeler::get_random_place(login::$user->player->bydel['id']);
			echo '
		<p>Du fant '.$this->up_offer->profile_link().' med ranken '.$this->up_offer->rank['name'].' og plassering nummer '.$this->up_offer->data['upr_rank_pos'].' på ranklista'.($place ? ' ved '.$place : '').' og angrep spilleren med '.$bullets.' '.fword("kule", "kuler", $bullets).'.</p>
		<p>Spilleren ble skadet av angrepet men overlevde. Du mottok '.$got.'.</p>';
		}
		
		// list opp vitner
		if (count($result['vitner']) == 0)
		{
			echo '
		<p>Ingen spillere vitnet '.($result['drept'] ? 'drapet' : 'drapsforsøket').'.</p>';
		}
		
		else
		{
			// sett opp liste over navngitte spillere som oppdaget det
			$list = array();
			$count_other = 0;
			foreach ($result['vitner'] as $vitne)
			{
				if ($vitne['visible']) $list[] = $vitne['up']->profile_link();
				else $count_other++;
			}
			if ($count_other > 0) $list[] = fwords("%d ukjent spiller", "%d ukjente spillere", $count_other);
			
			echo '
		<p>Du ble oppdaget av '.sentences_list($list).' da '.($result['drept'] ? 'drapet' : 'drapsforsøket').' ble gjennomført.</p>';
		}
		
		// fengselendring?
		if (isset($result['fengsel']))
		{
			// kom i fengsel?
			if ($result['fengsel'] === false)
			{
				// fjern meldingen som allerede er lagt inn
				ess::$b->page->message_get("fengsel");
				
				echo '
		<p>Du kom i fengsel og slipper ut om '.game::counter(login::$user->player->data['up_fengsel_time']-time()).'. Wanted nivået er nå på '.game::format_num(login::$user->player->data['up_wanted_level']/10, 1).' %.</p>';
			}
			
			// wanted nivået økte
			else
			{
				echo '
		<p>Wanted nivået økte med '.game::format_num($result['fengsel']/10, 1).' %.</p>';
			}
		}
		
		echo '
		<p class="c"><a href="angrip">Tilbake</a></p>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Våpentrening
	 */
	protected function page_training_show()
	{
		// sett opp og test for anti-bot
		$this->training_antibot = new antibot(login::$user->id, "training", 7);
		if (MAIN_SERVER) $this->training_antibot->check_required();
		
		// sett opp skjema
		$this->training_form = new form("training");
		
		// ventetid?
		$wait = max(0, login::$user->player->data['up_weapon_training_next'] - time());
		
		// skal vi trene våpenet?
		if (isset($_POST['wt']))
		{
			$this->training_form->validate(postval("h"), "Våpentrening");
			
			// kan vi ikke trene nå?
			if ($wait > 0)
			{
				redirect::handle();
			}
			
			// finnes ikke valget?
			$id = (int) postval("training_id");
			if (!isset(self::$trainings[$id]))
			{
				ess::$b->page->add_message("Du må velge et alternativ.", "error");
				redirect::handle();
			}
			$opt = self::$trainings[$id];
			
			// lagre valget for neste gang
			ess::session_put("training_id", $id);
			
			// har ikke nok cash?
			if ($opt['price'] > login::$user->player->data['up_cash'])
			{
				ess::$b->page->add_message("Du har ikke nok penger til å utføre våpentreningen.");
				redirect::handle();
			}
			
			$f = self::TRAINING_MAX * $opt['percent'];
			$next_old = login::$user->player->data['up_weapon_training_next'] ? ' = '.login::$user->player->data['up_weapon_training_next'] : ' IS NULL';
			
			// utfør våpentrening
			ess::$b->db->query("
				UPDATE users_players
				SET up_weapon_training = up_weapon_training + (1 - up_weapon_training) * $f, up_weapon_training_next = ".(time()+$opt['wait']).", up_cash = up_cash - {$opt['price']}
				WHERE up_id = ".login::$user->player->id." AND up_cash >= {$opt['price']} AND up_weapon_training_next$next_old");
			
			// ikke oppdatert?
			if (ess::$b->db->affected_rows() == 0)
			{
				ess::$b->page->add_message("Kunne ikke utføre våpentrening.", "error");
			}
			
			else
			{
				$this->training_antibot->increase_counter();
				ess::$b->page->add_message("Du trente opp våpenet ditt og våpentreningen økte med ".game::format_num((1 - login::$user->player->data['up_weapon_training']) * $f * 100, 2)." %.");
			}
			
			redirect::handle();
		}
		
		$training = login::$user->player->data['up_weapon_training'] * 100;
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Våpentrening<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">';
		
		if ($wait > 0)
		{
			echo '
		<p class="c">Du må vente '.game::counter($wait, true).' før du kan trene våpenet på nytt.</p>';
		}
		
		else
		{
			// vis alternativene
			echo '
		<form action="" method="post">
			<input type="hidden" name="h" value="'.$this->training_form->create().'" />
			<table class="table tablemt center">
				<thead>
					<tr>
						<th>Pris</th>
						<th>Ventetid</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			$match = ess::session_get("training_id") ?: 0;
			foreach (self::$trainings as $id => $row)
			{
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td class="r"><input type="radio" name="training_id"'.($match == $id ? ' checked="checked"' : '').' value="'.$id.'" />'.game::format_cash($row['price']).'</td>
						<td class="r">'.game::timespan($row['wait']).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Utfør", 'name="wt"').'</p>
		</form>';
		}
		
		echo '
		<div class="progressbar p'.($training < 28 ? ' levelcrit' : ($training < 35 ? ' levelwarn' : '')).'">
			<div class="progress" style="width: '.round(min(100, $training)).'%">
				<p>Våpentrening: '.($training == 100 ? '100' : game::format_num($training, 2)).' %</p>
			</div>
		</div>
		<p>Du har en <b>'.htmlspecialchars(login::$user->player->weapon->data['name']).'</b> med <b>'.game::format_num(login::$user->player->data['up_weapon_bullets']).'</b> '.fword('kule', 'kuler', login::$user->player->data['up_weapon_bullets']).'.</p>
		<p>Våpentreningen din synker jevnlig i løpet av dagen, og du er nødt til å trene for å holde oppe våpentreningen din. Hvis våpentreningen din faller under 25 %, risikerer du å miste våpenet ditt.</p>
		<p>Bedre våpentrening fører til:</p>
		<ul class="spacer">
			<li>Du forbedrer treffsikkerheten din</li>
			<li>Du øker skuddtakten samtidig som treffsikkerheten forblir den samme</li>
		</ul>
	</div>
</div>';
	}
}
