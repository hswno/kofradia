<?php namespace Kofradia\Controller\Users;

use Kofradia\Controller;

class Login extends Controller {
	protected $ssl = true;

	public function action_index()
	{
		// vis feilmelding hvis noen
		if ($err = $this->show_errors())
		{
			return $err;
		}

		#ess::$b->page->add_title("Logg inn");
		#ess::$b->page->theme_file = "logginn";
		
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
				\ess::$b->page->add_message("Mangler ID og passord.", "error", 'login');
				$err = true;
			}
			elseif (empty($id))
			{
				// mangler id
				\ess::$b->page->add_message("Mangler ID.", "error", 'login');
				$err = true;
			}
			elseif (empty($pass) && !$devlogin)
			{
				// mangler passord
				\ess::$b->page->add_message("Mangler passord.", "error", 'login');
				$err = true;
			}
			$type = intval(postval('expire_type'));
			if ($type < 0 || $type > 2)
			{
				// ugyldig expire type
				\ess::$b->page->add_message("Ugyldig expire type!", "error", 'login');
				$err = true;
			}
			
			// sikker tilkobling?
			$secure_only = isset($_POST['secure_only']);
			
			if (!$err)
			{
				// prøv å logg inn
				switch (\login::do_login($id, $pass, $type, true, $secure_only, $devlogin))
				{
					case LOGIN_ERROR_USER_OR_PASS:
						\ess::$b->page->add_message("Feil ID".(!$devlogin ? ' eller passord' : '').".", "error", 'login');
						
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
						\redirect::handle("", \redirect::ROOT);
						break;
					
					default:
					if (!\login::$logged_in)
					{
						\ess::$b->page->add_message("Ukjent innloggingsfeil!", "error");
					}
					else
					{
						// logget inn
						putlog("NOTICE", "%c7%bLOGG INN%b%c: (%u{$_SERVER['REMOTE_ADDR']}%u) %u".\login::$user->player->data['up_name']."%u (".\login::$user->data['u_email'].") ({$_SERVER['HTTP_USER_AGENT']}) ".\ess::$s['path']."/min_side?up_id=".\login::$user->player->id);
						if (isset($_GET['orign'])) \redirect::handle($_GET['orign'], \redirect::SERVER, \login::$info['ses_secure']);
						
						\redirect::handle("", NULL, \login::$info['ses_secure']);
					}
				}
			}
		}
		
		// spør brukeren etter en spesifikk side?
		if (isset($_GET['orign']) && $_GET['orign'] != "/")
		{
			\ess::$b->page->add_message("Du må logge inn for å se denne siden.", "error", 'login');
		}

		// sett opp e-posten vi ber om
		$id = '';
		if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id']))
		{
			$id = $_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id'];
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id']);
		}
		$id = requestval("id", $id);

		// expire type
		$expire = 0;
		if (isset($_REQUEST['expire_type']))
		{
			$val = intval($_POST['expire_type']);
			if ($val >= 0 && $val <= 2) $expire = $val;
		}

		$r = new \Kofradia\Response();
		$r->data = \Kofradia\View::forgeTwig("users/login/login", array(
			"norobots" => isset($_GET['orign']), // ikke la siden bli indeksert hvis det er en henvising
			"userid" => $id,
			"expire" => $expire,
			"secure_only" => isset($_POST['secure_only'])
		));
		return $r;
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
		\ess::$b->page->theme_file = "guest";
		
		switch ($login_error)
		{
			case "deactivated":
				\ess::$b->page->add_title("Deaktivert");

				return \Kofradia\View::forgeTwig('users/login/deactivated', array(
					"email" => $info['u_email'],
					"date" => $info['u_deactivated_time'],
					"reason" => \game::bb_to_html($info['u_deactivated_reason'])));
			
			default:
				throw new HSException("Ukjent innloggingsfeil ($login_error)");
		}
	}
}