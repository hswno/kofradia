<?php namespace Kofradia\Controller\Game;

class Lotto extends \Kofradia\Controller\Game {
	public function action_index()
	{
		$this->needUser();
		new \page_lotto($this->user->player);
	}
}