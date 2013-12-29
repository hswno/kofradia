<?php

// Kofradia
// (init fil)

new base();
class base
{
	public function __construct()
	{
		define("BASE_LOADED", true);
		
		// starter utdata buffer
		ob_start();
		
		// hent essenntials
		require "essentials.php";
		ess::$b->base = $this;
		
		// kjør scheduler
		if (!MAIN_SERVER) $this->scheduler();
		
		ess::$b->dt("load_es-gu_pre");
		
		// hent inn brukerinformasjon
		login::init();
		ess::$b->dt("post");
		
		// sjekk ssl
		$this->check_ssl();
		
		// brukerstæsj
		if (login::$logged_in)
		{
			$this->load_user_stuff();
		}
		
		// anonym statistikk?
		else
		{
			$this->update_anon_stats();
		}
		
		// logg visning
		$this->log_request();
		
		// sjekk referer
		$this->check_referer();
		
		define("SCRIPT_TIME_HALF", microtime(true)-SCRIPT_START);
		$profiler = \Kofradia\DB::getProfiler();
		define("QUERIES_TIME_HALF", $profiler ? $profiler->time : null);
		define("QUERIES_NUM_HALF", $profiler ? $profiler->num : null);
		
		$this->load_config();
		ess::$b->dt("base_loaded");
	}
	
	/** Kjøre scheduler */
	protected function scheduler()
	{
		// kjør scheduler
		require_once PATH_APP . "/scripts/scheduler.php";
	}
	
	/** Kontroller SSL status */
	protected function check_ssl()
	{
		// kontroller https status
		if (defined("FORCE_HTTPS") || defined("FORCE_HTTPS_ALWAYS"))
		{
			force_https();
		}
		elseif (!defined("OPTIONAL_HTTPS"))
		{
			// ikke benytt https hvis ikke brukeren krever det
			if (!login::$logged_in || !login::$info['ses_secure'])
			{
				force_https(false);
			}
		}
	}
	
	/** Hent diverse bruker funksjoner */
	protected function load_user_stuff()
	{
		// queries info
		if (access::has("admin") && isset($_COOKIE['show_queries_info']))
		{
			define("SHOW_QUERIES_INFO", true);
		}
	}
	
	/** Oppdater anonym besøksstatistikk */
	protected function update_anon_stats()
	{
		$date = ess::$b->date->get()->format("Y-m-d");
		
		// forsøk og oppdater
		$a = \Kofradia\DB::get()->exec("UPDATE stats_daily SET sd_hits_guests = sd_hits_guests + 1 WHERE sd_date = '$date'");
		
		// ingen oppdater? forsøk å sett inn
		if ($a == 0)
		{
			$a = \Kofradia\DB::get()->exec("INSERT IGNORE INTO stats_daily SET sd_date = '$date', sd_hits_guests = 1");
			
			// ble ikke satt inn? oppdater.. (da er den allerede satt inn av et annet script)
			if ($a == 0)
			{
				\Kofradia\DB::get()->exec("UPDATE stats_daily SET sd_hits_guests = sd_hits_guests + 1 WHERE sd_date = '$date'");
			}
		}
	}
	
	protected function log_request()
	{
		// logg
		if (!defined("AUTOSCRIPT"))
		{
			$userid = login::$logged_in ? login::$user->id : 0;
			$method = \Kofradia\DB::quote($_SERVER['REQUEST_METHOD']);
			$uri = \Kofradia\DB::quote($_SERVER['REQUEST_URI']);
			$time = time();
			$referer = isset($_SERVER['HTTP_REFERER']) ? \Kofradia\DB::quote($_SERVER['HTTP_REFERER']) : NULL;
			$ip = \Kofradia\DB::quoteNoNull($_SERVER['REMOTE_ADDR'], false);
			$browser = \Kofradia\DB::quote($_SERVER['HTTP_USER_AGENT']);
		
			$file = LOGFILE_REQUESTS;
			$fh = fopen($file, "a");
			if (!$fh) die("error (base) line ".__LINE__);
			fwrite($fh, "\r\n($userid, $method, $uri, $time, $referer, $ip, $browser),");
			fclose($fh);
		}
		
		$user = login::$logged_in ? login::$user->player->data['up_name'] : '';
		if (empty($user)) { if (defined("AUTOSCRIPT")) $user = "(autoscript)"; else $user = "(anonym):"; }
		putlog("INT", "(".str_pad($_SERVER['REMOTE_ADDR'], 15, "_").") (".str_pad($user, 15, "_").") (________) (".str_pad($_SERVER['REQUEST_METHOD'], 4, "_").") (http".(isset($_SERVER["SERVER_PORT_SECURE"]) ? 's' : '')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']})");
	}
	
	protected function check_referer()
	{
		global $__server;
		
		// referer?
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		if (!empty($referer))
		{
			// sjekk at den inneholder en webside..
			$matches = false;
			if (preg_match('~(https?://([^/\n\r\t]+))(/[^\n\r\t]*)?$~u', $referer, $matches))
			{
				$addr = mb_strtolower($matches[1]);
				if ($addr == $__server['http_path']) return;
				if ($__server['https_support'] && $addr == $__server['https_path']) return;
				if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'last_ref']) && $_SESSION[$GLOBALS['__server']['session_prefix'].'last_ref'] < time()-5) return;
				
				// lagre visning
				$_SESSION[$GLOBALS['__server']['session_prefix'].'last_ref'] = time();
				
				// logget inn?
				if (login::$logged_in)
				{
					putlog("NOTICE", "%c13Referer%c (Bruker: %u".login::$user->player->data['up_name']."%u; IP: {$_SERVER['REMOTE_ADDR']}; Adresse: %u{$_SERVER['REQUEST_URI']}%u):");
					putlog("NOTICE", "Kom fra: %u$referer%u");
					$up_id = login::$user->player->id;
				}
				else
				{
					putlog("NOTICE", "%c13Referer%c (Bruker: anonym; IP: {$_SERVER['REMOTE_ADDR']}; Adresse: %u{$_SERVER['REQUEST_URI']}%u):");
					putlog("NOTICE", "Kom fra: $referer");
					$up_id = 0;
				}
				
				$text = "Location: {$_SERVER['REQUEST_URI']}\nBrowser: {$_SERVER['HTTP_USER_AGENT']}\nIP: {$_SERVER['REMOTE_ADDR']}";
				\Kofradia\DB::get()->exec("INSERT INTO log_referers SET lr_up_id = ".\Kofradia\DB::quote($up_id).", lr_referer = ".\Kofradia\DB::quote($referer).", lr_time = ".time().", lr_data = ".\Kofradia\DB::quote($text));
			}
		}
	}
	
	/**
	 * Se om det ligger i config.php fil i mappen til dette scriptet og evt. last den inn
	 */
	protected function load_config()
	{
		ess::$b->dt("load_config");
		
		// se etter config fil i scriptmappen
		$config = dirname($_SERVER['SCRIPT_FILENAME']) . "/config.php";
		if (file_exists($config))
		{
			require $config;
		}
		
		ess::$b->dt("config_loaded");
	}
}
