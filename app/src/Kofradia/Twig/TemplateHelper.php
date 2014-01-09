<?php namespace Kofradia\Twig;

class TemplateHelper {
	public function isLoggedIn()
	{
		return \login::$logged_in;
	}

	public function getUser()
	{
		return \login::$user;
	}

	public function isMainServer()
	{
		return MAIN_SERVER;
	}

	public function getLibAddr()
	{
		return LIB_HTTP;
	}

	public function getServerTime()
	{
		return round(microtime(true)+\ess::$b->date->timezone->getOffset(\ess::$b->date->get()), 3) * 1000;
	}

	public function getStaticLink()
	{
		return STATIC_LINK;
	}

	public function getImgsHttp()
	{
		return IMGS_HTTP;
	}

	public function getServerSettings()
	{
		return \ess::$s;
	}

	public function isForceHttps()
	{
		return \login::is_force_https();
	}

	public function isLock()
	{
		return defined("LOCK") && LOCK;
	}

	public function getBrowserClass()
	{
		// sett opp nettleser "layout engine" til CSS
		$list = array(
			"opera" => "presto",
			"applewebkit" => "webkit",
			"msie 8" => "trident6 trident",
			"msie 7" => "trident5 trident",
			"msie 6" => "trident4 trident",
			"gecko" => "gecko"
		);
		$class_browser = 'unknown_engine';
		$browser = mb_strtolower($_SERVER['HTTP_USER_AGENT']);
		foreach ($list as $key => $item)
		{
			if (mb_strpos($browser, $key) !== false)
			{
				$class_browser = $item;
				break;
			}
		}

		return $class_browser;
	}

	public function hasHttpsSupport()
	{
		return \ess::$s['https_support'];
	}

	public function isPage($page)
	{
		$check = sprintf('~^%s$~i', "/".ltrim($page, "/"));
		return preg_match($check, ROUTE_URL);
	}

	public function getMessageBoxes($class = null)
	{
		return \ess::$b->page->messages->getBoxes($class);
	}

	public function buildMenu()
	{
		return \kf_menu::build_menu();
	}

	public function getLoginUrl()
	{
		return '/?orign='.urlencode($_SERVER['REQUEST_URI']);
	}

	public function getFacebookLikesNum()
	{
		return \facebook::get_likes_num();
	}

	public function getSid()
	{
		return \login::$logged_in ? \login::$info['ses_id'] : 0;
	}

	public function checkAccess($name)
	{
		return \access::has($name);
	}

	public function hasExtededAccess()
	{
		return \login::$extended_access;
	}

	public function isExtendedAccessAuthed()
	{
		return \login::extended_access_is_authed();
	}

	/**
	 * Hent diverse infobokser for crew
	 */
	public static function getExtendedAccessBoxes()
	{
		if (!isset(\login::$extended_access)) return;
		if (!\login::extended_access_is_authed()) return;
		
		$boxes = array();
		
		// support meldinger
		if (\access::has("crewet"))
		{
			$row = \tasks::get("support");
			if ($row['t_ant'] > 0)
			{
				$boxes[] = array(
					\ess::$s['relative_path'].'/support/?a=panel&amp;kategori=oppsummering',
					'Det er <b>'.$row['t_ant'].'</b> '.fword("ubesvart supportmelding", "ubesvarte supportmeldinger", $row['t_ant']).'!');
			}
		}
		
		// hent antall nye rapporteringer fra cache
		$row = \tasks::get("rapporteringer");
		if ($row['t_ant'] > 0)
		{
			$boxes[] = array(
				\ess::$s['relative_path'].'/crew/rapportering',
				'Det er <b>'.$row['t_ant'].'</b> '.fword("ubehandlet rapportering", "ubehandlede rapporteringer", $row['t_ant']).'.');
		}
		
		// hent antall nye søknader fra cache
		$row = \tasks::get("soknader");
		if ($row['t_ant'] > 0)
		{
			$boxes[] = array(
				\ess::$s['relative_path'].'/crew/soknader',
				'Det er <b>'.$row['t_ant'].'</b> '.fword("ubehandlet søknad", "ubehandlede søknader", $row['t_ant']).'.');
		}
		
		// antall ubesvarte henvendelser
		if (\access::has("mod"))
		{
			// hent antall nye henvendelser fra cache
			$row = \tasks::get("henvendelser");
			
			if ($row['t_ant'] > 0)
			{
				$boxes[] = array(
					\ess::$s['relative_path'].'/henvendelser?a',
					'Det er <b>'.$row['t_ant'].'</b> '.fword("ny henvendelse", "nye henvendelser", $row['t_ant']).' som er ubesvart.');
			}
		}
		
		// hendelser fra GitHub
		$github = \Kofradia\Users\GitHub::get(\login::$user);
		if (!$github->hasActivated())
		{
			$boxes[] = array(
				ess::$s['relative_path'].'/github',
				'Du vil nå motta nye hendelser fra GitHub her. Trykk her for å se de siste hendelsene.');
		}
		else
		{
			$num_changes = $github->getCodeBehindCount() + $github->getOtherBehindCount();
			
			if ($num_changes > 0)
			{
				$boxes[] = array(
					\ess::$s['relative_path'].'/github',
					'Det er <b>'.$num_changes.'</b> ny'.($num_changes == 1 ? '' : 'e').' hendelse'.($num_changes == 1 ? '' : 'r').' i GitHub.');
			}
		}
		
		return $boxes;
	}

	public function getAccessName()
	{
		return \access::name(\access::type(\login::$user->player->data['up_access_level']));
	}

	public function getExtendedAccessLogoutUrl()
	{
		return '/extended_access?logout&orign='.urlencode($_SERVER['REQUEST_URI']);
	}

	public function getExtendedAccessCreateUrl()
	{
		return '/extended_access?create&orign='.urlencode($_SERVER['REQUEST_URI']);
	}

	public function getExtendedAccessLoginUrl()
	{
		return '/extended_access?orign='.urlencode($_SERVER['REQUEST_URI']);
	}

	public function hasExtendedAccessPass()
	{
		return isset(\login::$extended_access['passkey']);
	}
}