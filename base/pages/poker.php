<?php

essentials::load_module("poker_round");

/*
 * Ulike state:
 * 1=nytt spill
 * 2=valgt kort, på lista
 * 3=blir utfordret
 * 4=fullført
 * 5=tidsavbrudd starter
 * 
 * Ulike winner:
 * 0=uavgjort
 * 1=starter vant
 * 2=utfordrer vant
 */

class page_poker extends pages_player
{
	const MIN_BET = 10000;
	protected $is_starter;
	
	public function __construct(player $up)
	{
		parent::__construct($up);
		
		$this->load_page();
	}
	
	protected function load_page()
	{
		ess::$b->page->add_title("Poker");
		
		// vise historikk?
		if (isset($_GET['stats']))
		{
			$this->show_stats();
			ess::$b->page->load();
		}
		
		// sjekk om vi holder på å utfordre
		$this->check_challenge();
		
		// har vi startet en runde?
		$this->check_start();
		
		// skal vi starte en utfordring?
		$this->start();
		
		// skal vi utfordre?
		$this->challenge();
		
		// vis skjema for å opprette utfordring
		$this->show_create();
		
		// vis liste over pokerrundene som er aktive
		$this->show_active();
		
		// vis siste statistikk
		$this->show_last_stats();
		
		ess::$b->page->load();
	}
	
	/**
	 * Holder vi på å utfordre?
	 */
	protected function check_challenge()
	{
		// holder vi på å utfordre?
		$result = ess::$b->db->query("
			SELECT poker_id, poker_starter_up_id, poker_challenger_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_state, poker_prize
			FROM poker
			WHERE poker_challenger_up_id = ".$this->up->id." AND poker_state IN (3,4) AND poker_challenger_seen = 0
			LIMIT 1");
		
		$row = mysql_fetch_assoc($result);
		if (!$row) return;
		
		$round = new poker_round_interactive($row);
		$round->handle_check_challenge();
	}
	
	/**
	 * Har vi startet en pokerrunde?
	 */
	protected function check_start()
	{
		$result = ess::$b->db->query("
			SELECT poker_id, poker_starter_up_id, poker_challenger_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_state, poker_prize
			FROM poker
			WHERE poker_starter_up_id = ".$this->up->id." AND poker_state <= 4 AND poker_starter_seen = 0
			LIMIT 1");
		
		$row = mysql_fetch_assoc($result);
		if (!$row) return;
		
		$round = new poker_round_interactive($row);
		$round->handle_check_start();
		
		if ($round->data['poker_state'] != poker_round::STATE_COMPLETE) $this->is_starter = true;
	}
	
	/**
	 * Starte en utfordring
	 */
	protected function start()
	{
		if (!isset($_POST['amount']) || $this->is_starter) return;
		$amount = game::intval($_POST['amount']);
		
		// for lite beløp?
		if (bccomp($amount, self::MIN_BET) == -1)
		{
			ess::$b->page->add_message("Du må satse minimum ".game::format_cash(self::MIN_BET).".", "error");
			redirect::handle();
		}
		
		// ikke råd?
		if (bccomp($amount, $this->up->data['up_cash']) == 1)
		{
			ess::$b->page->add_message("Du har ikke så mye penger på hånda.", "error");
			redirect::handle();
		}
		
		// nonstatuser?
		if (bccomp($amount, 10000) == 1 && MAIN_SERVER && (access::is_nostat() && $this->up->data['up_u_id'] != 1))
		{
			ess::$b->page->add_message("Nostat kan ikke spille poker med beløp over 10 000 kr.", "error");
			redirect::handle();
		}
		
		login::data_set("poker_siste_innsats", $amount);
		
		// trekk fra pengene fra spilleren
		ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $amount WHERE up_id = ".$this->up->id." AND up_cash >= $amount");
		if (ess::$b->db->affected_rows() == 0)
		{
			ess::$b->page->add_message("Du har ikke så mye penger på hånda.", "error");
			redirect::handle();
		}
		
		// start pokerspill
		$poker = new CardsPoker();
		$poker->new_cards(5);
		
		ess::$b->db->query("INSERT INTO poker SET poker_starter_up_id = ".$this->up->id.", poker_starter_cards = ".ess::$b->db->quote(implode(",", $poker->get_cards())).", poker_time_start = ".time().", poker_cash = $amount, poker_state = 1");
		
		redirect::handle();
	}
	
	/**
	 * Utfordre en spiller
	 */
	protected function challenge()
	{
		if (!isset($_POST['utfordre']) || !isset($_POST['id'])) return;
		
		// finn utfordringen
		$id = (int) $_POST['id'];
		$result = ess::$b->db->query("
			SELECT poker_id, poker_starter_up_id, poker_starter_cards, poker_time_start, poker_cash
			FROM poker
			WHERE poker_id = $id AND poker_state = 2 AND poker_starter_up_id != ".$this->up->id);
		
		$row = mysql_fetch_assoc($result);
		if (!$row)
		{
			ess::$b->page->add_message("Fant ikke utfordringen. Noen kan ha kommet før deg!", "error");
			redirect::handle();
		}
		
		// ikke råd?
		if (bccomp($row['poker_cash'], $this->up->data['up_cash']) == 1)
		{
			ess::$b->page->add_message("Du har ikke så mye penger på hånda.", "error");
			redirect::handle();
		}
		
		// nostatuser?
		if (bccomp($row['poker_cash'], 10000) == 1 && MAIN_SERVER && (access::is_nostat() && $this->up->data['up_u_id'] != 1))
		{
			ess::$b->page->add_message("Nostat kan ikke spille poker med beløp over 10 000 kr.", "error");
			redirect::handle();
		}
		
		// sett opp pokerhånd
		$poker1 = new CardsPoker(explode(",", $row['poker_starter_cards']));
		$poker2 = new CardsPoker();
		$poker2->remove_cards($poker1->get_cards());
		$poker2->new_cards(5);
		
		// oppdater utfordringen
		ess::$b->db->query("UPDATE poker SET poker_state = 3, poker_challenger_up_id = ".$this->up->id.", poker_challenger_cards = '".implode(",", $poker2->get_cards())."', poker_time_challenge = '".time()."' WHERE poker_id = {$row['poker_id']} AND poker_state = 2");
		
		if (ess::$b->db->affected_rows() == 0)
		{
			ess::$b->page->add_message("Fant ikke utfordringen. Noen kan ha kommet før deg!", "error");
			redirect::handle();
		}
		
		// trekk fra pengene fra brukeren
		ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - {$row['poker_cash']} WHERE up_id = ".$this->up->id." AND up_cash >= {$row['poker_cash']}");
		
		// ble ikke brukeren oppdatert?
		if (ess::$b->db->affected_rows() == 0)
		{
			ess::$b->page->add_message("Du har ikke så mye penger på hånda.", "error");
			
			// fjern challenge
			ess::$b->db->query("UPDATE poker SET poker_state = 2, poker_challenger_up_id = 0, poker_challenger_cards = '', poker_time_challenge = 0 WHERE poker_id = {$row['poker_id']}");
			
			redirect::handle();
		}
		
		poker_round::update_cache();
		redirect::handle();
	}
	
	protected function show_create()
	{
		if ($this->is_starter) return;
		
		$innsats = login::data_get("poker_siste_innsats", 10000);
		if (bccomp($innsats, $this->up->data['up_cash']) == 1) $innsats = $this->up->data['up_cash'];
		
		ess::$b->page->add_js_domready('
	var player_cash = '.js_encode(game::format_cash($this->up->data['up_cash'])).';
	var elm = $("poker_amount_set");
	var elm_t = $("poker_amount");
	
	elm
		.appendText(" (")
		.grab(new Element("a", {"text":"velg alt"}).addEvent("click", function()
		{
			elm_t.set("value", player_cash);
		}))
		.appendText(")");');
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Nytt pokerspill<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="'.ess::$s['relative_path'].'/node/28">Hjelp</a></p>
	<div class="bg1">
		<form action="" method="post">
			<dl class="dd_right">
				<dt id="poker_amount_set">Beløp</dt>
				<dd><input type="text" id="poker_amount" name="amount" value="'.game::format_cash($innsats).'" class="styled w120" /> '.show_sbutton("Start").'</dd>
			</dl>
		</form>
	</div>
</div>';
	}
	
	protected function show_active()
	{
		echo '
<div class="bg1_c '.(access::has("admin") ? 'xmedium' : 'small').'">
	<h1 class="bg1">Utfordre en spiller<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="'.ess::$s['relative_path'].'/node/28">Hjelp</a></p>
	<div class="bg1">';
		
		// hent alle utfordringene som utfordrer..
		$result = ess::$b->db->query("SELECT poker_id, poker_starter_up_id, poker_time_start, poker_starter_cards, poker_cash FROM poker WHERE poker_state = 2 ORDER BY poker_cash");
		$num = mysql_num_rows($result);
		
		// javascript for oppdatering
		ess::$b->page->add_js_domready('
	stuff = {
		interval: 3000, // hvert 3. sek
		active: true,
		init: function()
		{
			this.table = $("poker_players_table");
			this.players_true = $("poker_players_true");
			this.players_false = $("poker_players_false");
			this.tbody = $("poker_players");
			this.info = new Element("p", {"class": "c"}).inject(this.players_true, "before");
			this.start_timer();
		},
		start_timer: function()
		{
			this.info.set("text", "Oppdatering aktivert.");
			
			// sett events for aktiv/inaktiv
			var self = this;
			document.addEvents({
				"active": function()
				{
					if (self.active) return;
					
					// start timer og oppdater
					self.active = true;
					self.startUpdateTimer();
					self.update();
				},
				"idle": function()
				{
					// avbryt mulig xhr og stopp timer
					self.xhr.cancel();
					$clear(self.timer);
					self.active = false;
				}
			});
			
			// sett opp ajax objektet
			this.xhr = new Request({
				"url": relative_path + "/ajax/poker_challengers",
				"autoCancel": true
			});
			
			this.xhr.addEvents({
				"success": function(text, xml)
				{
					// vis sist oppdatert tidspunkt
					var d = new Date($time()+window.servertime_offset);
					self.info.set("html", "Oppdatert " + str_pad(d.getHours()) + ":" + str_pad(d.getMinutes()) + ":" + str_pad(d.getSeconds()));
					
					// hent ut spillerene
					var players = JSON.decode(text);
					
					if (players.length > 0)
					{
						self.players_true.setStyle("display", "block");
						self.players_false.setStyle("display", "none");
						
						// sjekk om vi har merket noen
						selected = false;
						$$("#poker_players_true input[type=radio]").each(function(elm)
						{
							if (elm.checked)
							{
								selected = elm.value;
							}
						});
						
						// fjern alle nåværende spillere
						self.tbody.empty();
						
						// legg til nye spillere
						i = 0;
						players.each(function(row)
						{
							tr = new Element("tr");
							if (!row.self) tr.addClass("box_handle");
							if (i == 1) { tr.addClass("color"); }
							i++;
							if (i == 2) { i = 0; }
							new Element("td", {"html": row.player}).inject(tr);
							new Element("td", {"class": "r", "html": row.cash}).inject(tr);
							new Element("td", {"class": "c", "html": row.reltime}).inject(tr);'.(access::has("admin") ? '
							new Element("td", {"html": row.cards}).inject(tr);' : '').'
							tr.inject(self.tbody);
						});
						self.tbody.check_html();
						
						// merke en spiller?
						if (selected)
						{
							box = $$("#poker_players_true input[type=radio]")[0];
							name = box.get("rel") || box.get("name").replace(new RegExp("^(.*)\\[.+?\\]$"), "$1[]");
							boxHandleElms[name].each(function(obj)
							{
								if (obj.box.value == selected)
								{
									obj.checked = true;
									obj.check();
								}
							});
						}
					}
					else
					{
						self.players_true.setStyle("display", "none");
						self.players_false.setStyle("display", "block");
					}
					
					window.fireEvent("update_pa", players.length);
				},
				"failure": function(xhr)
				{
					// logget inn men feil?
					if (xhr.responseText != "ERROR:SESSION-EXPIRE" && xhr.responseText != "ERROR:WRONG-SESSION-ID")
					{
						self.info.set("html", "<b>Oppdatering feilet:</b> "+xhr.responseText+"<br />Henter ikke lenger oppdateringer.");
						self.info.check_html();
					}
					
					// ikke logget inn
					else
					{
						self.info.set("text", "Oppdateringer avbrutt - ikke logget inn.");
					}
					
					// stopp timer
					$clear(self.timer);
				}
			});
			
			// start oppdatering
			this.startUpdateTimer();
		},
		
		/** Start oppdateringstimer */
		startUpdateTimer: function()
		{
			this.timer = this.update.bind(this).periodical(this.interval);
		},
		
		/** Hent oppdateringer */
		update: function()
		{
			// sett info
			this.info.set("text", "Oppdaterer..");
			
			// oppdater
			this.xhr.send();
		}
	}
	stuff.init();');
		
		echo '
		<div id="poker_players_true"'.($num == 0 ? ' style="display: none"' : '').'>
			<form action="" method="post">
				<table class="table center tablemb">
					<thead>
						<tr>
							<th>Spiller</th>
							<th>Beløp</th>
							<th>Tid</th>'.(access::has("admin") ? '
							<th>Kort</th>' : '').'
						</tr>
					</thead>
					<tbody id="poker_players">';
		
		$i = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$attr = new attr("class");
			if ($row['poker_starter_up_id'] != $this->up->id) $attr->add("box_handle");
			if (++$i % 2 == 0) $attr->add("color");
			
			$cards = new CardsPoker(explode(",", $row['poker_starter_cards']));
			$cardstext = $cards->solve_text($cards->solve());
			
			echo '
						<tr'.$attr->build().'>
							<td>'.($row['poker_starter_up_id'] != $this->up->id ? '<input type="radio" name="id" value="'.$row['poker_id'].'" />' : '').'<user id="'.$row['poker_starter_up_id'].'" /></td>
							<td class="r">'.game::format_cash($row['poker_cash']).'</td>
							<td class="c">'.poker_round::get_time_text($row['poker_time_start']).'</td>'.(access::has("admin") ? '
							<td>'.$cardstext.'</td>' : '').'
						</tr>';
		}
		
		echo '
					</tbody>
				</table>
				<p class="c">'.show_sbutton("Utfordre", 'name="utfordre"').'</p>
			</div>
			<div id="poker_players_false"'.($num > 0 ? ' style="display: none"' : '').'>
				<p class="c">Ingen utfordringer.</p>
				<p class="c"><a href="poker" class="button">Oppdater</a></p>
			</div>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Vis siste statistikk
	 */
	protected function show_last_stats()
	{
		// hent siste resultater
		
		// hent utfordringene (delt i starter og challenger)
		$poker = array();
		
		$result = ess::$b->db->query("SELECT poker_id, poker_starter_up_id, poker_challenger_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_winner, poker_prize FROM poker WHERE poker_starter_up_id = ".$this->up->id." AND poker_state = 4 ORDER BY poker_time_challenge DESC LIMIT 5");
		while ($row = mysql_fetch_assoc($result))
		{
			$poker[$row['poker_time_challenge'].$row['poker_id']] = $row;
		}
		
		$result = ess::$b->db->query("SELECT poker_id, poker_starter_up_id, poker_challenger_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_winner, poker_prize FROM poker WHERE poker_challenger_up_id = ".$this->up->id." AND poker_state = 4 ORDER BY poker_time_challenge DESC LIMIT 5");
		while ($row = mysql_fetch_assoc($result))
		{
			$poker[$row['poker_time_challenge'].$row['poker_id']] = $row;
		}
		
		krsort($poker);
		
		// ingen utfordringer?
		if (count($poker) == 0)
		{
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Siste resultater<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c">Du har ikke spilt poker i det siste.</p>
	</div>
</div>';
			
			return;
		}
		
		ess::$b->page->add_css(".poker_results tbody td { text-align: center }");
		
		echo '
<div class="bg1_c xlarge">
	<h1 class="bg1">Siste resultater<span class="left"></span><span class="right"></span></h1>
	<p class="h_right">
		<a href="min_side?u&amp;stats&amp;a=div">Statistikk &raquo;</a>
		<a href="poker?stats">Vis full historie &raquo;</a>
	</p>
	<div class="bg1">
		<table class="table center game poker_results tablem" width="100%">
			<thead>
				<tr>
					<th>Motstander</th>
					<th>Tid</th>
					<th>Din/motstanderens kombinasjon</th>
					<th>Beløp</th>
					<th>Resultat</th>
				</tr>
			</thead>
			<tbody>';
		
		$i = 0;
		foreach ($poker as $row)
		{
			if ($i == 5) break;
			
			$poker1 = new CardsPoker(explode(",", $row['poker_starter_cards']));
			$solve1 = $poker1->solve();
			$text1 = $poker1->solve_text($solve1);
			
			$poker2 = new CardsPoker(explode(",", $row['poker_challenger_cards']));
			$solve2 = $poker2->solve();
			$text2 = $poker2->solve_text($solve2);
			
			$is_starter = $row['poker_starter_up_id'] == $this->up->id;
			$won = ($row['poker_winner'] == 1 && $is_starter) || ($row['poker_winner'] == 2 && !$is_starter);
			
			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.($is_starter ? $row['poker_challenger_up_id'] : $row['poker_starter_up_id']).'" /></td>
					<td><span style="color: #888">'.ess::$b->date->get($row['poker_time_challenge'])->format().'</color><br />'.game::timespan($row['poker_time_challenge'], game::TIME_ABS).'</td>'.($is_starter ? '
					<td>'.$text1.'<br /><span style="color: #888">'.$text2.'</span></td>' : '
					<td>'.$text2.'<br /><span style="color: #888">'.$text1.'</span></td>').'
					<td class="r">'.game::format_cash($row['poker_cash']).'</td>'.($row['poker_winner'] == 0 ? '
					<td>Uavgjort</td>' : ($won ? '
					<td style="color: #F9E600"><b>Vant!</b><br />'.game::format_cash($row['poker_prize']).'</td>' : '
					<td style="color: #FF0000"><b>Tapte..</b></td>')).'
				</tr>';
		}
		
		echo '
			</tbody>
		</table>
	</div>
</div>';
	}
	
	/**
	 * Vis pokerhistorikk
	 */
	protected function show_stats()
	{
		ess::$b->page->add_title("Historikk");
		
		ess::$b->page->add_css('
.poker_results tbody td { text-align: center }
.poker_results .vi { color: #F9E600 }
.poker_results .ta { color: #FF0000 }');
		
		// sideinformasjon - startede runder
		$pagei_s = new pagei(pagei::ACTIVE_GET, "side_s", pagei::PER_PAGE, 15);
		$result_s = $pagei_s->query("
			SELECT poker_id, poker_challenger_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_state, poker_prize
			FROM poker
			WHERE poker_starter_up_id = {$this->up->id} AND poker_state = 4
			ORDER BY poker_time_challenge DESC");
		
		// sideinformasjon - utfordrede runder
		$pagei_u = new pagei(pagei::ACTIVE_GET, "side_u", pagei::PER_PAGE, 15);
		$result_u = $pagei_u->query("
			SELECT poker_id, poker_starter_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_state, poker_prize
			FROM poker
			WHERE poker_challenger_up_id = {$this->up->id} AND poker_state = 4
			ORDER BY poker_time_challenge DESC");
		
		// antall totalt
		$total = $pagei_s->total + $pagei_u->total;
		
		putlog("PROFILVIS", "%c7%bVIS-POKER-HISTORIE:%b%c %u".login::$user->player->data['up_name']."%u viste pokerhistorien ({$_SERVER['REQUEST_URI']})");
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Din pokerhistorie<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="poker">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p class="c">Pokerstatistikken viser kun pokerrunder ca. 90 dager tilbake i tid.</p>';
		
		// ingen runder?
		if ($total == 0)
		{
			echo '
		<p class="c">Ingen pokerrunder er registrert på deg.</p>';
		}
		
		else
		{
			echo '
		<p class="c">Totalt finnes det <b>'.game::format_number($total).'</b> pokerrunde'.($total == 1 ? '' : 'r').'.</p>';
		}
		
		echo '
	</div>
</div>';
		
		if ($total > 0)
		{
			// startede pokerrunder
			echo '
	<div class="bg1_c xlarge">
		<h2 class="bg1" id="startede">Mine startede pokerrunder<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1">';
			
			if ($pagei_s->total == 0)
			{
				echo '
			<p class="c">Du har ikke aldri startet noen pokerrunder.</p>';
			}
			
			else
			{
				echo '
			<p class="c">Du har totalt startet <b>'.game::format_number($pagei_s->total).'</b> pokerrunde'.($pagei_s->total == 1 ? '' : 'r').'.</p>
			<table class="table center poker_results" width="100%">
				<thead>
					<tr>
						<th>Motstander</th>
						<th>Tid</th>
						<th>Din/motstanderens kombinasjon</th>
						<th>Beløp</th>
						<th>Resultat</th>
					</tr>
				</thead>
				<tbody>';
				
				$i = 0;
				while ($row = mysql_fetch_assoc($result_s))
				{
					echo $this->stats_row($row, true, ++$i);
				}
				
				echo '
				</tbody>
			</table>
			<p class="c">'.$pagei_s->pagenumbers(game::address("poker", $_GET, array("side_s"))."#startede", game::address("poker", $_GET, array("side_s"), array("side_s" => "_pageid_"))."#startede").'</p>';
			}
			
			echo '
		</div>
	</div>';
			
			
			// startede pokerrunder
			echo '
	<div class="bg1_c xlarge">
		<h2 class="bg1" id="utfordrede">Mine utfordrede pokerrunder<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1">';
			
			if ($pagei_u->total == 0)
			{
				echo '
			<p class="c">Du har ikke aldri utfordret noen pokerrunder.</p>';
			}
			
			else
			{
				echo '
			<p class="c">Du har totalt utfordret <b>'.game::format_number($pagei_u->total).'</b> pokerrunde'.($pagei_u->total == 1 ? '' : 'r').'.</p>
			<table class="table center poker_results" width="100%">
				<thead>
					<tr>
						<th>Motstander</th>
						<th>Tid</th>
						<th>Din/motstanderens kombinasjon</th>
						<th>Beløp</th>
						<th>Resultat</th>
					</tr>
				</thead>
				<tbody>';
				
				$i = 0;
				while ($row = mysql_fetch_assoc($result_u))
				{
					echo $this->stats_row($row, false, ++$i);
				}
				
				echo '
				</tbody>
			</table>
			<p class="c">'.$pagei_u->pagenumbers(game::address("poker", $_GET, array("side_u"))."#utfordrede", game::address("poker", $_GET, array("side_u"), array("side_u" => "_pageid_"))."#utfordrede").'</p>';
			}
			
			echo '
		</div>
	</div>';
		}
	}
	
	protected function stats_row($row, $starter, $i)
	{
		$poker1 = new CardsPoker(explode(",", $row['poker_starter_cards']));
		$solve1 = $poker1->solve();
		$text1 = $poker1->solve_text($solve1);
		
		$poker2 = new CardsPoker(explode(",", $row['poker_challenger_cards']));
		$solve2 = $poker2->solve();
		$text2 = $poker2->solve_text($solve2);
		
		$winner = CardsPoker::compare($solve1, $solve2);
		
		return '
					<tr'.($i % 2 == 0 ? ' class="color"' : '').'>
						<td><user id="'.$row['poker_'.($starter ? 'challenger' : 'starter').'_up_id'].'" /></td>
						<td><span class="dark">'.ess::$b->date->get($row['poker_time_challenge'])->format().'</span><br />'.game::timespan($row['poker_time_challenge'], game::TIME_ABS).'</td>
						<td>'.($starter ? $text1 : $text2).'<br /><span style="color: #888">'.($starter ? $text2 : $text1).'</span></td>
						<td class="r">'.game::format_cash($row['poker_cash']).'</td>'.($winner[0] == 0 ? '
						<td>Uavgjort</td>' : (($winner[0] == 1 && $starter) || ($winner[0] == 2 && !$starter) ? '
						<td class="vi"><b>Vant!</b><br />'.game::format_cash($row['poker_prize']).'</td>' : '
						<td class="ta"><b>Tapte..</b></td>')).'
					</tr>';
	}
}