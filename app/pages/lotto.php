<?php

class page_lotto
{
	/** Energi lottofunksjonen krever */
	const ENERGY = 100;
	
	/**
	 * Skjema for lotto
	 * @var form
	 */
	protected $form;
	
	/**
	 * Anti-bot for lotto
	 * @var antibot
	 */
	protected $antibot;
	
	/**
	 * Aktiv spiller
	 * @var player
	 */
	protected $up;
	
	/** Tidspunkt for neste trekning */
	protected $next;
	
	/** Siste gang vi kjøpte lodd */
	protected $last;
	
	/** Ventetid før vi kan kjøpe lodd */
	protected $wait;
	
	/** Aktiv runde? */
	protected $active;
	
	/** Informasjon om nåværende lottorunde */
	protected $info;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		kf_menu::$data['lotto'] = true;
		
		login::$user->player->fengsel_require_no();
		login::$user->player->bomberom_require_no();
		login::$user->player->energy_require(self::ENERGY * 1.3); // legg til 30 % på kravet
		
		ess::$b->page->add_title("Lotto");
		
		$this->form = \Kofradia\Form::getByDomain("lotto", login::$user);
		$this->antibot = antibot::get_default();
		$this->antibot->check_required();
		
		// sjekk for aktiv runde, ventetid osv
		$this->check_active();
		
		// ber vi om å kjøpe lodd?
		if (isset($_POST['lodd']))
		{
			$this->lodd_kjop();
		}
		
		$this->show_page();
		ess::$b->page->load();
	}
	
	/**
	 * Vis lotto
	 */
	protected function show_page()
	{
		echo '
<div class="col2_w" style="margin: 40px"> 
	<div class="col_w left"> 
		<div class="col" style="margin-right: 20px">
			<div class="bg1_c" id="lotto">
				<h1 class="bg1">Lotto<span class="left"></span><span class="right"></span></h1>
				<div class="bg1">
					<p class="c">Neste trekning: '.game::counter($this->next, true).'.</p>';
		
		if ($this->active)
		{
			// kan vi kjøpe lodd?
			if ($this->info['antall_lodd'] < lotto::$lodd_maks)
			{
				$antall = min(lotto::$lodd_maks_om_gangen, floor(login::$user->player->data['up_cash']/lotto::get_lodd_price()));
				
				echo '
					<form action="" method="post">'.$this->form->getHTMLInput().'<input type="hidden" name="b" value="Gjenstående lodd" /><input type="hidden" name="lodd" value="'.$antall.'" /></form>'.(time() < lotto::PRICE_CHANGE_TIME+43200 ? '
					<p class="c">Pris per lodd '.(time() < lotto::PRICE_CHANGE_TIME ? 'blir' : 'ble').' økt til <span style="color: #DD3333">'.game::format_cash(lotto::PRICE).'</span> kl. '.ess::$b->date->get(lotto::PRICE_CHANGE_TIME)->format("H:i").'</p>' : '').'
					<form action="" method="post">
						'.$this->form->getHTMLInput().'
						<dl class="dd_right center" style="width: 80%">
							<dt>Gjenstående lodd</dt>
							<dd>'.game::format_number(lotto::$lodd_maks-$this->info['antall_lodd']).'</dd>
							<dt>Antall kjøpt</dt>
							<dd>'.game::format_number($this->info['antall_lodd']).'</dd>
							<dt>Pris per lodd</dt>
							<dd>'.game::format_cash(lotto::get_lodd_price()).'</dd>'.($this->wait > 0 ? '
							<dt>Må vente</dt>
							<dd style="color: #FF0000">'.game::counter($this->wait, true).'</dd>' : '
							<dt>Ventetid</dt>
							<dd>'.game::timespan(lotto::$ventetid, game::TIME_FULL).'</dd>
						</dl>
						<dl class="dd_right dl_2x center" style="width: 80%">
							<dt>Antall lodd</dt>
							<dd><input type="text" value="'.$antall.'" class="styled w40 r" name="lodd" maxlength="3" /></dd>
							<dd>'.show_sbutton("Kjøp lodd").'</dd>').'
						</dl>
					</form>';
			}
		}
		
		else
		{
			echo '
					<p class="c">Lottorunden er for øyeblikket ikke aktiv.</p>';
		}
		
		echo '
					<p class="c"><a href="lotto_vinn">Min historie</a> | <a href="node/25">Informasjon om funksjonen</a></p>
				</div>
			</div>
			<div class="bg1_c" style="margin-top: 20px">
				<h1 class="bg1">Informasjon om lottorunden<span class="left"></span><span class="right"></span></h1>
				<div class="bg1">
					<dl class="dd_right">
						<dt>Antall lodd solgt totalt denne runden</dt>
						<dd>'.game::format_number($this->info['totalt_lodd']).'</dd>
						<dt>Antall spillere som har kjøpt lodd</dt>
						<dd>'.game::format_number($this->info['brukere']).'</dd>
						<dt><b>Potten</b></dt>
						<dd><b>'.game::format_cash($this->info['pott']).'</b></dd>
					</dl>
				</div>
			</div>
			<div class="bg1_c" style="margin-top: 20px">
				<h1 class="bg1">Gevinster<span class="left"></span><span class="right"></span></h1>
				<div class="bg1">
					<table class="table tablem" width="100%">
						<tbody>';
		
		$i = 0;
		foreach (lotto::$premier as $premie)
		{
			echo '
							<tr'.(is_int($i/2) ? ' class="color"' : '').'>
								<td>'.($i+1).'. plass</td>
								<td class="r"><b style="color: #55AA55">'.game::format_num($premie[0]).'</b> poeng</td>
								<td class="r"><b style="color: #F9E600">'.game::format_number($premie[1]*100, 0).' %</b> av potten</td>
							</tr>';
			$i++;
		}
		
		echo '
						</tbody>
					</table>
					<p class="c">Poengene tar utgangspunkt i at '.lotto::PLAYERS_TOP.' spillere eller flere deltar. Ved færre deltakere vil poengene bli redusert.</p>
				</div>
			</div>
		</div> 
	</div> 
	<div class="col_w right">
		<div class="col" style="margin-left: 20px">
			<div class="bg1_c">
				<h1 class="bg1">Siste trekninger<span class="left"></span><span class="right"></span></h1>
				<div class="bg1">';
		
		// hent de siste trekningene
		$result = \Kofradia\DB::get()->query("SELECT CEILING((time-900)/1800)*1800+900 FROM lotto_vinnere GROUP BY CEILING((time-900)/1800)*1800+900 ORDER BY time DESC LIMIT 4");
		if ($result->rowCount() == 0)
		{
			echo '
					<p>Ingen trekninger har blitt gjennomført.</p>';
		}
		
		else
		{
			$row = $result->fetch(\PDO::FETCH_NUM);
			$last = $row[0];
			do {
				$first = $row[0] - 1800;
			} while ($row = $result->fetch(\PDO::FETCH_NUM));
			
			// hent vinnerene
			$result = \Kofradia\DB::get()->query("SELECT lv_up_id, time, won, total_lodd, total_users, type FROM lotto_vinnere WHERE time >= $first AND time < $last ORDER BY type");
			$rounds = array();
			
			// legg i riktig gruppe
			while ($row = $result->fetch())
			{
				$end = ceil(($row['time']-900)/1800)*1800 + 900;
				
				if (!isset($rounds[$end]))
				{
					$rounds[$end] = array(
						"time" => $end,
						"total_lodd" => $row['total_lodd'],
						"total_users" => $row['total_users'],
						"users" => array()
					);
				}
				
				$rounds[$end]['users'][$row['type']] = array($row['lv_up_id'], $row['won']);
			}
			krsort($rounds);
			
			foreach ($rounds as $round)
			{
				echo '
					<div class="section">
						<h1>'.ess::$b->date->get($round['time'])->format().'</h1>
						<p class="h_right">'.game::format_number($round['total_lodd']).' lodd, '.game::format_number($round['total_users']).' spiller'.($round['total_users'] == 1 ? '' : 'e').'</p>
						<dl class="dd_right">';
				
				foreach ($round['users'] as $num => $row)
				{
					echo '
							<dt>'.$num.' - <user id="'.$row[0].'" /></dt>
							<dd>'.game::format_cash($row[1]).'</dd>';
				}
				
				echo '
						</dl>
					</div>';
			}
			
			echo '
				<p class="c"><a href="lotto_trekninger">Vis utvidet oversikt</a></a>';
		}
		
		echo '
				</div>
			</div>
		</div>
	</div>
</div>';
	}
	
	/**
	 * Sjekk for aktiv runde, ventetid osv
	 */
	protected function check_active()
	{
		// finn ut om vi er i en aktiv periode (kan kjøpe lodd) og når neste trekning skjer
		$date = ess::$b->date->get();
		$this->active = ($date->format("i")/2 % 15) != 7;
		
		$offset_now = $date->format("i")*60+$date->format("s");
		$this->next = ($offset_now >= 2700 ? 4500-$offset_now : ($offset_now >= 900 ? 2700-$offset_now : 900-$offset_now));
		
		// hent informasjon om lottorunden og hvor mange lodd vi har
		$result = \Kofradia\DB::get()->query("SELECT COUNT(id), COUNT(IF(l_up_id = ".login::$user->player->id.", 1, NULL)), COUNT(id) * ".lotto::get_lodd_price().", COUNT(DISTINCT l_up_id) FROM lotto");
		$row = $result->fetch(\PDO::FETCH_NUM);
		$this->info = array(
			"antall_lodd" => $row[1],
			"totalt_lodd" => $row[0],
			"pott" => $row[2],
			"brukere" => $row[3]
		);
		
		$this->last = 0;
		$this->wait = 0;
		if ($this->info['antall_lodd'] > 0)
		{
			$result = \Kofradia\DB::get()->query("SELECT time FROM lotto WHERE l_up_id = ".login::$user->player->id." ORDER BY id DESC LIMIT 1");
			if ($result->rowCount() > 0)
			{
				$this->last = $result->fetchColumn(0);
				$this->wait = $this->last - time() + lotto::$ventetid;
				if ($this->wait < 0) $this->wait = 0;
			}
		}

		// correct time if it ends in the lock-time
		if ($this->wait > $this->next - 60)
		{
			$this->wait = $this->next + 60;
		}
	}
	
	/**
	 * Kjøpe lodd
	 */
	protected function lodd_kjop()
	{
		// nostat?
		if (access::is_nostat() && !access::has("sadmin") && MAIN_SERVER)
		{
			ess::$b->page->add_message("Du har ikke tilgang til å spille lotto. (NoStat)", "error");
			redirect::handle();
		}
		
		if (!$this->form->validateHashOrAlert(null, ($this->last > 0 ? "Previous=".game::timespan($this->last, game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD).";" : "First;").($this->active ? "Active;" : "NOT-ACTIVE;").($this->wait ? "%c11Ventetid=".game::timespan($this->wait, game::TIME_SHORT | game::TIME_NOBOLD)."%c" : "%c9No-wait%c")))
		{
			return;
		}
		
		if (isset($_POST['b']))
		{
			global $__server;
			putlog("ABUSE", "Trolig bot: ".login::$user->player->data['up_name']." - Skjult skjema sendt (Lotto) SID=".login::$info['ses_id']." ".$__server['path']."/min_side?up_id=".login::$user->player->id);
		}
		
		// ikke aktiv?
		if (!$this->active)
		{
			ess::$b->page->add_message("Lottoen er ikke aktiv for øyeblikket!", "error");
			redirect::handle();
		}
		
		// ventetid?
		if ($this->wait > 0)
		{
			ess::$b->page->add_message('Du må vente '.game::counter($this->wait, true).' før du kan kjøpe nye lodd!', "error");
			redirect::handle();
		}
		
		$lodd = intval($_POST['lodd']);
		
		// ikke gyldig?
		if ($lodd < 1)
		{
			ess::$b->page->add_message("Du må minimum kjøpe ett lodd!", "error");
			redirect::handle();
		}
		
		// for mange lodd?
		if ($lodd > lotto::$lodd_maks_om_gangen)
		{
			ess::$b->page->add_message("Du kan maks kjøpe ".game::format_number(lotto::$lodd_maks_om_gangen)." lodd på en gang!", "error");
			redirect::handle();
		}
		
		// kan vi kjøpe så mange lodd?
		if ($lodd > lotto::$lodd_maks - $this->info['antall_lodd'])
		{
			ess::$b->page->add_message("Du kan ikke kjøpe så mange lodd!", "error");
			redirect::handle();
		}
		
		$lodd_price = lotto::get_lodd_price();
		$cost = $lodd * $lodd_price;
		
		// trekk fra pengene
		$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - ($lodd * ".$lodd_price.") WHERE up_id = ".login::$user->player->id." AND up_cash >= ($lodd * ".$lodd_price.")");
		if ($a == 0)
		{
			ess::$b->page->add_message("Du har ikke nok penger på hånda!", "error");
			redirect::handle();
		}
		
		// gi loddene til brukeren
		$q = array();
		$time = time();
		for ($i = 0; $i < $lodd; $i++) $q[] = "(".login::$user->player->id.", $time)";
		\Kofradia\DB::get()->exec("INSERT INTO lotto (l_up_id, time) VALUES ".implode(",", $q));
		
		// energi
		login::$user->player->energy_use(self::ENERGY);
		
		ess::$b->page->add_message("Du har kjøpt <b>".game::format_number($lodd)."</b> lottolodd for <b>".game::format_cash($lodd * $lodd_price)."</b>!");
		$this->antibot->increase_counter();
		
		redirect::handle();
	}
}