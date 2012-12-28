<?php

/*
 * Live-feed er en oversikt på forsiden med de siste nye handlingene i spillet
 * som alle spillere skal kunne se
 */

class livefeed
{
	/**
	 * Legg til oppføring
	 */
	public static function add_row($html, $time = null)
	{
		$time = (int) $time;
		if (!$time) $time = time();
		
		if (empty($html)) throw new HSException("Mangler HTML.");
		
		// legg til oppføringen
		ess::$b->db->query("INSERT INTO livefeed SET lf_time = $time, lf_html = ".ess::$b->db->quote($html));
	}
	
	/**
	 * Hent siste oppføringene
	 */
	public static function get_latest($limit = 20)
	{
		$limit = (int) $limit;
		if ($limit <= 0) $limit = 1;
		
		$result = ess::$b->db->query("SELECT lf_time, lf_html FROM livefeed ORDER BY lf_time DESC, lf_id DESC LIMIT $limit");
		
		$data = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$data[] = $row;
		}
		
		return $data;
	}
}