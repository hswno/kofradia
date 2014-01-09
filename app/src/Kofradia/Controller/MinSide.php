<?php namespace Kofradia\Controller;

class MinSide extends \Kofradia\Controller
{
	public $createResponseObject = false;
	protected $ssl = true;

	public function action_index()
	{
		$this->needUser();

		if (isset($_GET['up_id']) && isset($_GET['u_id']))
		{
			\ess::$b->page->add_message("Ukjent forespørsel.", "error");
			\ess::$b->page->load();
		}

		\page_min_side::main();
	}
}