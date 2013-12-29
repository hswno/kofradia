<?php namespace Kofradia\Controller;

use \Kofradia\DB;
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
	public function action_list()
	{
		\ess::$b->page->add_title("Donasjoner");

		// hent donasjonene på denne siden
		$pagei = new \pagei(\pagei::ACTIVE_GET, "side", \pagei::PER_PAGE, 30);
		$list = Donation::getDonations($pagei);

		return View::forge("donations/list", array(
			"pagei"     => $pagei,
			"donations" => $list));
	}

	/**
	 * Handle notify URLs from PayPal
	 */
	public function action_notify()
	{
		file_put_contents(PATH_ROOT."/paypal.log", print_r($_POST, true), FILE_APPEND); // TODO: remove this when tested on production

		if (!isset($_POST['receiver_email']) || $_POST['receiver_email'] != 'henrist@henrist.net')
		{
			die;
		}

		// should really check for duplicates, but we don't
		// but it must be "completed"
		if (!isset($_POST['payment_status']) || $_POST['payment_status'] != 'Completed')
		{
			die;
		}

		// verify it
		$verify = Donation::verifyPayPalData($_POST);
		if (!$verify)
		{
			die;
		}

		// check for user etc
		$custom = postval("custom");
		if (!preg_match('~^(.*):(.*);public=(0|1)$~', $custom, $matches))
		{
			die;
		}

		trigger_error("should add");

		$player = null;
		if ($matches[1] != "gjest")
		{
			// find this player
			$result = DB::get()->query("
				SELECT up_id
				FROM users_players
					LEFT JOIN users ON up_u_id = u_id
					LEFT JOIN sessions ON u_id = ses_u_id
				WHERE ses_id = ".DB::quote($matches[1])." AND up_id = ".DB::quote($matches[2])." LIMIT 1");
			if ($up_id = $result->fetchColumn(0))
			{
				$player = \player::get($up_id);
			}
		}

		$time = \ess::$b->date->parse(postval("payment_date"));

		// add it
		$d = Donation::create(postval("mc_gross"), $time, ($matches[3] ? $player->id : null));

		putlog("CREWCHAN", sprintf("%%uDONASJON:%%u %s %s ble donert av %s",
			postval("mc_currency"),
			postval("mc_gross"),
			$player ? $player->data['up_name'] . ($matches[3] ? ' (synlig)' : ' (som anonym)') : 'anonym gjest'));

		// no output
		die;
	}
}