<?php

/**
 * Generere sidetall lenker
 */
class pagenumbers
{
	/** Antall man alltid skal vise fra aktiv side */
	protected $jump = 3;
	
	protected $page_1;
	protected $page_x;
	protected $page_1_safe;
	protected $page_x_safe;
	protected $page;
	protected $pages;
	
	/**
	 * Constructor
	 * @param string $page_1 (ikke htmlspecialchars)
	 * @param string $page_x (ikke htmlspecialchars, bruk _pageid_)
	 * @param integer $pages (antall sider)
	 * @param integer $page (aktiv side)
	 */
	public function __construct($page_1, $page_x, $pages, $page)
	{
		$this->page_1 = $page_1;
		$this->page_1_safe = htmlspecialchars($this->page_1);
		$this->page_x = $page_x;
		$this->page_x_safe = htmlspecialchars($this->page_x);
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
	public function link($i, $html)
	{
		return '<a href="'.($i == 1 ? $this->page_1_safe : str_replace("_pageid_", $i, $this->page_x_safe)).'">'.$html.'</a>';
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
					$ret[] = '<a href="#" onclick="var value=parseInt(prompt(\'Ønsket sidetall? (1 til '.$this->pages.')\','.$this->page.'));if(value>1&&value<'.$this->pages.'){setTimeout(function(){window.location=unescape(\''.rawurlencode($this->page_x).'\').replace(/_pageid_/g,value)},1)}else if(value==1){setTimeout(function(){window.location=unescape(\''.rawurlencode($this->page_1).'\')},1)}return false">..</a>';
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
				$ret[] = $this->link($i, $i);
			}
		}
		
		// lenke til forrige side
		if ($this->page > 1)
		{
			array_unshift($ret, $this->link($this->page-1, '&laquo; Forrige side').' -');
		}
		
		// lenke til neste side
		if ($this->page < $this->pages)
		{
			$ret[] = '- '.$this->link($this->page+1, 'Neste side &raquo;');
		}
		
		return implode(" ", $ret);
	}
}