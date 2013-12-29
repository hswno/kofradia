<?php

class page_logginn
{
	public function __construct()
	{
		force_https(true);
		
		// allerede logget inn?
		if (login::$logged_in)
		{
			// send brukeren til hovedsiden
			if (isset($_GET['orign'])) redirect::handle($_GET['orign'], redirect::SERVER, login::$info['ses_secure']);
			redirect::handle("", NULL, login::$info['ses_secure']);
		}
		
		// vis feilmelding hvis noen
		self::show_errors();
		
		ess::$b->page->add_title("Logg inn");
		ess::$b->page->theme_file = "logginn";
		
		// tillate logginn uten passord
		$devlogin = !MAIN_SERVER;
		
		if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['id']))
		{
			// skjekk logg inn formen
			$err = false;
			$id = postval("id");
			$pass = postval("passord");
			if (empty($id) && empty($pass) && !$devlogin)
			{
				ess::$b->page->add_message("Mangler ID og passord.", "error");
				$err = true;
			}
			elseif (empty($id))
			{
				// mangler id
				ess::$b->page->add_message("Mangler ID.", "error");
				$err = true;
			}
			elseif (empty($pass) && !$devlogin)
			{
				// mangler passord
				ess::$b->page->add_message("Mangler passord.", "error");
				$err = true;
			}
			$type = intval(postval('expire_type'));
			if ($type < 0 || $type > 2)
			{
				// ugyldig expire type
				ess::$b->page->add_message("Ugyldig expire type!", "error");
				$err = true;
			}
			
			// sikker tilkobling?
			$secure_only = isset($_POST['secure_only']);
			
			if (!$err)
			{
				// prøv å logg inn
				switch (login::do_login($id, $pass, $type, true, $secure_only, $devlogin))
				{
					case LOGIN_ERROR_USER_OR_PASS:
					ess::$b->page->add_message("Feil ID".(!$devlogin ? ' eller passord' : '').".", "error");
					
					// logg
					putlog("ABUSE", "%c4%bUGYLDIG BRUKERNAVN/PASSORD:%b%c {$_SERVER['REMOTE_ADDR']} forsøkte å logge inn med ID %u$id%u!");
					
					break;
					
					// utestengt
					case LOGIN_ERROR_ACTIVATE:
						global $uid;
						// hent begrunnelse og info
						$result = \Kofradia\DB::get()->query("SELECT u_id, u_email, u_deactivated_reason, u_deactivated_time, up_name FROM users LEFT JOIN users_players ON up_id = u_active_up_id WHERE u_id = $uid");
						$info = $result->fetch();
						$_SESSION[$GLOBALS['__server']['session_prefix'].'login_error'] = array("deactivated", $info);
						
						putlog("ABUSE", "%c8%bLOGG INN - DEAKTIVERT%b%c: %u{$_SERVER['REMOTE_ADDR']}%u forsøkte å logge inn på %u{$info['u_email']}%u som er en deaktivert bruker!");
						
						// send til feilside
						redirect::handle("", redirect::ROOT);
						break;
					
					default:
					if (!login::$logged_in)
					{
						ess::$b->page->add_message("Ukjent innloggingsfeil!", "error");
					}
					else
					{
						// logget inn
						putlog("NOTICE", "%c7%bLOGG INN%b%c: (%u{$_SERVER['REMOTE_ADDR']}%u) %u".login::$user->player->data['up_name']."%u (".login::$user->data['u_email'].") ({$_SERVER['HTTP_USER_AGENT']}) ".ess::$s['path']."/min_side?up_id=".login::$user->player->id);
						if (isset($_GET['orign'])) redirect::handle($_GET['orign'], redirect::SERVER, login::$info['ses_secure']);
						
						redirect::handle("", NULL, login::$info['ses_secure']);
					}
				}
			}
		}
		
		// spør brukeren etter en spesifikk side?
		if (isset($_GET['orign']) && $_GET['orign'] != "/")
		{
			ess::$b->page->add_message("Du må logge inn for å se denne siden.", "error");
		}
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis side for feilmelding hvis det er noen feilmelding
	 */
	protected function show_errors()
	{
		// ingen feilmelding?
		if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_error']))
		{
			return;
		}
		
		// vis feilside
		$login_error = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_error'][0];
		$info = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_error'][1];
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_error']);
		ess::$b->page->theme_file = "guest";
		
		switch ($login_error)
		{
			case "deactivated":
				ess::$b->page->add_title("Deaktivert");
				
				echo '
<h1>Deaktivert</h1>
<p>Din bruker med e-postadresse <u>'.htmlspecialchars($info['u_email']).'</u> ble deaktivert '.ess::$b->date->get($info['u_deactivated_time'])->format().'.</p>';
				
				// begrunnelse?
				if (!empty($info['u_deactivated_reason']))
				{
					echo '
<div class="section">
	<h2>Begrunnelse</h2>
	<p>'.game::bb_to_html($info['u_deactivated_reason']).'</p>
</div>';
				}
				else
				{
					echo '
<p>Begrunnelse er ikke oppgitt.</p>';
				}
	
				echo '
<p>Dersom du mener denne deaktiveringen er feil kan du ta <a href="henvendelser">kontakt</a>. Kun seriøse henvendelser blir behandlet.</p>';
				
				break;
				
			default:
				throw new HSException("Ukjent innloggingsfeil ($login_error)");
		}
		
		ess::$b->page->load();
	}
}
