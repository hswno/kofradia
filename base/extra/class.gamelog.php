<?php

// sett opp reverse
foreach (gamelog::$items as $name => $id)
{
	gamelog::$items_id[$id] = $name;
}

/**
 * Spillelogg
 */
class gamelog
{
	/**
	 * Navn til id
	 */
	public static $items = array(
		"utpressing" => 1,
		"fengsel" => 14,
		"fengsel_dusor_return" => 15,
		"renter" => 2,
		"forfremmelse" => 3,
		"nedgradering" => 4,
		"rank_bonus" => 16,
		"verve_bonus" => 17,
		"testing" => 5,
		"systeminfo" => 6,
		"raw" => 18,
		"crewforum_emne" => 7,
		"crewforum_svar" => 8,
		"bankoverforing" => 9,
		"lotto" => 10,
		"crewforuma_emne" => 11,
		"crewforuma_svar" => 12,
		"informasjon" => 13,
		"poker" => 19,
		"oppdrag" => 20,
		"dead" => 21,
		"crewforumi_emne" => 25,
		"crewforumi_svar" => 26,
		"support" => 31,
		"blokkering" => 32,
		"advarsel" => 33,
		"soknader" => 34,
		"player_bleed" => 35,
		"vitne" => 36,
		"beskyttelse_lost" => 37,
		"attacked" => 38,
		"weapon_lost" => 39,
		"bomberom_kicked" => 40,
		"bomberom_set" => 41,
		"etterlyst_receive" => 42,
		"etterlyst_deactivate" => 43,
		"etterlyst_add" => 44,
		"ff_invite" => 60,
		"ff_delinvite" => 61,
		"ff_member_priority" => 62,
		"ff_member_set_priority" => 68,
		"ff_kick" => 63,
		"ff_dead" => 64,
		"ff_dead_invited" => 65,
		"ff_member_parent" => 66,
		"ff_diverse" => 67,
		"ff_low_health" => 69,
		"ff_takeover" => 70,
		"forum_topic_move" => 80,
		"auksjon_kuler_no_bid" => 45,
		"auksjon_kuler_won" => 46,
		"garage_lost" => 47,
		"achievement" => 48,
		"hall_of_fame" => 49
	);
	
	/**
	 * ID til tekst (kort beskrivende)
	 */
	public static $items_name = array(
		1 => "Utpressing",
		2 => "Renter",
		3 => "Forfremmelse",
		4 => "Nedgradering",
		5 => "Testing",
		6 => "Systeminfo",
		7 => "Crewforum Emne",
		8 => "Crewforum Svar",
		9 => "Bankoverføring",
		10 => "Lotto",
		11 => "Crewforum (arkiv) Emne",
		12 => "Crewforum (arkiv) Svar",
		13 => "Generel Informasjon",
		14 => "Fengsel",
		15 => "Fengseldusør returnert",
		16 => "Rankbonus",
		17 => "Vervebonus",
		18 => "Informasjon",
		19 => "Poker tidsavbrudd",
		20 => "Oppdrag",
		21 => "Døde",
		25 => "Idémyldring Emne",
		26 => "Idémyldring Svar",
		31 => "Support",
		32 => "Blokkering",
		33 => "Advarsel",
		34 => "Søknader",
		35 => "Spiller døde",
		36 => "Vitne",
		37 => "Mistet beskyttelse",
		38 => "Angrepet",
		39 => "Mistet våpen",
		40 => "Kastet ut av bomberom",
		41 => "Plassert i bomberom",
		42 => "Motta penger fra etterlyst",
		43 => "Returnert penger fra etterlyst",
		44 => "Lagt til på etterlyst",
		45 => "Kuleauksjon uten bud",
		46 => "Kuleauksjon vunnet",
		47 => "Garasje mistet",
		48 => "Prestasjon",
		49 => "Hall of Fame",
		60 => "FF invitasjon",
		61 => "FF fjernet invitasjon",
		62 => "FF endret posisjon",
		63 => "FF sparket",
		64 => "FF dødd",
		65 => "FF dødd (invitert)",
		66 => "FF endret overordnet",
		67 => "FF diverse",
		68 => "FF posisjon",
		69 => "FF mistet posisjon",
		70 => "FF overtatt",
		80 => "Forumtråd flyttet"
	);
	
	/**
	 * ID til navn referanse
	 */
	public static $items_id = array();
	
	/**
	 * Sett opp tekst for en logg
	 */
	public function format_log($type, $note, $num)
	{
		global $_game, $__server;
		$html = false;

		// typenavn
		$type_name = self::$items_id[$type];

		switch ($type_name)
		{
			case "utpressing":
				$melding = '[user id='.$note.'] presset deg for [b]'.game::format_cash($num).'[/b]!';
			break;
			
			case "fengsel":
				$dusor = empty($note) ? '' : ' og mottok dusøren på '.game::format_cash($note);
				$melding = '[user id='.$num.'] brøt deg ut av fengselet'.$dusor.'!';
			break;
			
			case "fengsel_dusor_return":
				$melding = 'Ingen hadde brutt deg ut av fengsel innen du kom ut og du fikk tilbake dusøren på '.game::format_cash($num).'.';
			break;
			
			case "renter":
				$melding = "Du mottok [b]".game::format_cash($num)."[/b] i renter fra banken!".(!empty($note) ? ' ' . $note : '');
			break;
			
			case "forfremmelse":
				$melding = "Du ble forfremmet til [b]{$note}[/b]!";
			break;
			
			case "nedgradering":
				$melding = "Du ble nedgradert til [b]{$note}[/b]!";
			break;
			
			// rank bonus
			case "rank_bonus":
				// syntax: plassering(int):prosent bonus(float), num = bonus
				$info = explode(":", $note);
				$melding = 'Du var den '.($info[0] == 1 ? 'beste' : $info[0].'. beste').' rankeren de siste 24 timene og fikk '.game::format_num($info[1]*100).' % i bonus av poengene du hadde skaffet ('.game::format_num($num).' poeng i bonus).';
			break;
			
			// verve bonus
			case "verve_bonus":
				// syntax: antall_spillere num = bonus
				$melding = 'Du mottok '.game::format_num($num).' poeng i bonus fra '.fwords("%d spiller", "%d spillere", $note).' du har vervet som hadde ranket de siste 24 timene.';
			break;
			
			case "testing":
				$melding = "Testing - Melding: {$note} - Tall: {$num}";
			break;
			
			case "raw":
				$html = true;
				$melding = $note;
			break;
			
			case "systeminfo":
				$melding = "Systeminformasjon: " . $note;
			break;
			
			// emen i crewforumet
			case "crewforum_emne":
				$u = explode(":", $note, 2);
				$html = true;
				$melding = '<user id="'.$u[0].'" /> opprettet <a href="forum/topic?id='.$num.'">'.htmlspecialchars($u[1]).'</a> i crewforumet.';
			break;
			
			// svar i crewforumet
			case "crewforum_svar":
				$u = explode(":", $note, 2);
				$s = explode("#", $u[0]);
				$u[0] = $s[0];
				$replyid = isset($s[1]) ? '&amp;replyid='.$s[1] : '';
				$html = true;
				$melding = '<user id="'.$u[0].'" /> svarte i <a href="forum/topic?id='.$num.$replyid.'">'.htmlspecialchars($u[1]).'</a> i crewforumet';
			break;
			
			// emne i crewforumet (arkiv)
			case "crewforuma_emne":
				$u = explode(":", $note, 2);
				$html = true;
				$melding = '<user id="'.$u[0].'" /> opprettet <a href="forum/topic?id='.$num.'">'.htmlspecialchars($u[1]).'</a> i crewforumet (arkiv).';
			break;
			
			// svar i crewforumet (arkiv)
			case "crewforuma_svar":
				$u = explode(":", $note, 2);
				$s = explode("#", $u[0]);
				$u[0] = $s[0];
				$replyid = isset($s[1]) ? '&amp;replyid='.$s[1] : '';
				$html = true;
				$melding = '<user id="'.$u[0].'" /> svarte i <a href="forum/topic?id='.$num.$replyid.'">'.htmlspecialchars($u[1]).'</a> i crewforumet (arkiv).';
			break;
			
			// emne i idémyldringsforumet
			case "crewforumi_emne":
				$u = explode(":", $note, 2);
				$html = true;
				$melding = '<user id="'.$u[0].'" /> opprettet <a href="forum/topic?id='.$num.'">'.htmlspecialchars($u[1]).'</a> i idémyldringsforumet.';
			break;
			
			// svar i idémyldringsforumet
			case "crewforumi_svar":
				$u = explode(":", $note, 2);
				$s = explode("#", $u[0]);
				$u[0] = $s[0];
				$replyid = isset($s[1]) ? '&amp;replyid='.$s[1] : '';
				$html = true;
				$melding = '<user id="'.$u[0].'" /> svarte i <a href="forum/topic?id='.$num.$replyid.'">'.htmlspecialchars($u[1]).'</a> i idémyldringsforumet.';
			break;
			
			case "bankoverforing":
				$info = explode(":", $note, 2);
				$melding = '[user id='.$num.'] sendte deg [b]'.game::format_cash($info[0]).'[/b]!'.(!empty($info[1]) ? ' [b]Melding[/b]: '.$info[1] : '');
			break;
			
			case "lotto":
				$info = explode(":", $note);
				$data = array();
				$data[] = 'Du kom på <b>'.$info[0].'</b>. plass i lotto';
				$data[] = 'vant <b>'.game::format_cash($num).'</b>';
				if (isset($info[1])) $data[] = 'mottok <b>'.game::format_num($info[1]).'</b> poeng';
				$melding = sentences_list($data)."!";
				$html = true;
			break;
			
			case "informasjon":
				$html = $num == 1;
				$melding = $note;
			break;
			
			case "poker":
				// winner:utfordrer:pott
				$info = explode(":", $note, 3);
				$melding = 'Du brukte for lang tid da du utfordret [user id='.$info[1].'] i poker for [b]'.game::format_cash($num).'[/b]. Spillet valgte kort for deg automatisk..';
				switch ($info[0])
				{
					case 1:
						$melding .= ' Motstanderen vant runden.';
						break;
					case 2:
						$melding .= ' Du vant runden og fikk [b]'.game::format_cash($info[2]).'[/b].';
						break;
					default:
						$melding .= ' Det ble uavgjort og du fikk tilbake [b]'.game::format_cash($info[2]).'[/b].';
				}
			break;
			
			// oppdrag
			case "oppdrag":
				$html = true;
				$melding = $note;
			break;
			
			// døde
			case "dead":
				// syntax: instant(int:0/1)
				$html = true;
				$melding = $note ? "Du ble angrepet og klarte ikke å stå i mot angrepet. Du døde." : "Du døde på grunn av lav energi og lav helse.";
			break;
			
			case "support":
				// avsluttet?
				if (mb_substr($note, 0, 2) == "c:")
				{
					$info = explode(":", $note, 3); // c:sum_up_id:su_title
					$html = true;
					$melding = '<user id="'.$info[1].'" /> avsluttet din henvendelse &laquo;<a href="'.$__server['relative_path'].'/support/?a=show&amp;su_id='.$num.'">'.htmlspecialchars($info[2]).'</a>&raquo; hos support.';
					break;
				}
				
				// oppdatert
				$info = explode(":", $note, 2); // sum_up_id:su_title
				$html = true;
				$melding = '<user id="'.$info[0].'" /> oppdaterte din henvendelse &laquo;<a href="'.$__server['relative_path'].'/support/?a=show&amp;su_id='.$num.'">'.htmlspecialchars($info[1]).'</a>&raquo; hos support.';
			break;
			
			case "blokkering":
				// ny blokkering: 1:type:end:reason
				// blokkering endret: 2:type:end:reason end og reason kan være blank hvis feltet ikke ble endret
				// blokkering fjernet: 3:type
				$info = explode(":", $note);
				$blokkering = isset(blokkeringer::$types[$num]) ? blokkeringer::$types[$num]['userlog'] : '(type ukjent: '.$num.')';
				switch ($info[0])
				{
					// ny blokkering
					case 1:
						$melding = 'Du har blitt blokkert fra å '.$blokkering.'. Varer til '.ess::$b->date->get($info[1])->format().'. Begrunnelse: '.urldecode($info[2]);
					break;
					
					// blokkering endret
					case 2:
						$melding = 'Blokkeringen for å '.$blokkering.' har blitt endret.';
						if ($info[1] != "") $melding .= ' Ny varighet til '.ess::$b->date->get($info[1])->format().'.';
						if ($info[2] != "") $melding .= ' Ny begrunnelse: '.urldecode($info[2]);
					break;
					
					// blokkering fjernet
					case 3:
						$melding = 'Blokkeringen for å '.$blokkering.' har blitt fjernet.';
					break;
				}
			break;
			
			case "advarsel":
				// type:reason
				$info = explode(":", $note, 2);
				$melding = 'Du har fått en advarsel fra Crewet (kategori: '.urldecode($info[0]).'). Begrunnelse: '.urldecode($info[1]);
			break;
			
			case "soknader":
				// av enkelthetskyld (og praktiske årsaker) blir meldinger lagt til med full tekst fra søknadssystemet
				// dette kan utvidees ved en senere anledning
				// syntax: html:Din søknad [..]
				// syntax: bb:Din søknad [..]
				$info = explode(":", $note, 2);
				if ($info[0] == "html")
				{
					$html = true;
					$melding = $info[1];
				}
				elseif ($info[0] == "bb")
				{
					$melding = $info[1];
				}
			break;
			
			// spiller bløde ihjel etter angrep
			case "player_bleed":
				$html = true;
				$melding = '<user id="'.$num.'" /> døde av skadene som ble påført i ditt tidligere angrep.';
			break;
			
			// vitne
			case "vitne":
				// syntax: drept:attack_type:ble_sett:offer_up_id (num = angriper)
				$info = explode(":", $note);
				$html = true;
				$melding = 'Du vitnet <user id="'.$num.'" /> '.($info[0] ? 'drepe' : 'skade').' <user id="'.$info[3].'" />.';
				
				// ble vi oppdaget?
				if ($info[2]) $melding .= ' Du ble oppdaget av <user id="'.$num.'" />.';
			break;
			
			// mistet beskyttelse
			case "beskyttelse_lost":
				// syntax: gammel_beskyttelse_navn:ny_beskyttelse_navn:ny_beskyttelse_state (navn er urlencode-ed)
				$info = explode(":", $note);
				$html = true;
				$melding = 'Du mistet din beskyttelse <b>'.htmlspecialchars(urldecode($info[0])).'</b>. Du har nå <b>'.htmlspecialchars(urldecode($info[1])).'</b> som beskyttelse med en status på <b>'.game::format_num($info[2]*100, 2).' %</b>.';
			break;
			
			// angrepet?
			case "attacked":
				// syntax: lost_health:lost_energy:lost_protection:lost_rankpoints:new_health:new_energy:new_protection:new_rankpoints:gammel_bydel:ny_bydel:bank:cash
				$info = explode(":", $note);
				$html = true;
				
				// vise hvor mye vi mistet
				$d = array();
				$d[] = '<b>'.game::format_num($info[0]*100, 2).' %</b> helse';
				$d[] = '<b>'.game::format_num($info[1]*100, 2).' %</b> energi';
				if ($info[2]) $d[] = '<b>'.game::format_num($info[2]*100, 2).' %</b> beskyttelse';
				$d[] = '<b>'.$info[3].'</b> poeng';
				
				$melding = 'Du ble angrepet av en spiller og mistet '.sentences_list($d).'.';
				
				// vis verdiene etter angrepet
				$d = array();
				$d[] = '<b>'.game::format_num($info[4]*100, 2).' %</b> helse';
				$d[] = '<b>'.game::format_num($info[5]*100, 2).' %</b> energi';
				if ($info[6]) $d[] = '<b>'.game::format_num($info[6]*100, 2).' %</b> beskyttelse';
				
				$melding .= ' Du endte opp med '.sentences_list($d).'.';
				
				// mistet vi penger?
				if (!empty($info[10]))
				{
					$melding .= ' Angriperen fikk i tillegg med seg <b>'.game::format_cash($info[10]).'</b> fra hånda di.';
				}
				
				// ble vi flyttet til en annen bydel?
				if (!empty($info[8]))
				{
					$melding .= ' Du ble flyttet fra bydelen '.htmlspecialchars(urldecode($info[8])).' til <b>'.htmlspecialchars(urldecode($info[9])).'</b> siden du hadde under '.game::format_num(player::HEALTH_MOVE_AUTO*100).' % helse.';
				}
			break;
			
			// mistet/nedgradert våpen
			case "weapon_lost":
				// syntax 1: weapon_id:weapon_name:bullets (num = 0)
				// syntax 2: weapon_id:weapon_name:bullets:new_weapon:new_training (num = 1)
				$info = explode(":", $note);
				$html = true;
				
				if ($num == 1)
				{
					$melding = 'Våpentreningen falt under 25 % og ditt våpen <b>'.htmlspecialchars(urldecode($info[1])).'</b>'.($info[2] > 0 ? ' med <b>'.$info[2].'</b> kuler' : '').' ble nedgradert til våpnet <b>'.htmlspecialchars(urldecode($info[3])).'</b> med '.game::format_num($info[4]*100).' % våpentrening og 0 kuler.';
				}
				
				else
				{
					$melding = 'Våpentreningen falt under 25 % og du mistet våpenet <b>'.htmlspecialchars(urldecode($info[1])).'</b>'.($info[2] > 0 ? ' og <b>'.$info[2].'</b> kuler' : '').'.';
				}
			break;
			
			// kastet ut av bomberom
			case "bomberom_kicked":
				// syntax: up_id(som utfører handlingen):urlencode(ff_name):up_brom_expire(når vi egentlig skulle gå ut av bomberommet) num=ff_id
				$info = explode(":", $note);
				$html = true;
				
				$melding = 'Du ble kastet ut fra bomberommet <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$num.'">'.htmlspecialchars(urldecode($info[1])).'</a>. Du skulle egentlig sittet til '.ess::$b->date->get($info[2])->format().'.';
			break;
			
			// plassert i bomberom
			case "bomberom_set":
				// syntax: up_id(som utfører handlingen):urlencode(ff_name):up_brom_expire(hvor lenge vi er inne) num=ff_id
				$info = explode(":", $note);
				$html = true;
				
				$melding = '<user id="'.$info[0].'" /> plasserte deg i bomberommet <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$num.'">'.htmlspecialchars(urldecode($info[1])).'</a> til '.ess::$b->date->get($info[2])->format().'.';
			break;
			
			// fikk penger fra etterlyst for angrep
			case "etterlyst_receive":
				// syntax: up_id(som ble angrepet):bool(instant?):bool(bare skadet?)
				$info = explode(":", $note);
				$html = true;
				
				$melding = '<user id="'.$info[0].'" /> '.(!empty($info[2]) ? 'ble skadet av ditt angrep' : 'døde etter ditt angrep').' og du mottok '.game::format_cash($num).' som'.(!empty($info[2]) ? ' del av det' : '').' spilleren var etterlyst for.';
			break;
			
			// fikk tilbake penger fra etterlyst fordi spiller ble deaktivert
			case "etterlyst_deactivate":
				// syntax: up_id(som ble deaktivert)
				$html = true;
				$melding = '<user id="'.$note.'" /> ble deaktivert og du fikk tilbake '.game::format_cash($num).' fra etterlyst som du hadde plassert på spilleren.';
			break;
			
			// lagt til på etterlyst
			case "etterlyst_add":
				$melding = 'En spiller la til en dusør for deg på '.game::format_cash($num).'.';
			break;
			
			// kuleauksjon avsluttet uten bud
			case "auksjon_kuler_no_bid":
				// syntax: a_id(auksjonen) num=antall kuler returnert
				$html = true;
				$melding = 'Ingen vant <a href="'.ess::$s['relative_path'].'/auksjoner?a_id='.$note.'">auksjonen</a> for kuler du la ut for salg og du fikk tilbake '.$num.' kuler.';
			break;
			
			// vinner kuleauksjon
			case "auksjon_kuler_won":
				// syntax: a_id(auksjonen):amount(beløp man vant med) num=antall kuler
				$info = explode(":", $note);
				$html = true;
				$melding = 'Du vant <a href="'.ess::$s['relative_path'].'/auksjoner?a_id='.$info[0].'">auksjonen</a> for kuler med ditt bud på '.game::format_cash($info[1]).' og mottok '.$num.' kuler.';
			break;
			
			// mistet garasje
			case "garage_lost":
				// syntax: urlencode(bydel) num=antal biler
				$html = true;
				$melding = 'Du mistet garasjen din på '.htmlspecialchars(urldecode($note)).($num > 0 ? ' og '.fwords("den ene bilen", "de %d bilene", $num).' som var i garasjen' : '').'.';
			break;
			
			// FF-systemet
			
			case "ff_invite":
				// ff_id:ff_name:stilling:parent
				$info = explode(":", $note, 4);
				$html = true;
				$melding = '<user id="'.$num.'" /> inviterte deg til <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> for posisjonen <b>'.htmlspecialchars(urldecode($info[2])).'</b>'.(!empty($info[3]) ? ' underordnet <user id="'.$info[3].'" />' : '').'.';
			break;
			
			case "ff_delinvite":
				// ff_id:ff_name
				$info = explode(":", $note, 2);
				$html = true;
				if ($num)
				{
					$melding = '<user id="'.$num.'" /> fjernet din invitasjon til <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a>.';
				}
				else
				{
					$melding = 'Din invitasjon til <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> ble fjernet.';
				}
			break;
			
			case "ff_member_priority":
				// num = action_user_id
				// ff_id:ff_name:priority_old:priority_new:parent_old:parent_new
				$info = explode(":", $note);
				$html = true;
				if ($num)
				{
					$melding = '<user id="'.$num.'" /> endret din posisjon i <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> fra '.htmlspecialchars(urldecode($info[2])).(!empty($info[4]) ? ' underordnet <user id="'.$info[4].'" />' : '').' til <b>'.htmlspecialchars(urldecode($info[3])).'</b>'.(!empty($info[5]) ? ' underordnet <user id="'.$info[5].'" />' : '').'.';
				}
				else
				{
					// anonym
					$melding = 'Din posisjon i <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> ble endret fra '.htmlspecialchars(urldecode($info[2])).(!empty($info[4]) ? ' underordnet <user id="'.$info[4].'" />' : '').' til <b>'.htmlspecialchars(urldecode($info[3])).'</b>'.(!empty($info[5]) ? ' underordnet <user id="'.$info[5].'" />' : '').'.';
				}
			break;
			
			case "ff_member_set_priority":
				// ff_id:ff_name:priority:parent_up_id
				$info = explode(":", $note);
				$html = true;
				$melding = 'Du ble satt som '.htmlspecialchars(urldecode($info[2])).(!empty($info[3]) ? ' underordnet <user id="'.$info[3].'" />' : '').' i <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a>.';
			break;
			
			case "ff_member_parent":
				// num = action_user_id
				// ff_id:ff_name:parent_old:parent_new
				$info = explode(":", $note);
				$html = true;
				if ($num)
				{
					$melding = '<user id="'.$num.'" /> endret din overordnede i <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> fra <user id="'.$info[2].'" /> til <user id="'.$info[3].'" />.';
				}
				else
				{
					// anonym
					$melding = 'Din overordnede i <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> ble endret fra <user id="'.$info[2].'" /> til <user id="'.$info[3].'" />.';
				}
			break;
			
			case "ff_kick":
				// ff_id:ff_name:note
				$info = explode(":", $note, 3);
				$html = true;
				$note = empty($info[2]) ? '' : ' Begrunnelse: '.game::bb_to_html(urldecode($info[2]));
				$melding = '<user id="'.$num.'" /> kastet deg ut fra <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a>.'.$note;
			break;
			
			case "ff_dead":
				// refstring,name
				$info = explode(":", $note, 2);
				$html = true;
				$melding = ucfirst($info[0]).' <b>'.htmlspecialchars(urldecode($info[1])).'</b> har blitt oppløst.';
			break;
			
			case "ff_dead_invited":
				// refstring,name
				$info = explode(":", $note, 2);
				$html = true;
				$melding = ucfirst($info[0]).' <b>'.htmlspecialchars(urldecode($info[1])).'</b> som du var invitert til har blitt oppløst.';
			break;
			
			case "ff_diverse":
				$html = true;
				$melding = $note;
			break;
			
			case "ff_low_health":
				// ff_id:ff_name:stilling:parent
				$info = explode(":", $note, 4);
				$html = true;
				$melding = 'Du mistet posisjonen som <b>'.htmlspecialchars(urldecode($info[2])).'</b>'.(!empty($info[3]) ? ' underordnet <user id="'.$info[3].'" />' : '').' i <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> grunnet for lav helse.';
			break;
			
			case "ff_takeover":
				// ff_id:ff_name_org:ff_name_new:ff_type_ref:ff_stilling
				$info = explode(":", $note, 5);
				$html = true;
				$melding = 'Ditt angrep førte til at '.htmlspecialchars(urldecode($info[3])).' '.htmlspecialchars(urldecode($info[1])).' ble stående uten '.htmlspecialchars(urldecode($info[4])).'. Du tok derfor over '.htmlspecialchars(urldecode($info[3])).' som fikk navnet <a href="ff/?ff_id='.$info[0].'">'.htmlspecialchars(urldecode($info[2])).'</a>.';
			break;
			
			// forumtråd flyttet
			case "forum_topic_move":
				// ft_id, ft_title, fromname, toname, up_id(hvem gjorde det)
				$info = explode(":", $note);
				$html = true;
				$melding = 'Din forumtråd <a href="'.ess::$s['relative_path'].'/forum/topic?id='.$info[0].'">'.htmlspecialchars(urldecode($info[1])).'</a> ble flyttet fra '.htmlspecialchars(urldecode($info[2])).' til '.htmlspecialchars(urldecode($info[3])).'.';
			break;
			
			// prestasjon oppnådd
			case "achievement":
				// count(repetisjonsnummer), ac_name, prize
				// num: ac_id
				$info = explode(":", $note);
				$html = true;
				
				$rep = $info[0] > 1 ? ' for '.$info[0].'. gang' : '';
				$prize = !empty($info[2]) ? ' og mottok '.$info[2] : '';
				$melding = 'Du oppnådde prestasjonen &laquo;'.htmlspecialchars(urldecode($info[1]))."&raquo;".$rep.$prize.'.';
			break;
			
			// hall of fame
			case "hall_of_fame":
				$html = true;
				$melding = 'Du ble den '.$note.' og havnet på <a href="'.ess::$s['rpath'].'/hall_of_fame">Hall of Fame</a>!';
			break;
			
			default:
				$type = "Ukjent ({$type})";
				$melding = $note;
		}

		$melding = $html ? $melding : game::bb_to_html($melding);
		return $melding;
	}
}