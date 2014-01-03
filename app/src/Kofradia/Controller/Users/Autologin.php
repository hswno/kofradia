<?php namespace Kofradia\Controller\Users;

use Kofradia\Controller;
use Kofradia\Users\Autologin as AL;

class Autologin extends Controller {
	protected $ssl = true;

	/**
	 * Process the request
	 *
	 * @param string Hash to process
	 */
	public function action_index($hash)
	{
		$al = AL::getByHash($hash);
		if (!$al)
		{
			AL::logError("Hash ble ikke funnet i databasen: $hash");
			\redirect::handle("/", \redirect::ROOT);
		}
		
		$success = $al->process();
		if ($msgs = $al->getMessages())
		{
			foreach ($msgs as $msg)
			{
				if ($success)
				{
					\ess::$b->page->add_message($msg);
				}
				else
				{
					\ess::$b->page->add_message($msg, "error");
				}
			}
		}

		return $al->redirect();
	}
}