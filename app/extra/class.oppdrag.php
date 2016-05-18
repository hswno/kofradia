<?php

/**
 * Oppdrag
 * 
 * Premier:
 * 		cash: Penger
 * 			felt 1: antall kr
 * 		rank_points: Rank poeng
 * 			felt 1: rank poeng
 * 		bullets: Kuler, så fremt man har våpen med kapasitet
 * 			felt 1: antall kuler
 * 
 * Triggere:
 *  ALLE TRIGGERE:
 * 		prize (container): Premie -- kan settes for både unlock og active
 * 			[type_premie, info1, info2][..]
 * 
 * 
 * 	single_poker:
 * 		unlock: Ikke tilgjengelig
 * 		aktiv: Nå et bestemt beløp med penger innen en bestemt tid ved å spille poker mot en datamaskin
 * 			chips (int): Antall chips man skal nå
 * 			chips_start (int): Hvor mange chips man starter med
 * 			time_limit (int): Hvor lang tid man har
 * 			STATUS chips (int): Hvor mange chips man har nå
 * 			STATUS cards (text): Hvilke kort brukeren har
 * 			STATUS cards_pc (text): Hvilke kort pcen har
 * 			STATUS cards_used (text): Kort som allerede er brukt og som ikke skal være i kortstokken
 * 			STATUS bet (int): Hvor mye penger som satses nå eller forrige gang
 * 			STATUS finish (int): Vise resultat?
 * 
 * 	rank_points:
 * 		unlock: Få et bestemt antall rankpoeng i løpet av gitt antall minutter
 * 			points (int): Antall rankpoeng man skal skaffe
 * 			time_limit (int): Hvor mange minutter bak i tid skal telles
 * 			STATUS previous (array [time (int), points (int)]): Rankpoengene som blir skaffet (per gang)
 * 		aktiv: Oppnå et bestemt antall rankpoeng i løpet av gitt antall minutter
 * 			points (int): Antall rankpoeng man skal skaffe når man begynner på oppdraget
 * 			time_limit (int): Hvor lang tid man har
 * 			STATUS target_points (int): Antall rankpoeng totalt man skal oppnå
 * 
 * 	kriminalitet_different:
 * 		unlock: Klare bestemt antall forskjellige kriminaliteter på rad
 * 			count (int): Antall forskjellige som må utføres
 * 			STATUS previous (array [time (int)]): Tidligere kriminaliter som har vært vellykkede
 * 		aktiv: Samme som unlock, men med tidsgrense
 * 			time_limit (int): Hvor lang tid man har
 * 
 * 	poker_unique_people:
 * 		unlock: Vinne mot et bestemt antall forskjellige brukere i poker på rad (første teller)
 * 			time_limit (int) optional: Hvor lang tid man har
 * 			user_count (int): Antall forskjellige brukere man må vinne mot
 * 			STATUS previous (array [time, won, cash, prize, opponent]): Forsøkene
 * 			STATUS previous_s (int): Status siste forsøk
 * 		aktiv: Samme som unlock
 * 
 * 	wanted_level:
 * 		unlock: Oppnå gitt wanted nivå
 * 			wanted_level (int): Målet for wanted nivå (500 = 50 %)
 * 
 * 	fengsel_breakout:
 * 		aktiv:
 * 			user_count (int): Antall spillere man skal vinne mot
 * 			time_limit (int) optional: Hvor lang tid man har
 * 			STATUS user_count (int): Antall oppnådd
 * 
 */

class oppdrag
{
	/** Det aktive oppdraget (array) */
	public $active = false;
	
	/** Oppdragene (array) */
	public $oppdrag = false;
	
	/** For å lagre params som er tilknyttet oppdragene */
	public $params = false;
	
	/** Er alle oppdragene lastet inn (eller evt. kun aktivt) */
	public $oppdrag_loaded = false;
	
	/** Noen nye oppdrag? */
	public $new = array();
	
	/** Triggere */
	public $triggers = array();
	
	/** Triggere (oppdrag id => trigger) */
	public $triggers_id = array();
	
	/** Er det brukeren som viser siden som tilhører disse oppdragene? */
	public $user_active = NULL;
	
	/**
	 * Spillerobjektet
	 * @var player
	 */
	public $up;
	
	/** Antall oppdrag som skal være tilgjengelige for brukeren */
	const AVAILIABLE = 1;
	
	/** Hvor lenge et aktivt oppdrag standard kan vare (når det ikke er bestemt) */
	const DEFAULT_TIME_LIMIT_ACTIVE = 600; // 10 minutter
	
	/** Hvor lenge man skal være i fengsel når oppdraget mislykkes */
	const TIME_FENGSEL = 900; // 15 minutter
	
	/**
	 * Construct
	 * @param player $up
	 * @param int $up_id hvis man ikke har spillerobjekt fra før
	 */
	public function __construct(player $up = null, $up_id = null, &$ref = null)
	{
		// lagre referanse?
		if ($ref === true) $ref = $this;
		
		// har vi spillerobjekt?
		if ($up)
		{
			$this->up = $up;
		}
		
		// sett opp spillerobjekt
		else
		{
			$this->up = player::get($up_id);
			if (!$this->up) throw new HSException("Fant ikke gyldig spiller som det var referert til. Oppdragsystemet kan ikke fortsette.");
			$this->up->oppdrag = $this;
		}
		
		$this->user_active = login::is_active_user($this->up);
		
		// sjekk om vi er på et aktivt oppdrag
		if ($this->up->params->get("oppdrag"))
		{
			// hent ut detaljer om oppdraget
			$oppdrag = new params($this->up->params->get("oppdrag"));
			
			// legg til som oppdrag
			$this->oppdrag[$oppdrag->get("o_id")] = $oppdrag->params;
			$this->load_params();
			
			// sett som aktivt
			$this->active_set($oppdrag->get("o_id"));
		}
		
		// sett opp triggere for oppdragene som er tilgjengelige nå
		$this->load_triggers();
	}
	
	/**
	 * Sjekk for en mulig trigger (sjekker før den utfører kalkulasjoner)
	 * 
	 * @return boolean
	 */
	public function is_trigger($name)
	{
		// finn ut triggeren finnes for øyeblikket
		return isset($this->triggers[$name]);
	}
	
	/**
	 * Hent oppdrag status
	 * 
	 * @param oppdrag id int $o_id
	 * @return string
	 */
	public function status($o_id, $header = null)
	{
		// har vi noen trigger for dette oppdraget vi kan hente status for?
		if (isset($this->triggers_id[$o_id]))
		{
			$trigger = $this->triggers_id[$o_id];
			
			/*
			 * $trigger:
			 * 	o_id => int oppdrag id
			 * 	trigger => params trigger info
			 * 	type => string type (unlock || active)
			 * 	status => params status info
			 */
			
			// sjekk for tidsgrense
			#$time_start = false;
			if ($trigger['type'] == "active")
			{
				#$time_start = $this->active['uo_active_time'];
				$time_limit = (int) $trigger['trigger']->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
				$time_expire = 0;
			}
			else
			{
				$time_limit = (int) $trigger['trigger']->get("time_limit", false);
				$time_expire = time()-$time_limit;
			}
			
			switch ($trigger['trigger']->get("name"))
			{
				case "rank_points":
					// oppdrag aktivt? (oppnå poeng på gitt tid)
					if ($trigger['type'] == "active")
					{
						$target = $trigger['status']->get("target_points");
						$need = $target - $this->up->data['up_points'];
						
						// finn progress
						$points_total = $trigger['trigger']->get("points");
						$points_start = $target - $points_total;
						$progress = ($this->up->data['up_points'] - $points_start) / $points_total * 100;
						
						// progress for tid
						$progress_time_status = time() - $this->oppdrag[$o_id]['uo_active_time'];
						$progress_time = $progress_time_status / $time_limit * 100;
						
						// javascript for progress for tiden
						ess::$b->page->add_js_domready('
	new CountdownProgressbarTime($("progress_time"), '.$progress_time_status.', '.$time_limit.');');
						
						return '
			<p>Du må oppnå '.game::format_num($target).' poeng og trenger <b>'.game::format_num($need).'</b> flere poeng for å fullføre oppdraget.</p>
			<div class="progressbar">
				<div class="progress" style="width: '.round($progress < 0 ? 0 : $progress).'%"><p>'.game::format_number($progress, 1).' % (rank)'.($progress < 0 ? ' - du har færre poeng enn da du begynte på oppdraget' : '').'</p></div>
			</div>
			<div class="progressbar" style="margin-top: 3px">
				<div class="progress" style="width: '.round($progress_time).'%" id="progress_time"><p>'.game::timespan($time_limit-$progress_time_status, game::TIME_FULL).' gjenstår</p></div>
			</div>';
					}
					
					// unlock
					elseif ($trigger['type'] == "unlock")
					{
						// hvor mange poeng må vi oppnå?
						$target = $trigger['status']->get("points");
						
						// [] => array(time, points)
						$previous = new container($trigger['status']->get("previous"));
						
						// finn ut hvor mange poeng vi har fått i løpet av tidsgrensen
						$points_last = 0;
						$deleted = false;
						foreach ($previous as $key => $value)
						{
							// for lang tid siden?
							if ($value[0] < $time_expire)
							{
								unset($previous->items[$key]);
								$deleted = true;
								continue;
							}
							
							$points_last += $value[1];
						}
						
						// oppdatere status?
						if ($deleted)
						{
							$trigger['status']->update("previous", $previous->build());
							$this->update_status($trigger['o_id'], $trigger['status']);
						}
						
						return '<p>Du må for øyeblikket nå '.game::format_num($target).' poeng.</p>';
					}
					
				break;
					
				case "kriminalitet_different":
					// sett opp forrige forsøk
					$previous = new container($trigger['status']->get("previous"));
					
					/*
					 * 0 = time, 1 = krim id
					 */
					
					// hvor mange forskjellige må vi oppnå?
					$different = $trigger['trigger']->get("count", 5);
					
					// send status tekst
					if (count($previous->items) == 0)
					{
						// klarte ikke siste?
						if ($trigger['status']->get("previous_s", 1) == 0)
						{
							return '
			<p>Du klarte ikke siste kriminalitet.</p>';
						}
						
						return '
			<p>Du har ikke forsøkt å utføre noen kriminalitet enda.</p>';
					}
					
					return '
			<p>Du har utført '.count($previous->items).' av '.$different.' forskjellige kriminaliteter som har vært vellykkede.</p>';
				
				case "poker_unique_people":
					// sett opp tidligere utfordringer
					$previous = new container($trigger['status']->get("previous"));
					
					/*
					 * 0 = time, 1 = won, 2 = cash, 3 = prize, 4 = opponent
					 */
					
					// sjekk hvor mange vi har vunnet på rad
					$won = array();
					$new = array();
					foreach ($previous->items as $value)
					{
						// gått ut på tid?
						if ($value[0] < $time_expire) { continue; }
						
						$new[] = $value;
						
						// vunnet
						if ($value[1] == 1)
						{
							$won[$value[4]] = true;
						}
						
						// tapte
						else
						{
							// allerede vunnet?
							if (isset($won[$value[4]]))
							{
								// har ikke noe å si
								continue;
							}
							
							// må vinne 5 nye
							// alle andre oppføringene før og inkludert denne kan fjernes
							$new = array();
							$won = array();
						}
					}
					
					// oppdatere?
					if (count($previous->items) != count($new))
					{
						$previous->items = $new;
						$trigger['status']->update("previous", $previous->build());
						$this->update_status($trigger['o_id'], $trigger['status']);
					}
					
					$user_count = $trigger['trigger']->get("user_count", 10);
					
					// send status tekst
					if (count($won) == 0)
					{
						// tapte siste?
						if ($trigger['status']->get("previous_s", 1) == 0)
						{
							return '
			<p>Du tapte siste runde og må vinne mot '.$user_count.' forskjellige spillere.</p>';
						}
						
						return '
			<p>Du har ikke spilt mot noen enda og må vinne mot '.$user_count.' forskjellige spillere.</p>';
					}
					
					return '
			<p>Du har vunnet '.count($won).' av '.$user_count.' runder, og må derfor vinne mot ytterligere '.fwords("%s annen spiller", "%s andre spillere", $user_count-count($won)).'.</p>';
				
				case "wanted_level":
					return $this->status_wanted_level($o_id, $trigger, $time_limit, $time_expire);
				
				case "fengsel_breakout":
					return $this->status_fengsel_breakout($o_id, $trigger, $time_limit, $time_expire);
			}
		}
		
		return '';
	}
	
	/**
	 * Status: wanted_level
	 */
	protected function status_wanted_level($o_id, $trigger, $time_limit, $time_expire)
	{
		return '<p>Ditt wanted nivå er nå på '.game::format_number($this->up->data['up_wanted_level']/10, 1).' %. Du må nå '.game::format_number($trigger['trigger']->get("wanted_level", 500)/10, 1).' %.</p>';
	}
	
	/**
	 * Status: fengsel_breakout
	 */
	protected function status_fengsel_breakout($o_id, $trigger, $time_limit, $time_expire)
	{
		// hvor mange vi har klart på rad til nå
		$count = $trigger['status']->get("user_count", 0);
		
		// hvor man vi må klare
		$count_target = $trigger['trigger']->get("user_count", 3);
		
		// progress for tid
		$progress_time_status = time() - $this->oppdrag[$o_id]['uo_active_time'];
		$progress_time = $progress_time_status / $time_limit * 100;
		
		// javascript for progress for tiden
		ess::$b->page->add_js_domready('
		new CountdownProgressbarTime($("progress_time"), '.$progress_time_status.', '.$time_limit.');');
		
		return '<p>Du har oppnådd '.$count.' av '.$count_target.' utbrytninger på rad.</p>
			<div class="progressbar">
				<div class="progress" style="width: '.round($progress_time).'%" id="progress_time"><p>'.game::timespan($time_limit-$progress_time_status, game::TIME_FULL).' gjenstår</p></div>
			</div>';
	}
	
	
	
	/**
	 * Behandle trigger
	 *
	 * @param trigger name string $name
	 * @param array $data containing neccessary information about the trigger
	 */
	public function handle_trigger($name, $data)
	{
		// er denne triggeren satt?
		if ($this->is_trigger($name))
		{
			foreach ($this->triggers[$name] as $trigger)
			{
				/*
				 * $trigger:
				 * 	o_id => int oppdrag id
				 * 	trigger => params trigger info
				 * 	type => string type (unlock || active)
				 * 	status => params status info
				 */
				
				// sjekk for tidsgrense
				if ($trigger['type'] == "active")
				{
					$time_limit = $trigger['trigger']->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
					$time_expire = 0;
				}
				else
				{
					$time_limit = $trigger['trigger']->get("time_limit", false);
					$time_expire = time()-$time_limit;
				}
				
				switch ($name)
				{
					case "rank_points":
						/* $data: array(
						 *   source => string,
						 *   points => int,
						 *   points_rel => int,
						 *   points_after => int,
						 *   points_after_rel => int,
						 *   rank => int,
						 *   pos => int
						 * )
						 * player objektet vil være oppdatert på forhånd
						 */
						
						// aktiv? (oppnå poeng på gitt tid)
						if ($trigger['type'] == "active")
						{
							// hvor mye skal vi oppnå?
							$target = $trigger['status']->get("target_points", 0);
							
							// fikk vi rank fra lotto? (lotto teller ikke)
							if ($data['source'] == "lotto")
							{
								// legg til melding i hendelser
								$this->up->add_log("oppdrag", "Du vant i lotto og din rank har nå økt. Derfor har også målet i ditt nåværende oppdrag blitt høyere fordi lotto ikke er en del av oppdraget.");
								
								// legg til ranken man fikk til målet
								$target = $target + $data['points'];
								$trigger['status']->update("target_points", $target);
								
								// lagre status
								$this->update_status($trigger['o_id'], $trigger['status']);
							}
							
							// fikk vi rank fra angrep? (angrep teller ikke)
							elseif ($data['source'] == "attack")
							{
								// legg til melding i hendelser
								if ($data['points_rel'] > 0)
								{
									$this->up->add_log("oppdrag", "Du angrep en spiller og din rank har nå økt. Derfor har også målet i ditt nåværende oppdrag blitt høyere fordi angrep ikke er en del av oppdraget.");
								}
								
								// legg til ranken man fikk til målet
								$target = $target + $data['points'];
								$trigger['status']->update("target_points", $target);
								
								// lagre status
								$this->update_status($trigger['o_id'], $trigger['status']);
							}
							
							// kompensere mellom absolutt og relativ endring?
							elseif ($data['points'] != $data['points_rel'])
							{
								// beregn ny poenggrense
								$target = $target + $data['points'] - $data['points_rel'];
								$trigger['status']->update("target_points", $target);
								
								// lagre status
								$this->update_status($trigger['o_id'], $trigger['status']);
							}
							
							// har vi nådd målet?
							if ($data['points_after'] >= $target)
							{
								$this->success($trigger['o_id']);
							}
						}
						
						// unlock -- lagre hvor mange poeng brukeren har fått
						else
						{
							// lotto og angrep teller ikke med
							if ($data['source'] == "lotto" || $data['source'] == "attack") continue;
							
							// hvor mange poeng må vi oppnå?
							$target = $trigger['status']->get("points");
							
							// [] => array(time, points)
							$previous = new container($trigger['status']->get("previous"));
							
							// legg til denne
							$previous->items[] = array(time(), $data['points_rel']);
							
							// finn ut hvor mange poeng vi har fått i løpet av tidsgrensen
							$points_last = 0;
							foreach ($previous as $key => $value)
							{
								// for lang tid siden?
								if ($value[0] < $time_expire)
								{
									unset($previous->items[$key]);
									continue;
								}
								
								$points_last += $value[1];
							}
							
							// har vi nådd målet?
							if ($points_last >= $target)
							{
								$this->unlock($trigger['o_id']);
							}
							
							else
							{
								// lagre status
								$trigger['status']->update("previous", $previous->build());
								$this->update_status($trigger['o_id'], $trigger['status']);
							}
						}
						
					break;
						
					case "kriminalitet_different":
						// $data: array(option => data, success => boolean)
						// sett opp forrige forsøk
						$previous = new container($trigger['status']->get("previous"));
						
						/*
						 * 0 = time, 1 = krim id
						 */
						
						$k_id = $data['option']['id'];
						
						// klarte ikke denne?
						if (!$data['success'])
						{
							$previous->items = array();
						}
						
						else
						{
							// finnes denne fra før?
							foreach ($previous->items as $key => $value)
							{
								if ($value[1] == $k_id)
								{
									unset($previous->items[$key]);
									break;
								}
							}
							
							// legg til denne
							$previous->items[] = array(time(), $k_id);
							
							// hvor mange forskjellige må vi oppnå?
							$different = $trigger['trigger']->get("count", 5);
							
							// har vi mange nok?
							if (count($previous->items) >= $different)
							{
								if ($trigger['type'] == "unlock") $this->unlock($trigger['o_id']);
								else $this->success($trigger['o_id']);
								
								continue;
							}
						}
						
						$trigger['status']->update("previous", $previous->build());
						$trigger['status']->update("previous_s", $data['success'] ? 1 : 0);
						$this->update_status($trigger['o_id'], $trigger['status']);
						
					break;
					
					case "poker_unique_people":
						// uavgjort?
						if ($data['won'] == 0) continue;
						
						// sett opp tidligere utfordringer
						$previous = new container($trigger['status']->get("previous"));
						
						/*
						 * 0 = time, 1 = won, 2 = cash, 3 = prize, 4 = opponent
						 */
						
						// legg til denne enheten
						$previous->items[] = array(time(), $data['won'] == 1 ? 1 : 0, $data['cash'], $data['prize'], $data['opponent']->id);
						
						// sjekk hvor mange vi har vunnet på rad
						$won = array();
						$new = array();
						foreach ($previous->items as $key => $value)
						{
							// gått ut på tid?
							if ($value[0] < $time_expire) { continue; }
							
							$new[] = $value;
							
							// vunnet
							if ($value[1] == 1)
							{
								$won[$value[4]] = true;
							}
							
							// tapte
							else
							{
								// allerede vunnet?
								if (isset($won[$value[4]]))
								{
									// har ikke noe å si
									continue;
								}
								
								// må vinne 5 nye
								// alle andre oppføringene før og inkludert denne kan fjernes
								$new = array();
								$won = array();
							}
						}
						
						$previous->items = $new;
						
						// vant vi mot nok antall motstandere
						if (count($won) >= $trigger['trigger']->get("user_count", 10))
						{
							// ferdig utført
							if ($trigger['type'] == "unlock") $this->unlock($trigger['o_id']);
							else $this->complete($trigger['o_id']);
						}
						
						else
						{
							$trigger['status']->update("previous", $previous->build());
							$trigger['status']->update("previous_s", $data['won'] == 1 ? 1 : 0);
							$this->update_status($trigger['o_id'], $trigger['status']);
						}
						
					break;
					
					case "wanted_level":
						$this->handle_trigger_wanted_level($name, $data, $trigger, $time_limit, $time_expire);
					break;
					
					case "fengsel_breakout":
						$this->handle_trigger_fengsel_breakout($name, $data, $trigger, $time_limit, $time_expire);
					break;
				}
			}
		}
	}
	
	/**
	 * Trigger: Oppnå wanted nivå
	 */
	protected function handle_trigger_wanted_level($name, $data, $trigger, $time_limit, $time_expire)
	{
		// oppnådd wanted nivå?
		if ($this->up->data['up_wanted_level'] >= $trigger['trigger']->get("wanted_level", 500))
		{
			if ($trigger['type'] == "unlock")
				$this->unlock($trigger['o_id']);
			else
				$this->success($trigger['o_id']);
		}
	}
	
	/**
	 * Trigger: Bryte ut spillere fra fengsel
	 */
	protected function handle_trigger_fengsel_breakout($name, $data, $trigger, $time_limit, $time_expire)
	{
		/* $data: se pages/fengsel.php */
		
		// hvor mange vi har klart på rad til nå
		$count = $trigger['status']->get("user_count", 0);
		
		// klarte ikke?
		if (!$data['success'])
		{
			// nullstill
			if ($count > 0)
			{
				$trigger['status']->update("user_count", 0);
				$this->update_status($trigger['o_id'], $trigger['status']);
			}
		}
		
		// klarte det
		else
		{
			$count++;
			
			// har vi vunnet mot mange nok?
			if ($count >= $trigger['trigger']->get("user_count", 3))
			{
				if ($trigger['type'] == "unlock")
					$this->unlock($trigger['o_id']);
				else
					$this->success($trigger['o_id']);
			}
			
			else
			{
				$trigger['status']->update("user_count", $count);
				$this->update_status($trigger['o_id'], $trigger['status']);
			}
		}
	}
	
	/**
	 * Lagre status
	 * 
	 * @param integer $o_id
	 * @param object params $params
	 */
	public function update_status($o_id, $params)
	{
		$o_id = (int) $o_id;
		$data = $params->build();
		
		// oppdater lokalt
		if (isset($this->oppdrag[$o_id]))
		{
			$this->oppdrag[$o_id]['uo_params'] = $data;
			if (isset($this->params[$o_id]))
			{
				$this->params[$o_id]['uo_params'] = $params;
			}
		}
		
		// oppdater databasen
		\Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_params = ".\Kofradia\DB::quote($data)." WHERE uo_up_id = {$this->up->id} AND uo_o_id = $o_id");
		
		// oppdater triggers
		$this->link_triggers();
	}
	
	/**
	 * Sett opp triggers hos brukeren
	 */
	public function link_triggers()
	{
		if ($this->active)
		{
			// hent triggere kun for det aktive oppdraget
			$type = "active";
			$oppdrag = array($this->active);
		}
		else
		{
			if (!$this->oppdrag_loaded)
			{
				$this->user_load_all();
				return;
			}
			
			// kontroller at det ikke er satt noen params
			if ($this->up->params->exists("oppdrag"))
			{
				// fjern fra params
				$this->up->params->lock();
				$this->up->params->remove("oppdrag_id");
				$this->up->params->remove("oppdrag", true);
			}
			
			$type = "unlock";
			$oppdrag = $this->oppdrag;
		}
		
		// hvilke triggere skal være tilgjengelige nå?
		$triggers = new container();
		
		// gå gjennom oppdragene og sett opp triggerinformasjonen
		foreach ($oppdrag as $row)
		{
			// ingen triggere?
			if ($row['uo_locked'] == 0 && $row['uo_active'] == 0)
			{
				continue;
			}
			
			$o_params = $type == "active" ? $row['o_params'] : $row['o_unlock_params'];
			$o_params_object = $type == "active" ? $this->params[$row['o_id']]['o_params'] : $this->params[$row['o_id']]['o_unlock_params'];
			$uo_params = $row['uo_params'];
			
			// mangler navn?
			$name = $o_params_object->get("name");
			if (empty($name)) continue;
			
			$trigger = array(
				$name, // name
				$row['o_id'], // o_id
				$o_params, // oppdrag params
				$type, // type trigger
				$uo_params // status
			);
			
			$triggers->items[] = $trigger;
		}
		
		// endringer?
		$data = $triggers->build();
		if ($this->up->params->get("oppdrag_triggers") !== $data)
		{
			// aktiv?
			if ($this->active)
			{
				$params = new params();
				$params->params = $this->active;
				$this->up->params->lock();
				$this->up->params->update("oppdrag", $params->build());
				$this->up->params->update("oppdrag_id", $this->active['o_id'], true);
			}
			
			$this->up->params->update("oppdrag_triggers", $data, true);
		}
		$this->load_triggers($triggers);
	}
	
	/**
	 * Les triggere fra brukeren og lagre
	 * 
	 * @param container $container for å bruke tidligere initialisert params objekt
	 */
	private function load_triggers($container = NULL)
	{
		// sett opp triggere for oppdragene som er tilgjengelige nå
		if (!$this->up->params->exists("oppdrag_triggers"))
		{
			// triggers mangler hos brukeren -- sett opp
			$this->link_triggers();
			return;
		}
		
		/*
		 * 
		 * oppdrag_triggers:
		 * 	[]
		 * 		0 => name --> oppdrag triggers NAME var
		 * 		1 => oppdrag id --> o_id
		 * 		2 => oppdrag triggers --> o_unlock_params OR o_params
		 * 		3 => trigger type --> unlock OR active
		 * 		4 => trigger status --> uo_params
		 * 
		 */
		
		// hvis vi har fått params servert ikke hent på nytt
		if ($container)
		{
			$triggers = &$container;
		}
		else
		{
			$triggers = new container($this->up->params->get("oppdrag_triggers"));
		}
		
		$this->triggers = array();
		$this->triggers_id = array();
		
		foreach ($triggers->items as $row)
		{
			// har vi allerede satt opp params?
			if (isset($this->params[$row[1]]))
			{
				$trigger = $row[3] == "unlock" ? $this->params[$row[1]]['o_unlock_params'] : $this->params[$row[1]]['o_params'];
				$status = $this->params[$row[1]]['uo_params'];
			}
			else
			{
				$trigger = new params($row[2]);
				$status = new params($row[4]);
			}
			
			$this->triggers[$row[0]][$row[1]] = array(
				"o_id" => $row[1],
				"trigger" => $trigger,
				"type" => $row[3],
				"status" => $status
			);
			$this->triggers_id[$row[1]] = &$this->triggers[$row[0]][$row[1]]; 
		}
	}
	
	/**
	 * Sett oppdrag som aktivt
	 * 
	 * @param integer $o_id
	 * @return boolean active
	 */
	public function active_set($o_id)
	{
		// allerede satt som aktivt?
		if ($this->active && $this->active['o_id'] == $o_id) return false;
		
		// finnes ikke oppdraget? (må være hentet først for at vi kan fortsette)
		if (!isset($this->oppdrag[$o_id]))
		{
			return false;
		}
		$oppdrag = &$this->oppdrag[$o_id];
		
		// ikke aktivt allerede?
		if ($oppdrag['uo_active'] == 0)
		{
			// sjekk om noen andre oppdrag er aktive
			$result = \Kofradia\DB::get()->query("SELECT uo_o_id, uo_active_time FROM users_oppdrag WHERE uo_up_id = {$this->up->id} AND uo_active != 0 LIMIT 1");
			if ($result->rowCount() > 0)
			{
				$uo = $result->fetch();
				
				// et annet oppdrag?
				if ($uo['uo_o_id'] != $oppdrag['o_id'])
				{
					// sett riktig aktivt oppdrag
					if (!isset($this->oppdrag[$uo['uo_o_id']]) && !$this->oppdrag_loaded)
					{
						$this->user_load_all();
					}
					$this->active_set($uo['uo_o_id']);
					
					// må sette alle andre oppdrag som innaktive før vi kan begynne på et nytt oppdrag
					return false;
				}
				
				$oppdrag['uo_active'] = 1;
				$oppdrag['uo_active_time'] = $uo['uo_active_time'];
			}
			
			// sett oppdraget aktivt
			else
			{
				$oppdrag['uo_active'] = 1;
				$oppdrag['uo_active_time'] = time();
				
				\Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_active = 1, uo_active_time = {$oppdrag['uo_active_time']} WHERE uo_up_id = {$this->up->id} AND uo_o_id = {$oppdrag['o_id']}");
			}
		}
		
		// sett aktivt oppdrag
		if ($this->up->params->get("oppdrag_id") != $oppdrag['o_id'])
		{
			$params = new params();
			$params->params = $oppdrag;
			$this->up->params->lock();
			$this->up->params->update("oppdrag", $params->build());
			$this->up->params->update("oppdrag_id", $oppdrag['o_id'], true);
		}
		
		$this->active = &$oppdrag;
		
		// sett nye triggere
		$this->link_triggers();
		
		// sjekk om oppdraget er over tiden sin
		$params = $this->params[$oppdrag['o_id']]['o_params'];
		$time_start = $oppdrag['uo_active_time'];
		$time_limit = $params->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
		if ($time_start+$time_limit < time())
		{
			// kontroller trigger
			if (isset($this->triggers_id[$o_id]))
			{
				$trigger = $this->triggers_id[$o_id];
				
				switch ($params->get("name"))
				{
					case "single_poker":
						// nådde vi beløpet?
						if ($trigger['status']->get("chips") >= $trigger['trigger']->get("chips"))
						{
							$this->success($o_id, 'Du klarte å spille deg opp til '.game::format_number($trigger['status']->get("chips")).' chips i løpet av '.game::timespan($time_limit, game::TIME_FULL).', noe som var mer enn '.game::format_number($trigger['trigger']->get("chips")).' chips. Oppdraget &laquo;$name&raquo; ble vellykket!');
						}
						else
						{
							$this->failed($o_id, 'Du spilte deg opp til '.game::format_number($trigger['status']->get("chips")).' chips i løpet av '.game::timespan($time_limit, game::TIME_FULL).'. Det var mindre enn '.game::format_cash($trigger['trigger']->get("chips")).' chips. Oppdraget &laquo;$name&raquo; ble mislykket.');
						}
					break;
				}
			}
			
			// hvis oppdraget fortsatt er aktivt, sett det som feilet pga. tid
			if (isset($this->oppdrag[$o_id]) && $this->oppdrag[$o_id]['uo_active'] != 0)
			{
				$this->failed($o_id, 'Du brukte for lang tid på oppdraget &laquo;$name&raquo; og mislykket.');
			}
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Sett oppdrag som vellykket
	 *
	 * @param integer $o_id
	 */
	public function success($o_id, $text = 'Oppdraget &laquo;$name&raquo; ble utført vellykket!')
	{
		$o_id = (int) $o_id;
		if (!isset($this->oppdrag[$o_id])) return false;
		
		// kontroller at dette oppdraget er aktivt
		$oppdrag = &$this->oppdrag[$o_id];
		$params = $this->params[$o_id];
		if ($oppdrag['uo_active'] == 0)
		{
			return false;
		}
		
		// oppdater oppdraget
		$time = time();
		$a = \Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_repeats = uo_repeats + 1, uo_success = uo_success + 1, uo_last_time = $time, uo_last_state = 1, uo_availiable = 0, uo_locked = 1, uo_active = 0, uo_active_time = 0, uo_params = NULL WHERE uo_up_id = {$this->up->id} AND uo_o_id = $o_id AND uo_active != 0");
		
		// ble ikke oppdatert?
		if ($a == 0)
		{
			// hent frisk data og avbryt
			$this->user_load_all();
			
			return false;
		}
		
		$oppdrag['uo_repeats']++;
		$oppdrag['uo_success']++;
		$oppdrag['uo_last_time_prev'] = $oppdrag['uo_last_time'];
		$oppdrag['uo_last_time'] = time();
		$oppdrag['uo_available'] = 0;
		$oppdrag['uo_locked'] = 1;
		$oppdrag['uo_active'] = 0;
		$oppdrag['uo_active_time'] = 0;
		$oppdrag['uo_params'] = null;
		
		// fjern oppdraget fra lokalt (skal ikke være på listen)
		unset($this->active);
		$this->active = false;
		
		unset($this->oppdrag[$o_id]);
		unset($this->params[$o_id]);
		
		$text = str_replace('$name', $oppdrag['o_title'], $text);
		
		// send teksten til brukeren
		if ($this->user_active)
		{
			ess::$b->page->add_message($text);
		}
		
		// sjekk for premie
		$prize = new container($params['o_params']->get("prize"));
		$prizes_msg = '';
		if (count($prize->items) > 0)
		{
			$prizes = array();
			foreach ($prize->items as $item)
			{
				switch ($item[0])
				{
					case "cash":
						// gi brukeren pengene
						$cash = (int) $item[1];
						$this->up->update_money($cash, true, true);
						
						$prizes[] = game::format_cash($cash);
					break;
					
					case "rank_points":
						$points = (int) $item[1];
						$prizes[] = game::format_num($points)." poeng";
						
						// legg til ranken
						player::increase_rank_static($points, $this->up->id, true);
					break;
					
					// kuler
					case "bullets":
						$bullets = (int) $item[1];
						
						// har vi plass til noen kuler?
						$kap = $this->up->weapon ? $this->up->weapon->data['bullets'] : 0;
						if ($this->up->weapon)
						{
							$free = $kap - $this->up->data['up_weapon_bullets'] - $this->up->data['up_weapon_bullets_auksjon'];
							$bullets = max(0, min($free, $bullets));
							
							if ($bullets > 0)
							{
								// gi kuler
								\Kofradia\DB::get()->exec("UPDATE users_players SET up_weapon_bullets = up_weapon_bullets + $bullets WHERE up_id = {$this->up->id}");
								$this->up->data['up_weapon_bullets'] += $bullets;
								
								$prizes[] = fwords("%d kule", "%d kuler", $bullets);
							}
						}
					break;
				}
			}
			
			// mottok premie?
			if (count($prizes) > 0 && $this->user_active)
			{
				$last = '';
				if (count($prizes) > 1)
				{
					$last = " og ".array_pop($prizes);
				}
				$prizes_msg = " Du mottok ".implode(", ", $prizes).$last.".";
				ess::$b->page->add_message("Du mottok ".implode(", ", $prizes).$last." for å ha fullført oppdraget &laquo;{$oppdrag['o_title']}&raquo;.");
			}
		}
		
		// melding i spillelogg
		global $_game;
		player::add_log_static("oppdrag", $text.$prizes_msg, 0, $this->up->id);
		
		// fjern fra params
		$this->up->params->lock();
		$this->up->params->remove("oppdrag_id");
		$this->up->params->remove("oppdrag", true);
		
		$this->link_triggers();
		
		// trigger hos spiller
		$this->up->trigger("oppdrag", array(
				"success" => true,
				"oppdrag" => $oppdrag,
				"params" => $params
		));
		
		return true;
	}
	
	/**
	 * Sett oppdrag som mislykket
	 */
	public function failed($o_id, $text = 'Du mislykket oppdraget &laquo;$name&raquo;.')
	{
		$o_id = (int) $o_id;
		if (!isset($this->oppdrag[$o_id])) return false;
		
		// kontroller at dette oppdraget er aktivt
		$oppdrag = &$this->oppdrag[$o_id];
		if ($oppdrag['uo_active'] == 0)
		{
			return false;
		}
		
		// oppdater oppdraget
		$time = time();
		$a = \Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_repeats = uo_repeats + 1, uo_last_time = $time, uo_last_state = 0, uo_active = 0, uo_active_time = 0, uo_params = NULL WHERE uo_up_id = {$this->up->id} AND uo_o_id = $o_id AND uo_active != 0");
		
		// ble ikke oppdatert?
		if ($a == 0)
		{
			// hent frisk data og avbryt
			$this->user_load_all();
			
			return false;
		}
		
		if (isset($oppdrag['uo_repeats'])) $oppdrag['uo_repeats']++;
		$oppdrag['uo_last_time'] = $time;
		$oppdrag['uo_last_state'] = 0;
		$oppdrag['uo_active'] = 0;
		$oppdrag['uo_active_time'] = 0;
		$oppdrag['uo_params'] = NULL;
		
		unset($this->active);
		$this->active = false;
		
		$text = str_replace('$name', $oppdrag['o_title'], $text);
		
		// send teksten til brukeren
		/*if ($this->user_active)
		{
			ess::$b->page->add_message($text);
		}*/
		
		// melding i spillelogg
		player::add_log_static("oppdrag", $text, 0, $this->up->id);
		
		// forsøk å sett brukeren i fengsel
		$this->up->fengsel_rank(0, false, true, oppdrag::TIME_FENGSEL);
		
		// fjern fra params
		$this->up->params->lock();
		$this->up->params->remove("oppdrag_id");
		$this->up->params->remove("oppdrag", true);
		
		$this->link_triggers();
		
		// trigger hos spiller
		$this->up->trigger("oppdrag", array(
				"success" => false,
				"oppdrag" => $oppdrag
		));
		
		return true;
	}
	
	/**
	 * Brukeren kom i fengsel -- aktive oppdrag blir mislykket hvis det er satt på oppdraget
	 */
	public function fengsel()
	{
		// har vi noen aktive oppdrag?
		if ($this->active)
		{
			// blir dette oppdraget mislykket av fengsel?
			if ($this->triggers_id[$this->active['o_id']]['trigger']->get("fengsel_failure"))
			{
				// oppdraget blir mislykket
				$this->failed($this->active['o_id'], 'Du kom i fengsel noe som gjorde at oppdraget &laquo;$name&raquo; ble mislykket.');
			}
		}
	}
	
	/**
	 * Åpne oppdrag (gjør det mulig å gjennomføre)
	 * 
	 * @param int $o_id
	 */
	public function unlock($o_id, $text = 'Du har gjennomført første delen av oppdraget &laquo;$name&raquo;.')
	{
		$o_id = (int) $o_id;
		$a = \Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_locked = 0, uo_params = NULL WHERE uo_up_id = {$this->up->id} AND uo_o_id = $o_id AND uo_locked != 0");
		$updated = $a > 0;
		
		// oppdater lokal data
		if (isset($this->oppdrag[$o_id]))
		{
			$this->oppdrag[$o_id]['uo_locked'] = 0;
			$this->oppdrag[$o_id]['uo_params'] = NULL;
		}
		
		// oppdater triggers
		$this->link_triggers();
		
		// ikke allerede oppdatert
		if ($updated)
		{
			$text = str_replace('$name', $this->oppdrag[$o_id]['o_title'], $text);
			
			// send teksten til brukeren
			if ($this->user_active)
			{
				ess::$b->page->add_message($text);
			}
			
			// melding i spillelogg
			global $_game;
			player::add_log_static("oppdrag", $text, 0, $this->up->id);
			
			return true;
		}
		
		// ikke oppdatert av dette scriptet
		return false;
	}
	
	/**
	 * Hent ut params tekst og opprett objekt for oppdraget
	 * 
	 * @param oppdraget array reference $row
	 */
	private function load_params(&$row = NULL)
	{
		if ($row)
		{
			$rows = array(&$row);
		}
		else
		{
			$rows = &$this->oppdrag;
		}
		
		foreach ($rows as &$row)
		{
			$this->params[$row['o_id']] = array(
				"uo_params" => new params($row['uo_params']),
				"o_params" => new params($row['o_params']),
				"o_unlock_params" => new params($row['o_unlock_params'])
			);
		}
	}
	
	/**
	 * Hent oppdragene til brukeren og kontroller mot antall oppdrag brukeren kan ha
	 */
	public function user_load_all()
	{
		$result = \Kofradia\DB::get()->query("SELECT uo_repeats, uo_success, uo_availiable, uo_locked, uo_last_time, uo_last_state, uo_active, uo_active_time, uo_params, o_id, o_name, o_title, o_description, o_description_unlock, o_rank_min, o_rank_max, o_retry_wait, o_repeat_wait, o_unlock_params, o_params FROM users_oppdrag, oppdrag WHERE uo_up_id = {$this->up->id} AND uo_availiable = 1 AND uo_o_id = o_id ORDER BY uo_time DESC");
		
		// gå gjennom oppdragene
		unset($this->active);
		$this->active = false;
		$this->params = array();
		$this->oppdrag = array();
		while ($row = $result->fetch())
		{
			// legg til oppdraget
			$this->oppdrag[$row['o_id']] = $row;
			
			// sett opp params
			$this->load_params($this->oppdrag[$row['o_id']]);
			
			// aktivt oppdrag?
			if ($row['uo_active'] != 0)
			{
				$this->active_set($row['o_id']);
			}
		}
		
		$this->oppdrag_loaded = true;
		$this->link_triggers();
	}
	
	/**
	 * Sjekk for nye oppdrag (KUN aktiv bruker)
	 */
	public function check_new()
	{
		// sørg for at alle oppdragene er lastet inn
		if (!$this->oppdrag_loaded) $this->user_load_all();
		
		// sjekk om brukeren kan motta nye oppdrag (og ikke har noe oppdrag aktivt og dette er den aktive brukeren)
		if (!$this->active && count($this->oppdrag) < oppdrag::AVAILIABLE)
		{
			$limit = oppdrag::AVAILIABLE - count($this->oppdrag);
			
			// se om det finnes oppdrag som kan brukes og velg et tilfeldig
			$result = \Kofradia\DB::get()->query("
				SELECT uo_availiable, uo_locked, uo_last_time, uo_last_state, uo_active, uo_active_time, uo_params, o_id, o_name, o_title, o_description, o_description_unlock, o_rank_min, o_rank_max, o_retry_wait, o_repeat_wait, o_unlock_params, o_params
				FROM oppdrag LEFT JOIN users_oppdrag ON o_id = uo_o_id AND uo_up_id = ".$this->up->id."
				WHERE o_active != 0 AND (o_rank_min = 0 OR o_rank_min <= ".$this->up->rank['number'].") AND (o_rank_max = 0 OR o_rank_max >= ".$this->up->rank['number'].") AND IF(!ISNULL(uo_id), uo_availiable = 0 AND ".time()."-uo_last_time >= o_repeat_wait AND (o_repeats = 0 OR o_repeats > uo_repeats), TRUE)
				ORDER BY RAND() LIMIT $limit");
			
			while ($row = $result->fetch())
			{
				// hatt det før?
				if ($row['uo_availiable'] !== NULL)
				{
					\Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_time = ".time().", uo_availiable = 1, uo_locked = 1, uo_params = NULL WHERE uo_up_id = ".$this->up->id." AND uo_o_id = {$row['o_id']} AND uo_availiable = 0");
				}
				
				else
				{
					// legg til ny rad
					\Kofradia\DB::get()->exec("INSERT IGNORE INTO users_oppdrag SET uo_o_id = {$row['o_id']}, uo_up_id = ".$this->up->id.", uo_time = ".time().", uo_availiable = 1");
					$row['uo_active'] = 0;
					$row['uo_active_time'] = 0;
				}
				
				$row['uo_availiable'] = 1;
				$row['uo_locked'] = 1;
				$row['uo_params'] = NULL;
				
				// legg til oppdraget først (og behold keys)
				$this->oppdrag = array($row['o_id'] => $row) + $this->oppdrag;
				
				// sett opp params
				$this->load_params($this->oppdrag[$row['o_id']]);
				
				$this->new[$row['o_id']] = $row['o_id'];
			}
		}
		
		// noen nye oppdrag å lagre?
		if ($this->new) $this->link_triggers();
	}
	
	/**
	 * Generer beskrivelse for oppdraget
	 * 
	 * @param oppdrag id int $o_id
	 * @return string
	 */
	public function get_description($o_id)
	{
		// finn oppdraget
		if (!isset($this->oppdrag[$o_id]))
		{
			if ($this->oppdrag_loaded) throw new HSException("Brukeren har ikke noe oppdrag med ID $o_id.");
			$this->user_load_all();
			return $this->get_description($o_id);
		}
		$o = &$this->oppdrag[$o_id];
		$active = $o['uo_locked'] == 0;
		$params = $active ? $this->params[$o_id]['o_params'] : $this->params[$o_id]['o_unlock_params'];
		
		$prefix = (string) $active ? game::bb_to_html($o['o_description']) : game::bb_to_html($o['o_description_unlock']);
		
		// sjekk for tidsgrense
		if ($active)
		{
			$time_limit = $params->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
		}
		else
		{
			$time_limit = $params->get("time_limit", false);
		}
		
		// sett opp beskrivelse for premie
		$prize = new container($params->get("prize"));
		if (count($prize->items) == 0)
		{
			if ($active) $suffix = '<p><b>Premie:</b> Ikke definert.</p>';
			else $suffix = '';
		}
		else
		{
			$prizes = array();
			foreach ($prize->items as $item)
			{
				switch ($item[0])
				{
					case "cash":
						$prizes[] = game::format_cash($item[1]);
					break;
					
					case "rank_points":
						$points = (int) $item[1];
						$prizes[] = game::format_num($points)." poeng";
					break;
					
					case "bullets":
						$prizes[] = fwords("%d kule", "%d kuler", (int) $item[1]) . ' (må ha våpen og ledig kapasitet)';
					break;
				}
			}
			
			if (count($prizes) == 0)
			{
				$suffix = '<p><b>Premie:</b> Premie er feil satt opp.</p>';
			}
			
			else
			{
				$suffix = '<p><b>Premie:</b></p><ul><li>'.implode("</li><li>", $prizes).'</li></ul>';
			}
		}
		
		// hva slags trigger
		switch ($params->get("name"))
		{
			case "rank_points":
				// oppnå poeng på gitt tid
				$target = $params->get("points");
				return $prefix.'<p>Oppnå '.game::format_num($target).' poeng i løpet av '.game::timespan($time_limit, game::TIME_FULL).'. <span class="dark">Merk at <i>lotto</i> og <i>angrep</i> ikke teller med. Hvis du mottar poeng fra disse funksjonene vil poenggrensen øke med så mange poeng du mottar.</span></p>'.$suffix;
			break;
			
			case "kriminalitet_different":
				// hvor mange forskjellige må vi oppnå?
				$different = $params->get("count", 5);
				
				return $prefix.'<p>Utfør '.$different.' forskjellige kriminaliteter etter hverandre som blir vellykket'.($active ? ' i løpet av '.game::timespan($time_limit, game::TIME_FULL) : '').'.</p>'.$suffix;
			break;
			
			case "poker_unique_people":
				// hvor mange man må vinne mot
				$user_count = $params->get("user_count", 10);
				
				return $prefix.'<p>Vinn '.$user_count.' ganger på rad i poker mot '.$user_count.' forskjellige personer'.($time_limit ? ' innen '.game::timespan($time_limit, game::TIME_FULL) : '').'. Hvis du spiller flere ganger mot samme person, er det første gang som teller. (Taper du første gang, må du vinne '.$user_count.' nye ganger.)</p>'.$suffix;
			break;
			
			case "wanted_level":
				return $prefix.'<p>Oppnå wanted nivå på '.game::format_num($params->get("wanted_level", 500)/10, 1).' %'.($time_limit ? ' innen '.game::timespan($time_limit, game::TIME_FULL) : '').'.</p>'.$suffix;
			
			case "fengsel_breakout":
				return $prefix.'<p>Bryt ut '.$params->get("user_count", 3).' spillere fra fengsel på rad uten å komme i fengsel'.($time_limit ? ' innen '.game::timespan($time_limit, game::TIME_FULL) : '').'.</p>'.$suffix;
		}
		
		return !empty($prefix) ? $prefix.$suffix : '<p>Fant ingen relevant beskrivelse for dette oppdraget på dette stadiet. Ingen triggere som må utføres?</p>'.$suffix;
	}
}
