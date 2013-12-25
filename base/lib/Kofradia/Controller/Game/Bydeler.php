<?php namespace Kofradia\Controller\Game;

class Bydeler extends \Kofradia\Controller\Game {
	public function action_index()
	{
		new \page_bydeler($this->user ? $this->user->player : null);
	}
}