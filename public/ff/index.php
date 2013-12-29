<?php

require "../base.php";

class page_ff extends pages_player
{
	/**
	 * Construct
	 */
	public function __construct(player $up = null)
	{
		parent::__construct($up);
		
		// vis en konkurranse
		if (isset($_GET['fff_id']))
		{
			$this->fff_show();
		}
		
		// opprette en familie
		if (isset($_GET['create']) && $this->up)
		{
			$this->ff_create();
		}
		
		// vise en ff
		if (isset($_GET['ff_id']))
		{
			new page_ff_id($up);
		}
		
		redirect::handle("/bydeler", redirect::ROOT);
	}
	
	/**
	 * Vis en konkurranse
	 */
	protected function fff_show()
	{
		// hent info
		$fff_id = (int) $_GET['fff_id'];
		$limit_time = access::has("mod") ? '' : " AND fff_time_start <= ".time()." AND fff_active = 1";
		$result = \Kofradia\DB::get()->query("
			SELECT fff_id, fff_time_start, fff_time_expire, fff_required_points, fff_active
			FROM ff_free
			WHERE fff_id = $fff_id$limit_time");
		
		// fant ikke?
		if ($result->rowCount() == 0)
		{
			ess::$b->page->add_message("Fant ikke konkurransen.");
			redirect::handle();
		}
		$faf = $result->fetch();
		
		ess::$b->page->add_title("Viser konkurranse om å danne broderskap");
		
		// hent familier som deltar eller har deltatt i denne konkurransen
		$result = \Kofradia\DB::get()->query("
			SELECT ff_id, ff_name, ff_inactive, ff_inactive_time, ff_date_reg, COUNT(ffm_up_id) as ffm_count
			FROM ff
				LEFT JOIN ff_members ON ffm_ff_id = ff_id AND ffm_status = 1
			WHERE ff_fff_id = $fff_id
			GROUP BY ff_id");
		$ff = array();
		while ($row = $result->fetch())
		{
			$ff[$row['ff_id']] = $row;
		}
		
		$create_link = $this->up
			? ($this->up->rank['number'] < ff::$types[1]['priority_rank'][1]
				? ' - Du har ikke høy nok rank til å opprette broderskap'
				: ' - Du har høy nok rank - <a href="./?create">Opprett broderskap &raquo;</a>') : '';
		
		// vis informasjon
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Konkurranse om å danne broderskap<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="./">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p>Du viser en konkurranse om å danne et broderskap.</p>'.($faf['fff_time_start'] > time() && $faf['fff_active'] == 1 ? '
		<p>Denne konkurransen er ikke åpen enda. Starter '.ess::$b->date->get($faf['fff_time_start'])->format(date::FORMAT_SEC).' og varer til '.ess::$b->date->get($faf['fff_time_expire'])->format(date::FORMAT_SEC).'.</p>' : ($faf['fff_time_expire'] < time() && $faf['fff_active'] != 1 ? '
		<p>Denne konkurransen ble avsluttet '.ess::$b->date->get($faf['fff_time_expire'])->format(date::FORMAT_SEC).' (startet '.ess::$b->date->get($faf['fff_time_start'])->format(date::FORMAT_SEC).').</p>' : ($faf['fff_active'] != 1 ? '
		<p>Konkurransen er ikke aktivert.</p>' : '
		<p>Konkurransen startet '.ess::$b->date->get($faf['fff_time_start'])->format(date::FORMAT_SEC).' og blir avsluttet '.ess::$b->date->get($faf['fff_time_expire'])->format().' ('.game::counter(max(0, $faf['fff_time_expire']-time())).').</p>'.(count($ff) < ff::MAX_FFF_FF_COUNT ? '
		<p>Denne konkurransen er ikke fylt opp'.$create_link.'</p>' : ''))));
		
		if (count($ff) == 0)
		{
			echo '
		<p>Ingen broderskap deltar i denne konkurransen.</p>';
		}
		
		else
		{
			echo '
		<p>Broderskap i konkurransen:</p>
		<table class="table center tablemb">
			<thead>
				<tr>
					<th>Broderskap</th>
					<th>Opprettet</th>
					<th>Status</th>
					<th>Medlemmer</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		foreach ($ff as $row)
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td>'.($row['ff_inactive'] == 0 || access::has("mod") ? '<a href="./?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a>' : htmlspecialchars($row['ff_name'])).'</td>
					<td class="r">'.ess::$b->date->get($row['ff_date_reg'])->format().'</td>
					<td>'.($row['ff_inactive'] == 0 ? 'OK' : 'Lagt ned '.ess::$b->date->get($row['ff_inactive_time'])->format()).'</td>
					<td class="r">'.$row['ffm_count'].'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>';
		}
		
		echo '
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Opprette en familie
	 */
	protected function ff_create()
	{
		// har ikke høy nok rank?
		if ($this->up->rank['number'] < ff::$types[1]['priority_rank'][1])
		{
			ess::$b->page->add_message("Du må ha oppnådd ranken <b>".game::$ranks['items_number'][ff::$types[1]['priority_rank'][1]]['name']."</b> for å kunne danne et broderskap.", "error");
			redirect::handle();
		}
		
		// har for lav helse?
		if ($this->up->get_health_percent() < player::FF_HEALTH_LOW*100)
		{
			ess::$b->page->add_message("Du har for lite helse til å kunne danne et broderskap. Du må ha minimium ".(player::FF_HEALTH_LOW*100)." % helse.", "error");
			redirect::handle();
		}
		
		// se om brukeren er invitert eller medlem av en annen familie
		$result = \Kofradia\DB::get()->query("
			SELECT COUNT(ff_id)
			FROM ff_members JOIN ff ON ffm_ff_id = ff_id
			WHERE ffm_up_id = ".$this->up->id." AND ff_inactive = 0 AND ff_type = 1 AND ff_is_crew = 0 AND (ffm_status = 0 OR ffm_status = 1)");
		if ($result->fetchColumn(0) >= ff::MAX_FAMILIES && !access::has("mod"))
		{
			ess::$b->page->add_message("Du er allerede invitert eller medlem av for mange broderskap.");
			redirect::handle();
		}
		
		// se om brukeren tidligere har opprettet en familie i noen av konkurransene
		$time = time();
		$result = \Kofradia\DB::get()->query("
			SELECT ff_date_reg, ff_inactive_time
			FROM ff_free
				JOIN ff ON ff_fff_id = fff_id AND ff_inactive != 0
				JOIN ff_members ON ffm_ff_id = ff_id AND ffm_up_id = ".$this->up->id." AND ffm_priority = 1
			WHERE $time >= fff_time_start AND fff_active = 1 AND fff_ff_count < ".ff::MAX_FFF_FF_COUNT."
			LIMIT 1");
		if ($result->rowCount() > 0)
		{
			$row = $result->fetch();
			ess::$b->page->add_message("Du opprettet et broderskap i denne konkurransen ".ess::$b->date->get($row['ff_date_reg'])->format()." som ble lagt ned ".ess::$b->date->get($row['ff_inactive_time'])->format().". Du må vente på en ny konkurranse for å opprette nytt broderskap.", "error");
			redirect::handle();
		}
		
		// se om det er ledig plass for en familie
		$result = \Kofradia\DB::get()->query("
			SELECT fff_id, fff_time_start, fff_time_expire, fff_ff_count
			FROM ff_free
			WHERE $time >= fff_time_start AND fff_active = 1 AND fff_ff_count < ".ff::MAX_FFF_FF_COUNT."
			ORDER BY fff_time_start");
		$total_free = 0;
		$fafs = array();
		while ($row = $result->fetch())
		{
			$fafs[$row['fff_id']] = $row;
			$total_free += ff::MAX_FFF_FF_COUNT - $row['fff_ff_count'];
		}
		
		// ingen ledige plasser?
		if ($total_free == 0)
		{
			ess::$b->page->add_message("Det er ikke lenger noen ledige plasser for broderskap.", "error");
			redirect::handle();
		}
		
		// danne familien
		if (isset($_POST['confirm']) && validate_sid())
		{
			// trekk fra pengene fra brukeren
			$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - ".ff::CREATE_COST." WHERE up_id = ".$this->up->id." AND up_cash >= ".ff::CREATE_COST);
			
			// ble ikke brukeren oppdatert?
			if ($a == 0)
			{
				ess::$b->page->add_message("Du har ikke nok penger på hånda.", "error");
			}
			
			else
			{
				// forsøk å danne familie
				$fff_id = NULL;
				foreach ($fafs as $faf)
				{
					$a = \Kofradia\DB::get()->exec("UPDATE ff_free SET fff_ff_count = fff_ff_count + 1 WHERE fff_id = {$faf['fff_id']} AND fff_ff_count < ".ff::MAX_FFF_FF_COUNT." AND fff_active = 1");
					if ($a > 0)
					{
						$fff_id = $faf['fff_id'];
						break;
					}
				}
				
				// fant ingen ledig konkurranse?
				if (!$fff_id)
				{
					// gi tilbake pengene
					\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + ".ff::CREATE_COST." WHERE up_id = ".$this->up->id);
					
					ess::$b->page->add_message("Det er ikke lenger noen ledige plasser for broderskap.", "error");
					redirect::handle();
				}
				
				else
				{
					// opprett familien
					\Kofradia\DB::get()->exec("INSERT INTO ff SET ff_date_reg = ".time().", ff_type = 1, ff_name = ".\Kofradia\DB::quote($this->up->data['up_name']."'s broderskap").", ff_up_limit = ".\Kofradia\DB::quote(ff::MEMBERS_LIMIT_DEFAULT).", ff_fff_id = $fff_id");
					$ff_id = \Kofradia\DB::get()->lastInsertId();
					
					// legg til brukeren som boss
					\Kofradia\DB::get()->exec("INSERT INTO ff_members SET ffm_up_id = ".$this->up->id.", ffm_ff_id = $ff_id, ffm_date_created = ".time().", ffm_date_join = ".time().", ffm_priority = 1, ffm_status = 1, ffm_pay_points = ".$this->up->data['up_points']);
					
					// logg
					putlog("INFO", "Broderskap: %u".$this->up->data['up_name']."%u opprettet et broderskap og er nå del av en konkurranse ".ess::$s['path']."/ff/?ff_id=$ff_id");
					
					// fiks forum link for boss
					$ff = ff::get_ff($ff_id, ff::LOAD_SCRIPT);
					if ($ff)
					{
						$ff->members['members'][$this->up->id]->forum_link(true);
					}
					
					// live-feed
					livefeed::add_row(ucfirst($ff->type['refobj']).' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$ff->id.'">'.htmlspecialchars($ff->data['ff_name']).'</a> ble opprettet av <user id="'.$this->up->id.'" />.');
					
					ess::$b->page->add_message("Broderskapet ble opprettet.");
					redirect::handle("?ff_id=$ff_id");
				}
			}
		}
		
		ess::$b->page->add_title("Danne nytt broderskap");
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Danne nytt broderskap<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="./">&laquo; Tilbake</a></p>
	<div class="bg1">
		<boxes />
		<p>For øyeblikket er det '.$total_free.' '.fword("ledig broderskapplass", "ledige broderskapplasser", $total_free).'.</p>
		<dl class="dd_right">
			<dt>Broderskapnavn</dt>
			<dd>'.$this->up->data['up_name'].'\'s broderskap</dd>
			<dt>Kostnad å opprette</dt>
			<dd>'.game::format_cash(ff::CREATE_COST).'</dd>
		</dl>
		<p>Du vil kunne sende søknad om å endre navnet på broderskapet etter den er opprettet.</p>
		<p>Dette vil danne et konkurrende broderskap. I løpet av en gitt periode vil broderskapet få i oppdrag om å ranke mer enn to andre broderskap. Broderskapet som oppnår mest samlet rank får beholde broderskapet, mens de to andre broderskapene dør ut.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p class="c">
				'.show_sbutton("Opprett konkurrende broderskap", 'name="confirm"').'
				<a href="./">Tilbake</a>
			</p>
		</form>
	</div>
</div>';
		
		ess::$b->page->load();
	}
}

/**
 * FF
 */
class page_ff_id extends pages_player
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Hvor lang tid man har på å utføre anti-bot etter man har kjøpt kuler
	 */
	const BULLET_FREEZE_WAIT = 15;
	
	/**
	 * Skjema for kjøp av kuler
	 * @var form
	 */
	protected $bullets_form;
	
	/**
	 * Anti-bot for kulekjøp
	 * @var antibot
	 */
	protected $bullets_antibot;
	
	/**
	 * Construct
	 */
	public function __construct(player $up = null)
	{
		parent::__construct($up);
		
		// generere kart?
		if (isset($_GET['draw']))
		{
			$this->map_draw();
		}
		
		// hent inn ff
		$this->ff = ff::get_ff();
		
		redirect::store("?ff_id={$this->ff->id}");
		
		// godta/avslå invitasjon
		if ($this->up && (isset($_POST['invite_apply']) || isset($_POST['invite_decline'])))
		{
			$this->invite_handle();
		}
		
		// vise statistikk for FF?
		if (isset($_GET['stats']) && ($this->ff->type['type'] == "familie" || $this->ff->uinfo))
		{
			$this->stats();
		}
		
		// vis informasjon om ff
		$this->info();
		
		$this->ff->load_page();
	}
	
	/**
	 * Generer kart
	 */
	protected function map_draw()
	{
		// hent inn ff
		$this->ff = ff::get_ff(null, ff::LOAD_SILENT);
		if (!$this->ff)
		{
			page_not_found();
		}
		
		// har ingen bydel?
		if (!$this->ff->data['br_id'])
		{
			die("Har ingen bydel.");
		}
		
		$map = new bydeler_map();
		$map->mini_map($this->ff->data['br_id']);
		$map->push();
		
		die;
	}
	
	/**
	 * Godta/avslå invitasjon
	 */
	protected function invite_handle()
	{
		// godta/avslå invitasjon?
		if (!isset($this->ff->members['invited'][$this->up->id]))
		{
			ess::$b->page->add_message("Du er ikke invitert til {$this->ff->type['refobj']}.", "error");
			$this->ff->redirect();
		}
		
		$stilling = $this->ff->members['invited'][$this->up->id]->get_priority_name();
		
		// godta?
		if (isset($_POST['invite_apply']))
		{
			// har vi ikke nok helse?
			if ($this->up->get_health_percent() < player::FF_HEALTH_LOW*100)
			{
				ess::$b->page->add_message("Du har ikke nok helse til å bli med i ".$this->ff->type['refobj']." nå. Du må ha minimum ".(player::FF_HEALTH_LOW*100)." % helse.", "error");
			}
			
			else
			{
				$this->ff->members['invited'][$this->up->id]->invite_accept();
				ess::$b->page->add_message("Du er nå <b>$stilling</b> for <b>{$this->ff->data['ff_name']}</b>!");
			}
		}
		
		// avslå
		else
		{
			$this->ff->members['invited'][$this->up->id]->invite_decline();
			ess::$b->page->add_message("Du avslo invitasjonen for å bli <b>$stilling</b> for <b>{$this->ff->data['ff_name']}</b>!");
		}
		
		$this->ff->redirect();
	}
	
	/**
	 * Vis statistikk for en FF
	 */
	protected function stats()
	{
		ess::$b->page->add_title("Statistikk");
		
		// antall dager vi skal vise
		$days = 30;
		
		$fields = array("ffsd_money_in", "ffsd_money_out", "ffsd_attack_failed_num", "ffsd_attack_damaged_num", "ffsd_attack_killed_num", "ffsd_attack_bleed_num", "ffsd_attacked_failed_num", "ffsd_attacked_damaged_num", "ffsd_attacked_killed_num", "ffsd_attacked_bleed_num");
		
		// sett opp liste for data
		$stats = array();
		$date = ess::$b->date->get();
		$date->setTime(0, 0, 0);
		$date->modify("-".($days - 1)." days");
		for ($i = 0; $i < $days; $i++)
		{
			if ($i > 0) $date->modify("+1 day");
			foreach ($fields as $field)
			{
				$stats[$field][$date->format("Y-m-d")] = 0;
			}
		}
		
		// hent inn statistikk over dagene
		if ($this->ff->data['ff_time_reset'] && !$this->ff->mod && $this->ff->data['ff_time_reset'])
		{
			// hvis statistikken ble nullstilt må vi hoppe 1 dag frem fra tidspunktet det ble nullstillt for å ikke ta med noe statistikk fra tidligere periode
			$d = ess::$b->date->get($this->ff->data['ff_time_reset']);
			$d->modify("+1 day");
			if ($d->format("U") > $date->format("U")) $date = $d; // sørg for at tidspunktet er i perioden
		}
		$first = $date->format("Y-m-d");
		$result = \Kofradia\DB::get()->query("
			SELECT ffsd_date, ffsd_money_in, ffsd_money_out, ffsd_attack_failed_num, ffsd_attack_damaged_num, ffsd_attack_killed_num, ffsd_attack_bleed_num, ffsd_attacked_failed_num, ffsd_attacked_damaged_num, ffsd_attacked_killed_num, ffsd_attacked_bleed_num
			FROM ff_stats_daily
			WHERE ffsd_ff_id = {$this->ff->id} AND ffsd_date >= $first");
		while ($row = $result->fetch())
		{
			if (!isset($stats['ffsd_money_in'][$row['ffsd_date']])) continue;
			
			foreach ($fields as $field)
			{
				$stats[$field][$row['ffsd_date']] = (float) $row[$field];
			}
		}
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Statistikk for '.$this->ff->type['refobj'].'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Her er en oversikt over litt daglig statistikk for '.$this->ff->type['refobj'].'. Statistikken gjelder 30 dager tilbake i tid, inkludert i dag.</p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>'.($this->ff->type['type'] == "familie" || $this->ff->mod ? '
		<p>Angrep utført av '.$this->ff->type['refobj'].':</p>'.$this->stats_attack($stats).'
		<p>Angrep mot '.$this->ff->type['refobj'].':</p>'.$this->stats_attacked($stats) : '').($this->ff->uinfo ? '
		<p>Pengeflyt: <span class="dark">(Ikke medberegnet innskudd og uttak fra banken til '.$this->ff->type['refobj'].'.)</span></p>'.$this->stats_pengeflyt($stats) : '').'
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Sett opp stats over angrep FF har utført
	 */
	protected function stats_attack(&$stats)
	{
		// sett opp diagram for pengeflyt
		$elm_id = $this->stats_ofc_build("attack", array(
				array("Mislykkede angrep", "#x_label#<br>#val# mislykkede", array_values($stats['ffsd_attack_failed_num']), OFC_Colours::$colours[0]),
				array("Spiller skadet", "#x_label#<br>#val# skadet", array_values($stats['ffsd_attack_damaged_num']), OFC_Colours::$colours[1]),
				array("Spiller drept", "#x_label#<br>#val# drept", array_values($stats['ffsd_attack_killed_num']), OFC_Colours::$colours[2]),
				array("Spiller blødd ihjel", "#x_label#<br>#val# blødd ihjel", array_values($stats['ffsd_attack_bleed_num']), OFC_Colours::$colours[3]),
			),
			array_keys($stats['ffsd_money_in']));
		
		return '
		<div style="margin: 10px 0"><div id="'.$elm_id.'"></div></div>';
	}
	
	/**
	 * Sett opp stats over angrep som har blitt utført mot FF
	 */
	protected function stats_attacked(&$stats)
	{
		// sett opp diagram for pengeflyt
		$elm_id = $this->stats_ofc_build("attacked", array(
				array("Mislykkede angrep", "#x_label#<br>#val# mislykkede", array_values($stats['ffsd_attacked_failed_num']), OFC_Colours::$colours[0]),
				array("Spiller skadet", "#x_label#<br>#val# skadet", array_values($stats['ffsd_attacked_damaged_num']), OFC_Colours::$colours[1]),
				array("Spiller drept", "#x_label#<br>#val# drept", array_values($stats['ffsd_attacked_killed_num']), OFC_Colours::$colours[2]),
				array("Spiller blødd ihjel", "#x_label#<br>#val# blødd ihjel", array_values($stats['ffsd_attacked_bleed_num']), OFC_Colours::$colours[3]),
			),
			array_keys($stats['ffsd_money_in']));
		
		return '
		<div style="margin: 10px 0"><div id="'.$elm_id.'"></div></div>';
	}
	
	/**
	 * Sett opp stats for pengeflyt
	 */
	protected function stats_pengeflyt(&$stats)
	{
		// sett opp diagram for pengeflyt
		$elm_id = $this->stats_ofc_build("pengeflyt", array(
				array("Penger inn", "#x_label#<br>#val# kr inn", array_values($stats['ffsd_money_in']), OFC_Colours::$colours[0]),
				array("Penger ut", "#x_label#<br>#val# kr ut", array_values($stats['ffsd_money_out']), OFC_Colours::$colours[1])
			),
			array_keys($stats['ffsd_money_in']), 300);
		
		return '
		<div style="margin: 10px 0"><div id="'.$elm_id.'"></div></div>';
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
		
		$ofc->axis_x()->label()->steps(2)->rotate(330)->labels($labels);
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
	 * Vis informasjon om FF
	 */
	protected function info()
	{
		// ulike FF
		switch ($this->ff->type['type'])
		{
			// avisfirma
			case "avis":
				$this->type_avis();
			break;
			
			// bankfirma
			case "bank":
				$this->type_bank();
			break;
			
			// våpen/beskyttelse
			case "vapbes":
				$this->type_vapbes();
			break;
			
			// bomberom
			case "bomberom":
			case "familie":
				if ($this->ff->type['type'] != "familie" || !$this->ff->data['ff_is_crew']) $this->type_bomberom();
			break;
			
			// sykehus
			case "sykehus":
				new page_ff_sykehus($this->up, $this->ff);
			break;
			
			// utleiefirma for garasje
			case "garasjeutleie":
				$this->type_garasjeutleie();
			break;
		}
		
		$type_data = @ob_get_contents();
		@ob_clean();
		
		// vis evt. konkurransedetaljer
		$this->competition_details();
		
		// vis evt. salgsdetaljer
		$this->sell_details();
		
		// vis evt. invitasjon
		$this->invite_details();
		
		// vis beskrivelse
		if (($data = $this->ff->format_description()) != "")
		{
			echo '
		<div class="p familie_beskrivelse">
			'.$data.'
		</div>';
		}
		
		if ($this->ff->type['type'] == "familie")
		{
			// hent statistikk over angrep
			$first = ess::$b->date->get();
			$first->modify("-7 days");
			$result = \Kofradia\DB::get()->query("
				SELECT
					SUM(ffsd_attack_failed_num) afailed, SUM(ffsd_attack_damaged_num) adamaged, SUM(ffsd_attack_killed_num) akilled, SUM(ffsd_attack_bleed_num) ableed,
					SUM(ffsd_attacked_failed_num) dfailed, SUM(ffsd_attacked_damaged_num) ddamaged, SUM(ffsd_attacked_killed_num) dkilled, SUM(ffsd_attacked_bleed_num) dbleed
				FROM ff_stats_daily
				WHERE ffsd_ff_id = {$this->ff->id} AND ffsd_date >= ".\Kofradia\DB::quote($first->format("Y-m-d")));
			$stats = $result->fetch();
			foreach ($stats as &$num) $num = (int) $num;
			
			// statistikk over angrep
			$attacks = $stats['afailed'] + $stats['adamaged'] + $stats['akilled'];
			if ($attacks > 0)
			{
				echo '
		<p>De siste 7 dagene har medlemmene av '.$this->ff->type['refobj'].' utført <b>'.$attacks.'</b> angrep, hvorav '.fwords("<b>%d</b> spiller", "<b>%d</b> spillere", $stats['akilled']+$stats['ableed']).' har blitt drept og '.fwords("<b>%d</b> spiller", "<b>%d</b> spillere", $stats['adamaged']-$stats['ableed']).' har blitt skadet.</p>';
			}
			
			// antall ganger angrepet
			$attacked = $stats['dfailed'] + $stats['ddamaged'] + $stats['dkilled'];
			if ($attacked + $stats['dbleed'] > 0)
			{
				echo '
		<p>De siste 7 dagene har medlemmene av '.$this->ff->type['refobj'].' blitt angrepet <b>'.$attacked.'</b> '.fword("gang", "ganger", $attacked).', hvorav '.fwords("<b>%d</b> medlem", "<b>%d</b> medlemmer", $stats['dkilled']).' har blitt drept, '.fwords("<b>%d</b> medlem", "<b>%d</b> medlemmer", $stats['dbleed']).' har dødd av skader og medlemmene har ellers blitt skadet '.fwords("<b>%d</b> gang", "<b>%d</b> ganger", max(0, $stats['ddamaged']-$stats['dbleed'])).'.</p>';
			}
			
			echo '
		<p><a href="./?ff_id='.$this->ff->id.'&amp;stats">Vis statistikk over angrep &raquo;</a></p>';
		}
		
		echo $type_data;
		
		// vis oversikt over medlemmer
		$this->members_details();
		
		// last inn siden
		$this->info_load_page();
	}
	
	/**
	 * Last inn info-siden
	 */
	protected function info_load_page()
	{
		$data = ob_get_contents();
		ob_clean();
		
		// familie?
		if ($this->ff->type['type'] == "familie")
		{
			ess::$b->page->add_css('
.familie_wrap {
	margin: 15px auto 0;
	max-width: 70%;
	overflow: hidden;
	padding-right: 140px;
}
.familie_left {
	float: left;
	/*min-width: 460px;
	max-width: 500px;*/
	/*margin-right: 140px;*/
	width: 100%;
}
.familie_right {
	width: 120px;
	text-align: center;
	float: right;
	margin-right: -140px;
	/*margin: 20px 0 0 10px;*/
}
.familie_bilde img {
	/*clear: right;
	float: right;*/
	background-color: #0B0B0B;
	padding: 5px;
}
.familie_logo {
	position: relative;
	/*right: -5px;*/
	margin-top: 10px;
}');
			
			echo '
<div class="familie_wrap">
<div class="bg1_c familie_left">
	<h1 class="bg1">Broderskap: '.htmlspecialchars($this->ff->data['ff_name']).(!$this->ff->active ? ' (deaktivert)' : '').'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1 familie">
		<boxes />'.$data.'
	</div>
</div>
<p class="familie_right familie_bilde">'.($this->ff->data['br_b_id'] ? '
	<a href="'.ess::$s['relative_path'].'/bydeler"><img src="./?ff_id='.$this->ff->id.'&amp;draw" alt="Område" title="Tilholdssted: '.htmlspecialchars(game::$bydeler[$this->ff->data['br_b_id']]['name']).'" /></a><br />' : '').'
	<img src="'.htmlspecialchars($this->ff->get_logo_path()).'" alt="Logo" class="familie_logo" />
</p>
</div>';
			
			$this->ff->load_page(false);
		}
		
		// firma
		else
		{
			echo $data;
			
			$this->ff->load_page();
		}
	}
	
	/**
	 * Konkurransedetaljer
	 */
	protected function competition_details()
	{
		// vis informasjon hvis konkurranse?
		if ($this->ff->uinfo && $this->ff->competition && $this->ff->data['fff_time_expire'])
		{
			echo '
		<div class="section">
			<h2>Konkurransemodus</h2>
			<p class="h_right">Kun synlig for broderskapmedlemmer</p>
			<p>Broderskapet er i konkurransemodus. <a href="./?fff_id='.$this->ff->data['fff_id'].'">Vis konkurransedetaljer</a></p>';
			
			// vis tidsgraf
			$time_status = (time()-$this->ff->data['fff_time_start']) / ($this->ff->data['fff_time_expire']-$this->ff->data['fff_time_start']);
			$time_status = min(100, max(0, $time_status*100));
			
			echo '
			<div class="progressbar" style="margin: 1em 0">
				<div class="progress" style="width: '.round($time_status).'%">
					<p>Tidsstatus: '.game::format_number($time_status, 1).' %</p>
				</div>
			</div>';
			
			// vis rankstatus
			$rank_points = $this->ff->competition_rank_points();
			$rank_status = min(100, $rank_points / $this->ff->data['fff_required_points'] * 100);
			$rank_status_text = $rank_points / $this->ff->data['fff_required_points'] * 100;
			echo '
			<div class="progressbar" style="margin: 1em 0">
				<div class="progress" style="width: '.round($rank_status).'%">
					<p>Rankstatus minstekrav: '.game::format_number($rank_status_text, 2).' %</p>
				</div>
			</div>';
			
			if (isset($_GET['fff_compare']))
			{
				// har vi informasjon
				$info = $this->ff->params->get("competition_info");
				if ($info) $info = unserialize($info);
				
				echo '
		<h3>Sammenlikning med de andre broderskapene</h3>';
				
				// skjema for å kjøpe ny info
				if (isset($_GET['buy']) && $this->ff->access(1))
				{
					// har det ikke gått lang nok tid siden sist?
					if ($info && $info['time'] > time()-3600*6)
					{
						ess::$b->page->add_message("Ny informasjon kan ikke kjøpes før det har gått 6 timer siden forrige kjøp.", "error");
						redirect::handle("?ff_id={$this->ff->id}&fff_compare");
					}
					
					// utføre kjøpet?
					if (isset($_POST['confirm']) && validate_sid(false))
					{
						$result = $this->ff->buy_competition_info();
						if (is_array($result))
						{
							ess::$b->page->add_message("Informasjon om de andre broderskapene ble kjøpt.");
							redirect::handle("?ff_id={$this->ff->id}&fff_compare");
						}
						
						switch ($result)
						{
							case "wait": ess::$b->page->add_message("Du må vente minimum 6 timer mellom hvert kjøp.", "error"); break;
							case "none": ess::$b->page->add_message("Det er ingen andre broderskap i konkurransen.", "error"); break;
							case "familie_cash": ess::$b->page->add_message("Det er ikke nok penger i broderskapbanken.", "error"); break;
							default: ess::$b->page->add_message("Ukjent feil.", "error");
						}
					}
					
					echo '
			<p><a href="./?ff_id='.$this->ff->id.'&amp;buy">&laquo; Tilbake</a></p>
			<form action="" method="post">
				<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
				<p>Informasjonen som blir kjøpt gjelder <u>kun</u> for det øyeblikket kjøpet blir gjennomført, og blir ikke oppdatert før ny informasjon blir kjøpt.</p>
				<dl class="dd_right">
					<dt>Penger i broderskapbanken</dt>
					<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
					<dt>Kostnad for informasjon</dt>
					<dd>'.game::format_cash(ff::COMPETITION_INFO_COST).'</dd>
				</dl>
				<p>Beløpet blir trukket fra broderskapbanken.</p>
				<p class="c">'.show_sbutton("Kjøp informasjon", 'name="confirm"').' <a href="./?ff_id='.$this->ff->id.'&amp;buy">Tilbake</a></p>
			</form>';
				}
				
				// har ikke info
				elseif (!$info && (!access::has("mod") || !isset($_GET['override'])))
				{
					echo '
			<p><a href="./?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
			<p>For å kunne se informasjonen om de andre broderskapene, må det betales et beløp på '.game::format_cash(ff::COMPETITION_INFO_COST).'. Da får man vite hvor mye de andre broderskapene har ranket i forhold til ditt broderskap.</p>
			<p>Denne informasjonen gjelder kun det tidspunktet informasjonen ble kjøpt. Etter det har gått 6 timer kan ny informasjon kjøpes.</p>
			<p>Kun Capofamiglia har mulighet til å kjøpe denne informasjonen. Alle medlemmene i broderskapet kan se oversikten når den er kjøpt.</p>';
					
					if ($this->ff->access(1))
					{
						echo '
			<p><a href="./?ff_id='.$this->ff->id.'&amp;fff_compare&amp;buy">Kjøp informasjon &raquo;</a></p>';
					}
					
					if (access::has("mod"))
					{
						echo '
			<p><a href="./?ff_id='.$this->ff->id.'&amp;fff_compare&amp;override">Vis nåværende informasjon som moderator &raquo;</a></p>';
					}
				}
				
				else
				{
					// generere informasjon?
					if ((!$info || isset($_GET['override'])) && access::has("mod"))
					{
						ess::$b->page->add_message('Du viser informasjon som moderator. <a href="./?ff_id='.$this->ff->id.'&amp;fff_compare">Tilbake</a>');
						$info = $this->ff->get_competition_info();
					}
					
					// tidsgraf for det øyeblikket informasjonen ble kjøpt
					$time_status = ($info['time']-$this->ff->data['fff_time_start']) / ($this->ff->data['fff_time_expire']-$this->ff->data['fff_time_start']);
					$time_status = min(100, max(0, $time_status*100));
					
					// vis informasjon
					echo '
			<p><a href="./?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>
			<p>Informasjon om de andre broderskapene ble kjøpt '.ess::$b->date->get($info['time'])->format().'. Det er '.game::timespan($info['time'], game::TIME_ABS | game::TIME_PAST | game::TIME_FULL).'.</p>
			<div class="progressbar" style="margin: 1em 0">
				<div class="progress" style="width: '.round($time_status).'%">
					<p>Tidsstatus for informasjon: '.game::format_number($time_status, 1).' %</p>
				</div>
			</div>
			<p>Broderskap i konkurransen:</p>';
					
					// hent navn for familiene og sett opp data
					$ff_ids = array();
					$stats = array();
					$max = $this->ff->data['fff_required_points'];
					foreach ($info['stats'] as $row)
					{
						$ff_ids[] = $row['ff_id'];
						$stats[$row['ff_id']] = array($row['ff_id'], $row['ff_name'], $row['total_points'], false);
						$max = max($max, abs($row['total_points']));
					}
					if (count($ff_ids) > 0)
					{
						$result = \Kofradia\DB::get()->query("SELECT ff_id, ff_name, ff_inactive FROM ff WHERE ff_id IN (".implode(",", $ff_ids).")");
						while ($row = $result->fetch())
						{
							$stats[$row['ff_id']][1] = $row['ff_name'];
							$stats[$row['ff_id']][3] = $row['ff_inactive'] == 0;
						}
					}
					
					ess::$b->page->add_css('
.familie_panel_pay { margin-bottom: 1em }
.familie_panel_pay .progressbar p { width: 300px; color: #EEEEEE }
.familie_panel_pay .progressbar { margin-bottom: 2px; background-color: #2D2D2D }
.familie_panel_pay .progressbar .progress { background-color: #434343 }');
					
					echo '
			<div class="familie_panel_pay">';
					
					// vis oversikten over familiene
					foreach ($stats as $row)
					{
						// vis rankstatus
						$rank_status = $row[2] / $max * 100;
						$w = abs($rank_status);
						$link = $row[3] ? '<a href="./?ff_id='.$row[0].'">'.htmlspecialchars($row[1]).'</a>' : htmlspecialchars($row[1]);
						
						echo '
				<div class="progressbar">
					<div class="progress'.($row[2] < 0 ? ' ff_progress_negative' : '').'" style="width: '.round($w).'%">
						<p>'.$link.' ('.game::format_number($rank_status, 2).' %)</p>
					</div>
				</div>';
					}
					
					echo '
			</div>';
					
					if ($this->ff->access(1))
					{
						echo '
			<p><a href="./?ff_id='.$this->ff->id.'&amp;fff_compare&amp;buy">Kjøp ny informasjon &raquo;</a></p>';
					}
					
					if (access::has("mod") && !isset($_GET['override']))
					{
						echo '
			<p><a href="./?ff_id='.$this->ff->id.'&amp;fff_compare&amp;override">Vis nåværende informasjon som moderator &raquo;</a></p>';
					}
				}
			}
			
			elseif (isset($_GET['fff_rank']))
			{
				$rank_info = $this->ff->get_rank_info();
				
				ess::$b->page->add_css('
.familie_panel_pay .progressbar p { width: 300px; color: #EEEEEE }
.familie_panel_pay .progressbar { margin-bottom: 2px; background-color: #2D2D2D }
.familie_panel_pay .progressbar .progress { background-color: #434343 }');
				
				echo '
			<p>Bidrag fordelt på medlemmer:</p>
			<div class="familie_panel_pay">';
				
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
						<p>Tidligere medlemmer av '.$this->ff->type['refobj'].' - teller ikke ('.game::format_number($rank_info['others']['percent_text'], 1).' %)</p>
					</div>
				</div>';
				}
				
				echo '
			</div>
			<p><a href="./?ff_id='.$this->ff->id.'">&laquo; Tilbake</a></p>';
			}
		
			else
			{
				echo '
			<p><a href="./?ff_id='.$this->ff->id.'&amp;fff_compare">Sammenlikne med de andre broderskapene &raquo;</a></p>
			<p>Hvis et av medlemmene forlater broderskapet i konkurranseperioden, vil broderskapet <u>miste</u> ranken dette medlemmet har opptjent. Inviteres en spiller på nytt telles ranken fra det tidspunktet spilleren ble medlem på nytt.</p>
			<p>Minstekravet for rank <u>må</u> klares. Selv om minstekravet blir oppnådd, er det broderskapet i konkurransen som har opptjent <u>mest rank som overlever</u>.</p>
			<p>Hvis broderskapet ikke vinner konkurransen, vil broderskapet dø ut.</p>
			<p>Hvis broderskapet vinner konkurransen, må leder/nestleder velge en bygning broderskapet skal ha som tilholdssted innen <u>24 timer</u> etter konkurransen er avsluttet for ikke å miste broderskapet.</p>
			<p><a href="./?ff_id='.$this->ff->id.'&amp;fff_rank">Vis oversikt over medlemmers bidrag &raquo;</a></p>';
			}
			
			echo '
		</div>';
		}
		
		elseif ($this->ff->competition && $this->ff->data['fff_time_expire'])
		{
			echo '
		<div class="section">
			<h2>Konkurransemodus</h2>
			<p>Broderskapet er i konkurransemodus. <a href="./?fff_id='.$this->ff->data['fff_id'].'">Vis konkurransedetaljer</a></p>';
			
			// vis tidsgraf
			$time_status = (time()-$this->ff->data['fff_time_start']) / ($this->ff->data['fff_time_expire']-$this->ff->data['fff_time_start']);
			$time_status = min(100, max(0, $time_status*100));
			
			echo '
			<div class="progressbar" style="margin: 1em 0">
				<div class="progress" style="width: '.round($time_status).'%">
					<p>Tidsstatus: '.game::format_number($time_status, 1).' %</p>
				</div>
			</div>
		</div>';
		}
		
		elseif ($this->ff->uinfo && !$this->ff->data['br_id'] && $this->ff->data['fff_time_expire'] && $this->ff->data['ff_inactive'] == 0)
		{
			echo '
		<div class="section">
			<h2>Mangler bygning</h2>
			<p class="h_right">Kun synlig for broderskapmedlemmer</p>
			<p>Broderskapet vant broderskapkonkurransen, men leder/nestleder må fremdeles velge bygning for at broderskapet ikke skal dø ut.</p>
			<p>Valg av bygning må skje innen '.game::timespan($this->ff->data['fff_time_expire_br'], game::TIME_ABS).'.</p>'.($this->ff->access(2) ? '
			<p><a href="panel?ff_id='.$this->ff->id.'&amp;a=br">Velg bygning &raquo;</a></p>' : '').'
		</div>';
		}
		
		// har ikke betalt innen fristen?
		if ($this->ff->uinfo)
		{
			$pay_info = $this->ff->pay_info();
			if ($pay_info && !$pay_info['in_time'])
			{
				echo '
		<div class="section">
			<h2>Broderskapkostnaden er ikke betalt</h2>
			<p class="h_right">Kun synlig for broderskapmedlemmer</p>
			<p>Broderskapkostnaden ble ikke betalt i tide. '.ucfirst($this->ff->type['priority'][1]).'/'.$this->ff->type['priority'][2].' må betales dette innen '.ess::$b->date->get($pay_info['next'])->format().' for at broderskapet ikke skal dø ut.</p>
		</div>';
			}
		}
	}
	
	/**
	 * Vis evt. salgsdetaljer
	 */
	protected function sell_details()
	{
		if (!$this->ff->access(2)) return;
		
		$status = $this->ff->sell_status();
		if (!$status) return;
		
		// er vi kjøperen?
		if ($status['up_id'] == $this->ff->uinfo->id)
		{
			echo '
		<div class="section">
			<h2>Salg av broderskap</h2>
			<p><user id="'.$status['init_up_id'].'" /> har åpnet salg av broderskapet til deg. <a href="panel?ff_id='.$this->ff->id.'&amp;a=sell">Godta/avslå kjøp &raquo;</a></p>
		</div>';
		}
		
		// er vi selgeren?
		elseif ($status['init_up_id'] == $this->ff->uinfo->id)
		{
			echo '
		<div class="section">
			<h2>Salg av broderskapet</h2>
			<p>Du har åpnet salg av broderskapet med <user id="'.$status['up_id'].'" /> for '.game::format_cash($status['amount']).'. <a href="panel?ff_id='.$this->ff->id.'&amp;a=sell">Detaljer &raquo;</a></p>
		</div>';
		}
		
		else
		{
			echo '
		<div class="section">
			<h2>Salg av broderskapet</h2>
			<p class="h_right">Kun synlig for broderskapmedlemmer</p>
			<p><user id="'.$status['init_up_id'].'" /> har åpnet salg av broderskapet til <user id="'.$status['up_id'].'" /> for '.game::format_cash($status['amount']).'. <a href="panel?ff_id='.$this->ff->id.'&amp;a=sell">Detaljer &raquo;</a></p>
		</div>';
		}
	}
	
	/**
	 * Vis evt. invitasjon
	 */
	protected function invite_details()
	{
		// ikke invitert?
		if (!$this->up || !isset($this->ff->members['invited'][$this->up->id])) return;
		
		$member = $this->ff->members['invited'][$this->up->id];
		
		// vis skjema
		echo '
		<div class="bg1_c" style="margin: 20px auto; width: '.($member->data['ffm_parent_up_id'] ? '250' : '180').'px">
			<h2 class="bg1">Invitasjon<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1">
				<p class="c">Du er invitert til '.$this->ff->type['refobj'].'.</p>
				<dl class="dd_right">
					<dt>Posisjon</dt>
					<dd>'.ucfirst($member->get_priority_name()).($member->data['ffm_parent_up_id'] ? ' underordnet <user id="'.$member->data['ffm_parent_up_id'].'" />' : '').'</dd>
				</dl>
				<form action="?ff_id='.$this->ff->id.'" method="post">
					<p class="c">
						<input type="submit" class="button" name="invite_apply" value="Godta" />
						<input type="submit" class="button" name="invite_decline" value="Avslå" />
					</p>
				</form>
			</div>
		</div>';
	}
	
	/**
	 * Oversitk over medlemmer
	 */
	protected function members_details()
	{
		// ingen medlemmer?
		if (count($this->ff->members['members']) == 0)
		{
			// ikke vis at vi ikke har medlemmer for enkelte typer
			if ($this->ff->type['type'] == "vapbes" || $this->ff->type['type'] == "bomberom" || $this->ff->type['type'] == "sykehus") return;
			
			$memberstring = $this->ff->type['type'] == "familie" ? "medlemmer" : "ansatte";
			
			echo '
		<p'.($this->ff->type['type'] != "familie" ? ' class="c"' : '').'>'.ucfirst($this->ff->type['refobj']).' har ingen '.$memberstring.'.</p>';
			
			return;
		}
		
		// familie?
		if ($this->ff->type['type'] == "familie")
		{
			$this->members_details_familie();
		}
		
		else
		{
			$this->members_details_firma();
		}
	}
	
	/**
	 * Oversikt over medlemmer for familie
	 */
	protected function members_details_familie()
	{
		ess::$b->page->add_css('
/** Familiehierarki */
.familie_hier { padding: 0; margin: 15px 0 15px }
.familie_hier_c { overflow: hidden }
.familie_hier .boks { float: left; background-color: #303030; padding: 7px; margin: 0 10px 0 0 }
.familie_hier .ff_priority { color: #99CC33; font-weight: bold }
.familie_hier .boks_p, .familie_hier .boks {
	margin: 0 10px 0 0;
	float: left;
	line-height: 20px;
}
.familie_hier .boks_p {
	width: 100px;
	margin-top: 10px;
	padding: 0 5px;
	text-align: right;
}
.familie_hier .boks {
	background-color: #303030;
	margin-top: 10px;
	padding: 0 5px;
}
.familie_hier .ff_capo {
	background-color: #2A2A2A;
	display: block;
	margin: 0 -5px;
	padding: 0 5px;
}');
		
		echo '
		<div class="familie_hier">';
		
		for ($i = 1; $i <= 2; $i++)
		{
			if (isset($this->ff->members['members_priority'][$i]))
			{
				$players = array();
				foreach ($this->ff->members['members_priority'][$i] as $member)
				{
					$players[] = '<user id="'.$member->id.'" />';
				}
				
				echo '
			<div class="familie_hier_c">
				<p class="boks_p">
					<span class="ff_priority">'.ucfirst($this->ff->type['priority'][$i]).'</span>
				</p>
				<p class="boks">
					'.implode('<br />
					', $players).'
				</p>
			</div>';
			}
		}
		
		if (isset($this->ff->members['members_priority'][3]) || isset($this->ff->members['members_priority'][3]))
		{
			$soldiers = count($this->ff->members['members_parent']) > 0;
			
			echo '
			<div class="familie_hier_c">
				<p class="boks_p">
					<span class="ff_priority">'.ucfirst($this->ff->type['priority'][3]).'</span>'.($soldiers ? '<br />
					<span class="ff_priority">'.ucfirst($this->ff->type['priority'][4]).'</span>' : '').'
				</p>';
			
			// lag en midlertidig array for å finne ut om noen soldiers mangler capo
			$parents = $this->ff->members['members_parent'];
			
			// vis hver capo med tilhørende soldiers
			foreach ($this->ff->members['members_priority'][3] as $capo)
			{
				$soldiers = array();
				if (isset($this->ff->members['members_parent'][$capo->id]))
				{
					foreach ($this->ff->members['members_parent'][$capo->id] as $member)
					{
						$soldiers[] = '<user id="'.$member->id.'" />';
					}
				}
				unset($parents[$capo->id]);
				
				echo '
				<p class="boks">
					<span class="ff_capo"><user id="'.$capo->id.'" /></span>'.(count($soldiers) > 0 ? '
					'.implode("<br />
					", $soldiers) : '').'
				</p>';
			}
			
			// noen uten capo?
			foreach ($parents as $capo_id => $members)
			{
				$soldiers = array();
				foreach ($members as $member) $soldiers[] = '<user id="'.$member->id.'" />';
				
				echo '
				<p class="boks">
					<span class="ff_capo">Ukjent</span>
					'.implode("<br />
					", $soldiers).'
				</p>';
			}
			
			echo '
			</div>';
		}
		
		echo '
		</div>';
		
		// er vi medlem av familien?
		if ($this->ff->uinfo && (!$this->ff->data['ff_is_crew'] || $this->ff->mod))
		{
			echo '
		<p><a href="panel?ff_id='.$this->ff->id.'&amp;a=mi">Vis informasjon om medlemmer &raquo;</a></p>';
		}
	}
	
	/**
	 * Oversikt over medlemmer for firma
	 */
	protected function members_details_firma()
	{
		// vis liste over de ansatte
		echo '
<div class="section center w250">
	<h2>Ansatte</h2>
	<dl class="dd_right">';
		
		foreach ($this->ff->members['members_priority'] as $priority => $members)
		{
			foreach ($members as $member)
			{
				echo '
		<dt>'.game::profile_link($member->data['ffm_up_id'], $member->data['up_name'], $member->data['up_access_level']).'</dt>
		<dd>'.ucfirst($member->get_priority_name()).'</dd>';
			}
		}
		
		echo '
	</dl>
</div>';
	}
	
	/**
	 * Informasjon for avisfirma
	 */
	protected function type_avis()
	{
		// hent publiserte utvivelser
		$pagei = new pagei(pagei::ACTIVE, 1, pagei::PER_PAGE, 2);
		$ffnp_q = $this->up ? "ffnp_ffn_id = ffn_id AND ffnp_up_id = ".$this->up->id : "FALSE";
		$result = $pagei->query("
			SELECT ffn_id, ffn_published_time, ffn_cost, ffn_title, ffn_sold, ffn_description, ffnp_time
			FROM ff_newspapers LEFT JOIN ff_newspapers_payments ON $ffnp_q
			WHERE ffn_ff_id = {$this->ff->id} AND ffn_published != 0
			ORDER BY ffn_published_time DESC");
		
		// ingen publiserte utgivelser?
		if ($result->rowCount() == 0)
		{
			echo '
<p class="c">Ingen avisutgivelser er publisert.</p>';
		}
		
		else
		{
			echo '
<p class="c">'.$pagei->total.' utgivelse'.($pagei->total == 1 ? '' : 'r').' er publisert:</p>';
			
			while ($row = $result->fetch())
			{
				echo '
<div class="section center w200">
	<h2><a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$row['ffn_id'].'">'.htmlspecialchars($row['ffn_title']).'</a></h2>
	<dl class="dd_right">
		<dt>Publisert</dt>
		<dd>'.ess::$b->date->get($row['ffn_published_time'])->format().'</dd>
		<dt>Solgte utgaver</dt>
		<dd>'.game::format_number($row['ffn_sold']).'</dd>
		<dt>Pris</dt>
		<dd>'.game::format_cash($row['ffn_cost']).'</dd>
		<dt>Kjøpt?</dt>
		<dd>'.($row['ffnp_time'] ? '<a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$row['ffn_id'].'">Ja</a> ('.ess::$b->date->get($row['ffnp_time'])->format().')' : 'Nei [<a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$row['ffn_id'].'">Kjøp</a>]').'</dd>
	</dl>
	<div class="p">'.$this->ff->format_description($row['ffn_description']).'</div>
</div>';
			}
			
			echo '
<p class="c"><a href="avis?ff_id='.$this->ff->id.'">Vis alle publiserte utgivelser</a></p>';
		}
		
		echo '
<div class="hr fhr"><hr /></div>';
	}
	
	/**
	 * Informasjon for bankfirma
	 */
	protected function type_bank()
	{
		global $_game;
		
		// hent antall klienter og totalt bankinnskudd
		$result = \Kofradia\DB::get()->query("SELECT COUNT(up_id), SUM(up_bank) FROM users_players WHERE up_access_level != 0 AND up_access_level < {$_game['access_noplay']} AND up_bank_ff_id = {$this->ff->id}");
		$row = $result->fetch(\PDO::FETCH_NUM);
		$num_klienter = $row[0];
		$bank_value = (string) $row[1];
		if (mb_strlen($bank_value) > 2)
		{
			$bank_value = round(mb_substr($bank_value, 0, 3), -1) . str_repeat("0", mb_strlen($bank_value)-3);
		}
		
		// finn ut nåværende status
		$status = $this->ff->params->get("bank_overforing_tap_change", 0);
		$status_text = $status == 0 ? 'Ingen endring' : ($status > 0 ? 'Øke '.game::format_number($status*100, 2).' %' : 'Synke '.game::format_number(abs($status)*100, 2).' %');
		
		// nåværende overføringsgebyr
		$overforing_tap = $this->ff->params->get("bank_overforing_tap", 0);
		
		// finn ut hvor lang tid det er til neste endring
		if ($status == 0)
		{
			$next_update = 0;
		}
		else
		{
			$date = ess::$b->date->get();
			$next_update = 3600 - $date->format("i")*60 - $date->format("s");
		}
		
		// finn "tilgjengelige" overføringer
		$expire = time() - 3600;
		$result = \Kofradia\DB::get()->query("SELECT COUNT(ffbt_id), SUM(ffbt_amount), SUM(ffbt_profit) FROM ff_bank_transactions WHERE ffbt_ff_id = {$this->ff->id} AND ffbt_up_id = 0 AND ffbt_time > $expire");
		$info = $result->fetch(\PDO::FETCH_NUM);
		
		echo '
<div class="section" style="width: 250px; margin-left: auto; margin-right: auto">
	<h2>Bankinformasjon</h2>
	<dl class="dd_right">
		<dt>Overføringsgebyr</dt>
		<dd>'.game::format_number($overforing_tap*100, 2).' %</dd>'.($next_update != 0 ? '
		<dt>Neste endring</dt>
		<dd>'.$status_text.'</dd>
		<dt>Tid før neste endring</dt>
		<dd>'.game::counter($next_update).'</dd>' : '').'
	</dl>
	<dl class="dd_right">
		<dt>Antall klienter</dt>
		<dd>'.game::format_number($num_klienter).'</dd>
		<dt>Omtrentlige verdier i banken</dt>
		<dd>'.game::format_cash($bank_value).'</dd>
	</dl>
	<dl class="dd_right">
		<dt>Uhentede overføringer</dt>
		<dd>'.game::format_number($info[0]).'</dd>
		
		<dt>&nbsp;</dt>
		<dd>'.game::format_cash($info[2]).'</dd>
		
		<dt>&nbsp;</dt>
		<dd>('.game::format_cash($info[1]).')</dd>
	</dl>
</div>';
	}
	
	/**
	 * Informasjon om våpen/beskyttelse
	 */
	protected function type_vapbes()
	{
		// logget inn og FF aktivert?
		if ($this->up && $this->ff->active)
		{
			// i fengsel eller bomberom?
			if ($this->up->fengsel_require_no(false) || $this->up->bomberom_require_no(false)) return;
			
			// vise informasjon om våpen?
			if (isset($_GET['vap']))
			{
				$this->type_vapbes_vap();
				$this->ff->load_page();
			}
			
			// kjøpe våpen?
			if (isset($_GET['vap_kjop']))
			{
				$this->type_vapbes_vap_kjop();
				$this->ff->load_page();
			}
			
			// vise tilgjengelig beskyttelse?
			if (isset($_GET['bes']))
			{
				$this->type_vapbes_bes();
				$this->ff->load_page();
			}
			
			// kjøpe beskyttelse?
			if (isset($_GET['bes_kjop']))
			{
				$this->type_vapbes_bes_kjop();
				$this->ff->load_page();
			}
			
			// kontroller anti-bot for kjøp av kuler
			$this->bullets_antibot = antibot::get("kuler", 1);
			$this->bullets_antibot->check_required(ess::$s['relative_path']."/ff/?ff_id={$this->ff->id}");
			
			// kan vi kjøpe kuler?
			if ($this->up->weapon)
			{
				// sett opp skjema for å kjøpe kuler
				$this->bullets_form = new form("bullets");
				
				// skal vi kjøpe kuler?
				if (isset($_POST['buy_bullets']))
				{
					$this->bullets_buy();
				}
			}
		}
		
		// vise informasjon om kuler?
		if (isset($_GET['bul']))
		{
			$this->type_vapbes_bul();
			$this->ff->load_page();
		}
		
		// kolonneoppsett
		echo '
<div class="col2_w firmavapbes" style="margin: 35px 30px">
	<div class="col_w left">
		<div class="col" style="margin-right: 15px">';
		
		// vis informasjon om våpen
		echo '
<div class="bg1_c">
	<h1 class="bg1">Våpen<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>For å angripe en annen spiller behøver du våpen og kuler. Å angripe en spiller kan gi gevinst på flere måter, men kan også bli en kostbar affære hvis man stadig er uheldig med drapsforsøkene.</p>';
		
		// har ikke noe våpen?
		if ($this->up && !$this->up->weapon)
		{
			echo '
		<p><b>Du har ingen våpen og kan ikke angripe noen andre spillere!</b></p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;vap_kjop">Kjøp våpen</a></p>';
		}
		
		elseif ($this->up)
		{
			$training = $this->up->data['up_weapon_training'] * 100;
			
			// vis detaljer
			echo '
		<dl class="dd_right">
			<dt>Ditt våpen</dt>
			<dd>'.$this->up->weapon->data['name'].'</dd>
		</dl>
		<div class="progressbar p'.($training < 28 ? ' levelcrit' : ($training < 35 ? ' levelwarn' : '')).'">
			<div class="progress" style="width: '.round(min(100, $training)).'%">
				<p>Våpentrening: '.($training == 100 ? '100' : game::format_num($training, 2)).' %</p>
			</div>
		</div>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;vap_kjop">Oppgrader våpen</a></p>';
		}
		
		echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;vap">Vis oversikt over og generell informasjon om våpen</a></p>
	</div>
</div>';
		
		// vis informasjon om kuler
		echo '
<div class="bg1_c" style="margin-top: 20px">
	<h1 class="bg1">Kuler<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Når man angriper en spiller må man bestemme antall kuler man ønsker å angripe spilleren med.</p>
		<p>Flere kuler gir større sannsynlighet for å treffe en spiller, men ved bruk av mange kuler risikerer man at en del kuler ikke treffer spilleren.</p>';
		
		// har vi ikke noe våpen?
		if ($this->up && !$this->up->weapon)
		{
			echo '
		<p>Du har ikke noe våpen og kan ikke kjøpe kuler.</p>';
		}
		
		elseif ($this->up)
		{
			// klokka ikke mellom 20 og 22?
			$h = ess::$b->date->get()->format("H");
			$time_ok = true;
			if ($h < 20 || $h >= 22)
			{
				$ant = 0;
				$time_ok = false;
			}
			
			else
			{
				// finn ut hvor mange kuler som er til salgs
				$result = \Kofradia\DB::get()->query("SELECT COUNT(*) FROM bullets WHERE bullet_ff_id = {$this->ff->id} AND bullet_time <= ".time()." AND (bullet_freeze_time = 0 OR bullet_freeze_time <= ".time().")");
				$ant = $result->fetchColumn(0);
			}
			
			echo '
		<p>Du har <b>'.$this->up->data['up_weapon_bullets'].'</b> '.fword('kule', 'kuler', $this->up->data['up_weapon_bullets']).'. Pris per kule er '.game::format_cash($this->up->weapon->data['bullet_price']).'.</p>';
			
			// utenfor tidsrommet?
			if (!$time_ok)
			{
				echo '
		<p>Kuler kan kun kjøpes dersom det er tilgjengelig mellom kl. 20:00 og 22:00.</p>';
			}
			
			elseif ($ant == 0)
			{
				echo '
		<p>Ingen kuler er for øyeblikket tilgjengelig å kjøpe.</p>';
			}
			
			else
			{
				echo '
		<p>Det er for øyeblikket '.fwords("<b>%d</b> kule", "<b>%d</b> kuler", $ant).' til salgs.</p>
		<form action="" method="post">
			<input type="hidden" name="h" value="'.$this->bullets_form->create().'"  />
			<p class="c">Antall kuler: <input type="text" name="bullets" value="'.htmlspecialchars(postval("bullets", 1)).'" class="styled w30" /> '.show_sbutton("Kjøp", 'name="buy_bullets"').'</p>
		</form>';
			}
		}
		
		echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;bul">Vis informasjon om kuler</a></p>
	</div>
</div>';
		
		// kolonneoppsett
		echo '
		</div>
	</div>
	<div class="col_w right">
		<div class="col" style="margin-left: 15px">';
		
		// vis informasjon om beskyttelse
		echo '
<div class="bg1_c">
	<h1 class="bg1">Beskyttelse<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Beskyttelsen passer på helsen din når du blir angrepet. En bedre beskyttelse fører til at du mister mindre helse ved et angrep.</p>
		<p>Beskyttelsen din blir svekket ved et angrep. Om beskyttelsen skulle falle under 20 % vil den bli erstattet med det forrige alternativet, hvis et slikt alternativ finnes.</p>';
		
		// har ikke noe beskyttelse?
		if ($this->up && !$this->up->protection->data)
		{
			echo '
		<p><b>Du har ingen beskyttelse og vil være ekstra utsatt ved et angrep!</b></p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;bes_kjop">Kjøp beskyttelse</a></p>';
		}
		
		elseif ($this->up)
		{
			$protection = $this->up->get_protection_percent();
			
			// vis detaljer
			echo '
		<dl class="dd_right">
			<dt>Din beskyttelse</dt>
			<dd>'.$this->up->protection->data['name'].'</dd>
		</dl>
		<div class="progressbar p'.($protection < 20 ? ' levelcrit' : ($protection < 50 ? ' levelwarn' : '')).'">
			<div class="progress" style="width: '.round(min(100, $protection)).'%">
				<p>Status: '.($protection == 100 ? '100' : game::format_num($protection, 2)).' %</p>
			</div>
		</div>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;bes_kjop">Oppgrader beskyttelse</a></p>';
		}
		
		echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;bes">Vis oversikt over beskyttelser</a></p>
	</div>
</div>';
		
		// slutt på kolonneoppsett
		echo '
		</div>
	</div>
</div>';
	}
	
	/**
	 * Vise informasjon om våpen
	 */
	protected function type_vapbes_vap()
	{
		echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Oversikt over våpen<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<table class="table tablemt center">
			<thead>
				<tr>
					<th>Nummer</th>
					<th>Navn</th>
					<th>Pris</th>
					<th>Kulekapasitet</th>
					<th>Kulepris</th>
					<th>Rank krav</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		foreach (weapon::$weapons as $weapon)
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="r">'.$i.'</td>
					<td>'.htmlspecialchars($weapon['name']).'</td>
					<td class="r">'.game::format_cash($weapon['price']).'</td>
					<td class="r">'.game::format_number($weapon['bullets']).'</td>
					<td class="r">'.game::format_cash($weapon['bullet_price']).'</td>
					<td>'.($weapon['rank'] ? game::$ranks['items_number'][$weapon['rank']]['name'] : '<i>Ingen</i>').'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
		<p>Gevinster man kan oppnå ved å gjennomføre drapsforsøk:</p>
		<ul class="spacer">
			<li>Telleren på profilen øker ved både vellykket og mislykket drapsforsøk. Dette kan gi deg økte muligheter til å bli med i et broderskap og på den måten få økt beskyttelse</li>
			<li>Hvis du klarer å skade eller drepe en spiller mottar du mye rank som blir overført fra den du angriper (mer rank ved mer skade på offeret)</li>
			<li>Ved et vellykket drap mottar du pengene spilleren har på hånda, i tillegg til det som måtte ligge på etterlyst</li>
		</ul>
		<p>Annen informasjon</p>
		<ul class="spacer">
			<li>Styrken du klarer å angripe med blir svekket av at en spiller har forskjellig rank enn deg. Dessto lengre unna deg i rank en spiller er desto mindre styrke klarer du å angripe med</li>
			<li>Hvis man ikke opprettholder en våpentrening på minst 25 % risikerer man å miste våpenet sitt og må kjøpe det dårligste våpenet først</li>
		</ul> 
		<p>Våpenet kan du trene opp på samme side som du <a href="'.ess::$s['relative_path'].'/angrip">angriper en annen spiller</a>.</p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake til firmaet</a></p>
	</div>
</div>';
	}
	
	/**
	 * Kjøpe våpen
	 */
	protected function type_vapbes_vap_kjop()
	{
		// i feil bydel?
		if ($this->up->data['up_b_id'] != $this->ff->data['br_b_id'])
		{
			ess::$b->page->add_message("Du må befinne deg i samme bydel som firmaet for å kunne kjøpe/oppgradere våpenet ditt.", "error");
			redirect::handle();
		}
		
		// kan vi ikke kjøpe våpen for øyeblikket?
		if (DISABLE_BUY_VAP && !access::has("mod"))
		{
			ess::$b->page->add_message("Kjøp av våpen er for øyeblikket deaktivert.", "error");
			redirect::handle();
		}
		
		redirect::store("?ff_id={$this->ff->id}&vap_kjop");
		
		// finn ut hvilket våpen vi kan kjøpe
		$training = $this->up->data['up_weapon_training'];
		if (!$this->up->weapon)
		{
			$next_id = 1;
		}
		else
		{
			$next_id = $this->up->data['up_weapon_id'] + 1;
		}
		
		// det neste våpenet
		$next = isset(weapon::$weapons[$next_id]) ? weapon::$weapons[$next_id] : false;
		
		// sjekk ventetid
		$expire = time() - 86400 * 2; // 48 timer mellom hvert kjøp
		$wait = max(0, $this->up->data['up_weapon_time'] - $expire);
		
		// rank ok?
		$rank_ok = $next && $next['rank'] <= $this->up->rank['number'];
		
		// kjøpe våpen?
		if (isset($_POST['buy']))
		{
			if (!$next || !isset($_POST['weap_id']) || $_POST['weap_id'] != $next_id || $wait > 0 || ($training < 0.4 && $next_id != 1) || !$rank_ok || $this->up->data['up_weapon_bullets_auksjon'] > 0) redirect::handle();
			
			// har vi ikke nok penger?
			if ($next['price'] > $this->up->data['up_cash'])
			{
				ess::$b->page->add_message("Du har ikke nok penger på hånda til å kjøpe dette våpenet.", "error");
				redirect::handle();
			}
			
			// forsøk å trekk fra pengene og gi våpen
			$a = \Kofradia\DB::get()->exec("
				UPDATE users_players
				SET up_weapon_id = $next_id, up_weapon_training = ".($next_id == 1 ? '0.1' : 'up_weapon_training * 0.7').", up_weapon_training_next = NULL, up_weapon_bullets = 0, up_weapon_time = ".time().", up_cash = up_cash - {$next['price']}
				WHERE up_id = ".$this->up->id." AND up_cash >= {$next['price']} AND up_weapon_id ".($this->up->data['up_weapon_id'] ? "= ".\Kofradia\DB::quote($this->up->data['up_weapon_id']) : "IS NULL")." AND (up_weapon_time IS NULL OR up_weapon_time <= $expire)");
			
			// noe gikk galt?
			if ($a == 0)
			{
				ess::$b->page->add_message("Kunne ikke kjøpe våpen.", "error");
			}
			
			// nytt våpen kjøpt
			else
			{
				// sett tidspunkt for kjøp
				ess::$b->page->add_message("Du har kjøpt våpenet <b>".htmlspecialchars($next['name'])."</b>. Din våpentrening er nå på ".game::format_num($next_id == 1 ? 10 : $this->up->data['up_weapon_training']*0.7*100, 1)." % og du har 0 kuler.");
			}
			
			redirect::handle();
		}
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">'.($this->up->weapon ? 'Oppgrader våpen' : 'Kjøp våpen').'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1"><boxes />'.($this->up->weapon ? '
		<p>Ditt våpen:<br /><b>'.htmlspecialchars($this->up->weapon->data['name']).'</b> med '.fwords("<b>%d</b> kule", "<b>%d</b> kuler", $this->up->data['up_weapon_bullets']).' og <b>'.game::format_num($training*100, 1).' %</b> våpentrening</p>' : '');
		
		// har vi et bedre våpen vi kan kjøpe?
		if (!$next)
		{
			echo '
		<p>Du har det beste våpenet som kan kjøpes for øyeblikket.</p>
		<p>Husk at hvis våpentreningen din faller under 25 %, risikerer du å miste våpenet ditt.</p>';
		}
		
		// har vi ikke høy nok rank til å kjøpe høyere beskyttelse?
		elseif (!$rank_ok)
		{
			echo '
		<p>Du har ikke høy nok rank til å oppgradere til <b>'.htmlspecialchars($next['name']).'</b>. Du må oppnå ranken <b>'.game::$ranks['items_number'][$next['rank']]['name'].'</b>.</p>';
		}
		
		// har ikke høy nok våpentrening?
		elseif ($training < 0.4 && $next_id != 1)
		{
			echo '
		<p>Du må oppnå en våpentrening på minst 40 % før du kan oppgradere til et bedre våpen.</p>';
		}
		
		// har kuler på auksjon?
		elseif ($this->up->data['up_weapon_bullets_auksjon'] > 0)
		{
			echo '
		<p>Du har kuler på auksjon eller har bydd på en auksjon for kuler og må vente til auksjonene har blitt avsluttet før du kan oppgradere våpen.</p>';
		}
		
		else
		{
			// har bedre våpen tilgjengelig
			echo '
		<p>Følgende våpen er tilgjengelig:<br /><b>'.htmlspecialchars($next['name']).'</b> ('.game::format_cash($next['price']).')</p>';
			
			// må vente?
			if ($wait > 0)
			{
				echo '
		<p>Du må vente '.game::counter($wait, true).' før du kan oppgradere våpenet ditt.</p>';
			}
			
			else
			{
				echo ($next_id > 1 ? '
		<p>Når du oppgraderer våpenet ditt vil du miste alle kulene dine og våpentreningen vil synke.</p>' : '').'
		<form action="" method="post">
			<input type="hidden" name="weap_id" value="'.$next_id.'" />
			<p class="c">'.show_sbutton("Kjøp dette våpenet", 'name="buy"').'</p>
		</form>';
			}
		}
		
		echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;vap">Vis oversikt over våpen</a></p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake til firmaet</a></p>
	</div>
</div>';
	}
	
	/**
	 * Vise tilgjengelig beskyttelse
	 */
	protected function type_vapbes_bes()
	{
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Oversikt over beskyttelser<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<table class="table tablemt center">
			<thead>
				<tr>
					<th>Nummer</th>
					<th>Navn</th>
					<th>Pris</th>
					<th>Rank krav</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		foreach (protection::$protections as $protection)
		{
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="r">'.$i.'</td>
					<td>'.htmlspecialchars($protection['name']).'</td>
					<td class="r">'.game::format_cash($protection['price']).'</td>
					<td>'.($protection['rank'] ? game::$ranks['items_number'][$protection['rank']]['name'] : '<i>Ingen</i>').'</td>
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
		<p>Du må ha en beskyttelse på minst 80 % for å kunne oppgradere til den neste beskyttelsen. Har du under 80 % må du først fornye den beskyttelsen du har.</p>
		<p>Andre fordeler som gir økt beskyttelse:</p>
		<ul class="spacer">
			<li>Være medlem av et broderskap, høyere medlemskap gir økt beskyttelse</li>
			<li>Bli angrepet av en spiller med en rank annerledes enn egen, større avstand betyr svakere angrep</li>
			<li>Oppnådd en av de 3 topp-plassering-rankene (men denne fordelen forsvinner hvis en spiller med høyere eller tilsvarende topp-plassering-rank angriper deg og blir svakere dersom angriper har lavere topp-plassering-rank)</li>
		</ul> 
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake til firmaet</a></p>
	</div>
</div>';
	}
	
	/**
	 * Kjøpe beskyttelse
	 */
	protected function type_vapbes_bes_kjop()
	{
		// i feil bydel?
		if ($this->up->data['up_b_id'] != $this->ff->data['br_b_id'])
		{
			ess::$b->page->add_message("Du må befinne deg i samme bydel som firmaet for å kunne kjøpe/oppgradere beskyttelsen din.", "error");
			redirect::handle();
		}
		
		// kan vi ikke kjøpe beskyttelse for øyeblikket?
		if (DISABLE_BUY_PROT && !access::has("mod"))
		{
			ess::$b->page->add_message("Kjøp av beskyttelse er for øyeblikket deaktivert.", "error");
			redirect::handle();
		}
		
		redirect::store("?ff_id={$this->ff->id}&bes_kjop");
		
		// finn ut hvilken beskyttelse vi kan kjøpe
		$protection = $this->up->get_protection_percent();
		if (!$this->up->protection->data)
		{
			$next_id = 1;
		}
		else
		{
			$next_id = $this->up->data['up_protection_id'];
			if ($protection == 100 || ($protection >= 80 && isset(protection::$protections[$next_id+1]))) $next_id++;
		}
		
		// den neste beskyttelsen
		$next = isset(protection::$protections[$next_id]) ? protection::$protections[$next_id] : false;
		
		// sjekk ventetid
		$expire = time() - 86400 * 2;
		$wait = max(0, $this->up->data['up_protection_time'] - $expire);
		
		// rank ok?
		$rank_ok = $next && $next['rank'] <= $this->up->rank['number'];
		
		// kjøpe beskyttelse?
		if (isset($_POST['buy']))
		{
			if (!$next || !isset($_POST['prot_id']) || $_POST['prot_id'] != $next_id || $wait > 0 || !$rank_ok) redirect::handle();
			
			// har vi ikke nok penger?
			if ($next['price'] > $this->up->data['up_cash'])
			{
				ess::$b->page->add_message("Du har ikke nok penger på hånda til å kjøpe denne beskyttelsen.", "error");
				redirect::handle();
			}
			
			// forsøk å trekk fra pengene og gi beskyttelse
			$a = \Kofradia\DB::get()->exec("
				UPDATE users_players
				SET up_protection_id = $next_id, up_protection_state = 1, up_protection_time = ".time().", up_cash = up_cash - {$next['price']}
				WHERE up_id = ".$this->up->id." AND up_cash >= {$next['price']} AND up_protection_id ".(!$this->up->protection->data ? 'IS NULL' : '= '.\Kofradia\DB::quote($this->up->data['up_protection_id']))." AND (up_protection_time IS NULL OR up_protection_time <= $expire)");
			
			// noe gikk galt?
			if ($a == 0)
			{
				ess::$b->page->add_message("Kunne ikke kjøpe beskyttelse.", "error");
			}
			
			// ny beskyttelse kjøpt
			else
			{
				// sett tidspunkt for kjøp
				ess::$b->page->add_message("Du har kjøpt beskyttelsen <b>".htmlspecialchars($next['name'])."</b> og er nå beskyttet av denne.");
			}
			
			redirect::handle();
		}
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">'.($this->up->protection->data ? 'Oppgrader beskyttelse' : 'Kjøp beskyttelse').'<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1"><boxes />'.($this->up->protection->data ? '
		<p>Din beskyttelse:<br />'.htmlspecialchars($this->up->protection->data['name']).' ('.game::format_num($protection, 2).' %)</p>' : '');
		
		// har vi en høyere beskyttelse vi kan kjøpe?
		if (!$next)
		{
			echo '
		<p>Du har den beste beskyttelsen som kan kjøpes for øyeblikket. Beskyttelsen din er på 100 %.</p>';
		}
		
		// har vi ikke høy nok rank til å kjøpe høyere beskyttelse?
		elseif (!$rank_ok)
		{
			echo '
		<p>Du har ikke høy nok rank til å oppgradere til <b>'.htmlspecialchars($next['name']).'</b>. Du må oppnå ranken <b>'.game::$ranks['items_number'][$next['rank']]['name'].'</b>.</p>';
		}
		
		else
		{
			// har bedre beskyttelse tilgjengelig
			echo '
		<p>Følgende beskyttelse er tilgjengelig:<br /><b>'.htmlspecialchars($next['name']).'</b> ('.game::format_cash($next['price']).')</p>';
			
			// samme som den vi allerede har?
			if ($this->up->protection->data && $this->up->data['up_protection_id'] == $next_id)
			{
				echo '
		<p>Du må ha over 80 % beskyttelse for å kunne oppgradere til en bedre type beskyttelse.</p>';
			}
			
			// må vente?
			if ($wait > 0)
			{
				echo '
		<p>Du må vente '.game::counter($wait, true).' før du kan oppgradere beskyttelsen din.</p>';
			}
			
			else
			{
				echo '
		<form action="" method="post">
			<input type="hidden" name="prot_id" value="'.$next_id.'" />
			<p class="c">'.show_sbutton("Kjøp denne beskyttelsen", 'name="buy"').'</p>
		</form>';
			}
		}
		
		echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;bes">Vis oversikt over beskyttelser</a></p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake til firmaet</a></p>
	</div>
</div>';
	}
	
	/**
	 * Behandle kjøp av kuler
	 */
	protected function bullets_buy()
	{
		// kontroller skjema
		$this->bullets_form->validate(postval("h"), "Kjøpe kuler");
		
		// kan vi ikke kjøpe kuler for øyeblikket?
		if (DISABLE_BUY_VAP && !access::has("mod"))
		{
			ess::$b->page->add_message("Kjøp av kuler er for øyeblikket deaktivert.", "error");
			redirect::handle();
		}
		
		// har vi ikke valgt antall kuler som skal kjøpes
		$buy_ant = (int) postval("bullets");
		if ($buy_ant <= 0)
		{
			ess::$b->page->add_message("Du må skrive inn antall kuler du ønsker å kjøpe.", "error");
			redirect::handle();
		}
		
		// finn ut hvor mange kuler som er til salgs
		$result = \Kofradia\DB::get()->query("SELECT COUNT(*) FROM bullets WHERE bullet_ff_id = {$this->ff->id} AND bullet_time <= ".time()." AND (bullet_freeze_time = 0 OR bullet_freeze_time <= ".time().")");
		$ant = $result->fetchColumn(0);
		
		// for mange kuler vi ønsker å kjøpe?
		$h = ess::$b->date->get()->format("H");
		if ($buy_ant > $ant || $h < 20 || $h >= 22) // eller klokka er ikke mellom 20 og 22
		{
			ess::$b->page->add_message("Det er ikke så mange kuler til salgs.", "error");
			return;
		}
		
		// vil dette føre til at vi får for mange kuler?
		if ($this->up->data['up_weapon_bullets'] + $this->up->data['up_weapon_bullets_auksjon'] + $buy_ant > $this->up->weapon->data['bullets'])
		{
			ess::$b->page->add_message("Du har ikke plass til så mange kuler. Du kan maksimalt ha <b>".$this->up->weapon->data['bullets']."</b>.".($this->up->data['up_weapon_bullets_auksjon'] > 0 ? " (Teller også med kuler du forsøker å selge/kjøpe på auksjon.)" : ""), "error");
			return;
		}
		
		// har vi ikke nok penger?
		$price = $buy_ant * $this->up->weapon->data['bullet_price'];
		
		if ($this->up->data['up_cash'] < $price)
		{
			ess::$b->page->add_message("Du har ikke nok penger på hånda. For å kjøpe $buy_ant ".fword("kule", "kuler", $buy_ant)." må du ha ".game::format_cash($price)." på hånda.", "error");
			return;
		}
		
		
		\Kofradia\DB::get()->beginTransaction();
		
		// forsøk å skaff alle kulene
		$a = \Kofradia\DB::get()->exec("
			UPDATE bullets
			SET bullet_freeze_up_id = ".$this->up->id.", bullet_freeze_time = ".(time()+self::BULLET_FREEZE_WAIT)."
			WHERE bullet_ff_id = {$this->ff->id} AND bullet_time <= ".time()." AND (bullet_freeze_time = 0 OR bullet_freeze_time <= ".time().")
			ORDER BY bullet_time
			LIMIT $buy_ant");
		
		// feil antall kuler anskaffet?
		if ($a != $buy_ant)
		{
			// reverser transaksjon
			\Kofradia\DB::get()->rollback();
			
			// informer
			ess::$b->page->add_message("Det er ikke så mange kuler til salgs.", "error");
			
			return;
		}
		
		\Kofradia\DB::get()->commit();
		
		// kjør anti-bot
		$this->bullets_antibot->increase_counter();
		$this->bullets_antibot->check_required(ess::$s['relative_path']."/ff/?ff_id={$this->ff->id}");
		
		redirect::handle();
	}
	
	/**
	 * Vise informasjon om kuler
	 */
	protected function type_vapbes_bul()
	{
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Informasjon om kuler<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>For å kunne kjøpe og oppbevare kuler må man være i besittelse av et våpen.</p>
		<p>Antall kuler som blir produsert hver dag er avhengig av hvor mange spillere som har vært aktive de siste dagene.</p>
		<p>Kuler er kun mulig å kjøpe mellom kl. 20:00 og 22:00. Når kuler blir lagt ut for salg er tilfeldig, så det er viktig å følge med om man ønsker å få tak i noen kuler!</p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake til firmaet</a></p>
	</div>
</div>';
	}
	
	/**
	 * Bomberom
	 */
	protected function type_bomberom()
	{
		// konkurrerende broderskap har ikke bomberom
		if ($this->ff->competition) return;
		
		// deaktivert?
		if (!$this->ff->active) return;
		
		// ønsker vi å endre spilleren som er ansvarlig for vår spiller?
		if ($this->ff->type['type'] != "familie" && $this->up && isset($_GET['brom_ans']))
		{
			$this->type_bomberom_ans();
		}
		
		// vise liste over spillere som vi kan sette i bomberom?
		if ($this->ff->type['type'] != "familie" && $this->up && isset($_GET['brom_list']))
		{
			$this->type_bomberom_list();
		}
		
		// kan kun vise bomberom til familie hvis man er medlem eller sitter i det
		if ($this->ff->type['type'] == "familie" && !$this->up) return;
		if ($this->ff->type['type'] == "familie" && !$this->ff->uinfo && (!$this->up->bomberom_check() || $this->up->data['up_brom_ff_id'] != $this->ff->id)) return;
		
		// finn ut hvor mange ledige plasser det er
		$places = $this->ff->get_bomberom_places();
		
		// ønsker vi å forlate bomberommet?
		if ($this->up && isset($_POST['leave_brom']))
		{
			$this->type_bomberom_leave();
		}
		
		$fam = $this->ff->type['type'] == "familie";
		$minimize = $fam && !$this->up->bomberom_check();
		
		if ($minimize)
		{
			ess::$b->page->add_js_domready('
$("brom_hidden").removeClass("hide");
$("brom_visible").addClass("hide");
$("brom_hidden").getElement("a").addEvent("click", function(e)
{
	e.stop();
	$("brom_hidden").addClass("hide");
	$("brom_visible").removeClass("hide");
});
');
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Bomberom<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_right"><a href="&rpath;/node/39">Hjelp</a></p>'.($minimize ? '
	<div class="bg1 hide" id="brom_hidden">
		<p class="c"><a href="#">Vis bomberommet</a></p>
	</div>' : '').'
	<div class="bg1" id="brom_visible">
		<boxes />
		<p>Ved å gå i bomberom beskytter du deg selv mot å bli angrepet. Så lenge du sitter i et bomberom kan ingen andre spillere angripe deg.</p>
		<p>'.($fam ? ucfirst($this->ff->type['priority'][1]).' og '.ucfirst($this->ff->type['priority'][2]) : 'Bomberommet').' kan kaste deg ut hvis de ønsker, så helt trygg vil du ikke være. Hvis du blir kastet ut vil du bli flyttet til en tilfeldig bydel.</p>';
		
		if ($this->up && $this->ff->type['type'] != "familie")
		{
			echo '
		<div class="section">
			<h2>Ansvar for din spiller</h2>'.($this->up->data['up_brom_up_id'] ? '
			<p><user id="'.$this->up->data['up_brom_up_id'].'" /> kan plassere din spiller i bomberom og se hvor du oppholder deg.</p>' : '
			<p>Du har ikke gitt noen spillere mulighet for å sette deg i bomberom.</p>').'
			<p><a href="./?ff_id='.$this->ff->id.'&amp;brom_ans">Endre</a></p>
			<p><a href="./?ff_id='.$this->ff->id.'&amp;brom_list">Vis spillere du kan sette i bomberom &raquo;</a></p>
		</div>';
		}
		
		// ikke i bomberom?
		if ($this->up && !$this->up->bomberom_check())
		{
			echo '
		<div class="section">
			<h2>Plassere spiller i bomberom</h2>'.ess::$b->page->message_get("bomberom_set", true, true);
			
			if ($this->up->fengsel_check())
			{
				echo '
			<p>Du er for øyeblikket i fengsel og kan ikke plassere deg selv eller andre i bomberom.</p>';
			}
			
			else
			{
				$ok = true;
				
				// ikke i samme bydel?
				if ($this->up->data['up_b_id'] != $this->ff->data['br_b_id'])
				{
					$ok = false;
					echo '
			<p>Du befinner deg for øyeblikket i en annen bydel enn '.($fam ? 'broderskapet' : 'bomberommet').' og kan ikke plassere deg selv eller andre i '.($fam ? 'bomberommet til broderskapet' : 'det').'.</p>';
				}
				
				// ingen ledige plasser?
				elseif ($places['free'] == 0)
				{
					// er vi medlem av firmaet?
					if ($this->ff->access(true))
					{
						echo '
			<p>Bomberommet er egentlig fullt, men du kan alikevel sette deg selv i bomberommet.</p>';
					}
					
					else
					{
						$ok = false;
						echo '
			<p>Det er ingen ledige plasser i bomberommet.</p>';
					}
				}
				
				if ($ok)
				{
					$this->type_bomberom_set($places['free'], $places['in_brom']);
				}
			}
			
			echo '
		</div>';
		}
		
		echo '
	</div>
</div>';
		
		// er vi for øyeblikket i bomberom?
		if ($this->up && $this->up->bomberom_check())
		{
			$wait = $this->up->bomberom_wait();
			ess::$b->page->message_get("bomberom_set");
			
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">I bomberom<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1 c" style="color: #BBB">
		<!--<p style="float: right; margin: 10px 0 10px 10px"><img src="'.STATIC_LINK.'/other/bomberom.jpg" alt="I bomberom" style="border: 2px solid #333333" /></p>-->
		<p style="margin-top: 30px; text-align: center; font-size: 150%">I bomberom</p>
		<p style="margin-top: 20px">Du befinner deg i bomberom frem til '.ess::$b->date->get($this->up->data['up_brom_expire'])->format(date::FORMAT_SEC).'.</p>
		<p style="margin-top: 20px">'.game::counter($wait, true).' gjenstår</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p style="margin-top: 20px">'.show_sbutton("Forlat bomberom", 'name="leave_brom"').'</p>
		</form>
	</div>
</div>';
		}
	}
	
	/**
	 * Endre spilleren som er ansvarlig for egen spiller
	 */
	protected function type_bomberom_ans()
	{
		redirect::store("?ff_id={$this->ff->id}&brom_ans");
		
		// fjerne ansvarlig spiller?
		if (isset($_POST['brom_ans_remove']))
		{
			if ($this->up->data['up_brom_up_id'])
			{
				// fjern ansvarlig spiller
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_up_id = NULL WHERE up_id = ".$this->up->id);
				ess::$b->page->add_message('<user id="'.$this->up->data['up_brom_up_id'].'" /> kan ikke lenger flytte spilleren din og sette den i bomberom.');
				
				putlog("LOG", "BOMBEROM ANSVARLIG: ".$this->up->data['up_name']." fjernet up_id=".$this->up->data['up_brom_up_id']." som ansvarlig for spilleren sin");
			}
			redirect::handle();
		}
		
		// sette en spiller som ansvarlig?
		if (isset($_POST['brom_ans_set']) && isset($_POST['player']))
		{
			// finn spilleren
			$result = \Kofradia\DB::get()->query("
				SELECT up_id, up_name, up_access_level
				FROM users_players
				WHERE up_name = ".\Kofradia\DB::quote($_POST['player'])."
				ORDER BY up_access_level = 0, up_last_online DESC
				LIMIT 1");
			$player = $result->fetch();
			
			// fant ikke spilleren?
			if (!$player)
			{
				ess::$b->page->add_message("Fant ikke spilleren.", "error");
			}
			
			// seg selv?
			elseif ($player['up_id'] == $this->up->id)
			{
				ess::$b->page->add_message("Du kan allerede sette deg selv i bomberom.", "error");
			}
			
			// er allerede den ansvarlige?
			elseif ($player['up_id'] == $this->up->data['up_brom_up_id'])
			{
				ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> kan allerede sette deg i bomberom.', "error");
			}
			
			// ikke levende?
			elseif ($player['up_access_level'] == 0)
			{
				ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> er ikke levende og kan ikke settes som ansvarlig.', "error");
			}
			
			// kan ikke sette som ansvarlig?
			elseif (!login::$user->player->can_set_brom(player::get($player['up_id'])))
			{
				ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> kan ikke settes som ansvarlig for din spiller. Les mer <a href="/node">på hjelpesidene</a>.', "error");
			}
			
			else
			{
				// sett som ansvarlig
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_up_id = {$player['up_id']} WHERE up_id = ".$this->up->id);
				
				putlog("LOG", "BOMBEROM ANSVARLIG: ".$this->up->data['up_name']." satt ".$player['up_name']." som ansvarlig for spilleren sin");
				
				ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> har nå mulighet til å sette deg i bomberom.');
				redirect::handle();
			}
		}
		
		ess::$b->page->add_title("Endre ansvarlig spiller");
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Endre ansvarlig spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Hvis du er bortreist kan du velge en annen spiller som får mulighet til å plassere deg i bomberom. Hvis den spilleren dør, vil ansvaret bli gitt videre til spilleren som hadde ansvar for den spilleren.</p>
		<p>Spilleren som kan plassere deg i bomberom vil til enhver tid kunne se hvor du oppholder deg.</p>
		<p>En spiller kan ikke sette deg i bomberom dersom rankforskjellen mellom dere er mer enn 3 trinn. Unntak hvis man er i samme broderskap.</p>';
		
		if ($this->up->data['up_brom_up_id'])
		{
			echo '
		<form action="" method="post">
			<p>For øyeblikket kan <user id="'.$this->up->data['up_brom_up_id'].'" /> plassere deg i bomberom. Spilleren vil også kunne flytte deg til en annen bydel samtidig som du blir plassert i et bomberom.</p>
			<p class="c">'.show_sbutton("Fjern spiller", 'name="brom_ans_remove"').'</p>
		</form>';
		}
		
		else
		{
			echo '
		<p>Du har ikke gitt noen spillere ansvar for din spiller.</p>';
		}
		
		echo '
		<form action="" method="post">
			<p class="c">Gi ansvar til: <input type="text" name="player" class="styled w100" value="'.htmlspecialchars(postval("player")).'" /> '.show_sbutton("Utfør", 'name="brom_ans_set"').'</p>
		</form>';
		
		// hent ut medlemmer av familier som kan sette spilleren i bomberom
		$result = \Kofradia\DB::get()->query("
			SELECT DISTINCT up_id, up_name, up_access_level
			FROM users_players, ff, ff_members, (
				SELECT ffm_ff_id ff_id FROM ff_members WHERE ffm_up_id = ".$this->up->id." AND ffm_status = 1
			) ref
			WHERE ffm_ff_id = ref.ff_id AND ff.ff_id = ref.ff_id AND ff_type = 1 AND ffm_up_id != ".$this->up->id." AND ffm_status = 1 AND ffm_up_id = up_id AND ff_inactive = 0 AND ff_is_crew = 0
			ORDER BY up_name");
		if ($result->rowCount() > 0)
		{
			echo '
		<p>Spillere som kan plassere deg og som du kan plassere i bomberom gjennom broderskap:</p>
		<ul>';
			
			while ($row = $result->fetch())
			{
				echo '
			<li>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</li>';
			}
			
			echo '
		</ul>
		<p>Spillere som er med i broderskapet du er med i kan også sette deg i bomberom. De kan derimot ikke flytte deg til en annen bydel for å gjøre det.</p>';
		}
		
		echo '
		<p class="c"><a href="&rpath;/node/39">Mer informasjon</a></p>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Vis oversikt over spillere vi kan sette i bomberom
	 */
	protected function type_bomberom_list()
	{
		redirect::store("?ff_id={$this->ff->id}&brom_list");
		
		// sette en ny spiller som ansvarlig?
		if (isset($_POST['brom_ans_move']))
		{
			// mangler spiller?
			if (!isset($_POST['player']))
			{
				ess::$b->page->add_message("Du må velge en spiller.", "error");
				redirect::handle();
			}
			
			// hent informasjon om spilleren og kontroller at vi har ansvar for den
			$up_id = (int) $_POST['player'];
			$result = \Kofradia\DB::get()->query("
				SELECT up_id, up_name, up_access_level, up_brom_up_id
				FROM users_players
				WHERE up_id = $up_id");
			$player = $result->fetch();
			
			// fant ikke spilleren
			if (!$player)
			{
				ess::$b->page->add_message("Fant ikke spilleren.", "error");
				redirect::handle();
			}
			
			// deaktivert?
			if ($player['up_access_level'] == 0)
			{
				ess::$b->page->add_message('<user id="'.$player['up_id'].'" /> er deaktivert.', "error");
				redirect::handle();
			}
			
			// er ikke ansvarlig for den?
			if ($player['up_brom_up_id'] != $this->up->id)
			{
				ess::$b->page->add_message('Du kan ikke sette <user id="'.$player['up_id'].'" /> i bomberom.', "error");
				redirect::handle();
			}
			
			// har vi valgt en spiller det skal overføres til?
			if (isset($_POST['player_new']) || isset($_POST['player_new_id']))
			{
				// finn spilleren
				$where = isset($_POST['player_new_id']) ? "up_id = ".((int)$_POST['player_new_id']) : "up_name = ".\Kofradia\DB::quote($_POST['player_new']);
				$result = \Kofradia\DB::get()->query("
					SELECT up_id, up_name, up_access_level
					FROM users_players
					WHERE $where
					ORDER BY up_access_level = 0, up_last_online DESC
					LIMIT 1");
				$player_new = $result->fetch();
				
				// fant ikke spilleren?
				if (!$player_new)
				{
					ess::$b->page->add_message("Fant ikke spilleren.", "error");
				}
				
				// seg selv?
				elseif ($player_new['up_id'] == $player['up_id'])
				{
					ess::$b->page->add_message("Spilleren kan allerede sette seg selv i bomberom.", "error");
				}
				
				// oss?
				elseif ($player_new['up_id'] == $this->up->id)
				{
					ess::$b->page->add_message("Du er allerede den ansvarlige.", "error");
				}
				
				// ikke levende?
				elseif ($player_new['up_access_level'] == 0)
				{
					ess::$b->page->add_message('<user id="'.$player_new['up_id'].'" /> er ikke levende og kan ikke settes som ansvarlig.', "error");
				}
				
				else
				{
					// bekreftet?
					if (isset($_POST['confirm']))
					{
						validate_sid();
						
						// sett som ansvarlig
						\Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_up_id = {$player_new['up_id']} WHERE up_id = {$player['up_id']}");
						
						putlog("LOG", "BOMBEROM ANSVARLIG: ".$this->up->data['up_name']." satt ".$player_new['up_name']." som ansvarlig for {$player['up_name']}");
						
						ess::$b->page->add_message('Du gav bort muligheten for å sette <user id="'.$player['up_id'].'" /> i bomberom til <user id="'.$player_new['up_id'].'" />.');
						redirect::handle();
					}
					
					ess::$b->page->add_title("Gi bort ansvar for spiller");
					
					echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Gi bort ansvar for spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Du er i ferd med å gi bort ansvaret for å kunne sette <user id="'.$player['up_id'].'" /> i bomberom til <user id="'.$player_new['up_id'].'" />.</p>
		<p>Dette vil resultere i at du ikke lenger kan sette spilleren i bomberom.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="player" value="'.$player['up_id'].'" />
			<input type="hidden" name="player_new_id" value="'.$player_new['up_id'].'" />
			<input type="hidden" name="brom_ans_move" />
			<p class="c">'.show_sbutton("Bekreft overføring", 'name="confirm"').'</p>
		</form>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;brom_list">Tilbake</a></p>
	</div>
</div>';
					
					$this->ff->load_page();
				}
			}
			
			ess::$b->page->add_title("Gi bort ansvar for spiller");
			
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Gi bort ansvar for spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Du er i ferd med å gi bort ansvaret for å kunne sette <user id="'.$player['up_id'].'" /> i bomberom til en annen spiller.</p>
		<p>Dette vil resultere i at du ikke lenger kan sette spilleren i bomberom.</p>
		<form action="" method="post">
			<input type="hidden" name="player" value="'.$player['up_id'].'" />
			<p class="c">Gi ansvar til: <input type="text" name="player_new" class="styled w100" value="'.htmlspecialchars(postval("player_new")).'" /> '.show_sbutton("Fortsett", 'name="brom_ans_move"').'</p>
		</form>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;brom_list">Tilbake</a></p>
	</div>
</div>';
			
			$this->ff->load_page();
		}
		
		ess::$b->page->add_title("Oversikt over ansvar");
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Oversikt over ansvar<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Denne listen viser hvilke spillere som har gitt deg mulighet til å sette dem i bomberom. Du har muligheten til å gi ansvaret videre til en annen spiller.</p>';
		
		// hent spillere vi har ansvar for
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, up_b_id, up_fengsel_time, up_brom_expire
			FROM users_players
			WHERE up_brom_up_id = ".$this->up->id." AND up_access_level != 0
			ORDER BY up_name");
		$ansvar = array();
		while ($row = $result->fetch())
		{
			$ansvar[] = $row;
		}
		
		if (count($ansvar) == 0)
		{
			echo '
		<p>Det er ingen spillere som har gitt deg muligheten til å sette spilleren i bomberom.</p>';
		}
		
		else
		{
			echo '
		<form action="" method="post">
			<table class="table center">
				<thead>
					<tr>
						<th>Spiller</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			foreach ($ansvar as $row)
			{
				$i_bomberom = $row['up_brom_expire'] > time();
				$i_fengsel = $row['up_fengsel_time'] > time();
				$bydel = &game::$bydeler[$row['up_b_id']];
				
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="player" value="'.$row['up_id'].'" />'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td>Oppholder seg på '.htmlspecialchars($bydel['name']);
				
				if ($i_bomberom)
				{
					echo '<br />I bomberom (til '.ess::$b->date->get($row['up_brom_expire'])->format(date::FORMAT_SEC).')';
				}
				
				elseif ($i_fengsel)
				{
					echo '<br />I fengsel (til '.ess::$b->date->get($row['up_fengsel_time'])->format(date::FORMAT_SEC).')';
				}
				
				echo '</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Gi ansvar til en annen spiller", 'name="brom_ans_move"').'</p>
		</form>';
		}
		
		echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Sette seg selv eller andre i bomberommet
	 */
	protected function type_bomberom_set($ledige_plasser, $ant_i_bomberommet)
	{
		// plassere i bomberom?
		if (isset($_POST['hours']) && isset($_POST['player']))
		{
			$this->type_bomberom_set_handle($ledige_plasser, $ant_i_bomberommet);
			return;
		}
		
		// hent spillere vi har ansvar for
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, up_b_id, up_fengsel_time, up_brom_expire
			FROM users_players
			WHERE up_brom_up_id = ".$this->up->id."
			ORDER BY up_name");
		$ansvar = array();
		while ($row = $result->fetch())
		{
			$ansvar[] = $row;
		}
		
		// hent spillere vi er i familie med
		// hent ut medlemmer av familier som kan sette spilleren i bomberom
		$result = \Kofradia\DB::get()->query("
			SELECT DISTINCT up_id, up_name, up_access_level, up_b_id, up_fengsel_time, up_brom_expire
			FROM users_players, ff, ff_members, (
				SELECT ffm_ff_id ff_id FROM ff_members WHERE ffm_up_id = ".$this->up->id." AND ffm_status = 1
			) ref
			WHERE ffm_ff_id = ref.ff_id AND ff.ff_id = ref.ff_id AND ff_type = 1 AND ffm_up_id != ".$this->up->id." AND ffm_status = 1 AND ffm_up_id = up_id AND ff_inactive = 0 AND ff_is_crew = 0
			ORDER BY up_name");
		$familie = array();
		while ($row = $result->fetch())
		{
			$familie[] = $row;
		}
		
		// sett opp prisliste
		$price = bomberom::PRICE_HOUR + $ant_i_bomberommet * bomberom::PRICE_EACH_PLAYER;
		if ($this->ff->access(true)) $price *= bomberom::PRICE_FACTOR_OWN;
		$prices = array(
			"own" => $price
		);
		if ($ledige_plasser > 0)
		{
			foreach (array_merge($ansvar, $familie) as $row)
			{
				$price = bomberom::PRICE_HOUR + $ant_i_bomberommet * bomberom::PRICE_EACH_PLAYER;
				$price *= bomberom::PRICE_FACTOR_OTHER;
				$prices[$row['up_id']] = $price;
			}
		}
		
		ess::$b->page->add_js_domready('
(function(){
	var brom_price_obj = $("brom_price");
	var brom_time_obj = $("brom_time");
	var brom_price_p = $("brom_price_p");
	var brom_prices = '.js_encode($prices).';
	var brom_hours = $("brom_hours");
	var cp = -1, l;
	$$("#brom_player input[type=radio]").addEvent("click", function()
	{
		cp = brom_prices[this.get("value")];
		recalc();
	}).addEvent("unclick", function() { cp = -1; brom_price_obj.set("text", "Venter"); });
	function recalc()
	{
		var v = brom_hours.get("value");
		if (v == "") { brom_price_obj.set("text", "Venter"); brom_time_obj.set("text", "Venter"); brom_hours.focus(); return; }
		if (v != l) {
			v = v.toInt() || 0;
			v = Math.max(1, Math.min('.bomberom::MAX_HOURS.', v));
			l = v;
			brom_hours.set("value", v);
		}
		if (cp != -1) {
			var p = v * cp;
			brom_price_obj.set("text", format_number(p) + " kr");
			var d = new Date($time()+window.servertime_offset+v*3600000);
			brom_time_obj.set("text", str_pad(d.getHours()) + ":" + str_pad(d.getMinutes()) + ":" + str_pad(d.getSeconds()));
			brom_price_p.set("value", p);
		}
		brom_hours.focus();
	}
	brom_hours.addEvent("change", recalc).addEvent("keyup", recalc).focus();
	$$("#brom_player input:checked").fireEvent("click");
})();
');
		
		echo '
		<form action="" method="post" id="brom_player" autocomplete="off">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="price" id="brom_price_p" value="" />
			<p>Det er '.fwords("%d spiller", "%d spillere", $ant_i_bomberommet).' i bomberommet og '.fwords("%d ledig plass", "%d ledige plasser", $ledige_plasser).'.</p>
			<p>Prisen er avhenig av hvor stor etterspørsel det er for bomberommet for øyeblikket.</p>
			<div class="center" style="background-color: #161616; padding: 5px; width: 70%; border: 2px solid #1F1F1F">
				<dl class="dd_right" style="margin: 0">
					<dt>Antall timer</dt>
					<dd><input type="text" name="hours" id="brom_hours" class="styled w30" value="'.htmlspecialchars(postval("hours", "")).'" /></dd>
					<dt>Total kostnad</dt>
					<dd id="brom_price">Ukjent</dd>
					<dt>Til</dt>
					<dd id="brom_time">Ukjent</dd>
				</dl>
				<p style="margin-bottom: 0" class="c">'.show_sbutton("Plasser i bomberom").'</p>
			</div>
			
			<p>Velg spiller:</p>
			<table class="table tablemb center">
				<tbody>
					<tr class="box_handle">
						<td colspan="2"><input type="radio" name="player" value="own" checked="checked" />Deg selv</td>
					</tr>';
		
		$familie_wait = 0;
		if ($ledige_plasser > 0)
		{
			// spillere vi har direkte ansvar for
			if (count($ansvar) > 0)
			{
				echo '
					<tr>
						<th colspan="2" style="border-top: 10px solid #181818">Spillere du har fått ansvar for:</th>
					</tr>';
				
				$i = 0;
				foreach ($ansvar as $row)
				{
					$this->type_bomberom_set_row($row, $i);
				}
			}
			
			// spillere vi er i familie med
			if (count($familie) > 0)
			{
				$familie_wait = max(0, $this->up->data['up_brom_ff_time'] + bomberom::FAMILIY_MEMBERS_WAIT - time());
				
				echo '
					<tr>
						<th colspan="2" style="border-top: 10px solid #181818">Spillere du er i broderskap med:</th>
					</tr>';
				
				$i = 0;
				foreach ($familie as $row)
				{
					$this->type_bomberom_set_row($row, $i, true, $familie_wait);
				}
			}
		}
		
		echo '
				</tbody>
			</table>
		</form>';
		
		if ($familie_wait > 0)
		{
			echo '
		<p>Du plasserte et broderskapmedlem i bomberom '.ess::$b->date->get($this->up->data['up_brom_ff_time'])->format().'.</p>
		<p>Du må vente '.game::counter($familie_wait).' før du kan plassere et nytt broderskapmedlem i bomberom.</p>';
		}
	}
	
	protected function type_bomberom_set_row($row, &$i, $familie = null, $familie_wait = 0)
	{
		$i_bomberom = $row['up_brom_expire'] > time();
		$i_fengsel = $row['up_fengsel_time'] > time();
		$feil_bydel = $familie && $row['up_b_id'] != $this->ff->data['br_b_id'];
		$bydel = &game::$bydeler[$row['up_b_id']];
		
		$ok = $familie_wait == 0 && !$i_bomberom && !$i_fengsel && !$feil_bydel;
		
		$info = "";
		if ($i_bomberom)
		{
			$info = 'I bomberom<br />(til '.ess::$b->date->get($row['up_brom_expire'])->format(date::FORMAT_SEC).')';
		}
		
		elseif ($i_fengsel)
		{
			$info = 'I fengsel<br />(til '.ess::$b->date->get($row['up_fengsel_time'])->format(date::FORMAT_SEC).')';
		}
		
		elseif ($feil_bydel)
		{
			$info = 'Oppholder seg på '.htmlspecialchars($bydel['name']).'<br />(kan ikke flytte)';
		}
		
		echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td'.($info == "" ? ' colspan="2"' : '').'><input type="radio" name="player" value="'.$row['up_id'].'"'.(!$ok ? ' disabled="disabled"' : '').' />'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>'.($info != "" ? '
						<td>'.$info.'</td>' : '').'
					</tr>';
	}
	
	/**
	 * Plassere spiller i bomberom
	 */
	protected function type_bomberom_set_handle($ledige_plasser, $ant_i_bomberommet)
	{
		$player = login::$user->player;
		$self = true;
		$familie = false;
		
		// plassere en annen spiller?
		if ($_POST['player'] != "own")
		{
			// ingen ledige plasser?
			if ($ledige_plasser == 0)
			{
				ess::$b->page->add_message("Det er ingen ledige plasser til å plassere andre spillere i bomberommet.", "error", null, "bomberom_set");
				redirect::handle();
			}
			
			// hent spillerinfo
			$player = player::get(postval("player"));
			if (!$player || !$player->active || $player->bomberom_check() || $player->fengsel_check())
			{
				ess::$b->page->add_message("Kan ikke plassere spilleren i bomberom.", "error", null, "bomberom_set");
				redirect::handle();
			}
			
			// kan ikke sette i bomberom pga. rankforskjell?
			if (!login::$user->player->can_set_brom($player))
			{
				ess::$b->page->add_message('Kan ikke plassere <user id="'.$player->id.'" /> i bomberom pga. spilleren sin rank er for langt unna din rank. Se mer i <a href="&rpath;node/39">hjelp</a>.', "error", null, "bomberom_set");
				redirect::handle();
			}
			
			// kontroller at vi er ansvarlig eller har tilgang pga. familie
			if ($player->data['up_brom_up_id'] != $this->up->id)
			{
				// sjekk at vi er i en felles familie
				$result = \Kofradia\DB::get()->query("
					SELECT ffm_ff_id
					FROM ff, ff_members, (
						SELECT ffm_ff_id ff_id FROM ff_members WHERE ffm_up_id = ".$this->up->id." AND ffm_status = 1
					) ref
					WHERE ffm_ff_id = ref.ff_id AND ff.ff_id = ref.ff_id AND ff_type = 1 AND ffm_up_id = $player->id AND ffm_status = 1
					LIMIT 1");
				
				// ikke i felles familie?
				if ($result->rowCount() == 0)
				{
					ess::$b->page->add_message('Du kan ikke plassere <user id="'.$player->id.'" /> i bomberom.', "error", null, "bomberom_set");
					redirect::handle();
				}
				
				$familie = true;
			}
			
			// kan vi ikke flytte spilleren?
			if ($familie && $player->data['up_b_id'] != $this->ff->data['br_b_id'])
			{
				ess::$b->page->add_message('<user id="'.$player->id.'" /> er ikke i samme bydel som bomberommet. Du har ikke mulighet til å flytte spilleren og kan dermed ikke plassere spilleren i dette bomberommt.', "error", null, "bomberom_set");
				redirect::handle();
			}
			
			$self = false;
		}
		
		// familie og vi har allerede plassert en spiller de siste 12 timene?
		if ($familie)
		{
			$familie_wait = max(0, $this->up->data['up_brom_ff_time'] + bomberom::FAMILIY_MEMBERS_WAIT - time());
			if ($familie_wait > 0)
			{
				ess::$b->page->add_message("Du kan ikke plassere medlemmer av noen broderskap du er med i for øyeblikket, fordi du plasserte forrige spiller fra broderskap ".ess::$b->date->get($this->up->data['up_brom_ff_time'])->format().". Du må vente ".game::counter($familie_wait).".", "error", null, "bomberom_set");
				redirect::handle();
			}
		}
		
		// sett opp timepris
		$price_hour = bomberom::PRICE_HOUR + $ant_i_bomberommet * bomberom::PRICE_EACH_PLAYER;
		if (!$self) $price_hour *= bomberom::PRICE_FACTOR_OTHER;
		if ($self && $this->ff->access(true)) $price_hour *= bomberom::PRICE_FACTOR_OWN;
		
		// sjekke pris?
		$hours = (int) $_POST['hours'];
		if ($hours <= 0)
		{
			ess::$b->page->add_message("Du må skrive inn et gyldig antall timer.", "error", null, "bomberom_set");
		}
		
		elseif ($hours > bomberom::MAX_HOURS)
		{
			ess::$b->page->add_message("Du kan maksimalt sette en spiller i bomberom i ".bomberom::MAX_HOURS." timer.", "error", null, "bomberom_set");
		}
		
		else
		{
			// bekreftet pris?
			if (isset($_POST['price']) && $_POST['price'] != "")
			{
				validate_sid();
				
				// beregn pris
				$price = $hours * $price_hour;
				
				// har prisen endret seg?
				if (postval("price") != $price)
				{
					ess::$b->page->add_message("Prisen har endret seg og du må bekrefte på nytt.", "error", null, "bomberom_set");
				}
				
				else
				{
					\Kofradia\DB::get()->beginTransaction();
					
					// trekk fra pengene
					$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = ".$this->up->id." AND up_cash >= $price");
					if ($a == 0)
					{
						ess::$b->page->add_message("Du har ikke så mye penger på hånda.", "error", null, "bomberom_set");
					}
					
					else
					{
						$expire = (time()+$hours*3600);
						$b_id = !$self && !$familie ? ', up_b_id = '.$this->ff->data['br_b_id'] : '';
						
						// sett spilleren i bomberommet
						$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_ff_id = {$this->ff->id}, up_brom_expire = $expire$b_id WHERE up_id = $player->id AND up_brom_expire = {$player->data['up_brom_expire']}");
						
						// kunne ikke plassere spilleren i bomberommet?
						if ($a == 0)
						{
							ess::$b->page->add_message("Kunne ikke plassere spilleren i bomberommet.", "error", null, "bomberom_set");
							\Kofradia\DB::get()->rollback();
						}
						
						else
						{
							// send logg til spilleren
							if (!$self)
							{
								$player->add_log("bomberom_set", $this->up->id.":".urlencode($this->ff->data['ff_name']).":$expire", $this->ff->id);
							}
							
							// oppdatere tidspunkt for familie?
							if ($familie)
							{
								\Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_ff_time = ".time()." WHERE up_id = ".$this->up->id);
							}
							
							// gi penger til firmaet
							$this->ff->bank(ff::BANK_TJENT, round($price * ff::BOMBEROM_PERCENT));
							
							putlog("DF", "BOMBEROM ANSVARLIG: ".$this->up->data['up_name']." satt%c3 ".($self ? 'seg selv' : $player->data['up_name'])."%c i bomberom i firmaet {$this->ff->data['ff_name']} for $hours timer ".$player->generate_minside_url());
							
							ess::$b->page->add_message("Du plasserte ".($self ? 'deg selv' : '<user id="'.$player->id.'" />')." i bomberommet med en varighet på <b>$hours</b> ".fword("time", "timer", $hours).". Det kostet deg <b>".game::format_cash($price)."</b>.", null, null, "bomberom_set");
							
							\Kofradia\DB::get()->commit();
							redirect::handle("?ff_id={$this->ff->id}");
						}
					}
					
					\Kofradia\DB::get()->commit();
				}
			}
			
			echo '
		'.ess::$b->page->message_get("bomberom_set", true, true).'
		<p>Du er i ferd med å plassere '.($self ? 'deg selv' : $player->profile_link()).' i dette bomberommet.</p>
		<p>For tiden er det '.fwords("%d spiller", "%d spillere", $ant_i_bomberommet).' i bomberommet og '.fwords("%d ledig plass", "%d ledige plasser", $ledige_plasser).'.'.($ledige_plasser == 0 ? ' Du har alikevel plass i bomberommet som medlem av firmaet.' : '').'</p>
		<dl class="dd_right">
			<dt>Antall timer</dt>
			<dd>'.fwords("<b>%d</b> time", "<b>%d</b> timer", $hours).'</dd>
			<dt>Total kostnad</dt>
			<dd>'.game::format_cash($hours * $price_hour).'</dd>
			<dt>Varighet til</dt>
			<dd>'.ess::$b->date->get(time()+$hours*3600)->format(date::FORMAT_SEC).'</dd>
		</dl>
		<form action="" method="post">'.(!$self ? '
			<input type="hidden" name="player" value="'.$player->id.'" />' : '').'
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="hours" value="'.$hours.'" />
			<input type="hidden" name="price" value="'.($hours * $price_hour).'" />
			<p class="c">'.show_sbutton("Sett spilleren i bomberom").'</p>
		</form>
		<form action="" method="post">'.(!$self ? '
			<input type="hidden" name="player" value="'.$player->id.'" />' : '').'
			<p>Endre antall timer: <input type="text" name="hours" class="styled w30" value="'.$hours.'" /> '.show_sbutton("Nytt prisoppsett").'</p>
		</form>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>';
		}
	}
	
	/**
	 * Forlate bomberommet
	 */
	protected function type_bomberom_leave()
	{
		// ikke i bomberom?
		if (!$this->up->bomberom_check()) redirect::handle();
		
		// kontroller sid
		validate_sid();
		
		// bekreftet?
		if (isset($_POST['confirm']))
		{
			// gå ut av bomberommet
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_expire = 0 WHERE up_id = ".$this->up->id);
			
			ess::$b->page->add_message("Du har forlatt bomberommet.");
			redirect::handle();
		}
		
		ess::$b->page->add_title("Forlat bomberommet");
		
		// vis skjema
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Forlat bomberommet<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<p>Du er i ferd med å forlate bomberommet. Du skal egentlig sitte i bomberommet til '.ess::$b->date->get($this->up->data['up_brom_expire'])->format(date::FORMAT_SEC).'.</p>
		<p>Ved å forlate bomberommet får du ikke igjen noe av det har blitt betalt for å bli satt i bomberommet.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="leave_brom" />
			<p class="c">'.show_sbutton("Forlat bomberommet", 'name="confirm"').' <a href="./?ff_id='.$this->ff->id.'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$this->ff->load_page();
	}
	
	/**
	 * Garasjeutleie
	 */
	protected function type_garasjeutleie()
	{
		$price = $this->ff->params->get("garasje_price", ff::GTA_GARAGE_PRICE_DEFAULT);
		
		// forandre leiepris?
		if (isset($_GET['gprice']) && $this->ff->access(1))
		{
			$this->type_garasjeutleie_price($price);
		}
		
		// hent statistikk
		$result = \Kofradia\DB::get()->query("
			SELECT COUNT(DISTINCT ugg_up_id) count_up, COUNT(ugg_id) count_ugg, SUM(ugg_places) sum_ugg_places
			FROM users_garage
				JOIN users_players ON up_id = ugg_up_id AND up_access_level != 0
			WHERE ugg_ff_id = {$this->ff->id}");
		$stats = $result->fetch();
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Utleiefirma for garasje<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<dl class="dd_right">
			<dt>Leiepris per plass</dt>
			<dd>'.game::format_cash($price).'</dd>
			<dt>Antall kunder</dt>
			<dd>'.game::format_num($stats['count_up']).'</dd>
			<dt>Antall garasjer leid ut</dt>
			<dd>'.game::format_num($stats['count_ugg']).'</dd>
			<dt>Antall bilplasser leid ut</dt>
			<dd>'.game::format_num($stats['sum_ugg_places']).'</dd>
		</dl>';
		
		// kan vi forandre leieprisen?
		if ($this->ff->access(1))
		{
			echo '
		<p class="c"><a href="./?ff_id='.$this->ff->id.'&amp;gprice">Forandre leiepris</a></p>';
		}
		
		if ($this->ff->access())
		{
			echo '
		<p class="c">Firmaet får innbetalt '.(ff::GTA_PERCENT*100).' % av det spillerne betaler i leie til firmabanken.</p>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Endre leiepris
	 */
	protected function type_garasjeutleie_price($price)
	{
		// nylig endret?
		$last = $this->ff->params->get("garasje_time_change");
		$expire = time() - 86400*3;
		if ($last > $expire && !access::has("mod"))
		{
			ess::$b->page->add_message("Leieprisen ble sist forandret ".ess::$b->date->get($last)->format().". Du må vente ".game::counter($last - $expire, true)." før leieprisen kan endres på nytt.");
			$this->ff->redirect();
		}
		
		// valgt pris?
		if (isset($_POST['price']))
		{
			$price_new = game::intval($_POST['price']);
			if ($price_new == $price)
			{
				ess::$b->page->add_message("Du må skrive inn en ny pris.", "error");
			}
			
			elseif ($price_new < ff::GTA_GARAGE_PRICE_LOW)
			{
				ess::$b->page->add_message("Leieprisen kan ikke være under ".game::format_cash(ff::GTA_GARAGE_PRICE_LOW).".", "error");
			}
			
			elseif ($price_new > ff::GTA_GARAGE_PRICE_HIGH)
			{
				ess::$b->page->add_message("Leieprisen kan ikke være over ".game::format_cash(ff::GTA_GARAGE_PRICE_HIGH).".", "error");
			}
			
			else
			{
				// lagre ny pris
				$this->ff->params->update("garasje_price", $price_new);
				if (!access::has("mod")) $this->ff->params->update("garasje_time_change", time(), true);
				else $this->ff->params->commit();
				
				ess::$b->page->add_message("Leieprisen ble endret til ".game::format_cash($price_new).".");
				$this->ff->redirect();
			}
		}
		
		echo '
<div class="bg1_c xxsmall" style="width: 280px">
	<h1 class="bg1">Leiepris for garasje<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Nåværende pris per plass</dt>
				<dd>'.game::format_cash($price).'</dd>
				<dt>Ny leiepris per plass</dt>
				<dd><input type="text" class="styled w80" name="price" value="'.game::format_cash(game::intval(postval("price", $price))).'" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Lagre ny pris").' <a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>
			<p>Leieprisen kan settes fra '.game::format_cash(ff::GTA_GARAGE_PRICE_LOW).' til '.game::format_cash(ff::GTA_GARAGE_PRICE_HIGH).' og kan kun justeres hver 3. time</p>
		</form>
	</div>
</div>';
		
		$this->ff->load_page();
	}
}

/**
 * Sykehusfirma
 */
class page_ff_sykehus extends pages_player
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Alternativene for sykehus
	 */
	public static $options = array(
		1 => array(
			"name" => "Mye energi",
			"price" => 3000000,
			"increase" => 0.25
		),
		array(
			"name" => "Middels energi",
			"price" => 1000000,
			"increase" => 0.15
		),
		array(
			"name" => "Lite energi",
			"price" => 500000,
			"increase" => 0.1
		),
		array(
			"name" => "Minst energi",
			"price" => 100000,
			"increase" => 0.05
		)
	);
	
	/**
	 * Maksimal energi man kan ha
	 */
	const ENERGY_MAX = 3; // 300 %
	
	/**
	 * Ventetid mellom hver gang man utfører et alternativ
	 */
	const WAIT = 300; // 5 minutter ventetid
	
	/** Prosent energi for å kunne utføre sykebil valget */
	const ENERGY_SYKEBIL_REQ = 25;
	
	/**
	 * Skjema
	 * @var form
	 */
	protected $form;
	
	/**
	 * Sykehus
	 */
	public function __construct(player $up = null, ff $ff)
	{
		parent::__construct($up);
		$this->ff = $ff;
		
		$show = $this->up && $this->ff->active;
		
		if ($show)
		{
			// i fengsel eller bomberom?
			if ($this->up->fengsel_require_no(false) || $this->up->bomberom_require_no(false)) return;
			
			// sett opp skjema
			$this->form = new form("sykehus");
			
			// utføre et alternativ?
			if (isset($_POST['sykehus']))
			{
				$this->action();
			}
			
			// utføre sykebil
			if (isset($_POST['sykebil']) && $this->sykebil())
			{
				return;
			}
		}
		
		// vis alternativene
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Sykehus<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Sykehus gir deg muligheten til å øke din energi slik at helsen din går mye fortere opp enn hva den ville gjort utenom. Energien kan ved hjelp av sykehus gå over 100 %.</p>';
		
		// ventetid?
		$wait = $this->calc_wait();
		if ($wait > 0)
		{
			echo '
		<p>Du må vente '.game::counter($wait, true).' før du kan benytte deg av sykehus på nytt.</p>';
		}
		
		// i feil bydel?
		elseif ($show && $this->up->data['up_b_id'] != $this->ff->data['br_b_id'])
		{
			echo '
		<p>Du må befinne deg i samme bydel som sykehuset for å kunne benytte deg av det.</p>';
			
			// har vi lite nok energi til å ta sykebil?
			if ($this->up->get_energy_percent() < self::ENERGY_SYKEBIL_REQ)
			{
				echo '
		<div style="background-color: #533; padding: 1px 10px">
			<p>Du har svært lite energi. Hvis du føler du står i fare for å dø og ikke har nok energi for å reise via bydeler, kan du få en sykebil til å hente deg.</p>
			<p>Når du blir hentet av en sykebil, vil du bli fraktet til bydelen sykehuset befinner seg i uten å miste noe helse eller energi. Du vil deretter kunne utføre alternativene for å få energi.</p>
			<p>Ved å benytte seg av dette alternativet <b>må du ofre 25 % av din totale rank</b>.</p>
			<form action="" method="post">
				<p class="c">'.show_sbutton("Be om sykebil", 'name="sykebil"').'</p>
			</form>
		</div>';
			}
		}
		
		elseif ($show)
		{
			// vis alternativene
			echo '
		<form action="" method="post">
			<input type="hidden" name="h" value="'.$this->form->create().'" />
			<table class="table center">
				<thead>
					<tr>
						<th>Alternativ</th>
						<th>Pris</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			$last_id = ess::session_get("sykehus_last_id");
			foreach (self::$options as $id => $row)
			{
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="id" value="'.$id.'"'.($last_id == $id ? ' checked="checked"' : '').' />'.htmlspecialchars($row['name']).'</td>
						<td class="r">'.game::format_cash($row['price']).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Utfør handling", 'name="sykehus"').'</p>
		</form>';
		}
		
		echo '
		<p class="c"><a href="'.ess::$s['relative_path'].'/node/57">Mer informasjon om sykehus</a></p>
	</div>
</div>';
	}
	
	/**
	 * Utfør en handling
	 */
	protected function action()
	{
		// kontroller skjema
		$this->form->validate(postval("h"), "Sykehus");
		
		// mangler vi alternativ?
		if (!isset($_POST['id']) || !isset(self::$options[$_POST['id']]))
		{
			ess::$b->page->add_message("Du må velge et alternativ.", "error");
			redirect::handle();
		}
		$opt = self::$options[$_POST['id']];
		ess::session_put("sykehus_last_id", (int)$_POST['id']);
		
		// i feil bydel?
		if ($this->up->data['up_b_id'] != $this->ff->data['br_b_id'])
		{
			redirect::handle();
		}
		
		// må vi vente?
		$wait = $this->calc_wait();
		if ($wait > 0)
		{
			redirect::handle();
		}
		
		// trekk fra pengene og øk energien
		$a = \Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_cash = up_cash - {$opt['price']}, up_energy = up_energy + (".self::ENERGY_MAX." - up_energy / up_energy_max) * {$opt['increase']} * up_energy_max * GREATEST(0.2, LEAST(1, up_energy / up_energy_max)), up_sykehus_time = ".time()."
			WHERE up_id = ".$this->up->id." AND up_cash >= {$opt['price']}");
		
		// ble ikke oppdatert?
		if ($a == 0)
		{
			ess::$b->page->add_message("Du har ikke nok penger til å utføre dette alternativet.", "error");
			redirect::handle();
		}
		
		putlog("LOG", "SYKEHUS: ".$this->up->data['up_name']." utførte alternativet {$opt['name']}. Hadde ".round($this->up->get_energy_percent(), 4)." % energi før handlingen. ".$this->up->generate_minside_url());
		
		ess::$b->page->add_message("Du utførte alternativet &laquo;".htmlspecialchars($opt['name'])."&raquo;.");
		redirect::handle();
	}
	
	/**
	 * Be som sykebil
	 */
	protected function sykebil()
	{
		// kan ikke bruke sykebil?
		if ($this->up->data['up_b_id'] == $this->ff->data['br_b_id'] || $this->up->get_energy_percent() >= self::ENERGY_SYKEBIL_REQ) return;
		
		// bekreftet?
		if (isset($_POST['confirm']) && validate_sid())
		{
			// ikke bekreftet?
			if (!isset($_POST['c']))
			{
				ess::$b->page->add_message("Du må bekrefte at du mister 25 % rank for å kunne benytte deg av alternativet.", "error");
			}
			
			else
			{
				$this->form->validate(postval("h"), "Sykehus");
				
				// sett ned ranken
				$p = round($this->up->data['up_points'] * 0.25);
				$this->up->increase_rank(-$p, false);
				
				// flytt til korrekt bydel
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_b_id = {$this->ff->data['br_b_id']}, up_b_time = ".time()." WHERE up_id = ".$this->up->id);
				$this->up->data['up_b_id'] = $this->ff->data['br_b_id'];
				unset($this->up->bydel);
				
				// gi  melding
				ess::$b->page->add_message("Du ble hentet av en sykebil og ble fraktet til <b>".htmlspecialchars($this->up->bydel['name'])."</b>. Du kan nå kjøpe energi hos sykehuset. Du mistet ".game::format_num($p)." poeng (".game::format_rank($p)." rank).");
				
				putlog("DF", "%c4%bSYKEBIL:%b%c ".$this->up->data['up_name']." benyttet seg av sykebil og mistet ".game::format_number($p)." rankpoeng. ".$this->up->generate_minside_url());
				
				redirect::handle();
			}
		}
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Sykehus<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<boxes />
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>
		<p>Du har svært lite energi. Hvis du føler du står i fare for å dø og ikke har nok energi for å reise via bydeler, kan du få en sykebil til å hente deg.</p>
		<p>Når du blir hentet av en sykebil, vil du bli fraktet til bydelen sykehuset befinner seg i uten å miste noe helse eller energi. Du vil deretter kunne utføre alternativene for å få energi.</p>
		<p>Ved å benytte seg av dette alternativet <b>må du ofre 25 % av din totale rank</b>.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="sykebil" />
			<input type="hidden" name="h" value="'.$this->form->create().'" />
			<p class="c"><input type="checkbox" id="sykebil_c" name="c" /><label for="sykebil_c"> Jeg bekrefter at jeg mister 25 % av min rank</label></p>
			<p class="c">'.show_sbutton("Be om sykebil", 'name="confirm"').'</p>
		</form>
		<p class="c"><a href="./?ff_id='.$this->ff->id.'">Tilbake</a></p>
	</div>
</div>';
		
		return true;
	}
	
	/**
	 * Finn ut ventetid før neste gang vi kan utføre funksjonen
	 */
	protected function calc_wait()
	{
		if (!$this->up) return 0;
		return max(0, $this->up->data['up_sykehus_time'] - time() + self::WAIT);
	}
}

new page_ff(login::$logged_in ? login::$user->player : null);