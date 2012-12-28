<?php

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
		
		// fiks gpc variabler
		$this->fix_gpc();
		
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
	
	/** Fiks get, post, cookie */
	public function fix_gpc()
	{
		// fiks alle gpc variablene
		if (get_magic_quotes_gpc())
		{
			foreach ($_GET as $name => $val) $_GET[$name] = stripslashes_all($val);
			foreach ($_POST as $name => $val) $_POST[$name] = stripslashes_all($val);
			foreach ($_REQUEST as $name => $val) $_REQUEST[$name] = stripslashes_all($val);
		}
		
		// fiks utf8 ting (som javascript funksjonen encodeURIComponent f.eks. tuller til
		foreach ($_GET as $name => $val) $_GET[$name] = uri_fix($val);
		foreach ($_POST as $name => $val) $_POST[$name] = uri_fix($val);
		foreach ($_REQUEST as $name => $val) $_REQUEST[$name] = uri_fix($val);
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
			
			$contents = utf8_decode($contents);
			
			if (!preg_match("/^false/", $contents) && $contents != "" && $_SERVER['SCRIPT_NAME'] != "/irc/scripts/command.php" && $_SERVER['SCRIPT_NAME'] != "/irc/scripts/logs.php")
			{
				// siden er låst
				header("HTTP/1.0 503 Service Unavailiable");
				echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
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
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
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

function stripslashes_all($var)
{
	if (is_array($var))
	{
		foreach ($var as $key => $val)
		{
			$var[$key] = stripslashes_all($val);
		}
	}
	else
	{
		$var = stripslashes($var);
	}
	return $var;
}
function uri_fix($content)
{
	if (is_array($content)) return array_map("uri_fix", $content);
	
	$content = urlencode($content);
	return decodeURIComponent($content);
}

function encodeURIComponent($content)
{
	$result = "";
	
	for ($i = 0, $len = strlen($content); $i < $len; $i++)
	{
		$result .= encodeURIComponent_character(urlencode(substr($content, $i, 1)));
	}
	
	return $result;
}

function encodeURIComponent_character($str)
{
	static $table = array(
		/*"%21" => "!",
		"%27" => '"',
		"%28" => "(",
		"%29" => ")",
		"%2A" => "*",
		"%7E" => "~",*/
		"%80" => "%E2%82%AC",
		"%81" => "%C2%81",
		"%82" => "%E2%80%9A",
		"%83" => "%C6%92",
		"%84" => "%E2%80%9E",
		"%85" => "%E2%80%A6",
		"%86" => "%E2%80%A0",
		"%87" => "%E2%80%A1",
		"%88" => "%CB%86",
		"%89" => "%E2%80%B0",
		"%8A" => "%C5%A0",
		"%8B" => "%E2%80%B9",
		"%8C" => "%C5%92",
		"%8D" => "%C2%8D",
		"%8E" => "%C5%BD",
		"%8F" => "%C2%8F",
		"%90" => "%C2%90",
		"%91" => "%E2%80%98",
		"%92" => "%E2%80%99",
		"%93" => "%E2%80%9C",
		"%94" => "%E2%80%9D",
		"%95" => "%E2%80%A2",
		"%96" => "%E2%80%93",
		"%97" => "%E2%80%94",
		"%98" => "%CB%9C",
		"%99" => "%E2%84%A2",
		"%9A" => "%C5%A1",
		"%9B" => "%E2%80%BA",
		"%9C" => "%C5%93",
		"%9D" => "%C2%9D",
		"%9E" => "%C5%BE",
		"%9F" => "%C5%B8",
		"%A0" => "%C2%A0",
		"%A1" => "%C2%A1",
		"%A2" => "%C2%A2",
		"%A3" => "%C2%A3",
		"%A4" => "%C2%A4",
		"%A5" => "%C2%A5",
		"%A6" => "%C2%A6",
		"%A7" => "%C2%A7",
		"%A8" => "%C2%A8",
		"%A9" => "%C2%A9",
		"%AA" => "%C2%AA",
		"%AB" => "%C2%AB",
		"%AC" => "%C2%AC",
		"%AD" => "%C2%AD",
		"%AE" => "%C2%AE",
		"%AF" => "%C2%AF",
		"%B0" => "%C2%B0",
		"%B1" => "%C2%B1",
		"%B2" => "%C2%B2",
		"%B3" => "%C2%B3",
		"%B4" => "%C2%B4",
		"%B5" => "%C2%B5",
		"%B6" => "%C2%B6",
		"%B7" => "%C2%B7",
		"%B8" => "%C2%B8",
		"%B9" => "%C2%B9",
		"%BA" => "%C2%BA",
		"%BB" => "%C2%BB",
		"%BC" => "%C2%BC",
		"%BD" => "%C2%BD",
		"%BE" => "%C2%BE",
		"%BF" => "%C2%BF",
		"%C0" => "%C3%80",
		"%C1" => "%C3%81",
		"%C2" => "%C3%82",
		"%C3" => "%C3%83",
		"%C4" => "%C3%84",
		"%C5" => "%C3%85",
		"%C6" => "%C3%86",
		"%C7" => "%C3%87",
		"%C8" => "%C3%88",
		"%C9" => "%C3%89",
		"%CA" => "%C3%8A",
		"%CB" => "%C3%8B",
		"%CC" => "%C3%8C",
		"%CD" => "%C3%8D",
		"%CE" => "%C3%8E",
		"%CF" => "%C3%8F",
		"%D0" => "%C3%90",
		"%D1" => "%C3%91",
		"%D2" => "%C3%92",
		"%D3" => "%C3%93",
		"%D4" => "%C3%94",
		"%D5" => "%C3%95",
		"%D6" => "%C3%96",
		"%D7" => "%C3%97",
		"%D8" => "%C3%98",
		"%D9" => "%C3%99",
		"%DA" => "%C3%9A",
		"%DB" => "%C3%9B",
		"%DC" => "%C3%9C",
		"%DD" => "%C3%9D",
		"%DE" => "%C3%9E",
		"%DF" => "%C3%9F",
		"%E0" => "%C3%A0",
		"%E1" => "%C3%A1",
		"%E2" => "%C3%A2",
		"%E3" => "%C3%A3",
		"%E4" => "%C3%A4",
		"%E5" => "%C3%A5",
		"%E6" => "%C3%A6",
		"%E7" => "%C3%A7",
		"%E8" => "%C3%A8",
		"%E9" => "%C3%A9",
		"%EA" => "%C3%AA",
		"%EB" => "%C3%AB",
		"%EC" => "%C3%AC",
		"%ED" => "%C3%AD",
		"%EE" => "%C3%AE",
		"%EF" => "%C3%AF",
		"%F0" => "%C3%B0",
		"%F1" => "%C3%B1",
		"%F2" => "%C3%B2",
		"%F3" => "%C3%B3",
		"%F4" => "%C3%B4",
		"%F5" => "%C3%B5",
		"%F6" => "%C3%B6",
		"%F7" => "%C3%B7",
		"%F8" => "%C3%B8",
		"%F9" => "%C3%B9",
		"%FA" => "%C3%BA",
		"%FB" => "%C3%BB",
		"%FC" => "%C3%BC",
		"%FD" => "%C3%BD",
		"%FE" => "%C3%BE",
		"%FF" => "%C3%BF",
		"+" => "%20"
	);
	
	if (isset($table[$str])) return $table[$str];
	return $str;
}

function decodeURIComponent($content)
{
	$result = "";
	
	for ($i = 0, $len = strlen($content); $i < $len;)
	{
		$str = substr($content, $i, 9);
		$ret = decodeURIComponent_character($str);
		
		$result .= urldecode($ret[0]);
		$i += $ret[1];
	}
	
	return $result;
}

function decodeURIComponent_character($str)
{
	static $table_3 = array(
		"%E2%82%AC" => "%80",
		"%E2%80%9A" => "%82",
		"%E2%80%9E" => "%84",
		"%E2%80%A6" => "%85",
		"%E2%80%A0" => "%86",
		"%E2%80%A1" => "%87",
		"%E2%80%B0" => "%89",
		"%E2%80%B9" => "%8B",
		"%E2%80%98" => "%91",
		"%E2%80%99" => "%92",
		"%E2%80%9C" => "%93",
		"%E2%80%9D" => "%94",
		"%E2%80%A2" => "%95",
		"%E2%80%93" => "%96",
		"%E2%80%94" => "%97",
		"%E2%84%A2" => "%99",
		"%E2%80%BA" => "%9B"
	);
	static $table_2 = array(
		"%C2%81" => "%81",
		"%C6%92" => "%83",
		"%CB%86" => "%88",
		"%C5%A0" => "%8A",
		"%C5%92" => "%8C",
		"%C2%8D" => "%8D",
		"%C5%BD" => "%8E",
		"%C2%8F" => "%8F",
		"%C2%90" => "%90",
		"%CB%9C" => "%98",
		"%C5%A1" => "%9A",
		"%C5%93" => "%9C",
		"%C2%9D" => "%9D",
		"%C5%BE" => "%9E",
		"%C5%B8" => "%9F",
		"%C2%A0" => "%A0",
		"%C2%A1" => "%A1",
		"%C2%A2" => "%A2",
		"%C2%A3" => "%A3",
		"%C2%A4" => "%A4",
		"%C2%A5" => "%A5",
		"%C2%A6" => "%A6",
		"%C2%A7" => "%A7",
		"%C2%A8" => "%A8",
		"%C2%A9" => "%A9",
		"%C2%AA" => "%AA",
		"%C2%AB" => "%AB",
		"%C2%AC" => "%AC",
		"%C2%AD" => "%AD",
		"%C2%AE" => "%AE",
		"%C2%AF" => "%AF",
		"%C2%B0" => "%B0",
		"%C2%B1" => "%B1",
		"%C2%B2" => "%B2",
		"%C2%B3" => "%B3",
		"%C2%B4" => "%B4",
		"%C2%B5" => "%B5",
		"%C2%B6" => "%B6",
		"%C2%B7" => "%B7",
		"%C2%B8" => "%B8",
		"%C2%B9" => "%B9",
		"%C2%BA" => "%BA",
		"%C2%BB" => "%BB",
		"%C2%BC" => "%BC",
		"%C2%BD" => "%BD",
		"%C2%BE" => "%BE",
		"%C2%BF" => "%BF",
		"%C3%80" => "%C0",
		"%C3%81" => "%C1",
		"%C3%82" => "%C2",
		"%C3%83" => "%C3",
		"%C3%84" => "%C4",
		"%C3%85" => "%C5",
		"%C3%86" => "%C6",
		"%C3%87" => "%C7",
		"%C3%88" => "%C8",
		"%C3%89" => "%C9",
		"%C3%8A" => "%CA",
		"%C3%8B" => "%CB",
		"%C3%8C" => "%CC",
		"%C3%8D" => "%CD",
		"%C3%8E" => "%CE",
		"%C3%8F" => "%CF",
		"%C3%90" => "%D0",
		"%C3%91" => "%D1",
		"%C3%92" => "%D2",
		"%C3%93" => "%D3",
		"%C3%94" => "%D4",
		"%C3%95" => "%D5",
		"%C3%96" => "%D6",
		"%C3%97" => "%D7",
		"%C3%98" => "%D8",
		"%C3%99" => "%D9",
		"%C3%9A" => "%DA",
		"%C3%9B" => "%DB",
		"%C3%9C" => "%DC",
		"%C3%9D" => "%DD",
		"%C3%9E" => "%DE",
		"%C3%9F" => "%DF",
		"%C3%A0" => "%E0",
		"%C3%A1" => "%E1",
		"%C3%A2" => "%E2",
		"%C3%A3" => "%E3",
		"%C3%A4" => "%E4",
		"%C3%A5" => "%E5",
		"%C3%A6" => "%E6",
		"%C3%A7" => "%E7",
		"%C3%A8" => "%E8",
		"%C3%A9" => "%E9",
		"%C3%AA" => "%EA",
		"%C3%AB" => "%EB",
		"%C3%AC" => "%EC",
		"%C3%AD" => "%ED",
		"%C3%AE" => "%EE",
		"%C3%AF" => "%EF",
		"%C3%B0" => "%F0",
		"%C3%B1" => "%F1",
		"%C3%B2" => "%F2",
		"%C3%B3" => "%F3",
		"%C3%B4" => "%F4",
		"%C3%B5" => "%F5",
		"%C3%B6" => "%F6",
		"%C3%B7" => "%F7",
		"%C3%B8" => "%F8",
		"%C3%B9" => "%F9",
		"%C3%BA" => "%FA",
		"%C3%BB" => "%FB",
		"%C3%BC" => "%FC",
		"%C3%BD" => "%FD",
		"%C3%BE" => "%FE",
		"%C3%BF" => "%FF"
	);
	static $table_1 = array(
		"%20" => "+"
	);
	static $table_0 = array(
		"!" => "%21",
		'"' => "%27",
		"(" => "%28",
		")" => "%29",
		"*" => "%2A",
		"~" => "%7E"
	);
	
	// 3 sekvenser
	if (isset($table_3[$str])) return array($table_3[$str], 9);
	
	// 2 sekvenser
	$str = substr($str, 0, 6);
	if (isset($table_2[$str])) return array($table_2[$str], 6);
	
	// 1 sekvens
	$str = substr($str, 0, 3);
	if (isset($table_1[$str])) return array($table_1[$str], 3);
	if (substr($str, 0, 1) == "%" && strlen($str) == 3)
	{
		return array($str, 3);
	}
	
	// vanlig tegn
	$str = substr($str, 0, 1);
	if (isset($table_0[$str])) return array($table_0[$str], 1);
	
	return array($str, 1);
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