<?php

class pages_player
{
	/**
	 * Spilleren som viser siden
	 * @var player
	 */
	protected $up;
	
	/**
	 * Construct
	 */
	public function __construct(player $up = null)
	{
		$this->up = $up;
	}
}