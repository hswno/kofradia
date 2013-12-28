<?php namespace Kofradia\Controller;

use \Kofradia\GitHub\Hendelser;
use \Kofradia\View;

class GitHub extends \Kofradia\Controller
{
	public function action_index()
	{
		// sjekk at dette er GitHub
		if (!isset($_POST['payload']) || !\Kofradia\Network\Helpers::cidr_match($_SERVER['REMOTE_ADDR'], "192.30.252.0/22")) {
			return $this->show_list();
		}

		$event = $_SERVER['HTTP_X_GITHUB_EVENT'];
		$payload = json_decode($_POST['payload'], true);

		// log all request (actually just for debugging)
		$this->log_payload($event, $payload);

		// add event
		Hendelser::addEvent($event, $payload);
	}

	/**
	 * Show list of GitHub-events
	 * This is allowed for all users, including guests
	 */
	public function show_list()
	{
		if (\login::$logged_in)
		{
			$github = \Kofradia\Users\GitHub::get(\login::$user);
			$events = $events = $github->getUnseenEvents();
			$github->setUpdated();

			// any new?
			if ($events)
			{
				// show the new ones
				return View::forge("github/list_new", array(
					"events" => $events));
			}
		}

		// show full list
		$pagei = new \pagei(\pagei::PER_PAGE, 40, \pagei::ACTIVE_GET, "page");
		$events = Hendelser::getEvents($pagei);

		// show the list
		return View::forge("github/list", array(
			"events" => $events,
			"pagei"  => $pagei));
	}

	private function log_payload($event, $payload)
	{
		if (MAIN_SERVER)
		{
			$data = sprintf("%s\nevent: %s\npayload:\n%s\n\n",
				date("r"),
				$event,
				print_r($payload, true));
			file_put_contents("../github.log", $data, FILE_APPEND);
		}
	}
}
