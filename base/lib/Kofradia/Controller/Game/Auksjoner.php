<?php namespace Kofradia\Controller\Game;

class Auksjoner extends \Kofradia\Controller\Game {
	public function action_index()
	{
		$this->needUser();
		new \page_auksjoner($this->user->player);
	}
}