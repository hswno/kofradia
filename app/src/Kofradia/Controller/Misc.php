<?php namespace Kofradia\Controller;

use \Kofradia\Settings;
use \Kofradia\View;
use \Kofradia\Donation;

class Misc extends \Kofradia\Controller {
	public $createResponseObject = false;

	/**
	 * Ikke styr ssl foer kontrolleren
	 */
	protected $ssl = null;

	/**
	 * Main page
	 */
	public function action_index()
	{
		// logge inn?
		// tar seg også av eventuell nødvendig reauth ved ukjent IP
		if (!$this->user)
		{
			force_https();
			return \Kofradia\Controller::execute("Users\\Login@index");
		}

		// videresende?
		if (isset($_GET['orign']))
		{
			\redirect::handle($_GET['orign'], \redirect::SERVER, \login::$info['ses_secure']);
		}

		new \page_forsiden(\login::$user->player);
	}

	/**
	 * Show betingelser
	 */
	public function action_betingelser()
	{
		\ess::$b->page->add_title("Betingelser");
		$user = \login::$logged_in ? \login::$user : null;

		// markere betingelsene som sett?
		$updated = false;
		if ($user && ($user->data['u_tos_version'] != intval(Settings::get('tos_version')) || empty($user->data['u_tos_accepted_time'])))
		{
			$updated = true;
			
			$user->data['u_tos_version'] = intval(Settings::get("tos_version"));
			$user->data['u_tos_accepted_time'] = time();
			
			\Kofradia\DB::get()->exec("
				UPDATE users
				SET u_tos_version = ".$user->data['u_tos_version'].",
					u_tos_accepted_time = ".time()."
				WHERE u_id = ".$user->id);
		}

		return View::forge("misc/betingelser", array(
			"tos_version" => Settings::get("tos_version"),
			"tos_update"  => Settings::get("tos_update"),
			"tos"         => Settings::get("tos"),
			"user"        => $user,
			"updated"     => $updated));
	}

	/**
	 * Show 'takk til'-page
	 */
	public function action_credits()
	{
		\ess::$b->page->add_title("Takk til");

		$pagei = new \pagei(\pagei::PER_PAGE, 15);
		$donations = Donation::getDonations($pagei);

		return View::forge("misc/takk_til", array(
			"donations" => $donations));
	}
}