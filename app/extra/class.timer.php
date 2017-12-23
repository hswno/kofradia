<?php

class timer {
	public $start = 0;
	public $end = 0;
	public $time = 0;
	
	function __construct()
	{
		$this->start = microtime(true);
	}
	
	function stop()
	{
		$this->end = microtime(true);
		$this->time = $this->end - $this->start;
	}
	
	function announce($die = false, $file = "unknown", $line = 0)
	{
		if ($this->end == 0) $this->stop();
		if ($die)
		{
			throw new HSException("Mod Timer abort - File $file line $line<br /><br />Time: {$this->time}");
		}
		echo 'Time: '.$this->time;
	}
}