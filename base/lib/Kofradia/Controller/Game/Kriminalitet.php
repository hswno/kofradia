<?php namespace Kofradia\Controller\Game;

class Kriminalitet extends \Kofradia\Controller\Game {
	public function action_index()
	{
		$this->needUser();
		new \page_kriminalitet($this->user->player);
	}
}