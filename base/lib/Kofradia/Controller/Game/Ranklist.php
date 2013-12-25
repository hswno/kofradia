<?php namespace Kofradia\Controller\Game;

class Ranklist extends \Kofradia\Controller\Game {
	public function action_index()
	{
		new \page_ranklist();
	}
}