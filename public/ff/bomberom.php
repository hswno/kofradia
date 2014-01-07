<?php

require "../base.php";

new page_bomberom();
class page_bomberom
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Avgift for å kaste ut en spiller av bomberommet
	 */
	const KICK_PLAYER_COST = 50000000;
	
	/**
	 * Avgift for å kaste ut en spiller av bomberommet til familie
	 */
	const KICK_PLAYER_COST_FAMILIE = 2000000;
	
	const KICK_HOUR_START = 21;
	const KICK_HOUR_END = 22;
	
	protected $kick_hour_ok;
	protected $kick_access;
	protected $fam;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		if ($this->ff->type['type'] != "familie" || $this->ff->data['ff_is_crew']) $this->ff->needtype("bomberom");
		$this->ff->needaccess(true);
		$this->fam = $this->ff->type['type'] == "familie";
		
		// konkurrerende broderskap har ikke bomberom
		if ($this->ff->competition)
		{
			ess::$b->page->add_message("Broderskapet er i konkurransemodus og har derfor ikke et aktivt bomberom.", "error");
			$this->ff->redirect();
		}
		
		redirect::store("bomberom?ff_id={$this->ff->id}");
		ess::$b->page->add_title("Bomberommet");
		
		// sjekk om vi kan kaste ut nå
		$this->check_kick_hour();
		$this->kick_access = $this->ff->access($this->ff->type['type'] == "familie" ? 2 : true);
		
		// behandle forespørselen
		$this->page_handle();
		
		// last inn siden
		$this->ff->load_page();
	}
	
	/**
	 * Sjekk om vi kan kaste ut spillere nå
	 */
	protected function check_kick_hour()
	{
		// kan ikke kaste ut på julaften og nyttår
		$d = array("12-24", "12-31");
		if (in_array(ess::$b->date->get()->format("m-d"), $d))
		{
			$this->kick_hour_ok = false;
			return;
		}
		
		// familie kan kaste ut hele tiden
		if ($this->fam)
		{
			$this->kick_hour_ok = true;
			return;
		}
		
		$h = ess::$b->date->get()->format("H");
		$this->kick_hour_ok = $h >= self::KICK_HOUR_START && $h < self::KICK_HOUR_END;
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function page_handle()
	{
		// kaste ut en spiller?
		if (isset($_POST['kick']) && $this->kick_access)
		{
			$this->kick_handle();
		}
		
		// hent liste over spillere som befinner seg i bomberommet
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, up_brom_expire
			FROM users_players
			WHERE up_brom_ff_id = {$this->ff->id} AND up_brom_expire > ".time()." AND up_access_level != 0
			ORDER BY up_brom_expire DESC");
		$players = array();
		while ($row = $result->fetch())
		{
			$players[] = $row;
		}
		
		// hent kapasiteten
		$cap = $this->ff->get_bomberom_capacity();
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Bomberommet<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">'.($this->fam ? '' : '
		<p>Som medlem av firmaet kan du sette deg i bomberommet uavhengig av antall spillere som befinner seg i det. Du har alltid plass.</p>').'
		<p>Kapasitet i bomberommet: <b>'.$cap.'</b> spillere.</p>';
		
		// ingen i bomberommet?
		if (count($players) == 0)
		{
			echo '
		<p>Ingen spillere befinner seg i bomberommet'.($this->fam ? ' til broderskapet' : '').' for øyeblikket.</p>';
		}
		
		else
		{
			// kan vi kaste ut folk?
			$can_kick = $this->kick_access && !login::$user->player->fengsel_check() && !login::$user->player->bomberom_check() && login::$user->player->data['up_b_id'] == $this->ff->data['br_b_id'] && $this->kick_hour_ok;
			
			echo '
		<p>Spillere som befinner seg i bomberommet:</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<table class="table center'.($this->kick_access ? '' : ' tablemb').'">
				<thead>
					<tr>
						<th>Spiller</th>
						<th>Varighet</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			foreach ($players as $row)
			{
				echo '
					<tr'.(!$can_kick ? (++$i % 2 == 0 ? ' class="color"' : '') : ' class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'"').'>
						<td>'.(!$can_kick ? '' : '<input type="radio" name="player" value="'.$row['up_id'].'" />').game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td class="r">'.ess::$b->date->get($row['up_brom_expire'])->format(date::FORMAT_SEC).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>'.(!$this->kick_access ? '' : (!$this->kick_hour_ok ? '
			<p class="c">Det er kun mulig å kaste ut spillere mellom kl. '.self::KICK_HOUR_START.' og kl. '.self::KICK_HOUR_END.'.</p>' : (login::$user->player->fengsel_check() ? '
			<p class="c">Du befinner deg i fengsel og kan ikke kaste ut spillere.</p>' : (login::$user->player->bomberom_check() ? '
			<p class="c">Du befinner deg i bomberom og kan ikke kaste ut spillere.</p>' : (login::$user->player->data['up_b_id'] != $this->ff->data['br_b_id'] ? '
			<p class="c">Du befinner deg i en annen bydel enn bomberommet og kan ikke kaste ut spillere.</p>' : '
			<p class="c">'.show_sbutton("Kast ut spiller", 'name="kick"').'</p>'))))).'
		</form>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Kaste ut en spiller
	 */
	protected function kick_handle()
	{
		// valider sid
		validate_sid();
		
		// kan vi ikke kaste ut noen spillere nå?
		if (login::$user->player->fengsel_check() || login::$user->player->bomberom_check() || login::$user->player->data['up_b_id'] != $this->ff->data['br_b_id'] || !$this->kick_hour_ok)
		{
			redirect::handle();
		}
		
		// mangler spillervalg?
		if (!isset($_POST['player']))
		{
			ess::$b->page->add_message("Du må velge en spiller du vil kaste ut.", "error");
			redirect::handle();
		}
		
		// er ikke spilleren i bomberommet?
		$up_id = (int) $_POST['player'];
		$result = \Kofradia\DB::get()->query("
			SELECT u_email, up_id, up_name, up_access_level, up_brom_expire
			FROM users_players JOIN users ON up_u_id = u_id
			WHERE up_id = $up_id AND up_brom_ff_id = {$this->ff->id} AND up_brom_expire > ".time()." AND up_access_level != 0");
		
		$cost = $this->fam ? self::KICK_PLAYER_COST_FAMILIE : self::KICK_PLAYER_COST;
		$up = $result->fetch();
		if (!$up)
		{
			ess::$b->page->add_message("Fant ikke spilleren.", "error");
			redirect::handle();
		}
		
		// sett opp skjema
		$form = \Kofradia\Form::getByDomain("other", login::$user);
		
		// har vi bekreftet ønsket om å kaste ut en spiller?
		if (isset($_POST['confirm']) && $form->validateHashOrAlert(null, "Kast ut spiller fra bomberom"))
		{	
			\Kofradia\DB::get()->beginTransaction();
			
			// forsøk å trekk fra pengene
			if (!$this->ff->bank(ff::BANK_BETALING, $cost, "Kaste ut spilleren [user id={$up['up_id']}] fra bomberommet"))
			{
				ess::$b->page->add_message("Det er ikke nok penger i ".($this->fam ? "broderskapbanken" : "firmabanken").".", "error");
				\Kofradia\DB::get()->commit();
			}
			
			else
			{
				// finn en tilfeldig bydel å plassere spilleren
				$result = \Kofradia\DB::get()->query("SELECT id, name FROM bydeler WHERE active != 0 ORDER BY RAND() LIMIT 1");
				$b_id = $result->fetchColumn(0);
				
				// forsøk å trekk ut spilleren fra bomberommet
				$a = \Kofradia\DB::get()->exec("
					UPDATE users_players
					SET up_brom_expire = 0, up_b_id = {$b_id}
					WHERE up_id = {$up['up_id']} AND up_brom_ff_id = {$this->ff->id} AND up_brom_expire = {$up['up_brom_expire']} AND up_access_level != 0");
				
				// feilet?
				if ($a == 0)
				{
					// avbryt transaksjon
					\Kofradia\DB::get()->rollback();
					
					ess::$b->page->add_message("Kunne ikke kaste ut spilleren fra bomberommet.", "error");
				}
				
				else
				{
					// legg til hendelse hos spilleren
					player::add_log_static("bomberom_kicked", login::$user->player->id.":".urlencode($this->ff->data['ff_name']).":{$up['up_brom_expire']}", $this->ff->id, $up['up_id']);
					
					// send e-post til spilleren
					$email = new email();
					$email->text = 'Hei,

Din spiller ble kastet ut fra bomberommet av '.($this->fam ? 'broderskapet' : 'firmaet').' som styrer bomberommet.

--
www.kofradia.no';
					$email->send($up['u_email'], "Kastet ut av bomberom");
					
					// firmalogg
					$this->ff->add_log("bomberom_kick", login::$user->player->id.":{$up['up_id']}:{$up['up_brom_expire']}");
					
					// logg
					putlog("DF", "BOMBEROM: {$up['up_name']} ble kastet ut av bomberommet {$this->ff->data['ff_name']} av ".login::$user->player->data['up_name']." ".ess::$s['spath']."/min_side?up_id={$up['up_id']}");
					
					ess::$b->page->add_message('Du kastet ut <user id="'.$up['up_id'].'" /> fra bomberommet. '.($this->fam ? 'Broderskapet' : 'Firmaet').' betalte et gebyr på '.game::format_cash($cost).'.');
					\Kofradia\DB::get()->commit();
					redirect::handle();
				}
			}
		}
		
		ess::$b->page->add_title("Kaste ut spiller");
		
		// vis informasjon
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Kaste ut spiller fra bomberommet<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Du er i ferd med å kaste ut '.game::profile_link($up['up_id'], $up['up_name'], $up['up_access_level']).' fra bomberommet.</p>
		<p>Spilleren skal i utgangspunktet sitte i bomberommet til '.ess::$b->date->get($up['up_brom_expire'])->format(date::FORMAT_SEC).' ('.game::counter($up['up_brom_expire']-time()).' gjenstår).</p>
		<p>For å kaste ut spilleren må det betales en avgift på <b>'.game::format_cash($cost).'</b> som betales fra '.($this->fam ? 'broderskapkontoen' : 'firmakontoen').'.</p>';
		
		// har vi ikke nok penger i firmakontoen?
		if ($this->ff->data['ff_bank'] < $cost)
		{
			echo '
		<p>'.($this->fam ? 'Broderskapet' : 'Firmaet').' har for øyeblikket kun '.game::format_cash($this->ff->data['ff_bank']).' på konto, noe som ikke er nok. '.($this->ff->access(1) ? '<a href="'.ess::$s['relative_path'].'/ff/banken?ff_id='.$this->ff->id.'">Sett inn penger på '.($this->fam ? 'broderskapkontoen' : 'firmakontoen').'</a>' : '<a href="'.ess::$s['relative_path'].'/ff/panel?ff_id='.$this->ff->id.'">Donér til '.($this->fam ? 'broderskapet' : 'firmaet').'</a>').' først for å kunne kaste ut spilleren.</p>
		<p class="c"><a href="bomberom?ff_id='.$this->ff->id.'">Tilbake</a></p>';
		}
		
		else
		{
			echo '
		<p>'.($this->fam ? 'Broderskapet' : 'Firmaet').' har for øyeblikket '.game::format_cash($this->ff->data['ff_bank']).' på konto.</p>
		<form action="" method="post">
			'.$form->getHTMLInput().'
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="player" value="'.$up['up_id'].'" />
			<input type="hidden" name="kick" />
			<p class="c">'.show_sbutton("Bekreft, kast ut spilleren", 'name="confirm"').' <a href="bomberom?ff_id='.$this->ff->id.'">Avbryt</a></p>
		</form>';
		}
		
		echo '
	</div>
</div>';
		
		$this->ff->load_page();
	}
}