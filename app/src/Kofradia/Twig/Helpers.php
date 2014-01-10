<?php namespace Kofradia\Twig;

class Helpers extends \Twig_Extension {
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('url', array($this, 'urlFragment')),
			new \Twig_SimpleFunction('static', array($this, 'staticFragment')),
		);
	}

	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('bb', array($this, 'bbFilter')),
		);
	}

	public function bbFilter($data)
	{
		return \game::bb_to_html($data);
	}

	public function urlFragment($path, $absolute = false)
	{
		$url = $absolute ? \ess::$s['path'] : \ess::$s['rpath'];
		return $url."/".ltrim($path, "/");
	}

	public function staticFragment($path)
	{
		return STATIC_LINK."/".ltrim($path, "/");
	}

	public function getName()
	{
		return 'kofradia_helpers';
	}
}