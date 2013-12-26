<?php namespace Kofradia\Controller\Game;

class Banken extends \Kofradia\Controller\Game {
	public $ssl = true;

	public function action_index()
	{
		$this->needUser();
		new \page_banken($this->user->player);
	}
}