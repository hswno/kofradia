<?php

krsort(donasjoner::$steps_single);
krsort(donasjoner::$steps_sum);

class donasjoner
{
	public static $steps_single = array(
		0 => '1 - 49 kr',
		50 => '50 - 199 kr',
		200 => '200 - 499 kr',
		500 => '500 kr eller mer'
	);
	public static $steps_sum = array(
		0 => '1 - 99 kr',
		100 => '100 - 499 kr',
		500 => '500 - 999 kr',
		1000 => '1 000 - 1 999 kr',
		2000 => '2 000 - 4 999 kr',
		5000 => '5 0000 - 9 999 kr',
		10000 => '10 000 kr eller mer'
	);
	
	function step($amount, $type = "single")
	{
		$type = $type == 'single' ? 'steps_single' : 'steps_sum';
		foreach (self::$$type as $min => $step)
		{
			if ($amount >= $min)
			{
				return $step;
			}
		}
		
		throw new HSException("Ukjent mengde ($amount)");
	}
}