<?php

/**
 * Generere sidetall knapper <input type="submit">
 */
class pagenumbers_input
{
	/** Antall man alltid skal vise fra aktiv side */
	protected $jump = 3;
	protected $name;
	protected $name_safe;
	protected $page;
	protected $pages;
	
	/**
	 * Constructor
	 * @param string $page_1 (ikke htmlspecialchars)
	 * @param string $page_x (ikke htmlspecialchars, bruk _pageid_)
	 * @param integer $pages (antall sider)
	 * @param integer $page (aktiv side)
	 */
	public function __construct($name, $pages, $page)
	{
		$this->name = $name;
		$this->name_safe = htmlspecialchars($this->name);
		$this->page = (int) $page;
		$this->pages = (int) $pages;
		return $this;
	}
	
	/**
	 * Sett jump
	 * @param integer $jump (antall man alltid skal vise fra aktiv side, 0-10)
	 */
	public function jump($int)
	{
		$this->jump = max(10, min(0, (int) $int));
		return $this;
	}
	
	/**
	 * Generer lenke
	 * @param integer $i
	 * @param string $html
	 */
	public function link($i)
	{
		return show_sbutton($i, 'name="'.$this->name_safe.'"');
	}
	
	/**
	 * Generer alle lenkene
	 */
	public function build()
	{
		$ret = array();
		$low = $this->page - $this->jump;
		$high = $this->page + $this->jump;
		
		for ($i = 1; $i <= $this->pages; $i++)
		{
			// skal vi hoppe over lenken?
			if ($i > 1 && $i < $this->pages && ($i < $low || $i > $high))
			{
				// finn sidetallet vi skal hoppe til (minus 1)
				$n = $i > $high ? $this->pages - 1 : $low - 1;
				
				// hopp kun over hvis det er mer enn én side
				if ($n - $i >= 1)
				{
					$i = $n;
					$ret[] = show_sbutton("..", 'name="'.$this->name_safe.'" onclick="var v=prompt(\'Ønsket sidetall? (1-'.$this->pages.')\', 0); if (v && v >= 1 && v <= '.$this->pages.') this.value = v; else return false"');
					continue;
				}
			}
			
			// aktiv side?
			if ($i == $this->page)
			{
				$ret[] = "[$i]";
			}
			
			else
			{
				$ret[] = $this->link($i);
			}
		}
		
		return implode(" ", $ret);
	}
}