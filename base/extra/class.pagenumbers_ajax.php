<?php

/**
 * Generere sidetall for ajax/javascript
 */
class pagenumbers_ajax
{
	/** Antall man alltid skal vise fra aktiv side */
	protected $jump = 3;
	protected $page;
	protected $pages;
	
	/**
	 * Constructor
	 * @param integer $pages (antall sider)
	 * @param integer $page (aktiv side)
	 */
	public function __construct($pages, $page)
	{
		$this->page = (int) $page;
		$this->pages = (int) $pages;
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
	 */
	public function link($i)
	{
		return '<a onclick="this.getParent(\'span\').fireEvent(\'set_page\', '.$i.')">'.$i.'</a>';
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
					$ret[] = '<a onclick="var v=prompt(\'Ønsket sidetall? (1-'.$this->pages.')\', 0); if (v && v >= 1 && v <= '.$this->pages.') this.getParent(\'span\').fireEvent(\'set_page\', v)">..</a>';
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
		
		return '<span class="pagenumbers">'.implode(" ", $ret).'</span>';
	}
}