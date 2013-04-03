<?php

class page_utpressing extends utpressing
{
	/**
	 * Skjema
	 * @var form
	 */
	protected $form;
	
	/**
	 * Anti-bot
	 * @var antibot
	 */
	protected $antibot;
	
	/**
	 * Construct
	 * @param player $up
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		ess::$b->page->add_title("Utpressing");

		kf_menu::$data['utpressing'] = true;
		
		$this->handle();
		ess::$b->page->load();
	}
	
	/**
	 * Behandle siden
	 */
	protected function handle()
	{
		// skal vi vise siste utpressinger
		if (isset($_GET['log']))
		{
			$this->show_log();
			return;
		}

		// kontroller fengsel, bomberom og energi
		$this->up->fengsel_require_no();
		$this->up->bomberom_require_no();
		$this->up->energy_require(utpressing::ENERGY*1.3); // legg til 30 % for krav
		
		// kontroller anti-bot
		$this->antibot = antibot::get("utpressing", utpressing::ANTIBOT_SPAN);
		$this->antibot->check_required();
		
		// skjema
		$this->form = new form("utpressing");
		
		// sett opp hvilke ranker som kan angripes
		$this->rank_min = max(1, $this->up->rank['number'] - 1);
		$this->rank_max = min($this->rank_min + 3, count(game::$ranks['items']));
		if ($this->rank_max - $this->rank_min < 3) $this->rank_min = max(1, $this->rank_max - 3); // sørg for at man har 4 alternativer uavhengig av rank
		
		// utføre utpressing?
		if (isset($_POST['hash']))
		{
			$this->utpress();
			redirect::handle();
		}
		
		// vis siden
		$this->show();
	}

	/**
	 * Vis oversikt over siste utpressinger
	 */
	protected function show_log()
	{
		ess::$b->page->add_title("Siste utpressinger");
		
		// hent utpressingene man har gjennomført de siste 12 timene
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$expire = time()-43200;
		$result = $pagei->query("
			SELECT ut_affected_up_id, ut_b_id, ut_time
			FROM utpressinger
			WHERE ut_action_up_id = {$this->up->id} AND ut_time >= $expire
			ORDER BY ut_time DESC");

		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Siste utpressinger<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_right"><a href="'.ess::$s['rpath'].'/node/4">Hjelp</a></p>
	<div class="bg1">
		<p class="c"><a href="utpressing">&laquo; Tilbake</a></p>
		<p>Her kan du se utpressingene du har utført de siste 12 timene.</p>';

		if (mysql_num_rows($result) == 0)
		{
			echo '
		<p>Du har ikke utført noen utpressinger de siste 12 timene.</p>';
		}

		else
		{
			echo '
		<table class="table'.($pagei->pages == 1 ? ' tablemb' : '').' center">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Bydel</th>
					<th>Tidspunkt</th>
				</tr>
			</thead>
			<tbody>';

			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				$bydel = "Ukjent bydel";
				if (!empty($row['ut_b_id']) && isset(game::$bydeler[$row['ut_b_id']]))
				{
					$bydel = htmlspecialchars(game::$bydeler[$row['ut_b_id']]['name']);
				}
				
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$row['ut_affected_up_id'].'" /></td>
					<td>'.$bydel.'</td>
					<td>'.ess::$b->date->get($row['ut_time'])->format().'</td>
				</tr>';
			}

			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1) echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
		}

		echo '
	</div>
</div>';
	}

	
	/**
	 * Vis side for utpressing
	 */
	protected function show()
	{
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Utpressing<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_right"><a href="'.ess::$s['rpath'].'/node/4">Hjelp</a></p>
	<div class="bg1">';
		
		// er det noe ventetid?
		if (($wait = $this->calc_wait()) > 0)
		{
			echo '
				<p>Du må vente '.game::counter($wait, true).' før du kan utføre en ny utpressing!</p>';
		}
		
		// vis skjemaet for å utføre en utpressing
		else
		{
			echo '
		<form action="" method="post">
			<p class="c">Velg alternativ:</p>
			<input type="hidden" name="hash" value="'.$this->form->create().'" />
			<table class="table center">
				<tbody>';
			
			$match = ess::session_get("utpressing_opt_key") ?: null;
			$i = 0;
			foreach (self::$tabell as $key => $row)
			{
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="opt" value="'.$key.'"'.($key == $match ? ' checked="checked"' : '').' />'.$row['text'].'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Utfør").'</p>
		</form>';
		}
		
		echo '
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Kontroller inndata og utfør utpressing
	 */
	public function utpress()
	{
		// ventetid?
		if (($wait = $this->calc_wait()) > 0)
		{
			ess::$b->page->add_message("Du må vente ".game::counter($wait, true)." før du kan utføre en ny utpressing.", "error");
			redirect::handle();
		}
		
		// sjekk skjema
		$this->form->validate(postval('hash'), ($this->up->data['up_utpressing_last'] ? "Siste=".game::timespan($this->up->data['up_utpressing_last'], game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD).";" : "First;").($wait ? "%c11Ventetid=".game::timespan($wait, game::TIME_SHORT | game::TIME_NOBOLD)."%c" : "%c9No-wait%c"));
		
		// mangler alternativ?
		if (!isset($_POST['opt']))
		{
			ess::$b->page->add_message("Du må velge et alternativ.", "error");
			redirect::handle();
		}
		
		// kontroller alternativ
		$opt_key = (int) $_POST['opt'];
		if (!isset(self::$tabell[$opt_key]))
		{
			redirect::handle();
		}
		
		// lagre valg for neste gang
		ess::session_put("utpressing_opt_key", $opt_key);
		
		// forsøk utpressing
		$result = parent::utpress($opt_key);
		$post = $result['wanted'] > 0 ? ' Wanted nivået økte med '.game::format_number($result['wanted']/10, 1).' %.' : '';
		
		if ($result['success'] === true)
		{
			$post = ' Du mottok '.game::format_num($result['points']).' poeng.' . $post;
			$extra = '';
			
			// kom fra spillet?
			if (!isset($result['player']))
			{
				ess::$b->page->add_message("Du fant ".game::format_cash($result['cash'])." liggende på gata.".$post);
			}
			
			else
			{
				// døde spilleren?
				if (isset($result['attack']) && $result['attack']['drept'])
				{
					$extra .= ' Spilleren hadde så lite helse at spilleren døde av utpressingen din.';
					
					// list opp vitner
					if (count($result['attack']['vitner']) == 0)
					{
						$extra .= ' Ingen spillere vitnet drapet.';
					}
					
					else
					{
						// sett opp liste over navngitte spillere som oppdaget det
						$list = array();
						$count_other = 0;
						foreach ($result['attack']['vitner'] as $vitne)
						{
							if ($vitne['visible']) $list[] = $vitne['up']->profile_link();
							else $count_other++;
						}
						if ($count_other > 0) $list[] = fwords("%d ukjent spiller", "%d ukjente spillere", $count_other);
						
						$extra .= sentences_list($list).' vitnet drapet.';
					}
				}
				
				$text = $result['player_from_bank']
					? ". Spilleren hadde ingen kontanter på seg, men du tok bankkortet til spilleren og fikk ut ".game::format_cash($result['cash'])." fra kontoen"
					: " og presset spilleren for ".game::format_cash($result['cash']);
				ess::$b->page->add_message("Du fant ".$result['player']->profile_link()."$text.".$extra.$post);
			}
		}
		
		else
		{
			// har vi en spiller?
			if (isset($result['player']))
			{
				// penger i banken?
				$bank = $result['player']->data['up_bank'] > 10000;
				$text = $bank ? 'verken kontanter eller bankkort på seg' : 'ingen kontanter på seg. Du fikk tak i bankkortet til spilleren med det var ingen penger å hente der';
				
				ess::$b->page->add_message("Du fant ".$result['player']->profile_link().", men spilleren hadde $text.$post");
			}
			
			else
			{
				ess::$b->page->add_message("Du mislykket utpressingsforsøket.$post");
			}
		}
		
		// oppdater anti-bot
		$this->antibot->increase_counter();
	}
}
