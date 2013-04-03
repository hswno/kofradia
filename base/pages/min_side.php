<?php

class page_min_side
{
	/**
	 * Lage linker til menyen
	 * @param string $html
	 * @param string $name
	 * @param string $addr_suffix
	 * @param string $type
	 * @return string
	 */
	public static function link($html, $name, $addr_suffix = "", $type = "subpage")
	{
		$addr = self::addr($name, $addr_suffix, $type);
		$active = $type == "subpage" ? $name == self::$subpage : $type == self::$active_type;
		
		return '<a href="'.htmlspecialchars($addr).'"'.($active ? ' class="active"' : '').'>'.$html.'</a>';
	}
	
	/**
	 * Lage adresse til en side
	 * @param $name
	 * @param $addr_suffix
	 * @param $type
	 * @return string
	 */
	public static function addr($name = NULL, $addr_suffix = "", $type = "subpage")
	{
		if ($name === NULL) $name = self::$subpage;
		
		$addr = "min_side";
		$check_type = $type == "subpage" ? self::$active_type : $type;
		switch ($check_type)
		{
			case "player":
				if (!self::$active_own || !self::$active_player->active || self::$active_player->id != self::$active_user->data['u_active_up_id']) $addr .= "?up_id=".self::$active_player->id;
				break;
			case "user":
			case "stats":
				if (!self::$active_own) $addr .= "?u_id=".self::$active_user->id;
				elseif (self::$active_player->active) $addr .= "?u";
				if ($check_type == "stats") $addr .= (strpos($addr, "?") !== false ? "&" : "?") . "stats";
				break;
		}
		if ($name != "") $addr .= (strpos($addr, "?") !== false ? "&" : "?") . "a=$name";
		if ($addr_suffix != "") $addr .= (strpos($addr, "?") !== false ? "&" : "?") . $addr_suffix;
		
		return $addr;
	}
	
	/**
	 * Aktiv bruker
	 * @var user
	 */
	public static $active_user;
	
	/**
	 * Aktiv spiller
	 * @var player
	 */
	public static $active_player;
	
	/** Aktiv type */
	public static $active_type;
	
	/** Egen bruker? */
	public static $active_own;
	
	/** Underside */
	public static $subpage;
	
	/** Tilgang til spillerstats */
	public static $pstats = true;
	
	/**
	 * Hovedfunksjonen
	 */
	public static function main()
	{
		// hent informasjon om det vi skal vise
		if (isset($_GET['up_id']))
		{
			// forsøk å hent denne spilleren
			$up_id = (int) $_GET['up_id'];
			if ($up_id != login::$user->data['u_active_up_id'])
			{
				$player = new player($up_id);
			}
			else {
				$player = login::$user->player;
			}
			
			// er ikke dette vår spiller?
			if (!$player->data || login::$user->id != $player->data['up_u_id'])
			{
				// må logge inn i utvidede tilganger?
				if ($player->data && login::$extended_access && !login::$extended_access['authed'])
				{
					redirect::handle("extended_access?orign=".urlencode($_SERVER['REQUEST_URI']));
				}
				
				// har vi ikke tilgang til å vise andre spillere?
				elseif (!access::has("crewet"))
				{
					ess::$b->page->add_message('Du har ikke tilgang til å vise andre spillere enn dine egne. <a href="min_side">Tilbake</a>', "error");
					ess::$b->page->load();
				}
				
				// finnes ikke?
				elseif (!$player->data)
				{
					ess::$b->page->add_message("Fant ikke spilleren.", "error");
					redirect::handle("/admin/brukere/finn", redirect::ROOT);
				}
			}
			
			self::$active_type = "player";
			self::$active_player = $player;
			self::$active_user = $player->user;
			unset($player);
		}
		
		// hent informasjon om det vi skal vise
		elseif (isset($_GET['u_id']))
		{
			// forsøk å hent brukeren
			$u_id = (int) $_GET['u_id'];
			$user = $u_id == login::$user->id ? login::$user : new user($u_id);
			
			// er ikke dette vår bruker?
			if (!$user->data || login::$user->id != $user->id)
			{
				// har vi ikke tilgang til å vise andre spillere?
				if (!access::has("crewet"))
				{
					ess::$b->page->add_message('Du har ikke tilgang til å vise andre brukere enn din egen. <a href="min_side">Tilbake</a>', "error");
					ess::$b->page->load();
				}
				
				// finnes ikke?
				elseif (!$user->data)
				{
					ess::$b->page->add_message("Fant ikke brukeren.", "error");
					redirect::handle("/admin/brukere/finn", redirect::ROOT);
				}
			}
			
			self::$active_type = "user";
			self::$active_user = $user;
			self::$active_player = $user->player;
			unset($user);
		}
		
		else
		{
			self::$active_user = login::$user;
			self::$active_player = login::$user->player;
			self::$active_type = self::$active_player->active && !isset($_GET['u']) ? "player" : "user";
		}
		
		// egen bruker?
		self::$active_own = login::$user->id == self::$active_user->id;
		if (!self::$active_own && !access::has("mod")) self::$pstats = false;
		
		// hendelser?
		if (isset($_GET['log']))
		{
			redirect::handle(self::addr("log", "", "player"));
		}
		
		// statistikk?
		if (isset($_GET['stats']))
		{
			self::$active_type = "stats";
		}
		
		// informasjon om at dette er en annen person sin bruker/spiller
		if (!self::$active_own && false)
		{
			if (self::$active_type == "player") ess::$b->page->add_message("Denne spilleren tilhører ikke deg.");
			else ess::$b->page->add_message("Denne brukeren tilhører ikke deg.");
		}
		
		// overskrift
		if (self::$active_type == "player")
		{
			ess::$b->page->add_title(self::$active_player->data['up_name']);
		}
		else
		{
			ess::$b->page->add_title("Brukerinfo" . (!self::$active_own ? " (".self::$active_user->data['u_email'].",#".self::$active_user->id.")" : ""));
			if (self::$active_type == "stats")
			{
				ess::$b->page->add_title("Statistikk" . (!self::$active_own ? " (".self::$active_user->data['u_email'].",#".self::$active_user->id.")" : ""));
			}
		}
		
		// css
		ess::$b->page->add_css('
#page_user_info {
	margin: 20px 30px 30px 30px;
}
#page_user_info h1 { text-align: center }
#page_user_info .bg1_c { margin-bottom: 20px }
#page_user_info.user .col_w.left { width: 45% }
#page_user_info.user .col_w.right { width: 55% }
/*#page_user_info.player .col_w.left { width: 55% }
#page_user_info.player .col_w.right { width: 45% }*/
#page_user_info .col_w.left .col { margin-right: 10px }
#page_user_info .col_w.right .col { margin-left: 10px }');
		
		// overskrift
		echo '
<p class="minside_toplinks mainlinks">
	'.self::link('<img src="'.STATIC_LINK.'/icon/house.png" alt="" />Min bruker', "", "", "user").'
	'.self::link('<img src="'.STATIC_LINK.'/icon/user.png" alt="" />Min spiller', "", "", "player").'
	'.self::link('<img src="'.STATIC_LINK.'/icon/chart_bar.png" alt="" />Statistikk', "", "", "stats").'
</p>';
		
		// underside
		self::$subpage = isset($_GET['a']) ? $_GET['a'] : '';
		redirect::store(self::addr());
		
		switch (self::$active_type)
		{
			// statistikk
			case "stats":
				page_min_side_stats::main();
			break;
			
			// bruker
			case "user":
				page_min_side_user::main();
			break;
			
			// spiller
			default:
				page_min_side_player::main();
		}
		
		ess::$b->page->load();
	}
}