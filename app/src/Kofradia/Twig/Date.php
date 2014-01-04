<?php namespace Kofradia\Twig;

class Date extends \Twig_Extension {
	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('date', array($this, 'dateFilter')),
		);
	}

	public function dateFilter($timestamp, $format = \date::FORMAT_NORMAL)
	{
		$d = \ess::$b->date->get($timestamp);
		return $d->format($this->getFormat($format));
	}

	public function getName()
	{
		return 'kofradia-date';
	}

	protected function getFormat($format)
	{
		switch ($format)
		{
			case "NORMAL":
				return \date::FORMAT_NORMAL;

			case "SEC":
				return \date::FORMAT_SEC;

			case "NOTIME":
				return \date::FORMAT_NOTIME;

			case "MONTH":
				return \date::FORMAT_MONTH;

			case "WEEKDAY":
				return \date::FORMAT_WEEKDAY;
		}

		return $format;
	}
}