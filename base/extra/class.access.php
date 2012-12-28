<?php

/**
 * Tilgangssystem
 */
class access
{
	/**
	 * Sjekk for tilgang
	 * @param string $access_name
	 * @param boolean $allow_admin
	 * @param integer $access_level
	 * @param mixed $skip_extended_access_check "login" for å logge sende til logg inn siden
	 */
	public static function has($access_name, $allow_admin = NULL, $access_level = NULL, $skip_extended_access_check = NULL)
	{
		global $_game;
		
		if ($access_level === NULL)
		{
			if (!login::$logged_in) return false;
			$access_level = login::$user->data['u_access_level'];
		}
		
		$exists = isset($_game['access'][$access_name]);
		$access = $exists ? $_game['access'][$access_name] : NULL;
		
		// kontroller extended_access
		if (!$skip_extended_access_check || $skip_extended_access_check === "login")
		{
			if (!isset(login::$extended_access['authed']) && (!$exists || (!in_array(0, $access) && !in_array(1, $access))))
			{
				// logge inn utvidede tilganger?
				if ($skip_extended_access_check === "login" && login::$extended_access)
				{
					// send til logg inn siden
					redirect::handle("extended_access?orign=".urlencode($_SERVER['REQUEST_URI']), redirect::ROOT);
				}
				
				return false;
			}
		}
		
		// bruker er admin? => true
		if (($allow_admin === true || $allow_admin === NULL) && $access_name != "sadmin" && ($access_level == $_game['access']['admin'][0] || $access_level == $_game['access']['sadmin'][0]))
		{
			return true;
		}
		
		// skjekk om brukeren har en av tilgangsid-ene til tilgangen => true
		if ($exists && in_array($access_level, $_game['access'][$access_name]))
		{
			return true;
		}
		
		// tilgangen finnes ikke eller brukeren har ikke tilgang => false
		return false;
	}
	
	/**
	 * Krev at brukeren må ha en bestemt tilgang for å vise siden
	 * @param string $access_name
	 * @param boolean $allow_admin
	 * @param integer $access_level
	 * @param boolean $skip_extended_access_check
	 */
	public static function need($access_name, $allow_admin = NULL, $access_level = NULL, $skip_extended_access_check = NULL)
	{
		global $_base;
		if (self::has($access_name, $allow_admin, $access_level, $skip_extended_access_check))
		{
			// har tilgang
			return;
		}
		
		$name = self::name($access_name);
		
		// ajax?
		if (defined("SCRIPT_AJAX")) ajax::text("ERROR:NO-ACCESS,NEED:$name", ajax::TYPE_INVALID);
		
		// har ikke tilgang
		echo "<h1>Ikke tilgang!</h1><p>Du har ikke tilgang til denne siden!</p><p>Den er forebeholdt <b>$name</b>.</p>";
		$_base->page->load();
	}
	
	/**
	 * Er brukeren nostat?
	 * @param optional integer $access_level
	 * @return boolean
	 */
	public static function is_nostat($access_level = NULL)
	{
		global $_game;
		if (!$access_level)
		{
			if (!login::$logged_in) return false;
			$access_level = login::$user->data['u_access_level'];
		}
		return $access_level >= $_game['access_noplay'];
	}
	
	/**
	 * Krev at brukeren er nostat for å vise siden
	 * @param optional integer $access_level
	 */
	public static function need_nostat($access_level = NULL)
	{
		if (!self::is_nostat($access_level))
		{
			global $_base;
			
			// ajax?
			if (defined("SCRIPT_AJAX")) ajax::text("ERROR:NO-ACCESS,NEED:NOSTAT", ajax::TYPE_INVALID);
			
			echo "<h1>Ikke tilgang</h1><p>Du har ikke tilgang til denne siden!</p><p>Den er forebeholdt brukere av typen <b>NoStat</b>.</p>";
			$_base->page->load();
		}
	}
	
	/**
	 * Krev en bestemt bruker
	 * @param mixed brukerid/e-post/brukernavn
	 * @param optional mixed brukerid/e-post/brukernavn
	 * @param ..
	 */
	public static function need_userid()
	{
		global $_base;
		
		if (login::$logged_in)
		{
			for ($i = 0; $i < func_num_args(); $i++)
			{
				$req = func_get_arg($i);
				if (is_int($req))
				{
					if ($req == login::$user->id) return;
				}
				else
				{
					if ($req == login::$user->data['u_email']) return;
					if ($req == login::$user->player->data['up_name']) return;
				}
			}
		}
		
		// ajax?
		if (defined("SCRIPT_AJAX")) ajax::text("ERROR:NO-ACCESS,DEFINED-USERS-ONLY", ajax::TYPE_INVALID);
		
		echo "<h1>Ikke tilgang</h1><p>Du har ikke tilgang til denne siden!</p><p>Den er forebeholdt bestemte brukere.</p>";
		$_base->page->load();
	}
	
	/**
	 * Finn ut hvilken tilgangstype brukeren har
	 * @param integer $access_level
	 * @return string
	 */
	public static function type($access_level)
	{
		global $_game;
		
		foreach ($_game['access'] as $access_name => $access_levels)
		{
			if ($access_levels[0] == $access_level)
			{
				return $access_name;
			}
		}
		
		return "Ukjent";
	}
	
	/**
	 * Finn ut hvilke tilgangstyper vi har tilgang til
	 * @param integer $access_level
	 * @return string
	 */
	public static function types($access_level)
	{
		global $_game;
		
		$names = array();
		foreach ($_game['access'] as $access_name => $access_levels)
		{
			if (in_array($access_level, $access_levels))
			{
				$names[] = $access_name;
			}
		}
		
		return $names;
	}
	
	/**
	 * Finn ut navnet/tittelen for et tilgangsnivå
	 *
	 * @param string $access_type (f.eks. forum_mod)
	 * @return string f.eks. Forum moderator
	 */
	public static function name($access_type)
	{
		global $_game;
		
		if (isset($_game['access_names'][$access_type]))
		{
			return $_game['access_names'][$access_type];
		}
		
		// fant ikke noe navn, returner type
		return $access_type;
	}
	
	/**
	 * Finn det som skal inn i class="" i html
	 * @param string $access_type
	 * @return string
	 */
	public static function html_class($access_type)
	{
		global $_game;
		
		if (isset($_game['access_colors'][$access_type]))
		{
			return $_game['access_colors'][$access_type];
		}
		
		return false;
	}
	
	/**
	 * Finn spesielt oppsett for spillernavnet i html, %user må byttes ut med spillernavn
	 * @param string $access_type
	 * @return string
	 */
	public static function html_format($access_type)
	{
		global $_game;
		
		if (isset($_game['access_formats'][$access_type]))
		{
			return $_game['access_formats'][$access_type];
		}
		
		return false;
	}
	
	/**
	 * Ikke tillatt gjester på denne siden
	 */
	public static function no_guest()
	{
		// ikke logget inn?
		if (!login::$logged_in)
		{
			$param = "";
			if ($_SERVER['REQUEST_URI'] != ess::$s['relative_path']."/") $param = "?orign=".urlencode($_SERVER['REQUEST_URI']);
			
			// send til logg inn siden
			redirect::handle("/".$param, redirect::ROOT);
		}
		
		return true;
	}
	
	/**
	 * Ikke tillatt innloggede brukere på denne siden
	 */
	public static function no_user()
	{
		// logget inn?
		if (login::$logged_in)
		{
			// send til hovedsiden
			redirect::handle("/", redirect::ROOT);
		}
		
		return true;
	}
}
