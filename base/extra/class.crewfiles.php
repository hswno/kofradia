<?php

class crewfiles
{
	/**
	 * Aktiv spiller
	 * @var player
	 */
	public static $up;
	
	/** Gi full tilgang uansett */
	public static $access_override;
	
	/** Adresse til mappen som inneholder dataene */
	public static $path;
	
	/** Alle tilgangsnivåene som skal være tilgjengelig */
	public static $access_levels = array("forum_mod", "mod", "admin", "sadmin");
	
	/** Egne navn på tilgangsnivåene (for å kunne ha Senioradministrator) */
	public static $access_levels_name = array("sadmin" => "Senioradministrator");
	
	/** Initialize */
	public static function init(player $up = null, $override = null) {
		if ($up)
		{
			self::$up = $up;
		}
		
		elseif (login::$logged_in)
		{
			self::$up = login::$user->player;
		}
		
		if ($override)
		{
			self::$access_override = true;
		}
	}
	
	/**
	 * Hent ut ID og navn fra "id-navn" formatet
	 */
	public static function get_info($tag)
	{
		$pos = strpos($tag, "-");
		if ($pos === false) return false;
		
		return array(substr($tag, 0, $pos), substr($tag, $pos+1));
	}
	
	/**
	 * Generer tagnavn (uten ID) fra tittel
	 */
	public static function generate_tagname($title)
	{
		// mellomrom skrives om til _
		$title = str_replace(" ", "_", $title);
		
		// tillatte tegn: a-zA-Z0-9_-.,
		$title = preg_replace("/[^a-zA-Z0-9_\\-\\.,]/u", "", $title);
		
		// lowercase
		$title = strtolower($title);
		
		return $title;
	}
	
	/**
	 * Hent mappe
	 * @return crewfiles_directory
	 */
	public static function get_directory($id)
	{
		// lag mappeobjekt
		$directory = new crewfiles_directory($id);
		
		// fant ikke data?
		if (!$directory->info)
		{
			return false;
		}
		
		return $directory;
	}
	
	/**
	 * Hent fil
	 * @return crewfiles_file
	 */
	public static function get_file($id)
	{
		// lag filobjekt
		$file = new crewfiles_file($id);
		
		// fant ikke data?
		if (!$file->info)
		{
			return false;
		}
		
		return $file;
	}
	
	/**
	 * Hent revisjon
	 * @return crewfiles_revision
	 */
	public static function get_revision($id)
	{
		// lag revisjonobjekt
		$revision = new crewfiles_revision($id);
		
		// fant ikke data?
		if (!$revision->info)
		{
			return false;
		}
		
		return $revision;
	}
	
	/**
	 * Generer liste med tilgangsnivå brukeren har tilgang til og som kan settes på mappene
	 */
	public static function get_access_levels()
	{
		$access_levels = array();
		foreach (self::$access_levels as $value)
		{
			if (crewfiles::access($value))
			{
				$access_levels[] = $value;
			}
		}
		
		return $access_levels;
	}
	
	/**
	 * Valider tilgangsnivå
	 */
	public static function validate_access_level($access_level)
	{
		$access_levels = self::get_access_levels();
		return in_array($access_level, $access_levels);
	}
	
	/**
	 * Kontroller tilgangsnivå
	 * Hvis data hentes fra SESSION, må brukeren være logget inn som crew først
	 */
	public static function access($access_name, $allow_extended_access_login = NULL)
	{
		if (self::$access_override) return true;
		sess_start();
		if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'user'])) return false;
		return access::has($access_name, NULL, $_SESSION[$GLOBALS['__server']['session_prefix'].'user']->data['u_access_level'], ($allow_extended_access_login ? 'login' : NULL));
	}
	
	/**
	 * Hente ut navn for tilgangsnivå
	 * Sjekker først i lokal array
	 * @param string $access_level tilgangsnavn (f.eks. admin)
	 * @return string tilgangstittel (f.eks. Administrator)
	 */
	public static function access_name($access_level)
	{
		// forsøk å hente fra denne klassen
		if (isset(self::$access_levels_name[$access_level]))
		{
			return self::$access_levels_name[$access_level];
		}
		
		// hent fra game klassen
		return access::name($access_level);
	}
	
	/**
	 * Filtrer filnavn
	 */
	public static function filter_filename($filename)
	{
		// fjern "ugyldige" tegn
		return preg_replace("/[\\\\\\/:*?\"<>|]/u", "", $filename);
	}
	
	/**
	 * Hent tree for mappene
	 * @return tree
	 */
	public static function get_directory_tree()
	{
		global $_base;
		
		// hent alle mappene
		$result = $_base->db->query("SELECT cfd_id, cfd_parent_cfd_id, cfd_title, cfd_description, cfd_time, cfd_up_id, cfd_access_level FROM crewfiles_directories ORDER BY cfd_title");
		
		// les inn mappene
		$dirs = array();
		$dirs_sub = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$dirs[$row['cfd_id']] = $row;
			$dirs_sub[$row['cfd_parent_cfd_id']][] = $row['cfd_id'];
		}
		
		// hent tree
		$root = array(0 => array(
			"number" => 0,
			"prefix" => "",
			"prefix_node" => "",
			"data" => crewfiles_directory::$root
		));
		$tree = new tree($dirs_sub);
		$tree->generate(0, $root, $dirs);
		
		// returner tree
		return $tree;
	}
	
	/**
	 * Hent tree for alle filene i systemet
	 * @return tree
	 */
	public static function get_all_files()
	{
		global $_base;
		
		// hent tree
		$tree = crewfiles::get_directory_tree();
		
		// hent alle filene som ligger i systemet med antall revisjoner og  info om aktiv revisjon
		$result = $_base->db->query("SELECT cff_id, cff_cfd_id, cff_title, cff_description, cff_access_level, cff_hidden, a.cfr_id, a.cfr_title, a.cfr_time, a.cfr_size, a.cfr_description, a.cfr_mime, COUNT(r.cfr_id) count_revisions FROM crewfiles_files LEFT JOIN crewfiles_revisions a ON cff_cfr_id = a.cfr_id LEFT JOIN crewfiles_revisions r ON cff_id = r.cfr_cff_id GROUP BY cff_id ORDER BY cff_title");
		
		// les filene inn i riktig mappe i tree
		while ($row = mysql_fetch_assoc($result))
		{
			// kontroller at vi har tilgang til denne fileln
			if (!empty($row['cff_access_level']) && !crewfiles::access($row['cff_access_level']))
			{
				// hopp over filen
				continue;
			}
			
			// legg til filen
			$tree->data[$row['cff_cfd_id']]['cff'][$row['cff_id']] = $row;
		}
		
		return $tree;
	}
}

class crewfiles_directory
{
	/** Informasjon om rotmappen */
	public static $root = array(
		"cfd_id" => 0,
		"cfd_parent_cfd_id" => 0,
		"cfd_title" => "Rotmappe",
		"cfd_description" => NULL,
		"cfd_time" => NULL,
		"cfd_up_id" => NULL,
		"cfd_access_level" => NULL,
		"up_id" => NULL,
		"up_name" => NULL,
		"up_access_level" => NULL
	);
	
	/** ID-en til mappen */
	public $id;
	
	/** Informasjon om mappen */
	public $info;
	
	/** Om man har tilgang til mappen */
	public $access;
	
	/** Antall mapper i denne mappen */
	public $count_dirs;
	
	/** Antall filer i denne mappen */
	public $count_files;
	
	/** Constructor */
	public function __construct($id)
	{
		global $_base;
		$this->id = intval($id);
		
		// hent informasjon
		if ($this->id == 0)
		{
			$this->info = self::$root;
			$this->access = true;
			return;
		}
		
		// finn mappen
		$result = $_base->db->query("SELECT cfd_id, cfd_parent_cfd_id, cfd_title, cfd_description, cfd_time, cfd_up_id, cfd_access_level, up_id, up_name, up_access_level FROM crewfiles_directories LEFT JOIN users_players ON cfd_up_id = up_id WHERE cfd_id = $this->id");
		$info = mysql_fetch_assoc($result);
		
		// fant ikke mappen?
		if (!$info)
		{
			$this->info = false;
			return;
		}
		
		// lagre informasjon
		$this->info = $info;
		
		// identifiser om vi har tilgang
		$this->access = $this->access();
	}
	
	/**
	 * Sjekk for tilgang
	 * @param boolean $allow_login send til logg inn siden for utvidede tilganger hvis vi ikke er logget inn for det
	 */
	public function access($allow_login = NULL)
	{
		// kontroller tilgang
		if (!empty($this->info['cfd_access_level']))
		{
			// har ikke tilgang til filer?
			if (!crewfiles::access($this->info['cfd_access_level'], $allow_login))
			{
				return false;
			}
		}
		
		return true;
	}
	
	/** Valider tagnavn */
	public function validate_tag($tag)
	{
		// generer tagnavn
		$title = crewfiles::generate_tagname($this->info['cfd_title']);
		
		// stemmer overens?
		return $title === $tag;
	}
	
	/**
	 * Hent ut path (som raw array)
	 */
	public function get_path_raw()
	{
		global $_base;
		
		$dirs = array();
		$dirs[$this->info['cfd_id']] = $this->info['cfd_title'];
		$parent_id = $this->info['cfd_parent_cfd_id'];
		
		while ($parent_id != 0)
		{
			$result = $_base->db->query("SELECT cfd_id, cfd_title, cfd_parent_cfd_id FROM crewfiles_directories WHERE cfd_id = $parent_id");
			$row = mysql_fetch_assoc($result);
			
			if (!$row)
			{
				throw new HSException("Fant ikke frem til parent mappe med ID $parent_id");
			}
			
			// allerede gått gjennom parent? (løkke funnet)
			if (isset($dirs[$row['cfd_id']]))
			{
				throw new HSException("Parent-løkke funnet ved mappe ID $parent_id");
			}
			
			$parent_id = $row['cfd_parent_cfd_id'];
			$dirs[$row['cfd_id']] = $row['cfd_title'];
		}
		
		$dirs[0] = self::$root['cfd_title'];
		return $dirs;
	}
	
	/**
	 * Hent ut path (alle mapper som er ovenfor)
	 * @param $root string adresse til root med / på slutten
	 */
	public function get_path($root)
	{
		// hent raw liste
		$dirs = $this->get_path_raw();
		
		// sett opp lenkene
		foreach ($dirs as $key => $value)
		{
			$dirs[$key] = '<a href="'.$root.'mappe/'.$key.'-'.htmlspecialchars(crewfiles::generate_tagname($value)).'">'.htmlspecialchars($value).'</a>';
		}
		
		return $dirs;
	}
	
	/** Finn antall undermapper og filer */
	public function get_count()
	{
		// antall mapper i denne mappen må være lik null for at mappen skal kunne slettes
		$result = $_base->db->query("SELECT COUNT(*) FROM crewfiles_directories WHERE cfd_parent_cfd_id = $this->id");
		$this->count_dirs = mysql_result($result, 0, 0);
		
		// antall filer i denne mappen må være lik null for at mappen skal kunne slettes
		$result = $_base->db->query("SELECT COUNT(*) FROM crewfiles_files WHERE cff_cfd_id = $this->id");
		$this->count_files = mysql_result($result, 0, 0);
	}
	
	/** Hent mapper inni denne mappen */
	public function get_dirs()
	{
		global $_base;
		
		// hent mappene med antall undermapper og filer
		$result = $_base->db->query("SELECT m.cfd_id, m.cfd_title, m.cfd_description, m.cfd_access_level, COUNT(r.cfd_id) count_dirs, COUNT(cff_id) count_files FROM crewfiles_directories m LEFT JOIN crewfiles_directories r ON m.cfd_id = r.cfd_parent_cfd_id LEFT JOIN crewfiles_files ON m.cfd_id = cff_cfd_id WHERE m.cfd_parent_cfd_id = $this->id GROUP BY m.cfd_id ORDER BY m.cfd_title");
		
		// les mappene
		$dirs = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$dirs[$row['cfd_id']] = $row;
		}
		
		return $dirs;
	}
	
	
	/** Hent filene inni denne mappen */
	public function get_files()
	{
		global $_base;
		
		// ikke tilgang?
		if (!$this->access())
		{
			// dette skal være sjekket allerede
			throw new HSException("Brukeren har ikke tilgang til filene i denne mappen.");
		}
		
		// hent filene med antall revisjoner og info om aktiv revisjon
		$result = $_base->db->query("SELECT cff_id, cff_title, cff_description, cff_access_level, cff_hidden, a.cfr_id, a.cfr_title, a.cfr_time, a.cfr_size, a.cfr_description, a.cfr_mime, COUNT(r.cfr_id) count_revisions FROM crewfiles_files LEFT JOIN crewfiles_revisions a ON cff_cfr_id = a.cfr_id LEFT JOIN crewfiles_revisions r ON cff_id = r.cfr_cff_id WHERE cff_cfd_id = $this->id GROUP BY cff_id ORDER BY cff_title");
		
		// les filene
		$files = array();
		while ($row = mysql_fetch_assoc($result))
		{
			// kontroller at vi har tilgang til denne filen
			if (!empty($row['cff_access_level']) && !crewfiles::access($row['cff_access_level']))
			{
				// hopp over filen
				continue;
			}
			
			$files[$row['cff_id']] = $row;
		}
		
		return $files;
	}
	
	/** Slett mappen */
	public function delete($confirm = false)
	{
		global $_base;
		
		// rotmappe kan ikke slettes
		if ($this->id == 0)
		{
			throw new HSException("Rotmappe kan ikke slettes.");
		}
		
		// antall mapper i denne mappen må være lik null for at mappen skal kunne slettes
		$result = $_base->db->query("SELECT COUNT(*) FROM crewfiles_directories WHERE cfd_parent_cfd_id = $this->id");
		if (mysql_result($result, 0, 0) > 0) return false;
		
		// antall filer i denne mappen må være lik null for at mappen skal kunne slettes
		$result = $_base->db->query("SELECT COUNT(*) FROM crewfiles_files WHERE cff_cfd_id = $this->id");
		if (mysql_result($result, 0, 0) > 0) return false;
		
		// godkjent sletting?
		if ($confirm)
		{
			// slett mappen
			$_base->db->query("DELETE FROM crewfiles_directories WHERE cfd_id = $this->id");
		}
		
		return true;
	}
	
	/** Lag mappe */
	public function create_directory($title, $description, $access_level)
	{
		global $_base;
		
		// ikke logget inn?
		if (!crewfiles::$up)
		{
			throw new HSException("Ingen spillerobjekt er registert for å kunne opprette en mappe.");
		}
		
		// opprett mappen
		$_base->db->query("INSERT INTO crewfiles_directories SET cfd_parent_cfd_id = $this->id, cfd_title = ".$_base->db->quote($title).", cfd_description = ".$_base->db->quote($description).", cfd_time = ".time().", cfd_up_id = ".crewfiles::$up->id.", cfd_access_level = ".$_base->db->quote($access_level));
		
		// hent mappe-ID
		$id = $_base->db->insert_id();
		
		// hent mappeinformasjon
		return new crewfiles_directory($id);
	}
	
	/** Rediger mappeinformasjon */
	public function edit($title, $description, $access_level)
	{
		global $_base;
		
		// rotmappe kan ikke endres
		if ($this->id == 0)
		{
			throw new HSException("Rotmappe kan ikke endres.");
		}
		
		// lagre endringer
		$_base->db->query("UPDATE crewfiles_directories SET cfd_title = ".$_base->db->quote($title).", cfd_description = ".$_base->db->quote($description).", cfd_access_level = ".$_base->db->quote($access_level)." WHERE cfd_id = $this->id");
		
		$this->info['cfd_title'] = $title;
		$this->info['cfd_description'] = $description;
		$this->info['cfd_access_level'] = $access_level;
		
		return true;
	}
	
	/** Flytt mappen */
	public function move($cfd_id)
	{
		global $_base;
		$cfd_id = (int) $cfd_id;
		
		// samme plassering?
		if ($cfd_id == $this->info['cfd_parent_cfd_id'])
		{
			return "no_change";
		}
		
		// kontroller at mappen finnes
		$dir = crewfiles::get_directory($cfd_id);
		if (!$dir)
		{
			// mappen finnes ikke
			return "404";
		}
		
		// hent path
		$path = array_keys($dir->get_path_raw());
		
		// sjekk at aktiv mappe ikke ligger inni path
		foreach ($path as $id)
		{
			// fant treff?
			if ($id == $this->info['cfd_id'])
			{
				return "inherit";
			}
		}
		
		// flytt mappen
		$_base->db->query("UPDATE crewfiles_directories SET cfd_parent_cfd_id = $cfd_id WHERE cfd_id = $this->id");
		
		// oppdater lokal info
		$this->info['cfd_parent_cfd_id'] = $cfd_id;
		$this->parent_dir = $dir;
		
		return true;
	}
	
	/** Last opp ny fil */
	public function upload($title, $description_file, $description_rev, $access_level, $filename, $mime, $path)
	{
		global $_base;
		
		// ikke logget inn?
		if (!crewfiles::$up)
		{
			// anonym Exception for at $data ikke skal gå ut
			throw new HSException("Ingen spillerobjekt er registert for å kunne laste opp fil.", sysreport::EXCEPTION_ANONYMOUS);
		}
		
		// opprett fil
		$_base->db->query("INSERT INTO crewfiles_files SET cff_cfd_id = $this->id, cff_title = ".$_base->db->quote($title).", cff_description = ".$_base->db->quote($description_file).", cff_time = ".time().", cff_up_id = ".crewfiles::$up->id.", cff_access_level = ".$_base->db->quote($access_level));
		
		// hent fil-ID
		$id = mysql_insert_id();
		
		// hent filinformasjon
		$file = new crewfiles_file($id);
		
		// last opp revisjonen
		return $file->upload($filename, $description_rev, $mime, $path, true);
	}
}

class crewfiles_file
{
	/** ID-en til filen */
	public $id;
	
	/** Informasjon om filen */
	public $info;
	
	/** Om man har tilgang til filen */
	public $access;
	
	/** Mappeobjektet */
	public $dir;
	
	/** Constructor */
	public function __construct($id)
	{
		global $_base;
		$this->id = intval($id);
		
		// finn filen
		$result = $_base->db->query("SELECT cff_id, cff_cfd_id, cff_title, cff_description, cff_time, cff_up_id, cff_cfr_id, cff_access_level, cff_hidden, cfd_title, cfd_access_level FROM crewfiles_files LEFT JOIN crewfiles_directories On cff_cfd_id = cfd_id WHERE cff_id = $this->id");
		$info = mysql_fetch_assoc($result);
		
		// fant ikke filen?
		if (!$info)
		{
			$this->info = false;
			return;
		}
		
		// lagre informasjon
		$this->info = $info;
		
		
		// identifiser om vi har tilgang
		$this->access = $this->access();
	}
	
	/**
	 * Sjekk for tilgang
	 * @param boolean $allow_login send til logg inn siden for utvidede tilganger hvis vi ikke er logget inn for det
	 */
	public function access($allow_login = NULL)
	{
		// kontroller tilgang til filen
		if (!empty($this->info['cff_access_level']))
		{
			// har ikke tilgang til filer?
			if (!crewfiles::access($this->info['cff_access_level'], $allow_login))
			{
				return false;
			}
		}
		
		// kontroller tilgang til mappen
		if (!empty($this->info['cfd_access_level']))
		{
			// har ikke tilgang til mappen?
			if (!crewfiles::access($this->info['cfd_access_level'], $allow_login))
			{
				return false;
			}
		}
		
		return true;
	}
	
	/** Valider tagnavn */
	public function validate_tag($tag)
	{
		// generer tagnavn
		$title = crewfiles::generate_tagname($this->info['cff_title']);
		
		// stemmer overens?
		return $title === $tag;
	}
	
	/** Hent revisjonene til filen */
	public function get_revisions()
	{
		global $_base;
		
		// hent revisjonene
		$result = $_base->db->query("SELECT cfr_id, cfr_title, cfr_description, cfr_time, cfr_up_id, cfr_mime, cfr_size, up_name, up_access_level FROM crewfiles_revisions LEFT JOIN users_players ON cfr_up_id = up_id WHERE cfr_cff_id = $this->id ORDER BY cfr_time DESC");
		
		// les revisjonene
		$revisions = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$revisions[$row['cfr_id']] = $row;
		}
		
		return $revisions;
	}
	
	/**
	 * Hent mappen til denne filen
	 * @return crewfiles_directory
	 */
	public function get_dir()
	{
		// har vi allerede objektet?
		if ($this->dir)
		{
			return $this->dir;
		}
		
		$dir = crewfiles::get_directory($this->info['cff_cfd_id']);
		
		// fant ikke mappen?
		if (!$dir)
		{
			global $_base;
			
			// sett til rotmappe
			$_base->db->query("UPDATE crewfiles_files SET cff_cfd_id = 0 WHERE cff_id = $this->id");
			$this->info['cff_cfd_id'] = 0;
			
			// hent rotmappen
			$dir = crewfiles::get_directory(0);
		}
		
		$this->dir = $dir;
		return $this->dir;
	}
	
	/** Slett filen */
	public function delete($confirm = false)
	{
		global $_base;
		
		// antall revisjoner må være lik null for at filen skal kunne slettes
		$result = $_base->db->query("SELECT COUNT(*) FROM crewfiles_revisions WHERE cfr_cff_id = $this->id");
		if (mysql_result($result, 0, 0) > 0) return false;
		
		// godkjent sletting?
		if ($confirm)
		{
			// slett filen
			$_base->db->query("DELETE FROM crewfiles_files WHERE cff_id = $this->id");
		}
		
		return true;
	}
	
	/** Hent objekt for aktiv revisjon */
	public function get_active_revision()
	{
		return crewfiles::get_revision($this->info['cff_cfr_id']); 
	}
	
	/** Rediger filinformasjon */
	public function edit($title, $description, $access_level, $hidden)
	{
		global $_base;
		
		// lagre endringer
		$_base->db->query("UPDATE crewfiles_files SET cff_title = ".$_base->db->quote($title).", cff_description = ".$_base->db->quote($description).", cff_access_level = ".$_base->db->quote($access_level).", cff_hidden = ".($hidden ? 1 : 0)." WHERE cff_id = $this->id");
		
		$this->info['cff_title'] = $title;
		$this->info['cff_description'] = $description;
		$this->info['cff_access_level'] = $access_level;
		$this->info['cff_hidden'] = $hidden ? 1 : 0;
		
		return true;
	}
	
	/** Flytt filen */
	public function move($cfd_id)
	{
		global $_base;
		$cfd_id = (int) $cfd_id;
		
		// samme plassering?
		if ($cfd_id == $this->info['cff_cfd_id'])
		{
			return "no_change";
		}
		
		// kontroller at mappen finnes
		$dir = crewfiles::get_directory($cfd_id);
		if (!$dir)
		{
			// mappen finnes ikke
			return "404";
		}
		
		// har vi ikke tilgang til å flytte filer til denne mappen? (ikke filtilgang)
		if (!$dir->access())
		{
			return "no_access";
		}
		
		// flytt filen
		$_base->db->query("UPDATE crewfiles_files SET cff_cfd_id = $cfd_id WHERE cff_id = $this->id");
		
		// oppdater lokal info
		$this->info['cff_cfd_id'] = $cfd_id;
		
		// oppdater mappeobjekt
		$this->dir = $dir;
		
		return true;
	}
	
	/** Last opp ny revisjon */
	public function upload($filename, $description, $mime, $src, $set_active = false)
	{
		global $_base;
		
		// ikke logget inn?
		if (!crewfiles::$up)
		{
			// anonym Exception for at $data ikke skal gå ut
			throw new HSException("Ingen spillerobjekt er registert for å kunne laste opp fil.", sysreport::EXCEPTION_ANONYMOUS);
		}
		
		// sett opp adresse til ny fil
		$name = uniqid()."-".crewfiles::$up->id;
		$path = CREWFILES_DATA_FOLDER . "/" . $name;
		
		// kan vi flytte filen?
		if (is_uploaded_file($src))
		{
			move_uploaded_file($src, $path);
			$size = filesize($path);
		}
		
		else
		{
			$size = @file_put_contents($path, @file_get_contents($src));
		}
		
		// mislykket?
		if ($size === false)
		{
			// anonym Exception for at $data ikke skal gå ut
			throw new HSException("Kunne ikke opprette fil $path.", sysreport::EXCEPTION_ANONYMOUS);
		}
		
		// lagre revisjon
		$_base->db->query("INSERT INTO crewfiles_revisions SET cfr_cff_id = $this->id, cfr_title = ".$_base->db->quote($filename, false).", cfr_description = ".$_base->db->quote($description).", cfr_time = ".time().", cfr_up_id = ".crewfiles::$up->id.", cfr_mime = ".$_base->db->quote($mime).", cfr_path = ".$_base->db->quote($name).", cfr_size = ".intval($size));
		
		// hent revisjon-ID
		$id = mysql_insert_id();
		
		// hent revisjoninformasjon
		$revision = new crewfiles_revision($id);
		$revision->file = $this;
		
		// sette revisjonen aktiv?
		if ($set_active)
		{
			$revision->set_active();
		}
		
		return $revision;
	}
}

class crewfiles_revision
{
	/** ID-en til revisjonen */
	public $id;
	
	/** Informasjon om revisjonen */
	public $info;
	
	/** Filobjektet */
	public $file;
	
	/** Constructor */
	public function __construct($id)
	{
		global $_base;
		$this->id = intval($id);
		
		// finn revisjonen
		$result = $_base->db->query("SELECT cfr_id, cfr_cff_id, cfr_title, cfr_description, cfr_time, cfr_up_id, cfr_mime, cfr_path, cfr_size FROM crewfiles_revisions WHERE cfr_id = $this->id");
		$info = mysql_fetch_assoc($result);
		
		// fant ikke revisjonen?
		if (!$info)
		{
			$this->info = false;
			return;
		}
		
		// lagre informasjon
		$this->info = $info;
	}
	
	/** Valider tagnavn */
	public function validate_tag($tag)
	{
		// generer tagnavn
		$title = crewfiles::generate_tagname($this->info['cfr_title']);
		
		// stemmer overens?
		return $title === $tag;
	}
	
	/**
	 * Sjekk om vi har tilgang til revisjonen (filen)
	 * @param boolean $allow_login send til logg inn siden for utvidede tilganger hvis vi ikke er logget inn for det
	 */
	public function access($allow_login = NULL)
	{
		// hent filen
		$file = $this->get_file();
		
		// tilgang?
		return $file->access($allow_login);
	}
	
	/**
	 * Finn filen denne revisjonen tilhører
	 * @return crewfiles_file
	 */
	public function get_file()
	{
		// har vi allerede filobjektet?
		if ($this->file)
		{
			return $this->file;
		}
		
		// filen skal uansett finnes pga. relasjoner
		$this->file = crewfiles::get_file($this->info['cfr_cff_id']);
		
		return $this->file;
	}
	
	/** Hent full filbane til selve filen */
	public function get_path()
	{
		return CREWFILES_DATA_FOLDER . "/" . $this->info['cfr_path'];
	}
	
	/** Slette revisjonen */
	public function delete()
	{
		global $_base;
		
		// fjern filen
		if (!@unlink($this->get_path()))
		{
			sysreport::log("Kunne ikke slette ".$this->get_path().".", "Kofradia: crewfiles_revision->delete() err");
		}
		
		// er dette en aktiv revisjon?
		if ($this->get_file()->info['cff_cfr_id'] == $this->id)
		{
			// fjern som aktiv revisjon
			$_base->db->query("UPDATE crewfiles_files SET cff_cfr_id = NULL WHERE cff_id = {$this->info['cfr_cff_id']} AND cff_cfr_id = $this->id");
		}
		
		// slett fra databasen
		$_base->db->query("DELETE FROM crewfiles_revisions WHERE cfr_id = $this->id");
	}
	
	/** Sett som aktiv revisjon */
	public function set_active()
	{
		global $_base;
		
		// oppdater filen
		$_base->db->query("UPDATE crewfiles_files SET cff_cfr_id = $this->id WHERE cff_id = {$this->info['cfr_cff_id']}");
	}
	
	/** Hent data for revisjonen */
	public function get_raw()
	{
		// hent ut data
		return @file_get_contents($this->get_path());
	}
	
	/** Rediger revisjonsinformasjon */
	public function edit($title, $description, $mime)
	{
		global $_base;
		
		// sørg for at tittelen (filename) ikke inneholder noen ugyldige tegn
		$title = crewfiles::filter_filename($title);
		
		// lagre endringer
		$_base->db->query("UPDATE crewfiles_revisions SET cfr_title = ".$_base->db->quote($title).", cfr_description = ".$_base->db->quote($description).", cfr_mime = ".$_base->db->quote($mime)." WHERE cfr_id = $this->id");
		
		$this->info['cfr_title'] = $title;
		$this->info['cfr_description'] = $description;
		$this->info['cfr_mime'] = $mime;
	}
}