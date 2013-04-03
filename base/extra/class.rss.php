<?php

// finn ut om vi skal enkelt- eller dobbeltkode tegnene i <title>
// (Se http://www.xn--8ws00zhy3a.com/blog/2006/06/encoding-rss-titles)
// kjør enkeltkoding kun for firefox
define("RSS_SINGLE_ENCODE_TITLE", strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "firefox") !== false);

class rss
{
	/**
	 * Data
	 * @var array
	 */
	private $data = array(
		"generator" => '<generator>HSW-RSS</generator>'
	);
	
	/**
	 * Enhetene
	 * @var array
	 */
	private $items = array();
	
	/**
	 * Construct
	 *
	 * @param string $title (encode all HTML chars)
	 * @param string $link
	 * @param string $description
	 */
	public function __construct($title, $link, $description)
	{
		$this->data['title'] = '<title>'.htmlspecialchars(RSS_SINGLE_ENCODE_TITLE ? html_entity_decode($title) : $title).'</title>';
		$this->data['link'] = '<link>'.htmlspecialchars($link).'</link>';
		$this->data['description'] = '<description>'.htmlspecialchars($description).'</description>';
	}
	
	/**
	 * Sett språk
	 *
	 * @param string $lang
	 */
	public function language($lang)
	{
		$this->data['language'] = '<language>'.htmlspecialchars($lang).'</language>';
	}
	
	/**
	 * Sett copyright tekst
	 *
	 * @param string $text
	 */
	public function copyright($text)
	{
		$this->data['copyright'] = '<copyright>'.htmlspecialchars($text).'</copyright>';
	}
	
	/**
	 * Sett managingEditor
	 *
	 * @param string $text
	 */
	public function managingEditor($text)
	{
		$this->data['managingEditor'] = '<managingEditor>'.htmlspecialchars($text).'</managingEditor>';
	}
	
	/**
	 * Sett webMaster
	 *
	 * @param string $text
	 */
	public function webMaster($text)
	{
		$this->data['webMaster'] = '<webMaster>'.htmlspecialchars($text).'</webMaster>';
	}
	
	/**
	 * Sett publisert dato
	 *
	 * @param integer $timestamp
	 */
	
	public function pubDate($timestamp)
	{
		$this->data['pubDate'] = '<pubDate>'.date("r", $timestamp).'</pubDate>';
	}
	
	/**
	 * Sett sist gang innholdet ble endret
	 *
	 * @param integer $timestamp
	 */
	public function lastBuildDate($timestamp)
	{
		$this->data['lastBuildDate'] = '<lastBuildDate>'.date("r", $timestamp).'</pubDate>';
	}
	
	/**
	 * Sett varighet for feed i minutter
	 *
	 * @param integer $minutes
	 */
	public function ttl($minutes)
	{
		$this->data['ttl'] = '<ttl>'.intval($minutes).'</ttl>';
	}
	
	/**
	 * Sett bilde
	 *
	 * @param string $link
	 * @param string $title
	 * @param string $url
	 * @param optional string $description
	 * @param optional integer $width
	 * @param optional integer $height
	 */
	public function image($link, $title, $url, $description = NULL, $width = NULL, $height = NULL)
	{
		$elms = array(
			'<link>'.htmlspecialchars($link).'</link>',
			'<title>'.htmlspecialchars($title).'</title>',
			'<url>'.htmlspecialchars($url).'</url>'
		);
		
		if (!empty($description))
		{
			$elms[] = '<description>'.htmlspecialchars($description).'</description>';
		}
		
		if (!empty($width))
		{
			$elms[] = '<width>'.intval($width).'</width>';
		}
		
		if (!empty($height))
		{
			$elms[] = '<height>'.intval($height).'</height>';
		}
		
		$this->data['image'] = array('image', $elms);
	}
	
	/**
	 * Legg til element
	 * 
	 * @param rss_item $item
	 */
	public function item($item)
	{
		if (get_class($item) != "rss_item")
		{
			throw new HSException("Element er ikke rss element.");
		}
		
		$this->items[] = $item;
	}
	
	/**
	 * Generer RSS
	 */
	public function generate($seperator = "\n", $indent = "\t")
	{
		$data = $this->data;
		foreach ($this->items as $item)
		{
			$data[] = array("item", $item->data);
		}
		$data = array(array("rss", array(array("channel", $data)), array("version" => "2.0")));
		
		$result = '<?xml version="1.0" encoding="utf-8"?>'.$seperator.$this->generate_xml($data, $seperator, $indent, '');
		return $result;
	}
	
	/**
	 * Generer XML for RSS
	 */
	private function generate_xml($data, $seperator, $indent, $indentions)
	{
		$result = array();
		$indentions_next = $indentions.$indent;
		foreach ($data as $item)
		{
			// inneholder element med underelementer?
			if (is_array($item))
			{
				// noen atributter?
				$attr = '';
				if (isset($item[2]))
				{
					$attr = array();
					foreach ($item[2] as $key => $a)
					{
						$attr[] = $key.'="'.htmlspecialchars($a).'"';
					}
					$attr = " ".implode(" ", $attr);
				}
				
				$result[] = $indentions.'<'.$item[0].$attr.'>'.$seperator.$this->generate_xml($item[1], $seperator, $indent, $indentions_next).$seperator.$indentions.'</'.$item[0].'>';
				continue;
			}
			
			// ett element
			$result[] = $indentions.$item;
		}
		
		return implode($seperator, $result);
	}
}

class rss_item
{
	// required
	public $data = array();
	
	/**
	 * Sett tittel
	 *
	 * @param string $title
	 */
	public function title($title)
	{
		$this->data['title'] = '<title>'.htmlspecialchars(RSS_SINGLE_ENCODE_TITLE ? html_entity_decode($title) : $title).'</title>';
		return $this;
	}
	
	/**
	 * Sett link
	 *
	 * @param string $uri
	 */
	public function link($uri)
	{
		$this->data['link'] = '<link>'.htmlspecialchars($uri).'</link>';
		return $this;
	}
	
	/**
	 * Sett beskrivelse
	 *
	 * @param string $text
	 */
	
	public function description($text)
	{
		$this->data['description'] = '<description>'.htmlspecialchars($text).'</description>';
		return $this;
	}
	
	/**
	 * Sett forfatter
	 * 
	 * @param string $author
	 */
	public function author($author)
	{
		$this->data['author'] = '<author>'.htmlspecialchars($author).'</author>';
		return $this;
	}
	
	/**
	 * Sett guid
	 * 
	 * @param string $guid
	 * @param optional boolean $isPermaLink = true
	 */
	public function guid($guid, $isPermaLink = true)
	{
		$this->data['guid'] = '<guid'.(!$isPermaLink ? ' isPermaLink="false"' : '').'>'.htmlspecialchars($guid).'</guid>';
		return $this;
	}
	
	/**
	 * Sett publisert dato
	 *
	 * @param int $timestamp
	 */
	public function pubDate($timestamp)
	{
		$this->data['pubDate'] = '<pubDate>'.date("r", $timestamp).'</pubDate>';
		return $this;
	}
}