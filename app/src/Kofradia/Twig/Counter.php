<?php namespace Kofradia\Twig;

class Counter extends \Twig_Extension {
	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('counter', array($this, 'counterFilter')),
		);
	}

	public function counterFilter($time, $redirect_to)
	{
		$rel = (string) $time;
		if ($redirect_to)
		{
			if ($redirect_to === true) $rel .= ",refresh";
			else $rel .= ",".htmlspecialchars($redirect_to);
		}
		return '<span class="counter" rel="'.$rel.'">'.\game::timespan($time, \game::TIME_FULL, 5).'</span>';
	}

	public function getName()
	{
		return 'kofradia-counter';
	}
}