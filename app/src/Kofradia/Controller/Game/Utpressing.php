<?php namespace Kofradia\Controller\Game;

class Utpressing extends \Kofradia\Controller\Game {
	public function action_index()
	{
		$this->needUser();
		new \page_utpressing($this->user->player);
	}
}