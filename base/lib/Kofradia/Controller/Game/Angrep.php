<?php namespace Kofradia\Controller\Game;

class Angrip extends \Kofradia\Controller\Game {
	public function action_index()
	{
		$this->needUser();
		new \page_angrip(login::$user->player);
	}
}