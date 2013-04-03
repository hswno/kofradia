<?php

login::init();

/**
 * Funksjoner for innlogging/utlogging
 * @static
 */
class login
{
	/** Er brukeren logget inn? */
	public static $logged_in = NULL;
	
	/** Informasjon om sesjonen */
	public static $info;
	
	/**
	 * Referanse til user objektet
	 * @var user
	 */
	public static $user = NULL;
	
	/** Innlogging til tilgangssystemet */
	public static $extended_access = NULL;
	
	/** Ekstra data som settes i session som kun gjelder innloggingen */
	public static $data;
	
	/**
	 * Init funksjonen
	 * Sjekker om brukeren er logget inn og henter nødvendig informasjon
	 */
	public static function init()
	{
		// allerede kjørt? kjøres kun én gang
		if (!is_null(self::$logged_in)) return;
		
		// tøm
		self::trash();
		
		// ajax?
		$ajax = defined("SCRIPT_AJAX");
		
		// skjekk om brukeren er logget inn
		self::check_status($ajax);
		
		// sjekk lås
		self::check_lock();
		
		// ikke logget inn?
		if (!self::$logged_in)
		{
			sess_start();
			
			// slett mulige sessions
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'data']);
			
			// logg visningen
			$color = $_SERVER['REQUEST_METHOD'] == "POST" ? '%c9' : '%c7';
			putlog("NOTICE", "{$_SERVER['REMOTE_ADDR']} -- $color{$_SERVER['REQUEST_METHOD']}".($ajax ? '/AJAX' : '')."%c -- {$_SERVER['REQUEST_URI']}");
		}
	}
	
	/**
	 * Nullstill informasjon
	 */
	public static function trash()
	{
		// sett opp standardvariabel
		self::$logged_in = false;
		self::$info = NULL;
		self::$user = false;
		self::$extended_access = NULL;
		self::$data = NULL;
	}
	
	/**
	 * Sjekk om brukeren er logget inn
	 * @param boolean $ajax bruk data fra $_SESSION ?
	 */
	public static function check_status($ajax = false)
	{
		global $__server, $_base, $_game;
		
		// ajax?
		if ($ajax)
		{
			// er ikke session starta?
			if (!session_id())
			{
				// har ikke session?
				if (!isset($_COOKIE[session_name()]) || !isset($_COOKIE[$__server['cookie_prefix'] . "s"])) return;
				
				// start session
				sess_start();
			}
			
			// har vi ikke brukerinfo?
			if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in'])) return;
			
			// kontroller at brukeren fremdeles kan være logget inn
			if ($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['ses_expire_time'] <= time())
			{
				self::logout();
				return;
			}
			
			self::$logged_in = $_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in'];
			self::$info = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_info'];
			self::$user = $_SESSION[$GLOBALS['__server']['session_prefix'].'user'];
			if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'])) self::$extended_access = $_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'];
			if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'data'])) self::$data = &$_SESSION[$GLOBALS['__server']['session_prefix'].'data']; // if-test kan fjernes over tid grunnet overgangsfase
			
			// kontroller extended access
			if (isset(self::$extended_access['authed']))
			{
				// vært inaktiv for lenge?
				$time = time();
				if (self::$extended_access['auth_check']+1800 <= $time)
				{
					self::$extended_access = array(
						"authed" => NULL,
						"auth_time" => 0,
						"auth_check" => 0,
						"passkey" => self::$extended_access
					);
					$_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'] = self::$extended_access;
				}
			}
			
			// ajax sjekk fullført
			return;
		}
		
		// finnes cookies?
		if (isset($_COOKIE[$__server['cookie_prefix'] . "s"]))
		{
			$secure = $_COOKIE[$__server['cookie_prefix'] . "s"];
			if ($secure == 1)
			{
				force_https();
			}
			
			// sjekk at vi har alle cookies
			if (isset($_COOKIE[$__server['cookie_prefix'] . "id"]) && substr_count($_COOKIE[$__server['cookie_prefix'] . "id"], ":") == 1 && isset($_COOKIE[$__server['cookie_prefix'] . "h"]))
			{
				// finn sid, uid og hash
				list($sid, $uid) = explode(":", $_COOKIE[$__server['cookie_prefix'] . "id"]);
				$hash = $_COOKIE[$__server['cookie_prefix'] . "h"];
				
				$sid = intval($sid);
				$uid = intval($uid);
				
				// finn ut om dette finnes i databasen
				$result = $_base->db->query("SELECT
						ses_id, ses_u_id, ses_hash, ses_expire_type, ses_expire_time, ses_browsers, ses_phpsessid, ses_last_ip, ses_last_time, ses_secure,
						u_online_time, u_online_ip, u_access_level, u_force_ssl
					FROM sessions, users WHERE sessions.ses_u_id = users.u_id AND sessions.ses_u_id = $uid AND sessions.ses_id = $sid AND sessions.ses_active = 1 AND sessions.ses_expire_time > ".(time()));
				
				// kontroller hash
				$row = null;
				if (mysql_num_rows($result) > 0)
				{
					$row = mysql_fetch_assoc($result);
					if ($hash != $row['ses_hash'] && $hash != substr(md5($row['ses_hash']), 0, 13))
					{
						$row = null;
					}
				}
				
				// har vi en rad?
				if ($row)
				{
					self::$info = $row;
					mysql_free_result($result);
					self::$info['ses_secure'] = self::$info['ses_secure'] == 1;
					$extra = "";
					
					// start session
					sess_start(self::$info['ses_phpsessid']);
					
					// deaktivert?
					if (self::$info['u_access_level'] == 0)
					{
						// logg ut alle øktene
						self::logout(true);
						
						// hent begrunnelse og info
						$result = $_base->db->query("SELECT u_id, u_email, u_deactivated_reason, u_deactivated_time, up_name FROM users LEFT JOIN users_players ON up_id = u_active_up_id WHERE u_id = $uid");
						$_SESSION[$GLOBALS['__server']['session_prefix'].'login_error'] = array("deactivated", mysql_fetch_assoc($result));
						
						redirect::handle("", redirect::ROOT);
					}
					
					// ny IP-adresse?
					if ($_SERVER['REMOTE_ADDR'] != self::$info['ses_last_ip'] && self::$info['ses_last_ip'] != "0.0.0.0" && !empty(self::$info['ses_last_ip']))
					{
						// hent IP-liste
						$result = ess::$b->db->query("
							SELECT ses_ip_list
							FROM sessions
							WHERE ses_id = $sid");
						$ip_list = explode(";", mysql_result($result, 0));
						
						// er vi allerede verifisert?
						$ok = false;
						if (in_array($_SERVER['REMOTE_ADDR'], $ip_list))
						{
							$ok = true;
						}
						
						// har vi mulighet for å verifisere?
						elseif ($__server['https_support'])
						{
							if ($row['ses_hash'] == $hash && $secure)
							{
								$ok = true;
							}
							
							elseif (substr(md5($row['ses_hash']), 0, 13) == $hash)
							{
								// må bruke HTTPS?
								if (!HTTPS)
								{
									// _POST?
									if ($_SERVER['REQUEST_METHOD'] == "POST")
									{
										header("HTTP/1.1 406 Not Acceptable");
										die("Du forsøker å utføre en handling men må reautentisere deg på grunn av ny IP-adresse. Åpne siden i et nytt vindu og vend tilbake hit og oppdater siden for å fullføre handlingen.");
									}
									
									// videresend til sikker kobling
									redirect::handle("/?orign=".urlencode($_SERVER['REQUEST_URI']), redirect::ROOT, true);
								}
								
								// kontroller reauth-cookie
								if (isset($_COOKIE[$__server['cookie_prefix']."ra"]) && $_COOKIE[$__server['cookie_prefix']."ra"] == $row['ses_hash'])
								{
									$ok = true;
								}
							}
							
							// verifisert?
							if ($ok)
							{
								// legg til i listen
								$ip_list[] = $_SERVER['REMOTE_ADDR'];
								$extra .= ", ses_ip_list = ".ess::$b->db->quote(implode(";", $ip_list));
								
								putlog("ABUSE", "%c6%bAUTENTISERT-IP:%b%c #%u{$uid}%u har fått ny IP-adresse autentisert i økten (%u{$_SERVER['REMOTE_ADDR']}%u - forrige: ".self::$info['ses_last_ip'].") {$__server['path']}/min_side?u_id=$uid");
							}
						}
						
						if (!$ok)
						{
							// logg ut økten
							self::logout();
							
							putlog("CREWCHAN", "%c6%bMISLYKKET-AUTENTISERT-IP:%b%c #%u{$uid}%u har fått ny IP-adresse i økten (%u{$_SERVER['REMOTE_ADDR']}%u - forrige: ".self::$info['ses_last_ip'].") - %c4KUNNE IKKE VERIFISERES%c - {$__server['path']}/min_side?u_id=$uid");
							
							// hent e-post
							$result = $_base->db->query("SELECT u_email FROM users WHERE u_id = $uid");
							$email = mysql_result($result, 0);
							
							// lagre e-post i sessions slik at det kan hentes ut til logg inn skjemaet
							$_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id'] = $email;
							
							// info og redirect
							$_base->page->add_message("Du har fått ny IP-adresse og har blitt automatisk logget ut av sikkerhetsmessige årsaker. Vi klarte ikke å verifisere din identitet. Du kan nå logge inn igjen.", "info");
							redirect::handle("?orign=".urlencode($_SERVER['REQUEST_URI']), redirect::ROOT);
						}
						
						// sett som siste IP
						$extra .= ", ses_last_ip = ".ess::$b->db->quote($_SERVER['REMOTE_ADDR']);
					}
					
					// bruker ikke sikker tilkobling slik det skal?
					if (!$secure && self::$info['ses_secure'] && $__server['https_support'])
					{
						// endre secure cookie
						$cookie_expire = self::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER ? 0 : time()+31536000;
						setcookie($__server['cookie_prefix'] . "s", 1, $cookie_expire, $__server['cookie_path'], $__server['cookie_domain']);
						
						force_https();
					}
					
					// skal være tvunget til https?
					if ($__server['https_support'] && !self::$info['ses_secure'] && ((self::$info['u_access_level'] != 0 && self::$info['u_access_level'] != 1) || self::$info['u_force_ssl'] != 0))
					{
						// endre secure cookie
						$cookie_expire = self::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER ? 0 : time()+31536000;
						setcookie($__server['cookie_prefix'] . "s", 1, $cookie_expire, $__server['cookie_path'], $__server['cookie_domain']);
						
						// endre session
						$_base->db->query("UPDATE sessions SET ses_secure = 1 WHERE ses_id = $sid"); 
						
						// krev https
						force_https();
						self::$info['ses_secure'] = true;
					}
					
					// sjekk for hyppige oppdateringer
					if ($uid != 1)
					{
						$perioder = array(5 => 10, 10 => 15, 60 => 80);
						
						foreach ($perioder as $tid => $maks)
						{
							$periode = ceil(time()/$tid);
							$c_now = isset($_SESSION[$GLOBALS['__server']['session_prefix'].'user_hits_'.$tid][$periode]) ? $_SESSION[$GLOBALS['__server']['session_prefix'].'user_hits_'.$tid][$periode] + 1 : 1;
							unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user_hits_'.$tid]);
							$_SESSION[$GLOBALS['__server']['session_prefix'].'user_hits_'.$tid][$periode] = $c_now;
							
							// for mange visninger
							if ($c_now > $maks)
							{
								// finn info
								$result = $_base->db->query("SELECT up_name FROM users, users_players WHERE u_id = $uid AND up_id = u_active_up_id");
								$name = mysql_result($result, 0);
								putlog("ABUSE", "%bHITS LIMIT%b (%u$tid%u-%u$periode%u) - %u$name%u ($uid) - COUNT: %u$c_now%u -- {$_SERVER['REQUEST_METHOD']} -- {$_SERVER['REQUEST_URI']} -- {$__server['path']}/min_side?u_id=$uid");
								
								header("HTTP/1.0 503 Service Unavailiable");
								echo sysreport::html_template("For mange visninger", "<p>Du har hatt for mange visninger på siden i løpet av kort tid. Vent litt og prøv igjen.</p>");
								die;
							}
						}
					}
					
					// oppdater brukeren
					$expire = self::$info['ses_expire_type'] == LOGIN_TYPE_ALWAYS ? time()+31536000 : (self::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER ? time()+86400 : time()+900);
					self::$info['ses_expire_time'] = $expire;
					$time = time();
					
					// nettlesere
					$browsers = self::$info['ses_browsers'];
					if (empty($browsers))
					{
						$browsers = array();
					}
					else
					{
						$browsers = explode("\n", $browsers);
					}
					
					// endre nettleser?
					if (!in_array($_SERVER['HTTP_USER_AGENT'], $browsers))
					{
						$browsers[] = $_SERVER['HTTP_USER_AGENT'];
						$extra .= ", ses_browsers = ".$_base->db->quote(implode("\n", $browsers));
						
						$result = $_base->db->query("SELECT u_email, up_name FROM users, users_players WHERE u_id = $uid AND u_active_up_id = up_id");
						$row = mysql_fetch_assoc($result);
						putlog("ABUSE", "%b%c11NETTLESER OPPDAGET:%c%b (%c4%u".count($browsers)."%u%c) - {$row['up_name']} ({$row['u_email']}); UID: %u$uid%u - SID: {$sid} - IP: {$_SERVER['REMOTE_ADDR']} - NETTLESER: {$_SERVER['HTTP_USER_AGENT']}");
					}
					if (session_id() != self::$info['ses_phpsessid'])
					{
						$phpsessid = $_base->db->quote(session_id());
						$extra .= ", ses_phpsessid = $phpsessid";
					}
					
					$_base->db->query("UPDATE sessions SET ses_expire_time = $expire, ses_hits = ses_hits + 1, ses_last_time = $time$extra WHERE ses_u_id = $uid AND ses_id = $sid");
					
					// hent inn brukeren
					self::$logged_in = true;
					self::load_user($uid);
					
					// oppdater statisikk
					$date = $_base->date->get();
					self::$info['secs_hour'] = self::get_secs_hour();
					ess::$b->db->query("
						INSERT INTO users_hits SET uhi_hits = 1, uhi_up_id = ".login::$user->player->id.", uhi_secs_hour = ".self::$info['secs_hour']."
						ON DUPLICATE KEY UPDATE uhi_hits = uhi_hits + 1");
					
					$upd_u = array();
					$upd_up = array();
					if ($_SERVER['REMOTE_ADDR'] != self::$info['ses_last_ip'])
					{
						$last_ip = ess::$b->db->quote($_SERVER['REMOTE_ADDR']);
						$upd_u[] = "u_online_ip = $last_ip";
						self::$user->data['u_online_ip'] = $_SERVER['REMOTE_ADDR'];
						if (self::$info['u_online_time'] > time() - 300)
						{
							$delay = time() - self::$info['u_online_time'];
							putlog("ABUSE", "%c6%bSESSION-NY-IP:%b%c #%u{$uid}%u har ny IP (%u{$_SERVER['REMOTE_ADDR']}%u) i løpet av kort tid (%u{$delay}%u sekunder) (samme session) {$__server['path']}/min_side?u_id=$uid");
						}
					}
					elseif ($_SERVER['REMOTE_ADDR'] != self::$info['u_online_ip'])
					{
						$last_ip = ess::$b->db->quote($_SERVER['REMOTE_ADDR']);
						$upd_u[] = "u_online_ip = $last_ip";
						self::$user->data['u_online_ip'] = $_SERVER['REMOTE_ADDR'];
						if (self::$info['u_online_time'] > time() - 300)
						{
							$delay = time() - self::$info['u_online_time'];
							putlog("ABUSE", "%c6%bNY-IP:%b%c #%u{$uid}%u har ny IP (%u{$_SERVER['REMOTE_ADDR']}%u) i løpet av kort tid (%u{$delay}%u sekunder) (egen session) {$__server['path']}/min_side?u_id=$uid");
						}
					}
					
					// oppdatere spilleren eller brukeren?
					if (self::$user->player->data['up_access_level'] != 0) $upd_up[] = "up_hits = up_hits + 1";
					else $upd_u[] = "u_hits = u_hits + 1";
					self::$user->data['u_online_time'] = $time;
					$upd_u[] = "u_online_time = $time";
					
					// vise pålogget status for spilleren?
					if (self::$user->player->data['up_access_level'] != 0 && ($uid != SYSTEM_USER_ID || isset($_SESSION[$GLOBALS['__server']['session_prefix'].'show_online'])) && (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'hide_online']) || ($uid != 1 && $uid != SYSTEM_USER_ID)))
					{
						// oppdatere spilleren?
						if (self::$user->player) self::$user->player->data['up_last_online'] = $time;
						$upd_up[] = "up_last_online = $time";
					}
					
					if (count($upd_u) > 0) $_base->db->query("UPDATE users SET ".implode(",", $upd_u)." WHERE u_id = ".self::$user->id);
					if (count($upd_up) > 0) $_base->db->query("UPDATE users_players SET ".implode(",", $upd_up)." WHERE up_id = ".self::$user->player->id);
				}
				else
				{
					// fant ingen tilsvarende rad - slett session og cookies
					self::logout();
				}
			}
			else
			{
				// mangler alle cookies
				self::logout();
			}
		}
		
		else
		{
			sess_start();
		}
	}
	
	/**
	 * Last inn brukeren
	 */
	protected static function load_user($u_id)
	{
		global $_base, $_game;
		if (!self::$logged_in) return;
		
		// last inn brukeren
		if (!user::get($u_id, true))
		{
			self::logout();
		}
		
		// utvidede tilganger
		if (self::$user->data['u_access_level'] != 1 && self::$user->data['u_access_level'] != 0 && in_array(self::$user->data['u_access_level'], $_game['access']['crewet']))
		{
			// logget inn, ikke inaktiv mer enn 30 min og samme tilgangsnøkkel?
			$time = time();
			$key = self::$user->params->get("extended_access_passkey");
			if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access']['authed']) && $_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access']['auth_check']+1800 > $time && $_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access']['passkey'] == $key)
			{
				$_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access']['auth_check'] = $time;
				self::$extended_access = $_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'];
			}
			else
			{
				self::$extended_access = array(
					"authed" => NULL,
					"auth_time" => 0,
					"auth_check" => 0,
					"passkey" => $key
				);
				$_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'] = self::$extended_access;
			}
		}
		
		// trenger vi å hente nye kontakter?
		if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['contacts_update']) || $_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['contacts_update'] != self::$user->data['u_contacts_update_time'])
		{
			// kontakter
			self::$info['contacts'] = array(1 => array(), 2 => array());
			$result = $_base->db->query("SELECT uc_id, uc_contact_up_id, uc_time, uc_type, up_name, up_access_level FROM users_contacts LEFT JOIN users_players ON up_id = uc_contact_up_id WHERE uc_u_id = $u_id ORDER BY uc_type, up_name ASC");
			
			while ($row = mysql_fetch_assoc($result))
			{
				self::$info['contacts'][$row['uc_type']][$row['uc_contact_up_id']] = $row;
			}
			mysql_free_result($result);
			
			self::$info['contacts_update'] = self::$user->data['u_contacts_update_time'];
		}
		else
		{
			self::$info['contacts'] = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['contacts'];
			self::$info['contacts_update'] = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['contacts_update'];
		}
		
		// lagre session
		$_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in'] = true;
		$_SESSION[$GLOBALS['__server']['session_prefix'].'login_info'] = &self::$info;
		$_SESSION[$GLOBALS['__server']['session_prefix'].'user'] = self::$user;
		if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'data'])) $_SESSION[$GLOBALS['__server']['session_prefix'].'data'] = array();
		self::$data = &$_SESSION[$GLOBALS['__server']['session_prefix'].'data'];
		
		// sett opp tilganger for ajax etc
		$_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['accesses'] = access::types(self::$user->data['u_access_level']);
		if (self::$user->data['u_access_level'] != 1 && self::$user->data['u_access_level'] != 0 && !isset(self::$extended_access['authed']))
		{
			$_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['accesses'] = access::types(1);
		}
	}
	
	/**
	 * Logg ut en bruker
	 * Fjernet alle sessions og cookies
	 * @param boolean $all_sessions fjerne innlogginger fra andre steder også?
	 * @return boolean sessions ble slettet?
	 */
	// logg ut en bruker (slett session og cookie)
	public static function logout($all_sessions = false)
	{
		global $__server, $_base;
		
		sess_start();
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user_info']);
		
		// fjern cookies
		if (isset($_COOKIE[$__server['cookie_prefix'] . "h"])) setcookie($__server['cookie_prefix'] . "h", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		if (isset($_COOKIE[$__server['cookie_prefix'] . "s"])) setcookie($__server['cookie_prefix'] . "s", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		if (isset($_COOKIE[$__server['cookie_prefix'] . "id"])) setcookie($__server['cookie_prefix'] . "id", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		if (isset($_COOKIE[$__server['cookie_prefix'] . "ra"])) setcookie($__server['cookie_prefix'] . "ra", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		
		// må være innlogget/ha sesjonsinfo for å slette sessions
		if (!isset(self::$info['ses_u_id'])) return false;
		
		// slett session
		if ($all_sessions)
		{
			// slett alle sessions til denne brukeren
			$_base->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = ".self::$info['ses_u_id']." AND ses_active = 1");
		}
		elseif (isset(self::$info['ses_id']) && isset(self::$info['ses_hash']))
		{
			// slett kun den aktive session
			$_base->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = ".self::$info['ses_u_id']." AND ses_hash = ".$_base->db->quote(self::$info['ses_hash'])." AND ses_id = ".self::$info['ses_id']." AND ses_active = 1");
		}
		
		return true;
	}
	
	/**
	 * Behandle logg inn forespørsel
	 * @param string $email kan også være brukerid
	 * @param string $pass
	 * @param integer $expire_type
	 * @param boolean $md5 skal passordet krypteres?
	 * @param boolean $secure_only skal vi fortsette å bruke ssl etter innlogging?
	 * @return boolean
	 */
	public static function do_login($email, $pass, $expire_type = LOGIN_TYPE_TIMEOUT, $md5 = true, $secure_only = false, $skip_pass = null)
	{
		// hent potensielle brukere
		$result = ess::$b->db->query("
			SELECT u_id, u_pass, u_email, u_online_time, u_online_ip, u_access_level, u_force_ssl
			FROM users LEFT JOIN users_players ON u_id = up_u_id
			WHERE (u_email = ".ess::$b->db->quote($email)." OR u_id = ".intval($email)." OR up_name = ".ess::$b->db->quote($email).")
			ORDER BY u_access_level = 0, u_online_time DESC");
		if (mysql_num_rows($result) == 0)
		{
			return LOGIN_ERROR_USER_OR_PASS;
		}

		$p_ok = false;
		while ($user = mysql_fetch_assoc($result))
		{
			// ikke sjekke passord
			if ($skip_pass)
			{
				$p_ok = true;
				break;
			}

			// stemmer passordet?
			if (($md5 && password::verify_hash($pass, $user['u_pass'], 'user')) || (!$md5 && $pass == $user['u_pass']))
			{
				// ok!
				$p_ok = true;
				break;
			}
		}

		// fant ikke noen bruker med riktig passord?
		if (!$p_ok)
		{
			return LOGIN_ERROR_USER_OR_PASS;
		}
		
		// ikke aktivert?
		if ($user['u_access_level'] == 0)
		{
			global $uid;
			$uid = $user['u_id'];
			return LOGIN_ERROR_ACTIVATE;
		}
		
		// e-post og passord stemte, logg inn personen
		self::do_login_handle($user['u_id'], $user, $expire_type, $secure_only);
		return -1;
	}
	
	/**
	 * Logg inn en bruker
	 * @param string $email kan også være brukerid
	 * @param string $pass
	 * @param integer $expire_type
	 * @param boolean $md5 skal passordet krypteres?
	 * @param boolean $secure_only skal vi fortsette å bruke ssl etter innlogging?
	 * @return boolean
	 */
	public static function do_login_handle($u_id, $user = NULL, $expire_type = LOGIN_TYPE_TIMEOUT, $secure_only = false)
	{
		global $__server;
		
		// prøver vi å sette cookies uten HTTPS?
		if ($__server['https_support'] && !HTTPS) throw new HSException("Kan ikke sette logg inn cookies uten sikret tilkobling.");
		
		// må hente data?
		$u_id = (int) $u_id;
		if (!$user)
		{
			$result = ess::$b->db->query("
				SELECT u_id, u_email, u_online_time, u_online_ip, u_access_level, u_force_ssl
				FROM users
				WHERE u_id = $u_id");
			$user = mysql_fetch_assoc($result);
		}
		
		if (!$user || $u_id != $user['u_id']) return false;
		
		// ikke aktivert?
		if ($user['u_access_level'] == 0) return false;
		
		// lag unik id
		$hash = uniqid("");
		$hash_pub = substr(md5($hash), 0, 13);
		
		// timeout tid
		$timeout = 900;
		
		// secure only
		$secure_only = $__server['https_support'] && ($secure_only || ($user['u_access_level'] != 1 && $user['u_access_level'] != 0) || $user['u_force_ssl'] != 0);
		
		$expire_type = (int) $expire_type;
		$expire = $expire_type == LOGIN_TYPE_BROWSER ? time()+60*60*48 : ($expire_type == LOGIN_TYPE_TIMEOUT ? time()+$timeout : time()+31536000);
		
		// legg til session
		$ip = ess::$b->db->quote($_SERVER['REMOTE_ADDR']);
		$browsers = ess::$b->db->quote($_SERVER['HTTP_USER_AGENT']);
		ess::$b->db->query("INSERT INTO sessions SET ses_u_id = {$user['u_id']}, ses_hash = ".ess::$b->db->quote($hash).", ses_expire_time = $expire, ses_expire_type = $expire_type, ses_created_time = ".time().", ses_ip_list = $ip, ses_last_ip = $ip, ses_browsers = $browsers, ses_secure = ".($secure_only ? 1 : 0));
		
		// hent session id
		$ses_id = ess::$b->db->insert_id();
		
		// sett cookie
		$cookie_expire = $expire_type == LOGIN_TYPE_BROWSER ? 0 : time()+31536000;
		setcookie($__server['cookie_prefix'] . "id", "$ses_id:{$user['u_id']}", $cookie_expire, $__server['cookie_path'], $__server['cookie_domain'], $secure_only);
		setcookie($__server['cookie_prefix'] . "h", ($secure_only ? $hash : $hash_pub), $cookie_expire, $__server['cookie_path'], $__server['cookie_domain'], $secure_only, true);
		setcookie($__server['cookie_prefix'] . "s", ($secure_only ? 1 : 0), $cookie_expire, $__server['cookie_path'], $__server['cookie_domain']);
		
		// sett cookie for reauth
		if (!$secure_only && $__server['https_support'])
		{
			setcookie($__server['cookie_prefix'] . "ra", $hash, $cookie_expire, $__server['cookie_path'], $__server['cookie_domain'], true, true);
		}
		
		self::$logged_in = true;
		self::$info = array(
			"ses_id" => $ses_id,
			"ses_u_id" => $user['u_id'],
			"ses_hash" => $hash,
			"ses_expire_type" => $expire_type,
			"ses_expire_time" => $expire,
			"ses_browsers" => $_SERVER['HTTP_USER_AGENT'],
			"ses_phpsessid" => session_id(),
			"ses_last_ip" => $_SERVER['REMOTE_ADDR'],
			"ses_last_time" => time(),
			"ses_secure" => $secure_only,
			"u_online_time" => $user['u_online_time'],
			"u_online_ip" => $user['u_online_ip'],
			"u_access_level" => $user['u_access_level'],
			"u_force_ssl" => $user['u_force_ssl']
		);
		
		$date = ess::$b->date->get();
		$time = $date->format("U") - $date->format("i")*60 - $date->format("s");
		self::$info['secs_hour'] = $time;
		
		// last inn bruker
		self::load_user($user['u_id']);
		
		return true;
	}
	
	/**
	 * Logg inn til utvidede tilganger
	 */
	public static function extended_access_login()
	{
		if (!self::$logged_in || !isset(self::$extended_access)) return;
		
		$time = time();
		self::$extended_access = array(
			"authed" => true,
			"auth_time" => time(),
			"auth_check" => time(),
			"passkey" => self::$extended_access['passkey']
		);
		$_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'] = self::$extended_access;
	}
	
	/**
	 * Logg ut av utvidede tilganger
	 */
	public static function extended_access_logout()
	{
		if (!isset(self::$extended_access)) return;
		self::$extended_access = array(
			"authed" => NULL,
			"auth_time" => 0,
			"auth_check" => 0,
			"passkey" => self::$user->params->get("extended_access_passkey")
		);
		$_SESSION[$GLOBALS['__server']['session_prefix'].'extended_access'] = self::$extended_access;
	}
	
	/**
	 * Sjekk om vi er logget inn til utvidede tilganger
	 */
	public static function extended_access_is_authed()
	{
		return isset(self::$extended_access['authed']);
	}
	
	/**
	 * Er brukeren låst?
	 */
	public static function check_lock()
	{
		if (defined("LOCK")) return LOCK;
		if (!self::$logged_in || count(self::$user->lock) == 0)
		{
			define("LOCK", false);
			return false;
		}
		
		define("LOCK", true);
		
		// ajax?
		if (defined("SCRIPT_AJAX")) return false;
		
		// kontroller tillate adresser
		$allowed = array(
			"^(\\?|$)",
			"^loggut",
			"^support\\/",
			"^node(\\/|\$)",
			"^lock",
			"^betingelser",
			"^sessions",
			"^crew",
			"^min_side",
			"^henvendelser",
			"^donasjon",
			"^dev\\/",
			"^forum\\/(\\?|$|forum|topic)"
		);
		
		// tilgang til inbox?
		$allowed[] = "^innboks";
		$allowed[] = "^innboks_sok";
		$allowed[] = "^innboks_les";
		$allowed[] = "^innboks_ny";
		
		// crew?
		if (isset(login::$extended_access))
		{
			// crew har full tilgang til forumet
			$allowed[] = "^admin\\/";
			$allowed[] = "^crew\\/";
			$allowed[] = "^forum\\/";
			$allowed[] = "^extended_access";
			$allowed[] = "^profil";
			$allowed[] = "^p\\/";
		}
		
		$allowed = '/('.implode("|", $allowed).')/u';
		
		// finn adressen for denne siden
		global $__server;
		$path = substr($_SERVER['REQUEST_URI'], 0);
		$prefix = strlen($__server['relative_path']);
		if ($prefix > 0)
		{
			if (substr($path, 0, $prefix) != $__server['relative_path'])
			{
				redirect::handle("lock", redirect::ROOT);
			}
			
			$path = substr($path, $prefix);
		}
		$path = substr($path, 1);
		
		// er vi på en side vi ikke har tillatelse til å være?
		if (!preg_match($allowed, $path))
		{
			// send til informasjonssiden for begrensninger
			redirect::handle("lock?orign=".urlencode($_SERVER['REQUEST_URI']), redirect::ROOT);
		}
		
		// informere om begrensningene?
		if (!preg_match("/^lock/u", $path) && (count(self::$user->lock) > 1 || !in_array("player", self::$user->lock)))
		{
			// melding
			echo '
<div class="section">
	<h1><img src="/static/icon/error.png" class="icon" />Begrenset tilgang</h1>
	<div class="col2_w">
		<div class="col_w left" style="width: 60%">
			<div class="col">
				<p>Din tilgang til Kofradia har blitt begrenset fordi:</p>
				<ul>';
			
			foreach (self::$user->lock as $row)
			{
				switch ($row)
				{
					case "birth":
						echo '
					<li><a href="'.$__server['relative_path'].'/lock?f=birth">Fødselsdato er ikke registrert.</a></li>';
					break;
					
					case "player":
						echo '
					<li><a href="'.$__server['relative_path'].'/lock?f=player">Du har ingen levende spiller.</a></li>';
					break;
					
					case "pass":
						echo '
					<li><a href="'.$__server['relative_path'].'/lock?f=pass">Brukeren din har ikke noe passord.</a></li>';
					break;
					
					default:
						throw new HSException("Ukjent lock: $row");
				}
			}
			
			echo '
				</ul>
				<p><img src="/static/icon/error_go.png" class="icon" /><a href="'.$__server['relative_path'].'/lock">Trykk her</a> for å gå videre til neste trinn.</p>
			</div>
		</div>
		<div class="col_w right" style="width: 40%">
			<div class="col">
				<p>Du har kun tilgang til disse funksjonene:</p>
				<ul>
					<li><a href="'.$__server['relative_path'].'/min_side">Min side</a></li>
					<li><a href="'.$__server['relative_path'].'/support/">Support</a></li>
					<li><a href="'.$__server['relative_path'].'/innboks">Meldingssystemet</a></li>
					<li><a href="'.$__server['relative_path'].'/betingelser">Betingelsene</a></li>
					<li><a href="'.$__server['relative_path'].'/crewet">Liste over Crewet</a></li>
				</ul> 
			</div>
		</div>
	</div>
</div>';
		}
	}
	
	/**
	 * Sjekk om en spiller tilhører den aktive brukeren
	 * @param player $up
	 */
	public static function is_active_user(player $up)
	{
		if (!self::$logged_in) return false;
		return self::$user->id == $up->data['up_u_id'];
	}
	
	/**
	 * Finnes det noe sesjonsdata?
	 * @param $name
	 */
	public static function data_exists($name)
	{
		return isset(self::$data[$name]);
	}
	
	/**
	 * Fjern sesjonsdata
	 * @param $name
	 */
	public static function data_remove($name)
	{
		if (isset(self::$data[$name]))
		{
			unset(self::$data[$name]);
			return true;
		}
		return false;
	}
	
	/**
	 * Lagre sesjonsdata
	 * @param $name
	 * @param $value
	 */
	public static function data_set($name, $value)
	{
		self::$data[$name] = $value;
	}
	
	/**
	 * Hent sesjonsdata
	 * @param string $name
	 * @param mixed $default_value
	 */
	public static function data_get($name, $default_value = null)
	{
		return isset(self::$data[$name]) ? self::$data[$name] : $default_value;
	}
	
	/**
	 * Finn tidsstempel for denne timen
	 */
	public static function get_secs_hour($time = null)
	{
		static $t;
		if ($time === null && $t) return $t;
		
		$date = ess::$b->date->get($time);
		$t = $date->format("U") - $date->format("i")*60 - $date->format("s");
		
		return $t;
	}
}