<?php

class poker_round
{
	public static $kort_url;
	
	const MAX_CHALLENGE_TIME = 60;
	
	public $id;
	public $data;
	
	/**
	 * @var CardsPoker
	 */
	protected $poker1;
	protected $solve1;
	protected $text1;
	
	/**
	 * @var CardsPoker
	 */
	protected $poker2;
	protected $solve2;
	protected $text2;
	
	const STATE_BEGIN = 1;
	const STATE_FREE = 2;
	const STATE_CHALLENGE = 3;
	const STATE_COMPLETE = 4;
	const STATE_TIMEOUT = 5;
	
	const SHOW_STARTER = 0;
	const SHOW_CHALLENGER = 1;
	
	/**
	 * Construct
	 * @param array $data
	 */
	public function __construct($data)
	{
		$this->id = $data['poker_id'];
		$this->data = $data;
		
		$this->check();
	}
	
	protected function check()
	{
		$this->poker1 = new CardsPoker(explode(",", $this->data['poker_starter_cards']));
		$this->solve1 = $this->poker1->solve();
		$this->text1 = $this->poker1->solve_text($this->solve1);
		
		$cards = $this->data['poker_challenger_cards'] ? explode(",", $this->data['poker_challenger_cards']) : null;
		$this->poker2 = new CardsPoker($cards);
		$this->poker2->remove_cards($this->poker1->get_cards());
		if ($cards)
		{
			$this->solve2 = $this->poker2->solve();
			$this->text2 = $this->poker2->solve_text($this->solve2);
		}
		
		// gått ut på tid?
		if ($this->data['poker_state'] == self::STATE_CHALLENGE && $this->data['poker_time_challenge'] <= time() - self::MAX_CHALLENGE_TIME)
		{
			$this->auto_play();
		}
	}
	
	protected function auto_play()
	{
		// velg ut nye kort
		$this->poker2->play();
		$this->solve2 = $this->poker2->solve();
		$this->text2 = $this->poker2->solve_text($this->solve2);
		
		$res = $this->challenge_save(null, true);
		if (!is_array($res)) return;
		
		// send melding til utfordrer
		$res[3]->add_log("poker", "{$res[0][0]}:{$res[2]->id}:{$res[1]}", $this->data['poker_cash']);
	}
	
	protected function get_winner()
	{
		return CardsPoker::compare($this->solve1, $this->solve2);
	}
	
	protected function mark_seen_starter()
	{
		ess::$b->db->query("UPDATE poker SET poker_starter_seen = 1 WHERE poker_id = $this->id");
	}
	
	protected function mark_seen_challenger()
	{
		ess::$b->db->query("UPDATE poker SET poker_challenger_seen = 1 WHERE poker_id = $this->id");
	}
	
	protected function starter_replace_cards(array $replace)
	{
		if (count($replace) == 0) return;
		
		// hent nye kort
		$this->poker1->new_cards($replace);
		$this->solve1 = $this->poker1->solve();
		$this->text1 = $this->poker1->solve_text($this->solve1);
	}
	
	protected function challenger_replace_cards(array $replace)
	{
		if (count($replace) == 0) return;
		
		// hent nye kort
		$this->poker2->new_cards($replace);
		$this->solve2 = $this->poker2->solve();
		$this->text2 = $this->poker2->solve_text($this->solve2);
	}
	
	protected function start_save($dont_save = null)
	{
		// oppdater
		$time = time();
		$update = $dont_save ? '' : ", poker_starter_result = {$this->solve1[0]}, poker_state = 2, poker_time_start = $time";
		
		$cards = implode(",", $this->poker1->get_cards());
		ess::$b->db->query("
			UPDATE poker
			SET poker_starter_cards = ".ess::$b->db->quote($cards)."$update
			WHERE poker_id = $this->id AND poker_state = 1");
		
		if (ess::$b->db->affected_rows() == 0) return false;
		
		$this->data['poker_starter_cards'] = $cards;
		
		if (!$dont_save)
		{
			$this->data['poker_starter_result'] = $this->solve1[0];
			$this->data['poker_state'] = 2;
			$this->data['poker_time'] = $time;
		}
		
		self::update_cache();
		return true;
	}
	
	protected function challenge_save($dont_save = null, $auto = null)
	{
		// avgjør vinner
		$winner = CardsPoker::compare($this->solve1, $this->solve2);
		
		// avgjør gevinst
		$prize = $winner[0] == 0 ? $this->data['poker_cash'] : bcmul($this->data['poker_cash'], 2);
		
		// oppdater
		$update = $dont_save ? '' : ", poker_challenger_result = {$this->solve2[0]}, poker_state = 4, poker_prize = $prize, poker_winner = {$winner[0]}";
		if ($auto && !$dont_save) $update .= ", poker_auto = 1";
		
		$cards = implode(",", $this->poker2->get_cards());
		ess::$b->db->query("
			UPDATE poker
			SET poker_challenger_cards = ".ess::$b->db->quote($cards)."$update
			WHERE poker_id = $this->id AND poker_state = 3");
		
		if (ess::$b->db->affected_rows() == 0) return false;
		if ($dont_save) return true;
		
		$this->data['poker_challenger_cards'] = $cards;
		$this->data['poker_challenger_result'] = $this->solve2[0];
		$this->data['poker_state'] = 4;
		$this->data['poker_prize'] = $prize;
		$this->data['poker_winner'] = $winner[0];
		
		$up1 = player::get($this->data['poker_starter_up_id']);
		$up2 = player::get($this->data['poker_challenger_up_id']);
		
		switch ($winner[0])
		{
			// starter vant
			case 1:
				ess::$b->db->query("
					UPDATE users_players
					SET up_cash = up_cash + $prize
					WHERE up_id = {$this->data['poker_starter_up_id']}");
				
				$up1->data['up_cash'] = bcadd($up1->data['up_cash'], $prize);
				
				putlog("SPAMLOG", "%bPOKER%b: %u{$up2->data['up_name']}%u tapte (".strip_tags($this->text2)."). %u{$up1->data['up_name']}%u vant %u".game::format_cash($prize)."%u (".strip_tags($this->text1).")");
			break;
			
			// utfordrer vant
			case 2:
				ess::$b->db->query("
					UPDATE users_players
					SET up_cash = up_cash + $prize
					WHERE up_id = {$this->data['poker_challenger_up_id']}");
				
				$up2->data['up_cash'] = bcadd($up2->data['up_cash'], $prize);
				
				putlog("SPAMLOG", "%bPOKER%b: %u{$up2->data['up_name']}%u vant %u".game::format_cash($prize)."%u (".strip_tags($this->text2)."). %u{$up1->data['up_name']}%u tapte (".strip_tags($this->text1).")");
			break;
			
			// uavgjort
			default:
				ess::$b->db->query("
					UPDATE users_players
					SET up_cash = up_cash + $prize
					WHERE up_id IN ({$this->data['poker_starter_up_id']}, {$this->data['poker_challenger_up_id']})");
				
				$up1->data['up_cash'] = bcadd($up1->data['up_cash'], $prize);
				$up2->data['up_cash'] = bcadd($up2->data['up_cash'], $prize);
				
				putlog("SPAMLOG", "%bPOKER%b: %u{$up2->data['up_name']}%u (".strip_tags($this->text2).") uavgjort mot %u{$up1->data['up_name']}%u (".strip_tags($this->text1).")  - Begge fikk %u".game::format_cash($prize)."%u");
		}
		
		// trigger
		$up1->trigger("poker_result",
			array(
				"won" => $winner[0] == 0 ? 0 : ($winner[0] == 1 ? 1 : -1),
				"cash" => $this->data['poker_cash'],
				"prize" => $prize,
				"opponent" => $up2));
		$up2->trigger("poker_result",
			array(
				"won" => $winner[0] == 0 ? 0 : ($winner[0] == 1 ? -1 : 1),
				"cash" => $this->data['poker_cash'],
				"prize" => $prize,
				"opponent" => $up1));
		
		return array($winner, $prize, $up1, $up2);
	}
	
	/**
	 * Trekk tilbake pokerrunde
	 */
	protected function pullback()
	{
		// oppdater pokkerunden
		ess::$b->db->query("
			UPDATE poker
			SET poker_state = ".self::STATE_TIMEOUT."
			WHERE poker_id = $this->id AND poker_state IN (".self::STATE_BEGIN.",".self::STATE_FREE.")");
		
		if (ess::$b->db->affected_rows() > 0)
		{
			poker_round::update_cache();
			
			// gi tilbake pengene
			$up = player::get_loaded($this->data['poker_starter_up_id']);
			if ($up)
			{
				$up->data['up_cash'] = bcadd($up->data['up_cash'], $this->data['poker_cash']);
			}
			
			ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + {$this->data['poker_cash']} WHERE up_id = {$this->data['poker_starter_up_id']}");
			
			$this->data['poker_state'] = self::STATE_TIMEOUT;
			return true;
		}
		
		return false;
	}
	
	/**
	 * Oppdater cache for menyen
	 */
	public static function update_cache()
	{
		cache::store("poker_active", mysql_result(ess::$b->db->query("SELECT COUNT(*) FROM poker WHERE poker_state = 2"), 0));
	}
	
	/**
	 * Spiller dør/blir deaktivert
	 */
	public static function player_dies(player $up)
	{
		// hent evt. pokerrunder vi har startet og trekk tilbake
		$result = ess::$b->db->query("
			SELECT poker_id, poker_starter_up_id, poker_challenger_up_id, poker_starter_cards, poker_challenger_cards, poker_time_start, poker_time_challenge, poker_cash, poker_state, poker_prize
			FROM poker
			WHERE poker_starter_up_id = ".$up->id." AND poker_state IN (1,2)");
		
		while ($row = mysql_fetch_assoc($result))
		{
			// forsøk å trekk tilbake
			$round = new poker_round($row);
			$round->pullback();
		}
	}
	
	/**
	 * Hente tekst for hvor lenge en runde har ligget ute
	 */
	public static function get_time_text($time_start)
	{
		$time = time() - $time_start;
		
		// ny hvis under 1 time
		if ($time < 3600) return 'Ny';
		
		// moderat hvis under 12 timer
		if ($time < 43200) return 'Moderat';
		
		// gammel hvis 12 timer eller mer
		return 'Gammel';
	}
}

class poker_round_interactive extends poker_round
{
	/** Tid det må gå før en runde kan trekkes tilbake */
	const PULLBACK_TIME = 180;
	protected $can_pullback;
	
	public function handle_check_challenge()
	{
		// vise resultat?
		if ($this->data['poker_state'] == self::STATE_COMPLETE)
		{
			$this->show(self::SHOW_CHALLENGER);
			$this->mark_seen_challenger();
			
			return;
		}
		
		// be om nye kort?
		if (isset($_POST['state3']))
		{
			// kontroller ID
			if (postval("state3") != $this->id) redirect::handle();
			
			// beholde noen kort?
			$replace = array(0,1,2,3,4);
			if (isset($_POST['kort']) && is_array($_POST['kort']))
			{
				// gå gjennom hver og fjern fra den vi skal beholde
				for ($i = 0; $i < 5; $i++)
				{
					if (isset($_POST['kort'][$i])) unset($replace[$i]);
				}
			}
			
			$this->challenger_replace_cards($replace);
			$res = $this->challenge_save(access::has("admin") && isset($_POST['renew']));
			
			if ($res !== true) redirect::handle();
		}
		
		$this->show(self::SHOW_CHALLENGER);
		ess::$b->page->load();
	}
	
	public function handle_check_start()
	{
		// vise resultat?
		if ($this->data['poker_state'] == self::STATE_COMPLETE)
		{
			$this->mark_seen_starter();
		}
		
		elseif ($this->data['poker_state'] == self::STATE_FREE)
		{
			// kan trekkes tilbake?
			$this->can_pullback = access::has("admin") || $this->data['poker_time_start'] + self::PULLBACK_TIME <= time();
			
			// ønsker vi å trekke tilbake?
			if ($this->can_pullback && isset($_POST['pullback']))
			{
				// kontroller ID
				if (postval("pullback") != $this->id) redirect::handle();
				
				// trekk tilbake
				if ($this->pullback())
				{
					ess::$b->page->add_message("Du trakk tilbake pokerunden din og fikk tilbake ".game::format_cash($this->data['poker_cash']).".");
				}
				
				redirect::handle();
			}
		}
		
		elseif ($this->data['poker_state'] == self::STATE_BEGIN)
		{
			// har vi valgt ut kort?
			if (isset($_POST['state1']))
			{
				// kontroller ID
				if (postval("state1") != $this->id) redirect::handle();
				
				// beholde noen kort?
				$replace = array(0,1,2,3,4);
				if (isset($_POST['kort']) && is_array($_POST['kort']))
				{
					// gå gjennom hver og fjern fra den vi skal beholde
					for ($i = 0; $i < 5; $i++)
					{
						if (isset($_POST['kort'][$i])) unset($replace[$i]);
					}
				}
				
				$this->starter_replace_cards($replace);
				$dont_save = access::has("admin") && isset($_POST['renew']);
				$res = $this->start_save($dont_save);
				
				if (!$dont_save || !$res) redirect::handle();
			}
		}
		
		$this->show(self::SHOW_STARTER);
	}
	
	protected function show($as_who = null)
	{
		$complete = $this->data['poker_state'] == self::STATE_COMPLETE;
		if ($complete) $winner = $this->get_winner();
		
		$is_starter = $as_who == self::SHOW_STARTER;
		$has_challenger = $this->data['poker_state'] >= self::STATE_CHALLENGE;
		
		$new = $this->data['poker_state'] == self::STATE_BEGIN;
		$challenge = !$is_starter && $this->data['poker_state'] == self::STATE_CHALLENGE;
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">'.($is_starter ? ($new ? 'Nytt pokerspill' : 'Ditt pokerspill') : 'Din utfordring').'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';
		
		if ($new)
		{
			echo '
		<p>Du har nå startet et nytt pokerspill og fortsetter ved å velge de kortene du vil <u>beholde</u>. Når du har trykket fortsett kommer du opp på listen for utfordrere og andre kan spille mot deg.</p>
		<form action="" method="post">
		<input type="hidden" name="state1" value="'.$this->id.'" />';
		}
		
		elseif ($challenge)
		{
			$time_left = $this->data['poker_time_challenge'] + self::MAX_CHALLENGE_TIME - time();
			
			echo '
		<p>Du må fullføre din utfordring innen <b>'.game::counter($time_left, true).'</b>. Dersom du ikke fullfører vil spillet automatisk velge kort for deg.</p>
		<form action="" method="post">
		<input type="hidden" name="state3" value="'.$this->id.'" />';
		}
		
		echo '
		<dl class="dd_right center" style="width: 60%">
			<dt>Tid siden start</dt>
			<dd>'.game::timespan($this->data['poker_time_start'], game::TIME_ABS).'</dd>'.($has_challenger ? '
			<dt>Tid siden utfordring</dt>
			<dd>'.game::timespan($this->data['poker_time_challenge'], game::TIME_ABS).'</dd>' : '').'
			<dt>Innsats</dt>
			<dd>'.game::format_cash($this->data['poker_cash']).'</dd>'.($has_challenger ? '
			<dt>Utfordrer</dt>
			<dd><user id="'.$this->data['poker_'.($is_starter ? 'challenger' : 'starter').'_up_id'].'" /></dd>' : '
			<dt>Utfordrer</dt>
			<dd>Ingen enda</dd>').'
		</dl>';
		
		if ($complete || ($has_challenger && access::has("admin")))
		{
			echo '
		<div class="poker_cards_section">
			<p><b>Motstanderens kort:</b> '.($is_starter ? $this->text2 : $this->text1).'</p>
			<p>';
			
			if ($is_starter)
			{
				$this->list_cards($this->poker2, $this->solve2);
			} else {
				$this->list_cards($this->poker1, $this->solve1);
			}
			
			echo '
			</p>
		</div>';
		}
		
		echo '
		<div class="poker_cards_section">
			<p><b>Dine kort:</b> '.($is_starter ? $this->text1 : $this->text2).'</p>'.($challenge ? '
			<p>Marker de kortene du ønsker å <u>beholde</u>.</p>' : '').'
			<p>';
		
		if ($new || $challenge)
		{
			ess::$b->page->add_js('sm_scripts.poker_parse();');
			
			if ($new)
			{
				$this->list_cards_selectable($this->poker1, $this->solve1);
			}
			else
			{
				$this->list_cards_selectable($this->poker2, $this->solve2);
			}
		}
		elseif ($is_starter)
		{
			$this->list_cards($this->poker1, $this->solve1);
		}
		else
		{
			$this->list_cards($this->poker2, $this->solve2);
		}
		
		echo '
			</p>
		</div>';
		
		if ($complete)
		{
			if (($winner[0] == 1 && !$is_starter) || ($winner[0] == 2 && $is_starter))
			{
				if ($winner[1])
				{
					echo '
		<p class="poker_res_lost">Dere fikk samme kombinasjon, men motstanderen din hadde høyere highcard. Du tapte runden...</p>';
				}
				
				else
				{
					echo '
		<p class="poker_res_lost">Motstanderen fikk bedre kombinasjon enn deg. Du tapte runden...</p>';
				}
			}
			
			elseif ($winner[0] != 0)
			{
				if ($winner[1])
				{
					echo '
		<p class="poker_res_won">Dere fikk samme kombinasjon, men du hadde høyere highcard og vant '.game::format_cash($this->data['poker_prize']).'!</p>';
				}
				
				else
				{
					echo '
		<p class="poker_res_won">Du fikk bedre kombinasjon enn motstanderen og vant '.game::format_cash($this->data['poker_prize']).'!</p>';
				}
			}
			
			else
			{
				echo '
		<p class="poker_res_eq">Runden ble uavgjort.</p>';
			}
		}
		
		elseif ($new)
		{
			echo (access::has("admin") ? '
			<p class="c"><input type="checkbox" name="renew" id="renew"'.(isset($_POST['renew']) ? ' checked="checked"' : '').'><label for="renew"> Ikke avslutt runden</label></p>' : '').'
			<p class="c">'.show_sbutton("Velg kort og åpne runden").'</p>
			</form>';
		}
		
		elseif ($challenge)
		{
			echo (access::has("admin") ? '
			<p class="c"><input type="checkbox" name="renew" id="renew"'.(isset($_POST['renew']) ? ' checked="checked"' : '').'><label for="renew"> Ikke avslutt runden</label></p>' : '').'
			<p class="c">'.show_sbutton("Velg kort og avslutt").'</p>
			</form>';
		}
		
		elseif ($this->can_pullback)
		{
			echo '
			<form action="" method="post">
				<input type="hidden" name="pullback" value="'.$this->id.'" />
				<p class="c">Pokerrunden har ligget ute i mer enn '.game::timespan(self::PULLBACK_TIME, game::TIME_FULL).' og du kan trekke den tilbake for å få igjen pengene og evt. legge deg ut på nytt.</p>
				<p class="c">'.show_sbutton("Trekk tilbake pokerrunden").'</p>
			</form>';
		}
		
		elseif ($this->data['poker_state'] == self::STATE_FREE)
		{
			echo '
			<p class="c">Hvis ingen utfordrer deg i løpet av '.game::timespan(self::PULLBACK_TIME, game::TIME_FULL).' vil du kunne trekke tilbake runden.</p>';
		}
		
		elseif ($is_starter && $this->data['poker_state'] == self::STATE_CHALLENGE)
		{
			echo '
			<p class="c">Venter på at <user id="'.$this->data['poker_challenger_up_id'].'" /> skal velge kort...</p>';
		}
		
		echo '
	</div>
</div>';
	}
	
	protected function list_cards(CardsPoker $poker, array $solve)
	{
		foreach ($poker->active as $key => $card)
		{
			echo sprintf('
			<img src="%s" alt="%s" title="%s" class="spillekort%s" />',
				htmlspecialchars(sprintf(self::$kort_url,
					$card->num+1, $card->group['name']
				)),
				ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
				ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
				(isset($solve[2][$key]) ? ' result' : ' noresult')
			);
		}
	}
	
	protected function list_cards_selectable(CardsPoker $poker, array $solve)
	{
		foreach ($poker->active as $key => $card)
		{
			echo sprintf('
				<input type="checkbox" name="kort[%d]" value="1" id="kort%d"%s /><label for="kort%d"><img src="%s" alt="%s" title="%s" class="spillekort" /></label>',
				$key, $key,
				(isset($solve[2][$key]) && access::has("admin") ? ' checked="checked"' : ''),
				$key,
				htmlspecialchars(sprintf(self::$kort_url,
					$card->num+1, $card->group['name']
				)),
				ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
				ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign()
			);
		}
	}
}

poker_round::$kort_url = STATIC_LINK . "/kort/60x90/%d/%s.png";