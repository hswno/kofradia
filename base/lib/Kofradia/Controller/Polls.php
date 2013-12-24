<?php namespace Kofradia\Controller;

use \Kofradia\Polls\Poll;
use \Kofradia\View;

class Polls extends \Kofradia\Controller {
	/**
	 * List polls
	 */
	public function action_index($page = 1)
	{
		\ess::$b->page->add_title("Avstemninger");
		
		// hent avstemningene
		$pagei = new \pagei(\pagei::PER_PAGE, 10, \pagei::ACTIVE_GET, "side");
		if (isset($pages[1])) $pagei->__construct(\pagei::ACTIVE, intval($pages[1]));
		$polls = Poll::getPolls($pagei, \login::$user);

		return View::forge("polls/poll_list", array(
			"polls" => $polls,
			"pagei" => $pagei));
	}

	/**
	 * Registering vote
	 */
	public function action_vote()
	{
		\access::no_guest();

		if (!isset($_POST['poll']) || !is_array($_POST['poll']) || count($_POST['poll']) > 1)
		{
			\ess::$b->page->add_message("Du må velge et alternativ.", "error");
			\redirect::handle("", \redirect::ROOT);
		}

		$p_id = (int) key($_POST['poll']);
		$po_id = (int) current($_POST['poll']);

		$poll = Poll::load($p_id, \login::$user);
		if (!$poll || !$poll->isAvailable())
		{
			\ess::$b->page->add_message("Fant ikke avstemningen.", "error");
			\redirect::handle("", \redirect::ROOT);
		}

		// allerede stemt?
		if ($poll->getVote())
		{
			\ess::$b->page->add_message("Du har allerede stemt på avstemningen &laquo;".htmlspecialchars($poll->data['p_title'])."&raquo;.", "error");
			\redirect::handle("", \redirect::ROOT);
		}
		
		// finn alternativet
		$option = $poll->findOption($po_id);
		if (!$option)
		{
			\ess::$b->page->add_message("Ugyldig alternativ.", "error");
			\redirect::handle("", \redirect::ROOT);
		}
		
		if ($option->vote(\login::$user))
		{
			\ess::$b->page->add_message("Du har avgitt stemme på avstemningen &laquo;".htmlspecialchars($poll->data['p_title'])."&raquo;.");
		}
		else
		{
			\ess::$b->page->add_message("Din stemme ble ikke registrert.", "error");
		}

		// sende til forum tråden?
		if ($poll->data['p_ft_id'])
		{
			\redirect::handle("/forum/topic?id={$poll->data['p_ft_id']}", \redirect::ROOT);
		}
		
		\redirect::handle("", \redirect::ROOT);
	}

	/**
	 * Admin interface
	 */
	public function action_admin($pathinfo = null)
	{
		\ess::$b->page->add_title("Avstemninger");
		require ROOT."/crew/avstemninger.php";
	}
}