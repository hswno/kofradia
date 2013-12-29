<?php

class page_fengsel
{
	/**
	 * Spilleren
	 * @var player
	 */
	protected $up;
	
	/**
	 * Energi for å bryte ut folk
	 */
	const ENERGY = 100;
	
	/**
	 * Penger for å bryte ut (omvendt proposjonal med sannsynligheten)
	 */
	const CASH_MAX = 20000;
	
	/**
	 * Hvor mye vi får av dusøren
	 */
	const DUSOR_PROFIT = 0.9; // 90 % (10 % går ut)
	
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		$this->up = $up;
		
		ess::$b->page->add_title("Fengsel");
		
		$this->up->bomberom_require_no();
		
		// behandle siden
		$this->handle();
		
		ess::$b->page->load();
	}
	
	/**
	 * Behandle siden
	 */
	protected function handle()
	{
		// skal vi sette oss i fengsel?
		if (isset($_POST['time']) && (!MAIN_SERVER || (access::is_nostat() && access::has("mod"))))
		{
			$this->in();
		}
		
		// skal vi gå ut av fengsel?
		if (isset($_POST['remove']) && (!MAIN_SERVER || (access::is_nostat() && access::has("mod"))))
		{
			$this->out();
		}
		
		// sette dusør?
		if (isset($_POST['dusor']) && validate_sid())
		{
			$this->dusor();
		}
		
		// skal vi forsøke å bryte ut av fengsel?
		if (isset($_POST['up_id']))
		{
			$this->bryt_ut();
		}
		
		// vis fengsel
		$this->show();
	}
	
	/**
	 * Gå i fengsel
	 */
	protected function in()
	{
		// allerede i fengsel?
		if ($this->up->fengsel_check())
		{
			ess::$b->page->add_message("Du er allerede i fengsel.", "error");
			redirect::handle();
		}
		
		$time = intval(postval("time", 10));
		
		// ugyldig tid
		if ($time <= 0)
		{
			ess::$b->page->add_message("Du må skrive et positivt tall.", "error");
		}
		elseif ($time > 900)
		{
			ess::$b->page->add_message("Du kan ikke sette deg selv i fengsel for mer enn 15 minutter.", "error");
		}
		else
		{
			// sett i fengsel
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_fengsel_time = ".(time()+$time)." WHERE up_id = ".$this->up->id);
			ess::$b->page->add_message("Du er nå i fengsel.");
			redirect::handle();
		}
	}
	
	/**
	 * Gå ut av fengsel
	 */
	protected function out()
	{
		// ikke i fengsel?
		if (!$this->up->fengsel_check())
		{
			ess::$b->page->add_message("Du er ikke i fengsel.", "error");
			redirect::handle();
		}
		
		\Kofradia\DB::get()->exec("UPDATE users_players SET up_fengsel_time = ".time()." WHERE up_id = ".$this->up->id);
		ess::$b->page->add_message("Du er nå ute av fengsel.");
		redirect::handle();
	}
	
	/**
	 * Sette dusør for å bli brytet ut
	 */
	protected function dusor()
	{
		// er vi ikke i fengsel?
		if (!$this->up->fengsel_check())
		{
			ess::$b->page->add_message("Du er ikke i fengsel.", "error");
			redirect::handle();
		}
		
		$dusor = game::intval(postval("amount"));
		$expire = (int) postval("expire");
		
		// nostat?
		if ($this->up->is_nostat())
		{
			ess::$b->page->add_message("Du er nostat og kan ikke sette dusør på deg selv.", "error");
			redirect::handle();
		}
		
		if ($dusor < 0)
		{
			ess::$b->page->add_message("Ugyldig dusør.", "error");
			redirect::handle();
		}
		
		// ikke endret?
		if ($dusor == $this->up->data['up_fengsel_dusor'])
		{
			ess::$b->page->add_message("Dusøren ble ikke endret.", "error");
			redirect::handle();
		}
		
		// ikke riktig tid?
		if ($this->up->data['up_fengsel_time'] != $expire)
		{
			ess::$b->page->add_message('Tidspunktet for hvor lenge du skal være i fengsel har forandret seg. Prøv igjen.', "error");
			redirect::handle();
		}
		
		// for liten dusør?
		if ($dusor < 10000 && $dusor != 0)
		{
			ess::$b->page->add_message("Minste dusør du kan legge ut er på 10 000 kr.", "error");
			redirect::handle();
		}
		
		// har vi ikke så mye penger?
		if ($dusor > $this->up->data['up_cash']+$this->up->data['up_fengsel_dusor'])
		{
			ess::$b->page->add_message("Du har ikke så mye penger på hånda.", "error");
			redirect::handle();
		}
		
		// forsøk å sett dusøren
		$a = \Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_cash = up_cash - $dusor + up_fengsel_dusor, up_fengsel_dusor = $dusor
			WHERE up_id = {$this->up->id} AND up_fengsel_time = {$this->up->data['up_fengsel_time']} AND up_cash >= GREATEST(0, $dusor - up_fengsel_dusor)");
		
		// ble ikke endret?
		if ($a == 0)
		{
			ess::$b->page->add_message("Dusøren kunne ikke bli endret. Prøv på nytt.", "error");
		}
		
		// ble satt til 0?
		elseif ($dusor == 0)
		{
			ess::$b->page->add_message("Du fjernet dusøren og fikk tilbake ".game::format_cash($this->up->data['up_fengsel_dusor']).".");
		}
		
		elseif ($this->up->data['up_fengsel_dusor'] == 0)
		{
			ess::$b->page->add_message("Du satt en dusør for å bryte deg ut på ".game::format_cash($dusor).".");
		}
		
		else
		{
			ess::$b->page->add_message("Du endret dusøren fra ".game::format_cash($this->up->data['up_fengsel_dusor'])." til ".game::format_cash($dusor).".");
		}
		
		redirect::handle();
	}
	
	/**
	 * Bryte ut fra fengsel
	 */
	protected function bryt_ut()
	{
		// allerede i fengsel?
		if ($this->up->fengsel_check())
		{
			ess::$b->page->add_message("Du er allerede i fengsel.", "error");
			redirect::handle();
		}
		
		// har vi ikke nok energi?
		if (!$this->up->energy_check(self::ENERGY))
		{
			ess::$b->page->add_message("Du har ikke nok energi for å bryte ut andre spillere nå.");
			redirect::handle();
		}
		
		$time = intval(postval('time'));
		
		// hent informasjon
		@list($up_id, $expire, $dusor) = explode("_", $_POST['up_id']."_", 3);
		$up_id = intval($up_id);
		$expire = intval($expire);
		$dusor = game::intval($dusor);
		
		$up = player::get($up_id);
		if (!$up)
		{
			ess::$b->page->add_message("Fant ikke brukeren.", "error");
			redirect::handle();
		}
		
		// ikke i fengsel lengre?
		$wait = $up->fengsel_wait();
		if ($wait == 0)
		{
			ess::$b->page->add_message('<user id="'.$up->id.'" /> er nok allerede brutt ut!', "error");
			redirect::handle();
		}
		
		// ikke riktig tid?
		if ($up->data['up_fengsel_time'] != $expire)
		{
			ess::$b->page->add_message('<user id="'.$up->id.'" /> har kommet i fengsel på nytt. Prøv igjen.', "error");
			redirect::handle();
		}
		
		// feil dusør?
		if ($up->data['up_fengsel_dusor'] != $dusor)
		{
			ess::$b->page->add_message('Dusøren til <user id="'.$up->id.'" /> har endret seg. Prøv på nytt.', "error");
			redirect::handle();
		}
		
		// sett opp sannsynlighet
		$prob = self::calc_prob($wait, $up->data['up_wanted_level']/10);
		$points = self::calc_points($prob);
		
		// sett opp dusør
		$dusor_org = $up->data['up_fengsel_dusor'];
		$dusor = bcmul($up->data['up_fengsel_dusor'], self::DUSOR_PROFIT);
		
		// klarte vi det?
		$success = rand(0, 999) < $prob * 10;
		if ($success)
		{
			// penger man får for utbrytelsen
			$cash = round(max(0, 100 - $prob) / 100 * self::CASH_MAX);
			
			// sett som utbrytet
			$a = \Kofradia\DB::get()->exec("
				UPDATE users_players
				SET up_fengsel_time = ".(time()-1).", up_fengsel_dusor_total_out = up_fengsel_dusor_total_out + up_fengsel_dusor, up_fengsel_dusor = 0
				WHERE up_id = {$up->id} AND up_fengsel_time = {$up->data['up_fengsel_time']} AND up_fengsel_dusor = {$up->data['up_fengsel_dusor']}");
			if ($a == 0)
			{
				ess::$b->page->add_message('<user id="'.$up->id.'" /> er nok allerede brutt ut!', "error");
				redirect::handle();
			}
			$up->data['up_fengsel_time'] = time() - 1;
			$up->data['up_fengsel_dusor_total_out'] = bcadd($up->data['up_fengsel_dusor_total_out'], $up->data['up_fengsel_dusor']);
			$up->data['up_fengsel_dusor'] = 0;
			
			// oppdater antall utbrytninger og gi evt. penger
			\Kofradia\DB::get()->exec("
				UPDATE users_players
				SET up_fengsel_num_out_tries = up_fengsel_num_out_tries + 1, up_fengsel_num_out_success = up_fengsel_num_out_success + 1, up_cash = up_cash + $cash + $dusor, up_fengsel_dusor_total_in = up_fengsel_dusor_total_in + $dusor
				WHERE up_id = ".$this->up->id);
			
			$this->up->update_money(bcadd($cash, $dusor), true, false);
			
			// hendelse for spilleren som ble brutt ut
			$up->add_log("fengsel", ($dusor_org > 0 ? $dusor_org : null), $this->up->id);
			
			$fengsel = $this->up->fengsel_rank($points, true);
			
			// penger, dusør og poeng vi mottar
			$mottok = array();
			if ($cash > 0) $mottok[] = game::format_cash($cash);
			if ($dusor > 0) $mottok[] = "dusøren på ".game::format_cash($dusor);
			$mottok[] = game::format_num($points).' poeng';
			
			// melding
			$msg = 'Du brøt ut <user id="'.$up->id.'" /> fra fengselet og mottok '.sentences_list($mottok).'.';
			if ($fengsel > 0) $msg .= ' Wanted nivået økte med '.game::format_number($fengsel/10, 1).' %.';
			ess::$b->page->add_message($msg);
			
			// logg
			putlog("LOG", "FENGSELUTBRYTNING: {$this->up->data['up_name']} brøt ut {$up->data['up_name']} fra fengsel (wait=$wait, cash=$cash, dusør=$dusor_org, prob=$prob, rank=$points)");
			
			// rank
			$this->up->increase_rank($points);
		}
		
		else
		{
			// mislykket
			$fengsel = $this->up->fengsel_rank($points, false, true);
			
			// oppdater antall utbrytninger (kun forsøk)
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_fengsel_num_out_tries = up_fengsel_num_out_tries + 1 WHERE up_id = ".$this->up->id);
			
			if ($fengsel > 0)
			{
				ess::$b->page->add_message('Mislykket! Wanted nivået økte med '.game::format_number($fengsel/10, 1).' %.');
			}
		}
		
		// trigger
		$this->up->trigger("fengsel", array(
				"success" => $success,
				"up" => $up,
				"wait" => $wait,
				"prob" => $prob,
				"points" => $points,
				"cash" => $success ? $cash : null,
				"dusor" => $dusor,
				"dusor_org" => $dusor_org));
		
		$up->trigger("fengsel_affected", array(
				"success" => $success,
				"up" => $this->up,
				"wait" => $wait,
				"prob" => $prob,
				"points" => $points,
				"cash" => $success ? $cash : null,
				"dusor" => $dusor,
				"dusor_org" => $dusor_org));
		
		// energy
		$this->up->energy_use(self::ENERGY);
		
		redirect::handle();
	}
	
	/**
	 * Vis fengsel
	 */
	protected function show()
	{
		// er vi i fengsel nå?
		if ($wait = $this->up->fengsel_wait())
		{
			ess::$b->page->add_js_domready('$("fengsel_dusor").focus();');
			
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Du er i fengsel<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="node/16">Hjelp</a></p>
	<div class="bg1">
		<p>Du befinner deg for øyeblikket i fengsel og slipper ut om '.game::counter($wait, true).'.</p>'.(!$this->up->is_nostat() ? '
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="expire" value="'.$this->up->data['up_fengsel_time'].'" />
			<dl class="dd_right">
				<dt>Dusør for å bryte deg ut</dt>
				<dd><input type="text" class="styled w80" name="amount" id="fengsel_dusor" value="'.game::format_cash($this->up->data['up_fengsel_dusor']).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton($this->up->data['up_fengsel_dusor'] > 0 ? "Endre dusør" : "Sett dusør", 'name="dusor"').'</p>
			<p class="c">Spilleren som bryter ut mottar kun '.(self::DUSOR_PROFIT*100).' % av dusøren.</p>
		</form>' : '').'
	</div>
</div>';
		}
		
		// sortering
		$sort = new sorts("sort");
		$sort->append("asc", "Spiller", "up_name");
		$sort->append("desc", "Spiller", "up_name DESC");
		$sort->append("asc", "Wanted nivå", "up_wanted_level, up_fengsel_time DESC");
		$sort->append("desc", "Wanted nivå", "up_wanted_level DESC, up_fengsel_time DESC");
		$sort->append("asc", "Tid igjen", "up_fengsel_time");
		$sort->append("desc", "Tid igjen", "up_fengsel_time DESC");
		$sort->set_active(requestval("sort"), 5);
		
		// hent folk i fengsel
		$sort_info = $sort->active();
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 15);
		$result = $pagei->query("
			SELECT up_id, up_name, up_access_level, up_fengsel_time, up_fengsel_num, up_fengsel_dusor, ROUND(up_fengsel_dusor * ".self::DUSOR_PROFIT.") up_fengsel_dusor_get, up_wanted_level
			FROM users_players
			WHERE up_fengsel_time > ".time()." AND up_access_level != 0
			ORDER BY {$sort_info['params']}");
		
		$num = $result->rowCount();
		
		echo '
<div class="bg1_c '.($num == 0 ? 'xsmall' : 'xlarge').'">
	<h1 class="bg1">Fengsel<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="node/16">Hjelp</a></p>
	<div class="bg1">
		<form action="" method="post">
			<p class="c dark">Ditt wanted nivå er på '.game::format_number($this->up->data['up_wanted_level']/10, 1).' %.</p>';
		
		if ($num == 0)
		{
			echo '
			<p class="c dark">Ingen er i fengselet for øyeblikket.</p>
			<p class="c"><a href="'.htmlspecialchars(game::address("fengsel", $_GET)).'" class="button">Oppdater</a></p>';
		}
		
		else
		{
			echo '
			<table class="table center" width="100%">
				<thead>
					<tr>
						<th>Spiller '.$sort->show_link(0, 1).'</th>
						<th>Wanted<br />nivå '.$sort->show_link(2, 3).'</th>
						<th>Utbrytning<br />sannsynlighet</th>
						<th>Ca. poeng</th>
						<th>Dusør</th>
						<th>Tid igjen '.$sort->show_link(4, 5).'</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			while ($row = $result->fetch())
			{
				$prefix = "";
				$attr = new attr("class");
				if (++$i % 2 == 0) $attr->add("color");
				
				if (!$this->up->fengsel_check())
				{
					$attr->add("box_handle");
					$prefix = '<input type="radio" name="up_id" value="'.$row['up_id'].'_'.$row['up_fengsel_time'].'_'.$row['up_fengsel_dusor'].'" /> ';
				}
				
				$time = $row['up_fengsel_time']-time();
				$prob = self::calc_prob($time, $row['up_wanted_level']/10);
				$points = self::calc_points($prob);
				
				echo '
					<tr'.$attr->build().'>
						<td>'.$prefix.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td class="c">'.game::format_number($row['up_wanted_level']/10, 1).' %</td>
						<td class="c">'.game::format_number($prob, 1).' %</td>
						<td class="c">'.game::format_num($points).'</td>
						<td class="r nowrap">'.game::format_cash($row['up_fengsel_dusor_get']).'</td>
						<td class="r">'.game::counter($time).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.(($wait = $this->up->fengsel_wait()) == 0 ? '
				'.show_sbutton("Bryt ut", 'name="brytut"') : '
				Du er i fengsel og slipper ut om '.game::counter($wait, true).'.
			</p>
			<p class="c">').'
				<a href="'.htmlspecialchars(game::address("fengsel", $_GET)).'" class="button">Oppdater</a>
			</p>';
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
			<div class="hr"></div>
			<p class="c">
				'.$pagei->pagenumbers().'
			</p>';
			}
		}
		
		
		echo '
		</form>
	</div>
</div>';
		
		// testing
		if (!MAIN_SERVER || (access::is_nostat() && access::has("mod")))
		{
			echo '
<div class="bg1_c xxsmall bg1_padding">
	<h1 class="bg1">'.(MAIN_SERVER ? 'No-stat' : 'Testing').'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<form action="" method="post">'.($this->up->fengsel_check() ? '
			<p class="c">'.show_sbutton("Gå ut av fengsel", 'name="remove"').'</p>' : '
			<dl class="dd_right dl_2x">
				<dt>Tid</dt>
				<dd><input type="text" name="time" value="'.htmlspecialchars(postval("time", 20)).'" class="styled w40" /> sekunder</dd>
			</dl>
			<p class="c">'.show_sbutton("Gå inn i fensgel").'</p>').'
		</form>
	</div>
</div>';
		}
	}
	
	/**
	 * Regn ut sannsynlighet for å bryte ut
	 */
	protected static function calc_prob($wait, $wanted_level)
	{
		static $max = 500;
		static $min_y = 15;
		static $exp = false;
		if ($exp === false) $exp = log((100-$min_y))/$max;
		
		if ($wait > $max)
		{
			$prob = $min_y;
		}
		else
		{
			$prob = round(exp($exp * ($max - $wait)) + $min_y, 1);
		}
		
		// fiks i forhold til wanted level
		return $prob * (100 - max($wanted_level, 10))/100;
	}
	
	/**
	 * Regn ut hvor mye poeng vi får av å bryte ut
	 * @param $prob
	 */
	protected static function calc_points($prob)
	{
		static $points_max = 8;
		static $points_min = 1;
		static $points_intervals = false;
		static $points_difference = false;
		
		if ($points_intervals === false)
		{
			$points_intervals = $points_max - $points_min + 1;
			$points_difference = 100 / $points_intervals;
		}
		
		$interval = floor($prob / $points_difference);
		return $points_max - $interval + $points_min;
	}
}
