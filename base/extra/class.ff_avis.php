<?php

// sett opp riktig adresse til forhåndsvisningsbildet av avismalene
ff_avis::$templates['template1']['preview'] = STATIC_LINK.'/firma/aviser_template1.gif';
ff_avis::$templates['template2']['preview'] = STATIC_LINK.'/firma/aviser_template2.gif';
ff_avis::$templates['template3']['preview'] = STATIC_LINK.'/firma/aviser_template3.gif';
ff_avis::$templates['template4']['preview'] = STATIC_LINK.'/firma/aviser_template4.gif';

/**
 * Innstillinger for avissystemet
 */
class ff_avis
{
	/** Pris for å publisere en avisutgivelse */
	const FFN_PUBLISH_COST = 0;
	
	/** Ventetid mellom hver utgivelse kan publiseres */
	const FFN_PUBLISH_DELAY = 86400; // 1 dag
	
	/** Templatene for avisene */
	public static $templates = array(
		"template1" => array(
			"areas" => array(
				"top" => "Toppen",
				"left" => "Venstre kolonne",
				"center" => "Midtkolonne",
				"right" => "Høyre kolonne",
				"bottom" => "Bunnen"
			),
			"name" => "Template 1",
			"description" => "Inneholder topp, tre kolonner og bunn. De tre kolonnene består av to små på hver sin side og en litt større i midten."
		),
		"template2" => array(
			"areas" => array(
				"top" => "Toppen",
				"left" => "Venstre kolonne",
				"right" => "Høyre kolonne",
				"bottom" => "Bunnen"
			),
			"name" => "Template 2",
			"description" => "Inneholder topp, to like store kolonner og bunn."
		),
		"template3" => array(
			"areas" => array(
				"top" => "Toppen",
				"left" => "Venstre kolonne",
				"right" => "Høyre kolonne",
				"bottom" => "Bunnen"
			),
			"name" => "Template 3",
			"description" => "Inneholder topp, en tynn kolonne, en bred kolonne og bunn."
		),
		"template4" => array(
			"areas" => array(
				"top" => "Toppen",
				"left" => "Venstre kolonne",
				"right" => "Høyre kolonne",
				"bottom" => "Bunnen"
			),
			"name" => "Template 4",
			"description" => "Inneholder topp, en bred kolonne, en tynn kolonne og bunn."
		)
	);
}



/**
 * Bestemt avisutgivelse
 */
class ff_avis_utgivelse
{
	/**
	 * ID-en
	 */
	public $id;
	
	/**
	 * FF
	 * @var ff
	 */
	public $ff;
	
	/**
	 * Informasjon om utgivelsen
	 */
	public $data;
	
	/**
	 * Templaten for utgivelsen
	 * @var ff_avis_template
	 */
	public $template;
	
	/**
	 * Hent avisutgivelse
	 * @param int $ffn_id
	 * @param ff $ff
	 * @return ff_avis_utgivelse
	 */
	public static function get($ffn_id, ff $ff)
	{
		$ffn = new self($ffn_id, $ff);
		
		if (!$ffn->data) return null;
		return $ffn;
	}
	
	/**
	 * Finn utgivelse
	 */
	protected function __construct($ffn_id, ff $ff)
	{
		$this->id = (int) $ffn_id;
		$this->ff = $ff;
		
		// hent detaljer
		$result = ess::$b->db->query("
			SELECT ffn_id, ffn_ff_id, ffn_template, ffn_published, ffn_published_up_id, ffn_published_time, ffn_cost, ffn_sold, ffn_title, ffn_income, ffn_description, ffn_created_time, ffn_created_up_id
			FROM ff_newspapers
			WHERE ffn_id = $this->id AND ffn_ff_id = {$this->ff->id}");
		
		$this->data = mysql_fetch_assoc($result);
		if (!$this->data) return;
		
		$this->erase();
	}
	
	/**
	 * Hente template
	 */
	public function __get($name)
	{
		switch ($name)
		{
			case "template":
				$this->template = new ff_avis_template($this->data['ffn_template']);
				$this->template->ffn = $this;
				return $this->template;
			break;
		}
	}
	
	/**
	 * Fjern templatelenke
	 */
	protected function erase()
	{
		if (!isset($this->template)) unset($this->template);
	}
	
	/** Lag HTML for avisutgivelsen */
	public function build_avis_html()
	{
		// hent inn alle artiklene i utgivelsen
		$result = ess::$b->db->query("
			SELECT ffna_id, ffna_up_id, ffna_title, ffna_text, ffna_theme_position, ffna_theme_priority
			FROM ff_newspapers_articles
			WHERE ffna_ffn_id = $this->id
			ORDER BY ffna_theme_priority");
		
		while ($row = mysql_fetch_assoc($result))
		{
			$this->template->add_ffna($row);
		}
		
		return $this->template->build();
	}
}

/**
 * Avisartikkel
 */
class ff_avis_artikkel
{
	/**
	 * ID
	 */
	public $id;
	
	/**
	 * Avisutgivelsen artikkelen tilhører
	 * @var ff_avis_utgivelse
	 */
	public $ffn;
	
	/**
	 * Informasjon om artikkelen
	 */
	public $data;
	
	/**
	 * FF
	 * @var ff
	 */
	public $ff;
	
	/**
	 * Har vi lesetilgang?
	 */
	public $access_read;
	
	/**
	 * Har vi skrivetilgang?
	 */
	public $access_write;
	
	/**
	 * Hent en artikkel
	 * @return ff_avis_artikkel
	 */
	public static function get($ffna_id, ff $ff)
	{
		$ffna = new self($ffna_id, $ff);
		
		if (!$ffna->data) return null;
		return $ffna;
	}
	
	/**
	 * Finn artikkel
	 */
	protected function __construct($ffna_id, ff $ff)
	{
		$this->id = (int) $ffna_id;
		$this->ff = $ff;
		
		// hent informasjon
		$result = ess::$b->db->query("
			SELECT ffna_id, ffna_up_id, ffna_ffn_id, ffna_created_time, ffna_updated_time, ffna_title, ffna_text, ffna_theme_position, ffna_theme_parameters, ffna_theme_priority, ffna_published, ffna_published_time, ffna_price
			FROM ff_newspapers_articles
			WHERE ffna_id = $this->id AND ffna_ff_id = {$this->ff->id}");
		
		$this->data = mysql_fetch_assoc($result);
		if (!$this->data) return;
		
		// sett opp tilgang
		$this->check_access();
		
		// hent utgivelse
		$this->load_ffn();
	}
	
	/**
	 * Sjekk for tilganger
	 */
	protected function check_access()
	{
		// skrivetilgang?
		$this->access_write = $this->data['ffna_up_id'] == login::$user->player->id || access::has("mod");
		
		// lesetilgang?
		$this->access_read = $this->access_write || ($this->data['ffna_published'] != 0 && $this->ff->access(2));
	}
	
	/**
	 * Hent utgivelse
	 */
	public function load_ffn()
	{
		$this->ffn = $this->data['ffna_ffn_id'] == 0
			? null
			: ff_avis_utgivelse::get($this->data['ffna_ffn_id'], $this->ff);
	}
}


/**
 * Templatesystemet
 */
class ff_avis_template
{
	/** ID-en til templaten */
	public $template_id;
	
	/** Templateinformasjon */
	public $template;
	
	/**
	 * Utgivelse <br>Lagres kun dersom templaten er knyttet opp mot en utgivelse
	 * @var firma_avis_utgivelse
	 */
	public $ffn;
	
	/**
	 * Avisartiklene som skal være med
	 * @var array
	 */
	public $ffna;
	
	/**
	 * Konstruer objekt
	 * @param string $template_id
	 */
	public function __construct($template_id)
	{
		// kontroller at templaten eksisterer
		if (!isset(ff_avis::$templates[$template_id]))
		{
			throw new HSException("Fant ikke template for avis: ".$template_id);
		}
		
		// lagre data
		$this->template_id = $template_id;
		$this->template = ff_avis::$templates[$template_id];
		
		// sett opp template-plasseringer i $ffna
		foreach (array_keys($this->template['areas']) as $key)
		{
			$this->ffna[$key] = array();
		}
	}
	
	/**
	 * Legg til avisartikkel
	 * @param array $ffna data fra databasen
	 * @param string $position egendefinert posisjon hvis det ikke skal hentes fra artikkelen
	 */
	public function add_ffna(array $ffna, $position = NULL)
	{
		// kontroller for nødvendig data
		if (!isset($ffna['ffna_id']))
		{
			throw new HSException("Mangler data.");
		}
		
		// kontroller at plasseringen er gyldig
		if (!$position) $position = $ffna['ffna_theme_position'];
		if (!isset($this->ffna[$position]))
		{
			throw new HSException("Ugyldig plassering for artikkel: {$position}");
		}
		
		// legg til
		$this->ffna[$position][] = $ffna;
	}
	
	/** Diverse dummy tekst */
	public static $dummy_text = array(
		"Phasellus ut nisl et est hendrerit varius. Pellentesque accumsan cursus purus ut mattis. Suspendisse potenti. Nam id eros felis.\n\nPraesent facilisis malesuada dolor, et ultricies eros tempor eu. In vel risus urna. Donec metus nisl, volutpat suscipit viverra sit amet, consequat a sem. In nec congue libero. Pellentesque sapien lorem.",
		"Curabitur mauris leo, scelerisque porttitor tincidunt sit amet, commodo sed odio. Vivamus fringilla malesuada lectus nec ullamcorper.\n\nNullam cursus, elit vel sagittis scelerisque, felis purus suscipit dui, sed eleifend sem elit sit amet lectus. Sed eget condimentum lectus. Aenean ultricies ipsum nec ante vestibulum non accumsan purus porta. Sed quis.",
		"Duis ac dui erat. In velit massa, vestibulum a lacinia ac, tristique ac sapien. Cras a odio eget justo fringilla accumsan eu non nulla.\n\nIn congue dolor vitae lacus lacinia suscipit ullamcorper massa sagittis. Quisque rhoncus, nunc eu vehicula dictum, mauris elit elementum nisi, ut sagittis turpis tellus vitae tellus.",
		"Proin rutrum leo ac erat molestie volutpat. Donec vitae odio a nulla tempus vehicula. Suspendisse et tristique magna. Nullam nec orci nec nulla sollicitudin elementum id quis justo.\n\nPhasellus massa lacus, consectetur sed rhoncus et, ornare in tortor. Morbi auctor lacinia massa at tristique. Sed rutrum gravida ipsum at luctus.",
		"Maecenas porta nibh sed libero tempus tincidunt. Proin et elementum justo. Praesent consectetur diam sodales diam auctor non euismod ligula faucibus. Nunc ultricies iaculis euismod.\n\nSed vitae odio est. Vestibulum id nisl sapien, a tincidunt eros. Nam lectus nunc, congue non rhoncus id, luctus in enim. Quisque a nulla eget.",
		"Vestibulum dapibus ultricies posuere. Phasellus fringilla neque ultricies lacus eleifend vel consectetur velit mollis. Vestibulum semper viverra mauris vel hendrerit.\n\nDuis sit amet erat ut turpis ornare blandit iaculis vitae erat. Phasellus imperdiet rhoncus vehicula. Sed hendrerit ipsum non justo suscipit laoreet. Duis turpis orci, congue vel gravida eget, placerat."
	);
	
	/** Diverse dummy tekst (for overskrift) (må inneholde like mange elementer som self::$dummy_text */
	public static $dummy_text_short = array(
		"Sed non quam eros, ut mattis mauris.",
		"Praesent dignissim nibh.",
		"Curabitur nec tellus.",
		"Quisque ac lectus orci.",
		"Nullam tincidunt pulvinar.",
		"Nullam gravida magna."
	);
	
	/**
	 * Legg til dummy tekst på de plasseringene det ikke har artikler
	 */
	public function add_dummy_text()
	{
		foreach ($this->ffna as $name => &$pos)
		{
			if (count($pos) == 0)
			{
				$d = each(self::$dummy_text);
				if (!$d) { reset(self::$dummy_text); $d = each(self::$dummy_text); }
				$d = $d[0];
				
				$pos[] = array(
					"ffna_up_id" => false,
					"ffna_title" => self::$dummy_text_short[$d],
					"ffna_text" => self::$dummy_text[$d],
					"ffna_theme_position" => $name,
					"example" => true
				);
			}
		}
	}
	
	/**
	 * Lag HTML for en artikkelboks
	 * @param array $ffna
	 */
	public function build_box($ffna)
	{
		$text = game::format_data($ffna['ffna_text']);
		
		return '
		<div class="ffn_template_ffna'.(isset($ffna['example']) ? ' ffn_template_ffna_example' : '').'">
			<div class="ffn_template_title">'.htmlspecialchars($ffna['ffna_title']).'</div>
			<div class="ffn_template_text">
				'.$text.'
				<div class="clear"></div>
			</div>
			<div class="ffn_template_u">'.(!$ffna['ffna_up_id'] ? 'Eksempel' : 'Av <user id="'.$ffna['ffna_up_id'].'" />').'</div>
		</div>';
	}
	
	/**
	 * Lag HTML for bestemte artikler
	 */
	public function build_boxes($ffna_list)
	{
		$data = '';
		
		foreach ($ffna_list as $row)
		{
			// legg til artikkelen
			$data .= $this->build_box($row);
		}
		
		return $data;
	}
	
	// sett opp hele utgivelsen
	public function build()
	{
		global $__server;
		
		// adresse til logo
		$logo_url = $__server['relative_path'].'/ff/'.($this->ffn ? 'avis?ff_id='.$this->ffn->data['ffn_ff_id'].'&amp;load_logo='.$this->ffn->data['ffn_id'] : 'avis?load_logo');
		
		$data = '
<div class="ffn_template ffn_template_'.$this->template_id.'">
	<div class="ffn_template_logo"><img src="'.$logo_url.'" alt="Logo" /></div>';
		
		// hvilken mal?
		switch ($this->template_id)
		{
			case "template1":
				// top, left, center, right, bottom
				
				// artiklene på toppen
				if (count($this->ffna['top']) > 0)
				{
					$data .= '
	<div class="ffn_template_top">'.$this->build_boxes($this->ffna['top']).'
	</div>';
				}
				
				// bestemme om det skal settes clear under left, center, right
				$clear = false;
				
				// artiklene på venstre side
				if (count($this->ffna['left']) > 0)
				{
					$clear = true;
					$data .= '
	<div class="ffn_template_left">'.$this->build_boxes($this->ffna['left']).'
	</div>';
				}
				
				// artiklene i midten og på høyre side
				if (count($this->ffna['center']) > 0 || count($this->ffna['right']) > 0)
				{
					$clear = true;
					$data .= '
	<div class="ffn_template_group_right">';
					
					// artiklene i midten
					if (count($this->ffna['center']) > 0)
					{
						$data .= '
		<div class="ffn_template_center">'.$this->build_boxes($this->ffna['center']).'
		</div>';
					}
					
					// artiklene på høyre side
					if (count($this->ffna['right']) > 0)
					{
						$data .= '
		<div class="ffn_template_right">'.$this->build_boxes($this->ffna['right']).'
		</div>';
					}
					
					$data .= '
	</div>';
				}
				
				if ($clear)
				{
					$data .= '
	<div class="clear"></div>';
				}
				
				// artiklene i bunn
				if (count($this->ffna['bottom']) > 0)
				{
					$data .= '
	<div class="ffn_template_bottom">'.$this->build_boxes($this->ffna['bottom']).'
	</div>';
				}
				
				break;
			
			case "template2":
			case "template3":
			case "template4":
				// top, left, right, bottom
				
				// artiklene på toppen
				if (count($this->ffna['top']) > 0)
				{
					$data .= '
	<div class="ffn_template_top">'.$this->build_boxes($this->ffna['top']).'
	</div>';
				}
				
				// bestemme om det skal settes clear under left, center, right
				$clear = false;
				
				// artiklene på venstre side
				if (count($this->ffna['left']) > 0)
				{
					$clear = true;
					$data .= '
	<div class="ffn_template_left">'.$this->build_boxes($this->ffna['left']).'
	</div>';
				}
				
				// artiklene på høyre side
				if (count($this->ffna['right']) > 0)
				{
					$clear = true;
					$data .= '
	<div class="ffn_template_right">'.$this->build_boxes($this->ffna['right']).'
	</div>';
				}
				
				if ($clear)
				{
					$data .= '
	<div class="clear"></div>';
				}
				
				// artiklene i bunn
				if (count($this->ffna['bottom']) > 0)
				{
				$data .= '
	<div class="ffn_template_bottom">'.$this->build_boxes($this->ffna['bottom']).'
	</div>';
				}
				
				break;
		}
		
		$data .= '
</div>';
		
		return $data;
	}
}