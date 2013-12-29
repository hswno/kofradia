<?php namespace Kofradia\Controller;

use \Kofradia\View;

class Stats extends \Kofradia\Controller {
	/**
	 * Shows a recordlist of online users within each hour
	 */
	public function action_online_top()
	{
		\ess::$b->page->add_title("Antall pålogget rekorder");

		// hent stats
		$result = \Kofradia\DB::get()->query("SELECT name, extra, value, time FROM sitestats");
		$sitestats = array();
		$sitestats_max = array();

		while ($row = $result->fetch())
		{
			$sitestats[$row['name']][$row['extra']] = $row;
			$sitestats[$row['name']][$row['extra']] = $row;
			
			if (!array_key_exists($row['name'], $sitestats_max))
			{
				$sitestats_max[$row['name']] = $row;
			}
			else
			{
				if ($row['value'] > $sitestats_max[$row['name']]['value'])
				{
					$sitestats_max[$row['name']] = $row;
				}
			}
		}

		return View::forge("stats/online_top", array(
			"sitestats" => $sitestats,
			"sitestats_max" => $sitestats_max));
	}
}