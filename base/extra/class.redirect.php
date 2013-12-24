<?php

class redirect
{
	/** Standard plassering */
	public static $location = false;
	
	/** Standard plassering (utgangspunktet) */
	public static $from = false;
	
	/** I aktiv mappe */
	const CURRENT = 0;
	
	/** Fra sideroot */
	const ROOT = 1;
	
	/** Fra serverroot */
	const SERVER = 2;
	
	/** Absolutt adresse */
	const ABSOLUTE = 3;
	
	/** Sette standard */
	public static function store($location, $from = self::CURRENT)
	{
		self::$location = $location;
		self::$from = $from;
	}
	
	/** Redirecte */
	public static function handle($location  = false, $from = NULL, $https = NULL)
	{
		global $__server, $_base;
		if ($from === NULL) $from = self::CURRENT;
		
		if ($location === false)
		{
			// refresh
			if (self::$location !== false)
			{
				$location = self::$location;
				$from = self::$from;
			}
			
			else
			{
				$location = $_SERVER['REQUEST_URI'];
				//$location = PHP_SELF;
				$from = self::SERVER;
			}
		}
		
		// prefix
		$prefix = ((HTTPS && $https !== false) || $https) && $__server['https_support'] ? $__server['https_path'] : $__server['http_path'];
		
		// fra sideroot
		if ($from == self::ROOT)
		{
			if (mb_substr($location, 0, 1) != "/") $location = "/" . $location;
			$location = $prefix . $__server['relative_path'] . $location;
		}
		
		// fra serverroot
		elseif ($from == self::SERVER)
		{
			if (mb_substr($location, 0, 1) != "/") $location = "/" . $location;
			$location = $prefix.$location;
		}
		
		// aktiv mappe
		elseif ($from != self::ABSOLUTE)
		{
			$p = str_replace("\\", "/", dirname(PHP_SELF));
			if ($p == "/") $p = "";
			if (mb_substr($location, 0, 1) != "/") $location = "/" . $location;
			$location = $prefix . $p . $location;
		}
		
		// definer brukernavnet
		$user = login::$logged_in ? login::$user->player->data['up_name'] : false;
		if (empty($user))
		{
			if (defined("AUTOSCRIPT"))
			{
				$user = "(autoscript)";
			}
			else
			{
				$user = "(anonym)";
			}
		}
		
		// lagre logg
		putlog("INT", "(".str_pad($_SERVER['REMOTE_ADDR'], 15, "_").") (".str_pad($user, 15, "_").") (REDIRECT) (".str_pad($_SERVER['REQUEST_METHOD'], 4, "_").") (http".(isset($_SERVER["SERVER_PORT_SECURE"]) ? 's' : '')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}) for $location");
		
		// oppdater brukerinfo
		if (login::$logged_in)
		{
			$_base->db->query("UPDATE users_players SET up_hits_redirect = up_hits_redirect + 1 WHERE up_id = ".login::$user->player->id);
			$_base->db->query("UPDATE users_hits SET uhi_hits_redirect = uhi_hits_redirect + 1 WHERE uhi_up_id = ".login::$user->player->id." AND uhi_secs_hour = ".login::$info['secs_hour']);
		}
		
		// oppdatere daglig stats (gjester)
		else
		{
			$date = $_base->date->get()->format("Y-m-d");
			
			// oppdater
			ess::$b->db->query("
				INSERT INTO stats_daily SET sd_date = '$date', sd_hits_redirect_g = 1
				ON DUPLICATE KEY UPDATE sd_hits_redirect_g = sd_hits_redirect_g + 1");
		}
		
		// send til siden
		@header("Location: $location");
		@ob_clean();
		die('<HTML><HEAD><TITLE>302 Found</TITLE></HEAD><BODY><H1>Found</H1>You have been redirected <A HREF="'.$location.'">here</A>.<P></BODY></HTML>');
	}
}