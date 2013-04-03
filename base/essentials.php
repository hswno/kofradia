<?php

mb_internal_encoding("utf-8");

class ess
{
	/**
	 * Snarvei til $_base utenom globals
	 * @var essentials
	 */
	public static $b;
	
	/**
	 * Snarvei til $__server utenom globals
	 */
	public static $s;
	
	/**
	 * Snarvei til $_game utenom globals
	 */
	public static $g;
	
	/**
	 * Hent ut verdi fra session
	 */
	public static function session_get($name, $default = null)
	{
		global $__server;
		sess_start();
		
		if (!isset($_SESSION[$__server['session_prefix'].$name])) return $default;
		
		return $_SESSION[$__server['session_prefix'].$name];
	}
	
	/**
	 * Lagre verdi i session
	 */
	public static function session_put($name, $value)
	{
		global $__server;
		sess_start();
		
		$_SESSION[$__server['session_prefix'].$name] = $value;
	}
}

global $__server, $_game;
ess::$b = new essentials();
ess::$s = &$__server;
ess::$g = &$_game;

class essentials
{
	public $time_start = 0;
	public $db_debug = false;
	public $data = array();
	
	/** For å holde tidsoversikt i scriptet */
	public $time_debug = array();
	
	/**
	 * @var base
	 */
	public $base;
	
	/**
	 * @var db_wrap
	 */
	public $db;
	
	/**
	 * @var page
	 */
	public $page;
	
	/**
	 * @var date
	 */
	public $date;
	
	/** Constructor */
	public function __construct()
	{
		// sørg for at $_base blir til dette objektet
		global $_base;
		$_base = $this;
		
		unset($this->db);
		unset($this->page);
		unset($this->date);
		
		$this->time_start = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		
		define("SCRIPT_START", microtime(true));
		header("X-Powered-By: hsw.no");
		
		if (defined("BASE_LOADED"))
		{
			define("ESSENTIALS_ONLY", false);
		}
		else
		{
			define("ESSENTIALS_ONLY", true);
		}
		
		// sett opp nødvendige variabler
		if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
		if (!isset($_SERVER['REQUEST_METHOD'])) $_SERVER['REQUEST_METHOD'] = "CRON";
		if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
		if (!isset($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = "127.0.0.1";
		if (!isset($_SERVER['HTTP_USER_AGENT'])) $_SERVER['HTTP_USER_AGENT'] = '';
		if (!isset($_SERVER['HTTP_REFERER'])) $_SERVER['HTTP_REFERER'] = '';
		define("PHP_SELF", $_SERVER['SCRIPT_NAME']);
		
		// egen versjon av REDIRECT_URL (som ikke er tilgjengelig via CGI)
		$_SERVER['REDIR_URL'] = $_SERVER['REQUEST_URI'];
		if (($pos = strpos($_SERVER['REDIR_URL'], "?")) !== false) $_SERVER['REDIR_URL'] = substr($_SERVER['REDIR_URL'], 0, $pos);
		
		// hent innstillinger
		require "inc.innstillinger_pre.php";
		
		// set timeout
		if (MAIN_SERVER) @set_time_limit(10);
		else @set_time_limit(120);
		
		$this->dt("check_lockdown_pre");
		
		// sjekk lock status
		if (MAIN_SERVER) $this->check_lockdown();
		
		$this->dt("mainfunctions_settings_pre");
		
		// hent flere filer
		require "inc.mainfunctions.php";
		require "inc.innstillinger.php";
		
		$this->dt("post");
		
		// sett opp exception handler
		set_exception_handler(array("sysreport", "exception_handler"));
		
		// skal vi debugge databasen eller ikke?
		if (isset($_COOKIE['show_queries_info']))
		{
			$this->db_debug = true;
		}
		
		$this->init_time();
		
		// sett opp adresse til logg filene som skal brukes
		$del = 3;
		$date = $_base->date->get();
		$hour = floor($date->format("H")/$del)*$del; // del opp i hver 3. time
		$hour = str_pad($hour, 2, "0", STR_PAD_LEFT)."-".str_pad($hour+$del-1, 2, "0", STR_PAD_LEFT);
		$date = $date->format("Ymd_").$hour;
		
		define("LOGFILE", GAMELOG_DIR . '/gamelog_'.$date.'.log');
		define("LOGFILE_INT", GAMELOG_DIR . '/gamelog_int_'.$date.'.log');
		define("LOGFILE_REQUESTS", GAMELOG_DIR . '/gamelog_requests_'.$date.'.log');
		
		// koble til databasen
		#$this->__get("db");
		
		$this->dt("check_ip_ban_pre");
		
		// sjekk for IP-ban
		$this->check_ip_ban();
		
		$this->dt("post");
	}
	
	/** Lagre scripttid (debug time) */
	public function dt($name)
	{
		$this->time_debug[] = array($name, microtime(true));
	}
	
	/**
	 * Fiks objektet hvis det har vært serialized
	 */
	public function __wakeup()
	{
		// slett objektene på nytt hvis de ikke er initialisert med __get
		if (!isset($this->db)) unset($this->db);
		if (!isset($this->page)) unset($this->page);
		if (!isset($this->date)) unset($this->date);
	}
	
	/** Hent inn moduler */
	public function __get($module)
	{
		switch ($module)
		{
			case "db":
				// hent inn databasemodulen
				self::load_module("db_wrap");
				$this->db = $this->db_debug ? new db_wrap_debug() : new db_wrap();
				
				// koble til databasen
				$this->db->connect(DBHOST, DBUSER, DBPASS, DBNAME);
				
				return $this->db;
			
			case "page":
				// hent inn sidemodulen
				$this->page = new page();
				
				return $this->page;
			
			case "date":
				// hent inn tidsbehandling
				$this->date = new date();
				
				return $this->date;
			
			default:
				throw new HSException("Ukjent modul: $module");
		}
	}
	
	/**
	 * Hent inn tilleggscript (gjerne slags moduler)
	 * Sørger for at samme script ikke blir lastet inn flere ganger
	 * @param string $name delvis navn på scriptet
	 * @param string $type type script (class, func, div)
	 */
	public static function load_module($name, $type = "class")
	{
		static $loaded = array();
		
		// aliaser
		$aliases = array(
			"weapon" => "drap",
			"protection" => "drap"
		);
		if (isset($aliases[$name])) $name = $aliases[$name];
		
		// allerede lastet inn? (for å slippe require_once)
		if (in_array($name, $loaded)) return;
		
		// type må være class eller func
		if ($type != "class" && $type != "func" && $type != "div")
		{
			if (isset($GLOBALS['load_module_ignore'])) return;
			throw new HSException("Ugyldig type: $type");
		}
		
		if (isset($GLOBALS['load_module_ignore']) && !file_exists(ROOT."/base/extra/".$type.".".$name.".php")) return;
		$loaded[] = $name;
		
		// en side-klasse?
		if (substr($name, 0, 5) == "page_")
		{
			$name = substr($name, 5);
			require ROOT."/base/pages/$name.php";
		}
		
		else
		{
			require ROOT."/base/extra/$type.$name.php";
		}
	}
	
	/** Sjekk lock status */
	private function check_lockdown()
	{
		$fh = @fopen("/home/smafia/sm_base/lockdown.sm", "r");
		if ($fh && !preg_match(ADMIN_IP, $_SERVER['REMOTE_ADDR']))
		{
			$contents = "";
			while (!feof($fh))
			{
				$contents .= fread($fh, 8192);
			}
			
			if (!preg_match("/^false/u", $contents) && $contents != "" && $_SERVER['SCRIPT_NAME'] != "/irc/scripts/command.php" && $_SERVER['SCRIPT_NAME'] != "/irc/scripts/logs.php")
			{
				// siden er låst
				header("HTTP/1.0 503 Service Unavailiable");
				echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>Midlertidig utilgjengelig</title>
<style type="text/css">
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
-->
</style>
</head>
<body>
<h1>Midlertidig utilgjengelig</h1>
<p>Begrunnelse: '.$contents.'</p>
<p class="hsws"><a href="http://hsw.no/">hsw.no</a></p>
</body>
</html>';
				die;
			}
		}
	}
	
	/** Sett opp tidsvariabler */
	private function init_time()
	{
		global $__server;
		
		// sett opp tidssone
		$this->timezone = new DateTimeZone($__server['timezone']);
	}
	
	/** Sjekk for IP-ban */
	private function check_ip_ban()
	{
		global $_base;
		
		// sjekk for IP-ban
		if ($_SERVER['REQUEST_METHOD'] == "CRON") return;
		
		// allerede sjekket og OK?
		if (cache::fetch("ip_ok_".$_SERVER['REMOTE_ADDR'])) return;
		
		$ip = $this->db->quote(to_float(ip2long($_SERVER['REMOTE_ADDR'])));
		$time = time();
		
		$result = $this->db->query("SELECT bi_ip_start, bi_ip_end, bi_time_start, bi_time_end, bi_reason FROM ban_ip WHERE $ip BETWEEN bi_ip_start AND bi_ip_end AND IF(ISNULL(bi_time_end), $time >= bi_time_start, $time BETWEEN bi_time_start AND bi_time_end) ORDER BY bi_ip_end - bi_ip_start");
		
		// fant ingen IP-ban oppføring
		if (mysql_num_rows($result) == 0)
		{
			// sjekk om vi venter en kommende IP-ban
			$result = $_base->db->query("SELECT bi_time_start FROM ban_ip WHERE $ip BETWEEN bi_ip_start AND bi_ip_end AND $time <= bi_time_start ORDER BY bi_time_start LIMIT 1");
			if (mysql_num_rows($result) > 0)
			{
				$next = mysql_result($result, 0, 0);
				
				// marker som ok for tiden før IP-ban starter
				cache::store("ip_ok_".$_SERVER['REMOTE_ADDR'], true, $next-$time);
				return;
			}
			
			// marker som ok
			cache::store("ip_ok_".$_SERVER['REMOTE_ADDR'], true);
			return;
		}
		
		// utestengt via IP
		// mer enn 1 uke vil vise som ubestemt tid
		
		// sett opp grunner
		$ban_end = 0;
		$reasons = array();
		while ($row = mysql_fetch_assoc($result))
		{
			if ($ban_end !== false && empty($row['bi_time_end'])) { $ban_end = false; }
			elseif ($ban_end !== false && $row['bi_time_end'] > $ban_end) { $ban_end = $row['bi_time_end']; }
			
			// sett opp IP-adresse (range?)
			$ip = '<b>'.long2ip($row['bi_ip_start']);
			if ($row['bi_ip_start'] != $row['bi_ip_end'])
			{
				// range
				$ip .= ' - ' . long2ip($row['bi_ip_end']);
			}
			$ip .= '</b>';
			
			
			// grunn oppgitt?
			if (empty($row['bi_reason']))
			{
				// nei
				$reason = 'Grunn ikke oppgitt.';
			}
			else
			{
				// ja
				$reason = game::bb_to_html($row['bi_reason']);
			}
			
			#$reasons[] = '<p>'.$ip.': '.$reason.'</p>';
			$reasons[] = '<fieldset><legend>'.$ip.'</legend><p>'.$reason.'</p></fieldset>';
		}
		
		// "jukse" til ubestemt tid?
		#if ($ban_end !== false && $ban_end > time()+604800) $ban_end = false;
		
		#$timeinfo = $ban_end === false ? '<p>Din IP-adresse er utestengt på ubestemt tid.</p>' : '<p>Din IP-adresse er utestengt til <b>'.$_base->date->get($ban_end)->format(date::FORMAT_SEC).'</b>.</p>';
		
		putlog("ABUSE", "%c8%bIP-Blokk:%b%c %u{$_SERVER['REMOTE_ADDR']}%u - {$_SERVER['HTTP_USER_AGENT']}");
		
		// send feilmelding etc
		header("HTTP/1.0 403 Forbidden");
		echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>IP-blokkering</title>
<style type="text/css">
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
fieldset { margin: 10px 0 }
fieldset p { margin: 8px 3px 5px; padding: 0 }
legend { color: #FFFFFF; background-color: #222222; padding: 3px 5px; border: 3px solid #FFFFFF; border-width: 0 3px }
-->
</style>
</head>
<body>
<h1>IP-blokkering</h1>
<p>Din IP-adresse er blokkert/utestengt.</p>
<p>IP-adresse og eventuelle grunner:</p>
'.implode("\n", $reasons)./*'
'.$timeinfo.*/'
<p class="hsws"><a href="http://hsw.no/">hsw.no</a></p>
</body>
</html>';
		
		die;
	}
}


/**
 * Utvidelse av DateTime objektet
 */
class DateTimeHSW extends DateTime
{
	/**
	 * Formatter dato og tidspunkt
	 *
	 * @param format $format
	 * @return string
	 */
	public function format($format = 0)
	{
		// standard formattering
		if ($format === date::FORMAT_NORMAL)
		{
			global $_base;
			return $_base->date->format($this);
		}
		
		// med sekunder
		if ($format === date::FORMAT_SEC)
		{
			global $_base;
			return $_base->date->format($this, true);
		}
		
		// uten tidspunkt
		if ($format === date::FORMAT_NOTIME)
		{
			global $_base;
			return $_base->date->format($this, false, false);
		}
		
		// kun måned?
		if ($format === date::FORMAT_MONTH)
		{
			global $_lang;
			return $_lang['months'][$this->format("n")];
		}
		
		// kun ukedag?
		if ($format === date::FORMAT_WEEKDAY)
		{
			global $_lang;
			return $_lang['weekdays'][$this->format("w")];
		}
		
		// la DateTime ta seg av formatteringen
		return parent::format($format);
	}
}

/**
 * Tidssystem
 */
class date
{
	/** Formattere dato normalt (med tidspunkt men uten sekunder) */ 
	const FORMAT_NORMAL = 0;
	
	/** Formattere dato med sekunder */
	const FORMAT_SEC = 1;
	
	/** Formattere dato uten tidspunkt */
	const FORMAT_NOTIME = 2;
	
	/** Formattere kun med navn på måned */
	const FORMAT_MONTH = 3;
	
	/** Formattere kun med navn på ukedag */
	const FORMAT_WEEKDAY = 4;
	
	/** Variabel for å holde tidssone objektet */
	public $timezone = NULL;
	
	/** Variabel for å holde nåværende tidspunkt objektet */
	public $now = NULL;
	
	/** Constructor */
	public function __construct()
	{
		$this->timezone = new DateTimeZone("Europe/Oslo");
	}
	
	/**
	 * Hent ut tidsobjekt fra unixtime
	 * @param int unix timestamp $time
	 * @return DateTimeHSW
	 */
	public function get($time = NULL)
	{
		// akkurat nå?
		if ($time === NULL)
		{
			// benytte lagret tidspunkt?
			if ($this->now)
			{
				// kopier objektet og returner det
				return clone $this->now;
			}
			
			global $_base;
			$time = $_base->time_start;
		}
		
		$date = new DateTimeHSW("@".((int)$time));
		$date->setTimezone($this->timezone);
		
		// lagre for muligens senere bruk?
		if ($time === NULL)
		{
			$this->now = $date;
			
			// kopier objektet og returner det
			return clone $this->new;
		}
		
		return $date;
	}
	
	/**
	 * Hent ut tidsobjekt fra tekst
	 * @param string $time
	 * @return DateTimeHSW
	 */
	public function parse($time)
	{
		$date = new DateTimeHSW($time);
		$date->setTimezone($this->timezone);
		
		return $date;
	}
	
	/**
	 * Formatter dato og tidspunkt
	 *
	 * @param DateTime objekt $date
	 * @param vise sekunder $show_seconds
	 * @param vise timer $show_hour
	 * @param tidspunkt som bold $bold
	 * @return string
	 */
	public function format(DateTime $date, $show_seconds = false, $show_hour = true, $bold = false)
	{
		global $_lang;
		
		$hour = '';
		if ($show_hour)
		{
			$hour = 'H:i'.($show_seconds ? ':s' : '');
			$hour = $bold ? ' \\<\\b\\>'.$hour.'\\<\\/\\b\\>' : ' '.$hour;
		}
		
		return $date->format("j. ") . $_lang['months'][$date->format("n")] . $date->format(" Y$hour");
	}
}

/** Egen exception type */
class HSException extends Exception {}
class HSNotLoggedIn extends HSException {}