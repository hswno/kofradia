<?php

class page_lock
{
	/** _GET params */
	protected $get;
	
	/** Standard adresse */
	protected $url;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->get = isset($_GET['orign']) ? array("orign" => $_GET['orign']) : array();
		$this->url = game::address("lock", $this->get);
		redirect::store($this->url);
		
		ess::$b->page->add_title("Begrenset tilgang");
		
		// løse problemet?
		if (isset($_GET['f']))
		{
			$this->solve();
		}
		
		// vis oversikt
		else
		{
			$this->overview();
		}
		
		ess::$b->page->load();
	}
	
	/**
	 * Behandle
	 */
	protected function solve()
	{
		$f = $_GET['f'];
		if (!in_array($f, login::$user->lock))
		{
			redirect::handle();
		}
		
		switch ($f)
		{
			case "birth":
				$this->solve_birth();
			break;
			
			case "player":
				$this->solve_player();
			break;
			
			case "pass":
				$this->solve_pass();
			break;
			
			default:
				redirect::handle();
		}
	}
	
	/**
	 * Vis oversikt
	 */
	protected function overview()
	{
		echo '
<h1>Begrenset tilgang</h1>';
		
		ess::$b->page->add_css('
.lock_box {
	width: 300px;
	margin: 20px auto;
	padding: 0 10px;
	border: 2px solid #292929;
	background-color: #1A1A1A;
}
.lock_box h2 {
	background-color: #2D2D2D;
	margin: 0 -10px 10px -10px;
	padding: 4px 4px 2px 4px;
}');
		
		foreach (login::$user->lock as $row)
		{
			switch ($row)
			{
				case "birth":
					echo '
<div class="lock_box r3">
	<h2>Fødselsdato</h2>
	<p>Du har ikke registrert din fødselsdato. Vi krever at alle som skal benytte seg av Kofradia oppgir sin fødselsdato for vår garanti for at dere oppfyller vårt krav om alder.</p>
	<p><a href="'.htmlspecialchars(game::address("lock", $this->get, array(), array("f" => "birth"))).'">Fyll inn fødselsdato &raquo;</a></p>
</div>';
				break;
				
				case "player":
					$killed = login::$user->player->data['up_deactivated_dead'];
					$deact_self = false;
					
					// deaktivert self?
					if (!$killed)
					{
						// deaktivert av seg selv?
						if (!empty(login::$user->player->data['up_deactivated_up_id']))
						{
							$deact_self = login::$user->player->data['up_deactivated_up_id'] == login::$user->player->id;
							if (!$deact_self)
							{
								$result = ess::$b->db->query("SELECT u_id FROM users JOIN users_players ON u_id = up_u_id WHERE up_id = ".login::$user->player->data['up_deactivated_up_id']);
								$row = mysql_fetch_assoc($result);
								mysql_free_result($result);
								if ($row && $row['u_id'] == login::$user->id) $deact_self = true;
							}
						}
					}
					
					echo '
<div class="lock_box r3">
	<h2>Spiller '.($killed == 2 ? 'blødd ihjel' : ($killed ? 'drept' : 'deaktivert')).'</h2>
	<p>'.($deact_self ? 'Du deaktivert din spiller' : 'Din spiller '.($killed == 2 ? 'blødde ihjel på grunn av lite energi og helse' : ($killed ? 'ble drept' : 'ble deaktivert'))).'. Du må opprette en ny spiller for å kunne fortsette å spille.</p>
	<p><a href="">Mer informasjon</a> | <a href="'.htmlspecialchars(game::address("lock", $this->get, array(), array("f" => "player"))).'">Opprett ny spiller &raquo;</a></p>
</div>';
				break;
				
				case "pass":
					echo '
<div class="lock_box r3">
	<h2>Mangler passord</h2>
	<p>Din bruker har for øyeblikket ikke noe passord, noe som er et resultat av at du har bedt om å nullstille passordet ditt.</p>
	<p><a href="'.htmlspecialchars(game::address("lock", $this->get, array(), array("f" => "pass"))).'">Opprett nytt passord &raquo;</a></p>
</div>';
				break;
				
				default:
					throw new HSException("Ukjent lock: $row");
			}
		}
	}
	
	/**
	 * Behandle fødselsdato
	 */
	protected function solve_birth()
	{
		global $_lang;
		
		echo '
<h1>Fødselsdato</h1>
<p class="h_right"><a href="'.htmlspecialchars($this->url).'">Tilbake</a></p>
<p>Din bruker har ingen fødselsdato tilknyttet seg. Vi vil at alle brukerene skal ha dette.</p>
<p>Feil fødselsdato vil i de fleste tilfeller føre til deaktivering av kontoen.</p>';
		
		// submit?
		if (isset($_POST['b_dag']))
		{
			$b_dag = intval(postval("b_dag"));
			$b_maaned = intval(postval("b_maaned"));
			$b_aar = intval(postval("b_aar"));
			
			$date = ess::$b->date->get();
			$n_day = $date->format("j");
			$n_month = $date->format("n");
			$n_year = $date->format("Y");
			
			$age = $n_year - $b_aar - (($n_month < $b_maaned || ($b_maaned == $n_month && $n_day < $b_dag)) ? 1 : 0);
			$birth = $b_aar."-".str_pad($b_maaned, 2, "0", STR_PAD_LEFT)."-".str_pad($b_dag, 2, "0", STR_PAD_LEFT);
			
			// sjekk om fødselsdatoen er gyldig
			$birth_date = ess::$b->date->get();
			$birth_date->setDate($b_aar, $b_maaned, $b_dag);
			$birth_valid = $birth_date->format("Y-m-d") == $birth;
			
			// kontroller dataen
			$error = array();
			if ($b_dag < 1 || $b_dag > 31)
			{
				$error[] = "Du må velge en gyldig dag.";
			}
			if ($b_maaned < 1 || $b_maaned > 12)
			{
				$error[] = "Du må velge en gyldig måned.";
			}
			if ($b_aar < 1900 || $b_aar > $date->format("Y"))
			{
				$error[] = "Du må velge et gyldig år.";
			}
			
			// ugyldig fødselsdato?
			if (count($error) == 0 && !$birth_valid)
			{
				$error[] = "Datoen du fylte inn for fødselsdatoen din eksisterer ikke.";
			}
			
			// noen feil?
			if (count($error) > 0)
			{
				echo '
<div class="error_box">
	<p>Feil:</p>
	<ul>
		<li>'.implode('</li>
		<li>', $error).'</li>
	</ul>
</div>';
			}
			
			elseif ($age < 13)
			{
				putlog("CREWCHAN", "%c9%bUNDER ALDERSGRENSEN?:%b%c %u".login::$user->player->data['up_name']."%u prøvde å legge inn fødselsdatoen %u{$birth}%u (%u{$age}%u år)!");
				
				echo '
<p class="error_box">Du må ha fylt 13 år for å kunne spille Kofradia.</p>';
			}
			
			elseif (!isset($_POST['fix']))
			{
				// godkjent?
				if (isset($_POST['verify']))
				{
					// oppdater brukeren
					ess::$b->db->query("UPDATE users SET u_birth = '$birth' WHERE u_id = ".login::$user->id." AND (u_birth IS NULL OR u_birth = '0000-00-00')");
					
					if (ess::$b->db->affected_rows() > 0)
					{
						ess::$b->page->add_message("Fødselsdatoen $b_dag. {$_lang['months'][$b_maaned]} $b_aar er nå registrert til din bruker.");
						putlog("CREWCHAN", "%c7%bFødselsdato registrert:%b%c %u".login::$user->player->data['up_name']."%u la inn fødselsdatoen %u{$birth}%u (%u{$age}%u år).");
					}
					
					redirect::handle();
				}
				
				else
				{
					echo '
<div class="section">
	<h2>Godkjenn fødselsdato</h2>
	<p>Du har opplyst om at din fødselsdato er <u>'.$b_dag.'. '.$_lang['months'][$b_maaned].' '.$b_aar.'</u> ('.$birth.'). Det vil si at du er '.$age.' år.</p>
	<p>Stemmer dette?</p>
	<form action="" method="post">
		<input type="hidden" name="b_dag" value="'.$b_dag.'" />
		<input type="hidden" name="b_maaned" value="'.$b_maaned.'" />
		<input type="hidden" name="b_aar" value="'.$b_aar.'" />
		<p>'.show_sbutton("Ja - registrer dette", 'name="verify"').' '.show_sbutton("Nei - endre", 'name="fix"').'</p>
	</form>
</div>';
					
					return;
				}
			}
		}
		
		ess::$b->page->add_title("Registrere fødselsdato");
		
		echo '
<form action="" method="post">
	<dl class="dl_10">
		<dt>Dato</dt>
		<dd>
			<select name="b_dag">
				<option value="">Dag</option>';
		
		$active = postval("b_dag");
		for ($i = 1; $i <= 31; $i++)
		{
			echo '
				<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.$i.'</option>';
		}
		
		echo '
			</select>
		</dd>
		<dt>Måned</dt>
		<dd>
			<select name="b_maaned">
				<option value="">Måned</option>';
		
		$active = postval("b_maaned");
		for ($i = 1; $i <= 12; $i++)
		{
			echo '
				<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.ucfirst($_lang['months'][$i]).'</option>';
		}
		
		echo '
			</select>
		</dd>
		<dt>År</dt>
		<dd>
			<select name="b_aar">
				<option value="">År</option>';
		
		$active = postval("b_aar");
		for ($i = ess::$b->date->get()->format("Y"); $i >= 1900; $i--)
		{
			echo '
				<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.$i.'</option>';
		}
		
		echo '
			</select>
		</dd>
	</dl>
	<p>'.show_sbutton("Legg til fødselsdato").'</p>
</form>';
	}
	
	/**
	 * Behandle ny spiller
	 */
	protected function solve_player()
	{
		ess::$b->page->add_title("Ny spiller");
		redirect::store($_SERVER['REQUEST_URI']);
		
		// sjekk om vi allerede har en spiller fra før som ikke er den aktive
		$result = ess::$b->db->query("SELECT up_id, up_name, up_created_time, up_last_online, up_access_level FROM users_players WHERE up_u_id = ".login::$user->id." AND up_access_level != 0");
		if (mysql_num_rows($result) > 0)
		{
			// sett opp liste over spillere
			$players = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$players[$row['up_id']] = $row;
			}
			
			// velge aktiv spiller?
			if (isset($_POST['select']) && isset($_POST['up_id']) && validate_sid())
			{
				$up_id = (int) $_POST['up_id'];
				
				if (!isset($players[$up_id]))
				{
					ess::$b->page->add_message("Fant ikke spillere.", "error");
					redirect::handle();
				}
				
				// sett som aktiv spiller
				ess::$b->db->query("UPDATE users SET u_active_up_id = $up_id WHERE u_id = ".login::$user->id);
				ess::$b->page->add_message('Du har valgt <user="'.$players[$up_id]['up_name'].'" /> som din aktive spiller.');
				
				redirect::handle("min_side");
			}
			
			echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Ny spiller<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_left"><a href="'.htmlspecialchars($this->url).'">Tilbake</a></p>
	<div class="bg1">
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p>Du har allerede en annen spiller som er i live. Du må enten deaktivere spilleren eller velge å bruke den som din aktive spiller.</p>
			<table class="table center">
				<thead>
					<tr>
						<th>ID</th>
						<th>Spiller</th>
						<th>Opprettet</th>
						<th>Sist aktiv</th>
						<th>Deaktiver</th>
					</tr>
				</head>
				<tbody>';
			
			foreach ($players as $row)
			{
				echo '
					<tr class="box_handle">
						<td><input type="radio" name="up_id" value="' . $row['up_id'] . '" />'.$row['up_id'].'</td>
						<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>
						<td>'.ess::$b->date->get($row['up_created_time'])->format().'</td>
						<td>'.ess::$b->date->get($row['up_last_online'])->format().'</td>
						<td><a href="min_side?up_id='.$row['up_id'].'&amp;a=deact">Deaktiver</a></td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Velg som aktiv spiller", 'name="select"').'</p>
		</form>
	</div>
</div>';
			
			ess::$b->page->load();
		}
		
		// opprette ny spiller?
		if (isset($_POST['name']) && !isset($_POST['abort']) && validate_sid())
		{
			$name = trim($_POST['name']);
			$bydel = postval("bydel");
			if (!isset(game::$bydeler[$bydel]) || !game::$bydeler[$bydel]['active']) $bydel = false;
			
			// kontroller navnet
			$result1 = ess::$b->db->query("SELECT ".ess::$b->db->quote($name, false)." REGEXP regex AS m, error FROM regex_checks WHERE (type = 'reg_user_special' OR type = 'reg_user_strength') HAVING m = 1");
			
			$where = ALLOW_SAME_PLAYERNAME ? " AND (up_u_id != ".login::$user->id." OR up_access_level != 0)" : "";
			$result2 = ess::$b->db->query("SELECT up_id FROM users_players WHERE up_name = ".ess::$b->db->quote($name).$where);
			
			$result3 = ess::$b->db->query("SELECT id FROM registration WHERE user = ".ess::$b->db->quote($name));
			
			// ugyldig navn?
			if (mysql_num_rows($result1) > 0)
			{
				$feil = array();
				while ($row = mysql_fetch_assoc($result1)) $feil[] = '<li>'.htmlspecialchars($row['error']).'</li>';
				ess::$b->page->add_message("Spillernavnet var ikke gyldig:<ul>".implode("", $feil)."</ul>", "error");
			}
			
			// har ikke valgt noe navn?
			elseif (empty($name))
			{
				ess::$b->page->add_message("Du må skrive inn et navn du ønsker at din nye spiller skal ha.", "error");
			}
			
			// allerede i bruk?
			elseif (mysql_num_rows($result2) > 0)
			{
				ess::$b->page->add_message("Spillernavnet er allerede tatt! Velg et annet.", "error");
			}
			
			// noen forsøker å registrere seg med dette?
			elseif (mysql_num_rows($result3) > 0)
			{
				ess::$b->page->add_message("Noen holder allerede på å registrere seg med dette spillernavnet. Velg et annet.", "error");
			}
			
			else
			{
				// godkjent?
				if (isset($_POST['confirm']))
				{
					// finne tilfeldig bydel?
					if (!$bydel)
					{
						// finn en tilfeldig bydel
						$result = ess::$b->db->query("SELECT id FROM bydeler WHERE active = 1 ORDER BY RAND()");
						$bydel = mysql_result($result, 0);
					}
					
					ess::$b->db->begin();
					
					// opprett spiller og tilknytt brukeren
					ess::$b->db->query("INSERT INTO users_players SET up_u_id = ".login::$user->id.", up_name = ".ess::$b->db->quote($name).", up_created_time = ".time().", up_b_id = $bydel");
					$up_id = ess::$b->db->insert_id();
					
					// sett opp riktig rank plassering
					#ess::$b->db->query("UPDATE users_players AS main, (SELECT COUNT(users_players.up_id)+1 AS pos, ref.up_id FROM users_players AS ref LEFT JOIN users_players ON users_players.up_points > ref.up_points AND users_players.up_access_level < ".ess::$g['access_noplay']." AND users_players.up_access_level != 0 WHERE ref.up_id = $up_id GROUP BY ref.up_id) AS rp SET main.up_rank_pos = rp.pos WHERE main.up_id = rp.up_id");
					ess::$b->db->query("INSERT INTO users_players_rank SET upr_up_id = $up_id");
					ranklist::update();
					
					// sett spilleren som den aktive spilleren for brukerne
					ess::$b->db->query("UPDATE users SET u_active_up_id = $up_id WHERE u_id = ".login::$user->id);
					
					ess::$b->db->commit();
					
					// hent antall medlemmer
					$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_access_level < ".ess::$g['access_noplay']." AND up_access_level != 0");
					putlog("INFO", "%bNY SPILLER:%b (#$up_id - Nummer %b".mysql_result($result, 0)."%b) %u$name%u registrerte seg! ".ess::$s['path']."/p/".rawurlencode($name));
					
					ess::$b->page->add_message("Du har opprettet en ny spiller med navnet <b>".htmlspecialchars($name)."</b>!");
					redirect::handle("min_side");
				}
				
				echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Bekreft ny spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">
		<form action="" method="post">'.($bydel ? '
			<input type="hidden" name="bydel" value="'.$bydel['id'].'" />' : '').'
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="name" value="'.htmlspecialchars($name).'" />
			<p>Du er i ferd med å opprette følgende spiller:</p>
			<dl class="dd_right">
				<dt>Spillernavn</dt>
				<dd><b>'.htmlspecialchars($name).'</b></dd>
				<dt>Bydel</dt>
				<dd>'.($bydel ? htmlspecialchars(game::$bydeler[$bydel]['name']) : 'Tilfeldig valgt').'</dd>
			</dl> 
			<p>Du kan ikke bytte dette spillernavnet senere uten og opprette en ny spiller.</p>
			<p class="c">'.show_sbutton("Opprett spiller", 'name="confirm"').' '.show_sbutton("Avbryt", 'name="abort"').'</p>
		</form>
	</div>
</div>';
				
				ess::$b->page->load();
			}
		}
		
		ess::$b->page->add_css('
#name_validate { color: #AAA }
#name_validate_ok { color: #CCFF00 }
#name_validate_loading img { vertical-align: text-bottom; margin: -2px 0 }
#name_validate_taken { color: #900000 }
');
		ess::$b->page->add_js_domready('
	var status = function(val)
	{
		if (val == "") $("name_validate").removeClass("hide"); else $("name_validate").addClass("hide");
		if (val == "taken") $("name_validate_taken").removeClass("hide"); else $("name_validate_taken").addClass("hide");
		if (val == "ok") $("name_validate_ok").removeClass("hide"); else $("name_validate_ok").addClass("hide");
		if (val == "loading") $("name_validate_loading").removeClass("hide"); else $("name_validate_loading").addClass("hide");
	};
	var change_last = null, change_timer;
	var change = function()
	{
		if (this.get("value") == change_last) return; change_last = this.get("value");
		if (this.get("value") == "") { status(""); return; }
		
		$clear(change_timer);
		change_timer = this.search.delay(500, this, true);
		
		status("loading");
	};
	
	$("name_enter").addEvents({
		"keyup": change,
		"change": change
	}).focus();
	var xhr;
	$("name_enter").search = function()
	{
		if (!xhr)
		{
			xhr = new Request({"url": relative_path + "/ajax/find_user"});
			xhr.addEvent("success", function(text, xml)
			{
				if (xmlGetValue(xml, "user"))
					status("taken");
				else
					status("ok");
			});
			xhr.addEvent("failure", function(x)
			{
				alert("En feil oppsto.");
			});
		}
		xhr.send({"data": {"q": $("name_enter").get("value").trim(), "is": true}});
	};
	if ($("name_enter").get("value") != "") $("name_enter").search.run(null, $("name_enter"));
	else status("");
	change_last = $("name_enter").get("value");');
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Ny spiller<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_left"><a href="'.htmlspecialchars($this->url).'">Tilbake</a></p>
	<div class="bg1">
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<p>Du er nå i ferd med å opprette en ny spiller. Du kan også se informasjon for din tidligere spiller <a href="min_side?up_id='.login::$user->player->id.'">'.htmlspecialchars(login::$user->player->data['up_name']).'</a>.</p>
			<p>Du kan ikke bytte spillernavnet du velger å opprette her uten og opprette en ny spiller.</p>
			<dl class="dd_right" style="overflow: hidden">
				<dt>Nytt spillernavn</dt>
				<dd><input type="text" id="name_enter" name="name" class="styled w120" value="'.htmlspecialchars(postval("name")).'" /></dd>
				<dt>Status:
					<span class="name_v hide" id="name_validate_loading"><img src="'.STATIC_LINK.'/other/loading-black.gif" /></span>
					<span class="name_v hide" id="name_validate">Skriv inn ønsket navn</span>
					<span class="name_v hide" id="name_validate_ok">Ledig</span>
					<span class="name_v hide" id="name_validate_taken">Opptatt</span>
				</dt>
			</dl>
			<dl class="dd_right">
				<dt>Bydel</dt>
				<dd>
					<select name="bydel">';
		
		$active = postval("bydel");
		if (!isset(game::$bydeler[$active]) || !game::$bydeler[$active]['active']) $active = false;
		
		echo '
						<option'.(!$active ? ' selected="selected"' : '').'>Velg tilfeldig</option>';
		
		foreach (game::$bydeler as $bydel)
		{
			if (!$bydel['active']) continue;
			echo '
						<option value="'.$bydel['id'].'"'.($active == $bydel['id'] ? ' selected="selected"' : '').'>'.htmlspecialchars($bydel['name']).'</option>';
		}
		
		echo '
					</select>
				</dd>
			</dl>
			<p class="c">'.show_sbutton("Fortsett").'</p>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Behandle nytt passord
	 */
	protected function solve_pass()
	{
		// lagre passord
		if (isset($_POST['save_pass']))
		{
			// kontroller alle feltene
			$pass_new = trim(postval("pass_new"));
			$pass_repeat = trim(postval("pass_repeat"));
			
			// kontroller at alle feltene er fylt ut
			if ($pass_new == "" || $pass_repeat == "")
			{
				ess::$b->page->add_message("Alle feltene må fylles ut.", "error");
			}
			
			// kontroller nytt passord og repeat
			elseif ($pass_new != $pass_repeat)
			{
				ess::$b->page->add_message("De nye passordene var ikke like.", "error");
			}
			
			// kontroller krav (minst 6 tegn)
			elseif (strlen($pass_new) < 6)
			{
				ess::$b->page->add_message("Det nye passordet må inneholde minimum 6 tegn.", "error");
			}
			
			// for enkelt passord?
			elseif (password::validate($pass_new, password::LEVEL_LOGIN) != 0)
			{
				ess::$b->page->add_message("Du må velge et vanskeligere passord.", "error");
			}
			
			// samme passord som i banken?
			elseif (password::verify_hash($pass_new, login::$user->data['u_bank_auth'], 'bank_auth'))
			{
				ess::$b->page->add_message("Velg et annet passord enn du har i banken.");
			}
			
			// lagre passordet
			else
			{
				ess::$b->db->query("UPDATE users SET u_pass = ".ess::$b->db->quote(password::hash($pass_new, null, 'user')).", u_pass_change = NULL WHERE u_id = ".login::$user->id);
				
				// melding
				ess::$b->page->add_message("Du har nå lagret et nytt passord for brukeren din.");
				putlog("NOTICE", "%bPASSORD%b: %u".login::$user->player->data['up_name']."%u lagret nytt passord på sin bruker (var nullstilt). ".ess::$s['path']."/min_side?u_id=".login::$user->id);
				
				// send ut e-post for å informere
				$email = new email();
				$email->text = 'Hei,

Det er nå blitt opprettet et nytt passord fra '.$_SERVER['REMOTE_ADDR'].' ('.$_SERVER['HTTP_USER_AGENT'].').

Bruker ID: '.login::$user->data['u_id'].'
E-post: '.login::$user->data['u_email'].'

Vi sender selvfølgelig ikke ditt nye passord på e-post. Det skal du kunne selv!

--
www.kofradia.no';
				$email->send(login::$user->data['u_email'], "Nytt passord");
				
				// logg ut alle andre brukere
				ess::$b->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_active = 1 AND ses_u_id = ".login::$user->id." AND ses_id != ".login::$info['ses_id']);
				
				redirect::handle();
			}
		}
		
		ess::$b->page->add_js_domready('$("lockpass").focus();');
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Lagre nytt passord<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.htmlspecialchars($this->url).'">Tilbake</a></p>
	<div class="bg1">
		<p>Ditt passord har blitt nullstilt. Du vil ikke kunne logge inn uten å måtte benytte <i>glemt passord</i> funksjonen før du har opprettet et nytt passord.</p>
		<form action="" method="post" autocomplete="off">
			<dl class="dd_right dl_2x center" style="width: 80%">
				<dt>Nytt passord</dt>
				<dd><input type="password" class="styled w100" name="pass_new" id="lockpass" /></dd>
				<dt>Gjenta nytt passord</dt>
				<dd><input type="password" class="styled w100" name="pass_repeat" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Lagre passordet", 'name="save_pass"').'</p>
		</form>
	</div>
</div>';
	}
}