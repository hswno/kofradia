<?php

define("FORCE_HTTPS", true);
define("ALLOW_GUEST", true);
require "base.php";
global $__server;

ess::$b->page->theme_file = "guest_simple";

// logget inn og har vervelenke?
if (login::$logged_in && isset($_GET['r']))
{
	// finn spilleren og redirect dit om mulig
	if ($player = player::get($_GET['r']))
	{
		return $player->redirect_to();
	}
}


access::no_user();

ess::$b->page->add_css('
.registrer_felt {
	background: rgb(30, 30, 30);
	background: rgba(150, 150, 150, 0.05);
	border: 7px solid rgb(25, 25, 25);
	border: 7px solid rgba(31, 31, 31, 0.7);
	width: 300px;
	margin: 1em auto;
	padding: 1px 1em;
}
/*.registrer_felt input.styled {
	background: #111;
	color: #FFF;
	border: 1px solid #1F1F1F;
	padding: 1px;
	font-size: 12px;
}
.registrer_felt select {
	background: #111;
}*/
.registrer_felt dt, .registrer_felt dd { margin-bottom: 7px }
');

if (isset($_GET['r']))
{
	// sjekk om spilleren finnes
	$result = \Kofradia\DB::get()->query("SELECT up_id FROM users_players WHERE up_id = ".\Kofradia\DB::quote($_GET['r']));
	
	if ($row = $result->fetch())
	{
		// sett cookie - gyldig i 2 uker
		setcookie($__server['cookie_prefix'] . "rid", "{$row['up_id']}", time()+1209600, $__server['cookie_path'], $__server['cookie_domain']);
		
		#ess::$b->page->add_message("Du må være registrert for å vise denne siden.", "error");
	}
	redirect::handle("/registrer", redirect::ROOT);
}

ess::$b->page->add_title("Register ny spiller");

// css
ess::$b->page->add_css('.registrer_felt input, .registrer_felt select, .registrer_felt textarea { border: 1px solid #292929; margin-top: -3px }
.registrer_felt span {
	float: left;
	width: 120px;
}
.indent {
	margin-left: 120px;
}');

$registrer = new registrer();
$registrer->restore();

// registrer!
class registrer
{
	public $step = 1;
	public $id = false;
	public $info = false;
	
	function registrer()
	{
		$this->clean();
	}
	
	// slett gamle oppføringer
	function clean()
	{
		return \Kofradia\DB::get()->exec("DELETE FROM registration WHERE expire < ".time());
	}
	
	// gjennoppta riktig trinn
	function restore()
	{
		// er vi på noe trinn nå?
		if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'reg']['step']))
		{
			$this->step = $_SESSION[$GLOBALS['__server']['session_prefix'].'reg']['step'];
		}
		
		// hent info fra alle trinnene
		
		// trinn 3 og oppover
		if ($this->step >= 3)
		{
			// hent info
			$this->id = intval($_SESSION[$GLOBALS['__server']['session_prefix'].'reg']['id']);
			$result = \Kofradia\DB::get()->query("SELECT id, time, email, code, ip, expire, user, birth, pass, referer FROM registration WHERE id = {$this->id}");
			
			// finnes ikke?
			if ($result->rowCount() == 0)
			{
				ess::$b->page->add_message("Fant ikke oppføringen. Prøv på nytt.", "error");
				$this->trash();
				redirect::handle();
			}
			
			// lagre info
			$this->info = $result->fetch();
			
			// ønsker å avbryte?
			if (isset($_POST['abort']))
			{
				ess::$b->page->add_message("Din registrering ble avbrytet!");
				$this->trash();
				redirect::handle();
			}
			
			// oppdater expire
			$this->info['expire'] = time()+900;
			\Kofradia\DB::get()->exec("UPDATE registration SET expire = {$this->info['expire']} WHERE id = {$this->id}");
		}
		
		// hent riktig trinn
		switch ($this->step)
		{
			case 4:
			$this->step4();
			break;
			
			case 3:
			$this->step3();
			break;
			
			default:
			$this->step1_2();
		}
	}
	
	// nullstill info
	function trash()
	{
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'reg']);
	}
	
	// oversikt over trinnene:
	// 1 - be om e-post
	// 2 - godkjenn e-postkode
	// 3 - spillernavn, passord, referer
	// 4 - godkjenn betingelser etc
	
	// 1 - be om e-post ELLER 2 - godkjenn e-postkode
	function step1_2()
	{
		global $__server;
		
		// er skjemaet sendt inn?
		if ($_SERVER['REQUEST_METHOD'] == "POST")
		{
			// sjekk for gyldig trinn
			if (!isset($_POST['step']) || ($_POST['step'] != 1 && $_POST['step'] != 2))
			{
				redirect::handle();
			}
			
			$step = $_POST['step'];
			
			// trin 1
			if ($step == 1)
			{
				// epost1, epost2, b_dag, b_maaned, b_aar, forste_bruker
				$epost1 = trim(postval("epost1"));
				$epost2 = trim(postval("epost2"));
				$b_dag = intval(postval("b_dag"));
				$b_maaned = intval(postval("b_maaned"));
				$b_aar = intval(postval("b_aar"));
				$forste_bruker = isset($_POST['forste_bruker']);
				
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
				
				// sjekk e-post
				$email_valid = game::validemail($epost1);
				
				// kontroller om e-postadressen eller domenet er blokkert
				if ($email_valid)
				{
					$pos = mb_strpos($epost1, "@");
					$domain = mb_strtolower(mb_substr($epost1, $pos + 1));
					
					$result = \Kofradia\DB::get()->query("SELECT eb_id, eb_type FROM email_blacklist WHERE (eb_type = 'address' AND eb_value = ".\Kofradia\DB::quote($epost1).") OR (eb_type = 'domain' AND eb_value = ".\Kofradia\DB::quote($domain).") ORDER BY eb_type = 'address' LIMIT 1");
					$error_email = $result->fetch();
				}
				
				// sjekk e-post (1)
				if (!$email_valid)
				{
					ess::$b->page->add_message("Ugyldig e-postadresse.", "error");
				}
				
				// blokkert e-postadresse?
				elseif ($error_email)
				{
					if ($error_email['eb_type'] == "address")
					{
						ess::$b->page->add_message("E-postadressen <b>".htmlspecialchars($epost1)."</b> er blokkert for registrering.", "error");
					}
					else
					{
						ess::$b->page->add_message("Domenet <b>".htmlspecialchars($domain)."</b> er blokkert for registrering og kan ikke benyttes.", "error");
					}
				}
				
				// sjekk e-post (2)
				elseif ($epost1 != $epost2)
				{
					ess::$b->page->add_message("Den gjentatte e-postadressen var ikke lik den første.", "error");
				}
				
				// sjekk fødselsdato
				elseif ($b_dag < 1 || $b_dag > 31)
				{
					ess::$b->page->add_message("Du må velge en gyldig dag.", "error");
				}
				elseif ($b_maaned < 1 || $b_maaned > 12)
				{
					ess::$b->page->add_message("Du må velge en gyldig måned.", "error");
				}
				elseif ($b_aar < 1900 || $b_aar > ess::$b->date->get()->format("Y"))
				{
					ess::$b->page->add_message("Du må velge et gyldig år.", "error");
				}
				
				// ugyldig fødselsdato?
				elseif (!$birth_valid)
				{
					ess::$b->page->add_message("Datoen du fylte inn for fødselsdatoen din eksisterer ikke.");
				}
				
				// sjekk alder
				elseif ($age < 13)
				{
					putlog("ABUSE", "%c9%bUNDER ALDERSGRENSEN:%b%c %u{$_SERVER['REMOTE_ADDR']}%u prøvde å registrere seg med fødselsdato %u{$birth}%u (%u{$age}%u år) og e-posten %u{$epost1}%u!");
					
					ess::$b->page->add_message("Du må ha fylt 13 år for å registrere deg og spille Kofradia!", "error");
					
					redirect::handle("", redirect::ROOT);
				}
				
				// sjekk eneste bruker
				elseif (!$forste_bruker)
				{
					ess::$b->page->add_message("I følge betingelsene kan du kun ha en bruker. Bruk den!", "error");
					redirect::handle("", redirect::ROOT);
				}
				
				// fortsett
				else
				{
					// hent DB info
					$result1 = \Kofradia\DB::get()->query("SELECT id, time, expire FROM registration WHERE email = ".\Kofradia\DB::quote($epost1));
					$result2 = \Kofradia\DB::get()->query("SELECT u_id FROM users WHERE u_email = ".\Kofradia\DB::quote($epost1)." AND u_access_level != 0");
					
					// e-post allerede i registreringssystemet?
					if ($row = $result1->fetch())
					{
						$time = game::timespan($row['expire'], game::TIME_ABS | game::TIME_FULL);
						ess::$b->page->add_message("E-postadressen er allerede aktivt i registeringssystemet. Sjekk e-posten for e-postkode eller vent $time, for så å prøve igjen.", "error");
					}
					
					// allerede en spiller som har e-posten?
					elseif ($row = $result2->fetch())
					{
						putlog("ABUSE", "%c9%bREGISTRER KONTO:%b%c %u{$_SERVER['REMOTE_ADDR']}%u prøvde å registrere seg en e-post som allerede finnes: %u{$epost1}%u!");
						ess::$b->page->add_message("Denne e-posten er allerede i bruk.", "error");
						redirect::handle("", redirect::ROOT);
					}
					
					// legg til
					else
					{
						// sett opp kode
						$code = mb_substr(md5(uniqid("kofradia_")), 0, 16);
						
						// legg til i databasen
						\Kofradia\DB::get()->exec("INSERT INTO registration SET time = ".time().", birth = '$birth', email = ".\Kofradia\DB::quote($epost1).", code = '$code', ip = '{$_SERVER['REMOTE_ADDR']}', expire = ".(time()+7200));
						
						// send e-post
						$email = new email();
						$email->text = 'Hei,

Du har begynt registrering av bruker på Kofradia.
Dersom du ikke har bedt om denne e-posten kan du se bort ifra den.

For å bekrefte e-postadressen din må du følge denne lenken:
'.$__server['path'].'/registrer?e='.$code.'

Din verifiseringskode er: '.$code.'

Forespørselen ble utført fra '.$_SERVER['REMOTE_ADDR'].'.

Du må fortsette innen '.game::timespan(7200, game::TIME_FULL | game::TIME_NOBOLD).' ('.ess::$b->date->get(time()+7200)->format(date::FORMAT_SEC).'). Etter den tid må du be om ny e-post.

--
www.kofradia.no';
						$email->headers['X-SMafia-IP'] = $_SERVER['REMOTE_ADDR'];
						$email->headers['Reply-To'] = "henvendelse@smafia.no";
						$email->send($epost1, "Starte registrering på Kofradia");
						
						ess::$b->page->add_message("En e-post med verifiseringskode har blitt sendt til <b>".htmlspecialchars($epost1)."</b>. Sjekk e-posten snarest!");
						redirect::handle("?e");
					}
				}
			}
			
			
			// trinn 2
			else
			{
				// e
				$ecode = trim(postval("e"));
				
				// sjekk e-postkode
				if (empty($ecode))
				{
					ess::$b->page->add_message("Du må fylle ut e-postkoden du har fått på epost.", "error");
				}
				
				// fortsett
				else
				{
					// sjekk om den finnes
					$result = \Kofradia\DB::get()->query("SELECT id, time, email, code, ip, expire, user FROM registration WHERE code = ".\Kofradia\DB::quote($ecode));
					
					if (!($row = $result->fetch()))
					{
						putlog("ABUSE", "%c9%bE-POST KODE:%b%c %u{$_SERVER['REMOTE_ADDR']}%u prøvde å fortsette registreringen med ugyldig e-postkode (%u$ecode%u)!");
						ess::$b->page->add_message("Fant ikke e-postkoden i databasen! Kontroller at den er riktig og evt. be om ny e-postkode.", "error");
					}
					
					// fant oppføringen
					else
					{
						// oppdater oppføringen
						\Kofradia\DB::get()->exec("UPDATE registration SET verified = 1 WHERE id = {$row['id']}");
						$_SESSION[$GLOBALS['__server']['session_prefix'].'reg'] = array(
							"id" => $row['id'],
							"step" => 3
						);
						
						redirect::handle();
					}
				}
			}
		}
		
		echo '
<p><b>Velkommen</b> til Kofradia! Kampen om broderskapet tar sted i Drammen hvor folket har tatt opp kampen for å etablere seg. Her handler det ikke bare om å ha mest penger eller være høyest rank, men å stå samlet for å etablere det sterkeste og mektigste broderskapet. Kanskje kan du være med å gjøre en forskjell? Lykke til!</p>
<p>På denne siden oppretter du din bruker og spiller. Merk! Det er kun lov å ha én bruker. Har du allerede en bruker fra før har du ikke lov til å registrere ny bruker.</p>';
		
		if (!isset($_REQUEST['e']))
		{
			ess::$b->page->add_js_domready('$("epost1").focus();');
			echo '
<form action="registrer" method="post">
	<input type="hidden" name="step" value="1" />
	<div class="registrer_felt">
		<boxes />
		<dl class="dd_right dl_2x">
			<dt>E-postadresse</dt>
			<dd><input type="text" id="epost1" name="epost1" value="'.htmlspecialchars(postval("epost1")).'" class="styled w150" /></dd>
			
			<dt>Gjenta e-postadresse</dt>
			<dd><input type="text" name="epost2" value="'.htmlspecialchars(postval("epost2")).'" class="styled w150" /></dd>
			
			<dt>Fødselsdato</dt>
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
				<select name="b_maaned">
					<option value="">Måned</option>';
			
			global $_lang;
			$active = postval("b_maaned");
			for ($i = 1; $i <= 12; $i++)
			{
				echo '
					<option value="'.$i.'"'.($i == $active ? ' selected="selected"' : '').'>'.ucfirst($_lang['months'][$i]).'</option>';
			}
			
			echo '
				</select>
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
			
			<dd><input type="checkbox" name="forste_bruker" id="c1" /><label for="c1"> Jeg har ingen aktiv bruker fra før</label></dd>
		</dl>
		<p class="c">'.show_sbutton("Gå til neste trinn").'</p>
	</div>
</form>';
		}
		
		else
		{
			ess::$b->page->add_js_domready('$("verife").focus();');
			echo '
<p><b>Verifiseringskode</b></p>
<p>Når du har mottatt e-post etter å ha fylt inn e-postadresse og fødelsdato, mottar du en verifiseringskode som fylles inn her. Du kan deretter fortsette din registrering.</p>
<form action="registrer" method="post">
	<input type="hidden" name="step" value="2" />
	<div class="registrer_felt">
		<boxes />
		<dl class="dd_right">
			<dt>Verifiseringskode</dt>
			<dd><input type="text" id="verife" name="e" value="'.htmlspecialchars(requestval("e")).'" maxlength="32" class="styled w120" /></dd>
		</dl>
		<p class="c">'.show_sbutton("Valider", 'class="indent"').'</p>
	</div>
	<p><a href="registrer">Tilbake</a></p>
</form>';
		}
	}
	
	// 3 - spillernavn, passord, referer
	function step3()
	{
		$referers = array(
			1 => array("Via google eller en annen søkeside", false),
			array("En venn tipset meg", false),
			array("Leste det på en nettside", "Lenke til nettsiden"),
			array("Så en reklameannonse", "Lenke til nettsiden"),
			array("Så det i et forum", "Lenke til forumet"),
			array("Annet", "Spesifiser")
		);
		
		// er skjemaet sendt inn?
		if ($_SERVER['REQUEST_METHOD'] == "POST")
		{
			// sjekk for gyldig trinn
			if (!isset($_POST['step']) || $_POST['step'] != 3)
			{
				redirect::handle();
			}
			
			// spillernavn, passord1, passord2, referer1, referer2
			$brukernavn = postval("brukernavn");
			$passord1 = postval("passord1");
			$passord2 = postval("passord2");
			$referer1 = postval("referer1");
			$referer2 = trim(postval("referer2"));
			
			// diverse spørringer
			$result1 = \Kofradia\DB::get()->query("SELECT ".\Kofradia\DB::quoteNoNull($brukernavn)." REGEXP regex AS m, error FROM regex_checks WHERE (type = 'reg_user_special' OR type = 'reg_user_strength') HAVING m = 1");
			$result2 = \Kofradia\DB::get()->query("SELECT up_id FROM users_players WHERE up_name = ".\Kofradia\DB::quote($brukernavn));
			$result3 = \Kofradia\DB::get()->query("SELECT id FROM registration WHERE user = ".\Kofradia\DB::quote($brukernavn));
			$result4 = \Kofradia\DB::get()->query("SELECT ".\Kofradia\DB::quoteNoNull($passord1)." REGEXP regex AS m, error FROM regex_checks WHERE type = 'reg_pass' HAVING m = 1");
			
			// sjekk spillernavn
			if ($result1->rowCount() > 0)
			{
				$feil = array();
				while ($row = $result1->fetch()) $feil[] = '<li>'.htmlspecialchars($row['error']).'</li>';
				ess::$b->page->add_message("Spillernavnet var ikke gyldig:<ul>".implode("", $feil)."</ul>", "error");
			}
			elseif ($result2->rowCount() > 0)
			{
				ess::$b->page->add_message("Spillernavnet er allerede tatt! Velg et annet.", "error");
			}
			elseif ($result3->rowCount() > 0)
			{
				ess::$b->page->add_message("Noen holder allerede på å registrere seg med dette spillernavnet. Velg et annet.", "error");
			}
			
			// sjekk passord
			elseif ($result4->rowCount() > 0)
			{
				$feil = array();
				while ($row = $result4->fetch()) $feil[] = '<li>'.htmlspecialchars($row['error']).'</li>';
				ess::$b->page->add_message("Passordet var ikke gyldig:<ul>".implode("", $feil)."</ul>", "error");
			}
			elseif ($passord1 == $brukernavn)
			{
				ess::$b->page->add_message("Passordet kan ikke være det samme som spillernavnet.", "error");
			}
			elseif ($passord1 != $passord2)
			{
				ess::$b->page->add_message("Passordene var ikke like med hverandre.", "error");
			}
			
			// sjekk referer
			elseif (!isset($referers[$referer1]))
			{
				ess::$b->page->add_message("Velg et gyldig alternativ for hvor du hørte om Kofradia.", "error");
			}
			elseif ($referers[$referer1][1] && empty($referer2))
			{
				ess::$b->page->add_message("Fyll ut feltet for mer informasjon for hvor du hørte om Kofradia.", "error");
			}
			
			// fortsett
			else
			{
				$referer = $referers[$referer1][0]."|".$referer2;
				
				// oppdater databasen
				\Kofradia\DB::get()->exec("UPDATE registration SET user = ".\Kofradia\DB::quote($brukernavn).", referer = ".\Kofradia\DB::quote($referer).", pass = ".\Kofradia\DB::quote(password::hash($passord1, null, 'user'))." WHERE id = {$this->id}");
				
				$_SESSION[$GLOBALS['__server']['session_prefix'].'reg']['step'] = 4;
				
				redirect::handle();
			}
		}
		
		$refs = array();
		foreach ($referers as $ref)
		{
			if ($ref[1]) $refs[] = "'".addslashes($ref[1])."'";
			else $refs[] = "false";
		}
		ess::$b->page->add_js('var referers = [false,'.implode(",", $refs).'];
function checkReferer(elm)
{
	var index = elm.selectedIndex + (elm.options[0].value == "" ? 0 : 1);
	var ref = referers[index];
	var elms = $$(".referer2p");
	var text = $("referer2i");
	if (ref)
	{
		text.innerHTML = ref;
		elms.each(function(elm){elm.setStyle("display", "");});
	}
	else
	{
		elms.each(function(elm){elm.setStyle("display", "none");});
	}
}');
		
		echo '
<form action="registrer" method="post">
	<input type="hidden" name="abort" />
	<h1>Brukerinformasjon</h1>
	<p class="h_right">'.show_sbutton("Avbryt registrering", 'onclick="return confirm(\'Er du sikker på at du vil AVBRYTE?\')"').'</p>
</form>
<p>Det er nå tid for å velge spillernavn og passord. Spillernavnet vil du ikke kunne endre senere, mens passordet kan endres når du ønsker og nullstilles via e-post.</p>
<p>Tips: Trykk &laquo;Gå videre&raquo; for å sjekke om spillernavnet er ledig før du fyller inn passordet for å slippe å fylle inn passordet hver gang.</p>
<boxes />
<form action="registrer" method="post">
	<input type="hidden" name="step" value="3" />
	<dl class="dl_30">
		<dt>Ønsket spillernavn</dt>
		<dd><input type="text" name="brukernavn" value="'.htmlspecialchars(postval("brukernavn")).'" class="styled w120" /></dd>
		<dt>Passord</dt>
		<dd><input type="password" name="passord1" class="styled w120" /></dd>
		<dt>Gjenta passord</dt>
		<dd><input type="password" name="passord2" class="styled w120" /></dd>
		<dt>Hvor hørte du om Kofradia?</dt>
		<dd>
			<select name="referer1" id="referer_select" onchange="checkReferer(this)">';
		
		$selected = postval("referer1", false);
		if (!isset($referers[$selected]))
		{
			echo '
				<option value="">Velg</option>';
		}
		foreach ($referers as $id => $referer)
		{
			echo '
				<option value="'.$id.'"'.($selected == $id ? ' selected="selected"' : '').'>'.$referer[0].'</option>';
		}
		
		echo '
			</select>
		</dd>
		<dt class="referer2p" id="referer2i">Spesifiser</dt>
		<dd class="referer2p"><input type="text" name="referer2" value="'.htmlspecialchars(postval("referer2")).'" class="styled w250" /></dd>
		<dd>'.show_sbutton("Gå videre").'</dd>
	</dl>
</form>';
		
		ess::$b->page->add_body_post('<script type="text/javascript">checkReferer($("referer_select"));</script>');
	}
	
	// 4 - godkjenn betingelser etc
	function step4()
	{
		global $__server, $_game;
		
		// er skjemaet sendt inn?
		if ($_SERVER['REQUEST_METHOD'] == "POST")
		{
			// sjekk for gyldig trinn
			if (!isset($_POST['step']) || $_POST['step'] != 4)
			{
				redirect::handle();
			}
			
			// betingelser, alder, forste_bruker
			$betingelser = isset($_POST['betingelser']);
			$alder = isset($_POST['alder']);
			$forste_bruker = isset($_POST['forste_bruker']);
			
			// er ikke betingelsene godtatt?
			if (!$betingelser)
			{
				ess::$b->page->add_message("Hvis du ikke godtar betingelsene kan du dessverre ikke registrere deg her.", "error");
			}
			
			// er ikke alderen bekreftet?
			elseif (!$alder)
			{
				ess::$b->page->add_message("Hvis du ikke har fylt 13 år kan du dessverre ikke registrere deg her.", "error");
			}
			
			// sjekk for første bruker
			elseif (!$forste_bruker)
			{
				ess::$b->page->add_message("Hvis du allerede har en bruker fra før så bruk den! Å opprette ny konto gjør det bare dumt for deg selv og kan i værste tilfelle føre til politianmeldelse.", "error");
			}
			
			else
			{
				// finn en tilfeldig bydel
				$result = \Kofradia\DB::get()->query("SELECT id FROM bydeler WHERE active = 1 ORDER BY RAND()");
				$bydel = $result->fetchColumn(0);
				
				// sett opp nødvendig info
				$user = \Kofradia\DB::quote($this->info['user']);
				$pass = \Kofradia\DB::quote($this->info['pass']);
				$email = \Kofradia\DB::quote($this->info['email']);
				$referer = \Kofradia\DB::quote($this->info['referer']);
				$tos_version = intval(game::$settings['tos_version']['value']);
				$birth = \Kofradia\DB::quote($this->info['birth']);
				$recruiter = array(
					"up_id" => 'NULL',
					"up_u_id" => 'NULL'
				);
				
				global $__server;
				
				// er denne brukeren vervet?
				if (isset($_COOKIE[$__server['cookie_prefix'] . "rid"]))
				{
					$rid = $_COOKIE[$__server['cookie_prefix'] . "rid"];
					
					// finnes denne brukeren?
					$result = \Kofradia\DB::get()->query("SELECT up_id, up_u_id FROM users_players WHERE up_id = ".\Kofradia\DB::quote($rid));
					
					if ($row = $result->fetch())
					{
						$recruiter = $row;
					}
				}
				
				\Kofradia\DB::get()->beginTransaction();
				
				// deaktiver kontroll av foreign key
				\Kofradia\DB::get()->exec("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0");
				
				// opprett bruker
				$time = time();
				\Kofradia\DB::get()->exec("INSERT INTO users SET u_email = $email, u_pass = $pass, u_birth = $birth, u_tos_version = $tos_version, u_created_time = $time, u_created_ip = ".\Kofradia\DB::quote($_SERVER['REMOTE_ADDR']).", u_created_referer = $referer, u_recruiter_u_id = {$recruiter['up_u_id']}, u_recruiter_points_last = 0");
				$u_id = \Kofradia\DB::get()->lastInsertId();
				
				// opprett spiller og tilknytt brukeren
				\Kofradia\DB::get()->exec("INSERT INTO users_players SET up_u_id = $u_id, up_name = $user, up_created_time = $time, up_recruiter_up_id = {$recruiter['up_id']}, up_b_id = $bydel");
				$up_id = \Kofradia\DB::get()->lastInsertId();
				\Kofradia\DB::get()->exec("UPDATE users SET u_active_up_id = $up_id WHERE u_id = $u_id");
				
				// aktiver kontroll av foreign key
				\Kofradia\DB::get()->exec("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS");
				
				// sett opp riktig rank plassering
				#\Kofradia\DB::get()->exec("UPDATE users_players AS main, (SELECT COUNT(users_players.up_id)+1 AS pos, ref.up_id FROM users_players AS ref LEFT JOIN users_players ON users_players.up_points > ref.up_points AND users_players.up_access_level < {$_game['access_noplay']} AND users_players.up_access_level != 0 WHERE ref.up_id = $up_id GROUP BY ref.up_id) AS rp SET main.up_rank_pos = rp.pos WHERE main.up_id = rp.up_id");
				\Kofradia\DB::get()->exec("INSERT INTO users_players_rank SET upr_up_id = $up_id");
				ranklist::update();
				
				// slett registrasjonsoppføringen
				\Kofradia\DB::get()->exec("DELETE FROM registration WHERE id = {$this->id}");
				
				\Kofradia\DB::get()->commit();
				
				// send e-post
				$email = new email();
				$email->text = 'Hei,

Du har registrert deg som '.$this->info['user'].' på Kofradia.

Velkommen til spillet!

--
www.kofradia.no';
				$email->headers['X-SMafia-IP'] = $_SERVER['REMOTE_ADDR'];
				$email->headers['Reply-To'] = "henvendelse@smafia.no";
				$email->send($this->info['email'], "Velkommen til Kofradia");
				
				ess::$b->page->add_message('Velkommen til Kofradia!<br /><br />Du er nå registrert som <b>'.$this->info['user'].'</b> og automatisk logget inn.<br /><br />Sjekk ut menyen til venstre så ser du hva vi har å tilby i dag.<br /><br />Hvis du har noen spørsmål ta en titt under <a href="'.ess::$s['relative_path'].'/node">hjelp</a> og ta evt. kontakt med <a href="support/">support</a> om du ikke finner svar på det du lurer på!<br /><br />Ikke glem og les gjennom <a href="'.ess::$s['relative_path'].'/node/6">reglene for forumene</a> før du skriver i forumet. Lykke til i spillet!');
				
				// hent antall medlemmer
				$result = \Kofradia\DB::get()->query("SELECT COUNT(up_id) FROM users_players WHERE up_access_level < {$_game['access_noplay']} AND up_access_level != 0");
				putlog("INFO", "%bNY SPILLER:%b (#$up_id - Nummer %b".$result->fetchColumn(0)."%b) %u{$this->info['user']}%u registrerte seg! {$__server['absolute_path']}{$__server['relative_path']}/p/".rawurlencode($this->info['user']));
				
				// logg inn brukeren
				login::do_login($u_id, $this->info['pass'], LOGIN_TYPE_TIMEOUT, false);
				
				// slett registrasjonsoppføringen fra session etc
				$this->trash();
				
				// sjekk om det er mulig multi
				$result = \Kofradia\DB::get()->query("
					SELECT up_name
					FROM users_players, users
					WHERE u_online_ip = ".\Kofradia\DB::quote($_SERVER['REMOTE_ADDR'])."
						AND u_id != $u_id
						AND u_active_up_id = up_id AND up_access_level != 0
						AND up_last_online > ".(time()-86400*30)."
					LIMIT 10");
				if ($result->rowCount() > 0)
				{
					$names = array();
					while ($row = $result->fetch()) $names[] = $row['up_name'];
					
					putlog("CREWCHAN", "%b%c4NY REGISTERT, MULIG MULTI:%c%b (#$up_id) %u{$this->info['user']}%u registrerte seg. Andre spillere på IP-en: ".implode(", ", $names).". {$__server['path']}/admin/brukere/finn?ip=".rawurlencode($_SERVER['REMOTE_ADDR']));
				}
				
				// videresend til hovedsiden
				redirect::handle("", redirect::ROOT);
			}
		}
		
		echo '
<form action="registrer" method="post">
	<input type="hidden" name="abort" />
	<h1>Bekreftelse av betingelsene</h1>
	<p class="h_right">'.show_sbutton("Avbryt registrering", 'onclick="return confirm(\'Er du sikker på at du vil AVBRYTE?\')"').'</p>
</form>
<p>
	På denne siden finner du en oversikt over betingelsene. Betingelsene er ikke lange og er kjapt å lese igjennom. Det er viktig at du er klar over innholdet i disse betingelsene og at du følger dem. Følger du ikke disse betingelsene vil brukeren din bli deaktivert. For å kunne opprette må du godta betingelsene.
</p>
<boxes />
<form action="registrer" method="post">
	<input type="hidden" name="step" value="4" />
	<p>
		Betingelser:<br />
		<div id="betingelser_content">'.game::$settings['tos']['value'].'</div>
	</p>
	<p>
		<input type="checkbox" name="betingelser" id="betingelser" /><label for="betingelser"> Jeg har lest gjennom og aksepterer betingelsene</label>
	</p>
	<p>
		<input type="checkbox" name="alder" id="alder" /><label for="alder"> Jeg har fylt 13 år</label>
	</p>
	<p>
		<input type="checkbox" name="forste_bruker" id="forste_bruker" /><label for="forste_bruker"> Jeg har ingen bruker som er aktivert fra før av</label> <span class="dark">(Hvis du allerede har en bruker, må du deaktivere den <u>før</u> du registrerer deg på nytt.)</span>
	</p>
	<p>
		'.show_sbutton("Opprett bruker").'
	</p>
</form>
<form action="registrer" method="post">
	<input type="hidden" name="abort" />
	<p>
		'.show_sbutton("Avbryt registrering", 'onclick="return confirm(\'Er du sikker på at du vil AVBRYTE?\')"').'
	</p>
</form>';
	}
}

ess::$b->page->load();
