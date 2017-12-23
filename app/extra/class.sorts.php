<?php

// sortering

/*

// hent alle kontaktene med sist aktiv tid
$sort_k = new sorts("s_k");
$sort_k->append("asc", "Navn", "u.user");
$sort_k->append("desc", "Navn", "u.user DESC");
$sort_k->append("asc", "Sist aktiv", "u.last_online DESC");
$sort_k->append("desc", "Sist aktiv", "u.last_online");
$sort_k->append("asc", "Lagt til som kontakt", "c.time");
$sort_k->append("desc", "Lagt til som kontakt", "c.time DESC");
$sort_k->set_active(getval('s_k'), 0);
$info_k = $sort_k->active();


*/

class sorts {
	public $elms = array();
	public $active = NULL;
	public $types = array();
	public $address = NULL;
	public $sort_name = NULL;

	// construct
	function __construct($sort_name = "sort", $address = NULL)
	{
		// sett riktig adresse til knapper
		$this->types = array(
			"asc" => array(
				STATIC_LINK."/other/sort_asc.gif",	// not active
				STATIC_LINK."/other/sort_asc_e.gif"	// active
			),
			"desc" => array(
				STATIC_LINK."/other/sort_desc.gif",	// not active
				STATIC_LINK."/other/sort_desc_e.gif"	// active
			)
		);
		
		if (is_null($address)) $address = PHP_SELF;
		$this->address = $address;
		$this->sort_name = $sort_name;
	}

	// legg til element (sorteringsalternativ)
	function append($type, $title, $params)
	{
		if (!isset($this->types[$type]))
		{
			trigger_error("Type $type finnes ikke i listen.", E_USER_ERROR);
		}

		$this->elms[] = array("type" => $type, "title" => $title, "params" => $params);
		return count($this->elms)-1;
	}

	// finnes elementet
	function exists($id)
	{
		return isset($this->elms[$id]);
	}

	// prøv å sett som aktiv
	function set_active()
	{
		for ($i = 0; $i < func_num_args(); $i++)
		{
			$arg = func_get_arg($i);
			if ($this->exists($arg))
			{
				$this->active = $arg;
				return true;
			}
		}

		return false;
	}

	// hent den aktive
	function active()
	{
		if (is_null($this->active))
		{
			trigger_error("Ingen sort er satt aktiv.", E_USER_ERROR);
		}

		return $this->elms[$this->active];
	}

	// vis sorterlenke
	function show_link()
	{
		$ret = array();

		for ($i = 0; $i < func_num_args(); $i++)
		{
			$arg = func_get_arg($i);

			if (!$this->exists($arg))
			{
				trigger_error("Fant ikke elementet med ID $arg!", E_USER_ERROR);
			}

			$type = $this->types[$this->elms[$arg]['type']];

			// aktiv?
			$active = $this->active == $arg ? 1 : 0;

			$ret[] = '<a href="'.htmlspecialchars(game::address($this->address, $_GET, array($this->sort_name), array($this->sort_name => $arg))).'" class="op50"><img src="'.$type[$active].'" alt="'.htmlspecialchars($this->elms[$arg]['title']).'" /></a>';

			#if ($active == 1) return end($ret);
		}

		return implode("", $ret);
	}

	// vis sorterknapp
	// krever at vi har et element med ID sort_NAME hvor NAME er navnet vi sorterer for
	function show_button()
	{
		$ret = array();

		for ($i = 0; $i < func_num_args(); $i++)
		{
			$arg = func_get_arg($i);

			if (!$this->exists($arg))
			{
				trigger_error("Fant ikke elementet med ID $arg!", E_USER_ERROR);
			}

			$type = $this->types[$this->elms[$arg]['type']];

			// aktiv?
			$active = $this->active == $arg ? 1 : 0;

			$ret[] = '<a href="#" onclick="var e=$(\'sort_'.$this->sort_name.'\');e.value=\''.addcslashes($arg, '').'\';e.form.submit();return false"><img src="'.$type[$active].'" alt="'.htmlspecialchars($this->elms[$arg]['title']).'" /></a>';

			#if ($active == 1) return end($ret);
		}

		return implode("", $ret);
	}
}