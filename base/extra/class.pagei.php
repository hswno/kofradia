<?php

// avansert versjon av sideinfo
class pagei
{
	public $total = 0;
	public $active = 1;
	public $per_page = 15;
	public $pages = 0;
	public $start = 0;
	public $count_page = 0; // antall innlegg vi viser
	
	const TOTAL = 1;
	const ACTIVE = 2;
	const PER_PAGE = 3;
	const ACTIVE_LAST = 4;
	const ACTIVE_GET = 5;
	const ACTIVE_POST = 6;
	#const NO_CALC = 7;
	
	/** Hva $_GET variabelen heter, dersom den blir brukt */
	public $get_name = false;
	
	/** Construct */
	public function __construct()
	{
		$override = true;
		for ($i = 0; $i < func_num_args(); $i++)
		{
			$arg = func_get_arg($i);
			
			switch ($arg)
			{
				case self::TOTAL:
					$num = intval(func_get_arg(++$i));
					$this->set_total($num);
					$override = false;
				break;
				
				case self::ACTIVE:
					$num = intval(func_get_arg(++$i));
					$this->set_active($num);
				break;
				
				case self::PER_PAGE:
					$num = intval(func_get_arg(++$i));
					$this->set_per_page($num);
				break;
				
				case self::ACTIVE_LAST:
					$this->set_active($this->pages);
				break;
				
				case self::ACTIVE_GET:
					$e = func_get_arg(++$i);
					if (isset($_GET[$e]))
					{
						$num = intval($_GET[$e]);
						$this->set_active($num);
					}
					$this->get_name = $e;
				break;
				
				case self::ACTIVE_POST:
					$e = func_get_arg(++$i);
					if (isset($_POST[$e]))
					{
						$num = intval($_POST[$e]);
						$this->set_active($num);
					}
					if (!$this->get_name) $this->get_name = $e;
				break;
				
				#case self::NO_CALC:
					#$calc = false;
				#break;
				
				default:
					error_log("HSW: class pagei func pagei() unknown arg: $arg", E_USER_WARNING);
			}
		}
		
		return $this->calc($override);
	}
	
	/** Sett totalt antall rader */
	public function set_total($num)
	{
		$this->total = max(0, $num);
	}
	
	/** Sett aktiv side */
	public function set_active($num)
	{
		$this->active = max(1, $num);
	}
	
	/** Sett antall innlegg per side */
	public function set_per_page($num)
	{
		$this->per_page = max(1, $num);
	}
	
	/** Kalkuler verdier */
	public function calc($override = false)
	{
		$ok = true;
		
		// antall sider
		$this->pages = max(1, ceil($this->total/$this->per_page));
		
		// kontroller aktiv side
		if ($this->active > $this->pages)
		{
			$ok = false;
			if (!$override) $this->active = $this->pages;
		}
		
		// innleggstart
		$this->start = ($this->active - 1) * $this->per_page;
		
		// antall innlegg på siden
		$this->count_page = $this->active == $this->pages ? $this->total - ($this->pages - 1) * $this->per_page : $this->per_page;
		
		return $ok;
	}
	
	/** Hent antall rader uten LIMIT */
	public function found_rows()
	{
		global $_base;
		
		// hent antall
		$result = $_base->db->query("SELECT FOUND_ROWS()");
		$this->total = mysql_result($result, 0);
		
		// sjekk om vi er på OK side
		// hvis returnerer false betyr det at vi er på en tom side
		return $this->calc();
	}
	
	/** Utfør spørring */
	public function query($query, $critical = true, $debug = false)
	{
		$query = preg_replace("/^\\s*SELECT\\s+/", "", $query);
		$result = ess::$b->db->query("SELECT SQL_CALC_FOUND_ROWS $query LIMIT {$this->start}, {$this->per_page}", $critical, $debug);
		
		// hvis vi ikke er på en gyldig side
		if (!$this->found_rows() && $this->total > 0)
		{
			$this->set_active(1);
			$this->calc();
			$result = ess::$b->db->query("SELECT $query LIMIT {$this->start}, {$this->per_page}");
		}
		
		return $result;
	}
	
	/**
	 * Lager sidetall linker.
	 *
	 * @param mixed $page_1 adresse til pagenumbers funksjonen / array(fjern,disse,fra,adressen) / "input"
	 * @param string $page_x (bruk &lt;page&gt; eller _pageid_)
	 * @return string
	 */
	public function pagenumbers($page_1 = NULL, $page_x = NULL)
	{
		// generere lenker?
		if ($page_1 === NULL || is_array($page_1))
		{
			$rem = array($this->get_name);
			if (is_array($page_1)) $rem = array_merge($rem, $page_1);
			$page_1 = game::address(redirect::$location ?: PHP_SELF, $_GET, $rem);
			$page_x = game::address(redirect::$location ?: PHP_SELF, $_GET, $rem, array($this->get_name => "_pageid_"));
		}
		
		// som <input> knapper?
		elseif ($page_x === NULL && $page_1 == "input")
		{
			$page_x = $this->get_name;
		}
		
		return pagenumbers($page_1, $page_x, $this->pages, $this->active);
	}
	
	/**
	 * Lager sidetall-lenker for ajax/javascript
	 */
	public function pagenumbers_ajax()
	{
		$obj = new pagenumbers_ajax($this->pages, $this->active);
		return $obj->build();
	}
}