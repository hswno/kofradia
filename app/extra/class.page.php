<?php

class page
{
	public $title = array();
	public $head = '';
	public $css = '';
	public $js = '';
	public $js_domready = '';
	protected $js_files_loaded = array();
	public $body_start = '';
	public $body_end = '';
	public $keywords = array();
	public $description = '';
	
	public $theme = '';
	public $theme_file = 'default';
	
	public $content = '';
	public $content_right = array();
	
	/**
	 * Messages
	 *
	 * @var \Kofradia\Page\MessagesContainer
	 */
	public $messages;
	
	/**
	 * Ikke legge til javascript på siden
	 */
	public $js_disable;
	
	/** Constructor */
	public function __construct()
	{
		global $__page, $__server;
		
		// sørg for at session er startet
		sess_start();
		
		// standardverdier
		$this->title = array($__page['title']);
		$this->keywords = $__page['keywords_default'];
		$this->description = $__page['description_default'];
		$this->theme = $__page['theme'];
		
		// sørg for at sessions/page_settings/messages er satt opp
		if (!isset($_SESSION[$__server['session_prefix'].'page_settings']['messages'])
			|| !($_SESSION[$__server['session_prefix'].'page_settings']['messages'] instanceof \Kofradia\Page\MessagesContainer))
		{
			$_SESSION[$__server['session_prefix'].'page_settings']['messages'] = new \Kofradia\Page\MessagesContainer();
		}

		$this->messages = &$_SESSION[$__server['session_prefix'].'page_settings']['messages'];
	}
	
	/** Last inn siden (kalles til slutten av scriptet for å hente themet */
	public function load()
	{
		$this->content .= ob_get_contents();
		@ob_clean();

		global $_base;
		$_base->dt("page_load_pre");

		// temafilen
		$theme_file = PATH_PUBLIC."/themes/".$this->theme."/".$this->theme_file.".php";
		
		// finnes ikke temafilen?
		if (!file_exists($theme_file))
		{
			throw new HSException("Fant ikke temafilen <b>$this->theme_file.php</b> for temaet <b>$this->theme</b>.");
		}

		if (mb_strpos($this->content, '<boxes />') === false)
		{
			$this->content = '<boxes />'.$this->content;
		}

		// hent temafilen
		require $theme_file;
		
		// hent full html kode som ble generert
		$content = ob_get_contents();
		@ob_clean();

		echo $this->postParse($content);
		die;
	}

	/**
	 * Parse innhold etter det er generert og gjør siste endringer
	 *
	 * Legger til meldinger, brukerlenker m.v.
	 *
	 * Legger også til debuginfo
	 *
	 * @param string
	 * @return string
	 */
	public function postParse($content)
	{
		\ess::$b->dt("postParse start");
		
		// sjekk om betingelsene er oppdatert
		if (login::$logged_in && login::$user->data['u_tos_version'] != game::$settings['tos_version']['value'] && !defined("TOS_MESSAGE"))
		{
			define("TOS_MESSAGE", true);
			$this->add_message('<b>Betingelser oppdatert -</b> Du har ikke lest gjennom de nyeste <a href="'.ess::$s['rpath'].'/betingelser">betingelsene</a>! Ditt videre bruk betyr at du samtykker i disse betingelsene.', "error");
		}
		
		// informasjonsmeldinger
		$msgs = $this->messages->getBoxes();
		if (!preg_match_all("~<boxes( (-?\\d+))? />~", $content, $matches, PREG_SET_ORDER))
		{
			throw new \RuntimeException("Missing boxes-element from template.");
		}

		$match = '';
		$match_id = null;
		foreach ($matches as $m)
		{
			$id = isset($m[2]) ? (int) $m[2] : 0;
			if ($match_id === null || $id > $match_id)
			{
				$match = $m[0];
			}
		}

		$content = preg_replace("~".preg_quote($match, "~")."~", $msgs, $content, 1);
		$content = preg_replace("~<boxes( (-?\\d+))? />~", "", $content);

		// gå gjennom HTML og sjekk for brukerlinker (<user../>) osv. og vis innholdet
		$content = parse_html($content);
		
		if (defined("SHOW_QUERIES_INFO"))
		{
			$profiler = \Kofradia\DB::getProfiler();
			$db = '';
			if ($profiler && count($profiler->statements) > 0)
			{
				$statements = $profiler->statements;
				$x = 0;
				foreach (array_keys($statements[0]) as $key)
				{
					$x = max($x, strlen($key));
				}

				$newlist = array();
				foreach ($statements as $statement)
				{
					$new = array();
					foreach ($statement as $key => $row)
					{
						$new[str_pad($key, $x)] = $row;
					}
					$newlist[] = $new;
				}

				$db = '<br />DATABASE<br />'.htmlspecialchars(print_r($newlist, true));
			}

			$content .= '
<a href="javascript:void(0)" onclick="this.nextSibling.style.display=\'block\'">Debug info</a><div style="display: none"><br />
	<pre>
GET<br />'.htmlspecialchars(print_r($_GET, true)).'<br />
POST<br />'.htmlspecialchars(print_r($_POST, true)).$db.'
	</pre>
</div>';
		}

		\ess::$b->dt('postParse end');
		return $content;
	}
	
	/** Hent innhold til <head> */
	public function generate_head()
	{
		$head = $this->head;
		
		// legg til css
		if (!empty($this->css))
		{
			$head .= "<style type=\"text/css\">\r\n<!--\r\n" . $this->css . "-->\r\n</style>\r\n";
		}
		
		// legg til javascript
		if (!$this->js_disable && (!empty($this->js) || !empty($this->js_domready)))
		{
			$dr = !empty($this->js_domready) ? "window.addEvent(\"sm_domready\", function() {\r\n{$this->js_domready}});\r\n" : "";
			$head .= "<script type=\"text/javascript\">\r\n<!--\r\n" . $this->js . $dr . "// -->\r\n</script>\r\n";
		}
		
		// send resultatet
		return $head;
	}
	
	/** Generer tittel */
	public function generate_title()
	{
		global $__page;
		
		// sett sammen tittelen og send resultatet
		return implode($__page['title_split'], ($__page['title_direction'] == "right" ? $this->title : array_reverse($this->title)));
	}
	
	/** Generer nøkkelord */
	public function generate_keywords()
	{
		// sett sammen keywords og send resultatet
		return implode(", ", $this->keywords);
	}
	
	/** Generer innhold på høyre siden */
	public function generate_content_right()
	{
		$content = "";
		foreach ($this->content_right as $row)
		{
			$content .= $row["content"];
		}
		
		return $content;
	}
	
	/** Legg til innhold på høyre siden */
	public function add_content_right($content, $priority = NULL)
	{
		// bestem prioritering
		if ($priority !== NULL) $priority = (int) $priority;
		
		// innholdet
		$arr = array("priority" => $priority, "content" => $content);
		
		// finn ut hvor vi skal plassere den
		if ($priority === NULL) array_push($this->content_right, $arr);
		else
		{
			$i = 0;
			foreach ($this->content_right as $row)
			{
				if ($row['priority'] > $priority)
				{
					array_splice($this->content_right, $i, 0, array($arr));
					$i = -1;
					break;
				}
				$i++;
			}
			
			if ($i >= 0) array_push($this->content_right, $arr);
		}
	}
	
	/** Legg til tittel */
	public function add_title()
	{
		foreach (func_get_args() as $value) {
			$this->title[] = htmlspecialchars($value);
		}
	}
	
	/** Legg til data i <head> */
	public function add_head($value)
	{
		$this->head .= $value."\r\n";
	}
	
	/** Legg til CSS */
	public function add_css($value)
	{
		$this->css .= $value."\r\n";
	}
	
	/** Legg til en hel CSS fil */
	public function add_css_file($path, $media = "all")
	{
		$this->add_head('<link rel="stylesheet" type="text/css" href="'.$path.'" media="'.$media.'" />');
	}
	
	/** Legg til javascript */
	public function add_js($value)
	{
		$this->js .= $value."\r\n";
	}
	
	/** Legg til javascript som kjøres i domready event */
	public function add_js_domready($value)
	{
		$this->js_domready .= $value."\r\n";
	}
	
	/** Legg til javascript fil */
	public function add_js_file($path)
	{
		// allerede lastet inn?
		if (in_array($path, $this->js_files_loaded)) return;
		$this->js_files_loaded[] = $path;
		$this->add_head('<script src="'.$path.'" type="text/javascript"></script>');
	}
	
	/** Legg til HTML rett etter <body> */
	public function add_body_pre($value)
	{
		$this->body_start .= $value."\r\n";
	}
	
	/** Legg til HTML rett før </body> */
	public function add_body_post($value)
	{
		$this->body_end .= $value."\r\n";
	}
	
	/** Legg til nøkkelord */
	public function add_keyword()
	{
		foreach (func_get_args() as $value) {
			$this->keywords[] = htmlspecialchars($value);
		}
	}
	
	/** Nullstill alle nøkkelordene (sletter dem) */
	public function reset_keywords()
	{	
		$this->keywords = array();
	}
	
	/** Endre beskrivelsen */
	public function set_description($value)
	{
		$this->description = htmlspecialchars($value);
	}
	
	/**
	 * Legg til informasjonsmelding (info, error, osv)
	 * 
	 * @param string $value
	 * @param string $type = NULL
	 * @param string $force = NULL
	 * @param string $name = NULL
	 * @return \Kofradia\Page\Message
	 */
	public function add_message($value, $type = NULL, $force = NULL, $name = NULL)
	{
		if (!$type) $type = 'info';
		$msg = \Kofradia\Page\Message::forge($value, $type, $force);
		if ($name)
		{
			$msg->name = $name;
		}

		return $this->messages->addMessage($msg);
	}
	
	/**
	 * Hent ut en bestemt informasjonsmelding
	 */
	public function message_get($name, $erase = true, $format = null)
	{
		return $this->messages->getMessageByName($name, $erase, $format);
	}

	/**
	 * Formater html for melding
	 */
	public function getContent()
	{
		return $this->content;
		$content = $this->content;
		$this->content = '';
		return $content;
	}
}