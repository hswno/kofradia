<?php namespace Kofradia\Controller;

use \Kofradia\Donation;
use \Kofradia\View;

class Donations extends \Kofradia\Controller
{
	/**
	 * Show infopage
	 */
	public function action_index()
	{
		// vise donasjonene?
		if (isset($_GET['vis']))
		{
			return $this->action_list();
		}

		\ess::$b->page->add_title("Donasjon");

		return View::forge("donations/index");
	}
	
	/**
	 * Show full list
	 */
	protected function action_list()
	{
		\ess::$b->page->add_title("Donasjoner");

		// hent donasjonene på denne siden
		$pagei = new \pagei(\pagei::ACTIVE_GET, "side", \pagei::PER_PAGE, 30);
		$list = Donation::getDonations($pagei);

		return View::forge("donations/list", array(
			"pagei"     => $pagei,
			"donations" => $list));
	}
}